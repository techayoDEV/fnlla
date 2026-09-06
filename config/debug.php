<?php

declare(strict_types=1);

return [
    "toolbar" => (bool) env("DEBUG_TOOLBAR", false),
    "history" => [
        "enabled" => (bool) env("DEBUG_REQUEST_HISTORY", false),
        "max_entries" => 200,
        "retention_seconds" => 3600,
    ],
];
