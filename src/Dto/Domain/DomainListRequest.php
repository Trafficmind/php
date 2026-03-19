<?php

declare(strict_types=1);

namespace Trafficmind\Api\Dto\Domain;

final class DomainListRequest
{
    private const ALLOWED_MATCH_MODES = [
        'contains',
        'starts_with',
        'ends_with',
        'not_equal',
        'equal',
        'starts_with_case_sensitive',
        'ends_with_case_sensitive',
        'contains_case_sensitive',
    ];

    private string $query     = '';
    private string $matchMode = '';
    private int    $page      = 1;
    private int    $pageSize  = 20;

    public function setQuery(string $query): self
    {
        $this->query = $query;
        return $this;
    }

    public function setPage(int $page): self
    {
        $this->page = $page;
        return $this;
    }

    public function setMatchMode(string $matchMode): self
    {
        if (!in_array($matchMode, self::ALLOWED_MATCH_MODES, true)) {
            throw new \InvalidArgumentException(
                sprintf('Invalid match_mode "%s". Allowed values: %s.', $matchMode, implode(', ', self::ALLOWED_MATCH_MODES))
            );
        }
        $this->matchMode = $matchMode;
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

    public function toArray(): array
    {
        return array_filter([
            'query'      => $this->query,
            'match_mode' => $this->matchMode,
            'page'       => $this->page,
            'page_size'  => $this->pageSize,
        ], fn ($v) => $v !== null && $v !== '');
    }
}
