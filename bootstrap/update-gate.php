<?php

declare(strict_types=1);

// Deliberately independent of autoload and framework classes that may be replaced.
return static function (string $root): array {
    $directory = rtrim($root, "/\\") . "/.fnlla/update-transaction";
    $lock = null;
    if (is_file($directory . "/lock")) {
        $lock = @fopen($directory . "/lock", "rb");
        if ($lock === false || !flock($lock, LOCK_SH | LOCK_NB)) {
            if (is_resource($lock)) { fclose($lock); }
            return ["ready" => false, "lock" => null];
        }
    }
    if (is_file($directory . "/journal.json")) {
        if (is_resource($lock)) { fclose($lock); }
        return ["ready" => false, "lock" => null];
    }
    return ["ready" => true, "lock" => $lock];
};
