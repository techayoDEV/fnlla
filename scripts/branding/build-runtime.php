<?php

declare(strict_types=1);

// The optional brand kit is a build input, never a dependency of an exported application.
$root = dirname(__DIR__, 2);
$tokens = json_decode((string) file_get_contents($root . '/branding/tokens.json'), true, 512, JSON_THROW_ON_ERROR);
$check = in_array('--check', $argv, true);
$resolve = static function (string $value) use ($tokens): string {
    if (preg_match('/^\{color\.([a-z_]+)\}$/', $value, $match) === 1) {
        return strtolower($tokens['color'][$match[1]] ?? throw new RuntimeException('Unknown colour: ' . $match[1]));
    }
    return $value;
};
$replace = static function (string $source, string $pattern, string $replacement): string {
    $result = preg_replace_callback($pattern, static fn (): string => $replacement, $source, -1, $count);
    if ($result === null || $count !== 1) {
        throw new RuntimeException('Expected exactly one generated block: ' . $pattern);
    }
    return $result;
};
$runtimePath = '/public/vendor/fnlla-runtime/assets/css/fnlla-runtime.css';
$runtime = str_replace("\r\n", "\n", (string) file_get_contents($root . $runtimePath));
foreach ($tokens['runtime'] as $theme => $roles) {
    $lines = [];
    foreach ($roles as $role => $value) {
        $lines[] = '  --fnlla-' . $role . ': ' . $resolve($value) . ';';
    }
    $runtime = $replace($runtime, '~  /\* BEGIN GENERATED BRAND ' . preg_quote($theme, '~') . ' \*/.*?  /\* END GENERATED BRAND ' . preg_quote($theme, '~') . ' \*/~s',
        "  /* BEGIN GENERATED BRAND $theme */\n" . implode("\n", $lines) . "\n  /* END GENERATED BRAND $theme */");
}
foreach (['base' => 'body', 'sm' => 'supporting'] as $role => $key) {
    $runtime = $replace($runtime, '/  --fnlla-font-size-' . $role . ': [^;]+;/',
        '  --fnlla-font-size-' . $role . ': ' . ($tokens['type_px'][$key] / 16) . 'rem;');
}
foreach (['base' => 'brand', 'mono' => 'code'] as $role => $key) {
    $fallback = $role === 'base' ? ', "Segoe UI", Arial, sans-serif' : ', "Cascadia Code", Consolas, monospace';
    $runtime = $replace($runtime, '/  --fnlla-font-' . $role . ': [^;]+;/',
        '  --fnlla-font-' . $role . ': "' . $tokens['font'][$key] . '"' . $fallback . ';');
}
$quote = static fn (string $value): string => var_export($value, true);
$lines = ['        // BEGIN GENERATED BRAND', '        "version" => ' . $quote($tokens['edition']) . ',',
    '        "message" => ' . $quote($tokens['message']) . ',', '        "colors" => ['];
foreach ($tokens['framework_colors'] as $alias => $key) {
    $lines[] = '            ' . $quote($alias) . ' => ' . $quote($tokens['color'][$key]) . ',';
}
$lines[] = '        ],';
$lines[] = '        "fonts" => [';
foreach (['brand' => 'brand', 'mono' => 'code', 'fallback' => 'fallback'] as $alias => $key) {
    $lines[] = '            ' . $quote($alias) . ' => ' . $quote($tokens['font'][$key]) . ',';
}
$lines[] = '        ],';
$lines[] = '        // END GENERATED BRAND';
$configPath = '/config/framework.php';
$config = str_replace("\r\n", "\n", (string) file_get_contents($root . $configPath));
$config = $replace($config, '~        // BEGIN GENERATED BRAND.*?        // END GENERATED BRAND~s', implode("\n", $lines));
$failed = false;
foreach ([$runtimePath => $runtime, $configPath => $config] as $path => $expected) {
    $actual = str_replace("\r\n", "\n", (string) file_get_contents($root . $path));
    if ($check && $actual !== $expected) {
        fwrite(STDERR, 'Stale brand tokens: ' . $path . PHP_EOL);
        $failed = true;
    } elseif (!$check && $actual !== $expected) {
        file_put_contents($root . $path, $expected);
    }
}
if (!$failed) {
    echo $check ? "Runtime brand tokens are current.\n" : "Runtime brand tokens generated.\n";
}
exit($failed ? 1 : 0);
