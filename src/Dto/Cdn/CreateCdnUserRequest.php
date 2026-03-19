<?php

declare(strict_types=1);

namespace Trafficmind\Api\Dto\Cdn;

final class CreateCdnUserRequest
{
    private string $storageId = '';

    public function setStorageId(string $storageId): self
    {
        if (trim($storageId) === '') {
            throw new \InvalidArgumentException('storage_id must not be empty.');
        }
        $this->storageId = $storageId;
        return $this;
    }

    public function toArray(): array
    {
        if (trim($this->storageId) === '') {
            throw new \InvalidArgumentException('storage_id is required.');
        }
        return ['storage_id' => $this->storageId];
    }
}
