<?php

declare(strict_types=1);

namespace Trafficmind\Api\Dto\DomainRecordBatchRequest;

use Trafficmind\Api\Dto\DomainRecord\DomainDnsRecord;

final class BatchDnsDelete
{
    private string $id = '';

    private ?DomainDnsRecord $domainRecord = null;

    public function setId(string $id): self
    {
        $this->id = $id;
        return $this;
    }

    public function getId(): string
    {
        return $this->id;
    }

    /**
     * Returns the full record as returned by the API in a batch delete response.
     * Null when the request was built locally (not hydrated from an API response).
     */
    public function getDomainRecord(): ?DomainDnsRecord
    {
        return $this->domainRecord;
    }

    public function toArray(): array
    {
        return ['id' => $this->id];
    }

    public static function fromArray(array $data): self
    {
        $self               = new self();
        $self->id           = $data['id'] ?? '';
        $self->domainRecord = DomainDnsRecord::fromArray($data);
        return $self;
    }
}
