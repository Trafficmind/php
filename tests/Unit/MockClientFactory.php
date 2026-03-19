<?php

declare(strict_types=1);

namespace Trafficmind\Api\Tests\Unit;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use Trafficmind\Api\TrafficmindClient;

final class MockClientFactory
{
    /**
     * Creates a TrafficmindClient with a mock HTTP handler.
     * Captured requests are appended to $history array for assertion.
     *
     * @param Response[] $responses
     * @param array      $history   passed by reference, filled with ['request' => ..., 'response' => ...]
     */
    public static function create(array $responses, array &$history = [], int $maxRetries = 0): TrafficmindClient
    {
        $mock  = new MockHandler($responses);
        $stack = HandlerStack::create($mock);
        $stack->push(Middleware::history($history));

        return new TrafficmindClient(
            email:      'user@example.com',
            apiKey:     'key',
            maxRetries: $maxRetries,
            httpClient: new Client([
                'handler' => $stack,
                'headers' => [
                    'X-Access-User' => 'user@example.com',
                    'X-Access-Key'  => 'key',
                ],
            ]),
        );
    }

    public static function json(mixed $data, int $status = 200): Response
    {
        return new Response($status, ['Content-Type' => 'application/json'], (string) json_encode($data));
    }

    public static function success(mixed $payload = [], int $status = 200): Response
    {
        return self::json([
            'meta'    => ['request_id' => 'test-request-id', 'timestamp' => '2026-01-01T00:00:00Z'],
            'payload' => $payload,
            'status'  => ['code' => 'ok', 'message' => 'Request processed successfully'],
        ], $status);
    }

    public static function error(string $message, int $status, string $type = 'error'): Response
    {
        return self::json([
            'error'  => ['message' => $message, 'type' => $type, 'details' => []],
            'meta'   => ['request_id' => 'test-request-id', 'timestamp' => '2026-01-01T00:00:00Z'],
            'status' => ['code' => $type, 'message' => $message],
        ], $status);
    }
}
