<?php

declare(strict_types=1);

namespace Trafficmind\Api\Dto\Cdn;

use Trafficmind\Api\Dto\Response\ApiResponseStatus;
use Trafficmind\Api\Dto\Response\ResponseMeta;

final class SyncStateResponse
{
    public function __construct(
        public readonly SyncStatus   $syncState,
        public readonly ResponseMeta $meta,
        public readonly ApiResponseStatus $status = new ApiResponseStatus(),
    ) {
    }
}
