<?php

declare(strict_types=1);

// Run only against a disposable, fresh export. No real project settings or accounts are written.
$project = realpath($argv[1] ?? '') ?: throw new RuntimeException('Pass a fresh Full export path.');
if ($project === realpath(dirname(__DIR__, 2))) {
    throw new RuntimeException('Use a disposable export, not the maintainer workspace.');
}
$output = $argv[2] ?? throw new RuntimeException('Pass a temporary output directory.');
if (!is_dir($output) && !mkdir($output, 0700, true)) {
    throw new RuntimeException('Cannot create fixture directory.');
}
chdir($project);
$_SERVER['HTTP_HOST'] = 'brand-regression.test';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$_SERVER['REQUEST_URI'] = '/';
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SESSION = [];
$app = require $project . '/bootstrap/app.php';
if (developer_access()->viewState()['configured'] ?? false) {
    throw new RuntimeException('Refusing to use an export with existing developer access.');
}
config_set('app.environment', 'development');
config_set('app.name', 'Brand Regression');
config_set('app.base_url', 'http://brand-regression.test');
config_set('app.asset_url', '');
config_set('debug.toolbar', false);
$screens = [];
$responses = [];
$capture = static function (string $path, string $name, int $expected = 200) use ($app, $output, &$screens, &$responses): void {
    $response = $app->handle(\Fnlla\Php\Http\Request::capture('', [
        'HTTP_HOST' => 'brand-regression.test', 'REQUEST_URI' => $path,
        'REQUEST_METHOD' => 'GET', 'REMOTE_ADDR' => '127.0.0.1',
    ]));
    if ($response->status() !== $expected) {
        throw new RuntimeException($name . ': unexpected HTTP ' . $response->status());
    }
    file_put_contents($output . '/' . $name . '.html', $response->body());
    $responses[$name] = ['status' => $response->status(), 'headers' => []];
    foreach ($response->headers() as $header => $value) {
        if (strtolower($header) === 'content-security-policy') {
            $responses[$name]['headers'][$header] = $value;
        }
    }
    $screens[] = $name;
};
$capture('/', 'setup');
$account = ['email' => 'review@example.test', 'name' => 'Brand Regression', 'role' => 'admin',
    'avatar' => '', 'password_hash' => password_hash(bin2hex(random_bytes(20)), PASSWORD_DEFAULT)];
config_set('developer_access.path', '/developer');
config_set('developer_access.users', developer_access()->serializeAccounts([$account]));
$capture('/developer', 'login');
$capture('/developer/forgot-password', 'recovery');
foreach (['/' => 'home', '/about' => 'public-about', '/services' => 'services', '/contact' => 'contact', '/terms' => 'terms', '/privacy' => 'privacy'] as $path => $name) {
    $capture($path, $name);
}
$capture('/brand-fixture-missing', '404', 404);
developer_access()->grantAccess($account);
// Exercise populated states without a database or any existing workspace data.
config_set('developer_workspace.driver', 'file');
config_set('developer_workspace.path', 'framework/brand-fixture-' . bin2hex(random_bytes(6)) . '.json');
$board = new \Fnlla\Php\Support\DeveloperWorkspaceBoard();
foreach (['blue', 'slate', 'sky', 'indigo', 'green', 'red', 'yellow', 'orange'] as $index => $color) {
    $board->create(['title' => ucfirst($color) . ' delivery check', 'notes' => 'Review application readiness.',
        'color' => $color, 'status' => $index % 2 ? 'in_progress' : 'todo', 'type' => 'release',
        'priority' => 'high', 'blocked' => $index === 1,
        'due_date' => $index === 0 ? '2000-01-01' : '', 'client_visible' => true], $account);
}
foreach (['', 'project-identity', 'access', 'profile', 'settings', 'framework-updates', 'operations', 'project-logs',
    'my-todo',
    'analytics', 'heatmap', 'notifications', 'release-readiness', 'integrations', 'workspace', 'documentation', 'about', 'technical-debt', 'debug'] as $section) {
    $capture('/developer/panel' . ($section ? '/' . $section : ''), 'panel-' . ($section ?: 'dashboard'));
}
$capture('/maintenance/framework-update', 'maintenance-update');
config_set('app.debug', true);
config_set('debug.toolbar', true);
$capture('/contact', 'debug-active');
config_set('debug.toolbar', false);
developer_access()->lock();
$client = ['email' => 'client@example.test', 'name' => 'Client Fixture', 'company' => 'Example',
    'permissions' => array_keys(customer_access()->permissionOptions()), 'password_hash' => password_hash(bin2hex(random_bytes(20)), PASSWORD_DEFAULT)];
config_set('customer_access.users', customer_access()->serializeAccounts([$client]));
$capture('/client', 'client-login');
customer_access()->grantAccess($client);
foreach (['', 'kanban', 'analytics', 'heatmap'] as $section) {
    $capture('/client/panel' . ($section ? '/' . $section : ''), 'client-' . ($section ?: 'dashboard'));
}
customer_access()->lock();
config_set('maintenance.enabled', true);
config_set('maintenance.password', password_hash(bin2hex(random_bytes(20)), PASSWORD_DEFAULT));
config_set('client_preview.enabled', true);
$capture('/maintenance', 'client-preview');
file_put_contents($output . '/screens.json', json_encode($screens, JSON_THROW_ON_ERROR));
file_put_contents($output . '/responses.json', json_encode($responses, JSON_THROW_ON_ERROR));
echo count($screens) . " isolated screen fixtures rendered.\n";
