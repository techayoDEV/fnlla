<?php

declare(strict_types=1);

// Render real framework views in a disposable export; never use maintainer or customer data.
$project = realpath($argv[1] ?? '') ?: throw new RuntimeException('Pass a fresh Full export path.');
$temporaryRoot = realpath(sys_get_temp_dir()) ?: throw new RuntimeException('Temporary directory unavailable.');
$normalise = static fn (string $path): string => strtolower(str_replace('\\', '/', $path));
if (!str_starts_with($normalise($project), rtrim($normalise($temporaryRoot), '/') . '/')) {
    throw new RuntimeException('The demo export must be inside the operating system temporary directory.');
}
if (is_file($project . '/.env')) {
    throw new RuntimeException('Refusing an export with an existing environment file.');
}
$output = $argv[2] ?? throw new RuntimeException('Pass a private fixture output directory.');
if (!is_dir($output) && !mkdir($output, 0700, true)) {
    throw new RuntimeException('Cannot create fixture directory.');
}
$output = realpath($output) ?: throw new RuntimeException('Cannot resolve fixture directory.');
chdir($project);
$_SERVER['HTTP_HOST'] = 'example-app.test';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$_SERVER['REQUEST_URI'] = '/';
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SESSION = [];
$app = require $project . '/bootstrap/app.php';
if (developer_access()->viewState()['configured'] ?? false) {
    throw new RuntimeException('Refusing an export with existing developer access.');
}
config_set('app.environment', 'development');
config_set('app.name', 'Example App');
config_set('app.base_url', 'http://example-app.test');
config_set('app.asset_url', '');
config_set('debug.toolbar', false);
$screens = [];
$responses = [];
$capture = static function (string $path, string $name) use ($app, $output, &$screens, &$responses): void {
    $response = $app->handle(\Fnlla\Php\Http\Request::capture('', [
        'HTTP_HOST' => 'example-app.test', 'REQUEST_URI' => $path,
        'REQUEST_METHOD' => 'GET', 'REMOTE_ADDR' => '127.0.0.1',
    ]));
    if ($response->status() !== 200) {
        throw new RuntimeException($name . ': unexpected HTTP ' . $response->status());
    }
    file_put_contents($output . '/' . $name . '.html', $response->body());
    $responses[$name] = ['status' => 200, 'headers' => []];
    foreach ($response->headers() as $header => $value) {
        if (strtolower($header) === 'content-security-policy') {
            $responses[$name]['headers'][$header] = $value;
        }
    }
    $screens[] = $name;
};

// Capture genuine first-run setup BEFORE adding a session-only demonstration account.
$capture('/', 'setup');
$account = ['email' => 'developer@example.test', 'name' => 'Demo Developer', 'role' => 'admin',
    'avatar' => '', 'password_hash' => password_hash(bin2hex(random_bytes(24)), PASSWORD_DEFAULT)];
config_set('developer_access.path', '/developer');
config_set('developer_access.users', developer_access()->serializeAccounts([$account]));
$capture('/developer', 'login');
developer_access()->grantAccess($account);

config_set('developer_workspace.driver', 'file');
config_set('developer_workspace.path', 'framework/product-demo-' . bin2hex(random_bytes(8)) . '.json');
$board = new \Fnlla\Php\Support\DeveloperWorkspaceBoard();
$tasks = [
    ['Review access permissions', 'Confirm the roles needed by the application.', 'todo', 'blue', 'high'],
    ['Plan the next iteration', 'Agree the scope before starting implementation.', 'todo', 'slate', 'normal'],
    ['Add invoice validation', 'Cover required fields and invalid amounts.', 'in_progress', 'blue', 'high'],
    ['Test the recovery procedure', 'Run a restore against a disposable database.', 'in_progress', 'sky', 'normal'],
    ['Review release checks', 'Inspect application tests and deployment configuration.', 'review', 'slate', 'high'],
    ['Write application smoke tests', 'Cover the public pages and their expected responses.', 'done', 'green', 'normal'],
];
foreach ($tasks as [$title, $notes, $status, $color, $priority]) {
    $board->create(['title' => $title, 'notes' => $notes, 'status' => $status, 'color' => $color,
        'priority' => $priority, 'type' => 'task', 'client_visible' => false], $account);
}
foreach (['dashboard' => '', 'updates' => '/framework-updates', 'workspace' => '/workspace',
    'integrations' => '/integrations', 'access' => '/access'] as $name => $suffix) {
    $capture('/developer/panel' . $suffix, $name);
}

// Request metrics are collected from actual fixture requests, never invented benchmark figures.
config_set('app.debug', true);
config_set('debug.history.path', storage_path('framework/product-history-' . bin2hex(random_bytes(8)) . '.json'));
(new \Fnlla\Php\Observability\RequestHistory())->configure(true);
foreach (['/', '/about', '/services', '/contact'] as $path) {
    $response = $app->handle(\Fnlla\Php\Http\Request::capture('', [
        'HTTP_HOST' => 'example-app.test', 'REQUEST_URI' => $path,
        'REQUEST_METHOD' => 'GET', 'REMOTE_ADDR' => '127.0.0.1',
    ]));
    if ($response->status() !== 200) {
        throw new RuntimeException('A diagnostic demo request failed.');
    }
}
$capture('/developer/panel/debug', 'diagnostics');
file_put_contents($output . '/screens.json', json_encode($screens, JSON_THROW_ON_ERROR));
file_put_contents($output . '/responses.json', json_encode($responses, JSON_THROW_ON_ERROR));
echo count($screens) . " product fixtures rendered. Keep fixture HTML private; distribute reviewed screenshots only.\n";
