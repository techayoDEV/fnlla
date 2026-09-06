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

final class DeveloperProfileController extends DeveloperPanelController
{
    public function profile(Request $request, DeveloperAccessManager $developerAccess, MaintenanceAccessManager $maintenanceAccess): Response
    {
        return $this->renderDeveloperPanel(
            $developerAccess,
            $maintenanceAccess,
            "developer/profile",
            "Developer Profile",
            "profile"
        );
    }

    public function security(Request $request, DeveloperAccessManager $developerAccess, MaintenanceAccessManager $maintenanceAccess): Response
    {
        if (!$this->ensureDeveloperCapability($developerAccess, "developer.security.manage")) {
            return $this->redirect(route("developer.panel.profile"));
        }

        return $this->renderDeveloperPanel(
            $developerAccess,
            $maintenanceAccess,
            "developer/access-settings",
            "Access & Security",
            "access"
        );
    }

    public function updateDeveloperPassword(
        Request $request,
        DeveloperAccessManager $developerAccess,
        EnvironmentFileManager $environmentFileManager
    ): Response {
        if (!$this->ensureDeveloperCapability($developerAccess, "developer.profile.write")) {
            return $this->redirect($this->developerPasswordRedirect($request));
        }

        $payload = [
            "developer_access_password" => trim((string) $request->input("developer_access_password", "")),
            "developer_access_password_confirmation" => trim((string) $request->input("developer_access_password_confirmation", "")),
        ];

        try {
            $this->validate($payload, [
                "developer_access_password" => ["required", "string", "min:8", "max:255", "confirmed"],
            ]);
        } catch (ValidationException $exception) {
            flash_set("errors", $exception->errors());
            flash_set("status", [
                "variant" => "warning",
                "title" => "Developer password still needs attention",
                "text" => "Review the developer password fields and save them again.",
                "toast" => false,
            ]);
            regenerate_csrf_token();

            return $this->redirect($this->developerPasswordRedirect($request));
        }

        $passwordHash = password_hash($payload["developer_access_password"], PASSWORD_DEFAULT);
        $currentDeveloper = $developerAccess->currentDeveloper();
        $currentEmail = trim((string) ($currentDeveloper["email"] ?? ""));

        if ($currentEmail === "") {
            flash_set("status", [
                "variant" => "warning",
                "title" => "Named developer account required",
                "text" => "Password-only developer access is no longer supported. Activate a named developer account before rotating credentials.",
                "toast" => false,
            ]);
            regenerate_csrf_token();

            return $this->redirect(route("developer.panel.access"));
        }

        try {
            $accounts = $developerAccess->upsertAccount([
                "email" => $currentEmail,
                "name" => (string) ($currentDeveloper["name"] ?? "Developer"),
                "role" => (string) ($currentDeveloper["role"] ?? "lead_developer"),
                "password_hash" => $passwordHash,
            ]);
            $serializedAccounts = $developerAccess->serializeAccounts($accounts);
            $environmentValues = [
                "DEVELOPER_ACCESS_EMAIL" => $currentEmail,
                "DEVELOPER_ACCESS_USERS" => $serializedAccounts,
            ];

            $environmentFileManager->write($environmentValues);
            $environmentFileManager->apply($environmentValues);
            $environmentFileManager->remove(["DEVELOPER_ACCESS_PASSWORD", "DEVELOPER_ACCESS_PASSWORD_HASH"]);
        } catch (\RuntimeException $exception) {
            flash_set("status", [
                "variant" => "danger",
                "title" => "Developer password could not be saved",
                "text" => $exception->getMessage(),
                "toast" => false,
            ]);
            regenerate_csrf_token();

            return $this->redirect($this->developerPasswordRedirect($request));
        }

        config_set("developer_access", array_merge((array) config("developer_access", []), [
            "email" => $currentEmail,
            "password" => "",
            "password_hash" => "",
            "users" => $environmentValues["DEVELOPER_ACCESS_USERS"],
        ]));
        $developerAccess->grantAccess($this->developerAccountByEmail($developerAccess->accounts(), $currentEmail));
        Logger::write("notice", "Developer password updated", [
            "event" => "developer_password_updated",
            "email" => $currentEmail,
        ]);
        developer_activity()->record(
            "developer_access",
            "Developer password rotated",
            "A named developer account password was rotated.",
            $developerAccess->currentDeveloper()
        );

        flash_set("status", [
            "variant" => "success",
            "title" => "Developer password updated",
            "text" => "The hidden panel password was saved and this browser session stayed unlocked.",
            "toast" => true,
        ]);
        regenerate_csrf_token();

        return $this->redirect($this->developerPasswordRedirect($request));
    }

    public function saveDeveloperProfile(
        Request $request,
        DeveloperAccessManager $developerAccess,
        EnvironmentFileManager $environmentFileManager
    ): Response {
        if (!$this->ensureDeveloperCapability($developerAccess, "developer.profile.write")) {
            return $this->redirect(route("developer.panel.profile"));
        }

        $currentDeveloper = $developerAccess->currentDeveloper();
        $currentEmail = strtolower(trim((string) ($currentDeveloper["email"] ?? "")));
        $roleOptions = $developerAccess->roleOptions();
        $payload = [
            "developer_profile_name" => trim((string) $request->input("developer_profile_name", "")),
            "developer_profile_role" => strtolower(str_replace([" ", "-"], "_", trim((string) $request->input("developer_profile_role", "")))),
            "developer_profile_avatar" => trim((string) $request->input("developer_profile_avatar", "")),
            "developer_profile_generate_avatar" => (string) $request->input("developer_profile_generate_avatar", "") === "1",
            "developer_profile_remove_avatar" => (string) $request->input("developer_profile_remove_avatar", "") === "1",
        ];

        if (!array_key_exists($payload["developer_profile_role"], $roleOptions)) {
            $payload["developer_profile_role"] = "application_developer";
        }

        try {
            $validationPayload = [
                "developer_profile_name" => $payload["developer_profile_name"],
                "developer_profile_role" => $payload["developer_profile_role"],
                "developer_profile_avatar" => $payload["developer_profile_avatar"],
            ];
            $validationRules = [
                "developer_profile_name" => ["required", "string", "min:2", "max:100"],
                "developer_profile_role" => ["required", "string"],
                "developer_profile_avatar" => ["nullable", "string", "max:2048"],
            ];

            $this->validate($validationPayload, $validationRules);
        } catch (ValidationException $exception) {
            flash_set("errors", $exception->errors());
            flash_set("old", [
                "developer_profile_name" => $payload["developer_profile_name"],
                "developer_profile_role" => $payload["developer_profile_role"],
                "developer_profile_avatar" => $payload["developer_profile_avatar"],
            ]);
            flash_set("status", [
                "variant" => "warning",
                "title" => "Developer profile still needs attention",
                "text" => "Use a display name, a supported developer role and an optional short avatar mark or HTTP(S) image URL.",
                "toast" => false,
            ]);
            regenerate_csrf_token();

            return $this->redirect(route("developer.panel.profile"));
        }

        try {
            $payload["developer_profile_avatar"] = $this->resolveDeveloperAvatar($request, $payload);
        } catch (\RuntimeException $exception) {
            flash_set("old", [
                "developer_profile_name" => $payload["developer_profile_name"],
                "developer_profile_role" => $payload["developer_profile_role"],
                "developer_profile_avatar" => $payload["developer_profile_avatar"],
            ]);
            flash_set("status", [
                "variant" => "warning",
                "title" => "Developer avatar could not be saved",
                "text" => $exception->getMessage(),
                "toast" => false,
            ]);
            regenerate_csrf_token();

            return $this->redirect(route("developer.panel.profile"));
        }

        if ($currentEmail === "") {
            flash_set("status", [
                "variant" => "warning",
                "title" => "Named developer account required",
                "text" => "Password-only developer access is no longer supported. Activate a named developer account before editing profile details.",
                "toast" => false,
            ]);
            regenerate_csrf_token();

            return $this->redirect(route("developer.panel.access"));
        }

        $accounts = $developerAccess->updateAccountProfile($currentEmail, [
            "name" => $payload["developer_profile_name"],
            "role" => $payload["developer_profile_role"],
            "avatar" => $payload["developer_profile_avatar"],
        ]);
        $serializedAccounts = $developerAccess->serializeAccounts($accounts);

        try {
            $environmentFileManager->write([
                "DEVELOPER_ACCESS_EMAIL" => $currentEmail,
                "DEVELOPER_ACCESS_USERS" => $serializedAccounts,
            ]);
            $environmentFileManager->apply([
                "DEVELOPER_ACCESS_EMAIL" => $currentEmail,
                "DEVELOPER_ACCESS_USERS" => $serializedAccounts,
            ]);
            $environmentFileManager->remove(["DEVELOPER_ACCESS_PASSWORD", "DEVELOPER_ACCESS_PASSWORD_HASH"]);
        } catch (\RuntimeException $exception) {
            flash_set("status", [
                "variant" => "danger",
                "title" => "Developer profile could not be saved",
                "text" => $exception->getMessage(),
                "toast" => false,
            ]);
            regenerate_csrf_token();

            return $this->redirect(route("developer.panel.profile"));
        }

        config_set("developer_access", array_merge((array) config("developer_access", []), [
            "email" => $currentEmail,
            "password" => "",
            "password_hash" => "",
            "users" => $serializedAccounts,
        ]));
        $developerAccess->grantAccess($this->developerAccountByEmail($developerAccess->accounts(), $currentEmail));
        developer_activity()->record(
            "developer_profile",
            "Developer profile updated",
            "A developer changed their display name, role or avatar metadata.",
            $developerAccess->currentDeveloper()
        );

        flash_set("status", [
            "variant" => "success",
            "title" => "Developer profile saved",
            "text" => "Your developer profile was saved globally for this project.",
            "toast" => true,
        ]);
        regenerate_csrf_token();

        return $this->redirect(route("developer.panel.profile"));
    }

    public function updateDeveloperSecurity(
        Request $request,
        DeveloperAccessManager $developerAccess,
        EnvironmentFileManager $environmentFileManager
    ): Response {
        if (!$this->ensureDeveloperCapability($developerAccess, "developer.security.manage")) {
            return $this->redirect(route("developer.panel.security"));
        }

        $currentDeveloper = $developerAccess->currentDeveloper();
        $currentEmail = strtolower(trim((string) ($currentDeveloper["email"] ?? "")));

        if ($currentEmail === "") {
            flash_set("status", [
                "variant" => "warning",
                "title" => "Named developer required",
                "text" => "Create a named developer account before enabling two-factor security.",
                "toast" => false,
            ]);
            regenerate_csrf_token();

            return $this->redirect(route("developer.panel.access"));
        }

        $action = trim((string) $request->input("developer_security_action", ""));
        $state = $developerAccess->securityState($currentDeveloper);

        if ($action === "generate_totp") {
            $accounts = $developerAccess->updateAccountSecurity($currentEmail, [
                "totp_secret" => $developerAccess->newTotpSecret(),
                "totp_enabled" => false,
            ]);
            $message = "Authenticator setup key generated. Confirm it with a six-digit code before it is enforced.";
        } elseif ($action === "enable_totp") {
            $code = trim((string) $request->input("developer_totp_code", ""));

            if (!($state["totp_configured"] ?? false) || !$developerAccess->verifyTotpForEmail($currentEmail, $code)) {
                flash_set("status", [
                    "variant" => "warning",
                    "title" => "Authenticator code rejected",
                    "text" => "Enter the current six-digit code from your authenticator app before enabling TOTP.",
                    "toast" => false,
                ]);
                regenerate_csrf_token();

                return $this->redirect(route("developer.panel.security"));
            }

            $accounts = $developerAccess->updateAccountSecurity($currentEmail, [
                "totp_secret" => (string) ($state["totp_secret"] ?? ""),
                "totp_enabled" => true,
            ]);
            $message = "Two-factor authentication is now required for this developer account.";
        } elseif ($action === "disable_totp") {
            $accounts = $developerAccess->updateAccountSecurity($currentEmail, [
                "totp_secret" => "",
                "totp_enabled" => false,
            ]);
            $message = "Two-factor authentication was disabled for this developer account.";
        } elseif ($action === "passkey_contract") {
            $label = trim((string) $request->input("developer_passkey_label", ""));
            $adapter = trim((string) $request->input("developer_passkey_adapter", "disabled"));

            try {
                $this->validate([
                    "developer_passkey_label" => $label,
                    "developer_passkey_adapter" => $adapter,
                ], [
                    "developer_passkey_label" => ["nullable", "string", "max:100"],
                    "developer_passkey_adapter" => ["required", "string"],
                ]);
            } catch (ValidationException $exception) {
                flash_set("errors", $exception->errors());
                regenerate_csrf_token();

                return $this->redirect(route("developer.panel.security"));
            }

            $accounts = $developerAccess->updateAccountSecurity($currentEmail, [
                "passkey_label" => $label,
                "passkey_adapter" => $adapter === "external" ? "external" : "disabled",
            ]);
            $message = $adapter === "external"
                ? "Passkey adapter metadata was saved. The actual WebAuthn provider remains project-owned."
                : "Passkey adapter metadata was cleared.";
        } else {
            return $this->redirect(route("developer.panel.security"));
        }

        $serializedAccounts = $developerAccess->serializeAccounts($accounts);

        try {
            $environmentFileManager->write([
                "DEVELOPER_ACCESS_EMAIL" => $accounts[0]["email"] ?? $currentEmail,
                "DEVELOPER_ACCESS_USERS" => $serializedAccounts,
            ]);
            $environmentFileManager->apply([
                "DEVELOPER_ACCESS_EMAIL" => $accounts[0]["email"] ?? $currentEmail,
                "DEVELOPER_ACCESS_USERS" => $serializedAccounts,
            ]);
            $environmentFileManager->remove(["DEVELOPER_ACCESS_PASSWORD", "DEVELOPER_ACCESS_PASSWORD_HASH"]);
        } catch (\RuntimeException $exception) {
            flash_set("status", [
                "variant" => "danger",
                "title" => "Developer security could not be saved",
                "text" => $exception->getMessage(),
                "toast" => false,
            ]);
            regenerate_csrf_token();

            return $this->redirect(route("developer.panel.security"));
        }

        config_set("developer_access", array_merge((array) config("developer_access", []), [
            "email" => (string) ($accounts[0]["email"] ?? $currentEmail),
            "password" => "",
            "password_hash" => "",
            "users" => $serializedAccounts,
        ]));
        $developerAccess->grantAccess($this->developerAccountByEmail($developerAccess->accounts(), $currentEmail));
        developer_activity()->record(
            "developer_security",
            "Developer security updated",
            $message,
            $developerAccess->currentDeveloper()
        );

        flash_set("status", [
            "variant" => "success",
            "title" => "Developer security saved",
            "text" => $message,
            "toast" => true,
        ]);
        regenerate_csrf_token();

        return $this->redirect(route("developer.panel.security"));
    }

    private function developerPasswordRedirect(Request $request): string
    {
        return (string) $request->input("developer_password_redirect", "") === "profile"
            ? route("developer.panel.profile")
            : route("developer.panel.access");
    }

    private function resolveDeveloperAvatar(Request $request, array $payload): string
    {
        if (($payload["developer_profile_remove_avatar"] ?? false) === true) {
            $this->removeLocalDeveloperAvatar((string) ($payload["developer_profile_avatar"] ?? ""));

            return "";
        }

        $uploaded = $request->file("developer_profile_avatar_file");

        if ($uploaded instanceof UploadedFile && $uploaded->error() !== UPLOAD_ERR_NO_FILE && !$uploaded->isValid()) {
            throw new \RuntimeException($this->developerAvatarUploadErrorMessage($uploaded->error()));
        }

        if ($uploaded instanceof UploadedFile && $uploaded->isValid()) {
            $uploaded->validate(max(1, (int) config("security.uploads.max_file_bytes", 5242880)), ["image/jpeg", "image/png", "image/webp"]);
            $storedPath = $uploaded->store("developer-avatars", "public");

            return "/uploads/" . trim($storedPath, "/");
        }

        if (($payload["developer_profile_generate_avatar"] ?? false) === true) {
            return $this->writeGeneratedDeveloperAvatar((string) ($payload["developer_profile_name"] ?? "Developer"));
        }

        return (string) ($payload["developer_profile_avatar"] ?? "");
    }

    private function removeLocalDeveloperAvatar(string $avatar): void
    {
        $relativePath = ltrim(str_replace("\\", "/", trim($avatar)), "/");

        if (
            !str_starts_with($relativePath, "uploads/developer-avatars/")
            || str_contains($relativePath, "\0")
            || str_contains($relativePath, "..")
        ) {
            return;
        }

        $absolutePath = public_path($relativePath);
        $rootPath = public_path("uploads/developer-avatars");
        $realRoot = realpath($rootPath);
        $realFile = is_file($absolutePath) ? realpath($absolutePath) : false;

        if (!is_string($realRoot) || !is_string($realFile)) {
            return;
        }

        $normalisedRoot = rtrim(str_replace("\\", "/", $realRoot), "/") . "/";
        $normalisedFile = str_replace("\\", "/", $realFile);

        if (str_starts_with(strtolower($normalisedFile), strtolower($normalisedRoot))) {
            @unlink($realFile);
        }
    }

    private function developerAvatarUploadErrorMessage(int $error): string
    {
        return $this->uploadErrorMessage("Uploaded avatar", $error);
    }

    private function writeGeneratedDeveloperAvatar(string $name): string
    {
        $mark = strtoupper(substr((string) preg_replace('/[^A-Za-z0-9]/', "", $name), 0, 2));
        $mark = $mark !== "" ? $mark : "DV";
        $filename = "avatar-" . sha1(strtolower($name) . "|" . microtime(true)) . ".svg";
        $relativePath = "uploads/developer-avatars/" . $filename;
        $absolutePath = public_path($relativePath);
        $directory = dirname($absolutePath);

        if (!is_dir($directory)) {
            mkdir($directory, 0777, true);
        }

        $safeMark = htmlspecialchars($mark, ENT_QUOTES, "UTF-8");
        $svg = <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 128 128" role="img" aria-label="Developer avatar">
  <rect width="128" height="128" rx="26" fill="#eff6ff"/>
  <rect x="8" y="8" width="112" height="112" rx="22" fill="#ffffff" stroke="#2563eb" stroke-opacity=".28"/>
  <text x="64" y="74" fill="#2563eb" font-family="Arial, Helvetica, sans-serif" font-size="38" font-weight="800" text-anchor="middle">{$safeMark}</text>
</svg>
SVG;

        file_put_contents($absolutePath, $svg . PHP_EOL, LOCK_EX);

        return "/" . $relativePath;
    }
}
