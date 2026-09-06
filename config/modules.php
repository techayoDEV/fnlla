<?php

declare(strict_types=1);

// Preserve an earlier installation's opt-in marker; fresh full exports enable all modules.
$defaultEnabled = !is_file(base_path(".fnlla/modules-opt-in"));
return [
    "workspace" => (bool) env("FNLLA_MODULE_WORKSPACE", $defaultEnabled),
    "analytics" => (bool) env("FNLLA_MODULE_ANALYTICS", $defaultEnabled),
    "heatmap" => (bool) env("FNLLA_MODULE_HEATMAP", $defaultEnabled),
    "customer_portal" => (bool) env("FNLLA_MODULE_CUSTOMER_PORTAL", $defaultEnabled),
];
