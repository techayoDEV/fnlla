<?php

declare(strict_types=1);

/*
===============================================================================
FNLLA DATA FACTORY
File: database\factories\UserFactory.php
Copyright (c) 2026 TechAyo LTD (techayo.co.uk). Released under the MIT License.
===============================================================================

FNLLA is produced, maintained and distributed by TechAyo LTD
(techayo.co.uk). This repository is the authoritative maintainer workspace for
the FNLLA framework released under the MIT License and its related delivery scripts, tests,
templates and release metadata.

Purpose:
- Provides repeatable data generation for tests, seeding and local framework validation.
*/

namespace Database\Factories;

use Fnlla\Php\Database\Factories\Factory;
use Fnlla\Php\Hashing\Hasher;

final class UserFactory extends Factory
{
    protected function table(): string
    {
        return "users";
    }

    protected function definition(): array
    {
        $unique = substr(bin2hex(random_bytes(8)), 0, 12);
        $timestamp = gmdate("Y-m-d H:i:s");

        return [
            "name" => "Example User",
            "email" => "user-" . $unique . "@example.com",
            // The default has no known login credential. Authentication tests supply their own hash.
            "password" => $this->container->make(Hasher::class)->make(bin2hex(random_bytes(32))),
            "role" => "user",
            "created_at" => $timestamp,
            "updated_at" => $timestamp,
        ];
    }
}
