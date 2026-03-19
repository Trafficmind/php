<?php

declare(strict_types=1);

namespace Trafficmind\Api;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\TransferStats;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;
use Trafficmind\Api\Endpoint\CdnEndpoint;
use Trafficmind\Api\Endpoint\DomainEndpoint;
use Trafficmind\Api\Endpoint\DomainRecordEndpoint;
use Trafficmind\Api\Endpoint\DomainSettingsEndpoint;
use Trafficmind\Api\Endpoint\FirewallRuleEndpoint;
use Trafficmind\Api\Exception\ApiErrorException;
use Trafficmind\Api\Exception\AuthException;
use Trafficmind\Api\Exception\ForbiddenException;
use Trafficmind\Api\Exception\NotFoundException;
use Trafficmind\Api\Exception\RateLimitException;
use Trafficmind\Api\Exception\TrafficmindException;
use Trafficmind\Api\Hook\HookInterface;
use Trafficmind\Api\Option\RequestOptions;
use Trafficmind\Api\Retry\DefaultRetryStrategy;
use Trafficmind\Api\Retry\RetryStrategyInterface;

final class TrafficmindClient
{
    private const MAX_RESPONSE_BYTES  = 10 * 1024 * 1024; // 10 MB
    private const DEFAULT_TIMEOUT     = 30.0;             // seconds
    private const DEFAULT_MAX_RETRIES = 3;

    private Client $client;
    private string $baseUrl = 'https://api.trafficmind.com/public/v1/';
    private int    $maxRetries;
    private readonly ?HookInterface         $hook;
    private readonly RetryStrategyInterface $retryStrategy;
    private readonly ?LoggerInterface $logger;
    private readonly array $defaultHeaders;

    /**
     * @param string                      $email         Account email address used for authentication.
     * @param string                      $apiKey        API key used for authentication.
     * @param string|null                 $baseUrl       Custom API base URL. Must use HTTPS.
     * @param float                       $timeout       Request timeout in seconds. Default: 30.
     * @param int                         $maxRetries    Maximum number of retry attempts for failed requests. Default: 3.
     * @param Client|null                 $httpClient    Custom Guzzle HTTP client instance.
     * @param HookInterface|null          $hook          Hook for observing requests, responses, and errors.
     * @param RetryStrategyInterface|null $retryStrategy Strategy for waiting between retry attempts. Defaults to exponential backoff.
     * @param LoggerInterface|null        $logger        PSR-3 logger for request/response/error logging.
     *
     * @note This client is NOT safe for concurrent use within a single instance in async runtimes
     *        (Swoole, ReactPHP, Amp). In such environments create a separate instance per coroutine/fiber,
     *        or use a connection pool. In PHP-FPM and CLI the client is safe — process isolation applies.
     */
    public function __construct(
        private readonly string $email,
        private readonly string $apiKey,
        ?string                 $baseUrl = null,
        float                   $timeout = self::DEFAULT_TIMEOUT,
        int                     $maxRetries = self::DEFAULT_MAX_RETRIES,
        ?Client                 $httpClient = null,
        ?HookInterface          $hook = null,
        ?RetryStrategyInterface $retryStrategy = null,
        ?LoggerInterface        $logger = null,
    ) {
        if (trim($email) === '') {
            throw new InvalidArgumentException('email must not be empty.');
        }

        if (trim($apiKey) === '') {
            throw new InvalidArgumentException('apiKey must not be empty.');
        }

        if ($baseUrl !== null) {
            if (!str_starts_with($baseUrl, 'https://')) {
                throw new InvalidArgumentException(
                    'baseUrl must use HTTPS to prevent credentials from being sent in plaintext.'
                );
            }
            $this->baseUrl = $baseUrl;
        }

        if ($timeout <= 0) {
            throw new InvalidArgumentException('timeout must be greater than 0.');
        }

        if ($maxRetries < 0) {
            throw new InvalidArgumentException('maxRetries must not be negative.');
        }

        $this->maxRetries    = $maxRetries;
        $this->hook          = $hook;
        $this->retryStrategy = $retryStrategy ?? new DefaultRetryStrategy();
        $this->logger        = $logger;

        $this->defaultHeaders = [
            'X-Access-User' => $this->email,
            'X-Access-Key'  => $this->apiKey,
            'Accept'        => 'application/json',
            'Content-Type'  => 'application/json',
        ];

        $this->client = $httpClient ?? new Client([
            'base_uri'    => $this->baseUrl,
            'http_errors' => false,
            'timeout'     => $timeout,
            'headers'     => $this->defaultHeaders,
            'curl'        => [
                CURLOPT_SSLVERSION => CURL_SSLVERSION_TLSv1_2,
            ],
        ]);
    }

    public function __debugInfo(): array
    {
        return [
            'baseUrl'    => $this->baseUrl,
            'email'      => '***REDACTED***',
            'apiKey'     => '***REDACTED***',
            'maxRetries' => $this->maxRetries,
            'hook'       => $this->hook   !== null ? $this->hook::class : null,
            'logger'     => $this->logger !== null ? $this->logger::class : null,
        ];
    }

    private function request(string $method, string $uri, array $options = [], int $attempt = 0): array
    {
        $guzzleRequest = null;
        $startTime     = microtime(true);

        $this->logger?->debug('Trafficmind request', [
            'method'  => $method,
            'uri'     => $uri,
            'attempt' => $attempt,
        ]);

        try {
            $response = $this->client->request($method, $uri, array_merge($options, [
                'http_errors' => false,
                'on_stats'    => function (TransferStats $stats) use (&$guzzleRequest): void {
                    $guzzleRequest = $stats->getRequest();
                },
            ]));

            $duration    = microtime(true) - $startTime;
            $statusCode  = $response->getStatusCode();
            $stream      = $response->getBody();
            $rawResponse = $stream->read(self::MAX_RESPONSE_BYTES);

            if (!$stream->eof()) {
                throw new TrafficmindException(
                    'API response exceeds maximum allowed size of 10 MB.',
                    $statusCode
                );
            }

            $decoded = $rawResponse === '' ? null : json_decode($rawResponse, true);

            if ($rawResponse !== '' && json_last_error() !== JSON_ERROR_NONE) {
                throw new TrafficmindException(
                    'Failed to decode API response: ' . json_last_error_msg(),
                    $statusCode
                );
            }

            if ($guzzleRequest !== null) {
                $this->hook?->onResponse($guzzleRequest, $response, $duration, $attempt);
                $requestId = is_array($decoded) ? ($decoded['meta']['request_id'] ?? null) : null;
                $this->logger?->debug('Trafficmind response', [
                    'method'      => $method,
                    'uri'         => $uri,
                    'status'      => $statusCode,
                    'duration_ms' => round($duration * 1000, 2),
                    'request_id'  => $requestId,
                ]);
            }

            if ($statusCode >= 200 && $statusCode < 300) {
                return is_array($decoded) ? $decoded : [
                    'success' => true,
                    'result'  => $decoded,
                ];
            }

            $retryAfter = (int) $response->getHeaderLine('Retry-After') ?: null;
            $requestId  = is_array($decoded) ? ($decoded['meta']['request_id'] ?? null) : null;
            $apiError   = ApiErrorException::fromResponseData(is_array($decoded) ? $decoded : [], $statusCode, $requestId);

            match (true) {
                $statusCode === 401 => throw new AuthException(
                    message:   $apiError->getMessage(),
                    statusCode: $statusCode,
                    errorType: $apiError->getErrorType(),
                    details:   $apiError->getDetails(),
                    requestId: $requestId,
                ),
                $statusCode === 403 => throw new ForbiddenException(
                    message:   $apiError->getMessage(),
                    statusCode: $statusCode,
                    errorType: $apiError->getErrorType(),
                    details:   $apiError->getDetails(),
                    requestId: $requestId,
                ),
                $statusCode === 404 => throw new NotFoundException(
                    message:   $apiError->getMessage(),
                    statusCode: $statusCode,
                    errorType: $apiError->getErrorType(),
                    details:   $apiError->getDetails(),
                    requestId: $requestId,
                ),
                $statusCode === 429 => throw new RateLimitException($apiError->getMessage(), $retryAfter, requestId: $requestId),
                default             => throw $apiError,
            };
        } catch (TrafficmindException $e) {
            $duration = microtime(true) - $startTime;
            if ($guzzleRequest !== null) {
                $this->hook?->onError($guzzleRequest, $e, $duration, $attempt);
                $this->logger?->warning('Trafficmind error', [
                    'method'     => $method,
                    'uri'        => $uri,
                    'code'       => $e->getCode(),
                    'message'    => $e->getMessage(),
                    'request_id' => $e->getRequestId(),
                ]);
            }
            throw $e;
        } catch (GuzzleException $e) {
            $duration = microtime(true) - $startTime;
            $wrapped  = new TrafficmindException(
                'HTTP transport error, code: ' . $e->getCode(),
                $e->getCode(),
                $e
            );

            if ($guzzleRequest !== null) {
                $this->hook?->onError($guzzleRequest, $wrapped, $duration, $attempt);
            }

            $this->logger?->warning('Trafficmind transport error', [
                'method'  => $method,
                'uri'     => $uri,
                'code'    => $e->getCode(),
                'message' => $e->getMessage(),
            ]);

            throw $wrapped;
        }
    }

    private function executeWithRetry(string $method, string $uri, array $options, ?RequestOptions $requestOptions = null): array
    {
        if ($requestOptions) {
            if ($requestOptions->timeout !== null) {
                $options['timeout'] = $requestOptions->timeout;
            }

            $extraHeaders = $requestOptions->headers;

            if ($requestOptions->idempotencyKey !== null) {
                $extraHeaders['X-Idempotency-Key'] = $requestOptions->idempotencyKey;
            }

            if ($extraHeaders) {
                $options['headers'] = array_merge(
                    $this->defaultHeaders,
                    $extraHeaders
                );
            }
        }

        $attempt = 0;

        while (true) {
            if ($this->hook) {
                $previewRequest = new Request(
                    $method,
                    $this->baseUrl . $uri,
                    $options['headers'] ?? $this->defaultHeaders
                );

                $this->hook->onRequest($previewRequest, $attempt);
            }

            try {
                return $this->request($method, $uri, $options, $attempt);
            } catch (RateLimitException $e) {
                if ($attempt >= $this->maxRetries) {
                    throw $e;
                }
                $delay = $e->getRetryAfter() ?? $this->backoffDelay($attempt);
                $this->retryStrategy->wait($delay);
            } catch (TrafficmindException $e) {
                if ($attempt >= $this->maxRetries || !$this->isRetryable($e)) {
                    throw $e;
                }
                $this->retryStrategy->wait($this->backoffDelay($attempt));
            }

            $attempt++;
        }
    }

    private function isRetryable(TrafficmindException $e): bool
    {
        $code = $e->getCode();
        return $code === 0 || $code >= 500;
    }

    private function backoffDelay(int $attempt): int
    {
        $base   = min(30, 2 ** $attempt);
        $jitter = random_int(0, (int) round($base * 0.2));
        return $base + $jitter;
    }

    public function get(string $uri, array $query = [], ?RequestOptions $options = null): array
    {
        return $this->executeWithRetry('GET', $uri, ['query' => $query], $options);
    }

    public function post(string $uri, array $json = [], ?RequestOptions $options = null): array
    {
        return $this->executeWithRetry('POST', $uri, ['json' => $json], $options);
    }

    public function delete(string $uri, ?RequestOptions $options = null): array
    {
        return $this->executeWithRetry('DELETE', $uri, [], $options);
    }

    public function put(string $uri, array $json = [], ?RequestOptions $options = null): array
    {
        return $this->executeWithRetry('PUT', $uri, ['json' => $json], $options);
    }

    public function patch(string $uri, array $json = [], ?RequestOptions $options = null): array
    {
        return $this->executeWithRetry('PATCH', $uri, ['json' => $json], $options);
    }

    public function postEmpty(string $uri, ?RequestOptions $options = null): array
    {
        return $this->executeWithRetry('POST', $uri, [], $options);
    }

    public function putEmpty(string $uri, ?RequestOptions $options = null): array
    {
        return $this->executeWithRetry('PUT', $uri, [], $options);
    }

    public function domains(): DomainEndpoint
    {
        return new DomainEndpoint($this);
    }

    public function cdn(): CdnEndpoint
    {
        return new CdnEndpoint($this);
    }

    public function domainRecords(): DomainRecordEndpoint
    {
        return new DomainRecordEndpoint($this);
    }

    public function firewallRules(): FirewallRuleEndpoint
    {
        return new FirewallRuleEndpoint($this);
    }

    public function domainSettings(): DomainSettingsEndpoint
    {
        return new DomainSettingsEndpoint($this);
    }
}
