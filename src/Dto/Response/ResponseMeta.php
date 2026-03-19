<?php

declare(strict_types=1);

namespace Trafficmind\Api\Dto\Response;

use DateTimeImmutable;

final class ResponseMeta
{
    public function __construct(
        public readonly ?string            $requestId = null,
        public readonly ?DateTimeImmutable $timestamp = null,
    ) {
    }

    public static function fromArray(array $data): self
    {
        $timestamp = null;
        if (!empty($data['timestamp'])) {
            try {
                $timestamp = new DateTimeImmutable($data['timestamp']);
            } catch (\Exception) {
            }
        }

        return new self(
            requestId: $data['request_id'] ?? null,
            timestamp: $timestamp,
        );
    }
}
