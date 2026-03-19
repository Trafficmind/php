<?php

declare(strict_types=1);

namespace Trafficmind\Api\Dto\DomainRecordBatchRequest;

use Trafficmind\Api\Dto\Response\ApiResponseStatus;
use Trafficmind\Api\Dto\Response\ResponseMeta;

final class DomainRecordBatchResponse
{
    public function __construct(
        public readonly ResponseDnsRecordsBatchesResult $batch,
        public readonly ResponseMeta                    $meta,
        public readonly ApiResponseStatus               $status = new ApiResponseStatus(),
    ) {
    }
}
