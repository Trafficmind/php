<?php

declare(strict_types=1);

namespace Trafficmind\Api\Dto\DomainRecord;

final class DomainRecordListRequest
{
    private int    $page     = 1;
    private int    $pageSize = 20;
    private string $query    = '';

    public function setPage(int $page): self
    {
        $this->page = $page;
        return $this;
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

    public function setQuery(string $query): self
    {
        $this->query = $query;
        return $this;
    }

    public function toArray(): array
    {
        return array_filter([
            'page'      => $this->page,
            'page_size' => $this->pageSize,
            'query'     => $this->query,
        ], fn ($v) => $v !== null && $v !== '');
    }
}
