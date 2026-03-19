<?php

declare(strict_types=1);

namespace Trafficmind\Api\Dto\Cdn;

final class CdnStorageListRequest
{
    private int $page     = 1;
    private int $pageSize = 20;

    public function setPage(int $page): self
    {
        $this->page = $page;
        return $this;
    }

    public function getPage(): int
    {
        return $this->page;
    }

    public function setPageSize(int $pageSize): self
    {
        $this->pageSize = $pageSize;
        return $this;
    }

    public function getPageSize(): int
    {
        return $this->pageSize;
    }

    public function toArray(): array
    {
        return [
            'page'      => $this->page,
            'page_size' => $this->pageSize,
        ];
    }
}
