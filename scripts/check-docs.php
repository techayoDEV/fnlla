<?php

declare(strict_types=1);

/** Public-document hygiene is a release guard, not a substitute for secret review. */
function fnlla_documentation_issues(string $root): array
{
    $root = rtrim(str_replace("\\", "/", $root), "/");
    $skip = [".git", "vendor", "node_modules", "storage", "dist", ".fnlla", "branding", ".idea", ".vscode", "framework"];
    $iterator = new RecursiveIteratorIterator(new RecursiveCallbackFilterIterator(
        new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
        static function (SplFileInfo $file) use ($root, $skip): bool {
            $relative = substr(str_replace("\\", "/", $file->getPathname()), strlen($root) + 1);
            // Exclude root build/dependency trees, not public runtime or docs/framework.
            return !$file->isLink() && (!$file->isDir() || !in_array($relative, $skip, true));
        }
    ));
    $patterns = [
        "workstation path" => '~(?<![A-Za-z])[A-Za-z]:[/\\\\]|/(?:Users|home)/[A-Za-z0-9_.-]+/~',
        "private workspace reference" => '~\\.fnlla-tooling|\\.fnlla-techayo|techayo\\.co\\.uk/admin~i',
        "private key" => '~-----BEGIN (?:RSA |EC |OPENSSH |DSA )?PRIVATE KEY-----~',
        "access credential" => '~\\b(?:gh[pousr]_[A-Za-z0-9]{30,}|github_pat_[A-Za-z0-9_]{30,}|sk_live_[A-Za-z0-9]{16,}|AKIA[A-Z0-9]{16})\\b~',
        "password hash" => '~\\$2[aby]\\$[0-9]{2}\\$[./A-Za-z0-9]{53}~',
    ];
    $issues = [];
    $manifestPath = $root . "/resources/project-templates/v1/export-files.json";
    $export = is_file($manifestPath) ? json_decode((string) file_get_contents($manifestPath), true, 512, JSON_THROW_ON_ERROR) : [];
    foreach ($iterator as $file) {
        $relative = substr(str_replace("\\", "/", $file->getPathname()), strlen($root) + 1);
        $extension = strtolower($file->getExtension());
        if ($extension !== "md" && !($extension === "html" && str_starts_with($relative, "docs/"))) { continue; }
        $content = (string) file_get_contents($file->getPathname());
        foreach ($patterns as $reason => $pattern) {
            if (preg_match($pattern, $content) === 1) { $issues[] = $relative . ": " . $reason; }
        }
        if ($extension !== "md") { continue; }
        preg_match_all('~\\[[^\\]\\r\\n]*\\]\\(([^\\s)]+)\\)~', $content, $links);
        foreach ($links[1] as $target) {
            $target = trim($target, "<>");
            if (preg_match('~^(?:[a-z][a-z0-9+.-]*:|/|#)~i', $target) === 1 || str_contains($target, "{{")) { continue; }
            $target = rawurldecode(explode("#", explode("?", $target, 2)[0], 2)[0]);
            // Starter README links resolve in the export, not beside the template source.
            if (str_starts_with($relative, "resources/project-templates/")
                && in_array($target, $export["files"] ?? [], true) && is_file($root . "/" . $target)) { continue; }
            if ($target !== "" && !file_exists(dirname($file->getPathname()) . "/" . $target)) {
                $issues[] = $relative . ": broken relative link";
            }
        }
    }
    sort($issues);
    return array_values(array_unique($issues));
}

if (realpath((string) ($_SERVER["SCRIPT_FILENAME"] ?? "")) === __FILE__) {
    try {
        $issues = fnlla_documentation_issues(dirname(__DIR__));
        foreach ($issues as $issue) { fwrite(STDERR, $issue . PHP_EOL); }
        fwrite(STDOUT, $issues === [] ? "Documentation hygiene passed.\n" : "Documentation hygiene failed.\n");
        exit($issues === [] ? 0 : 1);
    } catch (Throwable $error) {
        fwrite(STDERR, "Cannot validate documentation: " . $error->getMessage() . PHP_EOL);
        exit(1);
    }
}
