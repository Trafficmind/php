<?php

declare(strict_types=1);

namespace Trafficmind\Api\Dto\Error;

final class ErrorDetail
{
    public function __construct(public readonly string $message, public readonly ?string $field = null)
    {
    }

    public static function fromArray(array $data): self
    {
        return new self(message: $data['message'] ?? '', field: $data['field'] ?? null, );
    }
}
