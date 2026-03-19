<?php

declare(strict_types=1);

namespace Trafficmind\Api\Option;

final class RequestOptions
{
    public function __construct(
        public readonly ?float  $timeout = null,
        public readonly array   $headers = [],
        public readonly ?string $idempotencyKey = null,
    ) {
    }
}
