<?php

declare(strict_types=1);

namespace Trafficmind\Api\Endpoint;

use Generator;
use Trafficmind\Api\Dto\Domain\CreateDomainRequest;
use Trafficmind\Api\Dto\Domain\DomainListRequest;
use Trafficmind\Api\Dto\Domain\DomainListResponse;
use Trafficmind\Api\Dto\Domain\DomainRemovalResponse;
use Trafficmind\Api\Dto\Domain\DomainResponse;
use Trafficmind\Api\Dto\Domain\ResponseDomainRecord;
use Trafficmind\Api\Dto\Response\ApiResponseStatus;
use Trafficmind\Api\Dto\Response\PaginationMeta;
use Trafficmind\Api\Dto\Response\ResponseMeta;
use Trafficmind\Api\Option\RequestOptions;
use Trafficmind\Api\TrafficmindClient;

final class DomainEndpoint
{
    /** @codeCoverageIgnore */
    public function __construct(private readonly TrafficmindClient $client)
    {
    }

    /**
     * Lists, searches, sorts, and filters your domains.
     * Returns items alongside pagination metadata (total, page, page_size).
     */
    public function listDomains(DomainListRequest $request, ?RequestOptions $options = null): DomainListResponse
    {
        $resp       = $this->client->get('domains', $request->toArray(), $options);
        $items      = array_map(fn ($i) => ResponseDomainRecord::fromArray($i), $resp['payload']['items'] ?? []);
        $pagination = PaginationMeta::fromArray($resp['payload']['pagination'] ?? []);
        $meta       = ResponseMeta::fromArray($resp['meta'] ?? []);
        $status     = ApiResponseStatus::fromArray($resp['status'] ?? []);

        return new DomainListResponse($items, $pagination, $meta, $status);
    }

    /**
     * Create Domain.
     */
    public function createDomain(CreateDomainRequest $request, ?RequestOptions $options = null): DomainResponse
    {
        $resp = $this->client->post('domains', $request->toArray(), $options);

        return new DomainResponse(
            domain: ResponseDomainRecord::fromArray($resp['payload']['domain'] ?? []),
            meta:   ResponseMeta::fromArray($resp['meta'] ?? []),
            status:  ApiResponseStatus::fromArray($resp['status'] ?? []),
        );
    }

    /**
     * Domain Details.
     */
    public function getDomain(string $domainId, ?RequestOptions $options = null): DomainResponse
    {
        $resp = $this->client->get("domains/$domainId", [], $options);

        return new DomainResponse(
            domain: ResponseDomainRecord::fromArray($resp['payload']['domain'] ?? []),
            meta:   ResponseMeta::fromArray($resp['meta'] ?? []),
            status:  ApiResponseStatus::fromArray($resp['status'] ?? []),
        );
    }

    /**
     * Deletes an existing Domain.
     * Returns the ID of the deleted domain alongside response meta.
     */
    public function deleteDomain(string $domainId, ?RequestOptions $options = null): DomainRemovalResponse
    {
        $resp = $this->client->delete("domains/$domainId", $options);

        return new DomainRemovalResponse(
            id:   $resp['payload']['domain']['id'] ?? '',
            meta: ResponseMeta::fromArray($resp['meta'] ?? []),
            status:  ApiResponseStatus::fromArray($resp['status'] ?? []),
        );
    }

    /**
     * @return Generator<ResponseDomainRecord>
     */
    public function paginate(DomainListRequest $request, ?RequestOptions $options = null): Generator
    {
        $page = 1;

        while (true) {
            $request->setPage($page);
            $result = $this->listDomains($request, $options);

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
}
