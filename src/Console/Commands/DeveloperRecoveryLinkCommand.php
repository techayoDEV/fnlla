<?php

declare(strict_types=1);

namespace Fnlla\Php\Console\Commands;

use Fnlla\Php\Console\Command;
use Fnlla\Php\Maintenance\DeveloperPasswordRecovery;

final class DeveloperRecoveryLinkCommand extends Command
{
    public function name(): string { return "developer:recovery-link"; }
    public function description(): string { return "Generate a private, one-time password reset link for an existing developer account."; }

    public function handle(array $arguments): int
    {
        if (PHP_SAPI !== "cli" || count($arguments) !== 1 || filter_var($arguments[0], FILTER_VALIDATE_EMAIL) === false) {
            $this->line("Usage: php fnlla developer:recovery-link developer@example.com");
            return 1;
        }
        $url = app(DeveloperPasswordRecovery::class)->issue($arguments[0]);
        if ($url === null) {
            $this->line("Recovery is disabled or the developer account does not exist.");
            return 1;
        }
        $this->line("Private bearer link. Do not paste it into tickets, chat or logs:");
        $this->line($url);
        return 0;
    }
}
