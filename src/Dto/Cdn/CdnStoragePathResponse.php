<?php

declare(strict_types=1);

namespace Trafficmind\Api\Dto\Cdn;

final class CdnStoragePathResponse
{
    public function __construct(
        public readonly string $id,
        public readonly string $storageId,
        public readonly string $domainId,
        public readonly string $pathPrefix,
        public readonly string $fullPath,
        public readonly string $domainName = '',
        public readonly string $subdomain = '',
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            id:         $data['id']          ?? '',
            storageId:  $data['storage_id']  ?? '',
            domainId:   $data['domain_id']   ?? '',
            pathPrefix: $data['path_prefix'] ?? '',
            fullPath:   $data['full_path']   ?? '',
            domainName: $data['domain_name'] ?? '',
            subdomain:  $data['subdomain']   ?? '',
        );
    }
}
