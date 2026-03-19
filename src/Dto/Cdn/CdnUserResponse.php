<?php

declare(strict_types=1);

namespace Trafficmind\Api\Dto\Cdn;

final class CdnUserResponse
{
    public function __construct(
        public readonly string $username,
        public readonly ?string $password,
        public readonly string $storageId,
        public readonly string $sftpHost,
        public readonly int    $sftpPort,
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            username:  $data['username']   ?? '',
            password:  $data['password']   ?? null,
            storageId: $data['storage_id'] ?? '',
            sftpHost:  $data['sftp_host']  ?? '',
            sftpPort:  $data['sftp_port']  ?? 0,
        );
    }
}
