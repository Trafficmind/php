<?php

declare(strict_types=1);

namespace Trafficmind\Api\Dto\DomainRecord;

final class DomainDnsRecord
{
    public function __construct(
        public readonly string  $id,
        public readonly string  $type,
        public readonly string  $name,
        public readonly string  $content,
        public readonly ?bool   $proxied = null,
        public readonly ?int    $ttl = null,
        public readonly ?string $comment = null,
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            id:      $data['id']      ?? '',
            type:    $data['type']    ?? '',
            name:    $data['name']    ?? '',
            content: $data['content'] ?? '',
            proxied: isset($data['proxied']) ? (bool) $data['proxied'] : null,
            ttl:     $data['ttl']     ?? null,
            comment: $data['comment'] ?? null,
        );
    }

    public function toArray(): array
    {
        $data = [
            'type'    => $this->type,
            'name'    => $this->name,
            'content' => $this->content,
        ];

        if ($this->proxied !== null) {
            $data['proxied'] = $this->proxied;
        }

        if ($this->id !== '') {
            $data['id'] = $this->id;
        }

        if ($this->ttl !== null) {
            $data['ttl'] = $this->ttl;
        }

        if ($this->comment !== null) {
            $data['comment'] = $this->comment;
        }

        return $data;
    }
}
