<?php

declare(strict_types=1);

namespace Trafficmind\Api\Dto\Domain;

final class DomainAccount
{
    public function __construct(
        public readonly string $id,
        public readonly string $name,
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            id:   $data['id']   ?? '',
            name: $data['name'] ?? '',
        );
    }
}
