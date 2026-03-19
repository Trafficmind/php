<?php

declare(strict_types=1);

namespace Trafficmind\Api\Dto\DomainRecordBatchRequest;

use Trafficmind\Api\Dto\DomainRecord\DomainDnsRecord;

final class ResponseDnsRecordsBatchesResult
{
    /** @var DomainDnsRecord[] */
    private array $creates = [];

    /** @var DomainDnsRecord[] */
    private array $updates = [];

    /** @var DomainDnsRecord[] */
    private array $replaces = [];

    /** @var DomainDnsRecord[] */
    private array $deletes = [];

    /** @return DomainDnsRecord[] */
    public function getCreates(): array
    {
        return $this->creates;
    }

    /** @return DomainDnsRecord[] */
    public function getUpdates(): array
    {
        return $this->updates;
    }

    /** @return DomainDnsRecord[] */
    public function getReplaces(): array
    {
        return $this->replaces;
    }

    /** @return DomainDnsRecord[] */
    public function getDeletes(): array
    {
        return $this->deletes;
    }

    public static function fromArray(array $data): self
    {
        $self = new self();

        foreach ($data['creates'] ?? [] as $item) {
            $self->creates[] = DomainDnsRecord::fromArray($item);
        }
        foreach ($data['updates'] ?? [] as $item) {
            $self->updates[] = DomainDnsRecord::fromArray($item);
        }
        foreach ($data['replaces'] ?? [] as $item) {
            $self->replaces[] = DomainDnsRecord::fromArray($item);
        }
        foreach ($data['deletes'] ?? [] as $item) {
            $self->deletes[] = DomainDnsRecord::fromArray($item);
        }

        return $self;
    }
}
