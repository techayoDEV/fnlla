<?php

declare(strict_types=1);

/*
===============================================================================
FNLLA DATABASE MIGRATION
File: database\migrations\20260829120000_create_developer_panel_storage_tables.php
Copyright (c) 2026 TechAyo LTD (techayo.co.uk). Released under the MIT License.
===============================================================================

Purpose:
- Creates the optional database-backed Developer Panel storage tables.
*/

use Fnlla\Php\Database\Migrations\Migration;
use Fnlla\Php\Support\DeveloperPanelStorageInstaller;

return new class(app(\Fnlla\Php\Database\DatabaseManager::class)) extends Migration {
    public function up(): void
    {
        foreach ((new DeveloperPanelStorageInstaller())->statements() as $statement) {
            $this->statement($statement);
        }
    }

    public function down(): void
    {
        foreach (array_reverse((new DeveloperPanelStorageInstaller())->tables()) as $table) {
            $safe = preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', (string) $table) === 1 ? (string) $table : "";

            if ($safe !== "") {
                $this->statement("DROP TABLE IF EXISTS `" . str_replace("`", "``", $safe) . "`");
            }
        }
    }
};
