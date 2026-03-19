<?php

declare(strict_types=1);

namespace Trafficmind\Api\Retry;

/** @codeCoverageIgnore */
final class NoopRetryStrategy implements RetryStrategyInterface
{
    public function wait(int $seconds): void
    {
    }
}
