<?php

declare(strict_types=1);

namespace Fnlla\Php\Console\Commands;

use Fnlla\Php\Console\Command;
use Fnlla\Php\Support\FnllaLicense;

final class LicenseInstallCommand extends Command
{
    public function name(): string
    {
        return "license:install";
    }

    public function description(): string
    {
        return "Verify and install an FNLLA licence token locally.";
    }

    public function usage(): string
    {
        return "license:install <token>";
    }

    public function handle(array $arguments): int
    {
        $token = trim((string) ($arguments[0] ?? ""));

        if ($token === "") {
            $this->error("Usage: php fnlla " . $this->usage());

            return 1;
        }

        $licences = $this->container->make(FnllaLicense::class);
        $record = $licences->install($token);
        $payload = (array) ($record["payload"] ?? []);

        $this->line("FNLLA licence installed.");
        $this->line("Path: " . $licences->installedPath());
        $this->line("Licence: " . (string) ($payload["license_id"] ?? "unknown"));
        $this->line("Customer: " . (string) ($payload["customer_email"] ?? "unknown"));

        return 0;
    }
}
