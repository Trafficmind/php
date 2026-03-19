<?php

declare(strict_types=1);

namespace Trafficmind\Api\Dto\DomainRecord;

use Trafficmind\Api\Dto\Response\ApiResponseStatus;
use Trafficmind\Api\Dto\Response\ResponseMeta;

final class DomainRecordResult
{
    public function __construct(
        public readonly DomainDnsRecord   $record,
        public readonly ResponseMeta      $meta,
        public readonly ApiResponseStatus $status = new ApiResponseStatus(),
    ) {
    }
}
