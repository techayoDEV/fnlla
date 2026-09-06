<?php

declare(strict_types=1);

namespace Fnlla\Php\Controllers;

use Fnlla\Php\Http\Request;
use Fnlla\Php\Http\Response;
use Fnlla\Php\Maintenance\DeveloperAccessManager;
use Fnlla\Php\Maintenance\MaintenanceAccessManager;
use Fnlla\Php\Observability\DebugToolbar;
use Fnlla\Php\Observability\RequestHistory;

final class DeveloperDebugController extends DeveloperPanelController
{
    public function show(Request $request, DeveloperAccessManager $access, MaintenanceAccessManager $maintenance): Response
    {
        if (!$access->can("operations.view")) {
            return Response::text("Forbidden", 403);
        }
        $toolbar = new DebugToolbar();
        return $this->renderDeveloperPanel($access, $maintenance, "developer/debug", "Debug", "debug", [
            "debugAvailable" => $toolbar->available(), "debugEnabled" => $toolbar->enabled(),
            "canManageDebug" => $access->can("panel.settings.write"),
            "historyEnabled" => (new RequestHistory())->enabled(),
            "historyEntries" => (new RequestHistory())->entries(),
        ]);
    }

    public function save(Request $request, DeveloperAccessManager $access): Response
    {
        if (!$access->can("operations.view") || !$access->can("panel.settings.write")) {
            return Response::text("Forbidden", 403);
        }
        if (!(new DebugToolbar())->available() && $request->input("enabled") === "1") {
            return Response::text("Debug toolbar is unavailable in this environment.", 403);
        }
        (new DebugToolbar())->setEnabled($request->input("enabled") === "1", (string) ($access->currentDeveloper()["email"] ?? ""));
        if ((new DebugToolbar())->authorized()) {
            (new RequestHistory())->configure($request->input("history_enabled") === "1", $request->input("clear_history") === "1");
        } elseif ($request->input("history_enabled") === "1") {
            return Response::text("Request history is unavailable in this environment.", 403);
        }
        return $this->redirect(route("developer.panel.debug"));
    }
}
