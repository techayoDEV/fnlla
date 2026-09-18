<?php

declare(strict_types=1);

namespace Fnlla\Php\Console\Commands;

use Fnlla\Php\Console\Command;
use Fnlla\Php\Support\FnllaLicense;

final class LicenseVerifyCommand extends Command
{
    public function name(): string
    {
        return "license:verify";
    }

    public function description(): string
    {
        return "Verify an FNLLA licence token without installing it.";
    }

    public function usage(): string
    {
        return "license:verify <token>";
    }

    public function handle(array $arguments): int
    {
        $token = trim((string) ($arguments[0] ?? ""));

        if ($token === "") {
            $this->error("Usage: php fnlla " . $this->usage());

            return 1;
        }

        $record = $this->container->make(FnllaLicense::class)->verify($token);
        $payload = (array) ($record["payload"] ?? []);

        $this->line("FNLLA licence is valid.");
        $this->line("Licence: " . (string) ($payload["license_id"] ?? "unknown"));
        $this->line("Customer: " . (string) ($payload["customer_email"] ?? "unknown"));
        $this->line("Product: " . (string) ($payload["product_name"] ?? "FNLLA"));

        return 0;
    }
}
