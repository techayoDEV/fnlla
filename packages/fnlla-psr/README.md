# Optional FNLLA PSR adapters

For Core projects using techayodev/fnlla-core. This package is not yet published
to a public registry. In a sibling workspace project:

```powershell
composer config repositories.fnlla-psr path ../fnlla/packages/fnlla-psr
composer require techayodev/fnlla-psr:@dev
```

Pin a tested package revision/version for distribution; a local junction follows
workspace changes. Complete --packages can use these adapters too; the legacy
Complete distribution has no Composer core dependency and is not auto-migrated.

Register adapters in an application provider when an external library needs them:

```php
$container->instance(\Psr\Container\ContainerInterface::class,
    new \Fnlla\Psr\ContainerAdapter($container));
$container->instance(\Psr\Log\LoggerInterface::class,
    new \Fnlla\Psr\LoggerAdapter());
```

The container adapter distinguishes unknown IDs from resolution failures and
preserves the original exception. LoggerAdapter accepts the eight PSR levels and
throws Psr\Log\InvalidArgumentException for unknown levels. Placeholders are not
interpolated: context remains structured so FNLLA's redaction can protect keys.
Do not put secrets in message strings. Context objects are logged by type, not by
calling their serializers.

## PSR-7/15 HTTP entrypoint

HttpHandler implements the real PSR-15 RequestHandlerInterface. Supply PSR-17
factories, for example nyholm/psr7 installed separately by the application:

```php
$factory = new \Nyholm\Psr7\Factory\Psr17Factory();
$handler = new \Fnlla\Psr\HttpHandler($application, $factory, $factory);
$response = $handler->handle($psrServerRequest);
```

A PSR-15 middleware pipeline can wrap this terminal handler. It preserves query,
headers, cookies, parsed array bodies, attributes, uploaded files and repeated
response headers. Input streams are bounded by security.request.max_body_bytes;
seekable input positions are restored. Uploads use temporary files cleaned after
handling, with depth/count/combined size limits. Move accepted uploads during the
request. HEAD and 204/304 do not emit a body. Object parsed bodies are rejected.

The adapter buffers bodies; it does not add streaming responses, SSE, WebSockets,
concurrent worker isolation or a server loop. Existing global/session helpers still
require normal PHP request isolation. This is HTTP interoperability, not a claim
that every FNLLA class now implements a PSR interface.
