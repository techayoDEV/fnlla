<?php

declare(strict_types=1);

/*
===============================================================================
FNLLA CONFIGURATION FILE
File: config\developer_workspace.php
Copyright (c) 2026 TechAyo LTD (techayo.co.uk). Released under the MIT License.
===============================================================================

Purpose:
- Controls the lightweight developer Kanban workspace.
*/

return [
    "driver" => trim((string) env("DEVELOPER_WORKSPACE_DRIVER", "file")),
    "path" => (string) env("DEVELOPER_WORKSPACE_PATH", "framework/developer/workspace.json"),
    "private_todo_path" => (string) env("DEVELOPER_PRIVATE_TODO_PATH", "framework/developer/private-todos.json"),
    "table" => trim((string) env("DEVELOPER_WORKSPACE_TABLE", "fnlla_developer_workspace_state")),
    "notifications_state_path" => (string) env("DEVELOPER_NOTIFICATIONS_STATE_PATH", "framework/developer/notifications-state.json"),
    "notifications_table" => trim((string) env("DEVELOPER_NOTIFICATIONS_TABLE", "fnlla_developer_notifications")),
    "analytics_table" => trim((string) env("DEVELOPER_ANALYTICS_EVENTS_TABLE", "fnlla_developer_analytics_events")),
];
