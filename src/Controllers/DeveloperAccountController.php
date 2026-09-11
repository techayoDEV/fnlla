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

final class DeveloperAccountController extends DeveloperPanelController
{
    public function accessSettings(Request $request, DeveloperAccessManager $developerAccess, MaintenanceAccessManager $maintenanceAccess): Response
    {
        return $this->renderDeveloperPanel(
            $developerAccess,
            $maintenanceAccess,
            "developer/access-settings",
            "Access & security",
            "access"
        );
    }

    public function saveDeveloperAccount(
        Request $request,
        DeveloperAccessManager $developerAccess,
        EnvironmentFileManager $environmentFileManager
    ): Response {
        if (!$this->ensureDeveloperCapability($developerAccess, "developer.accounts.write")) {
            return $this->redirect(route("developer.panel.access"));
        }

        $payload = [
            "developer_account_email" => strtolower(trim((string) $request->input("developer_account_email", ""))),
            "developer_account_name" => trim((string) $request->input("developer_account_name", "Developer")),
            "developer_account_role" => strtolower(str_replace([" ", "-"], "_", trim((string) $request->input("developer_account_role", "application_developer")))),
            "developer_account_password" => trim((string) $request->input("developer_account_password", "")),
            "developer_account_password_confirmation" => trim((string) $request->input("developer_account_password_confirmation", "")),
        ];

        if (!array_key_exists($payload["developer_account_role"], $developerAccess->roleOptions())) {
            $payload["developer_account_role"] = "application_developer";
        }

        try {
            $this->validate($payload, [
                "developer_account_email" => ["required", "email", "max:160"],
                "developer_account_name" => ["required", "string", "min:2", "max:100"],
                "developer_account_role" => ["required", "string"],
                "developer_account_password" => ["required", "string", "min:8", "max:255", "confirmed"],
            ]);
        } catch (ValidationException $exception) {
            flash_set("errors", $exception->errors());
            flash_set("old", [
                "developer_account_email" => $payload["developer_account_email"],
                "developer_account_name" => $payload["developer_account_name"],
                "developer_account_role" => $payload["developer_account_role"],
            ]);
            flash_set("status", [
                "variant" => "warning",
                "title" => "Developer account still needs attention",
                "text" => "Use a valid email, name, role and confirmed password for the developer account.",
                "toast" => false,
            ]);
            regenerate_csrf_token();

            return $this->redirect(route("developer.panel.access"));
        }

        $currentDeveloper = $developerAccess->currentDeveloper();
        $accounts = $developerAccess->upsertAccount([
            "email" => $payload["developer_account_email"],
            "name" => $payload["developer_account_name"],
            "role" => $payload["developer_account_role"],
            "password_hash" => password_hash($payload["developer_account_password"], PASSWORD_DEFAULT),
        ]);
        $serializedAccounts = $developerAccess->serializeAccounts($accounts);
        $environmentValues = [
            "DEVELOPER_ACCESS_EMAIL" => $accounts[0]["email"] ?? $payload["developer_account_email"],
            "DEVELOPER_ACCESS_USERS" => $serializedAccounts,
        ];

        try {
            $environmentFileManager->write($environmentValues);
            $environmentFileManager->apply($environmentValues);
            $environmentFileManager->remove(["DEVELOPER_ACCESS_PASSWORD", "DEVELOPER_ACCESS_PASSWORD_HASH"]);
        } catch (\RuntimeException $exception) {
            flash_set("status", [
                "variant" => "danger",
                "title" => "Developer account could not be saved",
                "text" => $exception->getMessage(),
                "toast" => false,
            ]);
            regenerate_csrf_token();

            return $this->redirect(route("developer.panel.access"));
        }

        config_set("developer_access", array_merge((array) config("developer_access", []), [
            "email" => (string) $environmentValues["DEVELOPER_ACCESS_EMAIL"],
            "password" => "",
            "password_hash" => "",
            "users" => $serializedAccounts,
        ]));
        $developerAccess->grantAccess($this->developerAccountByEmail($developerAccess->accounts(), (string) ($currentDeveloper["email"] ?? "")));
        developer_activity()->record(
            "developer_access",
            "Developer account saved",
            "A named developer account was added or rotated in the project access list.",
            $developerAccess->currentDeveloper()
        );

        flash_set("status", [
            "variant" => "success",
            "title" => "Developer account saved",
            "text" => "The named developer account was saved globally. Other developer sessions will see this change in the activity feed.",
            "toast" => true,
        ]);
        regenerate_csrf_token();

        return $this->redirect(route("developer.panel.access"));
    }

    public function deleteDeveloperAccount(
        Request $request,
        DeveloperAccessManager $developerAccess,
        EnvironmentFileManager $environmentFileManager
    ): Response {
        if (!$this->ensureDeveloperCapability($developerAccess, "developer.accounts.write")) {
            return $this->redirect(route("developer.panel.access"));
        }

        $email = strtolower(trim((string) $request->input("developer_account_email", "")));
        $currentEmail = strtolower(trim((string) ($developerAccess->currentDeveloper()["email"] ?? "")));

        if ($email === "" || $email === $currentEmail || count($developerAccess->accounts()) <= 1) {
            flash_set("status", [
                "variant" => "warning",
                "title" => "Developer account was not deactivated",
                "text" => "The current account and the final remaining developer account cannot be removed from the panel.",
                "toast" => false,
            ]);
            regenerate_csrf_token();

            return $this->redirect(route("developer.panel.access"));
        }

        $accounts = $developerAccess->removeAccount($email);
        $serializedAccounts = $developerAccess->serializeAccounts($accounts);

        try {
            $environmentFileManager->write([
                "DEVELOPER_ACCESS_EMAIL" => $accounts[0]["email"] ?? "",
                "DEVELOPER_ACCESS_USERS" => $serializedAccounts,
            ]);
            $environmentFileManager->apply([
                "DEVELOPER_ACCESS_EMAIL" => $accounts[0]["email"] ?? "",
                "DEVELOPER_ACCESS_USERS" => $serializedAccounts,
            ]);
            $environmentFileManager->remove(["DEVELOPER_ACCESS_PASSWORD", "DEVELOPER_ACCESS_PASSWORD_HASH"]);
        } catch (\RuntimeException $exception) {
            flash_set("status", [
                "variant" => "danger",
                "title" => "Developer account could not be deactivated",
                "text" => $exception->getMessage(),
                "toast" => false,
            ]);
            regenerate_csrf_token();

            return $this->redirect(route("developer.panel.access"));
        }

        config_set("developer_access", array_merge((array) config("developer_access", []), [
            "email" => (string) ($accounts[0]["email"] ?? ""),
            "password" => "",
            "password_hash" => "",
            "users" => $serializedAccounts,
        ]));
        $developerAccess->grantAccess($this->developerAccountByEmail($developerAccess->accounts(), $currentEmail));
        developer_activity()->record(
            "developer_access",
            "Developer account deactivated",
            "A named developer account was removed from the project access list.",
            $developerAccess->currentDeveloper()
        );

        flash_set("status", [
            "variant" => "success",
            "title" => "Developer account deactivated",
            "text" => "The account was removed and existing sessions will be invalidated by the updated credential fingerprint.",
            "toast" => true,
        ]);
        regenerate_csrf_token();

        return $this->redirect(route("developer.panel.access"));
    }

    public function saveCustomerAccount(
        Request $request,
        DeveloperAccessManager $developerAccess,
        CustomerAccessManager $customerAccess,
        EnvironmentFileManager $environmentFileManager,
        Mailer $mailer
    ): Response {
        if (!$this->ensureDeveloperCapability($developerAccess, "developer.accounts.write")) {
            return $this->redirect(route("developer.panel.access"));
        }

        $permissionOptions = $customerAccess->permissionOptions();
        $permissions = [];

        foreach (array_keys($permissionOptions) as $permission) {
            if ((string) $request->input("customer_permission_" . $permission, "0") === "1") {
                $permissions[] = $permission;
            }
        }

        $payload = [
            "customer_account_email" => strtolower(trim((string) $request->input("customer_account_email", ""))),
            "customer_account_name" => trim((string) $request->input("customer_account_name", "Customer")),
            "customer_account_company" => trim((string) $request->input("customer_account_company", "")),
            "customer_account_send_invite" => (string) $request->input("customer_account_send_invite", "0") === "1",
            "permissions" => $permissions,
        ];

        if ($payload["permissions"] === []) {
            flash_set("old", [
                "customer_account_email" => $payload["customer_account_email"],
                "customer_account_name" => $payload["customer_account_name"],
                "customer_account_company" => $payload["customer_account_company"],
            ]);
            flash_set("status", [
                "variant" => "warning",
                "title" => "Customer account still needs attention",
                "text" => "Choose at least one customer portal section.",
                "toast" => false,
            ]);
            regenerate_csrf_token();

            return $this->redirect(route("developer.panel.access") . "#customer-access-settings");
        }

        try {
            $this->validate($payload, [
                "customer_account_email" => ["required", "email", "max:160"],
                "customer_account_name" => ["required", "string", "min:2", "max:100"],
                "customer_account_company" => ["nullable", "string", "max:120"],
            ]);
        } catch (ValidationException $exception) {
            flash_set("errors", $exception->errors());
            flash_set("old", [
                "customer_account_email" => $payload["customer_account_email"],
                "customer_account_name" => $payload["customer_account_name"],
                "customer_account_company" => $payload["customer_account_company"],
            ]);
            flash_set("status", [
                "variant" => "warning",
                "title" => "Customer account still needs attention",
                "text" => "Use a valid email and customer name before creating the portal invitation.",
                "toast" => false,
            ]);
            regenerate_csrf_token();

            return $this->redirect(route("developer.panel.access") . "#customer-access-settings");
        }

        $invitation = $customerAccess->createInvitation([
            "email" => $payload["customer_account_email"],
            "name" => $payload["customer_account_name"],
            "company" => $payload["customer_account_company"],
            "permissions" => $payload["permissions"],
        ]);
        $serializedAccounts = $customerAccess->serializeAccounts((array) ($invitation["accounts"] ?? []));

        try {
            $environmentFileManager->write([
                "CUSTOMER_ACCESS_ENABLED" => true,
                "CUSTOMER_ACCESS_PATH" => $customerAccess->path(),
                "CUSTOMER_ACCESS_USERS" => $serializedAccounts,
            ]);
            $environmentFileManager->apply([
                "CUSTOMER_ACCESS_ENABLED" => true,
                "CUSTOMER_ACCESS_PATH" => $customerAccess->path(),
                "CUSTOMER_ACCESS_USERS" => $serializedAccounts,
            ]);
        } catch (\RuntimeException $exception) {
            flash_set("status", [
                "variant" => "danger",
                "title" => "Customer account could not be saved",
                "text" => $exception->getMessage(),
                "toast" => false,
            ]);
            regenerate_csrf_token();

            return $this->redirect(route("developer.panel.access") . "#customer-access-settings");
        }

        config_set("customer_access", array_merge((array) config("customer_access", []), [
            "enabled" => true,
            "path" => $customerAccess->path(),
            "users" => $serializedAccounts,
        ]));

        $mailStatus = "Invitation link is ready to copy.";

        if ($payload["customer_account_send_invite"]) {
            try {
                $this->sendCustomerInvitationMail($mailer, (array) ($invitation["account"] ?? []), (string) ($invitation["url"] ?? ""), (string) ($invitation["expires_at_utc"] ?? ""));
                $mailStatus = "Invitation email was sent with the first-login link.";
            } catch (\RuntimeException $exception) {
                $mailStatus = "Invitation link was created, but mail delivery failed: " . $exception->getMessage();
            }
        }

        developer_activity()->record(
            "customer_access",
            "Customer portal invitation created",
            "A customer account was added or rotated for the read-only project portal.",
            $developerAccess->currentDeveloper()
        );

        flash_set("customer_access_invite", [
            "email" => $payload["customer_account_email"],
            "url" => (string) ($invitation["url"] ?? ""),
            "expires_at_utc" => (string) ($invitation["expires_at_utc"] ?? ""),
        ]);
        flash_set("status", [
            "variant" => "success",
            "title" => "Customer access saved",
            "text" => $mailStatus,
            "toast" => true,
        ]);
        regenerate_csrf_token();

        return $this->redirect(route("developer.panel.access") . "#customer-access-settings");
    }

    public function deleteCustomerAccount(
        Request $request,
        DeveloperAccessManager $developerAccess,
        CustomerAccessManager $customerAccess,
        EnvironmentFileManager $environmentFileManager
    ): Response {
        if (!$this->ensureDeveloperCapability($developerAccess, "developer.accounts.write")) {
            return $this->redirect(route("developer.panel.access"));
        }

        $email = strtolower(trim((string) $request->input("customer_account_email", "")));
        $accounts = $customerAccess->removeAccount($email);
        $serializedAccounts = $customerAccess->serializeAccounts($accounts);

        try {
            $environmentFileManager->write([
                "CUSTOMER_ACCESS_USERS" => $serializedAccounts,
            ]);
            $environmentFileManager->apply([
                "CUSTOMER_ACCESS_USERS" => $serializedAccounts,
            ]);
        } catch (\RuntimeException $exception) {
            flash_set("status", [
                "variant" => "danger",
                "title" => "Customer account could not be deactivated",
                "text" => $exception->getMessage(),
                "toast" => false,
            ]);
            regenerate_csrf_token();

            return $this->redirect(route("developer.panel.access") . "#customer-access-settings");
        }

        config_set("customer_access", array_merge((array) config("customer_access", []), [
            "users" => $serializedAccounts,
        ]));
        developer_activity()->record(
            "customer_access",
            "Customer portal account deactivated",
            "A customer account was removed from the project portal access list.",
            $developerAccess->currentDeveloper()
        );

        flash_set("status", [
            "variant" => "success",
            "title" => "Customer access deactivated",
            "text" => "The customer account was removed from the portal access list.",
            "toast" => true,
        ]);
        regenerate_csrf_token();

        return $this->redirect(route("developer.panel.access") . "#customer-access-settings");
    }

    private function sendCustomerInvitationMail(Mailer $mailer, array $account, string $url, string $expiresAt): void
    {
        $projectName = (string) config("app.name", "FNLLA Project");
        $name = trim((string) ($account["name"] ?? "Customer"));
        $email = strtolower(trim((string) ($account["email"] ?? "")));

        if ($email === "" || $url === "") {
            throw new \RuntimeException("Customer invitation email or URL is missing.");
        }

        $expiresLabel = $expiresAt !== "" ? $expiresAt : "the configured invitation window";
        $html = "<p>Hello " . h($name) . ",</p>"
            . "<p>You have been invited to the customer portal for " . h($projectName) . ".</p>"
            . "<p><a href=\"" . h($url) . "\">Set your customer portal password</a></p>"
            . "<p>This first-login link expires at " . h($expiresLabel) . ".</p>";
        $text = "Hello {$name},\n\nYou have been invited to the customer portal for {$projectName}.\n\nSet your customer portal password:\n{$url}\n\nThis first-login link expires at {$expiresLabel}.";

        $mailer->send($email, "Project access invitation for " . $projectName, $html, $text);
    }
}
