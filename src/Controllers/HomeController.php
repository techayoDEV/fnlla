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

/** Compatibility entrypoints for cached routes and existing projects. */
final class HomeController extends Controller
{
    public function projectHome(
        Request $request,
        MaintenanceAccessManager $maintenanceAccess,
        DeveloperAccessManager $developerAccess,
        PageController $pages
    ): Response
    {
        return app()->call([app(OnboardingController::class), "projectHome"], func_get_args());
    }

    public function developerSetupAlias(): Response
    {
        return app()->call([app(OnboardingController::class), "developerSetupAlias"], func_get_args());
    }

    public function setupDeveloperAccess(
        Request $request,
        DeveloperAccessManager $developerAccess,
        MaintenanceAccessManager $maintenanceAccess,
        EnvironmentFileManager $environmentFileManager
    ): Response
    {
        return app()->call([app(OnboardingController::class), "setupDeveloperAccess"], func_get_args());
    }

    public function maintenanceHome(
        Request $request,
        MaintenanceAccessManager $maintenanceAccess,
        DeveloperAccessManager $developerAccess
    ): Response
    {
        return app()->call([app(MaintenanceController::class), "maintenanceHome"], func_get_args());
    }

    public function setupMaintenanceAccess(
        Request $request,
        MaintenanceAccessManager $maintenanceAccess,
        DeveloperAccessManager $developerAccess,
        EnvironmentFileManager $environmentFileManager
    ): Response
    {
        return app()->call([app(MaintenanceController::class), "setupMaintenanceAccess"], func_get_args());
    }

    public function unlockMaintenance(Request $request, MaintenanceAccessManager $maintenanceAccess): Response
    {
        return app()->call([app(MaintenanceController::class), "unlockMaintenance"], func_get_args());
    }

    public function lockMaintenance(Request $request, MaintenanceAccessManager $maintenanceAccess): Response
    {
        return app()->call([app(MaintenanceController::class), "lockMaintenance"], func_get_args());
    }

    public function redirectHealthToMaintenance(Request $request, DeveloperAccessManager $developerAccess): Response
    {
        return app()->call([app(HealthController::class), "redirectHealthToMaintenance"], func_get_args());
    }

    public function healthPage(Request $request): Response
    {
        return app()->call([app(HealthController::class), "healthPage"], func_get_args());
    }

    public function healthApi(Request $request): Response
    {
        return app()->call([app(HealthController::class), "healthApi"], func_get_args());
    }

    public function profileApi(): array
    {
        return app()->call([app(HealthController::class), "profileApi"], func_get_args());
    }

    public function healthPayload(Request $request): array
    {
        return app()->call([app(HealthController::class), "healthPayload"], func_get_args());
    }
}
