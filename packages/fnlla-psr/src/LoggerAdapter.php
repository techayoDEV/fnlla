<?php

declare(strict_types=1);

namespace Fnlla\Psr;

use Fnlla\Php\Support\Logger;
use Psr\Log\AbstractLogger;
use Psr\Log\InvalidArgumentException;
use Stringable;

final class LoggerAdapter extends AbstractLogger
{
    public function log($level, string|Stringable $message, array $context = []): void
    {
        if (!in_array($level, ["debug", "info", "notice", "warning", "error", "critical", "alert", "emergency"], true)) {
            throw new InvalidArgumentException("Unsupported log level.");
        }
        // Keep placeholders intact: interpolating secrets would bypass structured-context redaction.
        Logger::write($level, (string) $message, $context);
    }
}
