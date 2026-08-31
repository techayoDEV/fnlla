<?php

declare(strict_types=1);

/*
===============================================================================
FNLLA SUPPORT SOURCE
File: src\Support\RecentFileLines.php
Copyright (c) 2026 TechAyo LTD (techayo.co.uk). Released under the MIT License.
===============================================================================

Purpose:
- Reads the newest non-empty lines from append-only files without loading the
  whole file into memory.
*/

namespace Fnlla\Php\Support;

final class RecentFileLines
{
    public static function read(string $path, int $limit): array
    {
        if (!is_file($path)) {
            return [];
        }

        $handle = fopen($path, "rb");

        if (!is_resource($handle)) {
            return [];
        }

        $lines = [];
        $buffer = "";
        $position = filesize($path);
        $limit = max(1, $limit);

        try {
            while ($position > 0 && count($lines) < $limit) {
                $read = min(8192, $position);
                $position -= $read;
                fseek($handle, $position);
                $chunk = fread($handle, $read);
                $buffer = (is_string($chunk) ? $chunk : "") . $buffer;
                $parts = preg_split('/\R/', $buffer) ?: [];

                if ($position > 0) {
                    $buffer = (string) array_shift($parts);
                } else {
                    $buffer = "";
                }

                for ($index = count($parts) - 1; $index >= 0 && count($lines) < $limit; $index--) {
                    $line = trim((string) $parts[$index]);

                    if ($line !== "") {
                        $lines[] = $line;
                    }
                }
            }
        } finally {
            fclose($handle);
        }

        return $lines;
    }
}
