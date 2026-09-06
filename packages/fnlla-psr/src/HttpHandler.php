<?php

declare(strict_types=1);

namespace Fnlla\Psr;

use Fnlla\Php\Application;
use Fnlla\Php\Exceptions\ExceptionHandler;
use Fnlla\Php\Http\HttpException;
use Fnlla\Php\Http\Request;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UploadedFileInterface;
use Psr\Http\Server\RequestHandlerInterface;

/** PSR-15 terminal handler. Does not emit headers, mutate globals or own the server loop. */
final class HttpHandler implements RequestHandlerInterface
{
    public function __construct(private Application $application,
        private ResponseFactoryInterface $responses, private StreamFactoryInterface $streams) {}

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $temporary = [];
        $server = array_filter($request->getServerParams(), static fn (string $key): bool =>
            !str_starts_with(strtoupper($key), "HTTP_") && !in_array(strtoupper($key),
                ["CONTENT_TYPE", "CONTENT_LENGTH", "FNLLA_REQUEST_ID"], true), ARRAY_FILTER_USE_KEY);
        $uri = $request->getUri();
        $server["REQUEST_URI"] = ($uri->getPath() ?: "/") . ($uri->getQuery() !== "" ? "?" . $uri->getQuery() : "");
        $server["REQUEST_METHOD"] = $request->getMethod();
        $server["HTTPS"] = $uri->getScheme() === "https" ? "on" : "off";
        $server["SERVER_PORT"] = $uri->getPort() ?? ($uri->getScheme() === "https" ? 443 : 80);
        foreach ($request->getHeaders() as $name => $values) {
            $name = strtoupper(str_replace("-", "_", $name));
            $server[in_array($name, ["CONTENT_TYPE", "CONTENT_LENGTH"], true) ? $name : "HTTP_" . $name] = implode(", ", $values);
        }
        $server["HTTP_HOST"] = $request->getHeaderLine("Host") ?: $uri->getHost();
        try {
            $maximum = max(1, (int) config("security.request.max_body_bytes", 1048576));
            $raw = $this->read($request->getBody(), $maximum);
            $maximum -= strlen($raw);
            $body = $request->getParsedBody();
            if ($body !== null && !is_array($body)) {
                throw new HttpException(400, "FNLLA expects an array parsed body.");
            }
            $files = $this->files($request->getUploadedFiles(), $temporary, $maximum);
            $input = Request::capture($raw, $server, $request->getQueryParams(), $body ?? [], $request->getCookieParams(), $files);
            foreach ($request->getAttributes() as $name => $value) { $input = $input->withAttribute($name, $value); }
            $result = $this->application->handle($input);
        } catch (\Throwable $error) {
            $input = Request::fromTrustedFallback($server);
            $handler = new ExceptionHandler();
            $handler->report($error, $input);
            $result = $handler->render($error, $input)->withHeader("X-Request-Id", $input->requestId());
        } finally {
            foreach ($temporary as $file) { if (is_file($file)) { unlink($file); } }
        }
        $response = $this->responses->createResponse($result->status());
        foreach ($result->headers() as $name => $values) { $response = $response->withHeader($name, $values); }
        $empty = $request->getMethod() === "HEAD" || in_array($result->status(), [204, 304], true);
        return $response->withBody($this->streams->createStream($empty ? "" : $result->body()));
    }

    private function read(StreamInterface $stream, int $maximum): string
    {
        $position = $stream->isSeekable() ? $stream->tell() : null;
        try {
            if ($position !== null) { $stream->rewind(); }
            $body = "";
            while (true) {
                if ($stream->eof()) { break; }
                $chunk = $this->readChunk($stream, min(8192, $maximum + 1 - strlen($body)));
                if ($chunk === "") { break; }
                $body .= $chunk;
                if (strlen($body) > $maximum) { throw new HttpException(413, "Request body is too large."); }
            }
            return $body;
        } finally {
            if ($position !== null) { $stream->seek($position); }
        }
    }

    private function files(array $files, array &$temporary, int &$budget, int $depth = 0): array
    {
        if ($depth > 8) { throw new HttpException(400, "Upload nesting is too deep."); }
        $result = [];
        foreach ($files as $key => $file) {
            if (is_array($file)) { $result[$key] = $this->files($file, $temporary, $budget, $depth + 1); continue; }
            if (!$file instanceof UploadedFileInterface) { throw new HttpException(400, "Invalid uploaded file."); }
            if (count($temporary) >= 100) { throw new HttpException(413, "Too many uploaded files."); }
            $path = "";
            $size = 0;
            if ($file->getError() === UPLOAD_ERR_OK) {
                $contents = $this->read($file->getStream(), $budget);
                $size = strlen($contents);
                $budget -= $size;
                $path = tempnam(sys_get_temp_dir(), "fnlla-psr-");
                if ($path === false) { throw new \RuntimeException("Cannot stage uploaded file."); }
                $temporary[] = $path;
                if (file_put_contents($path, $contents) !== $size) { throw new \RuntimeException("Cannot persist upload."); }
            }
            $result[$key] = ["tmp_name" => $path, "name" => $file->getClientFilename() ?? "upload",
                "type" => $file->getClientMediaType() ?? "application/octet-stream", "size" => $size, "error" => $file->getError()];
        }
        return $result;
    }

    private function readChunk(StreamInterface $stream, int $length): string
    {
        $chunk = $stream->read($length);
        if ($chunk === "" && !$stream->eof()) { throw new \RuntimeException("HTTP stream made no progress."); }
        return $chunk;
    }
}
