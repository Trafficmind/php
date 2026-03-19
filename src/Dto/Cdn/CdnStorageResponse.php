<?php

declare(strict_types=1);

namespace Trafficmind\Api\Dto\Cdn;

use DateTimeImmutable;
use Trafficmind\Api\Exception\TrafficmindException;

final class CdnStorageResponse
{
    public function __construct(
        public readonly string             $id,
        public readonly string             $name,
        public readonly int                $filesCount,
        public readonly int                $bytesTotal,
        public readonly bool               $needsRefresh,
        public readonly ?DateTimeImmutable $startRefreshAt = null,
        public readonly ?DateTimeImmutable $lastRefreshAt = null,
        public readonly ?DateTimeImmutable $lastFileChangeAt = null,
        public readonly ?DateTimeImmutable $deletedAt = null,
        public readonly ?DateTimeImmutable $purgeAt = null,
        public readonly ?CdnUserResponse   $cdnUser = null,
        public readonly array              $syncedDCs = [],
        public readonly array              $paths = [],
    ) {
    }

    public static function fromArray(array $data): self
    {
        try {
            return new self(
                id:               $data['id']            ?? '',
                name:             $data['name']          ?? '',
                filesCount:       $data['files_count']   ?? 0,
                bytesTotal:       $data['bytes_total']   ?? 0,
                needsRefresh:     $data['needs_refresh'] ?? false,
                startRefreshAt:   !empty($data['start_refresh_at']) ? new DateTimeImmutable($data['start_refresh_at']) : null,
                lastRefreshAt:    !empty($data['last_refresh_at']) ? new DateTimeImmutable($data['last_refresh_at']) : null,
                lastFileChangeAt: !empty($data['last_file_change_at']) ? new DateTimeImmutable($data['last_file_change_at']) : null,
                deletedAt:        !empty($data['deleted_at']) ? new DateTimeImmutable($data['deleted_at']) : null,
                purgeAt:          !empty($data['purge_at']) ? new DateTimeImmutable($data['purge_at']) : null,
                cdnUser:          !empty($data['cdn_user']) ? CdnUserResponse::fromArray($data['cdn_user']) : null,
                syncedDCs:        array_map(fn (array $i) => RefreshedCdn::fromArray($i), $data['synced_dc'] ?? []),
                paths:            array_map(fn (array $i) => CdnStoragePathResponse::fromArray($i), $data['paths'] ?? []),
            );
        } catch (\Exception $e) {
            throw new TrafficmindException($e->getMessage(), $e->getCode(), $e);
        }
    }
}
