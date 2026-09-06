<?php

declare(strict_types=1);

namespace Fnlla\Php\Tests;

use Fnlla\Php\Application;
use Fnlla\Php\Container\Container;
use Fnlla\Php\Controllers\DeveloperRecoveryController;
use Fnlla\Php\Exceptions\ExceptionHandler;
use Fnlla\Php\Http\Request;
use Fnlla\Php\Mail\MailTransportInterface;
use Fnlla\Php\Maintenance\DeveloperPasswordRecovery;
use Fnlla\Php\Maintenance\DeveloperRecoveryMailJob;
use Fnlla\Php\Queue\FileQueueStore;
use Fnlla\Php\Queue\QueueManager;
use Fnlla\Php\Support\EnvironmentFileManager;
use Fnlla\Php\Support\LockedJsonStore;
use PHPUnit\Framework\TestCase;

final class DeveloperPasswordRecoveryTest extends TestCase
{
    private array $config;
    private array $session;
    private mixed $container;
    private mixed $usersEnv;
    private array $env;
    private array $server;
    private string $directory;
    private string $users;
    private DeveloperPasswordRecovery $recovery;
    private RecoveryTestTransport $transport;

    protected function setUp(): void
    {
        $this->config = $GLOBALS["fnlla_config"];
        $this->session = $_SESSION;
        $this->container = $GLOBALS["fnlla_container"];
        $this->usersEnv = getenv("DEVELOPER_ACCESS_USERS");
        $this->env = $_ENV;
        $this->server = $_SERVER;
        $_SESSION = [];
        $this->directory = sys_get_temp_dir() . "/fnlla-recovery-" . bin2hex(random_bytes(6));
        mkdir($this->directory, 0700);
        $container = new Container();
        foreach ((array) config("app.providers") as $class) {
            $provider = new $class($container);
            $provider->register();
            $provider->boot();
        }
        $GLOBALS["fnlla_container"] = $GLOBALS["fnlla_php_container"] = $container;
        config_set("app.environment", "testing");
        config_set("app.base_url", "https://project.example.test");
        config_set("maintenance.env_path", $this->directory . "/.env");
        config_set("maintenance.env_example_path", $this->directory . "/missing");
        config_set("developer_access.enabled", true);
        config_set("developer_access.recovery_enabled", true);
        config_set("developer_access.recovery_path", $this->directory);
        config_set("developer_access.path", "/private-tools");
        $this->users = developer_access()->serializeAccounts([[
            "email" => "dev@example.test", "name" => "Developer", "role" => "lead_developer",
            "password_hash" => password_hash("previous-password", PASSWORD_DEFAULT),
            "security" => ["totp_enabled" => true, "totp_secret" => "JBSWY3DPEHPK3PXP"],
        ]]);
        config_set("developer_access.users", $this->users);
        app(EnvironmentFileManager::class)->write(["DEVELOPER_ACCESS_USERS" => $this->users, "KEEP_ME" => "unchanged"]);
        $this->transport = new RecoveryTestTransport();
        $container->instance(MailTransportInterface::class, $this->transport);
        config_set("mail.default", "adapter");
        $this->recovery = app(DeveloperPasswordRecovery::class);
    }

    protected function tearDown(): void
    {
        $GLOBALS["fnlla_config"] = $GLOBALS["fnlla_php_config"] = $this->config;
        $GLOBALS["fnlla_container"] = $GLOBALS["fnlla_php_container"] = $this->container;
        $_SESSION = $this->session;
        $_ENV = $this->env;
        $_SERVER = $this->server;
        putenv($this->usersEnv === false ? "DEVELOPER_ACCESS_USERS" : "DEVELOPER_ACCESS_USERS=" . $this->usersEnv);
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->directory, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($iterator as $file) {
            $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        }
        rmdir($this->directory);
    }

    public function testResetIsSingleUsePreservesMfaAndRevokesSessions(): void
    {
        $before = developer_access()->accounts()[0];
        developer_access()->grantAccess($before);
        $oldSession = $_SESSION;
        $token = $this->token();
        self::assertStringNotContainsString($token, (string) file_get_contents($this->directory . "/tokens.json"));
        self::assertTrue($this->recovery->valid($token));
        self::assertTrue($this->recovery->reset($token, "new-safe-password"));
        self::assertFalse($this->recovery->reset($token, "another-password"));
        $after = developer_access()->accounts()[0];
        self::assertTrue(password_verify("new-safe-password", $after["password_hash"]));
        self::assertFalse(password_verify("previous-password", $after["password_hash"]));
        self::assertSame($before["security"], $after["security"]);
        self::assertSame($before["role"], $after["role"]);
        self::assertStringContainsString("KEEP_ME=unchanged", (string) file_get_contents($this->directory . "/.env"));
        $_SESSION = $oldSession;
        self::assertFalse(developer_access()->isUnlocked());
        self::assertSame(1, count($this->transport->messages));
        self::assertStringNotContainsString("new-safe-password", json_encode($this->transport->messages));
    }

    public function testExpiryReplacementAndCredentialChangesInvalidateLinks(): void
    {
        $first = $this->token();
        $second = $this->token();
        self::assertFalse($this->recovery->valid($first));
        self::assertTrue($this->recovery->valid($second));
        (new LockedJsonStore($this->directory . "/tokens.json"))->update(static function (array $state): array {
            foreach ($state as &$entry) { $entry["expires_at"] = time() - 1; }
            return $state;
        });
        self::assertFalse($this->recovery->reset($second, "new-safe-password"));
        $token = $this->token();
        config_set("developer_access.users", $this->users . ";");
        self::assertFalse($this->recovery->valid($token));
        self::assertFalse($this->recovery->valid(str_repeat("a", 64)));
    }

    public function testStaleEnvironmentCannotBeOverwritten(): void
    {
        $token = $this->token();
        app(EnvironmentFileManager::class)->write(["DEVELOPER_ACCESS_USERS" => "changed-by-another-process"]);
        try {
            $this->recovery->reset($token, "new-safe-password");
            self::fail("Stale credentials were overwritten.");
        } catch (\RuntimeException) {
            self::assertStringContainsString("changed-by-another-process", (string) file_get_contents($this->directory . "/.env"));
        }
    }

    public function testPasswordPolicyAndDisabledRecovery(): void
    {
        $token = $this->token();
        foreach (["short", str_repeat("a", 73), " leading-spaces", "trailing-spaces ", "invalid\0password"] as $password) {
            self::assertFalse($this->recovery->reset($token, $password));
        }
        self::assertTrue($this->recovery->valid($token));
        config_set("developer_access.recovery_enabled", false);
        self::assertSame(null, $this->recovery->issue("dev@example.test"));
        self::assertFalse($this->recovery->reset($token, "new-safe-password"));
        self::assertSame(404, app(DeveloperRecoveryController::class)->show(new Request("GET", "/"), $this->recovery)->status());
    }

    public function testUrlUsesConfiguredOriginAndCustomPathOnly(): void
    {
        $_SERVER["HTTP_HOST"] = "attacker.test";
        $url = $this->recovery->issue("DEV@example.test");
        self::assertTrue(str_starts_with($url, "https://project.example.test/private-tools/reset-password?token="));
        foreach (["", "http://public.example", "https://user:pass@example.test", "https://example.test?override=yes"] as $url) {
            config_set("app.base_url", $url);
            try {
                $this->recovery->issue("dev@example.test");
                self::fail("Untrusted recovery URL accepted.");
            } catch (\RuntimeException) {
                self::assertTrue(true);
            }
        }
        config_set("app.base_url", "http://127.0.0.1:8080");
        self::assertTrue($this->recovery->issue("dev@example.test") !== null);
        config_set("app.environment", "production");
        $this->expectException(\RuntimeException::class);
        $this->recovery->issue("dev@example.test");
    }

    public function testRequestQueuesKnownAndUnknownAccountsAndLimitsByEmailAndIp(): void
    {
        $store = new FileQueueStore($this->directory . "/queue");
        $queue = new QueueManager(app(), $store);
        foreach (["dev@example.test", "unknown@example.test"] as $email) {
            $this->recovery->request($email, "127.0.0.1", $queue);
            $job = $store->pop();
            self::assertSame(DeveloperRecoveryMailJob::class, $job["job"]);
            self::assertSame($email, $job["payload"]["email"]);
            self::assertArrayNotHasKey("token", $job["payload"]);
            $store->complete($job);
        }
        for ($i = 0; $i < 5; $i++) {
            $this->recovery->request("dev@example.test", "other-ip", $queue);
        }
        self::assertSame(2, $queue->work(10));
        self::assertSame(2, count($this->transport->messages));
        self::assertSame(0, $queue->work(10));
        for ($i = 0; $i < 8; $i++) {
            $this->recovery->request("unknown" . $i . "@example.test", "127.0.0.1", $queue);
        }
        self::assertSame(3, $queue->work(10));
        self::assertSame(2, count($this->transport->messages));
    }

    public function testWorkerDoesNotSendUnknownExpiredOrProductionLogRequests(): void
    {
        (new DeveloperRecoveryMailJob("unknown@example.test", time()))->handle();
        (new DeveloperRecoveryMailJob("dev@example.test", time() - 3601))->handle();
        self::assertSame(0, count($this->transport->messages));
        config_set("app.environment", "production");
        config_set("mail.default", "log");
        $this->expectException(\RuntimeException::class);
        (new DeveloperRecoveryMailJob("dev@example.test", time()))->handle();
    }

    public function testControllerRemovesTokenFromUrlAndDoesNotConsumeOnGet(): void
    {
        $token = $this->token();
        $container = app();
        $router = require base_path("bootstrap/router.php");
        $controller = app(DeveloperRecoveryController::class);
        $response = $controller->edit(new Request("GET", "/private-tools/reset-password", ["token" => $token]), $this->recovery);
        self::assertSame("no-referrer", $response->headers()["Referrer-Policy"]);
        self::assertSame("private, no-store", $response->headers()["Cache-Control"]);
        self::assertStringNotContainsString($token, $response->headers()["Location"]);
        self::assertTrue($this->recovery->valid($token));
        $form = $controller->edit(new Request("GET", "/private-tools/reset-password"), $this->recovery);
        self::assertStringContainsString('autocomplete="new-password"', $form->body());
        self::assertStringNotContainsString($token, $form->body());
        $invalid = $controller->update(new Request("POST", "/", [], ["password" => "new-safe-password", "password_confirmation" => "different"]), $this->recovery);
        self::assertSame(422, $invalid->status());
        self::assertTrue($this->recovery->valid($token));
        $complete = $controller->update(new Request("POST", "/", [], ["password" => "new-safe-password", "password_confirmation" => "new-safe-password"]), $this->recovery);
        self::assertStringContainsString("Password updated", $complete->body());
        self::assertFalse($this->recovery->valid($token));
    }

    public function testRecoveryRoutesRequireCsrfAndLoginContainsSplitHero(): void
    {
        config_set("maintenance.enabled", false);
        $container = app();
        $router = require base_path("bootstrap/router.php");
        $application = new Application($router, app(), app(ExceptionHandler::class));
        $login = $application->handle(new Request("GET", "/private-tools"));
        self::assertSame(200, $login->status());
        self::assertStringContainsString("developer-sign-in-hero", $login->body());
        self::assertStringContainsString("Forgot password?", $login->body());
        foreach (["forgot-password", "reset-password"] as $path) {
            $response = $application->handle(new Request("POST", "/private-tools/" . $path, [], [], [], ["accept" => "application/json"]));
            self::assertSame(419, $response->status());
        }
    }

    private function token(): string
    {
        $url = $this->recovery->issue("dev@example.test");
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
        return $query["token"];
    }

    public function testDuplicateCredentialsAndInvalidValuesLeaveEnvironmentUntouched(): void
    {
        $environment = app(EnvironmentFileManager::class);
        $before = (string) file_get_contents($this->directory . "/.env");
        try {
            $environment->write(["KEEP_ME" => "changed", "INVALID" => "line\nbreak"]);
            self::fail("Invalid environment value accepted.");
        } catch (\RuntimeException) {
            self::assertSame($before, file_get_contents($this->directory . "/.env"));
        }
        $token = $this->token();
        $duplicate = $before . "\nDEVELOPER_ACCESS_USERS=" . $this->users . "\n";
        file_put_contents($this->directory . "/.env", $duplicate);
        try {
            $this->recovery->reset($token, "new-safe-password");
            self::fail("Ambiguous credentials accepted.");
        } catch (\RuntimeException) {
            self::assertSame($duplicate, file_get_contents($this->directory . "/.env"));
        }
    }

    public function testPublicResponsesDoNotRevealAccountsOrTokens(): void
    {
        $container = app();
        $router = require base_path("bootstrap/router.php");
        $queue = new QueueManager($container, new FileQueueStore($this->directory . "/queue"));
        $controller = app(DeveloperRecoveryController::class);
        $known = $controller->send(new Request("POST", "/", [], ["email" => "dev@example.test"]), $this->recovery, $queue);
        $unknown = $controller->send(new Request("POST", "/", [], ["email" => "unknown@example.test"]), $this->recovery, $queue);
        self::assertSame(200, $known->status());
        self::assertSame($known->body(), $unknown->body());
        self::assertSame($known->headers(), $unknown->headers());
        self::assertFalse(is_file($this->directory . "/tokens.json"));
        self::assertSame(0, count($this->transport->messages));
    }

    public function testDeliveryFailureDoesNotUndoCompletedPasswordChange(): void
    {
        $token = $this->token();
        app()->instance(MailTransportInterface::class, new class implements MailTransportInterface {
            public function send(array $message): void { throw new \RuntimeException("Synthetic mail failure"); }
        });
        self::assertTrue($this->recovery->reset($token, "new-safe-password"));
        self::assertFalse($this->recovery->valid($token));
        self::assertTrue(password_verify("new-safe-password", developer_access()->accounts()[0]["password_hash"]));
    }
}

final class RecoveryTestTransport implements MailTransportInterface
{
    public array $messages = [];
    public function send(array $message): void { $this->messages[] = $message; }
}
