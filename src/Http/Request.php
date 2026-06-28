<?php

declare(strict_types=1);

/*
===============================================================================
FNLLA PHP HTTP SOURCE
File: src\Http\Request.php
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

final class Request
{
    public function __construct(
        private string $method,
        private string $path,
        private array $query = [],
        private array $request = [],
        private array $server = [],
        private array $headers = [],
        private array $cookies = [],
        private array $files = [],
        private string $rawBody = "",
        private mixed $jsonPayload = null,
        private array $routeParams = [],
        private string $requestId = "",
        private string $originalMethod = "GET",
        private array $attributes = []
    ) {
    }

    public static function capture(
        ?string $rawBody = null,
        ?array $server = null,
        ?array $query = null,
        ?array $request = null,
        ?array $cookies = null,
        ?array $files = null
    ): self
    {
        $serverData = $server ?? $_SERVER;
        $queryData = $query ?? $_GET;
        $requestData = $request ?? $_POST;
        $cookieData = $cookies ?? $_COOKIE;
        $fileData = self::normalizeFiles($files ?? $_FILES);
        $resolvedRawBody = $rawBody ?? (string) file_get_contents("php://input");
        $headers = self::captureHeaders($serverData);
        $jsonPayload = self::parseJsonPayload($headers, $resolvedRawBody);

        if ($requestData === [] && is_array($jsonPayload)) {
            $requestData = $jsonPayload;
        }

        if ($requestData === [] && self::isFormUrlEncoded($headers)) {
            parse_str($resolvedRawBody, $parsedBody);
            if (is_array($parsedBody)) {
                $requestData = $parsedBody;
            }
        }

        $requestUri = $serverData["REQUEST_URI"] ?? "/";
        $path = parse_url($requestUri, PHP_URL_PATH) ?: "/";
        $effectiveMethod = self::detectMethod($serverData, $requestData, $headers);
        $requestId = self::detectRequestId($headers, $serverData);
        $serverData["FNLLA_REQUEST_ID"] = $requestId;

        return new self(
            $effectiveMethod,
            self::normalizePath($path),
            $queryData,
            $requestData,
            $serverData,
            $headers,
            $cookieData,
            $fileData,
            $resolvedRawBody,
            $jsonPayload,
            [],
            $requestId,
            strtoupper((string) ($serverData["REQUEST_METHOD"] ?? "GET"))
        );
    }

    public function method(): string
    {
        return $this->method;
    }

    public function path(): string
    {
        return $this->path;
    }

    public function originalMethod(): string
    {
        return $this->originalMethod;
    }

    public function input(string $key, mixed $default = null): mixed
    {
        return $this->request[$key] ?? $this->query[$key] ?? $this->routeParams[$key] ?? $default;
    }

    public function only(array $keys): array
    {
        $data = [];

        foreach ($keys as $key) {
            $data[$key] = $this->input($key);
        }

        return $data;
    }

    public function all(): array
    {
        return array_merge($this->query, $this->request, $this->routeParams);
    }

    public function query(string $key, mixed $default = null): mixed
    {
        return $this->query[$key] ?? $default;
    }

    public function header(string $key, mixed $default = null): mixed
    {
        return $this->headers[strtolower($key)] ?? $default;
    }

    public function headers(): array
    {
        return $this->headers;
    }

    public function cookie(string $key, mixed $default = null): mixed
    {
        return $this->cookies[$key] ?? $default;
    }

    public function cookies(): array
    {
        return $this->cookies;
    }

    public function file(string $key, mixed $default = null): mixed
    {
        return $this->files[$key] ?? $default;
    }

    public function files(): array
    {
        return $this->files;
    }

    public function rawBody(): string
    {
        return $this->rawBody;
    }

    public function json(?string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return $this->jsonPayload;
        }

        if (!is_array($this->jsonPayload)) {
            return $default;
        }

        return $this->jsonPayload[$key] ?? $default;
    }

    public function isJson(): bool
    {
        $contentType = strtolower((string) $this->header("Content-Type", ""));

        return str_contains($contentType, "application/json") || str_contains($contentType, "+json");
    }

    public function expectsJson(): bool
    {
        $accept = strtolower((string) $this->header("Accept", ""));
        $requestedWith = strtolower((string) $this->header("X-Requested-With", ""));

        return str_contains($accept, "application/json")
            || str_contains($accept, "text/json")
            || $requestedWith === "xmlhttprequest";
    }

    public function routeParam(string $key, mixed $default = null): mixed
    {
        return $this->routeParams[$key] ?? $default;
    }

    public function routeParams(): array
    {
        return $this->routeParams;
    }

    public function withRouteParams(array $routeParams): self
    {
        $clone = clone $this;
        $clone->routeParams = $routeParams;

        return $clone;
    }

    public function requestId(): string
    {
        return $this->requestId;
    }

    public function attribute(string $key, mixed $default = null): mixed
    {
        return $this->attributes[$key] ?? $default;
    }

    public function attributes(): array
    {
        return $this->attributes;
    }

    public function withAttribute(string $key, mixed $value): self
    {
        $clone = clone $this;
        $clone->attributes[$key] = $value;

        return $clone;
    }

    public function ip(): string
    {
        $forwardedFor = (string) $this->header("X-Forwarded-For", "");

        if ($forwardedFor !== "") {
            $parts = explode(",", $forwardedFor);

            return trim($parts[0]);
        }

        return (string) ($this->server["REMOTE_ADDR"] ?? "0.0.0.0");
    }

    public function bearerToken(): ?string
    {
        $authorization = (string) $this->header("Authorization", "");

        if (!str_starts_with(strtolower($authorization), "bearer ")) {
            return null;
        }

        $token = trim(substr($authorization, 7));

        return $token !== "" ? $token : null;
    }

    public function server(string $key, mixed $default = null): mixed
    {
        return $this->server[$key] ?? $default;
    }

    private static function captureHeaders(array $server): array
    {
        $headers = [];

        foreach ($server as $key => $value) {
            if (!is_string($key)) {
                continue;
            }

            if (str_starts_with($key, "HTTP_")) {
                $normalizedName = str_replace(" ", "-", ucwords(strtolower(str_replace("_", " ", substr($key, 5)))));
                $headers[strtolower($normalizedName)] = (string) $value;
                continue;
            }

            if (in_array($key, ["CONTENT_TYPE", "CONTENT_LENGTH", "CONTENT_MD5"], true)) {
                $normalizedName = str_replace(" ", "-", ucwords(strtolower(str_replace("_", " ", $key))));
                $headers[strtolower($normalizedName)] = (string) $value;
            }
        }

        return $headers;
    }

    private static function parseJsonPayload(array $headers, string $rawBody): mixed
    {
        $contentType = strtolower((string) ($headers["content-type"] ?? ""));

        if ($rawBody === "" || (!str_contains($contentType, "application/json") && !str_contains($contentType, "+json"))) {
            return null;
        }

        try {
            return json_decode($rawBody, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }
    }

    private static function isFormUrlEncoded(array $headers): bool
    {
        $contentType = strtolower((string) ($headers["content-type"] ?? ""));

        return str_contains($contentType, "application/x-www-form-urlencoded");
    }

    private static function detectMethod(array $server, array $request, array $headers): string
    {
        $method = strtoupper((string) ($server["REQUEST_METHOD"] ?? "GET"));

        if ($method !== "POST") {
            return $method;
        }

        $override = $request["_method"] ?? $headers["x-http-method-override"] ?? null;

        if (!is_string($override) || $override === "") {
            return $method;
        }

        $candidate = strtoupper(trim($override));
        $allowedOverrides = ["PUT", "PATCH", "DELETE", "OPTIONS", "HEAD"];

        return in_array($candidate, $allowedOverrides, true) ? $candidate : $method;
    }

    private static function detectRequestId(array $headers, array $server): string
    {
        $incoming = $headers["x-request-id"] ?? $server["FNLLA_REQUEST_ID"] ?? null;

        if (is_string($incoming) && $incoming !== "") {
            return $incoming;
        }

        return request_id();
    }

    private static function normalizePath(string $path): string
    {
        $normalized = "/" . trim($path, "/");

        return $normalized === "/" ? "/" : rtrim($normalized, "/");
    }

    private static function normalizeFiles(array $files): array
    {
        $normalized = [];

        foreach ($files as $key => $value) {
            if (!is_array($value)) {
                continue;
            }

            $normalized[$key] = self::normalizeFileValue($value);
        }

        return $normalized;
    }

    private static function normalizeFileValue(array $value): mixed
    {
        $expectedKeys = ["name", "type", "tmp_name", "error", "size"];

        if (array_diff($expectedKeys, array_keys($value)) === []) {
            if (is_array($value["name"])) {
                return self::normalizeNestedFileArray($value);
            }

            return UploadedFile::fromArray($value);
        }

        $normalized = [];

        foreach ($value as $key => $item) {
            if (is_array($item)) {
                $normalized[$key] = self::normalizeFileValue($item);
            }
        }

        return $normalized;
    }

    private static function normalizeNestedFileArray(array $value): array
    {
        $normalized = [];

        foreach (array_keys((array) $value["name"]) as $key) {
            $normalized[$key] = self::normalizeFileValue([
                "name" => $value["name"][$key] ?? null,
                "type" => $value["type"][$key] ?? null,
                "tmp_name" => $value["tmp_name"][$key] ?? null,
                "error" => $value["error"][$key] ?? null,
                "size" => $value["size"][$key] ?? null,
            ]);
        }

        return $normalized;
    }
}
