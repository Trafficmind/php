<?php

declare(strict_types=1);

namespace Trafficmind\Api\Tests\Unit\Endpoint;

use PHPUnit\Framework\TestCase;
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
use Trafficmind\Api\Exception\TrafficmindException;
use Trafficmind\Api\Tests\Unit\MockClientFactory;

class CdnEndpointTest extends TestCase
{
    private function storageData(string $id = 's1'): array
    {
        return [
            'id'            => $id,
            'name'          => 'store1',
            'files_count'   => 10,
            'bytes_total'   => 1024,
            'needs_refresh' => false,
        ];
    }

    private function paginationData(int $total = 10, int $page = 1, int $pageSize = 20, int $items = 1): array
    {
        return [
            'total'     => $total,
            'page'      => $page,
            'page_size' => $pageSize,
            'items'     => $items,
        ];
    }

    private function userData(string $username = 'u1'): array
    {
        return [
            'username'   => $username,
            'password'   => 'secret',
            'sftp_host'  => 'sftp.example.com',
            'sftp_port'  => 22,
            'storage_id' => 's1',
        ];
    }

    public function testListStoragesSendsGetWithDefaultPaginationAndReturnsResult(): void
    {
        $history = [];
        $client  = MockClientFactory::create([
            MockClientFactory::success([
                'items'      => [$this->storageData(), $this->storageData('s2')],
                'pagination' => $this->paginationData(total: 2, items: 2),
            ]),
        ], $history);

        $result = $client->cdn()->listCdnStorages();

        $request = $history[0]['request'];
        $this->assertSame('GET', $request->getMethod());
        $this->assertStringContainsString('cdn/storage', $request->getUri()->getPath());
        $this->assertStringContainsString('page=1', $request->getUri()->getQuery());
        $this->assertStringContainsString('page_size=20', $request->getUri()->getQuery());

        $this->assertInstanceOf(CdnStorageListResponse::class, $result);
        $this->assertCount(2, $result->items);
        $this->assertInstanceOf(CdnStorageResponse::class, $result->items[0]);
        $this->assertSame('s1', $result->items[0]->id);
        $this->assertSame('s2', $result->items[1]->id);
    }

    public function testListStoragesReturnsPaginationMeta(): void
    {
        $client = MockClientFactory::create([
            MockClientFactory::success([
                'items'      => [$this->storageData()],
                'pagination' => $this->paginationData(total: 42, page: 2, pageSize: 10, items: 1),
            ]),
        ]);

        $result = $client->cdn()->listCdnStorages(
            (new CdnStorageListRequest())->setPage(2)->setPageSize(10)
        );

        $this->assertInstanceOf(PaginationMeta::class, $result->pagination);
        $this->assertSame(42, $result->pagination->total);
        $this->assertSame(2, $result->pagination->page);
        $this->assertSame(10, $result->pagination->pageSize);
        $this->assertSame(1, $result->pagination->items);
    }

    public function testListStoragesReturnsPaginationMetaWithZerosWhenMissing(): void
    {
        $client = MockClientFactory::create([
            MockClientFactory::success(['items' => []]),
        ]);

        $result = $client->cdn()->listCdnStorages();

        $this->assertInstanceOf(PaginationMeta::class, $result->pagination);
        $this->assertSame(0, $result->pagination->total);
        $this->assertSame(0, $result->pagination->page);
    }

    public function testListStoragesReturnsResponseMeta(): void
    {
        $client = MockClientFactory::create([
            MockClientFactory::success(['items' => [], 'pagination' => $this->paginationData()]),
        ]);

        $result = $client->cdn()->listCdnStorages();

        $this->assertInstanceOf(ResponseMeta::class, $result->meta);
        $this->assertSame('test-request-id', $result->meta->requestId);
        $this->assertNotNull($result->meta->timestamp);
    }

    public function testListStoragesReturnsApiResponseStatus(): void
    {
        $client = MockClientFactory::create([
            MockClientFactory::success(['items' => [], 'pagination' => $this->paginationData()]),
        ]);

        $result = $client->cdn()->listCdnStorages();

        $this->assertInstanceOf(ApiResponseStatus::class, $result->status);
        $this->assertSame('ok', $result->status->code);
        $this->assertSame('Request processed successfully', $result->status->message);
    }

    public function testListStoragesReturnsNullStatusFieldsWhenStatusMissing(): void
    {
        $client = MockClientFactory::create([
            MockClientFactory::json([
                'meta'    => ['request_id' => 'test-request-id', 'timestamp' => '2026-01-01T00:00:00Z'],
                'payload' => ['items' => [], 'pagination' => $this->paginationData()],
            ]),
        ]);

        $result = $client->cdn()->listCdnStorages();

        $this->assertInstanceOf(ApiResponseStatus::class, $result->status);
        $this->assertNull($result->status->code);
        $this->assertNull($result->status->message);
    }

    public function testListStoragesSendsCustomPaginationParams(): void
    {
        $history = [];
        $client  = MockClientFactory::create([
            MockClientFactory::success([
                'items'      => [$this->storageData()],
                'pagination' => $this->paginationData(),
            ]),
        ], $history);

        $client->cdn()->listCdnStorages(
            (new CdnStorageListRequest())->setPage(3)->setPageSize(5)
        );

        $query = $history[0]['request']->getUri()->getQuery();
        $this->assertStringContainsString('page=3', $query);
        $this->assertStringContainsString('page_size=5', $query);
    }

    public function testListStoragesReturnsEmptyItemsArray(): void
    {
        $client = MockClientFactory::create([
            MockClientFactory::success(['items' => [], 'pagination' => $this->paginationData(total: 0, items: 0)]),
        ]);

        $result = $client->cdn()->listCdnStorages();

        $this->assertSame([], $result->items);
    }

    public function testPaginateIteratesMultiplePages(): void
    {
        $client = MockClientFactory::create([
            MockClientFactory::success(['items' => [$this->storageData('s1'), $this->storageData('s2')], 'pagination' => ['total' => 4, 'page' => 1, 'page_size' => 2, 'items' => 2]]),
            MockClientFactory::success(['items' => [$this->storageData('s3'), $this->storageData('s4')], 'pagination' => ['total' => 4, 'page' => 2, 'page_size' => 2, 'items' => 2]]),
        ]);

        $storages = iterator_to_array(
            $client->cdn()->paginate((new CdnStorageListRequest())->setPageSize(2)),
            false
        );

        $this->assertCount(4, $storages);
        $this->assertSame('s1', $storages[0]->id);
        $this->assertSame('s4', $storages[3]->id);
    }

    public function testPaginateStopsWhenLastPageIsPartial(): void
    {
        $client = MockClientFactory::create([
            MockClientFactory::success(['items' => [$this->storageData('s1'), $this->storageData('s2')], 'pagination' => ['total' => 3, 'page' => 1, 'page_size' => 2, 'items' => 2]]),
            MockClientFactory::success(['items' => [$this->storageData('s3')], 'pagination' => ['total' => 3, 'page' => 2, 'page_size' => 2, 'items' => 1]]),
        ]);

        $storages = iterator_to_array(
            $client->cdn()->paginate((new CdnStorageListRequest())->setPageSize(2)),
            false
        );

        $this->assertCount(3, $storages);
        $this->assertSame('s3', $storages[2]->id);
    }

    public function testPaginateStopsWhenFirstPageIsEmpty(): void
    {
        $client = MockClientFactory::create([
            MockClientFactory::success(['items' => [], 'pagination' => ['total' => 0, 'page' => 1, 'page_size' => 20, 'items' => 0]]),
        ]);

        $storages = iterator_to_array(
            $client->cdn()->paginate(),
            false
        );

        $this->assertCount(0, $storages);
    }

    public function testPaginateUsesDefaultRequestWhenNullPassed(): void
    {
        $history = [];
        $client  = MockClientFactory::create([
            MockClientFactory::success(['items' => [$this->storageData()], 'pagination' => ['total' => 1, 'page' => 1, 'page_size' => 20, 'items' => 1]]),
        ], $history);

        iterator_to_array($client->cdn()->paginate(), false);

        $query = $history[0]['request']->getUri()->getQuery();
        $this->assertStringContainsString('page=1', $query);
        $this->assertStringContainsString('page_size=20', $query);
    }

    public function testCreateStorageSendsPostWithBodyAndReturnsCdnStorageResult(): void
    {
        $history = [];
        $client  = MockClientFactory::create([
            MockClientFactory::success(['storage' => $this->storageData()]),
        ], $history);

        $result = $client->cdn()->createCdnStorage(
            (new CreateCdnStorageRequest())->setName('store1')
        );

        $request = $history[0]['request'];
        $this->assertSame('POST', $request->getMethod());
        $this->assertStringContainsString('cdn/storage', $request->getUri()->getPath());

        $body = json_decode((string) $request->getBody(), true);
        $this->assertSame('store1', $body['name']);
        $this->assertArrayNotHasKey('domain_id', $body);

        $this->assertInstanceOf(CdnStorageEnvelopeResponse::class, $result);
        $this->assertInstanceOf(CdnStorageResponse::class, $result->storage);
        $this->assertSame('s1', $result->storage->id);
        $this->assertInstanceOf(ResponseMeta::class, $result->meta);
        $this->assertSame('test-request-id', $result->meta->requestId);
    }

    public function testCreateStorageReturnsApiResponseStatus(): void
    {
        $client = MockClientFactory::create([
            MockClientFactory::success(['storage' => $this->storageData()]),
        ]);

        $result = $client->cdn()->createCdnStorage(
            (new CreateCdnStorageRequest())->setName('store1')
        );

        $this->assertInstanceOf(ApiResponseStatus::class, $result->status);
        $this->assertSame('ok', $result->status->code);
        $this->assertSame('Request processed successfully', $result->status->message);
    }

    public function testSetNameAcceptsExactly255Characters(): void
    {
        $name    = str_repeat('a', 255);
        $request = (new CreateCdnStorageRequest())->setName($name);
        $this->assertSame($name, $request->toArray()['name']);
    }

    public function testSetNameThrowsWhenExceeds255Characters(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('name must not exceed 255 characters.');
        (new CreateCdnStorageRequest())->setName(str_repeat('a', 256));
    }

    public function testSetNameThrowsOnEmptyString(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('name is required for CDN storage creation.');
        (new CreateCdnStorageRequest())->setName('');
    }

    public function testSetNameThrowsOnBlankString(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('name is required for CDN storage creation.');
        (new CreateCdnStorageRequest())->setName('   ');
    }

    public function testToArrayThrowsWhenSetNameNeverCalled(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('name is required for CDN storage creation.');
        (new CreateCdnStorageRequest())->toArray();
    }

    public function testDeleteStorageSendsDeleteRequestAndReturnsCdnOperationResult(): void
    {
        $history = [];
        $client  = MockClientFactory::create([
            MockClientFactory::success(['result' => ['message' => 'Storage deleted successfully']]),
        ], $history);

        $result = $client->cdn()->deleteCdnStorage('s1');

        $request = $history[0]['request'];
        $this->assertSame('DELETE', $request->getMethod());
        $this->assertStringContainsString('cdn/storage/s1', $request->getUri()->getPath());

        $this->assertInstanceOf(OperationResultResponse::class, $result);
        $this->assertInstanceOf(SuccessResponse::class, $result->operation);
        $this->assertSame('Storage deleted successfully', $result->operation->message);
        $this->assertInstanceOf(ResponseMeta::class, $result->meta);
        $this->assertSame('test-request-id', $result->meta->requestId);
    }

    public function testDeleteStorageReturnsApiResponseStatus(): void
    {
        $client = MockClientFactory::create([
            MockClientFactory::success(['result' => ['message' => 'Storage deleted successfully']]),
        ]);

        $result = $client->cdn()->deleteCdnStorage('s1');

        $this->assertInstanceOf(ApiResponseStatus::class, $result->status);
        $this->assertSame('ok', $result->status->code);
    }

    public function testDeleteStorageParsesMessageFromResultKey(): void
    {
        $client = MockClientFactory::create([
            MockClientFactory::success(['result' => ['message' => 'Deleted OK']]),
        ]);

        $result = $client->cdn()->deleteCdnStorage('s1');

        $this->assertSame('Deleted OK', $result->operation->message);
    }

    public function testDeleteStorageReturnsEmptyMessageWhenResultMissing(): void
    {
        $client = MockClientFactory::create([
            MockClientFactory::success([]),
        ]);

        $result = $client->cdn()->deleteCdnStorage('s1');

        $this->assertSame('', $result->operation->message);
    }

    public function testRefreshStorageSendsPostAndReturnsSyncStateResult(): void
    {
        $history = [];
        $client  = MockClientFactory::create([
            MockClientFactory::success(['sync' => ['status' => 'in_progress']]),
        ], $history);

        $result = $client->cdn()->refreshCdnStorage('s1');

        $request = $history[0]['request'];
        $this->assertSame('POST', $request->getMethod());
        $this->assertStringContainsString('cdn/storage/s1/refresh', $request->getUri()->getPath());

        $this->assertInstanceOf(SyncStateResponse::class, $result);
        $this->assertInstanceOf(SyncStatus::class, $result->syncState);
        $this->assertSame('in_progress', $result->syncState->status);
        $this->assertInstanceOf(ResponseMeta::class, $result->meta);
        $this->assertSame('test-request-id', $result->meta->requestId);
    }

    public function testRefreshStorageReturnsApiResponseStatus(): void
    {
        $client = MockClientFactory::create([
            MockClientFactory::success(['sync' => ['status' => 'in_progress']]),
        ]);

        $result = $client->cdn()->refreshCdnStorage('s1');

        $this->assertInstanceOf(ApiResponseStatus::class, $result->status);
        $this->assertSame('ok', $result->status->code);
    }

    public function testRefreshStorageSendsNoBody(): void
    {
        $history = [];
        $client  = MockClientFactory::create([
            MockClientFactory::success(['sync' => ['status' => 'in_progress']]),
        ], $history);

        $client->cdn()->refreshCdnStorage('s1');

        $body = (string) $history[0]['request']->getBody();
        $this->assertSame('', $body);
    }

    public function testGetSftpCredentialsSendsGetAndReturnsCdnUserResult(): void
    {
        $history = [];
        $client  = MockClientFactory::create([
            MockClientFactory::success(['user' => $this->userData()]),
        ], $history);

        $result = $client->cdn()->getCdnStorageUser('s1');

        $request = $history[0]['request'];
        $this->assertSame('GET', $request->getMethod());
        $this->assertStringContainsString('cdn/storage/s1/user', $request->getUri()->getPath());

        $this->assertInstanceOf(CdnUserEnvelopeResponse::class, $result);
        $this->assertInstanceOf(CdnUserResponse::class, $result->user);
        $this->assertSame('u1', $result->user->username);
        $this->assertSame(22, $result->user->sftpPort);
        $this->assertInstanceOf(ResponseMeta::class, $result->meta);
        $this->assertSame('test-request-id', $result->meta->requestId);
    }

    public function testGetSftpCredentialsReturnsApiResponseStatus(): void
    {
        $client = MockClientFactory::create([
            MockClientFactory::success(['user' => $this->userData()]),
        ]);

        $result = $client->cdn()->getCdnStorageUser('s1');

        $this->assertInstanceOf(ApiResponseStatus::class, $result->status);
        $this->assertSame('ok', $result->status->code);
    }

    public function testCreateSftpUserSendsPostWithStorageIdAndReturnsCdnUserResult(): void
    {
        $history = [];
        $client  = MockClientFactory::create([
            MockClientFactory::success(['user' => $this->userData()]),
        ], $history);

        $result = $client->cdn()->createCdnUser(
            (new CreateCdnUserRequest())->setStorageId('s1')
        );

        $request = $history[0]['request'];
        $this->assertSame('POST', $request->getMethod());
        $this->assertStringContainsString('cdn/user', $request->getUri()->getPath());

        $body = json_decode((string) $request->getBody(), true);
        $this->assertSame('s1', $body['storage_id']);

        $this->assertInstanceOf(CdnUserEnvelopeResponse::class, $result);
        $this->assertInstanceOf(CdnUserResponse::class, $result->user);
        $this->assertSame('u1', $result->user->username);
        $this->assertInstanceOf(ResponseMeta::class, $result->meta);
        $this->assertSame('test-request-id', $result->meta->requestId);
    }

    public function testCreateSftpUserReturnsApiResponseStatus(): void
    {
        $client = MockClientFactory::create([
            MockClientFactory::success(['user' => $this->userData()]),
        ]);

        $result = $client->cdn()->createCdnUser(
            (new CreateCdnUserRequest())->setStorageId('s1')
        );

        $this->assertInstanceOf(ApiResponseStatus::class, $result->status);
        $this->assertSame('ok', $result->status->code);
    }

    public function testCdnUserCreateRequestToArrayContainsStorageId(): void
    {
        $req = (new CreateCdnUserRequest())->setStorageId('storage-123');
        $this->assertSame(['storage_id' => 'storage-123'], $req->toArray());
    }

    public function testCdnUserCreateRequestThrowsOnEmptyStorageId(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('storage_id must not be empty.');
        (new CreateCdnUserRequest())->setStorageId('');
    }

    public function testCdnUserCreateRequestThrowsOnWhitespaceStorageId(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new CreateCdnUserRequest())->setStorageId('   ');
    }

    public function testCdnUserCreateRequestToArrayThrowsWhenStorageIdNotSet(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('storage_id is required.');
        (new CreateCdnUserRequest())->toArray();
    }

    public function testRevokeSftpUserSendsPostAndReturnsCdnUserResult(): void
    {
        $history = [];
        $client  = MockClientFactory::create([
            MockClientFactory::success(['user' => $this->userData()]),
        ], $history);

        $result = $client->cdn()->revokeCdnUser('u1');

        $request = $history[0]['request'];
        $this->assertSame('POST', $request->getMethod());
        $this->assertStringContainsString('cdn/user/u1/revoke', $request->getUri()->getPath());

        $this->assertInstanceOf(CdnUserEnvelopeResponse::class, $result);
        $this->assertInstanceOf(CdnUserResponse::class, $result->user);
        $this->assertSame('u1', $result->user->username);
        $this->assertInstanceOf(ResponseMeta::class, $result->meta);
        $this->assertSame('test-request-id', $result->meta->requestId);
    }

    public function testRevokeSftpUserReturnsApiResponseStatus(): void
    {
        $client = MockClientFactory::create([
            MockClientFactory::success(['user' => $this->userData()]),
        ]);

        $result = $client->cdn()->revokeCdnUser('u1');

        $this->assertInstanceOf(ApiResponseStatus::class, $result->status);
        $this->assertSame('ok', $result->status->code);
    }

    public function testRevokeSftpUserSendsNoBody(): void
    {
        $history = [];
        $client  = MockClientFactory::create([
            MockClientFactory::success(['user' => $this->userData()]),
        ], $history);

        $client->cdn()->revokeCdnUser('u1');

        $body = (string) $history[0]['request']->getBody();
        $this->assertSame('', $body);
    }

    public function testListStoragesWithDateFieldsParsesDates(): void
    {
        $client = MockClientFactory::create([
            MockClientFactory::success(['items' => [
                [
                    'id'                  => 's1',
                    'name'                => 'store1',
                    'files_count'         => 5,
                    'bytes_total'         => 2048,
                    'needs_refresh'       => true,
                    'start_refresh_at'    => '2024-01-01T00:00:00Z',
                    'last_refresh_at'     => '2024-01-02T00:00:00Z',
                    'last_file_change_at' => '2024-01-03T00:00:00Z',
                    'deleted_at'          => '2024-01-04T00:00:00Z',
                    'purge_at'            => '2024-01-05T00:00:00Z',
                    'cdn_user'            => [
                        'username'   => 'u1',
                        'password'   => 'secret',
                        'sftp_host'  => 'sftp.example.com',
                        'sftp_port'  => 22,
                        'storage_id' => 's1',
                    ],
                    'synced_dc' => [
                        [
                            'address'      => '1.2.3.4',
                            'name'         => 'DC-EU',
                            'status'       => true,
                            'refreshed_at' => '2024-01-01T12:00:00Z',
                        ],
                    ],
                    'paths' => [
                        [
                            'id'          => 'path-uuid-1',
                            'storage_id'  => 's1',
                            'domain_id'   => 'd1',
                            'path_prefix' => '/files',
                            'full_path'   => 'cdn.example.com/files',
                            'domain_name' => 'cdn.example.com',
                            'subdomain'   => 'cdn',
                        ],
                    ],
                ],
            ]]),
        ]);

        $result = $client->cdn()->listCdnStorages();

        $this->assertCount(1, $result->items);
        $s = $result->items[0];
        $this->assertInstanceOf(\DateTimeImmutable::class, $s->startRefreshAt);
        $this->assertInstanceOf(\DateTimeImmutable::class, $s->lastRefreshAt);
        $this->assertInstanceOf(\DateTimeImmutable::class, $s->lastFileChangeAt);
        $this->assertInstanceOf(\DateTimeImmutable::class, $s->deletedAt);
        $this->assertInstanceOf(\DateTimeImmutable::class, $s->purgeAt);
        $this->assertInstanceOf(CdnUserResponse::class, $s->cdnUser);
        $this->assertCount(1, $s->syncedDCs);
        $this->assertCount(1, $s->paths);
        $this->assertSame('DC-EU', $s->syncedDCs[0]->name);
        $this->assertSame('1.2.3.4', $s->syncedDCs[0]->address);
        $this->assertTrue($s->syncedDCs[0]->status);
        $this->assertInstanceOf(\DateTimeImmutable::class, $s->syncedDCs[0]->refreshedAt);
        $this->assertSame('path-uuid-1', $s->paths[0]->id);
        $this->assertSame('/files', $s->paths[0]->pathPrefix);
    }

    public function testListStoragesWithInvalidDateThrowsTrafficmindException(): void
    {
        $client = MockClientFactory::create([
            MockClientFactory::success(['items' => [
                [
                    'id'               => 's1',
                    'name'             => 'store1',
                    'files_count'      => 0,
                    'bytes_total'      => 0,
                    'needs_refresh'    => false,
                    'start_refresh_at' => 'not-a-valid-date',
                ],
            ]]),
        ]);

        $this->expectException(TrafficmindException::class);
        $client->cdn()->listCdnStorages();
    }

    public function testListStoragesWithInvalidSyncedDcDateThrowsException(): void
    {
        $client = MockClientFactory::create([
            MockClientFactory::success(['items' => [
                [
                    'id'            => 's1',
                    'name'          => 'store1',
                    'files_count'   => 0,
                    'bytes_total'   => 0,
                    'needs_refresh' => false,
                    'synced_dc'     => [
                        [
                            'address'      => '1.2.3.4',
                            'name'         => 'DC-EU',
                            'status'       => true,
                            'refreshed_at' => 'not-a-valid-date',
                        ],
                    ],
                ],
            ]]),
        ]);

        $this->expectException(TrafficmindException::class);
        $client->cdn()->listCdnStorages();
    }

    public function testCdnStorageListRequestDefaultValues(): void
    {
        $req = new CdnStorageListRequest();
        $arr = $req->toArray();

        $this->assertSame(1, $arr['page']);
        $this->assertSame(20, $arr['page_size']);
        $this->assertSame(1, $req->getPage());
        $this->assertSame(20, $req->getPageSize());
    }

    public function testCdnStorageListRequestCustomValues(): void
    {
        $req = (new CdnStorageListRequest())->setPage(2)->setPageSize(50);
        $arr = $req->toArray();

        $this->assertSame(2, $arr['page']);
        $this->assertSame(50, $arr['page_size']);
        $this->assertSame(2, $req->getPage());
        $this->assertSame(50, $req->getPageSize());
    }
}
