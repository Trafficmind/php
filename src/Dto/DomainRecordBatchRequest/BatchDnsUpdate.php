<?php

declare(strict_types=1);

namespace Trafficmind\Api\Dto\DomainRecordBatchRequest;

use Trafficmind\Api\Dto\DomainRecord\DomainDnsRecord;

final class BatchDnsUpdate
{
    private string          $id = '';
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

    public function setId(string $id): self
    {
        if (trim($id) === '') {
            throw new \InvalidArgumentException('id must not be empty.');
        }
        $this->id = $id;
        return $this;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function toArray(): array
    {
        if (trim($this->id) === '') {
            throw new \InvalidArgumentException('id must not be empty.');
        }
        $data       = $this->domainRecord->toArray();
        $data['id'] = $this->id;
        return $data;
    }

    public static function fromArray(array $data): self
    {
        $self     = new self();
        $self->id = $data['id'] ?? '';
        $self->setDomainRecord(DomainDnsRecord::fromArray($data));
        return $self;
    }
}
