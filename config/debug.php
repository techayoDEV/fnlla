<?php

declare(strict_types=1);

return [
    "toolbar" => (bool) env("DEBUG_TOOLBAR", false),
    "history" => [
        "enabled" => (bool) env("DEBUG_REQUEST_HISTORY", false),
        "max_entries" => 200,
        "retention_seconds" => 3600,
    ],
    "runtime_issues" => [
        "enabled" => (bool) env("DEBUG_RUNTIME_ISSUES", true),
        "max_items" => 100,
    ],
];
