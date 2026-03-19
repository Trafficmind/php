<?php

declare(strict_types=1);

namespace Trafficmind\Api\Dto\Domain;

final class CreateDomainRequest
{
    private const ALLOWED_DNS_METHODS = ['auto', 'file', 'manual', 'source'];
    private const MAX_NAME_LENGTH     = 253;

    private string  $name           = '';
    private ?int    $groupId        = null;
    private ?string $dnsMethod      = null;
    private ?string $sourceDomainId = null;
    private ?string $dnsFileContent = null;

    public function setName(string $name): self
    {
        if (trim($name) === '') {
            throw new \InvalidArgumentException('name is required for domain creation.');
        }
        if (mb_strlen($name) > self::MAX_NAME_LENGTH) {
            throw new \InvalidArgumentException(
                sprintf('name must not exceed %d characters.', self::MAX_NAME_LENGTH)
            );
        }
        $this->name = $name;
        return $this;
    }

    public function setGroupId(?int $groupId): self
    {
        $this->groupId = $groupId;
        return $this;
    }

    public function setDnsMethod(?string $dnsMethod): self
    {
        if ($dnsMethod !== null && !in_array($dnsMethod, self::ALLOWED_DNS_METHODS, true)) {
            throw new \InvalidArgumentException(
                sprintf('Invalid dns_method "%s". Allowed values: %s.', $dnsMethod, implode(', ', self::ALLOWED_DNS_METHODS))
            );
        }
        $this->dnsMethod = $dnsMethod;
        return $this;
    }

    public function setSourceDomainId(?string $sourceDomainId): self
    {
        $this->sourceDomainId = $sourceDomainId;
        return $this;
    }

    /**
     * Sets the DNS domain file content for import.
     *
     * The API expects the content to be base64-encoded (swagger format: byte).
     * Use base64_encode() before passing the raw domain file string:
     *
     *   $request->setDnsFileContent(base64_encode($rawDomainFileContent));
     *
     * Only used when dns_method is set to "file".
     */
    public function setDnsFileContent(?string $dnsFileContent): self
    {
        $this->dnsFileContent = $dnsFileContent;
        return $this;
    }

    public function toArray(): array
    {
        if (trim($this->name) === '') {
            throw new \InvalidArgumentException('name is required for domain creation.');
        }

        $payload = [
            'name' => $this->name,
        ];

        if ($this->groupId !== null) {
            $payload['group_id'] = $this->groupId;
        }
        if ($this->dnsMethod !== null) {
            $payload['dns_method'] = $this->dnsMethod;
        }
        if ($this->sourceDomainId !== null) {
            $payload['source_domain_id'] = $this->sourceDomainId;
        }
        if ($this->dnsFileContent !== null) {
            $payload['dns_file_content'] = $this->dnsFileContent;
        }

        return $payload;
    }
}
