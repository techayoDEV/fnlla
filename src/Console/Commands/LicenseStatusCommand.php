<?php

declare(strict_types=1);

namespace Fnlla\Php\Console\Commands;

use Fnlla\Php\Console\Command;
use Fnlla\Php\Support\FnllaLicense;

final class LicenseStatusCommand extends Command
{
    public function name(): string
    {
        return "license:status";
    }

    public function description(): string
    {
        return "Show the locally installed FNLLA licence status.";
    }

    public function handle(array $arguments): int
    {
        unset($arguments);

        $licences = $this->container->make(FnllaLicense::class);
        $record = $licences->current();

        if ($record === null) {
            $this->line("No FNLLA licence is installed.");
            $this->line("Expected path: " . $licences->installedPath());

            return 1;
        }

        $payload = (array) ($record["payload"] ?? []);
        $this->line("FNLLA licence is installed.");
        $this->line("Path: " . $licences->installedPath());
        $this->line("Licence: " . (string) ($payload["license_id"] ?? "unknown"));
        $this->line("Customer: " . (string) ($payload["customer_email"] ?? "unknown"));
        $this->line("Verified: " . (string) ($record["verified_at_utc"] ?? "unknown"));

        return 0;
    }
}
