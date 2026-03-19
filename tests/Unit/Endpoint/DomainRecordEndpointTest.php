<?php

declare(strict_types=1);

namespace Trafficmind\Api\Tests\Unit\Endpoint;

use PHPUnit\Framework\TestCase;
use Trafficmind\Api\Dto\DomainRecord\DomainDnsRecord;
use Trafficmind\Api\Dto\DomainRecord\DomainRecordDeleteResult;
use Trafficmind\Api\Dto\DomainRecord\DomainRecordListRequest;
use Trafficmind\Api\Dto\DomainRecord\DomainRecordListResponse;
use Trafficmind\Api\Dto\DomainRecord\DomainRecordResult;
use Trafficmind\Api\Dto\DomainRecordBatchRequest\BatchDnsCreate;
use Trafficmind\Api\Dto\DomainRecordBatchRequest\BatchDnsDelete;
use Trafficmind\Api\Dto\DomainRecordBatchRequest\BatchDnsReplace;
use Trafficmind\Api\Dto\DomainRecordBatchRequest\BatchDnsRequest;
use Trafficmind\Api\Dto\DomainRecordBatchRequest\BatchDnsUpdate;
use Trafficmind\Api\Dto\DomainRecordBatchRequest\DomainRecordBatchResponse;
use Trafficmind\Api\Dto\DomainRecordBatchRequest\ResponseDnsRecordsBatchesResult;
use Trafficmind\Api\Dto\Response\ApiResponseStatus;
use Trafficmind\Api\Dto\Response\PaginationMeta;
use Trafficmind\Api\Dto\Response\ResponseMeta;
use Trafficmind\Api\Tests\Unit\MockClientFactory;

class DomainRecordEndpointTest extends TestCase
{
    private function recordData(string $id = 'r1'): array
    {
        return [
            'id'      => $id,
            'type'    => 'A',
            'name'    => 'example.com',
            'content' => '1.2.3.4',
            'proxied' => false,
            'ttl'     => 3600,
        ];
    }

    public function testListSendsCorrectRequestAndReturnsRecords(): void
    {
        $history = [];
        $client  = MockClientFactory::create([
            MockClientFactory::success([
                'items'      => [$this->recordData()],
                'pagination' => ['total' => 1, 'page' => 1, 'page_size' => 20, 'items' => 1],
            ]),
        ], $history);

        $result = $client->domainRecords()->listDomainRecords(
            (new DomainRecordListRequest())->setPage(1)->setPageSize(20)->setQuery('all'),
            'd1'
        );

        $request = $history[0]['request'];
        $this->assertSame('GET', $request->getMethod());
        $this->assertStringContainsString('domains/d1/records', $request->getUri()->getPath());
        $this->assertStringContainsString('query=all', $request->getUri()->getQuery());
        $this->assertStringContainsString('page=1', $request->getUri()->getQuery());
        $this->assertStringContainsString('page_size=20', $request->getUri()->getQuery());

        $this->assertInstanceOf(DomainRecordListResponse::class, $result);
        $this->assertCount(1, $result->items);
        $this->assertInstanceOf(DomainDnsRecord::class, $result->items[0]);
        $this->assertSame('r1', $result->items[0]->id);
        $this->assertSame('A', $result->items[0]->type);
    }

    public function testListReturnsPaginationMeta(): void
    {
        $client = MockClientFactory::create([
            MockClientFactory::success([
                'items'      => [$this->recordData('r1'), $this->recordData('r2')],
                'pagination' => ['total' => 55, 'page' => 3, 'page_size' => 10, 'items' => 2],
            ]),
        ]);

        $result = $client->domainRecords()->listDomainRecords(
            (new DomainRecordListRequest())->setPage(3)->setPageSize(10),
            'd1'
        );

        $this->assertInstanceOf(PaginationMeta::class, $result->pagination);
        $this->assertSame(55, $result->pagination->total);
        $this->assertSame(3, $result->pagination->page);
        $this->assertSame(10, $result->pagination->pageSize);
        $this->assertSame(2, $result->pagination->items);
    }

    public function testListReturnsPaginationMetaWithZerosWhenMissing(): void
    {
        $client = MockClientFactory::create([
            MockClientFactory::success(['items' => []]),
        ]);

        $result = $client->domainRecords()->listDomainRecords(new DomainRecordListRequest(), 'd1');

        $this->assertInstanceOf(PaginationMeta::class, $result->pagination);
        $this->assertSame(0, $result->pagination->total);
    }

    public function testListReturnsResponseMeta(): void
    {
        $client = MockClientFactory::create([
            MockClientFactory::success(['items' => [], 'pagination' => []]),
        ]);

        $result = $client->domainRecords()->listDomainRecords(new DomainRecordListRequest(), 'd1');

        $this->assertInstanceOf(ResponseMeta::class, $result->meta);
        $this->assertSame('test-request-id', $result->meta->requestId);
    }

    public function testListReturnsApiResponseStatus(): void
    {
        $client = MockClientFactory::create([
            MockClientFactory::success(['items' => [], 'pagination' => []]),
        ]);

        $result = $client->domainRecords()->listDomainRecords(new DomainRecordListRequest(), 'd1');

        $this->assertInstanceOf(ApiResponseStatus::class, $result->status);
        $this->assertSame('ok', $result->status->code);
        $this->assertSame('Request processed successfully', $result->status->message);
    }

    public function testListReturnsSearchQueryWhenPresent(): void
    {
        $client = MockClientFactory::create([
            MockClientFactory::success([
                'items'        => [],
                'pagination'   => [],
                'search_query' => 'example.com',
            ]),
        ]);

        $result = $client->domainRecords()->listDomainRecords(new DomainRecordListRequest(), 'd1');

        $this->assertSame('example.com', $result->searchQuery);
    }

    public function testListReturnsNullSearchQueryWhenAbsent(): void
    {
        $client = MockClientFactory::create([
            MockClientFactory::success(['items' => [], 'pagination' => []]),
        ]);

        $result = $client->domainRecords()->listDomainRecords(new DomainRecordListRequest(), 'd1');

        $this->assertNull($result->searchQuery);
    }

    public function testPaginateYieldsAllRecordsAcrossPages(): void
    {
        $client = MockClientFactory::create([
            MockClientFactory::success(['items' => [$this->recordData('r1'), $this->recordData('r2')], 'pagination' => ['total' => 5, 'page' => 1, 'page_size' => 2, 'items' => 2]]),
            MockClientFactory::success(['items' => [$this->recordData('r3'), $this->recordData('r4')], 'pagination' => ['total' => 5, 'page' => 2, 'page_size' => 2, 'items' => 2]]),
            MockClientFactory::success(['items' => [$this->recordData('r5')], 'pagination' => ['total' => 5, 'page' => 3, 'page_size' => 2, 'items' => 1]]),
        ]);

        $request = (new DomainRecordListRequest())->setPageSize(2);
        $records = iterator_to_array($client->domainRecords()->paginate($request, 'd1'), false);

        $this->assertCount(5, $records);
        $this->assertSame('r1', $records[0]->id);
        $this->assertSame('r3', $records[2]->id);
        $this->assertSame('r5', $records[4]->id);
    }

    public function testPaginateStopsWhenEmptyPageReturned(): void
    {
        $client = MockClientFactory::create([
            MockClientFactory::success(['items' => [], 'pagination' => ['total' => 0, 'page' => 1, 'page_size' => 20, 'items' => 0]]),
        ]);

        $request = (new DomainRecordListRequest())->setPageSize(20);
        $records = iterator_to_array($client->domainRecords()->paginate($request, 'd1'), false);

        $this->assertCount(0, $records);
    }

    public function testPaginateStopsWhenLastPageIsPartial(): void
    {
        $client = MockClientFactory::create([
            MockClientFactory::success(['items' => [$this->recordData('r1'), $this->recordData('r2')], 'pagination' => ['total' => 3, 'page' => 1, 'page_size' => 2, 'items' => 2]]),
            MockClientFactory::success(['items' => [$this->recordData('r3')], 'pagination' => ['total' => 3, 'page' => 2, 'page_size' => 2, 'items' => 1]]),
        ]);

        $request = (new DomainRecordListRequest())->setPageSize(2);
        $records = iterator_to_array($client->domainRecords()->paginate($request, 'd1'), false);

        $this->assertCount(3, $records);
        $this->assertSame('r3', $records[2]->id);
    }

    public function testCreateSendsPostAndReturnsDomainRecordResult(): void
    {
        $history = [];
        $client  = MockClientFactory::create([
            MockClientFactory::success(['batch' => [
                'creates'  => [$this->recordData('r1')],
                'updates'  => [],
                'replaces' => [],
                'deletes'  => [],
            ]]),
        ], $history);

        $record = new DomainDnsRecord(
            id:      '',
            type:    'A',
            name:    'example.com',
            content: '1.2.3.4',
            proxied: false,
            ttl:     3600,
        );

        $result = $client->domainRecords()->create($record, 'd1');

        $request = $history[0]['request'];
        $this->assertSame('POST', $request->getMethod());
        $this->assertStringContainsString('records/batch', $request->getUri()->getPath());

        $this->assertInstanceOf(DomainRecordResult::class, $result);
        $this->assertInstanceOf(DomainDnsRecord::class, $result->record);
        $this->assertSame('r1', $result->record->id);
        $this->assertInstanceOf(ResponseMeta::class, $result->meta);
        $this->assertSame('test-request-id', $result->meta->requestId);
    }

    public function testCreateReturnsApiResponseStatus(): void
    {
        $client = MockClientFactory::create([
            MockClientFactory::success(['batch' => [
                'creates'  => [$this->recordData('r1')],
                'updates'  => [],
                'replaces' => [],
                'deletes'  => [],
            ]]),
        ]);

        $record = new DomainDnsRecord(id: '', type: 'A', name: 'example.com', content: '1.2.3.4', proxied: false);
        $result = $client->domainRecords()->create($record, 'd1');

        $this->assertInstanceOf(ApiResponseStatus::class, $result->status);
        $this->assertSame('ok', $result->status->code);
    }

    public function testCreateRecordWithCommentIncludesCommentInBody(): void
    {
        $history = [];
        $client  = MockClientFactory::create([
            MockClientFactory::success(['batch' => [
                'creates'  => [$this->recordData('r1')],
                'updates'  => [],
                'replaces' => [],
                'deletes'  => [],
            ]]),
        ], $history);

        $record = new DomainDnsRecord(
            id:      '',
            type:    'A',
            name:    'example.com',
            content: '1.2.3.4',
            proxied: false,
            ttl:     3600,
            comment: 'my comment',
        );

        $client->domainRecords()->create($record, 'd1');

        $body = json_decode((string) $history[0]['request']->getBody(), true);
        $this->assertSame('my comment', $body['creates'][0]['comment']);
    }

    public function testCreateThrowsWhenApiReturnsEmptyCreates(): void
    {
        $client = MockClientFactory::create([
            MockClientFactory::success(['batch' => [
                'creates'  => [],
                'updates'  => [],
                'replaces' => [],
                'deletes'  => [],
            ]]),
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/returned no record in "creates"/');

        $record = new DomainDnsRecord(id: '', type: 'A', name: 'example.com', content: '1.2.3.4', proxied: false);
        $client->domainRecords()->create($record, 'd1');
    }

    public function testUpdateSendsPatchAndReturnsDomainRecordResult(): void
    {
        $history = [];
        $client  = MockClientFactory::create([
            MockClientFactory::success(['batch' => [
                'creates'  => [],
                'updates'  => [$this->recordData('r1')],
                'replaces' => [],
                'deletes'  => [],
            ]]),
        ], $history);

        $record = new DomainDnsRecord(
            id:      'r1',
            type:    'A',
            name:    'example.com',
            content: '5.6.7.8',
            proxied: false,
            ttl:     300,
        );

        $result = $client->domainRecords()->update('r1', $record, 'd1');

        $body = json_decode((string) $history[0]['request']->getBody(), true);
        $this->assertSame('r1', $body['updates'][0]['id']);

        $this->assertInstanceOf(DomainRecordResult::class, $result);
        $this->assertInstanceOf(DomainDnsRecord::class, $result->record);
        $this->assertSame('r1', $result->record->id);
        $this->assertInstanceOf(ResponseMeta::class, $result->meta);
        $this->assertSame('test-request-id', $result->meta->requestId);
    }

    public function testUpdateReturnsApiResponseStatus(): void
    {
        $client = MockClientFactory::create([
            MockClientFactory::success(['batch' => [
                'creates'  => [],
                'updates'  => [$this->recordData('r1')],
                'replaces' => [],
                'deletes'  => [],
            ]]),
        ]);

        $record = new DomainDnsRecord(id: 'r1', type: 'A', name: 'example.com', content: '5.6.7.8', proxied: false);
        $result = $client->domainRecords()->update('r1', $record, 'd1');

        $this->assertInstanceOf(ApiResponseStatus::class, $result->status);
        $this->assertSame('ok', $result->status->code);
    }

    public function testUpdateThrowsWhenIdIsEmpty(): void
    {
        $client = MockClientFactory::create([]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/id must not be empty/');

        $record = new DomainDnsRecord(id: '', type: 'A', name: 'example.com', content: '1.2.3.4', proxied: false);
        $client->domainRecords()->update('', $record, 'd1');
    }

    public function testUpdateThrowsWhenApiReturnsEmptyUpdates(): void
    {
        $client = MockClientFactory::create([
            MockClientFactory::success(['batch' => [
                'creates'  => [],
                'updates'  => [],
                'replaces' => [],
                'deletes'  => [],
            ]]),
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/returned no record in "updates"/');

        $record = new DomainDnsRecord(id: 'r1', type: 'A', name: 'example.com', content: '5.6.7.8', proxied: false);
        $client->domainRecords()->update('r1', $record, 'd1');
    }

    public function testReplaceSendsPostWithReplacesArrayAndReturnsResult(): void
    {
        $history = [];
        $client  = MockClientFactory::create([
            MockClientFactory::success(['batch' => [
                'creates'  => [],
                'updates'  => [],
                'replaces' => [$this->recordData('r1')],
                'deletes'  => [],
            ]]),
        ], $history);

        $record = new DomainDnsRecord(
            id:      '',
            type:    'A',
            name:    'new.example.com',
            content: '10.0.0.1',
            proxied: false,
        );

        $result = $client->domainRecords()->replace('r1', $record, 'dom-1');

        $request = $history[0]['request'];
        $this->assertSame('POST', $request->getMethod());
        $this->assertStringContainsString('domains/dom-1/records/batch', $request->getUri()->getPath());

        $body = json_decode((string) $request->getBody(), true);
        $this->assertArrayHasKey('replaces', $body);
        $this->assertArrayNotHasKey('updates', $body);
        $this->assertArrayNotHasKey('creates', $body);
        $this->assertSame('r1', $body['replaces'][0]['id']);
        $this->assertSame('A', $body['replaces'][0]['type']);

        $this->assertInstanceOf(DomainRecordResult::class, $result);
        $this->assertSame('r1', $result->record->id);
    }

    public function testReplaceReturnsApiResponseStatus(): void
    {
        $client = MockClientFactory::create([
            MockClientFactory::success(['batch' => [
                'creates'  => [],
                'updates'  => [],
                'replaces' => [$this->recordData('r1')],
                'deletes'  => [],
            ]]),
        ]);

        $record = new DomainDnsRecord(id: '', type: 'A', name: 'x', content: '1.2.3.4', proxied: false);
        $result = $client->domainRecords()->replace('r1', $record, 'd1');

        $this->assertInstanceOf(ApiResponseStatus::class, $result->status);
        $this->assertSame('ok', $result->status->code);
    }

    public function testReplaceThrowsOnEmptyId(): void
    {
        $client = MockClientFactory::create([]);

        $record = new DomainDnsRecord(id: '', type: 'A', name: 'x', content: '1.2.3.4', proxied: false);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Domain record id must not be empty.');
        $client->domainRecords()->replace('', $record, 'dom-1');
    }

    public function testReplaceThrowsWhenApiReturnsEmptyReplaces(): void
    {
        $client = MockClientFactory::create([
            MockClientFactory::success(['batch' => ['replaces' => []]]),
        ]);

        $record = new DomainDnsRecord(id: '', type: 'A', name: 'x', content: '1.2.3.4', proxied: false);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/"replaces"/');
        $client->domainRecords()->replace('rec-1', $record, 'dom-1');
    }

    public function testDeleteSendsBatchWithDeleteIdAndReturnsDeleteResult(): void
    {
        $history = [];
        $client  = MockClientFactory::create([
            MockClientFactory::success(['batch' => [
                'creates'  => [],
                'updates'  => [],
                'replaces' => [],
                'deletes'  => [['id' => 'r1']],
            ]]),
        ], $history);

        $result = $client->domainRecords()->delete('r1', 'd1');

        $body = json_decode((string) $history[0]['request']->getBody(), true);
        $this->assertSame('POST', $history[0]['request']->getMethod());
        $this->assertSame('r1', $body['deletes'][0]['id']);

        $this->assertInstanceOf(DomainRecordDeleteResult::class, $result);
        $this->assertInstanceOf(ResponseMeta::class, $result->meta);
        $this->assertSame('test-request-id', $result->meta->requestId);
    }

    public function testDeleteReturnsApiResponseStatus(): void
    {
        $client = MockClientFactory::create([
            MockClientFactory::success(['batch' => [
                'creates'  => [],
                'updates'  => [],
                'replaces' => [],
                'deletes'  => [['id' => 'r1']],
            ]]),
        ]);

        $result = $client->domainRecords()->delete('r1', 'd1');

        $this->assertInstanceOf(ApiResponseStatus::class, $result->status);
        $this->assertSame('ok', $result->status->code);
    }

    public function testBatchCreateSendsPostWithCorrectBody(): void
    {
        $history = [];
        $client  = MockClientFactory::create([
            MockClientFactory::success(['batch' => [
                'creates'  => [$this->recordData()],
                'updates'  => [],
                'replaces' => [],
                'deletes'  => [],
            ]]),
        ], $history);

        $record = new DomainDnsRecord(
            id:      'temp',
            type:    'A',
            name:    'example.com',
            content: '1.2.3.4',
            proxied: false,
            ttl:     3600,
        );

        $batch = (new BatchDnsRequest())
            ->addCreate((new BatchDnsCreate())->setDomainRecord($record));

        $result = $client->domainRecords()->batchDomainRecords($batch, 'd1');

        $request = $history[0]['request'];
        $this->assertSame('POST', $request->getMethod());
        $this->assertStringContainsString('domains/d1/records/batch', $request->getUri()->getPath());

        $body = json_decode((string) $request->getBody(), true);
        $this->assertCount(1, $body['creates']);
        $this->assertSame('A', $body['creates'][0]['type']);
        $this->assertSame('1.2.3.4', $body['creates'][0]['content']);
        $this->assertArrayHasKey('proxied', $body['creates'][0]);
        $this->assertFalse($body['creates'][0]['proxied']);

        $this->assertInstanceOf(DomainRecordBatchResponse::class, $result);
        $this->assertInstanceOf(ResponseDnsRecordsBatchesResult::class, $result->batch);
        $this->assertCount(1, $result->batch->getCreates());
        $this->assertInstanceOf(ResponseMeta::class, $result->meta);
        $this->assertSame('test-request-id', $result->meta->requestId);
    }

    public function testBatchActionReturnsApiResponseStatus(): void
    {
        $client = MockClientFactory::create([
            MockClientFactory::success(['batch' => [
                'creates'  => [$this->recordData()],
                'updates'  => [],
                'replaces' => [],
                'deletes'  => [],
            ]]),
        ]);

        $record = new DomainDnsRecord(id: '', type: 'A', name: 'example.com', content: '1.2.3.4', proxied: false);
        $batch  = (new BatchDnsRequest())
            ->addCreate((new BatchDnsCreate())->setDomainRecord($record));

        $result = $client->domainRecords()->batchDomainRecords($batch, 'd1');

        $this->assertInstanceOf(ApiResponseStatus::class, $result->status);
        $this->assertSame('ok', $result->status->code);
    }

    public function testBatchUpdateSendsCorrectBody(): void
    {
        $history = [];
        $client  = MockClientFactory::create([
            MockClientFactory::success(['batch' => [
                'creates'  => [],
                'updates'  => [$this->recordData()],
                'replaces' => [],
                'deletes'  => [],
            ]]),
        ], $history);

        $record = new DomainDnsRecord(
            id:      'r1',
            type:    'A',
            name:    'example.com',
            content: '1.2.3.4',
            proxied: false,
            ttl:     300,
        );

        $batch = (new BatchDnsRequest())
            ->addUpdate((new BatchDnsUpdate())->setId('r1')->setDomainRecord($record));

        $client->domainRecords()->batchDomainRecords($batch, 'd1');

        $body = json_decode((string) $history[0]['request']->getBody(), true);
        $this->assertCount(1, $body['updates']);
        $this->assertSame('r1', $body['updates'][0]['id']);
        $this->assertSame(300, $body['updates'][0]['ttl']);
    }

    public function testBatchDeleteSendsCorrectBody(): void
    {
        $history = [];
        $client  = MockClientFactory::create([
            MockClientFactory::success(['batch' => [
                'creates'  => [],
                'updates'  => [],
                'replaces' => [],
                'deletes'  => [['id' => 'r1']],
            ]]),
        ], $history);

        $batch = (new BatchDnsRequest())
            ->addDelete((new BatchDnsDelete())->setId('r1'));

        $client->domainRecords()->batchDomainRecords($batch, 'd1');

        $body = json_decode((string) $history[0]['request']->getBody(), true);
        $this->assertCount(1, $body['deletes']);
        $this->assertSame('r1', $body['deletes'][0]['id']);
    }

    public function testBatchReplaceSendsCorrectBodyAndReturnsReplaces(): void
    {
        $history = [];
        $client  = MockClientFactory::create([
            MockClientFactory::success(['batch' => [
                'creates'  => [],
                'updates'  => [],
                'replaces' => [$this->recordData('r1')],
                'deletes'  => [],
            ]]),
        ], $history);

        $record = new DomainDnsRecord(
            id:      'r1',
            type:    'A',
            name:    'example.com',
            content: '1.2.3.4',
            proxied: false,
            ttl:     3600,
        );

        $batch = (new BatchDnsRequest())
            ->addReplace((new BatchDnsReplace())->setId('r1')->setDomainRecord($record));

        $result = $client->domainRecords()->batchDomainRecords($batch, 'd1');

        $body = json_decode((string) $history[0]['request']->getBody(), true);
        $this->assertCount(1, $body['replaces']);
        $this->assertSame('r1', $body['replaces'][0]['id']);

        $this->assertInstanceOf(DomainRecordBatchResponse::class, $result);
        $this->assertCount(1, $result->batch->getReplaces());
        $this->assertInstanceOf(DomainDnsRecord::class, $result->batch->getReplaces()[0]);
        $this->assertSame('r1', $result->batch->getReplaces()[0]->id);
    }

    public function testDomainRecordToArrayOmitsProxiedWhenNull(): void
    {
        $record = new DomainDnsRecord(
            id:      '',
            type:    'A',
            name:    'example.com',
            content: '1.2.3.4',
        );

        $this->assertArrayNotHasKey('proxied', $record->toArray());
    }

    public function testDomainRecordToArrayIncludesProxiedTrueWhenSet(): void
    {
        $record = new DomainDnsRecord(
            id:      '',
            type:    'A',
            name:    'example.com',
            content: '1.2.3.4',
            proxied: true,
        );

        $data = $record->toArray();
        $this->assertArrayHasKey('proxied', $data);
        $this->assertTrue($data['proxied']);
    }

    public function testDomainRecordToArrayIncludesProxiedFalseWhenExplicitlySet(): void
    {
        $record = new DomainDnsRecord(
            id:      '',
            type:    'A',
            name:    'example.com',
            content: '1.2.3.4',
            proxied: false,
        );

        $data = $record->toArray();
        $this->assertArrayHasKey('proxied', $data);
        $this->assertFalse($data['proxied']);
    }

    public function testDomainRecordFromArrayParsesProxiedTrue(): void
    {
        $record = DomainDnsRecord::fromArray([
            'id'      => 'r1',
            'type'    => 'A',
            'name'    => 'example.com',
            'content' => '1.2.3.4',
            'proxied' => true,
        ]);

        $this->assertTrue($record->proxied);
    }

    public function testDomainRecordFromArrayReturnsNullProxiedWhenAbsent(): void
    {
        $record = DomainDnsRecord::fromArray([
            'id'      => 'r1',
            'type'    => 'A',
            'name'    => 'example.com',
            'content' => '1.2.3.4',
        ]);

        $this->assertNull($record->proxied);
    }

    public function testBatchGettersReturnCorrectCollections(): void
    {
        $record = new DomainDnsRecord(id: 'r1', type: 'A', name: 'example.com', content: '1.2.3.4', proxied: false);

        $createReq  = (new BatchDnsCreate())->setDomainRecord($record);
        $updateReq  = (new BatchDnsUpdate())->setId('r1')->setDomainRecord($record);
        $replaceReq = (new BatchDnsReplace())->setId('r1')->setDomainRecord($record);
        $deleteReq  = (new BatchDnsDelete())->setId('r1');

        $batch = (new BatchDnsRequest())
            ->addCreate($createReq)
            ->addUpdate($updateReq)
            ->addReplace($replaceReq)
            ->addDelete($deleteReq);

        $this->assertCount(1, $batch->getCreates());
        $this->assertCount(1, $batch->getUpdates());
        $this->assertCount(1, $batch->getReplaces());
        $this->assertCount(1, $batch->getDeletes());
        $this->assertSame($createReq, $batch->getCreates()[0]);
        $this->assertSame($updateReq, $batch->getUpdates()[0]);
        $this->assertSame($replaceReq, $batch->getReplaces()[0]);
        $this->assertSame($deleteReq, $batch->getDeletes()[0]);
    }

    public function testBatchToArrayOmitsEmptyKeys(): void
    {
        $record = new DomainDnsRecord(id: 'r1', type: 'A', name: 'example.com', content: '1.2.3.4', proxied: false);
        $batch  = (new BatchDnsRequest())
            ->addCreate((new BatchDnsCreate())->setDomainRecord($record));

        $data = $batch->toArray();

        $this->assertArrayHasKey('creates', $data);
        $this->assertArrayNotHasKey('updates', $data);
        $this->assertArrayNotHasKey('replaces', $data);
        $this->assertArrayNotHasKey('deletes', $data);
    }

    public function testBatchToArrayReturnsEmptyArrayWhenNothingAdded(): void
    {
        $data = (new BatchDnsRequest())->toArray();
        $this->assertSame([], $data);
    }

    public function testBatchToArrayIncludesOnlyPopulatedKeys(): void
    {
        $record = new DomainDnsRecord(id: 'r1', type: 'A', name: 'example.com', content: '1.2.3.4', proxied: false);
        $batch  = (new BatchDnsRequest())
            ->addUpdate((new BatchDnsUpdate())->setId('r1')->setDomainRecord($record))
            ->addDelete((new BatchDnsDelete())->setId('r1'));

        $data = $batch->toArray();

        $this->assertArrayNotHasKey('creates', $data);
        $this->assertArrayHasKey('updates', $data);
        $this->assertArrayNotHasKey('replaces', $data);
        $this->assertArrayHasKey('deletes', $data);
    }

    public function testUpdateRequestSetIdThrowsOnEmptyString(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('id must not be empty.');
        (new BatchDnsUpdate())->setId('');
    }

    public function testUpdateRequestSetIdThrowsOnBlankString(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('id must not be empty.');
        (new BatchDnsUpdate())->setId('   ');
    }

    public function testUpdateRequestToArrayThrowsWhenIdNotSet(): void
    {
        $record  = new DomainDnsRecord(id: 'r1', type: 'A', name: 'example.com', content: '1.2.3.4', proxied: false);
        $request = (new BatchDnsUpdate())->setDomainRecord($record);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('id must not be empty.');
        $request->toArray();
    }

    public function testDeleteRequestFromArrayHydratesFullDomainRecord(): void
    {
        $data = [
            'id'      => 'r1',
            'type'    => 'A',
            'name'    => 'example.com',
            'content' => '1.2.3.4',
            'proxied' => true,
            'ttl'     => 300,
        ];

        $request = BatchDnsDelete::fromArray($data);

        $this->assertSame('r1', $request->getId());
        $this->assertInstanceOf(DomainDnsRecord::class, $request->getDomainRecord());
        $this->assertSame('r1', $request->getDomainRecord()->id);
        $this->assertSame('A', $request->getDomainRecord()->type);
        $this->assertSame('example.com', $request->getDomainRecord()->name);
        $this->assertSame('1.2.3.4', $request->getDomainRecord()->content);
        $this->assertTrue($request->getDomainRecord()->proxied);
        $this->assertSame(300, $request->getDomainRecord()->ttl);
    }

    public function testDeleteRequestBuiltLocallyHasNullDomainRecord(): void
    {
        $request = (new BatchDnsDelete())->setId('r1');
        $this->assertNull($request->getDomainRecord());
    }

    public function testDomainRecordListRequestGetPageSizeReturnsDefault(): void
    {
        $this->assertSame(20, (new DomainRecordListRequest())->getPageSize());
    }

    public function testDomainRecordListRequestGetPageSizeReturnsSetValue(): void
    {
        $this->assertSame(5, (new DomainRecordListRequest())->setPageSize(5)->getPageSize());
    }

    public function testBatchDnsCreateFromArrayAndGetDomainRecord(): void
    {
        $create = BatchDnsCreate::fromArray([
            'id'      => 'r1',
            'type'    => 'A',
            'name'    => 'example.com',
            'content' => '1.2.3.4',
        ]);

        $this->assertInstanceOf(DomainDnsRecord::class, $create->getDomainRecord());
        $this->assertSame('r1', $create->getDomainRecord()->id);
    }

    public function testBatchDnsReplaceFromArrayAndGetters(): void
    {
        $replace = BatchDnsReplace::fromArray([
            'id'      => 'r1',
            'type'    => 'A',
            'name'    => 'example.com',
            'content' => '1.2.3.4',
        ]);

        $this->assertSame('r1', $replace->getId());
        $this->assertInstanceOf(DomainDnsRecord::class, $replace->getDomainRecord());
        $this->assertSame('r1', $replace->getDomainRecord()->id);
    }

    public function testBatchDnsReplaceSetIdThrowsOnEmptyString(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('id must not be empty.');
        (new BatchDnsReplace())->setId('');
    }

    public function testBatchDnsReplaceToArrayThrowsWhenIdEmpty(): void
    {
        $record  = new DomainDnsRecord(id: '', type: 'A', name: 'x', content: '1.2.3.4', proxied: false);
        $replace = (new BatchDnsReplace())->setDomainRecord($record);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('id must not be empty.');
        $replace->toArray();
    }

    public function testBatchDnsUpdateFromArrayAndGetters(): void
    {
        $update = BatchDnsUpdate::fromArray([
            'id'      => 'u1',
            'type'    => 'A',
            'name'    => 'example.com',
            'content' => '5.6.7.8',
        ]);

        $this->assertSame('u1', $update->getId());
        $this->assertInstanceOf(DomainDnsRecord::class, $update->getDomainRecord());
        $this->assertSame('u1', $update->getDomainRecord()->id);
    }

    public function testBatchDnsRequestFromArray(): void
    {
        $data = [
            'creates'  => [['id' => 'c1', 'type' => 'A', 'name' => 'a.com', 'content' => '1.1.1.1']],
            'updates'  => [['id' => 'u1', 'type' => 'A', 'name' => 'b.com', 'content' => '2.2.2.2']],
            'replaces' => [['id' => 'r1', 'type' => 'A', 'name' => 'c.com', 'content' => '3.3.3.3']],
            'deletes'  => [['id' => 'd1', 'type' => 'A', 'name' => 'd.com', 'content' => '4.4.4.4']],
        ];

        $batch = BatchDnsRequest::fromArray($data);

        $this->assertCount(1, $batch->getCreates());
        $this->assertCount(1, $batch->getUpdates());
        $this->assertCount(1, $batch->getReplaces());
        $this->assertCount(1, $batch->getDeletes());
    }

    public function testResponseDnsRecordsBatchesResultGetDeletes(): void
    {
        $client = MockClientFactory::create([
            MockClientFactory::success(['batch' => [
                'creates'  => [],
                'updates'  => [],
                'replaces' => [],
                'deletes'  => [$this->recordData('r1')],
            ]]),
        ]);

        $result = $client->domainRecords()->batchDomainRecords(
            (new BatchDnsRequest())->addDelete((new BatchDnsDelete())->setId('r1')),
            'd1'
        );

        $this->assertCount(1, $result->batch->getDeletes());
        $this->assertInstanceOf(DomainDnsRecord::class, $result->batch->getDeletes()[0]);
        $this->assertSame('r1', $result->batch->getDeletes()[0]->id);
    }
}
