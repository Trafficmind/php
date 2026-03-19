<?php

declare(strict_types=1);

require __DIR__ . '/../bootstrap.php';

use Trafficmind\Api\TrafficmindClient;
use Trafficmind\Api\Dto\Domain\DomainListRequest;
use Trafficmind\Api\Dto\DomainRecord\DomainRecordListRequest;
use Trafficmind\Api\Exception\AuthException;
use Trafficmind\Api\Exception\RateLimitException;
use Trafficmind\Api\Exception\TrafficmindException;

$client = new TrafficmindClient(
	email:   $_ENV['TRAFFICMIND_ACCESS_USER'] ?? throw new \RuntimeException('TRAFFICMIND_ACCESS_USER is required'),
	apiKey:  $_ENV['TRAFFICMIND_ACCESS_KEY']   ?? throw new \RuntimeException('TRAFFICMIND_ACCESS_KEY is required'),
	baseUrl: $_ENV['TRAFFICMIND_BASE_URL']   ?? null,
);

try {
	// List domains
	$domainListResponse = $client->domains()->listDomains(
		(new DomainListRequest())->setPage(1)->setPageSize(20)
	);

	echo 'Domains: ' . count($domainListResponse->items) . PHP_EOL;

	if (empty($domainListResponse->items)) {
		exit(0);
	}

	$domainId = $domainListResponse->items[0]->id;

	// List domain records for the first domain
	$recordListResponse = $client->domainRecords()->listDomainRecords(
		(new DomainRecordListRequest())->setPage(1)->setPageSize(20),
		$domainId
	);

	echo 'Domain records: ' . count($recordListResponse->items) . PHP_EOL;

} catch (RateLimitException $e) {
	echo 'Rate limited. Retry after: ' . ($e->getRetryAfter() ?? 60) . 's' . PHP_EOL;
} catch (AuthException $e) {
	echo 'Authentication failed. Check your credentials.' . PHP_EOL;
} catch (TrafficmindException $e) {
	echo 'Error ' . $e->getStatusCode() . ': ' . $e->getMessage() . PHP_EOL;
}