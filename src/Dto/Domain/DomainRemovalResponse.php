<?php

declare(strict_types=1);

namespace Trafficmind\Api\Dto\Domain;

use Trafficmind\Api\Dto\Response\ApiResponseStatus;
use Trafficmind\Api\Dto\Response\ResponseMeta;

final class DomainRemovalResponse
{
    public function __construct(
        public readonly string       $id,
        public readonly ResponseMeta $meta,
        public readonly ApiResponseStatus $status = new ApiResponseStatus(),
    ) {
    }
}
