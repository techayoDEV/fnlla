<?php

declare(strict_types=1);

namespace Fnlla\Php\Middleware;

use Fnlla\Php\Http\Request;
use Fnlla\Php\Http\HttpException;
use Fnlla\Php\Support\DeveloperModules;

final class RequireDeveloperModule implements MiddlewareInterface
{
    public function handle(Request $request, callable $next): mixed
    {
        foreach (DeveloperModules::forRoute((string) $request->attribute("route_name", "")) as $module) {
            if (!DeveloperModules::enabled($module)) {
                throw new HttpException(404, "This route is not available.");
            }
        }
        return $next($request);
    }
}
