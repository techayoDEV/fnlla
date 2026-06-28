<?php

declare(strict_types=1);

/*
===============================================================================
FNLLA PHP DATABASE MIGRATION
File: database\migrations\20260627180000_create_users_table.php
Copyright (c) 2026 TechAyo LTD (techayo.co.uk). Released under the MIT License.
===============================================================================

FNLLA PHP is produced, maintained and distributed by TechAyo LTD
(techayo.co.uk). This repository is the authoritative maintainer workspace for
the FNLLA PHP framework released under the MIT License and its related delivery scripts, tests,
templates and release metadata.

Purpose:
- Defines a schema change for the maintained MySQL delivery contract.
*/

use Fnlla\Php\Database\Migrations\Migration;

return new class(app(\Fnlla\Php\Database\DatabaseManager::class)) extends Migration {
    public function up(): void
    {
        $this->statement(
            "CREATE TABLE IF NOT EXISTS users (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(255) NOT NULL,
                email VARCHAR(255) NOT NULL UNIQUE,
                password VARCHAR(255) NOT NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL
            )"
        );
    }

    public function down(): void
    {
        $this->statement("DROP TABLE IF EXISTS users");
    }
};
