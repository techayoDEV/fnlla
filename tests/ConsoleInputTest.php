<?php

declare(strict_types=1);

namespace Fnlla\Php\Tests;

use Fnlla\Php\Console\Application;
use Fnlla\Php\Console\Command;
use Fnlla\Php\Console\Input;
use Fnlla\Php\Console\Commands\MigrateCommand;
use Fnlla\Php\Console\Commands\MigrateRollbackCommand;
use Fnlla\Php\Container\Container;
use Fnlla\Php\Database\Migrations\Migrator;
use PHPUnit\Framework\TestCase;

final class ConsoleInputTest extends TestCase
{
    public function testOptionsAreStrictAndSupportBothValueFormsAndLiteralArguments(): void
    {
        foreach ([["--connection", "audit", "--force", "3"], ["--force", "--connection=audit", "3"]] as $arguments) {
            $input = Input::parse($arguments, ["connection" => true, "force" => false], 1);
            self::assertSame("audit", $input->option("connection"));
            self::assertTrue($input->option("force"));
            self::assertSame(["3"], $input->arguments);
        }
        self::assertSame(["--literal"], Input::parse(["--", "--literal"], [], 1)->arguments);
        foreach ([["--typo"], ["--connection"], ["--connection="], ["--connection", "--force"], ["--force=false"], ["--force", "--force"], ["extra"]] as $arguments) {
            try {
                Input::parse($arguments, ["connection" => true, "force" => false]);
                self::fail("Invalid arguments were accepted.");
            } catch (\InvalidArgumentException $error) {
                self::assertTrue($error->getMessage() !== "");
            }
        }
    }

    public function testInvalidRollbackArgumentsNeverResolveDatabaseServices(): void
    {
        $container = new Container();
        $resolved = false;
        $container->bind(Migrator::class, static function () use (&$resolved): never {
            $resolved = true;
            throw new \RuntimeException("Unexpected database access.");
        });
        $command = new MigrateRollbackCommand($container);
        foreach ([["nonsense"], ["0"], ["-1"], ["1.5"], ["1e2"], ["99999999999999999999999999"], ["1", "2"], ["2", "--steps=3"], ["--step=3"]] as $arguments) {
            try {
                $command->handle($arguments);
                self::fail("Invalid rollback was accepted.");
            } catch (\InvalidArgumentException $error) {
                self::assertFalse($resolved);
            }
        }
    }

    public function testNonLocalMigrationsRequireExplicitConfirmationBeforeDatabaseResolution(): void
    {
        $previous = config("app.environment");
        try {
            foreach (["production", "staging", "unknown"] as $environment) {
                config_set("app.environment", $environment);
                try {
                    (new MigrateCommand(new Container()))->handle([]);
                    self::fail("Non-local migration was accepted without --force.");
                } catch (\RuntimeException $error) {
                    self::assertStringContainsString("--force", $error->getMessage());
                }
            }
        } finally {
            config_set("app.environment", $previous);
        }
    }

    public function testHelpNeverExecutesACommand(): void
    {
        $container = new Container();
        $fixture = new HelpMutationFixture($container);
        $container->instance(HelpMutationFixture::class, $fixture);
        $console = new Application($container);
        $console->register(HelpMutationFixture::class);
        foreach ([["fnlla", "help", "danger"], ["fnlla", "danger", "--help"], ["fnlla", "danger", "-h"]] as $argv) {
            self::assertSame(0, $console->run($argv));
            self::assertFalse($fixture->executed);
        }
    }
}

final class HelpMutationFixture extends Command
{
    public bool $executed = false;
    public function name(): string { return "danger"; }
    public function description(): string { return "Help fixture."; }
    public function handle(array $arguments): int { $this->executed = true; return 0; }
}
