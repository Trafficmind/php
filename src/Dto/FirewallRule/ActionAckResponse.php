<?php

declare(strict_types=1);

namespace Trafficmind\Api\Dto\FirewallRule;

use Trafficmind\Api\Dto\Response\ApiResponseStatus;
use Trafficmind\Api\Dto\Response\ResponseMeta;

final class ActionAckResponse
{
    public function __construct(
        public readonly bool         $acknowledged,
        public readonly ResponseMeta $meta,
        public readonly ApiResponseStatus $status = new ApiResponseStatus(),
    ) {
    }
}
