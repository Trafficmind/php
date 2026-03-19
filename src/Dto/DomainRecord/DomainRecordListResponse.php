<?php

declare(strict_types=1);

namespace Trafficmind\Api\Dto\DomainRecord;

use Trafficmind\Api\Dto\Response\ApiResponseStatus;
use Trafficmind\Api\Dto\Response\PaginationMeta;
use Trafficmind\Api\Dto\Response\ResponseMeta;

final class DomainRecordListResponse
{
    /**
     * @param DomainDnsRecord[] $items
     */
    public function __construct(
        public readonly array          $items,
        public readonly PaginationMeta $pagination,
        public readonly ?string        $searchQuery = null,
        public readonly ResponseMeta   $meta = new ResponseMeta(),
        public readonly ApiResponseStatus $status = new ApiResponseStatus(),
    ) {
    }
}
