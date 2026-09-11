<?php

declare(strict_types=1);

namespace Fnlla\Php\Tests;

use Fnlla\Php\Application;
use Fnlla\Php\Container\Container;
use Fnlla\Php\Exceptions\ExceptionHandler;
use Fnlla\Php\Http\Request;
use Fnlla\Php\Http\Response;
use Fnlla\Php\Http\HttpException;
use Fnlla\Php\Observability\DebugToolbar;
use Fnlla\Php\Observability\QueryTelemetry;
use Fnlla\Php\Observability\RequestHistory;
use Fnlla\Php\Observability\RuntimeIssueTracker;
use Fnlla\Php\Support\DeveloperPrivateTodo;
use Fnlla\Php\Support\DeveloperWorkspaceBoard;
use Fnlla\Php\Support\LockedJsonStore;
use Fnlla\Php\Support\TechnicalDebtRegistry;
use PHPUnit\Framework\TestCase;

final class DeveloperToolsTest extends TestCase
{
    private array $config;
    private array $session;
    private mixed $container;
    private string $directory;

    protected function setUp(): void
    {
        $this->config = $GLOBALS["fnlla_config"];
        $this->session = $_SESSION;
        $this->container = $GLOBALS["fnlla_container"];
        $this->directory = sys_get_temp_dir() . "/fnlla-tools-" . bin2hex(random_bytes(8));
        mkdir($this->directory, 0700);
        config_set("app.environment", "testing");
        config_set("app.debug", true);
        config_set("debug", ["toolbar" => true, "state_path" => $this->directory . "/debug.json"]);
        config_set("debug.history.path", $this->directory . "/history.json");
        config_set("debug.runtime_issues.path", $this->directory . "/issues.json");
        config_set("developer_tools.debt_path", $this->directory . "/debt.json");
        config_set("developer_workspace", array_merge((array) config("developer_workspace", []), [
            "driver" => "file",
            "path" => $this->directory . "/workspace.json",
            "private_todo_path" => $this->directory . "/private-todos.json",
        ]));
        $_SESSION = [];
    }

    protected function tearDown(): void
    {
        $GLOBALS["fnlla_config"] = $this->config;
        $GLOBALS["fnlla_php_config"] = $this->config;
        $GLOBALS["fnlla_container"] = $this->container;
        $GLOBALS["fnlla_php_container"] = $this->container;
        $_SESSION = $this->session;
        unset($_SERVER["FNLLA_ROUTE_NAME"]);
        QueryTelemetry::reset();
        foreach (glob($this->directory . "/*") ?: [] as $file) {
            unlink($file);
        }
        rmdir($this->directory);
    }

    public function testDebtTracksOwnershipAndPreservesTriageAcrossScans(): void
    {
        $registry = new TechnicalDebtRegistry();
        $state = $registry->synchronize(["src/Example.php"], 0, "admin");
        $item = array_values($state["items"])[0];
        $item["status"] = "accepted";
        $item["owner"] = "Platform team";
        $item["notes"] = "Replace after the next migration.";
        $item["accepted_until"] = "2026-12-31";
        $item["issue_ref"] = "https://github.com/techayoDEV/fnlla/issues/123";
        $item["adr_ref"] = "docs/adr/0001-example.md";
        $item["evidence_ref"] = "/developer/panel/project-logs";
        $registry->save($item, 1, "admin");
        $state = $registry->synchronize([], 2, "admin");
        self::assertSame("accepted", $state["items"][$item["id"]]["status"]);
        self::assertFalse($state["items"][$item["id"]]["observed"]);
        $state = $registry->synchronize(["src/Example.php"], 3, "admin");
        self::assertSame(1, count($state["items"]));
        self::assertSame("Platform team", $state["items"][$item["id"]]["owner"]);
        self::assertSame("2026-12-31", $state["items"][$item["id"]]["accepted_until"]);
        self::assertSame("https://github.com/techayoDEV/fnlla/issues/123", $state["items"][$item["id"]]["issue_ref"]);
        self::assertSame("docs/adr/0001-example.md", $state["items"][$item["id"]]["adr_ref"]);
        self::assertSame("/developer/panel/project-logs", $state["items"][$item["id"]]["evidence_ref"]);
        self::assertSame(4, count($state["history"]));
    }

    public function testStaleDebtEditsAreRejectedWithoutLosingData(): void
    {
        $registry = new TechnicalDebtRegistry();
        $registry->save(["title" => "Existing"], 0, "admin");
        try {
            $registry->save(["title" => "Stale"], 0, "admin");
            self::fail("Stale write was accepted.");
        } catch (\RuntimeException $exception) {
            self::assertStringContainsString("Reload", $exception->getMessage());
        }
        self::assertSame("Existing", array_values($registry->state()["items"])[0]["title"]);
    }

    public function testAcceptedDebtRequiresReasonAndDatesAreValidated(): void
    {
        $registry = new TechnicalDebtRegistry();
        foreach ([["status" => "accepted"], ["status" => "accepted", "notes" => "Temporary risk"], ["due_date" => "2026-02-31"], ["accepted_until" => "2026-02-31"], ["issue_ref" => "javascript:alert(1)"], ["priority" => "arbitrary"], ["notes" => ["invalid"]]] as $input) {
            try {
                $registry->save(["title" => "Example"] + $input, 0, "admin");
                self::fail("Invalid input was accepted.");
            } catch (\InvalidArgumentException) {
                self::assertSame([], $registry->state()["items"]);
            }
        }
    }

    public function testCorruptStateIsNotSilentlyReplaced(): void
    {
        $path = $this->directory . "/corrupt.json";
        file_put_contents($path, "invalid");
        try {
            (new LockedJsonStore($path))->update(static fn (): array => []);
            self::fail("Corrupt state was replaced.");
        } catch (\JsonException) {
            self::assertSame("invalid", file_get_contents($path));
        }
    }

    public function testDebugNeverAppearsForGuestsOrProductionEvenWhenEnabled(): void
    {
        $toolbar = new DebugToolbar();
        $html = Response::html("<html><body>Private fixture</body></html>");
        self::assertSame($html->body(), $toolbar->decorate(new Request("GET", "/"), $html, 10)->body());
        $this->application("admin");
        config_set("app.environment", "production");
        self::assertFalse($toolbar->enabled());
        self::assertSame($html->body(), $toolbar->decorate(new Request("GET", "/"), $html, 10)->body());
        config_set("app.environment", "development");
        config_set("app.debug", false);
        self::assertFalse($toolbar->enabled());
    }

    public function testDebugPreservesResponseSemanticsAndDoesNotExposeInputs(): void
    {
        $this->application("admin");
        $toolbar = new DebugToolbar();
        $request = new Request("GET", "/", ["token" => "secret-fixture"], [], [], [], ["password" => "secret-fixture"]);
        $html = Response::html("<html><body>Example</body></html>")->withHeader("ETag", "old")->withHeader("Content-Length", "10");
        $response = $toolbar->decorate($request, $html, 10);
        self::assertStringContainsString('id="fnlla-debug-toolbar"', $response->body());
        self::assertStringContainsString('class="fnlla-debug-grid"', $response->body());
        self::assertStringContainsString("No database queries were captured for this request.", $response->body());
        self::assertStringNotContainsString("secret-fixture", $response->body());
        self::assertSame("private, no-store", $response->headers()["Cache-Control"]);
        self::assertArrayNotHasKey("ETag", $response->headers());
        self::assertArrayNotHasKey("Content-Length", $response->headers());
        foreach ([Response::json(["ok" => true]), Response::empty(), Response::redirect("/"), $html->withHeader("Content-Disposition", "attachment")] as $original) {
            self::assertSame($original->body(), $toolbar->decorate($request, $original, 1)->body());
        }
        self::assertSame($html->body(), $toolbar->decorate(new Request("HEAD", "/"), $html, 1)->body());
    }

    public function testDebugToolbarIsLimitedToPublicHtmlSurfaces(): void
    {
        $this->application("admin");
        $toolbar = new DebugToolbar();
        $publicHtml = Response::html("<html><body>Public page</body></html>");
        $_SERVER["FNLLA_ROUTE_NAME"] = "home";

        self::assertStringContainsString(
            'id="fnlla-debug-toolbar"',
            $toolbar->decorate(new Request("GET", "/"), $publicHtml, 10)->body()
        );

        foreach ([
            "developer.panel.notifications" => "/developer/panel/notifications",
            "customer.panel" => "/client/panel",
            "maintenance.framework_update" => "/maintenance/framework-update",
        ] as $routeName => $path) {
            $internalHtml = Response::html("<html><body>Internal surface</body></html>");
            $_SERVER["FNLLA_ROUTE_NAME"] = $routeName;

            $response = $toolbar->decorate(new Request("GET", $path), $internalHtml, 10);
            self::assertSame($internalHtml->body(), $response->body());
            self::assertStringNotContainsString('id="fnlla-debug-toolbar"', $response->body());
        }

        $fallbackInternalHtml = Response::html("<html><body>Internal surface</body></html>");
        $_SERVER["FNLLA_ROUTE_NAME"] = "";
        $fallbackResponse = $toolbar->decorate(new Request("GET", "/developer/panel"), $fallbackInternalHtml, 10);
        self::assertSame($fallbackInternalHtml->body(), $fallbackResponse->body());
        self::assertStringNotContainsString('id="fnlla-debug-toolbar"', $fallbackResponse->body());
    }

    public function testDeveloperToolsRoutesEnforcePermissionsAndCsrf(): void
    {
        $application = $this->application("observer");
        $response = $application->handle(new Request("POST", "/developer/panel/debug", [], ["_token" => csrf_token(), "enabled" => "1"]));
        self::assertSame(403, $response->status());
        $application = $this->application("admin");
        $technicalDebt = $application->handle(new Request("GET", "/developer/panel/technical-debt"));
        self::assertSame(200, $technicalDebt->status());
        self::assertStringContainsString("Release triage", $technicalDebt->body());
        self::assertStringContainsString("debt-snapshot-grid", $technicalDebt->body());
        self::assertStringContainsString("debt-status-tabs", $technicalDebt->body());
        self::assertStringContainsString("Evidence links", $technicalDebt->body());
        self::assertStringContainsString("Issue / PR ref", $technicalDebt->body());
        self::assertStringContainsString("Accepted until", $technicalDebt->body());
        self::assertStringContainsString('<select class="select" id="debt-status-filter" name="status">', $technicalDebt->body());
        self::assertStringContainsString('<summary class="debt-add-summary">Add debt item</summary>', $technicalDebt->body());
        self::assertSame(200, $application->handle(new Request("GET", "/developer/panel/debug"))->status());
        $privateTodo = $application->handle(new Request("GET", "/developer/panel/my-tasks"));
        self::assertSame(200, $privateTodo->status());
        self::assertStringContainsString("Private developer tasks, notes, subtasks and attachments", $privateTodo->body());
        self::assertStringContainsString("action=\"/developer/panel/my-tasks/items\"", $privateTodo->body());
        $legacyPrivateTodo = $application->handle(new Request("GET", "/developer/panel/my-todo"));
        self::assertSame(301, $legacyPrivateTodo->status());
        self::assertSame("/developer/panel/my-tasks", $legacyPrivateTodo->headers()["Location"] ?? null);
        self::assertSame(419, $application->handle(new Request("POST", "/developer/panel/debug", [], ["enabled" => "1"], [], ["accept" => "application/json"]))->status());
        $todoResponse = $application->handle(new Request("POST", "/developer/panel/my-tasks/items", [], [
            "_token" => csrf_token(),
            "developer_private_todo_title" => "Review local debug workflow",
            "developer_private_todo_notes" => "Do not expose this as shared Kanban work.",
            "developer_private_todo_priority" => "high",
            "developer_private_todo_color" => "green",
            "developer_private_todo_subtasks" => [
                "0" => "Check route",
                "1" => "Confirm local panel",
            ],
            "developer_private_todo_subtasks_done" => ["1"],
            "developer_private_todo_attachment_label" => "Debug reference",
            "developer_private_todo_attachment_url" => "https://example.test/debug",
        ]));
        self::assertSame(302, $todoResponse->status());
        self::assertSame(route("developer.panel.private_todo"), $todoResponse->headers()["Location"]);
        $createdPrivateTodo = (new DeveloperPrivateTodo())->state(["email" => "tools@example.test"]);
        self::assertSame(1, $createdPrivateTodo["open_count"] ?? null);
        self::assertSame("green", $createdPrivateTodo["items"][0]["color"] ?? null);
        self::assertSame(2, count((array) ($createdPrivateTodo["items"][0]["subtasks"] ?? [])));
        self::assertTrue((bool) ($createdPrivateTodo["items"][0]["subtasks"][1]["done"] ?? false));
        self::assertSame(1, count((array) ($createdPrivateTodo["items"][0]["attachments"] ?? [])));
        $createdPrivateTodoItemId = (string) ($createdPrivateTodo["items"][0]["id"] ?? "");
        $privateTodoWithItem = $application->handle(new Request("GET", "/developer/panel/my-tasks"));
        self::assertStringContainsString("Quick add a private task", $privateTodoWithItem->body());
        self::assertStringContainsString("Next action", $privateTodoWithItem->body());
        self::assertStringContainsString("Edit details", $privateTodoWithItem->body());
        self::assertStringContainsString("data-developer-confirm-title=\"Delete private task: Review local debug workflow\"", $privateTodoWithItem->body());
        self::assertStringContainsString("Delete &quot;Review local debug workflow&quot; from your private My Tasks list?", $privateTodoWithItem->body());
        self::assertStringContainsString("action=\"/developer/panel/my-tasks/items/update\"", $privateTodoWithItem->body());
        self::assertStringContainsString("data-private-todo-subtask-add", $privateTodoWithItem->body());
        self::assertStringContainsString("name=\"developer_private_todo_subtasks[0]\"", $privateTodoWithItem->body());
        self::assertStringContainsString("name=\"developer_private_todo_attachment_file\"", $privateTodoWithItem->body());
        $updatedTodoResponse = $application->handle(new Request("POST", "/developer/panel/my-tasks/items/update", [], [
            "_token" => csrf_token(),
            "developer_private_todo_id" => $createdPrivateTodoItemId,
            "developer_private_todo_title" => "Update private workflow note",
            "developer_private_todo_notes" => "Keep this private until the handover is ready.",
            "developer_private_todo_priority" => "low",
            "developer_private_todo_due_date" => "2026-09-18",
            "developer_private_todo_color" => "orange",
            "developer_private_todo_subtasks" => [
                "0" => "Write private note",
                "1" => "Attach supporting reference",
            ],
            "developer_private_todo_subtasks_done" => ["0"],
            "developer_private_todo_attachment_label" => "Updated reference",
            "developer_private_todo_attachment_url" => "https://example.test/updated-private-note",
        ]));
        self::assertSame(302, $updatedTodoResponse->status());
        self::assertSame(route("developer.panel.private_todo"), $updatedTodoResponse->headers()["Location"]);
        $updatedPrivateTodo = (new DeveloperPrivateTodo())->state(["email" => "tools@example.test"]);
        self::assertSame("Update private workflow note", $updatedPrivateTodo["items"][0]["title"] ?? null);
        self::assertSame("Keep this private until the handover is ready.", $updatedPrivateTodo["items"][0]["notes"] ?? null);
        self::assertSame("low", $updatedPrivateTodo["items"][0]["priority"] ?? null);
        self::assertSame("2026-09-18", $updatedPrivateTodo["items"][0]["due_date"] ?? null);
        self::assertSame("orange", $updatedPrivateTodo["items"][0]["color"] ?? null);
        self::assertSame(2, count((array) ($updatedPrivateTodo["items"][0]["subtasks"] ?? [])));
        self::assertTrue((bool) ($updatedPrivateTodo["items"][0]["subtasks"][0]["done"] ?? false));
        self::assertSame(2, count((array) ($updatedPrivateTodo["items"][0]["attachments"] ?? [])));
        $response = $application->handle(new Request("POST", "/developer/panel/technical-debt", [], [
            "_token" => csrf_token(), "revision" => "0", "title" => "Track release debt", "status" => "open", "priority" => "high",
        ]));
        self::assertSame(302, $response->status());
        self::assertSame(1, count((new TechnicalDebtRegistry())->state()["items"]));
        $filtered = $application->handle(new Request("GET", "/developer/panel/technical-debt", ["status" => "resolved"]));
        self::assertSame(200, $filtered->status());
        self::assertStringNotContainsString("Track release debt", $filtered->body());
    }

    public function testDebugLiveEndpointReportsHistoryAndRuntimeIssuesWithoutMessages(): void
    {
        $application = $this->application("admin");
        $history = new RequestHistory();
        $history->configure(true);
        $history->record(new Request("GET", "/private-secret-fixture", ["token" => "secret-fixture"]), Response::empty(), 12.5);
        $tracker = new RuntimeIssueTracker();
        $tracker->record(new \RuntimeException("secret-token-fixture"), new Request("GET", "/failing-page"));

        $response = $application->handle(new Request("GET", "/developer/panel/debug/live", [], [], [], ["accept" => "application/json"]));
        self::assertSame(200, $response->status());
        self::assertStringContainsString("fnlla.debug_live.v1", $response->body());
        self::assertStringContainsString("fnlla.debug_report.v1", $response->body());
        self::assertStringContainsString("\"open\": 1", $response->body());
        self::assertStringNotContainsString("secret-token-fixture", $response->body());
        self::assertStringNotContainsString("private-secret-fixture", $response->body());
    }

    public function testRuntimeIssueTrackerSkipsHttpClientErrorsAndDeduplicatesServerFailures(): void
    {
        $tracker = new RuntimeIssueTracker();
        $tracker->record(new HttpException(404, "Not found"), new Request("GET", "/missing"));
        self::assertSame([], $tracker->issues());

        $exception = new \RuntimeException("private exception message");
        $tracker->record($exception, new Request("GET", "/first"));
        $tracker->record($exception, new Request("GET", "/second"));
        $issues = $tracker->issues();

        self::assertSame(1, count($issues));
        self::assertSame(2, (int) $issues[0]["occurrences"]);
        self::assertStringNotContainsString("private exception message", (string) file_get_contents($this->directory . "/issues.json"));
    }

    public function testRuntimeIssueCanBePromotedToTechnicalDebt(): void
    {
        $application = $this->application("admin");
        $tracker = new RuntimeIssueTracker();
        $tracker->record(new \RuntimeException("private promotion message"), new Request("GET", "/broken"));
        $issue = $tracker->issues()[0];

        $response = $application->handle(new Request("POST", "/developer/panel/debug/runtime-issues/promote", [], [
            "_token" => csrf_token(),
            "revision" => "0",
            "runtime_issue_id" => $issue["id"],
            "runtime_issue_create_workspace_task" => "1",
        ]));

        self::assertSame(302, $response->status());
        self::assertSame(route("developer.panel.technical_debt"), $response->headers()["Location"]);
        $debtItemId = TechnicalDebtRegistry::runtimeIssueDebtId((string) $issue["fingerprint"]);
        $debtItem = (new TechnicalDebtRegistry())->state()["items"][$debtItemId] ?? [];
        $linkedIssue = (new RuntimeIssueTracker())->find((string) $issue["id"]);
        $workspaceTaskId = (string) ($linkedIssue["workspace_task_id"] ?? "");
        $workspaceTask = [];
        foreach ((new DeveloperWorkspaceBoard())->state(["email" => "tools@example.test"])["tasks"] as $task) {
            if (($task["id"] ?? "") === $workspaceTaskId) {
                $workspaceTask = $task;
                break;
            }
        }
        self::assertSame("runtime_issue", $debtItem["source"] ?? null);
        self::assertSame($issue["fingerprint"], $debtItem["runtime_issue_id"] ?? null);
        self::assertSame("linked", $linkedIssue["status"] ?? null);
        self::assertStringStartsWith("runtime-", $workspaceTaskId);
        self::assertSame("bug", $workspaceTask["type"] ?? null);
        self::assertSame("backlog", $workspaceTask["status"] ?? null);
        self::assertFalse((bool) ($workspaceTask["client_visible"] ?? true));
        self::assertStringNotContainsString("private promotion message", json_encode($debtItem, JSON_THROW_ON_ERROR));
    }

    public function testPrivateTodoIsScopedToCurrentDeveloper(): void
    {
        $todo = new DeveloperPrivateTodo();
        $firstDeveloper = ["email" => "first@example.test", "name" => "First"];
        $secondDeveloper = ["email" => "second@example.test", "name" => "Second"];

        $todo->create([
            "title" => "Private release note",
            "priority" => "high",
            "color" => "orange",
            "subtasks" => "[ ] Draft\n[x] Review",
            "attachment_label" => "Decision note",
            "attachment_url" => "https://example.test/private-note",
        ], $firstDeveloper);
        $todo->create(["title" => "Second developer note", "priority" => "normal"], $secondDeveloper);
        $firstState = $todo->state($firstDeveloper);
        $secondState = $todo->state($secondDeveloper);
        $firstItemId = (string) ($firstState["items"][0]["id"] ?? "");

        $todo->toggle($firstItemId, $firstDeveloper);
        $firstDoneState = $todo->state($firstDeveloper);

        self::assertSame("fnlla.developer_private_todo.v1", $firstState["schema"] ?? null);
        self::assertSame(1, $firstState["open_count"] ?? null);
        self::assertSame(1, $secondState["open_count"] ?? null);
        self::assertSame("Private release note", $firstState["items"][0]["title"] ?? null);
        self::assertSame("orange", $firstState["items"][0]["color"] ?? null);
        self::assertSame(2, $firstState["subtasks_count"] ?? null);
        self::assertSame(1, $firstState["completed_subtasks_count"] ?? null);
        self::assertSame(1, $firstState["attachments_count"] ?? null);
        self::assertSame("Second developer note", $secondState["items"][0]["title"] ?? null);
        self::assertSame(0, $firstDoneState["open_count"] ?? null);
        self::assertSame(1, $firstDoneState["done_count"] ?? null);
        self::assertStringNotContainsString("Second developer note", json_encode($firstDoneState, JSON_THROW_ON_ERROR));
    }

    public function testQueryTelemetryIsBoundedAndNeverRetainsSqlOrBindings(): void
    {
        $statement = new class extends \PDOStatement {
            public function __construct()
            {
                $this->queryString = "SELECT secret_column FROM private_table WHERE password = 'private-fixture'";
            }
            public function execute(?array $params = null): bool
            {
                return true;
            }
        };
        QueryTelemetry::reset(true);
        for ($index = 0; $index < 110; $index++) {
            QueryTelemetry::execute($statement, ["password" => "private-fixture"]);
        }
        $snapshot = QueryTelemetry::snapshot();
        self::assertSame(110, $snapshot["count"]);
        self::assertSame(100, count($snapshot["queries"]));
        self::assertSame("SELECT", $snapshot["queries"][0]["operation"]);
        self::assertStringNotContainsString("private", json_encode($snapshot));
        QueryTelemetry::reset();
        QueryTelemetry::execute($statement);
        self::assertSame(0, QueryTelemetry::snapshot()["count"]);
    }

    public function testHistoryIsOptInBoundedRedactedAndExpiresOnRead(): void
    {
        $this->application("admin");
        $history = new RequestHistory();
        $request = new Request("GET", "/private-secret-fixture", ["token" => "secret-fixture"], [], [], ["Authorization" => "secret-fixture"]);
        self::assertFalse($history->enabled());
        $history->record($request, Response::json(["password" => "secret-fixture"]), 10);
        self::assertSame([], $history->entries());
        config_set("debug.history.max_entries", 3);
        $history->configure(true);
        for ($i = 0; $i < 6; $i++) { $history->record($request, Response::empty(), $i); }
        $entries = $history->entries();
        self::assertSame(3, count($entries));
        self::assertSame(5, (int) $entries[0]["duration_ms"]);
        self::assertSame(["at", "method", "status", "duration_ms", "memory_bytes"], array_keys($entries[0]));
        self::assertStringNotContainsString("secret-fixture", (string) file_get_contents($this->directory . "/history.json"));
        (new LockedJsonStore($this->directory . "/history.json"))->update(static function (array $state): array {
            $state["entries"][0]["at"] = time() - 86401;
            return $state;
        });
        self::assertSame(2, count($history->entries()));
        $history->configure(true, true);
        self::assertSame([], $history->entries());
        $history->record($request, Response::empty(), 1);
        $history->configure(false);
        self::assertSame([], (new LockedJsonStore($this->directory . "/history.json"))->read()["entries"]);
    }

    public function testHistoryRejectsGuestsProductionAndUnauthorizedMutations(): void
    {
        $history = new RequestHistory();
        config_set("debug.history.enabled", true);
        self::assertFalse($history->enabled());
        $application = $this->application("observer");
        try {
            $history->configure(true);
            self::fail("Observer changed history configuration.");
        } catch (\RuntimeException $error) {
            self::assertStringContainsString("forbidden", $error->getMessage());
        }
        self::assertSame(403, $application->handle(new Request("POST", "/developer/panel/debug", [], ["_token" => csrf_token(), "history_enabled" => "1"]))->status());
        $application = $this->application("admin");
        self::assertSame(419, $application->handle(new Request("POST", "/developer/panel/debug", [], ["history_enabled" => "1"], [], ["accept" => "application/json"]))->status());
        config_set("app.environment", "production");
        self::assertFalse($history->enabled());
        $history->record(new Request("GET", "/"), Response::empty(), 1);
        self::assertSame([], $history->entries());
    }

    public function testAiSettingsRequireCapabilityAndCsrfWithoutWritingEnvironment(): void
    {
        $path = $this->directory . "/ai.env";
        config_set("maintenance.env_path", $path);
        $application = $this->application("observer");
        $response = $application->handle(new Request("POST", "/developer/panel/integrations/ai", [], ["_token" => csrf_token(), "ai_runtime_driver" => "local"]));
        self::assertSame(302, $response->status());
        self::assertFileDoesNotExist($path);
        $application = $this->application("admin");
        self::assertSame(419, $application->handle(new Request("POST", "/developer/panel/integrations/ai", [], [], [], ["accept" => "application/json"]))->status());
        self::assertFileDoesNotExist($path);
        $response = $application->handle(new Request("POST", "/developer/panel/integrations/ai", [], ["_token" => csrf_token(), "ai_runtime_driver" => "unsupported"]));
        self::assertSame(302, $response->status());
        self::assertFileDoesNotExist($path);
    }

    public function testFrameworkApplyRequiresApplyCapability(): void
    {
        $application = $this->application("operations_engineer");
        $response = $application->handle(new Request("POST", "/developer/panel/framework-updates/run", [], [
            "_token" => csrf_token(),
            "mode" => "github-apply",
        ]));

        self::assertSame(302, $response->status());
        self::assertSame(route("developer.panel.framework_updates"), $response->headers()["Location"] ?? null);
    }

    public function testIntegrationViewNeverPrefillsCloudApiKeys(): void
    {
        $application = $this->application("admin");
        config_set("ai.runtime.openai.api_key", "private-test-provider-key");
        config_set("ai.runtime.anthropic.api_key", "private-test-provider-key");
        $response = $application->handle(new Request("GET", "/developer/panel/integrations"));
        self::assertSame(200, $response->status());
        self::assertStringContainsString('id="ai-providers-title"', $response->body());
        self::assertStringContainsString('Configured; leave blank to keep', $response->body());
        self::assertStringNotContainsString("private-test-provider-key", $response->body());
    }

    public function testIntegrationViewDistinguishesReferenceLookupFromAccountBackedAi(): void
    {
        $application = $this->application("admin");
        $response = $application->handle(new Request("GET", "/developer/panel/integrations"));
        self::assertSame(200, $response->status());
        self::assertStringContainsString('>Local reference (no AI model)</option>', $response->body());
        self::assertStringContainsString("Neutral server-side AI provider slot", $response->body());
        self::assertStringContainsString("FIONN AI adapter / optional API account required", $response->body());
        self::assertStringContainsString("Anthropic API", $response->body());
        self::assertStringNotContainsString("Claude Platform API", $response->body());
    }

    public function testHistoryFailureDoesNotBreakApplicationResponses(): void
    {
        $this->application("admin");
        file_put_contents($this->directory . "/history.json", "invalid");
        $history = new RequestHistory();
        $history->record(new Request("GET", "/"), Response::empty(), INF);
        self::assertFalse($history->enabled());
        self::assertSame("invalid", file_get_contents($this->directory . "/history.json"));
    }

    public function testOwnershipMigrationDoesNotDeleteApplicationFilesFromOldLocks(): void
    {
        $old = ["framework_base" => ["managed_files" => ["routes/web.php" => "old", "public/assets/app.css" => "old", ".env.production" => "old"]]];
        $method = new \ReflectionMethod(\Fnlla\Php\Support\FrameworkUpdater::class, "buildReport");
        $report = $method->invoke(null, $old, ["framework_base" => ["managed_files" => []]], $this->directory, $this->directory);
        self::assertSame([], $report["updates"]);
        self::assertSame([], $report["conflicts"]);
    }

    public function testUpdatePlanRejectsTraversalAndConcurrentEditsBeforeWriting(): void
    {
        foreach (["../outside.php", "/absolute", "C:/outside", "src/../../outside", "src/file.php:stream"] as $path) {
            self::assertFalse(\Fnlla\Php\Support\FrameworkLock::isFrameworkManagedPath($path));
        }
        $method = new \ReflectionMethod(\Fnlla\Php\Support\FrameworkUpdater::class, "applyReport");
        file_put_contents($this->directory . "/example.php", "changed locally");
        $report = ["updates" => ["example.php" => ["action" => "remove", "current_hash" => hash("sha256", "old"), "source_hash" => null]]];
        try {
            $method->invoke(null, $report, $this->directory, $this->directory);
            self::fail("Changed local file was removed.");
        } catch (\RuntimeException $exception) {
            self::assertStringContainsString("changed after planning", $exception->getMessage());
        }
        self::assertSame("changed locally", file_get_contents($this->directory . "/example.php"));
    }

    private function application(string $role): Application
    {
        $container = new Container();
        foreach ((array) config("app.providers") as $class) {
            $provider = new $class($container);
            $provider->register();
            $provider->boot();
        }
        $GLOBALS["fnlla_container"] = $container;
        $GLOBALS["fnlla_php_container"] = $container;
        $account = ["email" => "tools@example.test", "name" => "Tools", "role" => $role, "avatar" => "", "password_hash" => password_hash("fixture", PASSWORD_DEFAULT)];
        config_set("developer_access.enabled", true);
        config_set("developer_access.path", "/developer");
        config_set("developer_access.users", developer_access()->serializeAccounts([$account]));
        developer_access()->grantAccess($account);
        $router = require base_path("bootstrap/router.php");
        return new Application($router, $container, $container->make(ExceptionHandler::class));
    }
}
