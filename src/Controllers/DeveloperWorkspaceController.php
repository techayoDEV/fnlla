<?php

declare(strict_types=1);

namespace Fnlla\Php\Controllers;

use Fnlla\Php\Http\Request;
use Fnlla\Php\Http\Response;
use Fnlla\Php\Http\UploadedFile;
use Fnlla\Php\Mail\Mailer;
use Fnlla\Php\Maintenance\CustomerAccessManager;
use Fnlla\Php\Maintenance\DeveloperActivityLog;
use Fnlla\Php\Maintenance\DeveloperAccessManager;
use Fnlla\Php\Maintenance\DeveloperControlManager;
use Fnlla\Php\Maintenance\MaintenanceAccessManager;
use Fnlla\Php\Support\DeveloperAnalyticsReport;
use Fnlla\Php\Support\DeveloperHeatmapReport;
use Fnlla\Php\Support\DeveloperNotificationCenter;
use Fnlla\Php\Support\DeveloperOperationsReport;
use Fnlla\Php\Support\DeveloperWorkspaceBoard;
use Fnlla\Php\Support\EnvironmentFileManager;
use Fnlla\Php\Support\FrameworkIdentity;
use Fnlla\Php\Support\Logger;
use Fnlla\Php\Support\ProjectLeadership;
use Fnlla\Php\Validation\ValidationException;

final class DeveloperWorkspaceController extends DeveloperPanelController
{
    public function workspace(Request $request, DeveloperAccessManager $developerAccess, MaintenanceAccessManager $maintenanceAccess, DeveloperWorkspaceBoard $workspace): Response
    {
        return $this->renderDeveloperPanel(
            $developerAccess,
            $maintenanceAccess,
            "developer/workspace",
            "Project Kanban",
            "workspace",
            [
                "workspaceBoard" => $workspace->state($developerAccess->currentDeveloper()),
            ]
        );
    }

    public function createWorkspaceTask(Request $request, DeveloperAccessManager $developerAccess, DeveloperWorkspaceBoard $workspace): Response
    {
        if (!$this->ensureDeveloperCapability($developerAccess, "workspace.write")) {
            return $this->redirect(route("developer.panel.workspace"));
        }

        try {
            $payload = $this->workspaceTaskPayload($request, $developerAccess->currentDeveloper());
            $payload = $this->withWorkspaceAttachmentFile($request, $payload, $developerAccess->currentDeveloper());
        } catch (\RuntimeException $exception) {
            flash_set("status", [
                "variant" => "warning",
                "title" => "Attachment upload failed",
                "text" => $exception->getMessage(),
                "toast" => true,
            ]);
            regenerate_csrf_token();

            return $this->redirect(route("developer.panel.workspace"));
        }

        if ($payload["title"] === "") {
            flash_set("status", [
                "variant" => "warning",
                "title" => "Task needs a title",
                "text" => "Add a short task title before saving it to the developer workspace.",
                "toast" => true,
            ]);
            regenerate_csrf_token();

            return $this->redirect(route("developer.panel.workspace"));
        }

        $workspace->create($payload, $developerAccess->currentDeveloper());
        developer_activity()->record(
            "developer_workspace_task",
            "Workspace task created",
            "A developer added a project task to the Kanban workspace.",
            $developerAccess->currentDeveloper()
        );

        flash_set("status", [
            "variant" => "success",
            "title" => "Workspace task saved",
            "text" => "The project workspace was updated for every developer session.",
            "toast" => true,
        ]);
        regenerate_csrf_token();

        return $this->redirect(route("developer.panel.workspace"));
    }

    public function updateWorkspaceTask(Request $request, DeveloperAccessManager $developerAccess, DeveloperWorkspaceBoard $workspace): Response
    {
        if (!$this->ensureDeveloperCapability($developerAccess, "workspace.write")) {
            return $this->redirect(route("developer.panel.workspace"));
        }

        $taskId = trim((string) $request->input("developer_workspace_task_id", ""));

        if ($taskId === "") {
            return $this->redirect(route("developer.panel.workspace"));
        }

        try {
            $payload = $this->workspaceTaskPayload($request, $developerAccess->currentDeveloper());
            $payload = $this->withWorkspaceAttachmentFile($request, $payload, $developerAccess->currentDeveloper());
        } catch (\RuntimeException $exception) {
            flash_set("status", [
                "variant" => "warning",
                "title" => "Attachment upload failed",
                "text" => $exception->getMessage(),
                "toast" => true,
            ]);
            regenerate_csrf_token();

            return $this->redirect(route("developer.panel.workspace"));
        }

        $workspace->update($taskId, $payload, $developerAccess->currentDeveloper());
        developer_activity()->record(
            "developer_workspace_task",
            "Workspace task updated",
            "A developer changed a Kanban task status, owner or priority.",
            $developerAccess->currentDeveloper()
        );

        flash_set("status", [
            "variant" => "success",
            "title" => "Workspace task updated",
            "text" => "The shared developer workspace now shows the latest state.",
            "toast" => true,
        ]);
        regenerate_csrf_token();

        return $this->redirect(route("developer.panel.workspace"));
    }

    public function deleteWorkspaceTask(Request $request, DeveloperAccessManager $developerAccess, DeveloperWorkspaceBoard $workspace): Response
    {
        if (!$this->ensureDeveloperCapability($developerAccess, "workspace.write")) {
            return $this->redirect(route("developer.panel.workspace"));
        }

        $taskId = trim((string) $request->input("developer_workspace_task_id", ""));

        if ($taskId !== "") {
            $workspace->delete($taskId);
            developer_activity()->record(
                "developer_workspace_task",
                "Workspace task removed",
                "A developer removed a project task from the Kanban workspace.",
                $developerAccess->currentDeveloper()
            );
        }

        flash_set("status", [
            "variant" => "success",
            "title" => "Workspace task removed",
            "text" => "The shared developer workspace was updated.",
            "toast" => true,
        ]);
        regenerate_csrf_token();

        return $this->redirect(route("developer.panel.workspace"));
    }

    private function workspaceTaskPayload(Request $request, array $developer = []): array
    {
        $developerEmail = strtolower(trim((string) ($developer["email"] ?? "")));
        $subtasksText = $request->input("developer_workspace_subtasks_text", null);
        $subtasksDone = $request->input("developer_workspace_subtasks_done", []);
        $subtasksColor = $request->input("developer_workspace_subtasks_color", []);

        return [
            "title" => trim((string) $request->input("developer_workspace_title", "")),
            "notes" => trim((string) $request->input("developer_workspace_notes", "")),
            "status" => trim((string) $request->input("developer_workspace_status", "todo")),
            "priority" => trim((string) $request->input("developer_workspace_priority", "normal")),
            "type" => trim((string) $request->input("developer_workspace_type", "task")),
            "color" => trim((string) $request->input("developer_workspace_color", "blue")),
            "assignee" => trim((string) $request->input("developer_workspace_assignee", "")),
            "due_date" => trim((string) $request->input("developer_workspace_due_date", "")),
            "estimate" => trim((string) $request->input("developer_workspace_estimate", "")),
            "position" => $request->input("developer_workspace_position", null),
            "blocked" => (string) $request->input("developer_workspace_blocked", "0") === "1",
            "client_visible" => (string) $request->input("developer_workspace_client_visible", "0") === "1",
            "checklist" => trim((string) $request->input("developer_workspace_checklist", "")),
            "subtasks_text" => is_array($subtasksText) ? $subtasksText : null,
            "subtasks_done" => is_array($subtasksDone) ? $subtasksDone : [],
            "subtasks_color" => is_array($subtasksColor) ? $subtasksColor : [],
            "subtask" => trim((string) $request->input("developer_workspace_subtask", "")),
            "subtask_color" => trim((string) $request->input("developer_workspace_subtask_color", "blue")),
            "toggle_subtask_index" => $request->input("developer_workspace_toggle_subtask_index", null),
            "delete_subtask_index" => $request->input("developer_workspace_delete_subtask_index", null),
            "edit_subtask_index" => $request->input("developer_workspace_edit_subtask_index", null),
            "edit_subtask_text" => trim((string) $request->input("developer_workspace_edit_subtask_text", "")),
            "edit_subtask_color" => trim((string) $request->input("developer_workspace_edit_subtask_color", "blue")),
            "comment" => trim((string) $request->input("developer_workspace_comment", "")),
            "attachment_label" => trim((string) $request->input("developer_workspace_attachment_label", "")),
            "attachment_url" => trim((string) $request->input("developer_workspace_attachment_url", "")),
            "attachment_added_by" => $developerEmail,
        ];
    }

    private function withWorkspaceAttachmentFile(Request $request, array $payload, array $developer = []): array
    {
        $uploaded = $request->file("developer_workspace_attachment_file");

        if (!$uploaded instanceof UploadedFile || $uploaded->error() === UPLOAD_ERR_NO_FILE) {
            return $payload;
        }

        if (!$uploaded->isValid()) {
            throw new \RuntimeException($this->uploadErrorMessage("Uploaded workspace attachment", $uploaded->error()));
        }

        $uploaded->validate(
            max(1, (int) config("security.uploads.max_file_bytes", 5242880)),
            (array) config("security.uploads.allowed_mime_types", [])
        );

        $mimeType = $uploaded->detectedMimeType();
        $sizeBytes = $uploaded->size();
        $storedPath = $uploaded->store("developer-workspace-attachments", "public");
        $originalName = trim($uploaded->originalName());

        $payload["attachment_file"] = [
            "type" => "file",
            "label" => $originalName !== "" ? $originalName : "Uploaded attachment",
            "url" => "/uploads/" . trim($storedPath, "/"),
            "added_by" => strtolower(trim((string) ($payload["attachment_added_by"] ?? $developer["email"] ?? "developer"))),
            "created_at_utc" => gmdate(DATE_ATOM),
            "original_name" => $originalName,
            "mime_type" => $mimeType,
            "size_bytes" => $sizeBytes,
        ];

        return $payload;
    }
}
