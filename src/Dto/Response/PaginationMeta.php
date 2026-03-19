<?php

declare(strict_types=1);

namespace Trafficmind\Api\Dto\Response;

final class PaginationMeta
{
    public function __construct(
        public readonly int $items,
        public readonly int $page,
        public readonly int $pageSize,
        public readonly int $total,
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            items:    (int) ($data['items'] ?? 0),
            page:     (int) ($data['page'] ?? 0),
            pageSize: (int) ($data['page_size'] ?? 0),
            total:    (int) ($data['total'] ?? 0),
        );
    }
}
