import { spawnSync } from 'node:child_process';
import { createHash } from 'node:crypto';
import { mkdtempSync, readFileSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join, resolve } from 'node:path';
import { pathToFileURL } from 'node:url';

export const workflows = ['quality.yml', 'fnlla-hardening.yml', 'fnlla-release-gate.yml'];
const repository = 'techayoDEV/fnlla';
const digest = (bytes) => createHash('sha256').update(bytes).digest('hex');

export function approvedRun(runs, sha, workflow, repo = repository) {
    // A previous success must not hide a newer failing or still-running attempt.
    const run = runs.filter((item) => item.head_sha === sha && item.event === 'push'
        && item.head_branch === 'main' && item.head_repository?.full_name?.toLowerCase() === repo.toLowerCase()
        && item.path === `.github/workflows/${workflow}`)
        .sort((a, b) => b.run_number - a.run_number || b.run_attempt - a.run_attempt)[0];
    if (!run || run.status !== 'completed' || run.conclusion !== 'success') {
        throw new Error(`${workflow}: latest main push for ${sha} has not passed.`);
    }
    return run;
}

export function assertReleaseTarget(tag, version, existing) {
    if (!/^v(0|[1-9]\d*)\.(0|[1-9]\d*)\.(0|[1-9]\d*)$/.test(tag) || tag !== `v${version}`) {
        throw new Error('An existing stable tag matching VERSION is required.');
    }
    if (existing.some((item) => item.tag_name === tag)) {
        throw new Error('This tag already has a draft or public release; no assets will be replaced.');
    }
}

export function releaseSummary(changelog, version) {
    const lines = changelog.split(/\r?\n/);
    const start = lines.indexOf(`## ${version}`);
    if (start < 0) throw new Error('Version-specific release notes are missing.');
    const next = lines.findIndex((line, i) => i > start && line.startsWith('## '));
    const section = lines.slice(start + 1, next < 0 ? undefined : next);
    const summary = section.indexOf('### Release Summary');
    if (summary < 0) throw new Error('A reviewed release summary is required.');
    const end = section.findIndex((line, i) => i > summary && line.startsWith('### '));
    const text = section.slice(summary + 1, end < 0 ? undefined : end).join('\n').trim();
    if (!text) throw new Error('The release summary cannot be empty.');
    return text;
}

function execute(executable, args, binary = false) {
    const result = spawnSync(executable, args, { encoding: binary ? undefined : 'utf8',
        maxBuffer: 32 * 1024 * 1024, timeout: 120000, windowsHide: true });
    if (result.error || result.status !== 0) {
        throw new Error(`${executable} failed: ${result.error?.message || String(result.stderr).trim()}`);
    }
    return binary ? result.stdout : result.stdout.trim();
}

export function prepareDraft(command = execute, tag = process.env.RELEASE_TAG || '') {
    const api = (path) => JSON.parse(command('gh', ['api', path]));
    const releases = () => JSON.parse(command('gh', ['api', `repos/${repository}/releases?per_page=100`,
        '--paginate', '--slurp'])).flat();
    const version = readFileSync('VERSION', 'utf8').split(/\r?\n/)[0].trim();
    assertReleaseTarget(tag, version, releases());
    if (command('git', ['status', '--porcelain']) !== '') {
        throw new Error('Draft preparation requires a clean checkout.');
    }
    const sha = command('git', ['rev-parse', 'HEAD']);
    if (command('git', ['rev-parse', `refs/tags/${tag}^{commit}`]) !== sha) {
        throw new Error('Checkout does not match the tag.');
    }
    const checkRemoteTag = () => {
        const refs = command('git', ['ls-remote', `https://github.com/${repository}.git`,
            `refs/tags/${tag}`, `refs/tags/${tag}^{}`]).split(/\r?\n/).map((line) => line.split(/\s+/));
        const remote = refs.find((item) => item[1]?.endsWith('^{}')) || refs[0];
        if (remote?.[0] !== sha) throw new Error('Remote tag does not match the accepted commit.');
    };
    checkRemoteTag();
    const checkRuns = () => workflows.map((workflow) => approvedRun(api(
        `repos/${repository}/actions/workflows/${workflow}/runs?event=push&branch=main&head_sha=${sha}&per_page=100`
    ).workflow_runs, sha, workflow));
    const runs = checkRuns();
    const artifacts = api(`repos/${repository}/actions/runs/${runs[0].id}/artifacts?per_page=100`).artifacts;
    const artifact = artifacts.filter((item) => item.name === 'accepted-source-archive' && !item.expired);
    if (artifact.length !== 1) throw new Error('Exactly one retained accepted source archive is required.');

    const work = mkdtempSync(join(tmpdir(), 'fnlla-release-draft-'));
    command('gh', ['run', 'download', String(runs[0].id), '--repo', repository,
        '--name', 'accepted-source-archive', '--dir', work]);
    const source = join(work, 'fnlla-source.zip');
    const hash = digest(readFileSync(source));
    // ZIP encoding varies across Git/zlib builds. Compare every entry, keeping the original CI bytes.
    const expected = join(work, 'expected-source.zip');
    command('git', ['archive', '--format=zip', `--output=${expected}`, sha]);
    command('python', ['scripts/release/verify-archive.py', expected, source]);
    const metadata = join(work, 'fnlla-source', 'dist', 'release');
    const names = ['fnlla-sbom.cdx.json', 'SHA256SUMS', 'fnlla-release-manifest.json'];
    const files = [source, ...names.map((name) => join(metadata, name))];
    for (const file of files) readFileSync(file);
    const manifest = JSON.parse(readFileSync(files[3], 'utf8'));
    if (manifest.schema !== 'fnlla.release_manifest.v1' || manifest.version !== version
        || manifest.artifacts?.sbom?.sha256 !== digest(readFileSync(files[1]))
        || manifest.artifacts?.checksums?.sha256 !== digest(readFileSync(files[2]))) {
        throw new Error('Supply-chain metadata does not match the accepted edition and files.');
    }
    const receipt = join(work, 'fnlla-release-acceptance.json');
    writeFileSync(receipt, JSON.stringify({ schema: 'fnlla.release_acceptance.v1', tag, commit: sha,
        archive_sha256: hash, artifact_id: artifact[0].id,
        workflows: runs.map((run) => ({ id: run.id, attempt: run.run_attempt, url: run.html_url })),
        status: 'ci_accepted; publication status is recorded in GitHub Releases' }, null, 2) + '\n', { flag: 'wx' });
    files.push(receipt);
    const checksums = join(work, 'fnlla-downloads.sha256');
    writeFileSync(checksums, files.map((file) => `${digest(readFileSync(file))}  ${file.split(/[\\/]/).pop()}`).join('\n') + '\n', { flag: 'wx' });
    files.push(checksums);
    const notes = join(work, 'release-notes.md');
    const changelog = readFileSync('CHANGELOG.md', 'utf8');
    const changes = releaseSummary(changelog, version);
    writeFileSync(notes, `# FNLLA ${version}\n\n${changes}\n\nAccepted commit: \`${sha}\`.\n`
        + 'Publication requires manual download, installation and approval. Checksums are not a digital signature.\n');
    assertReleaseTarget(tag, version, releases());
    checkRemoteTag();
    const latest = checkRuns();
    if (latest.some((run, i) => run.id !== runs[i].id || run.run_attempt !== runs[i].run_attempt)) {
        throw new Error('CI changed during preparation; retry against the completed runs.');
    }
    command('gh', ['release', 'create', tag, ...files, '--repo', repository, '--verify-tag',
        '--draft', '--title', `FNLLA ${version}`, '--notes-file', notes]);
    console.log(`Draft created for ${tag} (${sha}). No public release was published.`);
}

if (process.argv[1] && import.meta.url === pathToFileURL(resolve(process.argv[1])).href) {
    try { prepareDraft(); } catch (error) { console.error(error.message); process.exitCode = 1; }
}
