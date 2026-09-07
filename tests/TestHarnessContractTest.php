<?php

declare(strict_types=1);

namespace Fnlla\Php\Tests;

use PHPUnit\Framework\TestCase;

final class TestHarnessContractTest extends TestCase
{
    public function testLocalHarnessRejectsMissingOrMismatchedExceptionsAndAlwaysTearsDown(): void
    {
        // Run the dependency-free harness separately even when this suite uses real PHPUnit.
        $source = 'require ' . var_export(base_path('tests/PHPUnit/Framework/TestCase.php'), true) . ';' . <<<'PHP'
        $fixture = new class extends \PHPUnit\Framework\TestCase {
            public bool $cleaned = false;
            protected function tearDown(): void { $this->cleaned = true; }
            public function valid(): void { $this->expectException(RuntimeException::class); $this->expectExceptionMessage('expected'); throw new RuntimeException('The expected detail'); }
            public function messageOnly(): void { $this->expectExceptionMessage('expected'); throw new LogicException('expected'); }
            public function absent(): void { $this->expectException(RuntimeException::class); }
            public function wrongMessage(): void { $this->expectExceptionMessage('expected'); throw new RuntimeException('different'); }
            public function wrongClass(): void { $this->expectException(LogicException::class); throw new RuntimeException('expected'); }
            public function strictAssertions(): void {
                self::assertNull(null);
                self::assertContains(1, [1, '2']);
                self::assertNotContains('1', [1, 2]);
                self::assertFileDoesNotExist(__DIR__ . '/fnlla-harness-no-such-file');
            }
        };
        $results = [];
        foreach (['valid', 'messageOnly', 'absent', 'wrongMessage', 'wrongClass', 'strictAssertions'] as $method) {
            $fixture->cleaned = false;
            try { $fixture->runTestMethod($method); $passed = true; } catch (Throwable) { $passed = false; }
            $results[] = [$passed, $fixture->cleaned];
        }
        echo json_encode($results, JSON_THROW_ON_ERROR);
        PHP;
        $process = proc_open([PHP_BINARY, '-r', $source], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        self::assertTrue(is_resource($process));
        $output = stream_get_contents($pipes[1]);
        $errors = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        self::assertSame(0, proc_close($process), (string) $errors);
        self::assertSame([[true, true], [true, true], [false, true], [false, true], [false, true], [true, true]], json_decode((string) $output, true, 512, JSON_THROW_ON_ERROR));
    }
}
