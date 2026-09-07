<?php

declare(strict_types=1);

namespace Fnlla\Php\Tests;

use Fnlla\Php\Application;
use Fnlla\Php\Container\Container;
use Fnlla\Php\Exceptions\ExceptionHandler;
use Fnlla\Php\Http\Request;
use Fnlla\Php\Http\Response;
use Fnlla\Php\Observability\DebugToolbar;
use Fnlla\Php\Observability\QueryTelemetry;
use Fnlla\Php\Observability\RequestHistory;
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
        config_set("developer_tools.debt_path", $this->directory . "/debt.json");
        $_SESSION = [];
    }

    protected function tearDown(): void
    {
        $GLOBALS["fnlla_config"] = $this->config;
        $GLOBALS["fnlla_php_config"] = $this->config;
        $GLOBALS["fnlla_container"] = $this->container;
        $GLOBALS["fnlla_php_container"] = $this->container;
        $_SESSION = $this->session;
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
        $registry->save($item, 1, "admin");
        $state = $registry->synchronize([], 2, "admin");
        self::assertSame("accepted", $state["items"][$item["id"]]["status"]);
        self::assertFalse($state["items"][$item["id"]]["observed"]);
        $state = $registry->synchronize(["src/Example.php"], 3, "admin");
        self::assertSame(1, count($state["items"]));
        self::assertSame("Platform team", $state["items"][$item["id"]]["owner"]);
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
        foreach ([["status" => "accepted"], ["due_date" => "2026-02-31"], ["priority" => "arbitrary"], ["notes" => ["invalid"]]] as $input) {
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
        self::assertStringNotContainsString("secret-fixture", $response->body());
        self::assertSame("private, no-store", $response->headers()["Cache-Control"]);
        self::assertArrayNotHasKey("ETag", $response->headers());
        self::assertArrayNotHasKey("Content-Length", $response->headers());
        foreach ([Response::json(["ok" => true]), Response::empty(), Response::redirect("/"), $html->withHeader("Content-Disposition", "attachment")] as $original) {
            self::assertSame($original->body(), $toolbar->decorate($request, $original, 1)->body());
        }
        self::assertSame($html->body(), $toolbar->decorate(new Request("HEAD", "/"), $html, 1)->body());
    }

    public function testDeveloperToolsRoutesEnforcePermissionsAndCsrf(): void
    {
        $application = $this->application("observer");
        $response = $application->handle(new Request("POST", "/developer/panel/debug", [], ["_token" => csrf_token(), "enabled" => "1"]));
        self::assertSame(403, $response->status());
        $application = $this->application("admin");
        self::assertSame(200, $application->handle(new Request("GET", "/developer/panel/technical-debt"))->status());
        self::assertSame(200, $application->handle(new Request("GET", "/developer/panel/debug"))->status());
        self::assertSame(419, $application->handle(new Request("POST", "/developer/panel/debug", [], ["enabled" => "1"], [], ["accept" => "application/json"]))->status());
        $response = $application->handle(new Request("POST", "/developer/panel/technical-debt", [], [
            "_token" => csrf_token(), "revision" => "0", "title" => "Track release debt", "status" => "open", "priority" => "high",
        ]));
        self::assertSame(302, $response->status());
        self::assertSame(1, count((new TechnicalDebtRegistry())->state()["items"]));
        $filtered = $application->handle(new Request("GET", "/developer/panel/technical-debt", ["status" => "resolved"]));
        self::assertSame(200, $filtered->status());
        self::assertStringNotContainsString("Track release debt", $filtered->body());
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
        self::assertStringContainsString("Persistent Personal Intelligence by TechAyo", $response->body());
        self::assertStringContainsString("FIONN developer account and API access required", $response->body());
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
