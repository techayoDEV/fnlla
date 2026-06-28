<?php

declare(strict_types=1);

/*
===============================================================================
FNLLA PHP HTTP SOURCE
File: src\Http\Response.php
Copyright (c) 2026 TechAyo LTD (techayo.co.uk). Released under the MIT License.
===============================================================================

FNLLA PHP is produced, maintained and distributed by TechAyo LTD
(techayo.co.uk). This repository is the authoritative maintainer workspace for
the FNLLA PHP framework released under the MIT License and its related delivery scripts, tests,
templates and release metadata.

Purpose:
- Implements request, response and HTTP-facing runtime primitives.
*/

namespace Fnlla\Php\Http;

use JsonException;

final class Response
{
    public function __construct(
        private string $body = "",
        private int $status = 200,
        private array $headers = []
    ) {
    }

    public static function html(string $body, int $status = 200, array $headers = []): self
    {
        return new self($body, $status, array_merge([
            "Content-Type" => "text/html; charset=UTF-8",
        ], $headers));
    }

    public static function text(string $body, int $status = 200, array $headers = []): self
    {
        return new self($body, $status, array_merge([
            "Content-Type" => "text/plain; charset=UTF-8",
        ], $headers));
    }

    public static function json(mixed $payload, int $status = 200, array $headers = []): self
    {
        try {
            $encoded = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new JsonException("Unable to encode JSON response: " . $exception->getMessage(), (int) $exception->getCode(), $exception);
        }

        return new self($encoded, $status, array_merge([
            "Content-Type" => "application/json; charset=UTF-8",
        ], $headers));
    }

    public static function empty(int $status = 204, array $headers = []): self
    {
        return new self("", $status, $headers);
    }

    public static function redirect(string $location, int $status = 302): self
    {
        return new self("", $status, [
            "Location" => $location,
        ]);
    }

    public function withHeader(string $name, string $value): self
    {
        $clone = clone $this;
        $clone->headers[$name] = $value;

        return $clone;
    }

    public function withHeaders(array $headers): self
    {
        $clone = clone $this;
        $clone->headers = array_merge($clone->headers, $headers);

        return $clone;
    }

    public function withCookie(Cookie $cookie): self
    {
        $clone = clone $this;
        $existing = $clone->headers["Set-Cookie"] ?? [];

        if (!is_array($existing)) {
            $existing = [$existing];
        }

        $existing[] = $cookie->toHeaderValue();
        $clone->headers["Set-Cookie"] = $existing;

        return $clone;
    }

    public function withoutCookie(string $name): self
    {
        return $this->withCookie(new Cookie(
            $name,
            "",
            time() - 3600,
            (string) config("session.path", "/"),
            (string) config("session.domain", ""),
            (bool) config("session.secure", false),
            (bool) config("session.http_only", true),
            (string) config("session.same_site", "Lax")
        ));
    }

    public function withoutBody(): self
    {
        $clone = clone $this;
        $clone->body = "";

        return $clone;
    }

    public function status(): int
    {
        return $this->status;
    }

    public function body(): string
    {
        return $this->body;
    }

    public function headers(): array
    {
        return $this->prepareHeaders();
    }

    public function send(): void
    {
        http_response_code($this->status);

        foreach ($this->prepareHeaders() as $name => $value) {
            if (is_array($value)) {
                foreach ($value as $headerValue) {
                    header($name . ": " . $headerValue, false);
                }
                continue;
            }

            header($name . ": " . $value, true);
        }

        if (!in_array($this->status, [204, 304], true)) {
            echo $this->body;
        }
    }

    private function prepareHeaders(): array
    {
        $defaultHeaders = config("http.security_headers", []);

        if (!is_array($defaultHeaders)) {
            $defaultHeaders = [];
        }

        return array_merge($defaultHeaders, $this->headers);
    }
}
