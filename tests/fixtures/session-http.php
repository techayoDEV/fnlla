<?php

declare(strict_types=1);

// Copied into a private temporary HTTP server by SessionHttpTest, never exported.
define("FNLLA_RUNTIME_SKIP_AUTO_GUARD", true);
define("FNLLA_CACHE_ROOT", __DIR__ . "/cache");
$container = require (string) getenv("FNLLA_SESSION_TEST_BOOTSTRAP");
config_set("app.session_path", __DIR__ . "/sessions");
config_set("session.driver", (string) (getenv("FNLLA_SESSION_TEST_DRIVER") ?: "file"));
if (config("session.driver") === "redis") {
    config_set("session.redis", json_decode((string) getenv("FNLLA_SESSION_TEST_REDIS"), true, 512, JSON_THROW_ON_ERROR));
}
config_set("session.name", "fnlla_test_session");
config_set("session.secure", true);
config_set("session.http_only", true);
config_set("session.strict_mode", true);
config_set("session.use_only_cookies", true);
config_set("session.same_site", "Lax");
config_set("session.domain", "");
config_set("session.lifetime_minutes", 2);
config_set("session.absolute_lifetime_minutes", 5);
config_set("session.rotate_after_minutes", 1);
config_set("auth.session_key", "auth.user_id");
config_set("auth.providers.users.key", "id");
config_set("developer_access.enabled", true);
config_set("customer_access.enabled", true);
$session = new \Fnlla\Php\Session\SessionStore();
$provider = new class implements \Fnlla\Php\Auth\UserProviderInterface {
    public function findById(string|int $id): ?array { return $id === 7 ? ["id" => 7] : null; }
    public function findByCredentials(array $credentials): ?array { return null; }
};
$auth = new \Fnlla\Php\Auth\AuthManager($session, $provider, new \Fnlla\Php\Hashing\Hasher());
$developer = $container->make(\Fnlla\Php\Maintenance\DeveloperAccessManager::class);
$customer = $container->make(\Fnlla\Php\Maintenance\CustomerAccessManager::class);
$developerAccount = ["email" => "dev@example.com", "name" => "Developer", "role" => "application_developer", "password_hash" => "synthetic-hash"];
$ownerAccount = ["email" => "owner@example.com", "name" => "Owner", "role" => "owner_developer", "password_hash" => "synthetic-owner-hash"];
config_set("developer_access.users", $developer->serializeAccounts([$ownerAccount, $developerAccount]));
config_set("customer_access.users", $customer->serializeAccounts([["email" => "client@example.com", "name" => "Customer", "password_hash" => "synthetic-hash"]]));
$action = (string) ($_GET["action"] ?? "state");
if ($action === "storage-independent") { config_set("app.session_path", __FILE__ . "/not-a-directory"); }
if ($action === "late-start") {
    echo "output-before-session\n";
    flush();
    try { $session->put("auth.user_id", 7); echo "unsafe-success"; }
    catch (\RuntimeException) { echo "session-rejected"; }
    return;
}
$session->get("cart");
$before = session_id();
switch ($action) {
    case "login": $auth->login(["id" => 7]); $session->put("cart", [42]); break;
    case "developer": $developer->grantAccess($developerAccount); break;
    case "refresh-developer": $developer->grantAccess(); break;
    case "remove-developer": config_set("developer_access.users", $developer->serializeAccounts([$ownerAccount])); $developer->grantAccess(); break;
    case "customer": $customer->grantAccess(); break;
    case "logout": $auth->logout(); break;
    case "invalidate": $session->invalidate(); break;
    case "idle": $_SESSION["_meta"]["started_at"] = time() - 150; $_SESSION["_meta"]["last_activity_at"] = time() - 120; break;
    case "absolute": $_SESSION["_meta"]["started_at"] = time() - 300; break;
    case "corrupt": $_SESSION["_meta"]["started_at"] = "invalid"; break;
    case "future": $_SESSION["_meta"]["last_activity_at"] = time() + 300; break;
    case "missing-meta": unset($_SESSION["_meta"]); break;
    case "legacy": unset($_SESSION["_meta"]["last_activity_at"]); break;
    case "rotate": $_SESSION["_meta"]["last_regenerated_at"] = time() - 60; break;
    case "revoke-developer": $developerAccount["password_hash"] = "changed-hash"; config_set("developer_access.users", $developer->serializeAccounts([$ownerAccount, $developerAccount])); break;
}
// Return before checking a deliberately damaged state; the next HTTP request must enforce it.
$seeded = in_array($action, ["idle", "absolute", "corrupt", "future", "missing-meta", "legacy", "rotate"], true);
$state = ["id" => session_id(), "before" => $before];
if (!$seeded) {
    $state += ["application" => $auth->check(), "developer" => $developer->isUnlocked(),
        "developer_email" => $developer->isUnlocked() ? $developer->currentDeveloper()["email"] : null,
        "customer" => $customer->isUnlocked(), "cart" => $session->get("cart"),
        "started_at" => $_SESSION["_meta"]["started_at"]];
    $state["id"] = session_id();
}
session_write_close();
header("Content-Type: application/json");
echo json_encode($state, JSON_THROW_ON_ERROR);
