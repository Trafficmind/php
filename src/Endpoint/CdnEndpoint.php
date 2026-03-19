<?php

declare(strict_types=1);

namespace Trafficmind\Api\Endpoint;

use Generator;
use Trafficmind\Api\Dto\Cdn\CdnStorageEnvelopeResponse;
use Trafficmind\Api\Dto\Cdn\CdnStorageListRequest;
use Trafficmind\Api\Dto\Cdn\CdnStorageListResponse;
use Trafficmind\Api\Dto\Cdn\CdnStorageResponse;
use Trafficmind\Api\Dto\Cdn\CdnUserEnvelopeResponse;
use Trafficmind\Api\Dto\Cdn\CdnUserResponse;
use Trafficmind\Api\Dto\Cdn\CreateCdnStorageRequest;
use Trafficmind\Api\Dto\Cdn\CreateCdnUserRequest;
use Trafficmind\Api\Dto\Cdn\OperationResultResponse;
use Trafficmind\Api\Dto\Cdn\SuccessResponse;
use Trafficmind\Api\Dto\Cdn\SyncStateResponse;
use Trafficmind\Api\Dto\Cdn\SyncStatus;
use Trafficmind\Api\Dto\Response\ApiResponseStatus;
use Trafficmind\Api\Dto\Response\PaginationMeta;
use Trafficmind\Api\Dto\Response\ResponseMeta;
use Trafficmind\Api\Option\RequestOptions;
use Trafficmind\Api\TrafficmindClient;

final class CdnEndpoint
{
    /** @codeCoverageIgnore */
    public function __construct(private readonly TrafficmindClient $client)
    {
    }

    /**
     * Provide full list of CDN storages which is accessible for you.
     * Returns items alongside pagination metadata (total, page, page_size).
     */
    public function listCdnStorages(?CdnStorageListRequest $request = null, ?RequestOptions $options = null): CdnStorageListResponse
    {
        $request ??= new CdnStorageListRequest();
        $data       = $this->client->get('cdn/storage', $request->toArray(), $options);
        $items      = array_map(fn ($i) => CdnStorageResponse::fromArray($i), $data['payload']['items'] ?? []);
        $pagination = PaginationMeta::fromArray($data['payload']['pagination'] ?? []);
        $meta       = ResponseMeta::fromArray($data['meta'] ?? []);
        $status     = ApiResponseStatus::fromArray($data['status'] ?? []);

        return new CdnStorageListResponse($items, $pagination, $meta, $status);
    }

    /**
     * Lazily iterate over all CDN storages across multiple pages.
     *
     * @return Generator<CdnStorageResponse>
     */
    public function paginate(?CdnStorageListRequest $request = null, ?RequestOptions $options = null): Generator
    {
        $request ??= new CdnStorageListRequest();
        $page = 1;

        while (true) {
            $request->setPage($page);
            $result = $this->listCdnStorages($request, $options);

            if (empty($result->items)) {
                return;
            }

            yield from $result->items;

            if ($result->pagination->total <= $result->pagination->page * $result->pagination->pageSize) {
                return;
            }

            $page++;
        }
    }

    /**
     * Create new CDN storage where you can store your files.
     */
    public function createCdnStorage(CreateCdnStorageRequest $req, ?RequestOptions $options = null): CdnStorageEnvelopeResponse
    {
        $data = $this->client->post('cdn/storage', $req->toArray(), $options);

        return new CdnStorageEnvelopeResponse(
            storage: CdnStorageResponse::fromArray($data['payload']['storage'] ?? []),
            meta:    ResponseMeta::fromArray($data['meta'] ?? []),
            status:  ApiResponseStatus::fromArray($data['status'] ?? []),
        );
    }

    /**
     * Delete your CDN storage.
     * Returns operation result with message from the API response.
     */
    public function deleteCdnStorage(string $storageId, ?RequestOptions $options = null): OperationResultResponse
    {
        $data = $this->client->delete("cdn/storage/$storageId", $options);

        return new OperationResultResponse(
            operation: SuccessResponse::fromArray($data['payload']['result'] ?? []),
            meta: ResponseMeta::fromArray($data['meta'] ?? []),
            status:  ApiResponseStatus::fromArray($data['status'] ?? []),
        );
    }

    /**
     * Refresh CDN storage from remote server.
     * Returns sync status from the API response.
     */
    public function refreshCdnStorage(string $storageId, ?RequestOptions $options = null): SyncStateResponse
    {
        $data = $this->client->postEmpty("cdn/storage/$storageId/refresh", $options);

        return new SyncStateResponse(
            syncState: SyncStatus::fromArray($data['payload']['sync'] ?? []),
            meta:      ResponseMeta::fromArray($data['meta'] ?? []),
            status:  ApiResponseStatus::fromArray($data['status'] ?? []),
        );
    }

    /**
     * Provide sftp credentials for connection.
     */
    public function getCdnStorageUser(string $storageId, ?RequestOptions $options = null): CdnUserEnvelopeResponse
    {
        $data = $this->client->get("cdn/storage/$storageId/user", [], $options);

        return new CdnUserEnvelopeResponse(
            user: CdnUserResponse::fromArray($data['payload']['user'] ?? []),
            meta: ResponseMeta::fromArray($data['meta'] ?? []),
            status:  ApiResponseStatus::fromArray($data['status'] ?? []),
        );
    }

    /**
     * Create new sftp credentials if they don't exist.
     */
    public function createCdnUser(CreateCdnUserRequest $request, ?RequestOptions $options = null): CdnUserEnvelopeResponse
    {
        $data = $this->client->post('cdn/user', $request->toArray(), $options);

        return new CdnUserEnvelopeResponse(
            user: CdnUserResponse::fromArray($data['payload']['user'] ?? []),
            meta: ResponseMeta::fromArray($data['meta'] ?? []),
            status:  ApiResponseStatus::fromArray($data['status'] ?? []),
        );
    }

    /**
     * Revoke old sftp credentials and simultaneously provides new credentials.
     */
    public function revokeCdnUser(string $username, ?RequestOptions $options = null): CdnUserEnvelopeResponse
    {
        $data = $this->client->postEmpty("cdn/user/$username/revoke", $options);

        return new CdnUserEnvelopeResponse(
            user: CdnUserResponse::fromArray($data['payload']['user'] ?? []),
            meta: ResponseMeta::fromArray($data['meta'] ?? []),
            status:  ApiResponseStatus::fromArray($data['status'] ?? []),
        );
    }
}
