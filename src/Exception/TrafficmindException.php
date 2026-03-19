<?php

declare(strict_types=1);

namespace Trafficmind\Api\Exception;

use RuntimeException;

class TrafficmindException extends RuntimeException
{
    public function __construct(
        string $message,
        int $code = 0,
        ?\Throwable $previous = null,
        private readonly ?string $requestId = null,
    ) {
        parent::__construct($message, $code, $previous);
    }

    public function getStatusCode(): int
    {
        return $this->getCode();
    }

    public function getRequestId(): ?string
    {
        return $this->requestId;
    }
}
