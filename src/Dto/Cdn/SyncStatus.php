<?php

declare(strict_types=1);

namespace Trafficmind\Api\Dto\Cdn;

final class SyncStatus
{
    public function __construct(
        public readonly string $status,
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            status: $data['status'] ?? '',
        );
    }
}
