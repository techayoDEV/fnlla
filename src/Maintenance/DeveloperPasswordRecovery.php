<?php

declare(strict_types=1);

namespace Fnlla\Php\Maintenance;

use Fnlla\Php\Mail\Mailer;
use Fnlla\Php\Queue\QueueManager;
use Fnlla\Php\Support\EnvironmentFileManager;
use Fnlla\Php\Support\LockedJsonStore;
use Fnlla\Php\Support\Logger;
use RuntimeException;

final class DeveloperPasswordRecovery
{
    public function __construct(private DeveloperAccessManager $access, private EnvironmentFileManager $environment)
    {
    }

    public function enabled(): bool
    {
        return $this->access->enabled() && $this->access->configured()
            && (bool) config("developer_access.recovery_enabled", true);
    }

    public function request(string $email, string $ip, QueueManager $queue): void
    {
        $email = strtolower(trim($email));
        if (!$this->enabled() || strlen($email) > 160 || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            return;
        }
        // Check and increment together, independently of account existence and mail latency.
        $allowed = false;
        $this->store("limits")->update(function (array $state) use ($email, $ip, &$allowed): array {
            $now = time();
            $state = array_filter($state, static fn (array $entry): bool => $entry["until"] > $now);
            $keys = [hash("sha256", "ip:" . $ip) => 5, hash("sha256", "email:" . $email) => 3];
            if (count($state) > 8000) {
                return $state;
            }
            foreach ($keys as $key => $max) {
                if (($state[$key]["count"] ?? 0) >= $max) {
                    return $state;
                }
            }
            foreach ($keys as $key => $max) {
                $state[$key] = ["count" => ($state[$key]["count"] ?? 0) + 1, "until" => $state[$key]["until"] ?? $now + 3600];
            }
            $allowed = true;
            return $state;
        });
        if ($allowed) {
            $queue->push(DeveloperRecoveryMailJob::class, ["email" => $email, "requestedAt" => time()]);
        }
    }

    /** Only server-side callers receive the bearer URL; never return it from an HTTP action. */
    public function issue(string $email): ?string
    {
        if (!$this->enabled()) {
            return null;
        }
        $email = strtolower(trim($email));
        $account = null;
        foreach ($this->access->accounts() as $candidate) {
            if ($candidate["email"] === $email) {
                $account = $candidate;
            }
        }
        if ($account === null) {
            return null;
        }
        $url = $this->baseUrl() . $this->access->path() . "/reset-password";
        $token = bin2hex(random_bytes(32));
        $this->store("tokens")->update(function (array $state) use ($email, $token): array {
            $state = array_filter($state, static fn (array $entry): bool => $entry["expires_at"] > time());
            $state[hash("sha256", $email)] = [
                "email" => $email,
                "digest" => hash("sha256", $token),
                "credentials" => $this->fingerprint(),
                "expires_at" => time() + max(5, min(60, (int) config("developer_access.recovery_ttl_minutes", 30))) * 60,
            ];
            return $state;
        });
        return $url . "?token=" . $token;
    }

    public function valid(string $token): bool
    {
        return $this->enabled() && $this->matchingKey($this->store("tokens")->read(), $token) !== null;
    }

    public function reset(string $token, string $password): bool
    {
        if (!$this->enabled() || strlen($password) < 12 || strlen($password) > 72 || trim($password) !== $password || str_contains($password, "\0")) {
            return false;
        }
        $changedEmail = null;
        $this->store("tokens")->update(function (array $state) use ($token, $password, &$changedEmail): array {
            $key = $this->matchingKey($state, $token);
            if ($key === null) {
                return $state;
            }
            $email = $state[$key]["email"];
            $accounts = $this->access->accounts();
            foreach ($accounts as &$account) {
                if ($account["email"] === $email) {
                    // Preserve role, profile and MFA; only the password changes.
                    $account["password_hash"] = password_hash($password, PASSWORD_DEFAULT);
                }
            }
            unset($account);
            $serialized = $this->access->serializeAccounts($accounts);
            $values = ["DEVELOPER_ACCESS_USERS" => $serialized];
            $this->environment->writeCompared($values, ["DEVELOPER_ACCESS_USERS" => (string) config("developer_access.users", "")]);
            $this->environment->apply($values);
            config_set("developer_access.users", $serialized);
            // A crash before token persistence still leaves every token invalid by fingerprint.
            unset($state[$key]);
            $changedEmail = $email;
            return $state;
        });
        if ($changedEmail === null) {
            return false;
        }
        $this->access->lock();
        Logger::write("notice", "Developer password recovered; existing developer sessions invalidated.", ["event" => "developer_password_recovered"]);
        try {
            if ($this->mailAllowed()) {
                app(Mailer::class)->send($changedEmail, "Developer password changed", "<p>Your developer password has changed. If this was not you, contact the project owner immediately.</p>");
            }
        } catch (\Throwable) {
            Logger::write("error", "Developer password-change notification failed.", ["event" => "developer_recovery_mail_failed"]);
        }
        return true;
    }

    public function mailAllowed(): bool
    {
        return config("mail.default", "log") !== "log"
            || in_array(config("app.environment"), ["development", "testing"], true);
    }

    private function baseUrl(): string
    {
        $url = rtrim((string) config("app.base_url", ""), "/");
        $parts = parse_url($url);
        $localHttp = is_array($parts) && ($parts["scheme"] ?? "") === "http"
            && in_array($parts["host"] ?? "", ["localhost", "127.0.0.1", "[::1]"], true)
            && in_array(config("app.environment"), ["development", "testing"], true);
        if (!is_array($parts) || filter_var($url, FILTER_VALIDATE_URL) === false
            || (!$localHttp && ($parts["scheme"] ?? "") !== "https")
            || isset($parts["user"]) || isset($parts["pass"]) || isset($parts["query"]) || isset($parts["fragment"])) {
            throw new RuntimeException("Developer recovery requires a trusted HTTPS APP_URL (loopback HTTP is allowed in development).");
        }
        return $url;
    }

    private function fingerprint(): string
    {
        return hash("sha256", (string) config("developer_access.users", ""));
    }

    private function matchingKey(array $state, string $token): ?string
    {
        if (preg_match('/^[a-f0-9]{64}$/D', $token) !== 1) {
            return null;
        }
        foreach ($state as $key => $entry) {
            if ($entry["expires_at"] > time() && hash_equals($entry["digest"], hash("sha256", $token))
                && hash_equals($entry["credentials"], $this->fingerprint())) {
                return (string) $key;
            }
        }
        return null;
    }

    private function store(string $name): LockedJsonStore
    {
        $directory = (string) config("developer_access.recovery_path", storage_path("framework/developer-recovery"));
        return new LockedJsonStore($directory . "/" . $name . ".json");
    }
}
