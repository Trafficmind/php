<?php

declare(strict_types=1);

namespace Trafficmind\Api\Dto\Domain;

final class ResponseDomainSetting
{
    public function __construct(
        public readonly string $id,
        public readonly string $value,
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            id:    $data['id'] ?? '',
            value: (string) ($data['value'] ?? ''),
        );
    }
}
