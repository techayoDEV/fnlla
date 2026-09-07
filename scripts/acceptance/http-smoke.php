<?php

declare(strict_types=1);

// This client mutates only a disposable export prepared by the acceptance runner.
$project = realpath($argv[1] ?? '') ?: throw new RuntimeException('Pass an acceptance export.');
$base = rtrim($argv[2] ?? '', '/');
$phase = $argv[3] ?? 'exercise';
if (!is_file($project . '/.fnlla-http-acceptance') || $project === realpath(dirname(__DIR__, 2))
    || !preg_match('#^https?://127\.0\.0\.1:[0-9]+$#D', $base)) {
    throw new RuntimeException('Only a marked, disposable loopback export is allowed.');
}
$inheritedEnvironment = getenv();
require $project . '/bootstrap/app.php';
$environment = new \Fnlla\Php\Support\EnvironmentFileManager();
$check = static function (bool $condition, string $message): void {
    if (!$condition) { fwrite(STDERR, 'FAIL ' . $message . PHP_EOL); throw new RuntimeException($message); }
    echo 'PASS ' . $message . PHP_EOL;
};
$cli = static function (array $arguments) use ($project, $inheritedEnvironment): string {
    // Each CLI invocation represents a fresh operator shell, not this client's stale .env snapshot.
    $current = getenv();
    foreach ($current as $name => $value) {
        if (!array_key_exists($name, $inheritedEnvironment)) { putenv($name); }
    }
    foreach ($inheritedEnvironment as $name => $value) { putenv($name . '=' . $value); }
    try {
        $result = \Fnlla\Php\Support\ProcessRunner::run([PHP_BINARY, $project . '/fnlla', ...$arguments], $project);
    } finally {
        foreach ($current as $name => $value) { putenv($name . '=' . $value); }
    }
    if ($result['exit_code'] !== 0) {
        fwrite(STDERR, 'FAIL acceptance CLI: ' . $arguments[0] . PHP_EOL);
        throw new RuntimeException('Acceptance CLI failed: ' . $arguments[0]);
    }
    // Command output may contain a one-time bearer link. Never print it to CI logs.
    return $result['stdout'];
};
if ($phase === 'prepare') {
    $environment->write(['APP_ENV' => 'development', 'APP_DEBUG' => 'false', 'APP_URL' => $base,
        'TRUSTED_HOSTS' => '127.0.0.1', 'TRUSTED_PROXIES' => '127.0.0.1', 'SESSION_SECURE' => 'true',
        'DEVELOPER_ACCESS_SETUP_UI_ENABLED' => 'true', 'DEVELOPER_ACCESS_SETUP_UI_LOCAL_ONLY' => 'true',
        'FNLLA_RUNTIME_AUTO_SYNC' => 'false', 'MAIL_MAILER' => 'log']);
    exit(0);
}
$client = static function (): CurlHandle {
    $handle = curl_init();
    curl_setopt_array($handle, [CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_COOKIEFILE => '', CURLOPT_CONNECTTIMEOUT => 5, CURLOPT_TIMEOUT => 30,
        CURLOPT_PROXY => '', CURLOPT_SSL_VERIFYPEER => true, CURLOPT_SSL_VERIFYHOST => 2]);
    if (getenv('FNLLA_ACCEPTANCE_CA')) { curl_setopt($handle, CURLOPT_CAINFO, getenv('FNLLA_ACCEPTANCE_CA')); }
    return $handle;
};
$send = static function (CurlHandle $handle, string $path, ?array $form = null, array $extraHeaders = []) use ($base): array {
    $headers = [];
    curl_setopt_array($handle, [CURLOPT_URL => $base . $path, CURLOPT_POST => $form !== null,
        CURLOPT_HTTPHEADER => $extraHeaders,
        CURLOPT_HEADERFUNCTION => static function ($curl, string $line) use (&$headers): int {
            $parts = explode(':', $line, 2);
            if (count($parts) === 2) { $headers[strtolower(trim($parts[0]))][] = trim($parts[1]); }
            return strlen($line);
        }]);
    if ($form !== null) { curl_setopt($handle, CURLOPT_POSTFIELDS, http_build_query($form)); }
    $body = curl_exec($handle);
    if ($body === false) { throw new RuntimeException('HTTP transport failed: ' . curl_error($handle)); }
    return ['status' => curl_getinfo($handle, CURLINFO_RESPONSE_CODE), 'body' => $body, 'headers' => $headers];
};
$csrf = static function (array $response): string {
    $document = new DOMDocument();
    $previous = libxml_use_internal_errors(true);
    try {
        $document->loadHTML($response['body']);
        $input = (new DOMXPath($document))->query('//input[@name="_token"]')->item(0);
        if (!$input instanceof DOMElement) { throw new RuntimeException('Expected a CSRF-protected form.'); }
        return $input->getAttribute('value');
    } finally { libxml_clear_errors(); libxml_use_internal_errors($previous); }
};
$browser = $client();
if ($phase === 'after-reload') {
    $check(str_contains($send($browser, '/about')['body'], 'acceptance-opcache-v2'), 'FPM restart serves changed PHP views');
    exit(0);
}
if ($phase !== 'exercise') { throw new RuntimeException('Unknown acceptance phase.'); }
$password = bin2hex(random_bytes(18));
$email = 'acceptance@example.test';
$page = $send($browser, '/');
$check($page['status'] === 200 && str_contains($page['body'], 'developer_setup_email'), 'first visit presents Project Setup');
$cookies = strtolower(implode(';', $page['headers']['set-cookie'] ?? []));
$check(str_contains($cookies, 'secure') && str_contains($cookies, 'httponly') && str_contains($cookies, 'samesite=lax'), 'HTTPS session cookie attributes');
$result = $send($browser, '/maintenance/setup-developer-access', ['_token' => $csrf($page),
    'project_name' => 'Acceptance Application', 'project_url' => $base, 'developer_setup_email' => $email,
    'developer_setup_password' => $password, 'developer_setup_password_confirmation' => $password]);
$check($result['status'] === 302 && $send($browser, '/developer/panel')['status'] === 200, 'setup creates the first account and opens the private panel');
$check(!str_contains((string) file_get_contents($project . '/.env'), $password), 'setup stores no plaintext password');

$environment->write(['APP_ENV' => 'production', 'APP_DEBUG' => 'false', 'DEBUG_TOOLBAR' => 'true',
    'DEVELOPER_ACCESS_SETUP_UI_ENABLED' => 'false', 'MAINTENANCE_SETUP_UI_ENABLED' => 'false']);
$cli(['config:cache']);
$cli(['route:cache']);
$guest = $client();
$login = $send($guest, '/developer');
$check($login['status'] === 200 && !str_contains($login['body'], 'developer_setup_email'), 'production exposes login, not onboarding');
$check($send($guest, '/developer/panel')['status'] === 302, 'guest cannot open the panel');
$check($send($guest, '/developer/unlock', ['developer_access_email' => $email, 'developer_access_password' => $password], ['Accept: application/json'])['status'] === 419, 'login rejects missing CSRF');
$login = $send($guest, '/developer');
$anonymous = curl_getinfo($guest, CURLINFO_COOKIELIST);
$result = $send($guest, '/developer/unlock', ['_token' => $csrf($login), 'developer_access_email' => $email, 'developer_access_password' => $password]);
$check($result['status'] === 302 && $send($guest, '/developer/panel')['status'] === 200, 'production login works with cached configuration and routes');
$check(curl_getinfo($guest, CURLINFO_COOKIELIST) !== $anonymous, 'login rotates the session');
$body = $send($guest, '/developer/panel')['body'];
$check(!str_contains($body, 'id="fnlla-debug-toolbar"'), 'production suppresses the debug toolbar despite its switch');
$before = hash_file('sha256', $project . '/.env');
$send($guest, '/maintenance/setup-developer-access', ['_token' => $csrf($send($guest, '/developer/panel')), 'developer_setup_email' => 'other@example.test']);
$check($before === hash_file('sha256', $project . '/.env'), 'production setup cannot overwrite existing credentials');
foreach (['/.env', '/.git/config', '/storage/framework/', '/database/', '/composer.json', '/missing.php'] as $path) {
    $status = $send($client(), $path)['status'];
    $check(in_array($status, [403, 404], true), 'private path is not served: ' . $path);
}
$check($send($client(), '/', null, ['Host: untrusted.example'])['status'] === 400, 'untrusted Host is rejected');

$recovery = $client();
$link = $cli(['developer:recovery-link', $email]);
if (!preg_match('#https?://[^\s]+#', $link, $match) || !str_starts_with($match[0], $base . '/developer/reset-password?token=')) {
    throw new RuntimeException('Recovery link must use the canonical application URL.');
}
$result = $send($recovery, substr($match[0], strlen($base)));
$check($result['status'] === 302 && !str_contains(implode('', $result['headers']['location'] ?? []), 'token='), 'recovery removes bearer token from the visible URL');
$reset = $send($recovery, '/developer/reset-password');
$check(str_contains(implode('', $reset['headers']['cache-control'] ?? []), 'no-store'), 'recovery responses cannot be cached');
$newPassword = bin2hex(random_bytes(18));
$reset = $send($recovery, '/developer/reset-password', ['_token' => $csrf($reset), 'password' => $newPassword, 'password_confirmation' => $newPassword]);
$check($reset['status'] === 200 && str_contains($reset['body'], 'Password updated'), 'server-owner recovery resets the password over HTTPS');
$check($send($guest, '/developer/panel')['status'] === 302 && $send($browser, '/developer/panel')['status'] === 302, 'reset revokes all previous developer sessions');
$fresh = $client();
$result = $send($fresh, '/developer/unlock', ['_token' => $csrf($send($fresh, '/developer')), 'developer_access_email' => $email, 'developer_access_password' => $newPassword]);
$check($result['status'] === 302 && $send($fresh, '/developer/panel')['status'] === 200, 'new credentials work without rebuilding configuration cache');
$replay = $client();
$send($replay, substr($match[0], strlen($base)));
$check(!str_contains($send($replay, '/developer/reset-password')['body'], 'name="password_confirmation"'), 'used recovery token cannot be replayed');

$view = $project . '/views/pages/about.php';
file_put_contents($view, '<?php declare(strict_types=1); echo "acceptance-opcache-v1 acceptance-env-" . config("app.environment");');
// The runner reloads FPM before this phase; this view has not been requested yet.
$warm = $send($client(), '/about')['body'];
$check(str_contains($warm, 'acceptance-opcache-v1'), 'serving SAPI warms the PHP view cache');
$check(str_contains($warm, 'acceptance-env-production'), 'serving SAPI uses the production configuration cache');
file_put_contents($view, '<?php declare(strict_types=1); echo "acceptance-opcache-v2";');
if (getenv('FNLLA_ACCEPTANCE_OPCACHE') === '1') {
    $check(str_contains($send($client(), '/about')['body'], 'acceptance-opcache-v1'), 'timestamp-disabled OPcache requires serving-process restart');
}
echo "HTTP acceptance passed. Mailbox delivery and arbitrary hosting topologies are not certified.\n";
