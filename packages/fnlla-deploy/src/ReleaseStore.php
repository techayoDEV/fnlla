<?php

declare(strict_types=1);

namespace Fnlla\Deploy;

use RuntimeException;

/** Immutable code releases plus a single atomically replaced activation record. */
final class ReleaseStore
{
    public function __construct(private string $root)
    {
        if (!is_dir($root) && !mkdir($root, 0700, true) && !is_dir($root)) {
            throw new RuntimeException("Cannot create deployment root.");
        }
        $this->root = (string) realpath($root);
        foreach (["releases", "shared", "shared/storage", "shared/public", "shared/public/uploads"] as $path) {
            $directory = $this->root . "/" . $path;
            if (is_link($directory)) { throw new RuntimeException("Deployment directories cannot be links."); }
            if (!is_dir($directory) && !mkdir($directory, 0700) && !is_dir($directory)) {
                throw new RuntimeException("Cannot create deployment directory.");
            }
        }
    }

    public function stage(string $source, string $id): string
    {
        $target = $this->release($id);
        $source = realpath($source) ?: throw new RuntimeException("Artifact directory does not exist.");
        $a = strtolower(str_replace("\\", "/", $source) . "/");
        $b = strtolower(str_replace("\\", "/", $this->root) . "/");
        if (str_starts_with($a, $b) || str_starts_with($b, $a)) {
            throw new RuntimeException("Artifact and deployment trees must not overlap.");
        }
        return $this->locked(function () use ($source, $target, $id): string {
            if (file_exists($target) || !mkdir($target, 0700)) {
                throw new RuntimeException("Release ID already exists or cannot be created.");
            }
            $files = $this->inventory($source);
            if (!isset($files["public/index.php"])) { throw new RuntimeException("Artifact has no public entrypoint."); }
            foreach ($files as $path => $hash) {
                $destination = $target . "/" . $path;
                if (!is_dir(dirname($destination)) && !mkdir(dirname($destination), 0700, true)) {
                    throw new RuntimeException("Cannot stage release directory.");
                }
                if (!copy($source . "/" . $path, $destination) || hash_file("sha256", $destination) !== $hash) {
                    throw new RuntimeException("Artifact changed during staging: " . $path);
                }
            }
            $this->write($target . "/.fnlla-release.json", ["schema" => "fnlla.release.v1", "id" => $id, "files" => $files]);
            return $target;
        });
    }

    public function activate(string $id, ?string $expectedCurrent, callable $validate): array
    {
        return $this->locked(function () use ($id, $expectedCurrent, $validate): array {
            $state = $this->state();
            if (($state["current"] ?? null) !== $expectedCurrent) {
                throw new RuntimeException("Active release changed; inspect status before activating again.");
            }
            if ($id === $expectedCurrent) { throw new RuntimeException("Release is already active."); }
            $path = $this->release($id);
            $this->verify($id);
            if ($validate($path) !== true) { throw new RuntimeException("Release validation did not pass."); }
            $this->verify($id);
            $state = ["schema" => "fnlla.deployment.v1", "current" => $id, "previous" => $expectedCurrent,
                "revision" => (int) ($state["revision"] ?? 0) + 1, "activated_at" => gmdate(DATE_ATOM)];
            $this->write($this->root . "/current.json", $state);
            return $state;
        });
    }

    public function rollback(string $expectedCurrent, callable $validate): array
    {
        $previous = $this->state()["previous"] ?? null;
        if (!is_string($previous)) { throw new RuntimeException("No previous release is recorded."); }
        return $this->activate($previous, $expectedCurrent, $validate);
    }

    public function state(): array
    {
        $path = $this->root . "/current.json";
        if (!file_exists($path)) { return []; }
        $state = $this->read($path);
        if (($state["schema"] ?? "") !== "fnlla.deployment.v1" || !is_string($state["current"] ?? null)) {
            throw new RuntimeException("Invalid deployment state.");
        }
        $this->release($state["current"]);
        return $state;
    }

    public function release(string $id): string
    {
        if (preg_match('/^[A-Za-z0-9][A-Za-z0-9_-]{0,63}$/D', $id) !== 1) {
            throw new RuntimeException("Invalid release ID.");
        }
        $path = $this->root . "/releases/" . $id;
        if (is_link($path)) { throw new RuntimeException("Release cannot be a link."); }
        return $path;
    }

    public function verify(string $id): void
    {
        $path = $this->release($id);
        $manifest = $this->read($path . "/.fnlla-release.json");
        if (($manifest["schema"] ?? "") !== "fnlla.release.v1" || ($manifest["id"] ?? "") !== $id
            || ($manifest["files"] ?? null) !== $this->inventory($path)) {
            throw new RuntimeException("Release inventory does not match its sealed artifact.");
        }
    }

    private function inventory(string $root): array
    {
        $files = [];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveCallbackFilterIterator(
            new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS),
            static function (\SplFileInfo $file) use ($root): bool {
                if ($file->isLink()) { throw new RuntimeException("Release artifacts cannot contain symbolic links."); }
                $path = str_replace("\\", "/", substr($file->getPathname(), strlen($root) + 1));
                return !in_array($path, [".git", "storage", "public/uploads", ".fnlla-release.json"], true)
                    && (!str_starts_with($path, ".env") || in_array($path, [".env.example", ".env.full.example"], true));
            }
        ));
        foreach ($iterator as $file) {
            if (!$file->isFile()) { throw new RuntimeException("Artifact contains a non-regular file."); }
            $path = str_replace("\\", "/", substr($file->getPathname(), strlen($root) + 1));
            $hash = hash_file("sha256", $file->getPathname());
            if ($hash === false) { throw new RuntimeException("Cannot hash release file."); }
            $files[$path] = $hash;
        }
        ksort($files);
        return $files;
    }

    private function read(string $path): array
    {
        if (is_link($path) || !is_file($path)) { throw new RuntimeException("Deployment record is missing or unsafe."); }
        $value = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($value)) { throw new RuntimeException("Invalid deployment record."); }
        return $value;
    }

    private function write(string $path, array $value): void
    {
        if (is_link($path)) { throw new RuntimeException("Deployment record cannot be a link."); }
        $json = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $temporary = tempnam(dirname($path), ".activation-");
        if ($temporary === false) { throw new RuntimeException("Cannot stage activation record."); }
        try {
            if (file_put_contents($temporary, $json) !== strlen($json) || !rename($temporary, $path)) {
                throw new RuntimeException("Cannot atomically activate release.");
            }
        } finally { if (is_file($temporary)) { unlink($temporary); } }
    }

    private function locked(callable $operation): mixed
    {
        $path = $this->root . "/deploy.lock";
        if (is_link($path)) { throw new RuntimeException("Deployment lock cannot be a link."); }
        $lock = fopen($path, "c+b");
        if ($lock === false) { throw new RuntimeException("Cannot open deployment lock."); }
        try {
            if (!flock($lock, LOCK_EX | LOCK_NB)) { throw new RuntimeException("Another deployment is running."); }
            return $operation();
        } finally { flock($lock, LOCK_UN); fclose($lock); }
    }
}
