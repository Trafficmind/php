<?php

declare(strict_types=1);

namespace Trafficmind\Api\Dto\DomainRecord;

use Trafficmind\Api\Dto\Response\ApiResponseStatus;
use Trafficmind\Api\Dto\Response\ResponseMeta;

final class DomainRecordDeleteResult
{
    public function __construct(
        public readonly ResponseMeta $meta,
        public readonly ApiResponseStatus $status = new ApiResponseStatus(),
    ) {
    }
}
