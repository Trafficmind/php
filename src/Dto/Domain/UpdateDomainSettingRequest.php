<?php

declare(strict_types=1);

namespace Trafficmind\Api\Dto\Domain;

final class UpdateDomainSettingRequest
{
    public function __construct(
        public readonly string $value,
    ) {
    }
}
