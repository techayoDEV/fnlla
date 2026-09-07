<?php

declare(strict_types=1);

namespace Fnlla\Php\Tests;

use PHPUnit\Framework\TestCase;

final class RuntimeBrandTest extends TestCase
{
    public function testGeneratedThemeContractIsCurrent(): void
    {
        $result = \Fnlla\Php\Support\ProcessRunner::run([PHP_BINARY, base_path('scripts/branding/build-runtime.php'), '--check'], base_path());
        self::assertSame(0, $result['exit_code'], $result['output']);
        $tokens = json_decode((string) file_get_contents(base_path('branding/tokens.json')), true, 512, JSON_THROW_ON_ERROR);
        $framework = require base_path('config/framework.php');
        foreach ($tokens['framework_colors'] as $alias => $key) {
            self::assertSame($tokens['color'][$key], $framework['brand']['colors'][$alias]);
        }
        foreach (['public/assets/app.css', 'public/assets/developer-panel.css', 'public/vendor/fnlla-runtime/assets/css/fnlla-runtime.css'] as $path) {
            $css = (string) file_get_contents(base_path($path));
            preg_match_all('/font-size:\s*([\d.]+)(rem|px);/', $css, $matches, PREG_SET_ORDER);
            foreach ($matches as $match) {
                $size = (float) $match[1] * ($match[2] === 'rem' ? 16 : 1);
                if ($size > 0) {
                    self::assertTrue($size >= $tokens['type_px']['supporting'], $path . ': ' . $match[0]);
                }
            }
        }
    }

    public function testPrivateStylesAndMarksHaveFrameworkOwnership(): void
    {
        $public = (string) file_get_contents(public_path('assets/app.css'));
        $private = (string) file_get_contents(public_path('assets/developer-panel.css'));
        self::assertStringNotContainsString('.client-preview-icon-svg', $public);
        self::assertStringContainsString('.client-preview-icon-svg', $private);
        self::assertStringContainsString('.framework-update-actions-grid', $private);
        self::assertStringNotContainsString('fnlla init --workspace', $public);
        self::assertTrue(\Fnlla\Php\Support\FrameworkLock::isFrameworkManagedPath('views/partials/framework-wordmark.php'));
        self::assertTrue(\Fnlla\Php\Support\FrameworkLock::isFrameworkManagedPath('public/assets/brand/fnlla/wordmark.svg'));
        self::assertFalse(\Fnlla\Php\Support\FrameworkLock::isFrameworkManagedPath('public/assets/brand/my-product.svg'));
        self::assertTrue(\Fnlla\Php\Support\ProjectProfile::isPanelFile('views/partials/framework-wordmark.php'));
    }

    public function testRuntimeUsesBrandRolesWithoutMarketingAssetsInTheExport(): void
    {
        $tokens = json_decode((string) file_get_contents(base_path('branding/tokens.json')), true, 512, JSON_THROW_ON_ERROR);
        $css = (string) file_get_contents(public_path('vendor/fnlla-runtime/assets/css/fnlla-runtime.css'));
        foreach (['primary' => 'blue', 'secondary' => 'ink', 'text' => 'text', 'muted' => 'supporting_text', 'success-text' => 'success_text', 'danger-text' => 'danger_text'] as $role => $brand) {
            self::assertStringContainsString('--fnlla-color-' . $role . ': ' . strtolower($tokens['color'][$brand]) . ';', strtolower($css));
        }
        self::assertStringContainsString('outline: 2px solid var(--fnlla-color-focus-ring);', $css);
        self::assertStringContainsString('background: var(--fnlla-color-action);', $css);
        $exports = json_decode((string) file_get_contents(base_path('resources/project-templates/v1/export-files.json')), true, 512, JSON_THROW_ON_ERROR)['files'];
        self::assertTrue(in_array('public/assets/brand/fnlla/binary-signature.png', $exports, true));
        self::assertNotContains('public/assets/brand/fnlla-mark.svg', $exports);
        self::assertNotContains('public/assets/brand/fnlla/fnlla-blueprint-pattern.svg', $exports);
        self::assertContains('views/partials/framework-wordmark.php', $exports);
        foreach ($exports as $path) {
            self::assertFalse(str_starts_with($path, 'branding/'));
            self::assertSame(0, preg_match('/\.(?:psd|docx)$/i', $path));
        }
    }
}
