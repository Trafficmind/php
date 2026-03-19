<?php

declare(strict_types=1);

namespace Trafficmind\Api\Retry;

interface RetryStrategyInterface
{
    public function wait(int $seconds): void;
}
