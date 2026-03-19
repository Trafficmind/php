<?php

declare(strict_types=1);

namespace Trafficmind\Api\Dto\Response;

final class ApiResponseStatus
{
    public function __construct(
        public readonly ?string $code = null,
        public readonly ?string $message = null,
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            code:    $data['code']    ?? null,
            message: $data['message'] ?? null,
        );
    }
}
