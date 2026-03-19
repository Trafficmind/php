<?php

declare(strict_types=1);

namespace Trafficmind\Api\Tests\Unit\Exception;

use PHPUnit\Framework\TestCase;
use Trafficmind\Api\Exception\AuthException;
use Trafficmind\Api\Exception\ForbiddenException;
use Trafficmind\Api\Exception\NotFoundException;
use Trafficmind\Api\Exception\RateLimitException;
use Trafficmind\Api\Exception\TrafficmindException;

class ExceptionHierarchyTest extends TestCase
{
    public function testAuthExceptionExtendsTrafficmindException(): void
    {
        $e = new AuthException('Unauthorized', 401);
        $this->assertInstanceOf(TrafficmindException::class, $e);
        $this->assertSame(401, $e->getCode());
    }

    public function testNotFoundExceptionExtendsTrafficmindException(): void
    {
        $e = new NotFoundException('Not found', 404);
        $this->assertInstanceOf(TrafficmindException::class, $e);
        $this->assertSame(404, $e->getCode());
    }

    public function testRateLimitExceptionExtendsTrafficmindException(): void
    {
        $e = new RateLimitException('Rate limited', 30);
        $this->assertInstanceOf(TrafficmindException::class, $e);
        $this->assertSame(429, $e->getCode());
        $this->assertSame(30, $e->getRetryAfter());
    }

    public function testForbiddenExceptionExtendsTrafficmindException(): void
    {
        $e = new ForbiddenException('Forbidden', 403);
        $this->assertInstanceOf(TrafficmindException::class, $e);
        $this->assertSame(403, $e->getCode());
    }

    public function testRateLimitExceptionWithNullRetryAfter(): void
    {
        $e = new RateLimitException('Rate limited');
        $this->assertNull($e->getRetryAfter());
    }

    public function testCatchByBaseClassCatchesAll(): void
    {
        $exceptions = [
            new AuthException('msg', 401),
            new NotFoundException('msg', 404),
            new RateLimitException('msg'),
            new ForbiddenException('msg'),
        ];

        foreach ($exceptions as $exception) {
            $caught = false;
            try {
                throw $exception;
            } catch (TrafficmindException) {
                $caught = true;
            }
            $this->assertTrue($caught);
        }
    }

    public function testGetStatusCodeReturnsHttpStatus(): void
    {
        $e = new TrafficmindException('Not found', 404);
        $this->assertSame(404, $e->getStatusCode());
    }

    public function testGetRequestIdReturnsNullByDefault(): void
    {
        $e = new TrafficmindException('error', 500);
        $this->assertNull($e->getRequestId());
    }

    public function testGetRequestIdReturnsValueWhenProvided(): void
    {
        $e = new TrafficmindException('error', 500, requestId: 'abc-123');
        $this->assertSame('abc-123', $e->getRequestId());
    }

    public function testRateLimitExceptionPropagatesRequestId(): void
    {
        $e = new RateLimitException('Rate limited', 30, requestId: 'req-xyz');
        $this->assertSame('req-xyz', $e->getRequestId());
    }

    public function testForbiddenExceptionPropagatesRequestId(): void
    {
        $e = new ForbiddenException('Forbidden', requestId: 'req-403');
        $this->assertSame('req-403', $e->getRequestId());
    }

}
