<?php

declare(strict_types=1);

namespace Fnlla\Php\Controllers;

use Fnlla\Php\Http\Request;
use Fnlla\Php\Http\Response;
use Fnlla\Php\Maintenance\DeveloperAccessManager;
use Fnlla\Php\Maintenance\MaintenanceAccessManager;
use Fnlla\Php\Support\TechnicalDebtRegistry;
use Fnlla\Php\Support\TechnicalDebtReportBuilder;

final class DeveloperDebtController extends DeveloperPanelController
{
    public function show(Request $request, DeveloperAccessManager $access, MaintenanceAccessManager $maintenance): Response
    {
        if (!$access->can("operations.view")) {
            return Response::text("Forbidden", 403);
        }
        return $this->renderDeveloperPanel($access, $maintenance, "developer/technical-debt", "Technical debt", "technical-debt", [
            "debtState" => (new TechnicalDebtRegistry())->state(), "canManageDebt" => $access->can("workspace.write"),
            "debtStatusFilter" => $request->query("status", ""),
        ]);
    }

    public function save(Request $request, DeveloperAccessManager $access): Response
    {
        if (!$access->can("operations.view") || !$access->can("workspace.write")) {
            return Response::text("Forbidden", 403);
        }
        $registry = new TechnicalDebtRegistry();
        $revision = (int) $request->input("revision", -1);
        $actor = (string) ($access->currentDeveloper()["email"] ?? "");
        try {
            if ($request->input("action") === "scan") {
                $registry->synchronize((new TechnicalDebtReportBuilder())->markerFiles(), $revision, $actor);
            } else {
                $input = [];
                foreach (["id", "title", "status", "priority", "owner", "notes", "due_date", "accepted_until", "issue_ref", "adr_ref", "evidence_ref"] as $key) {
                    $input[$key] = $request->input($key, "");
                }
                $registry->save($input, $revision, $actor);
            }
        } catch (\InvalidArgumentException $exception) {
            return Response::text($exception->getMessage(), 422);
        } catch (\RuntimeException $exception) {
            return Response::text($exception->getMessage(), 409);
        }
        return $this->redirect(route("developer.panel.technical_debt"));
    }
}
