<?php

declare(strict_types=1);

namespace Fnlla\Php\Controllers;

use DateTimeImmutable;
use DateTimeZone;
use Fnlla\Php\Http\Request;
use Fnlla\Php\Http\Response;
use Fnlla\Php\Maintenance\DeveloperAccessManager;
use Fnlla\Php\Maintenance\MaintenanceAccessManager;
use Fnlla\Php\Support\EnvironmentFileManager;
use Fnlla\Php\Support\FrameworkReleaseChannel;
use Fnlla\Php\Support\FrameworkUpdater;
use Fnlla\Php\Support\Logger;
use Fnlla\Php\Support\ProjectLeadership;
use Fnlla\Php\Support\VersionManifest;
use Fnlla\Php\Validation\ValidationException;

final class OnboardingController extends ProjectAccessController
{
    public function projectHome(
        Request $request,
        MaintenanceAccessManager $maintenanceAccess,
        DeveloperAccessManager $developerAccess,
        PageController $pages
    ): Response {
        if ($this->freshDeveloperOnboardingAvailable($request, $maintenanceAccess, $developerAccess)) {
            return $this->firstRunDeveloperSetup($request, $maintenanceAccess, $developerAccess);
        }

        return $pages->home($request);
    }


    public function developerSetupAlias(): Response
    {
        return $this->redirect(route("home"));
    }

    public function developerEntrySetup(Request $request, DeveloperAccessManager $developerAccess, MaintenanceAccessManager $maintenanceAccess): Response
    {
        $state = $this->developerAccessSetupState($request, app(EnvironmentFileManager::class), $developerAccess);
        if (!$developerAccess->enabled() || !$state["can_setup"] || !$state["needs_setup"]) {
            return Response::text("Not Found", 404)->withHeader("Cache-Control", "private, no-store");
        }

        return $this->redirect($this->developerSetupRedirectTarget($maintenanceAccess))
            ->withHeader("Cache-Control", "private, no-store");
    }


    private function firstRunDeveloperSetup(
        Request $request,
        MaintenanceAccessManager $maintenanceAccess,
        DeveloperAccessManager $developerAccess
    ): Response {
        $environmentFileManager = app(EnvironmentFileManager::class);

        return $this->view("maintenance/index", [
            "pageTitle" => "Project Setup",
            "pageTitleSection" => "Developer Onboarding",
            "maintenanceAccess" => $maintenanceAccess->viewState(),
            "developerAccess" => $developerAccess->viewState(),
            "maintenanceSetup" => $this->maintenanceSetupState($request, $environmentFileManager, $maintenanceAccess, $developerAccess),
            "developerSetup" => $this->developerAccessSetupState($request, $environmentFileManager, $developerAccess),
            "projectSetup" => $this->projectSetupState(),
            "maintenanceLocked" => false,
        ]);
    }


    public function setupDeveloperAccess(
        Request $request,
        DeveloperAccessManager $developerAccess,
        MaintenanceAccessManager $maintenanceAccess,
        EnvironmentFileManager $environmentFileManager
    ): Response {
        $setupState = $this->developerAccessSetupState($request, $environmentFileManager, $developerAccess);

        if ($setupState["can_setup"] !== true || $developerAccess->configured()) {
            flash_set("status", [
                "variant" => "warning",
                "title" => "Developer panel setup is unavailable here",
                "text" => $developerAccess->configured()
                    ? "The developer panel is already configured for this project."
                    : (string) $setupState["message"],
                "toast" => false,
            ]);
            regenerate_csrf_token();

            return $this->redirect($this->developerSetupRedirectTarget($maintenanceAccess));
        }

        $payload = [
            "project_name" => $this->normalizeProjectName((string) $request->input("project_name", (string) config("app.name", "FNLLA Project"))),
            "project_tagline" => $this->normalizeProjectName((string) $request->input("project_tagline", (string) config("app.tagline", ""))),
            "project_url" => trim((string) $request->input("project_url", (string) config("app.base_url", ""))),
            "project_leadership_organization" => $this->normalizeProjectName((string) $request->input("project_leadership_organization", "")),
            "project_leadership_person_name" => $this->normalizeProjectName((string) $request->input("project_leadership_person_name", "")),
            "project_leadership_person_email" => strtolower(trim((string) $request->input("project_leadership_person_email", ""))),
            "project_leadership_person_role" => $this->normalizeProjectName((string) $request->input("project_leadership_person_role", "")),
            "project_leadership_responsibility" => $this->normalizeProjectName((string) $request->input("project_leadership_responsibility", "")),
            "project_leadership_profile_url" => trim((string) $request->input("project_leadership_profile_url", "")),
            "project_leadership_visibility" => (new ProjectLeadership())->visibility((string) $request->input("project_leadership_visibility", ProjectLeadership::VISIBILITY_DISABLED)),
            "developer_setup_email" => strtolower(trim((string) $request->input("developer_setup_email", ""))),
            "developer_setup_password" => trim((string) $request->input("developer_setup_password", "")),
            "developer_setup_password_confirmation" => trim((string) $request->input("developer_setup_password_confirmation", "")),
        ];
        $leadershipEnabled = $payload["project_leadership_visibility"] !== ProjectLeadership::VISIBILITY_DISABLED;

        try {
            $this->validate($payload, [
                "project_name" => ["required", "string", "min:2", "max:80"],
                "project_tagline" => ["nullable", "string", "max:120"],
                "project_url" => ["nullable", "string", "url", "max:2048"],
                "project_leadership_organization" => [$leadershipEnabled ? "required" : "nullable", "string", "max:120"],
                "project_leadership_person_name" => [$leadershipEnabled ? "required" : "nullable", "string", "max:120"],
                "project_leadership_person_email" => [$leadershipEnabled ? "required" : "nullable", "email", "max:160"],
                "project_leadership_person_role" => ["nullable", "string", "max:120"],
                "project_leadership_responsibility" => ["nullable", "string", "max:240"],
                "project_leadership_profile_url" => ["nullable", "string", "url", "max:2048"],
                "project_leadership_visibility" => ["required", "string"],
                "developer_setup_email" => ["required", "email", "max:160"],
                "developer_setup_password" => ["required", "string", "min:8", "max:255", "confirmed"],
            ]);
        } catch (ValidationException $exception) {
            flash_set("errors", $exception->errors());
            flash_set("status", [
                "variant" => "warning",
                "title" => "Developer panel setup still needs attention",
                "text" => "Review the developer email and password fields before activating the developer panel.",
                "toast" => false,
            ]);
            regenerate_csrf_token();

            return $this->redirect($this->developerSetupRedirectTarget($maintenanceAccess));
        }

        $developerPasswordHash = password_hash($payload["developer_setup_password"], PASSWORD_DEFAULT);
        $developerAccount = [
            "email" => $payload["developer_setup_email"],
            "name" => "Developer",
            "role" => "lead_developer",
            "password_hash" => $developerPasswordHash,
        ];
        $environmentValues = [
            "APP_NAME" => $payload["project_name"],
            "APP_TAGLINE" => $payload["project_tagline"],
            "APP_URL" => $payload["project_url"],
            "PROJECT_LEADERSHIP_ORGANIZATION" => $leadershipEnabled ? $payload["project_leadership_organization"] : "",
            "PROJECT_LEADERSHIP_PERSON_NAME" => $leadershipEnabled ? $payload["project_leadership_person_name"] : "",
            "PROJECT_LEADERSHIP_PERSON_EMAIL" => $leadershipEnabled ? $payload["project_leadership_person_email"] : "",
            "PROJECT_LEADERSHIP_PERSON_ROLE" => $leadershipEnabled ? $payload["project_leadership_person_role"] : "",
            "PROJECT_LEADERSHIP_RESPONSIBILITY" => $leadershipEnabled ? $payload["project_leadership_responsibility"] : "",
            "PROJECT_LEADERSHIP_PROFILE_URL" => $leadershipEnabled ? $payload["project_leadership_profile_url"] : "",
            "PROJECT_LEADERSHIP_VISIBILITY" => $leadershipEnabled ? $payload["project_leadership_visibility"] : ProjectLeadership::VISIBILITY_DISABLED,
            "PROJECT_LEADERSHIP_STATUS" => ProjectLeadership::STATUS_PENDING,
            "PROJECT_LEADERSHIP_CONFIRMED_BY" => "",
            "PROJECT_LEADERSHIP_CONFIRMED_AT" => "",
            "DEVELOPER_ACCESS_ENABLED" => "true",
            "DEVELOPER_ACCESS_PATH" => $developerAccess->path(),
            "DEVELOPER_ACCESS_EMAIL" => $payload["developer_setup_email"],
            "DEVELOPER_ACCESS_USERS" => $developerAccess->serializeAccounts([$developerAccount]),
            "DEVELOPER_OPERATIONS_NAV_MODE" => "hidden",
        ];

        try {
            $environmentFileManager->write($environmentValues);
            $environmentFileManager->apply($environmentValues);
            $environmentFileManager->remove(["DEVELOPER_ACCESS_PASSWORD", "DEVELOPER_ACCESS_PASSWORD_HASH"]);
        } catch (\RuntimeException $exception) {
            flash_set("status", [
                "variant" => "danger",
                "title" => "Developer panel could not be activated",
                "text" => $exception->getMessage(),
                "toast" => false,
            ]);
            regenerate_csrf_token();

            return $this->redirect($this->developerSetupRedirectTarget($maintenanceAccess));
        }

        config_set("developer_access", array_merge((array) config("developer_access", []), [
            "enabled" => true,
            "email" => $payload["developer_setup_email"],
            "password" => "",
            "password_hash" => "",
            "path" => (string) $environmentValues["DEVELOPER_ACCESS_PATH"],
            "users" => (string) $environmentValues["DEVELOPER_ACCESS_USERS"],
            "operations_nav_mode" => "hidden",
        ]));
        config_set("app", array_merge((array) config("app", []), [
            "name" => $payload["project_name"],
            "tagline" => $payload["project_tagline"],
            "base_url" => rtrim($payload["project_url"], "/"),
            "project_leadership" => [
                "organization" => $leadershipEnabled ? $payload["project_leadership_organization"] : "",
                "person_name" => $leadershipEnabled ? $payload["project_leadership_person_name"] : "",
                "person_email" => $leadershipEnabled ? $payload["project_leadership_person_email"] : "",
                "person_role" => $leadershipEnabled ? $payload["project_leadership_person_role"] : "",
                "responsibility" => $leadershipEnabled ? $payload["project_leadership_responsibility"] : "",
                "profile_url" => $leadershipEnabled ? $payload["project_leadership_profile_url"] : "",
                "visibility" => $leadershipEnabled ? $payload["project_leadership_visibility"] : ProjectLeadership::VISIBILITY_DISABLED,
                "status" => ProjectLeadership::STATUS_PENDING,
                "confirmed_by" => "",
                "confirmed_at" => "",
            ],
        ]));
        $developerAccess->grantAccess($developerAccount);
        Logger::write("notice", "Developer access created", [
            "event" => "developer_access_created",
            "email" => $payload["developer_setup_email"],
        ]);
        $maintenanceAccess->lock();
        flash_set("developer_access_notice", [
            "title" => "Named developer account created",
            "text" => "The developer session is ready at the standard /developer address and uses email plus password sign-in.",
        ]);
        flash_set("status", [
            "variant" => "success",
            "title" => "Developer panel activated",
            "text" => "The developer panel was added to this project and the current browser session can use it immediately. Maintenance stays optional until you enable it from the panel.",
            "toast" => true,
        ]);
        regenerate_csrf_token();

        return $this->redirect(route("developer.panel"));
    }

}
