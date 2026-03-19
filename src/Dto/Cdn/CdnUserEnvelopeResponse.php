<?php

declare(strict_types=1);

namespace Trafficmind\Api\Dto\Cdn;

use Trafficmind\Api\Dto\Response\ApiResponseStatus;
use Trafficmind\Api\Dto\Response\ResponseMeta;

final class CdnUserEnvelopeResponse
{
    public function __construct(
        public readonly CdnUserResponse   $user,
        public readonly ResponseMeta      $meta,
        public readonly ApiResponseStatus $status = new ApiResponseStatus(),
    ) {
    }
}
