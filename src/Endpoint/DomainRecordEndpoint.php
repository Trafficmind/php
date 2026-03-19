<?php

declare(strict_types=1);

namespace Trafficmind\Api\Endpoint;

use Generator;
use InvalidArgumentException;
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
use Trafficmind\Api\Option\RequestOptions;
use Trafficmind\Api\TrafficmindClient;

final class DomainRecordEndpoint
{
    /** @codeCoverageIgnore */
    public function __construct(private readonly TrafficmindClient $client)
    {
    }

    /**
     * List, search, sort, and filter domain records.
     * Returns items alongside pagination metadata (total, page, page_size).
     */
    public function listDomainRecords(DomainRecordListRequest $request, string $domainId, ?RequestOptions $options = null): DomainRecordListResponse
    {
        $resp        = $this->client->get("domains/$domainId/records", $request->toArray(), $options);
        $items       = array_map(fn ($i) => DomainDnsRecord::fromArray($i), $resp['payload']['items'] ?? []);
        $pagination  = PaginationMeta::fromArray($resp['payload']['pagination'] ?? []);
        $searchQuery = $resp['payload']['search_query'] ?? null;
        $meta        = ResponseMeta::fromArray($resp['meta'] ?? []);
        $status      = ApiResponseStatus::fromArray($resp['status'] ?? []);

        return new DomainRecordListResponse($items, $pagination, $searchQuery, $meta, $status);
    }

    /**
     * Create a single domain record for a domain.
     */
    public function create(DomainDnsRecord $record, string $domainId, ?RequestOptions $options = null): DomainRecordResult
    {
        $batch = (new BatchDnsRequest())
            ->addCreate((new BatchDnsCreate())->setDomainRecord($record));

        $result = $this->batchDomainRecords($batch, $domainId, $options);

        if (empty($result->batch->getCreates())) {
            throw new \RuntimeException(
                'Domain record batch create succeeded but the API returned no record in "creates". '
                . 'The record may not have been created.'
            );
        }

        return new DomainRecordResult(
            record: $result->batch->getCreates()[0],
            meta:   $result->meta,
            status:  $result->status,
        );
    }

    /**
     * Update a single domain record for a domain.
     *
     * @throws InvalidArgumentException if $id is empty — the API silently ignores
     *                                   records without an id and returns empty updates.
     */
    public function update(string $id, DomainDnsRecord $record, string $domainId, ?RequestOptions $options = null): DomainRecordResult
    {
        if (trim($id) === '') {
            throw new InvalidArgumentException(
                'Domain record record id must not be empty. '
                . 'The API ignores records without an id and will not update anything.'
            );
        }

        $batch = (new BatchDnsRequest())
            ->addUpdate((new BatchDnsUpdate())->setId($id)->setDomainRecord($record));

        $result = $this->batchDomainRecords($batch, $domainId, $options);

        if (empty($result->batch->getUpdates())) {
            throw new \RuntimeException(
                'Domain record batch update succeeded but the API returned no record in "updates". '
                . 'The record id may be invalid or the record does not exist.'
            );
        }

        return new DomainRecordResult(
            record: $result->batch->getUpdates()[0],
            meta:   $result->meta,
            status:  $result->status,
        );
    }

    /**
     * Fully replace a single domain record (PUT semantics).
     *
     * @throws InvalidArgumentException if $id is empty.
     */
    public function replace(string $id, DomainDnsRecord $record, string $domainId, ?RequestOptions $options = null): DomainRecordResult
    {
        if (trim($id) === '') {
            throw new InvalidArgumentException(
                'Domain record id must not be empty. '
                . 'The API ignores records without an id and will not replace anything.'
            );
        }

        $batch = (new BatchDnsRequest())
            ->addReplace((new BatchDnsReplace())->setId($id)->setDomainRecord($record));

        $result = $this->batchDomainRecords($batch, $domainId, $options);

        if (empty($result->batch->getReplaces())) {
            throw new \RuntimeException(
                'Domain record batch replace succeeded but the API returned no record in "replaces". '
                . 'The record id may be invalid or the record does not exist.'
            );
        }

        return new DomainRecordResult(
            record: $result->batch->getReplaces()[0],
            meta:   $result->meta,
            status: $result->status,
        );
    }

    /**
     * Delete a single domain record for a domain.
     */
    public function delete(string $id, string $domainId, ?RequestOptions $options = null): DomainRecordDeleteResult
    {
        $batch = (new BatchDnsRequest())
            ->addDelete((new BatchDnsDelete())->setId($id));

        $result = $this->batchDomainRecords($batch, $domainId, $options);

        return new DomainRecordDeleteResult(meta: $result->meta, status: $result->status);
    }

    /**
     * Send a Batch of domain Record API calls to be executed together.
     */
    public function batchDomainRecords(BatchDnsRequest $request, string $domainId, ?RequestOptions $options = null): DomainRecordBatchResponse
    {
        $resp = $this->client->post("domains/$domainId/records/batch", $request->toArray(), $options);

        return new DomainRecordBatchResponse(
            batch: ResponseDnsRecordsBatchesResult::fromArray($resp['payload']['batch'] ?? []),
            meta:  ResponseMeta::fromArray($resp['meta'] ?? []),
            status:  ApiResponseStatus::fromArray($resp['status'] ?? []),
        );
    }

    /**
     * Lazily iterate over all domain records for a domain across multiple pages.
     *
     * @return Generator<DomainDnsRecord>
     */
    public function paginate(DomainRecordListRequest $request, string $domainId, ?RequestOptions $options = null): Generator
    {
        $page = 1;

        while (true) {
            $request->setPage($page);
            $result = $this->listDomainRecords($request, $domainId, $options);

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
