<?php

declare(strict_types=1);

namespace Trafficmind\Api\Dto\Cdn;

use DateTimeImmutable;
use Trafficmind\Api\Exception\TrafficmindException;

final class RefreshedCdn
{
    public function __construct(
        public readonly string             $address,
        public readonly string             $name,
        public readonly bool               $status,
        public readonly ?DateTimeImmutable $refreshedAt = null,
    ) {
    }

    public static function fromArray(array $data): self
    {
        try {
            return new self(
                address:     $data['address'] ?? '',
                name:        $data['name']    ?? '',
                status:      $data['status']  ?? false,
                refreshedAt: !empty($data['refreshed_at']) ? new DateTimeImmutable($data['refreshed_at']) : null,
            );
        } catch (\Exception $e) {
            throw new TrafficmindException($e->getMessage(), $e->getCode(), $e);
        }
    }
}
