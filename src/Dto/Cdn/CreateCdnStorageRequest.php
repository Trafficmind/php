<?php

declare(strict_types=1);

namespace Trafficmind\Api\Dto\Cdn;

final class CreateCdnStorageRequest
{
    private const MAX_NAME_LENGTH = 255;

    private string $name = '';

    public function setName(string $name): self
    {
        if (trim($name) === '') {
            throw new \InvalidArgumentException('name is required for CDN storage creation.');
        }
        if (mb_strlen($name) > self::MAX_NAME_LENGTH) {
            throw new \InvalidArgumentException(
                sprintf('name must not exceed %d characters.', self::MAX_NAME_LENGTH)
            );
        }
        $this->name = $name;
        return $this;
    }

    public function toArray(): array
    {
        if (trim($this->name) === '') {
            throw new \InvalidArgumentException('name is required for CDN storage creation.');
        }
        return ['name' => $this->name];
    }
}
