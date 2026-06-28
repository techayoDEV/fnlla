<?php

declare(strict_types=1);

/*
===============================================================================
FNLLA PHP APPLICATION KERNEL
File: src\Application.php
Copyright (c) 2026 TechAyo LTD (techayo.co.uk). Released under the MIT License.
===============================================================================

FNLLA PHP is produced, maintained and distributed by TechAyo LTD
(techayo.co.uk). This repository is the authoritative maintainer workspace for
the FNLLA PHP framework released under the MIT License and its related delivery scripts, tests,
templates and release metadata.

Purpose:
- Coordinates the maintained request lifecycle for the FNLLA PHP runtime.
*/

namespace Fnlla\Php;

use Fnlla\Php\Container\Container;
use Fnlla\Php\Exceptions\ExceptionHandler;
use Fnlla\Php\Http\Request;
use Fnlla\Php\Http\Response;
use Fnlla\Php\Http\Resources\JsonResource;
use Fnlla\Php\Http\Resources\ResourceCollection;
use Fnlla\Php\Middleware\Pipeline;
use Fnlla\Php\Routing\Router;
use Throwable;

final class Application
{
    /* Global middleware applies before route-level middleware and wraps the entire request lifecycle. */
    private array $middleware = [];

    public function __construct(
        private Router $router,
        private Container $container,
        private ExceptionHandler $exceptionHandler
    )
    {
    }

    public function middleware(array|string $middleware): self
    {
        $items = is_array($middleware) ? $middleware : [$middleware];
        $this->middleware = array_values(array_merge($this->middleware, $items));

        return $this;
    }

    public function run(): void
    {
        $request = Request::capture();
        $response = $this->handle($request);
        $response->send();
    }

    public function handle(Request $request): Response
    {
        try {
            /* Route dispatch is treated as the pipeline destination so framework middleware stays transport-agnostic. */
            $pipeline = new Pipeline($this->container);
            $result = $pipeline->process($request, $this->middleware, fn (Request $request): mixed => $this->router->dispatch($request));
        } catch (Throwable $exception) {
            $this->exceptionHandler->report($exception, $request);
            $response = $this->exceptionHandler->render($exception, $request);

            return $this->finalizeResponse($request, $response);
        }

        return $this->finalizeResponse($request, $this->normalizeResponse($result));
    }

    private function normalizeResponse(mixed $result): Response
    {
        /* Keep controller and route return types ergonomic while centralizing the final HTTP normalization rules. */
        if ($result instanceof Response) {
            return $result;
        }

        if (is_string($result)) {
            return Response::html($result);
        }

        if (is_array($result)) {
            return Response::json($result);
        }

        if ($result instanceof JsonResource || $result instanceof ResourceCollection) {
            return Response::json($result->resolve());
        }

        if ($result === null) {
            return Response::empty();
        }

        if (is_scalar($result) || (is_object($result) && method_exists($result, "__toString"))) {
            return Response::html((string) $result);
        }

        return Response::json($result);
    }

    private function finalizeResponse(Request $request, Response $response): Response
    {
        /* Request IDs are always attached at the edge so logs and client-visible responses stay correlated. */
        $final = $response->withHeader("X-Request-Id", $request->requestId());

        if ($request->method() === "HEAD") {
            return $final->withoutBody();
        }

        return $final;
    }
}
