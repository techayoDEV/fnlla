<?php

declare(strict_types=1);

$router = ($rebuildRouteCache ?? false) ? new \Fnlla\Php\Routing\Router($container) : $container->make(\Fnlla\Php\Routing\Router::class);
foreach (["csrf" => \Fnlla\Php\Middleware\VerifyCsrfToken::class,
    "auth" => \Fnlla\Php\Auth\Middleware\Authenticate::class,
    "authorize" => \Fnlla\Php\Auth\Middleware\Authorize::class,
    "cors" => \Fnlla\Php\Middleware\HandleCors::class,
    "throttle" => \Fnlla\Php\Middleware\ThrottleRequests::class,
    "trusted-hosts" => \Fnlla\Php\Middleware\EnforceTrustedHosts::class] as $alias => $class) {
    $router->middleware($alias, $class);
}
$cache = framework_route_cache_path();
$cachedRoutes = ($rebuildRouteCache ?? false) ? null : \Fnlla\Php\Support\PhpArrayCache::routes($cache, "plain");
if ($cachedRoutes !== null) {
    $router->loadCachedRoutes($cachedRoutes);
} else {
    require base_path("routes/web.php");
}
return $router;
