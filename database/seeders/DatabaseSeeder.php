<?php

declare(strict_types=1);

/*
===============================================================================
FNLLA PHP DATABASE SEEDER
File: database\seeders\DatabaseSeeder.php
Copyright (c) 2026 TechAyo LTD (techayo.co.uk). Released under the MIT License.
===============================================================================

FNLLA PHP is produced, maintained and distributed by TechAyo LTD
(techayo.co.uk). This repository is the authoritative maintainer workspace for
the FNLLA PHP framework released under the MIT License and its related delivery scripts, tests,
templates and release metadata.

Purpose:
- Provides seed data for framework demos, local setup or delivery bootstrapping.
*/

namespace Database\Seeders;

use Database\Factories\UserFactory;
use Fnlla\Php\Database\Seeders\Seeder;

final class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        if (!$this->database->table("users")->where("email", "admin@example.com")->exists()) {
            $this->container->make(UserFactory::class)->create([
                "name" => "Admin User",
                "email" => "admin@example.com",
                "password" => app(\Fnlla\Php\Hashing\Hasher::class)->make("password123"),
                "role" => "admin",
            ]);
        }
    }
}
