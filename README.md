# Trafficmind PHP SDK

<a href="https://github.com/trafficmind/php/actions/workflows/ci.yml"><img src="https://img.shields.io/badge/coverage-100%25-brightgreen" alt="Coverage"></a>

Official PHP SDK for the Trafficmind **Public API** (`/public/v1`): domains, domain records, CDN, firewall rules, and domain settings.

## Configuration

Trafficmind SDK requires API credentials to authenticate requests.

### Required environment variables
```dotenv
# Trafficmind API credentials
TRAFFICMIND_ACCESS_USER=example@trafficmind.com
TRAFFICMIND_ACCESS_KEY=example_api_key_123
```

### Optional environment variable
```dotenv
# Base URL for Trafficmind API (optional)
TRAFFICMIND_BASE_URL=https://api.trafficmind.com/public/v1/
```

`TRAFFICMIND_BASE_URL` is optional.  
If not provided, the SDK will use the default production API endpoint.

---

## Requirements

- PHP **8.1+**
- Composer
- `ext-json`, `ext-curl`

## Installation

### From GitHub (VCS)
```bash
composer config repositories.trafficmind vcs https://github.com/trafficmind/php
composer require trafficmind/php:dev-main
```

> Replace `dev-main` with a tag or branch that matches your release process.

## Authentication

All requests automatically include:

- `X-Access-User` — email associated with your account.
- `X-Access-Key` — global API key.

By default requests go to `https://api.trafficmind.com/public/v1/`. Use `$baseUrl` to point
the SDK at a staging or private deployment.

> The base URL must include the `/public/v1/` prefix.

## Quick start
```php
<?php

require __DIR__ . '/vendor/autoload.php';

use Trafficmind\Api\TrafficmindClient;
use Trafficmind\Api\Dto\Domain\DomainListRequest;
use Trafficmind\Api\Dto\DomainRecord\DomainRecordListRequest;

$client = new TrafficmindClient(
    email:  $_ENV['TRAFFICMIND_ACCESS_USER'],
    apiKey: $_ENV['TRAFFICMIND_ACCESS_KEY'],
);

$domains = $client->domains()->listDomains(new DomainListRequest());

if (count($domains->items) === 0) {
    exit(0);
}

$records = $client->domainRecords()->listDomainRecords(new DomainRecordListRequest(), $domains->items[0]->id);
echo 'domain records: ' . count($records->items) . PHP_EOL;
```

See `examples/basic.php` for a more complete example with error handling.

## Available resources

| Method | Description                                            |
|--------|--------------------------------------------------------|
| `$client->domains()` | List, create, get, and delete domains                  |
| `$client->domainRecords()` | List, create, update, delete, and batch domain records |
| `$client->domainSettings()` | Get and update domain settings                         |
| `$client->firewallRules()` | Create account-level and domain-level firewall rules   |
| `$client->cdn()` | Manage CDN storages and SFTP users                     |

Request and response DTOs are in `src/Dto/`.

## Error handling

Non-2xx responses throw typed exceptions:
```php
use Trafficmind\Api\Exception\AuthException;
use Trafficmind\Api\Exception\NotFoundException;
use Trafficmind\Api\Exception\RateLimitException;
use Trafficmind\Api\Exception\TrafficmindException;

try {
    $domains = $client->domains()->listDomains(new DomainListRequest());
} catch (AuthException $e) {
    // 401 / 403
} catch (NotFoundException $e) {
    // 404
} catch (RateLimitException $e) {
    echo 'retry after: ' . $e->getRetryAfter() . 's' . PHP_EOL;
} catch (TrafficmindException $e) {
    echo $e->getCode() . ': ' . $e->getMessage() . PHP_EOL;
}
```

## Retries

The client automatically retries failed requests with exponential backoff.

- Default: **3 retries** for 5xx and network errors.
- `429` responses respect the `Retry-After` header.
- Provide a custom `RetryStrategyInterface` to control wait behaviour.
```php
$client = new TrafficmindClient(
    email:         $_ENV['TRAFFICMIND_ACCESS_USER'],
    apiKey:        $_ENV['TRAFFICMIND_ACCESS_KEY'],
    maxRetries:    5,         // default: 3, set 0 to disable
    retryStrategy: new MyRetryStrategy(),
);
```

## Timeouts

Default timeout is **30 seconds**. Override per client or per request:
```php
// Client-level
$client = new TrafficmindClient(
    email:   $_ENV['TRAFFICMIND_ACCESS_USER'],
    apiKey:  $_ENV['TRAFFICMIND_ACCESS_KEY'],
    timeout: 10.0,
);

// Per-request
use Trafficmind\Api\Option\RequestOptions;

$domains = $client->domains()->listDomains(
    new DomainListRequest(),
    new RequestOptions(timeout: 5.0),
);
```

## Pagination

Use `paginate()` to lazily iterate all pages without loading everything into memory:
```php
foreach ($client->domains()->paginate(new DomainListRequest()) as $domain) {
    echo $domain->name . PHP_EOL;
}

foreach ($client->domainRecords()->paginate(new DomainRecordListRequest(), $domainId) as $record) {
    echo $record->name . PHP_EOL;
}
```

Use `listDomains()` for a single page:
```php
$domains = $client->domains()->listDomains(
    (new DomainListRequest())->setPage(2)->setPageSize(50)
);
```

See `examples/pagination.php` for a complete working example.

## Per-request options

Override timeout or add custom headers on individual requests:
```php
use Trafficmind\Api\Option\RequestOptions;

$client->get('domains', [], new RequestOptions(
    timeout: 5.0,
    headers: ['X-Request-ID' => 'abc-123'],
));
```

## Observability

### PSR-3 Logger

Pass any PSR-3 compatible logger (Monolog, Symfony Logger, etc.) for automatic
request/response/error logging:
```php
$client = new TrafficmindClient(
    email:  $_ENV['TRAFFICMIND_ACCESS_USER'],
    apiKey: $_ENV['TRAFFICMIND_ACCESS_KEY'],
    logger: $logger, // any PSR-3 LoggerInterface
);
```

### HookInterface

Implement `HookInterface` for low-level access to request/response objects —
useful for metrics or tracing.

### OpenTelemetry

Use `HookInterface` to integrate with OpenTelemetry — create a span per request
and record duration, status, and retry attempts.

To install the OpenTelemetry SDK:
```bash
composer require open-telemetry/sdk
```

See `examples/observability.php` for complete working examples of PSR-3 Logger,
HookInterface, and OpenTelemetry integration.

## Custom HTTP client

Inject a pre-configured Guzzle client for custom middleware or proxies:
```php
use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;

$stack = HandlerStack::create();
$stack->push($myMiddleware);

$client = new TrafficmindClient(
    email:      $_ENV['TRAFFICMIND_ACCESS_USER'],
    apiKey:     $_ENV['TRAFFICMIND_ACCESS_KEY'],
    httpClient: new Client(['handler' => $stack]),
);
```

## Concurrency

This client is safe for **PHP-FPM and CLI** — each request is handled in an isolated
process or sequentially.

In **async runtimes** (Swoole, ReactPHP, Amp) a single instance is **not safe for
concurrent use**. Create a separate instance per coroutine or fiber:
```php
go(function () {
    $client = new TrafficmindClient(
        email:  $_ENV['TRAFFICMIND_ACCESS_USER'],
        apiKey: $_ENV['TRAFFICMIND_ACCESS_KEY'],
    );
    $domains = $client->domains()->listDomains(new DomainListRequest());
});
```

For bulk operations across multiple domains use Guzzle Pool to send requests concurrently.
See `examples/async.php` for a complete working example.

## Project structure
```
src/
  TrafficmindClient.php     — root client, auth, retry, error handling
  Endpoint/                 — API endpoints (domains, domain records, firewall rules, CDN, ...)
  Dto/                      — request and response DTOs
  Exception/                — exception hierarchy
  Hook/                     — HookInterface for observability
  Option/                   — RequestOptions DTO
  Retry/                    — RetryStrategyInterface and default implementation
tests/
  Unit/                     — unit tests (MockHandler, no real API calls)
examples/
  basic.php                 — domains and domain records quickstart with error handling
  pagination.php            — paginate domains and domain records records
  observability.php         — PSR-3 Logger, HookInterface, and OpenTelemetry
  async.php                 — concurrent requests via Guzzle Pool
scripts/
  coverage-check.php        — coverage threshold check
```

## Running tests
```bash
composer install
composer test
```

With coverage:
```bash
composer coverage-check
```

## Security

- Treat API keys as secrets — do not commit credentials.
- Prefer environment variables or a secrets manager.
- Credentials are automatically redacted from `var_dump()` output.
- See [SECURITY.md](SECURITY.md) for vulnerability reporting.

## Support & Compatibility

- Supported PHP versions: **8.1, 8.2, 8.3, 8.4**
- Supported API: `/public/v1`
- Deprecated SDK APIs will be marked with `@deprecated` at least one minor version before removal.

## Versioning

This SDK follows [Semantic Versioning](https://semver.org/):

- Patch releases `1.0.x` — bug fixes, no breaking changes
- Minor releases `1.x.0` — new features, no breaking changes
- Major releases `x.0.0` — breaking changes, migration guide provided

## Changelog

See [CHANGELOG.md](CHANGELOG.md) for release history and breaking changes.

## License

Apache-2.0. See [LICENSE](LICENSE).
