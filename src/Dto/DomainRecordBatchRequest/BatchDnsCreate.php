<?php

declare(strict_types=1);

namespace Trafficmind\Api\Dto\DomainRecordBatchRequest;

use Trafficmind\Api\Dto\DomainRecord\DomainDnsRecord;

final class BatchDnsCreate
{
    private DomainDnsRecord $domainRecord;

    public function setDomainRecord(DomainDnsRecord $domainRecord): self
    {
        $this->domainRecord = $domainRecord;
        return $this;
    }

    public function getDomainRecord(): DomainDnsRecord
    {
        return $this->domainRecord;
    }

    public function toArray(): array
    {
        return $this->domainRecord->toArray();
    }

    public static function fromArray(array $data): self
    {
        $self               = new self();
        $self->domainRecord = DomainDnsRecord::fromArray($data);
        return $self;
    }
}
