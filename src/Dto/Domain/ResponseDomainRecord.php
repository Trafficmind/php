<?php

declare(strict_types=1);

namespace Trafficmind\Api\Dto\Domain;

final class ResponseDomainRecord
{
    public function __construct(
        public readonly string        $id,
        public readonly string        $name,
        public readonly DomainAccount $account,
        public readonly ?int          $groupId = null,
        public readonly ?array        $assignedNameservers = null,
        public readonly ?array        $originalNameservers = null,
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            id:                  $data['id']   ?? '',
            name:                $data['name'] ?? '',
            account:             DomainAccount::fromArray($data['account'] ?? []),
            groupId:             $data['group_id']             ?? null,
            assignedNameservers: $data['assigned_nameservers'] ?? [],
            originalNameservers: $data['original_nameservers'] ?? [],
        );
    }
}
