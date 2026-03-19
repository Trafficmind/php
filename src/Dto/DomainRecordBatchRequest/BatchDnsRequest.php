<?php

declare(strict_types=1);

namespace Trafficmind\Api\Dto\DomainRecordBatchRequest;

final class BatchDnsRequest
{
    /** @var BatchDnsCreate[] */
    private array $creates = [];

    /** @var BatchDnsUpdate[] */
    private array $updates = [];

    /** @var BatchDnsReplace[] */
    private array $replaces = [];

    /** @var BatchDnsDelete[] */
    private array $deletes = [];

    public function addCreate(BatchDnsCreate $request): static
    {
        $this->creates[] = $request;
        return $this;
    }

    public function addUpdate(BatchDnsUpdate $request): static
    {
        $this->updates[] = $request;
        return $this;
    }

    public function addReplace(BatchDnsReplace $request): static
    {
        $this->replaces[] = $request;
        return $this;
    }

    public function addDelete(BatchDnsDelete $request): static
    {
        $this->deletes[] = $request;
        return $this;
    }

    /** @return BatchDnsCreate[] */
    public function getCreates(): array
    {
        return $this->creates;
    }

    /** @return BatchDnsUpdate[] */
    public function getUpdates(): array
    {
        return $this->updates;
    }

    /** @return BatchDnsReplace[] */
    public function getReplaces(): array
    {
        return $this->replaces;
    }

    /** @return BatchDnsDelete[] */
    public function getDeletes(): array
    {
        return $this->deletes;
    }

    public function toArray(): array
    {
        $data = [];

        if ($this->creates !== []) {
            $data['creates'] = array_map(fn ($i) => $i->toArray(), $this->creates);
        }
        if ($this->updates !== []) {
            $data['updates'] = array_map(fn ($i) => $i->toArray(), $this->updates);
        }
        if ($this->replaces !== []) {
            $data['replaces'] = array_map(fn ($i) => $i->toArray(), $this->replaces);
        }
        if ($this->deletes !== []) {
            $data['deletes'] = array_map(fn ($i) => $i->toArray(), $this->deletes);
        }

        return $data;
    }

    public static function fromArray(array $data): self
    {
        $self = new self();

        foreach ($data['creates'] ?? [] as $item) {
            $self->addCreate(BatchDnsCreate::fromArray($item));
        }
        foreach ($data['updates'] ?? [] as $item) {
            $self->addUpdate(BatchDnsUpdate::fromArray($item));
        }
        foreach ($data['replaces'] ?? [] as $item) {
            $self->addReplace(BatchDnsReplace::fromArray($item));
        }
        foreach ($data['deletes'] ?? [] as $item) {
            $self->addDelete(BatchDnsDelete::fromArray($item));
        }

        return $self;
    }
}
