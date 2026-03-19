<?php

declare(strict_types=1);

namespace Trafficmind\Api\Exception;

class RateLimitException extends TrafficmindException
{
    public function __construct(
        string $message,
        private readonly ?int $retryAfter = null,
        ?\Throwable $previous = null,
        ?string $requestId = null,
    ) {
        parent::__construct($message, 429, $previous, $requestId);
    }

    /** @return int|null Seconds to wait before retrying, null if header was absent. */
    public function getRetryAfter(): ?int
    {
        return $this->retryAfter;
    }
}
