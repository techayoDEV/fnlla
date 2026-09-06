<?php

declare(strict_types=1);

namespace Fnlla\Php\Observability;

use Fnlla\Php\Http\Request;
use Fnlla\Php\Http\Response;
use Fnlla\Php\Http\RequestLifecycleObserver;

final class RuntimeRequestObserver implements RequestLifecycleObserver
{
    public function __construct(private RequestObserver $metrics, private DebugToolbar $toolbar)
    {
    }

    public function begin(Request $request): void
    {
        $this->toolbar->begin();
    }

    public function finish(Request $request, Response $response, float $durationMs): Response
    {
        (new RequestHistory())->record($request, $response, $durationMs);
        return $this->toolbar->decorate($request, $this->metrics->observe($request, $response, $durationMs), $durationMs);
    }

    public function reset(): void
    {
        QueryTelemetry::reset();
    }
}
