<?php

declare(strict_types=1);

namespace Trafficmind\Api\Retry;

final class DefaultRetryStrategy implements RetryStrategyInterface
{
    public function wait(int $seconds): void
    {
        sleep($seconds);
    }
}
