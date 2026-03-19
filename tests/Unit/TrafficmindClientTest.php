<?php

declare(strict_types=1);

namespace Trafficmind\Api\Tests\Unit;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Request as GuzzleRequest;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\LoggerTrait;
use Trafficmind\Api\Exception\ApiErrorException;
use Trafficmind\Api\Exception\AuthException;
use Trafficmind\Api\Exception\ForbiddenException;
use Trafficmind\Api\Exception\NotFoundException;
use Trafficmind\Api\Exception\RateLimitException;
use Trafficmind\Api\Exception\TrafficmindException;
use Trafficmind\Api\Hook\HookInterface;
use Trafficmind\Api\Option\RequestOptions;
use Trafficmind\Api\Retry\NoopRetryStrategy;
use Trafficmind\Api\TrafficmindClient;

class TrafficmindClientTest extends TestCase
{
    private function makeClient(MockHandler $mock, array &$history = []): TrafficmindClient
    {
        $stack = HandlerStack::create($mock);
        $stack->push(Middleware::history($history));
        $httpClient = new Client(['handler' => $stack]);

        return new TrafficmindClient(
            email:      'test@example.com',
            apiKey:     'key',
            maxRetries: 0,
            httpClient: $httpClient,
        );
    }

    private function jsonResponse(mixed $data, int $status = 200): Response
    {
        return new Response($status, ['Content-Type' => 'application/json'], (string) json_encode($data));
    }

    public function testRejectsEmptyEmail(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/email/');
        new TrafficmindClient(email: '', apiKey: 'key');
    }

    public function testRejectsEmptyApiKey(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/apiKey/');
        new TrafficmindClient(email: 'test@example.com', apiKey: '');
    }

    public function testRejectsHttpBaseUrl(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/HTTPS/');
        new TrafficmindClient(email: 'test@example.com', apiKey: 'key', baseUrl: 'http://api.example.com/');
    }

    public function testAcceptsHttpsBaseUrl(): void
    {
        $client = new TrafficmindClient(
            email:   'test@example.com',
            apiKey:  'key',
            baseUrl: 'https://api.example.com/v1/',
        );
        $this->assertInstanceOf(TrafficmindClient::class, $client);
    }

    public function testRejectsNonPositiveTimeout(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new TrafficmindClient(email: 'test@example.com', apiKey: 'key', timeout: 0);
    }

    public function testRejectsNegativeMaxRetries(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new TrafficmindClient(email: 'test@example.com', apiKey: 'key', maxRetries: -1);
    }

    public function testDebugInfoRedactsCredentials(): void
    {
        $client = new TrafficmindClient(email: 'test@example.com', apiKey: 'secret-key');
        $info   = $client->__debugInfo();

        $this->assertSame('***REDACTED***', $info['email']);
        $this->assertSame('***REDACTED***', $info['apiKey']);
        $this->assertArrayNotHasKey('secret-key', $info);
    }

    public function testGetReturnsDecodedArray(): void
    {
        $mock   = new MockHandler([$this->jsonResponse(['status' => ['code' => 'ok'], 'payload' => ['items' => [['id' => 'abc']]], 'meta' => []])]);
        $client = $this->makeClient($mock);

        $result = $client->get('domains');
        $this->assertSame([['id' => 'abc']], $result['payload']['items']);
    }

    public function testPostReturnsDecodedArray(): void
    {
        $mock   = new MockHandler([$this->jsonResponse(['status' => ['code' => 'ok'], 'payload' => ['domain' => ['id' => 'xyz']], 'meta' => []])]);
        $client = $this->makeClient($mock);

        $result = $client->post('domains', ['name' => 'example.com']);
        $this->assertSame('xyz', $result['payload']['domain']['id']);
    }

    public function testThrowsAuthExceptionOn401(): void
    {
        $mock   = new MockHandler([$this->jsonResponse(['errors' => [['message' => 'Unauthorized']]], 401)]);
        $client = $this->makeClient($mock);

        $this->expectException(AuthException::class);
        $client->get('domains');
    }

    public function testThrowsForbiddenExceptionOn403(): void
    {
        $mock   = new MockHandler([$this->jsonResponse(['errors' => [['message' => 'Forbidden']]], 403)]);
        $client = $this->makeClient($mock);

        $this->expectException(ForbiddenException::class);
        $client->get('domains');
    }

    public function testThrowsNotFoundExceptionOn404(): void
    {
        $mock   = new MockHandler([$this->jsonResponse(['errors' => [['message' => 'Not found']]], 404)]);
        $client = $this->makeClient($mock);

        $this->expectException(NotFoundException::class);
        $client->get('domains/nonexistent');
    }

    public function testThrowsRateLimitExceptionOn429(): void
    {
        $mock = new MockHandler([
            new Response(429, ['Retry-After' => '30'], (string) json_encode(['errors' => [['message' => 'Too many requests']]])),
        ]);
        $client = $this->makeClient($mock);

        try {
            $client->get('domains');
            $this->fail('Expected RateLimitException');
        } catch (RateLimitException $e) {
            $this->assertSame(429, $e->getCode());
            $this->assertSame(30, $e->getRetryAfter());
        }
    }

    public function testThrowsRateLimitExceptionWithNullRetryAfterWhenHeaderAbsent(): void
    {
        $mock   = new MockHandler([$this->jsonResponse(['errors' => [['message' => 'Rate limited']]], 429)]);
        $client = $this->makeClient($mock);

        try {
            $client->get('domains');
            $this->fail('Expected RateLimitException');
        } catch (RateLimitException $e) {
            $this->assertNull($e->getRetryAfter());
        }
    }

    public function testThrowsTrafficmindExceptionOn500(): void
    {
        $mock   = new MockHandler([$this->jsonResponse(['errors' => [['message' => 'Server error']]], 500)]);
        $client = $this->makeClient($mock);

        $this->expectException(TrafficmindException::class);
        $client->get('domains');
    }

    public function testThrowsApiErrorExceptionOn422WithDetails(): void
    {
        $body = [
            'error' => [
                'message' => 'Validation failed',
                'type'    => 'validation_error',
                'details' => [
                    ['field' => 'name', 'message' => 'is required'],
                    ['field' => 'mode', 'message' => 'invalid value'],
                ],
            ],
            'meta' => ['request_id' => 'req-abc'],
        ];
        $mock   = new MockHandler([$this->jsonResponse($body, 422)]);
        $client = $this->makeClient($mock);

        try {
            $client->get('domains');
            $this->fail('Expected ApiErrorException');
        } catch (ApiErrorException $e) {
            $this->assertSame('Validation failed', $e->getMessage());
            $this->assertSame('validation_error', $e->getErrorType());
            $this->assertSame('req-abc', $e->getRequestId());
            $this->assertCount(2, $e->getDetails());
            $this->assertSame('name', $e->getDetails()[0]->field);
            $this->assertSame('is required', $e->getDetails()[0]->message);
            $this->assertSame('mode', $e->getDetails()[1]->field);
        }
    }

    public function testApiErrorExceptionHasEmptyDetailsWhenAbsent(): void
    {
        $body   = ['error' => ['message' => 'Something went wrong']];
        $mock   = new MockHandler([$this->jsonResponse($body, 500)]);
        $client = $this->makeClient($mock);

        try {
            $client->get('domains');
            $this->fail('Expected ApiErrorException');
        } catch (ApiErrorException $e) {
            $this->assertSame('Something went wrong', $e->getMessage());
            $this->assertSame([], $e->getDetails());
            $this->assertNull($e->getErrorType());
        }
    }

    public function testApiErrorExceptionHandlesNonArrayDetails(): void
    {
        $body   = ['error' => ['message' => 'Bad request', 'details' => 'some string']];
        $mock   = new MockHandler([$this->jsonResponse($body, 400)]);
        $client = $this->makeClient($mock);

        try {
            $client->get('domains');
            $this->fail('Expected ApiErrorException');
        } catch (ApiErrorException $e) {
            $this->assertSame('Bad request', $e->getMessage());
            $this->assertSame([], $e->getDetails());
        }
    }

    public function testApiErrorExceptionHandlesNullDetails(): void
    {
        $body   = ['error' => ['message' => 'Bad request', 'details' => null]];
        $mock   = new MockHandler([$this->jsonResponse($body, 400)]);
        $client = $this->makeClient($mock);

        try {
            $client->get('domains');
            $this->fail('Expected ApiErrorException');
        } catch (ApiErrorException $e) {
            $this->assertSame([], $e->getDetails());
        }
    }

    public function testThrowsOnInvalidJson(): void
    {
        $mock   = new MockHandler([new Response(200, [], 'not-json')]);
        $client = $this->makeClient($mock);

        $this->expectException(TrafficmindException::class);
        $this->expectExceptionMessageMatches('/decode/');
        $client->get('domains');
    }

    public function testThrowsWhenBodyExceedsLimit(): void
    {
        $bigBody = str_repeat('x', 10 * 1024 * 1024 + 1);
        $mock    = new MockHandler([new Response(200, [], $bigBody)]);
        $client  = $this->makeClient($mock);

        $this->expectException(TrafficmindException::class);
        $this->expectExceptionMessageMatches('/10 MB/');
        $client->get('domains');
    }

    public function testRetriesOn500AndEventuallySucceeds(): void
    {
        $mock = new MockHandler([
            $this->jsonResponse(['errors' => [['message' => 'Server error']]], 500),
            $this->jsonResponse(['errors' => [['message' => 'Server error']]], 500),
            $this->jsonResponse(['status' => ['code' => 'ok'], 'payload' => ['id' => 'abc'], 'meta' => []]),
        ]);

        $httpClient = new Client(['handler' => HandlerStack::create($mock)]);
        $client     = new TrafficmindClient(
            email: 'test@example.com',
            apiKey: 'key',
            maxRetries: 3,
            httpClient: $httpClient,
        );

        $result = $client->get('domains');
        $this->assertSame('abc', $result['payload']['id']);
    }

    public function testDoesNotRetryOn401(): void
    {
        $mock = new MockHandler([
            $this->jsonResponse(['errors' => [['message' => 'Unauthorized']]], 401),
            $this->jsonResponse(['status' => ['code' => 'ok'], 'payload' => [], 'meta' => []]),
        ]);

        $httpClient = new Client(['handler' => HandlerStack::create($mock)]);
        $client     = new TrafficmindClient(
            email: 'test@example.com',
            apiKey: 'key',
            maxRetries: 3,
            httpClient: $httpClient,
        );

        $this->expectException(AuthException::class);
        $client->get('domains');

        $this->assertSame(1, $mock->count());
    }

    public function testDoesNotRetryOn403(): void
    {
        $mock = new MockHandler([
            $this->jsonResponse(['error' => ['message' => 'Forbidden']], 403),
            $this->jsonResponse(['status' => ['code' => 'ok'], 'payload' => [], 'meta' => []]),
        ]);

        $httpClient = new Client(['handler' => HandlerStack::create($mock)]);
        $client     = new TrafficmindClient(
            email:      'test@example.com',
            apiKey:     'key',
            maxRetries: 3,
            httpClient: $httpClient,
        );

        $this->expectException(ForbiddenException::class);
        $client->get('domains');

        $this->assertSame(1, $mock->count());
    }

    public function testExhaustsRetriesAndThrows(): void
    {
        $mock = new MockHandler([
            $this->jsonResponse(['errors' => [['message' => 'Error']]], 500),
            $this->jsonResponse(['errors' => [['message' => 'Error']]], 500),
            $this->jsonResponse(['errors' => [['message' => 'Error']]], 500),
            $this->jsonResponse(['errors' => [['message' => 'Error']]], 500),
        ]);

        $httpClient = new Client(['handler' => HandlerStack::create($mock)]);
        $client     = new TrafficmindClient(
            email: 'test@example.com',
            apiKey: 'key',
            maxRetries: 3,
            httpClient: $httpClient,
        );

        $this->expectException(TrafficmindException::class);
        $client->get('domains');
    }

    public function testPutReturnsDecodedArray(): void
    {
        $mock   = new MockHandler([$this->jsonResponse(['status' => ['code' => 'ok'], 'payload' => ['id' => 'abc'], 'meta' => []])]);
        $client = $this->makeClient($mock);

        $result = $client->put('domains/d1/subscription', ['id' => 'pro']);
        $this->assertSame('abc', $result['payload']['id']);
    }

    public function testPatchReturnsDecodedArray(): void
    {
        $mock   = new MockHandler([$this->jsonResponse(['status' => ['code' => 'ok'], 'payload' => ['id' => 'ssl', 'value' => 'strict'], 'meta' => []])]);
        $client = $this->makeClient($mock);

        $result = $client->patch('domains/d1/settings/ssl', ['value' => 'strict']);
        $this->assertSame('ssl', $result['payload']['id']);
    }

    public function testResolveErrorMessageFallsBackToMessageField(): void
    {
        $mock   = new MockHandler([$this->jsonResponse(['message' => 'Something went wrong'], 500)]);
        $client = $this->makeClient($mock);

        $this->expectException(TrafficmindException::class);
        $this->expectExceptionMessage('Something went wrong');
        $client->get('domains');
    }

    public function testThrowsTrafficmindExceptionOnNetworkError(): void
    {
        $request = new GuzzleRequest('GET', 'domains');
        $mock    = new MockHandler([
            new ConnectException('Connection refused', $request),
        ]);
        $client = $this->makeClient($mock);

        $this->expectException(TrafficmindException::class);
        $this->expectExceptionMessageMatches('/transport error/');
        $client->get('domains');
    }

    public function testConstructorUsesDefaultRetryStrategyWhenNoneProvided(): void
    {
        $client = new TrafficmindClient(
            email:  'test@example.com',
            apiKey: 'key',
        );
        $this->assertInstanceOf(TrafficmindClient::class, $client);
    }

    public function testThrowsWithDefaultMessageWhenBodyEmptyOnError(): void
    {
        $mock   = new MockHandler([new Response(500, [], '')]);
        $client = $this->makeClient($mock);

        $this->expectException(TrafficmindException::class);
        $this->expectExceptionMessageMatches('/HTTP 500/');
        $client->get('domains');
    }

    public function testHookIsCalledOnRequestAndResponse(): void
    {
        $mock = new MockHandler([
            $this->jsonResponse(['status' => ['code' => 'ok'], 'payload' => [], 'meta' => []]),
        ]);

        $requestsCalled  = 0;
        $responsesCalled = 0;

        $hook = new class ($requestsCalled, $responsesCalled) implements HookInterface {
            public function __construct(
                private int &$requests,
                private int &$responses,
            ) {
            }

            public function onRequest(RequestInterface $request, int $attempt): void
            {
                $this->requests++;
            }

            public function onResponse(
                RequestInterface  $request,
                ResponseInterface $response,
                float $duration,
                int   $attempt,
            ): void {
                $this->responses++;
            }

            public function onError(
                RequestInterface     $request,
                TrafficmindException $exception,
                float                $duration,
                int                  $attempt,
            ): void {
            }
        };

        $httpClient = new Client(['handler' => HandlerStack::create($mock)]);
        $client     = new TrafficmindClient(
            email:      'test@example.com',
            apiKey:     'key',
            maxRetries: 0,
            httpClient: $httpClient,
            hook:       $hook,
        );

        $client->get('domains');

        $this->assertSame(1, $requestsCalled);
        $this->assertSame(1, $responsesCalled);
    }

    public function testHookIsCalledOnError(): void
    {
        $mock = new MockHandler([
            $this->jsonResponse(['errors' => [['message' => 'fail']]], 500),
        ]);

        $errorsCalled = 0;

        $hook = new class ($errorsCalled) implements HookInterface {
            public function __construct(private int &$errors)
            {
            }

            public function onRequest(RequestInterface $request, int $attempt): void
            {
            }

            public function onResponse(
                RequestInterface  $request,
                ResponseInterface $response,
                float $duration,
                int   $attempt,
            ): void {
            }

            public function onError(
                RequestInterface     $request,
                TrafficmindException $exception,
                float                $duration,
                int                  $attempt,
            ): void {
                $this->errors++;
            }
        };

        $httpClient = new Client(['handler' => HandlerStack::create($mock)]);
        $client     = new TrafficmindClient(
            email:      'test@example.com',
            apiKey:     'key',
            maxRetries: 0,
            httpClient: $httpClient,
            hook:       $hook,
        );

        try {
            $client->get('domains');
        } catch (TrafficmindException) {
        }

        $this->assertSame(1, $errorsCalled);
    }

    public function testResponseArrayIsReturnedAsIs(): void
    {
        $payload = ['status' => ['code' => 'ok'], 'payload' => ['id' => 's1'], 'meta' => []];
        $mock    = new MockHandler([
            new Response(200, ['Content-Type' => 'application/json'], (string) json_encode($payload)),
        ]);
        $client = $this->makeClient($mock);

        $result = $client->get('cdn/storage/s1');

        $this->assertSame($payload, $result);
        $this->assertSame(['id' => 's1'], $result['payload']);
    }

    public function testRetriesOn429WithRetryAfterAndEventuallySucceeds(): void
    {
        $mock = new MockHandler([
            new Response(429, ['Retry-After' => '1'], (string) json_encode(['errors' => [['message' => 'rate limited']]])),
            $this->jsonResponse(['status' => ['code' => 'ok'], 'payload' => ['id' => 'ok'], 'meta' => []]),
        ]);

        $httpClient = new Client(['handler' => HandlerStack::create($mock)]);
        $client     = new TrafficmindClient(
            email:         'test@example.com',
            apiKey:        'key',
            maxRetries:    1,
            httpClient:    $httpClient,
            retryStrategy: new NoopRetryStrategy(),
        );

        $result = $client->get('domains');
        $this->assertSame('ok', $result['payload']['id']);
    }

    public function testLoggerIsCalledOnSuccessfulRequest(): void
    {
        $mock = new MockHandler([
            $this->jsonResponse(['status' => ['code' => 'ok'], 'payload' => [], 'meta' => []]),
        ]);

        $logger = new class () implements LoggerInterface {
            use LoggerTrait;
            public array $logs = [];
            public function log($level, string|\Stringable $message, array $context = []): void
            {
                $this->logs[] = ['level' => $level, 'message' => $message];
            }
        };

        $httpClient = new Client(['handler' => HandlerStack::create($mock)]);
        $client     = new TrafficmindClient(
            email:      'test@example.com',
            apiKey:     'key',
            maxRetries: 0,
            httpClient: $httpClient,
            logger:     $logger,
        );

        $client->get('domains');

        $messages = array_column($logger->logs, 'message');
        $this->assertContains('Trafficmind request', $messages);
        $this->assertContains('Trafficmind response', $messages);
    }

    public function testLoggerIsCalledOnError(): void
    {
        $mock = new MockHandler([
            $this->jsonResponse(['errors' => [['message' => 'fail']]], 500),
        ]);

        $logger = new class () implements LoggerInterface {
            use LoggerTrait;
            public array $logs = [];
            public function log($level, string|\Stringable $message, array $context = []): void
            {
                $this->logs[] = ['level' => $level, 'message' => $message];
            }
        };

        $httpClient = new Client(['handler' => HandlerStack::create($mock)]);
        $client     = new TrafficmindClient(
            email:      'test@example.com',
            apiKey:     'key',
            maxRetries: 0,
            httpClient: $httpClient,
            logger:     $logger,
        );

        try {
            $client->get('domains');
        } catch (TrafficmindException) {
        }

        $messages = array_column($logger->logs, 'message');
        $this->assertContains('Trafficmind error', $messages);
    }

    public function testLoggerIsCalledOnTransportError(): void
    {
        $request = new GuzzleRequest('GET', 'domains');
        $mock    = new MockHandler([
            new ConnectException('Connection refused', $request),
        ]);

        $logger = new class () implements LoggerInterface {
            use LoggerTrait;
            public array $logs = [];
            public function log($level, string|\Stringable $message, array $context = []): void
            {
                $this->logs[] = ['level' => $level, 'message' => $message];
            }
        };

        $httpClient = new Client(['handler' => HandlerStack::create($mock)]);
        $client     = new TrafficmindClient(
            email:      'test@example.com',
            apiKey:     'key',
            maxRetries: 0,
            httpClient: $httpClient,
            logger:     $logger,
        );

        try {
            $client->get('domains');
        } catch (TrafficmindException) {
        }

        $messages = array_column($logger->logs, 'message');
        $this->assertContains('Trafficmind transport error', $messages);
    }

    public function testTimeoutIsRespectedByDefaultClient(): void
    {
        $client = new TrafficmindClient(
            email:   'test@example.com',
            apiKey:  'key',
            timeout: 5.0,
        );
        $this->assertInstanceOf(TrafficmindClient::class, $client);
    }

    public function testRequestOptionsTimeoutOverridesDefault(): void
    {
        $mock = new MockHandler([
            $this->jsonResponse(['status' => ['code' => 'ok'], 'payload' => [], 'meta' => []]),
        ]);
        $client = $this->makeClient($mock);

        $result = $client->get('domains', [], new RequestOptions(timeout: 5.0));
        $this->assertIsArray($result);
    }

    public function testRequestOptionsHeadersMergedWithDefaults(): void
    {
        $history = [];
        $mock    = new MockHandler([
            $this->jsonResponse(['status' => ['code' => 'ok'], 'payload' => [], 'meta' => []]),
        ]);
        $client = $this->makeClient($mock, $history);

        $client->get('domains', [], new RequestOptions(headers: ['X-Request-ID' => 'test-123']));

        $request = $history[0]['request'];
        $this->assertSame('test-123', $request->getHeaderLine('X-Request-ID'));
    }

    public function testRequestOptionsIdempotencyKeySentAsHeader(): void
    {
        $history = [];
        $mock    = new MockHandler([
            $this->jsonResponse(['status' => ['code' => 'ok'], 'payload' => [], 'meta' => []]),
        ]);
        $client = $this->makeClient($mock, $history);

        $client->post('domains', [], new RequestOptions(idempotencyKey: 'unique-key-123'));

        $request = $history[0]['request'];
        $this->assertSame('unique-key-123', $request->getHeaderLine('X-Idempotency-Key'));
    }

    public function testRequestOptionsIdempotencyKeyNullSendsNoHeader(): void
    {
        $history = [];
        $mock    = new MockHandler([
            $this->jsonResponse(['status' => ['code' => 'ok'], 'payload' => [], 'meta' => []]),
        ]);
        $client = $this->makeClient($mock, $history);

        $client->post('domains', []);

        $request = $history[0]['request'];
        $this->assertSame('', $request->getHeaderLine('X-Idempotency-Key'));
    }

    public function testPostEmptySendsNoBody(): void
    {
        $history = [];
        $mock    = new MockHandler([
            $this->jsonResponse(['status' => ['code' => 'ok'], 'payload' => [], 'meta' => []]),
        ]);
        $client = $this->makeClient($mock, $history);

        $client->postEmpty('cdn/storage/s1/refresh');

        $body = (string) $history[0]['request']->getBody();
        $this->assertSame('', $body);
    }

    public function testPutEmptySendsNoBody(): void
    {
        $history = [];
        $mock    = new MockHandler([
            $this->jsonResponse(['status' => ['code' => 'ok'], 'payload' => [], 'meta' => []]),
        ]);
        $client = $this->makeClient($mock, $history);

        $client->putEmpty('cdn/user/u1/revoke');

        $body = (string) $history[0]['request']->getBody();
        $this->assertSame('', $body);
    }

    public function testPostWithEmptyArrayStillSendsJsonBody(): void
    {
        $history = [];
        $mock    = new MockHandler([
            $this->jsonResponse(['status' => ['code' => 'ok'], 'payload' => [], 'meta' => []]),
        ]);
        $client = $this->makeClient($mock, $history);

        $client->post('domains', []);

        $body = (string) $history[0]['request']->getBody();
        $this->assertNotSame('', $body);
        $this->assertSame('[]', $body);
    }
}
