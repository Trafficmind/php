<?php

declare(strict_types=1);

namespace Trafficmind\Api\Dto\Cdn;

final class SuccessResponse
{
    public function __construct(
        public readonly string $message,
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            message: $data['message'] ?? '',
        );
    }
}
