<?php

declare(strict_types=1);

/*
===============================================================================
FNLLA AI SOURCE
File: src\Ai\RuntimeAiProviderInterface.php
Copyright (c) 2026 TechAyo LTD (techayo.co.uk). Released under the MIT License.
===============================================================================

Purpose:
- Defines the small provider contract used by runtime AI adapters.
*/

namespace Fnlla\Php\Ai;

interface RuntimeAiProviderInterface
{
    public function answer(string $input, array $context = []): array;

    public function status(): array;
}
