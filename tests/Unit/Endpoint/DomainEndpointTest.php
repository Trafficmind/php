<?php

declare(strict_types=1);

namespace Trafficmind\Api\Tests\Unit\Endpoint;

use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use Trafficmind\Api\Dto\Domain\CreateDomainRequest;
use Trafficmind\Api\Dto\Domain\DomainListRequest;
use Trafficmind\Api\Dto\Domain\DomainListResponse;
use Trafficmind\Api\Dto\Domain\DomainRemovalResponse;
use Trafficmind\Api\Dto\Domain\DomainResponse;
use Trafficmind\Api\Dto\Domain\ResponseDomainRecord;
use Trafficmind\Api\Dto\Response\ApiResponseStatus;
use Trafficmind\Api\Dto\Response\PaginationMeta;
use Trafficmind\Api\Dto\Response\ResponseMeta;
use Trafficmind\Api\Exception\NotFoundException;
use Trafficmind\Api\Tests\Unit\MockClientFactory;

class DomainEndpointTest extends TestCase
{
    private function domainData(string $id = 'd1', string $name = 'example.com'): array
    {
        return [
            'id'      => $id,
            'name'    => $name,
            'account' => ['id' => 'a1', 'name' => 'Account'],
        ];
    }

    private function paginationData(int $total = 10, int $page = 1, int $pageSize = 20, int $items = 2): array
    {
        return [
            'total'     => $total,
            'page'      => $page,
            'page_size' => $pageSize,
            'items'     => $items,
        ];
    }

    public function testListSendsCorrectRequestAndReturnsDomains(): void
    {
        $history = [];
        $client  = MockClientFactory::create([
            MockClientFactory::success([
                'items'      => [$this->domainData('d1'), $this->domainData('d2', 'test.com')],
                'pagination' => $this->paginationData(total: 2, items: 2),
            ]),
        ], $history);

        $result = $client->domains()->listDomains(
            (new DomainListRequest())->setPage(1)->setPageSize(20)->setQuery('example.com')
        );

        /** @var RequestInterface $request */
        $request = $history[0]['request'];
        $this->assertSame('GET', $request->getMethod());
        $this->assertStringContainsString('domains', $request->getUri()->getPath());
        $this->assertStringContainsString('page=1', $request->getUri()->getQuery());
        $this->assertStringContainsString('page_size=20', $request->getUri()->getQuery());
        $this->assertStringContainsString('query=example.com', $request->getUri()->getQuery());
        $this->assertNotEmpty($request->getHeaderLine('X-Access-User'));
        $this->assertNotEmpty($request->getHeaderLine('X-Access-Key'));

        $this->assertInstanceOf(DomainListResponse::class, $result);
        $this->assertCount(2, $result->items);
        $this->assertInstanceOf(ResponseDomainRecord::class, $result->items[0]);
        $this->assertSame('d1', $result->items[0]->id);
        $this->assertSame('example.com', $result->items[0]->name);
    }

    public function testListReturnsPaginationMeta(): void
    {
        $client = MockClientFactory::create([
            MockClientFactory::success([
                'items'      => [$this->domainData('d1')],
                'pagination' => $this->paginationData(total: 42, page: 2, pageSize: 10, items: 1),
            ]),
        ]);

        $result = $client->domains()->listDomains((new DomainListRequest())->setPage(2)->setPageSize(10));

        $this->assertInstanceOf(PaginationMeta::class, $result->pagination);
        $this->assertSame(42, $result->pagination->total);
        $this->assertSame(2, $result->pagination->page);
        $this->assertSame(10, $result->pagination->pageSize);
        $this->assertSame(1, $result->pagination->items);
    }

    public function testListReturnsPaginationMetaWithZerosWhenMissing(): void
    {
        $client = MockClientFactory::create([
            MockClientFactory::success(['items' => []]),
        ]);

        $result = $client->domains()->listDomains(new DomainListRequest());

        $this->assertInstanceOf(PaginationMeta::class, $result->pagination);
        $this->assertSame(0, $result->pagination->total);
        $this->assertSame(0, $result->pagination->page);
    }

    public function testListReturnsResponseMeta(): void
    {
        $client = MockClientFactory::create([
            MockClientFactory::success(['items' => [], 'pagination' => $this->paginationData()]),
        ]);

        $result = $client->domains()->listDomains(new DomainListRequest());

        $this->assertInstanceOf(ResponseMeta::class, $result->meta);
        $this->assertSame('test-request-id', $result->meta->requestId);
        $this->assertNotNull($result->meta->timestamp);
    }

    public function testListReturnsApiResponseStatus(): void
    {
        $client = MockClientFactory::create([
            MockClientFactory::success(['items' => [], 'pagination' => $this->paginationData()]),
        ]);

        $result = $client->domains()->listDomains(new DomainListRequest());

        $this->assertInstanceOf(ApiResponseStatus::class, $result->status);
        $this->assertSame('ok', $result->status->code);
        $this->assertSame('Request processed successfully', $result->status->message);
    }

    public function testListSendsMatchModeWhenSet(): void
    {
        $history = [];
        $client  = MockClientFactory::create([
            MockClientFactory::success(['items' => [$this->domainData('d1')]]),
        ], $history);

        $client->domains()->listDomains(
            (new DomainListRequest())->setQuery('example.com')->setMatchMode('contains')
        );

        $query = $history[0]['request']->getUri()->getQuery();
        $this->assertStringContainsString('match_mode=contains', $query);
    }

    public function testListOmitsMatchModeWhenNotSet(): void
    {
        $history = [];
        $client  = MockClientFactory::create([
            MockClientFactory::success(['items' => [$this->domainData('d1')]]),
        ], $history);

        $client->domains()->listDomains(new DomainListRequest());

        $query = $history[0]['request']->getUri()->getQuery();
        $this->assertStringNotContainsString('match_mode', $query);
    }

    public function testGetSendsCorrectRequestAndReturnsDomainResult(): void
    {
        $history = [];
        $client  = MockClientFactory::create([
            MockClientFactory::success(['domain' => $this->domainData()]),
        ], $history);

        $result = $client->domains()->getDomain('d1');

        $request = $history[0]['request'];
        $this->assertSame('GET', $request->getMethod());
        $this->assertStringContainsString('domains/d1', $request->getUri()->getPath());

        $this->assertInstanceOf(DomainResponse::class, $result);
        $this->assertInstanceOf(ResponseDomainRecord::class, $result->domain);
        $this->assertSame('d1', $result->domain->id);
        $this->assertSame('example.com', $result->domain->name);
        $this->assertInstanceOf(ResponseMeta::class, $result->meta);
        $this->assertSame('test-request-id', $result->meta->requestId);
    }

    public function testGetReturnsApiResponseStatus(): void
    {
        $client = MockClientFactory::create([
            MockClientFactory::success(['domain' => $this->domainData()]),
        ]);

        $result = $client->domains()->getDomain('d1');

        $this->assertInstanceOf(ApiResponseStatus::class, $result->status);
        $this->assertSame('ok', $result->status->code);
    }

    public function testGetThrowsNotFoundForMissingDomain(): void
    {
        $client = MockClientFactory::create([
            MockClientFactory::error('Domain not found', 404),
        ]);

        $this->expectException(NotFoundException::class);
        $client->domains()->getDomain('nonexistent');
    }

    public function testCreateSendsPostWithBodyAndReturnsDomainResult(): void
    {
        $history = [];
        $client  = MockClientFactory::create([
            MockClientFactory::success(['domain' => $this->domainData()]),
        ], $history);

        $result = $client->domains()->createDomain(
            (new CreateDomainRequest())->setName('example.com')
        );

        $request = $history[0]['request'];
        $this->assertSame('POST', $request->getMethod());
        $this->assertStringContainsString('domains', $request->getUri()->getPath());

        $body = json_decode((string) $request->getBody(), true);
        $this->assertSame('example.com', $body['name']);

        $this->assertInstanceOf(DomainResponse::class, $result);
        $this->assertInstanceOf(ResponseDomainRecord::class, $result->domain);
        $this->assertSame('d1', $result->domain->id);
        $this->assertInstanceOf(ResponseMeta::class, $result->meta);
        $this->assertSame('test-request-id', $result->meta->requestId);
    }

    public function testCreateReturnsApiResponseStatus(): void
    {
        $client = MockClientFactory::create([
            MockClientFactory::success(['domain' => $this->domainData()]),
        ]);

        $result = $client->domains()->createDomain(
            (new CreateDomainRequest())->setName('example.com')
        );

        $this->assertInstanceOf(ApiResponseStatus::class, $result->status);
        $this->assertSame('ok', $result->status->code);
    }

    public function testDeleteSendsDeleteRequestAndReturnsDomainDeleteResult(): void
    {
        $history = [];
        $client  = MockClientFactory::create([
            MockClientFactory::success(['domain' => ['id' => 'd1']]),
        ], $history);

        $result = $client->domains()->deleteDomain('d1');

        $request = $history[0]['request'];
        $this->assertSame('DELETE', $request->getMethod());
        $this->assertStringContainsString('domains/d1', $request->getUri()->getPath());

        $this->assertInstanceOf(DomainRemovalResponse::class, $result);
        $this->assertSame('d1', $result->id);
        $this->assertInstanceOf(ResponseMeta::class, $result->meta);
        $this->assertSame('test-request-id', $result->meta->requestId);
    }

    public function testDeleteReturnsApiResponseStatus(): void
    {
        $client = MockClientFactory::create([
            MockClientFactory::success(['domain' => ['id' => 'd1']]),
        ]);

        $result = $client->domains()->deleteDomain('d1');

        $this->assertInstanceOf(ApiResponseStatus::class, $result->status);
        $this->assertSame('ok', $result->status->code);
    }

    public function testDeleteReturnsEmptyStringWhenPayloadMissing(): void
    {
        $client = MockClientFactory::create([
            MockClientFactory::success([]),
        ]);

        $result = $client->domains()->deleteDomain('d1');

        $this->assertInstanceOf(DomainRemovalResponse::class, $result);
        $this->assertSame('', $result->id);
    }

    public function testPaginateIteratesMultiplePages(): void
    {
        $client = MockClientFactory::create([
            MockClientFactory::success(['items' => [$this->domainData('d1'), $this->domainData('d2', 'test.com')], 'pagination' => ['total' => 4, 'page' => 1, 'page_size' => 2, 'items' => 2]]),
            MockClientFactory::success(['items' => [$this->domainData('d3', 'third.com'), $this->domainData('d4', 'fourth.com')], 'pagination' => ['total' => 4, 'page' => 2, 'page_size' => 2, 'items' => 2]]),
        ]);

        $domains = iterator_to_array(
            $client->domains()->paginate((new DomainListRequest())->setPageSize(2)),
            false
        );

        $this->assertCount(4, $domains);
        $this->assertSame('d1', $domains[0]->id);
        $this->assertSame('d4', $domains[3]->id);
    }

    public function testPaginateStopsWhenPageHasFewerItemsThanPerPage(): void
    {
        $client = MockClientFactory::create([
            MockClientFactory::success(['items' => [$this->domainData('d1')], 'pagination' => ['total' => 1, 'page' => 1, 'page_size' => 2, 'items' => 1]]),
        ]);

        $domains = iterator_to_array(
            $client->domains()->paginate((new DomainListRequest())->setPageSize(2)),
            false
        );

        $this->assertCount(1, $domains);
    }

    public function testPaginateStopsWhenFirstPageIsEmpty(): void
    {
        $client = MockClientFactory::create([
            MockClientFactory::success(['items' => [], 'pagination' => ['total' => 0, 'page' => 1, 'page_size' => 20, 'items' => 0]]),
        ]);

        $domains = iterator_to_array(
            $client->domains()->paginate(new DomainListRequest()),
            false
        );

        $this->assertCount(0, $domains);
    }

    public function testCreateThrowsWhenNameEmpty(): void
    {
        $client = MockClientFactory::create([
            MockClientFactory::success([]),
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('name is required');
        $client->domains()->createDomain(new CreateDomainRequest());
    }

    public function testCreateWithOptionalFieldsSendsFullPayload(): void
    {
        $history = [];
        $client  = MockClientFactory::create([
            MockClientFactory::success(['domain' => $this->domainData()]),
        ], $history);

        $client->domains()->createDomain(
            (new CreateDomainRequest())
                ->setName('example.com')
                ->setGroupId(5)
                ->setDnsMethod('file')
                ->setSourceDomainId('d0')
                ->setDnsFileContent('dmFsdWU=')
        );

        $body = json_decode((string) $history[0]['request']->getBody(), true);
        $this->assertSame(5, $body['group_id']);
        $this->assertSame('file', $body['dns_method']);
        $this->assertSame('d0', $body['source_domain_id']);
        $this->assertArrayHasKey('dns_file_content', $body);
    }

    public function testCreateThrowsWhenDnsMethodInvalid(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid dns_method');

        (new CreateDomainRequest())->setDnsMethod('upload');
    }

    public function testSetNameThrowsOnEmptyString(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('name is required for domain creation.');
        (new CreateDomainRequest())->setName('');
    }

    public function testSetNameThrowsOnBlankString(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('name is required for domain creation.');
        (new CreateDomainRequest())->setName('   ');
    }

    public function testSetNameAcceptsExactly253Characters(): void
    {
        $name    = str_repeat('a', 253);
        $request = (new CreateDomainRequest())->setName($name);
        $this->assertSame($name, $request->toArray()['name']);
    }

    public function testSetNameThrowsWhenExceeds253Characters(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('name must not exceed 253 characters.');
        (new CreateDomainRequest())->setName(str_repeat('a', 254));
    }

    public function testDomainListRequestGetPageSizeReturnsDefault(): void
    {
        $this->assertSame(20, (new DomainListRequest())->getPageSize());
    }

    public function testDomainListRequestGetPageSizeReturnsSetValue(): void
    {
        $this->assertSame(50, (new DomainListRequest())->setPageSize(50)->getPageSize());
    }

    public function testSetMatchModeThrowsOnInvalidValue(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/Invalid match_mode/');
        (new DomainListRequest())->setMatchMode('invalid_mode');
    }
}
