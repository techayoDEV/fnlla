<?php

declare(strict_types=1);

namespace Fnlla\Php\Tests;

use Fnlla\Php\View\View;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class ViewSecurityTest extends TestCase
{
    public function testViewNamesCannotEscapeTheirRoot(): void
    {
        foreach (["../config/app", "pages/../../config/app", "C:/private", "pages\\home", "php://filter/resource=x"] as $name) {
            try {
                View::render($name, [], null);
                self::fail("Unsafe view name was accepted.");
            } catch (RuntimeException $error) {
                self::assertSame("Invalid view name.", $error->getMessage());
            }
        }
    }
}
