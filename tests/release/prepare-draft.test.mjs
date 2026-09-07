import assert from 'node:assert/strict';
import test from 'node:test';
import { createHash } from 'node:crypto';
import { mkdtempSync, mkdirSync, writeFileSync, readFileSync, rmSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { approvedRun, assertReleaseTarget, prepareDraft, releaseSummary } from '../../scripts/release/prepare-draft.mjs';

const sha = 'a'.repeat(40);
const run = { id: 1, head_sha: sha, event: 'push', head_branch: 'main',
    head_repository: { full_name: 'techayoDEV/fnlla' }, path: '.github/workflows/quality.yml',
    run_number: 1, run_attempt: 1, status: 'completed', conclusion: 'success' };

test('accepts the exact workflow, repository and main commit', () => {
    assert.equal(approvedRun([run], sha, 'quality.yml'), run);
});
for (const change of [{ head_sha: 'b'.repeat(40) }, { event: 'pull_request' }, { head_branch: 'feature' },
    { head_repository: { full_name: 'fork/fnlla' } }, { path: '.github/workflows/other.yml' },
    { status: 'in_progress' }, { conclusion: 'failure' }, { conclusion: 'cancelled' }, { conclusion: 'skipped' }]) {
    test(`rejects unaccepted evidence: ${JSON.stringify(change)}`, () => {
        assert.throws(() => approvedRun([{ ...run, ...change }], sha, 'quality.yml'));
    });
}
test('latest failure or active rerun cannot be hidden by an older success', () => {
    assert.throws(() => approvedRun([run, { ...run, run_number: 2, conclusion: 'failure' }], sha, 'quality.yml'));
    assert.throws(() => approvedRun([run, { ...run, run_attempt: 2, status: 'queued' }], sha, 'quality.yml'));
    assert.throws(() => approvedRun([], sha, 'quality.yml'));
});
test('only matching stable tags with no existing release are accepted', () => {
    assertReleaseTarget('v2.2.0', '2.2.0', []);
    for (const tag of ['main', 'v2.2.0-beta.1', 'v02.2.0', 'v2.1.3', 'v2.2.0;echo bad']) {
        assert.throws(() => assertReleaseTarget(tag, '2.2.0', []));
    }
    for (const draft of [true, false]) {
        assert.throws(() => assertReleaseTarget('v2.2.0', '2.2.0', [{ tag_name: 'v2.2.0', draft }]));
    }
});

test('release notes contain only the selected version summary', () => {
    const input = '## 2.2.0\r\n\r\n### Release Summary\r\nPublic notes.\r\n#### Install\r\nCommands.\r\n### Release Acceptance\r\nDetails.\r\n## 2.1.3\r\nOld notes.';
    assert.equal(releaseSummary(input, '2.2.0'), 'Public notes.\n#### Install\nCommands.');
    assert.throws(() => releaseSummary(input, '2.2.1'));
    assert.throws(() => releaseSummary('## 2.2.0\nNo summary.', '2.2.0'));
    assert.throws(() => releaseSummary('## 2.2.0\n### Release Summary\n\n### Details', '2.2.0'));
});

for (const scenario of ['accepted', 'wrong-zip', 'wrong-metadata', 'expired', 'rerun', 'moved-tag', 'existing', 'api-error']) {
    test(`draft orchestration fails closed: ${scenario}`, () => {
        const cwd = process.cwd();
        const root = mkdtempSync(join(tmpdir(), 'fnlla-release-policy-test-'));
        let download;
        let published = false;
        let checks = 0;
        const hash = (value) => createHash('sha256').update(value).digest('hex');
        try {
            process.chdir(root);
            writeFileSync('VERSION', '2.2.0\n');
            writeFileSync('CHANGELOG.md', '## 2.2.0\n\n### Release Summary\nReviewed changes.\n');
            const fake = (exe, args) => {
                if (exe === 'git') {
                    if (args[0] === 'status') return '';
                    if (args[0] === 'rev-parse') return sha;
                    if (args[0] === 'ls-remote') return `${scenario === 'moved-tag' ? 'b'.repeat(40) : sha}\trefs/tags/v2.2.0`;
                    if (args[0] === 'archive') return Buffer.from('accepted ZIP bytes');
                }
                if (exe === 'gh' && args[0] === 'api') {
                    if (scenario === 'api-error') throw new Error('API unavailable');
                    if (args[1].includes('/releases?')) return JSON.stringify([scenario === 'existing' ? [{ tag_name: 'v2.2.0' }] : []]);
                    if (args[1].includes('/workflows/')) {
                        const workflow = args[1].split('/workflows/')[1].split('/')[0];
                        checks++;
                        return JSON.stringify({ workflow_runs: [{ ...run, path: `.github/workflows/${workflow}`,
                            run_attempt: scenario === 'rerun' && checks > 3 ? 2 : 1 }] });
                    }
                    if (args[1].includes('/artifacts?')) return JSON.stringify({ artifacts: [{ id: 123,
                        name: 'accepted-source-archive', expired: scenario === 'expired' }] });
                }
                if (exe === 'gh' && args[0] === 'run' && args[1] === 'download') {
                    download = args.at(-1);
                    writeFileSync(join(download, 'fnlla-source.zip'), scenario === 'wrong-zip' ? 'other bytes' : 'accepted ZIP bytes');
                    const metadata = join(download, 'fnlla-source/dist/release');
                    mkdirSync(metadata, { recursive: true });
                    writeFileSync(join(metadata, 'fnlla-sbom.cdx.json'), '{}');
                    writeFileSync(join(metadata, 'SHA256SUMS'), 'source checksums');
                    writeFileSync(join(metadata, 'fnlla-release-manifest.json'), JSON.stringify({ schema: 'fnlla.release_manifest.v1',
                        version: scenario === 'wrong-metadata' ? '2.1.3' : '2.2.0',
                        artifacts: { sbom: { sha256: hash('{}') }, checksums: { sha256: hash('source checksums') } } }));
                    return '';
                }
                if (exe === 'gh' && args[0] === 'release' && args[1] === 'create') {
                    assert.ok(args.includes('--draft'));
                    assert.ok(args.includes('--verify-tag'));
                    assert.ok(!args.includes('--clobber'));
                    assert.equal(readFileSync(join(download, 'fnlla-source.zip'), 'utf8'), 'accepted ZIP bytes');
                    assert.match(readFileSync(join(download, 'fnlla-downloads.sha256'), 'utf8'), /fnlla-source\.zip/);
                    assert.equal(JSON.parse(readFileSync(join(download, 'fnlla-release-acceptance.json'))).commit, sha);
                    published = true;
                    return '';
                }
                throw new Error(`Unexpected command: ${exe} ${args[0]}`);
            };
            if (scenario === 'accepted') prepareDraft(fake, 'v2.2.0');
            else assert.throws(() => prepareDraft(fake, 'v2.2.0'));
            assert.equal(published, scenario === 'accepted');
        } finally {
            process.chdir(cwd);
            rmSync(root, { recursive: true });
            if (download) rmSync(download, { recursive: true });
        }
    });
}
