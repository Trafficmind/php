<?php

declare(strict_types=1);

require __DIR__ . '/../bootstrap.php';

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\AbstractLogger;
use Trafficmind\Api\Dto\Domain\DomainListRequest;
use Trafficmind\Api\Exception\TrafficmindException;
use Trafficmind\Api\Hook\HookInterface;
use Trafficmind\Api\TrafficmindClient;

// ── Example 1: PSR-3 Logger ────────────────────────────────────────────────────
//
// Pass any PSR-3 compatible logger to get automatic request/response/error logging.
// In production replace StdoutLogger with Monolog, Symfony Logger, or any other
// PSR-3 implementation.

final class StdoutLogger extends AbstractLogger
{
	public function log($level, string|\Stringable $message, array $context = []): void
	{
		$ctx = empty($context) ? '' : ' ' . json_encode($context);
		echo sprintf('[%s] %s%s' . PHP_EOL, strtoupper((string) $level), $message, $ctx);
	}
}

echo '=== PSR-3 Logger ===' . PHP_EOL;

$clientWithLogger = new TrafficmindClient(
	email:   $_ENV['TRAFFICMIND_ACCESS_USER'] ?? throw new \RuntimeException('TRAFFICMIND_ACCESS_USER is required'),
	apiKey:  $_ENV['TRAFFICMIND_ACCESS_KEY']   ?? throw new \RuntimeException('TRAFFICMIND_ACCESS_KEY is required'),
	baseUrl: $_ENV['TRAFFICMIND_BASE_URL']   ?? null,
	logger:  new StdoutLogger(),
);

try {
	$response = $clientWithLogger->domains()->listDomains(new DomainListRequest());
	echo 'Domains: ' . count($response->items) . PHP_EOL;
} catch (TrafficmindException $e) {
	echo 'Error: ' . $e->getMessage() . PHP_EOL;
}

// ── Example 2: HookInterface ───────────────────────────────────────────────────
//
// Low-level access to request/response objects.
// Useful for metrics, custom tracing, or any logic that needs the raw PSR-7 objects.

final class StdoutHook implements HookInterface
{
	public function onRequest(RequestInterface $request, int $attempt): void
	{
		echo sprintf(
			'[hook:request] %s %s attempt=%d' . PHP_EOL,
			$request->getMethod(),
			$request->getUri(),
			$attempt,
		);
	}

	public function onResponse(RequestInterface $request, ResponseInterface $response, float $duration, int $attempt): void
	{
		echo sprintf(
			'[hook:response] %d %s duration=%sms attempt=%d' . PHP_EOL,
			$response->getStatusCode(),
			$request->getUri(),
			round($duration * 1000, 2),
			$attempt,
		);
	}

	public function onError(RequestInterface $request, TrafficmindException $exception, float $duration, int $attempt): void
	{
		echo sprintf(
			'[hook:error] code=%d message=%s duration=%sms attempt=%d' . PHP_EOL,
			$exception->getStatusCode(),
			$exception->getMessage(),
			round($duration * 1000, 2),
			$attempt,
		);
	}
}

echo PHP_EOL . '=== HookInterface ===' . PHP_EOL;

$clientWithHook = new TrafficmindClient(
	email:   $_ENV['TRAFFICMIND_ACCESS_USER'] ?? throw new \RuntimeException('TRAFFICMIND_ACCESS_USER is required'),
	apiKey:  $_ENV['TRAFFICMIND_ACCESS_KEY']   ?? throw new \RuntimeException('TRAFFICMIND_ACCESS_KEY is required'),
	baseUrl: $_ENV['TRAFFICMIND_BASE_URL']   ?? null,
	hook:    new StdoutHook(),
);

try {
	$response = $clientWithHook->domains()->listDomains(new DomainListRequest());
	echo 'Domains: ' . count($response->items) . PHP_EOL;
} catch (TrafficmindException $e) {
	echo 'Error: ' . $e->getMessage() . PHP_EOL;
}

// ── Example 3: OpenTelemetry ───────────────────────────────────────────────────
//
// Use HookInterface to create an OpenTelemetry span per request.
// Requires: composer require open-telemetry/sdk
//
// Uncomment and adapt once open-telemetry/sdk is installed.

/*
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\API\Trace\StatusCode;
use OpenTelemetry\API\Trace\TracerInterface;

final class OpenTelemetryHook implements HookInterface
{
    private array $spans = [];

    public function __construct(private readonly TracerInterface $tracer)
    {
    }

    public function onRequest(RequestInterface $request, int $attempt): void
    {
        $span = $this->tracer
            ->spanBuilder('trafficmind ' . $request->getMethod())
            ->setSpanKind(SpanKind::KIND_CLIENT)
            ->startSpan();

        $span->setAttributes([
            'http.method'  => $request->getMethod(),
            'http.url'     => (string) $request->getUri(),
            'attempt'      => $attempt,
        ]);

        $this->spans[$attempt] = $span;
    }

    public function onResponse(RequestInterface $request, ResponseInterface $response, float $duration, int $attempt): void
    {
        $span = $this->spans[$attempt] ?? null;

        $span?->setAttributes([
            'http.status_code' => $response->getStatusCode(),
            'duration_ms'      => round($duration * 1000, 2),
        ]);
        $span?->setStatus(StatusCode::STATUS_OK);
        $span?->end();

        unset($this->spans[$attempt]);
    }

    public function onError(RequestInterface $request, TrafficmindException $exception, float $duration, int $attempt): void
    {
        $span = $this->spans[$attempt] ?? null;

        $span?->setStatus(StatusCode::STATUS_ERROR, $exception->getMessage());
        $span?->recordException($exception);
        $span?->end();

        unset($this->spans[$attempt]);
    }
}

$client = new TrafficmindClient(
    email:   $_ENV['TRAFFICMIND_ACCESS_USER'] ?? throw new \RuntimeException('TRAFFICMIND_ACCESS_USER is required'),
    apiKey:  $_ENV['TRAFFICMIND_ACCESS_KEY']   ?? throw new \RuntimeException('TRAFFICMIND_ACCESS_KEY is required'),
    baseUrl: $_ENV['TRAFFICMIND_BASE_URL']   ?? null,
    hook:    new OpenTelemetryHook($tracer),
);
*/