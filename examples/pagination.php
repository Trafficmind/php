<?php

declare(strict_types=1);

require __DIR__ . '/../bootstrap.php';

use Trafficmind\Api\TrafficmindClient;
use Trafficmind\Api\Dto\Domain\DomainListRequest;
use Trafficmind\Api\Dto\DomainRecord\DomainRecordListRequest;
use Trafficmind\Api\Exception\TrafficmindException;

$client = new TrafficmindClient(
	email:   $_ENV['TRAFFICMIND_ACCESS_USER'] ?? throw new \RuntimeException('TRAFFICMIND_ACCESS_USER is required'),
	apiKey:  $_ENV['TRAFFICMIND_ACCESS_KEY']   ?? throw new \RuntimeException('TRAFFICMIND_ACCESS_KEY is required'),
	baseUrl: $_ENV['TRAFFICMIND_BASE_URL']   ?? null,
);

try {
	// ── Paginate all domains ─────────────────────────────────────────────────────

	echo '=== Domains ===' . PHP_EOL;

	$domainCount   = 0;
	$firstDomainId = null;

	foreach ($client->domains()->paginate((new DomainListRequest())->setPageSize(5)) as $domain) {
		echo $domain->name . PHP_EOL;
		$firstDomainId ??= $domain->id;
		$domainCount++;
	}

	echo 'Total: ' . $domainCount . ' domains' . PHP_EOL;

	if ($firstDomainId === null) {
		exit(0);
	}

	// ── Paginate domain records for the first domain ────────────────────────────────

	echo PHP_EOL . '=== Domain records for domain ' . $firstDomainId . ' ===' . PHP_EOL;

	$recordCount = 0;

	foreach ($client->domainRecords()->paginate((new DomainRecordListRequest())->setPageSize(5), $firstDomainId) as $record) {
		echo sprintf('%s %s -> %s' . PHP_EOL, $record->type, $record->name, $record->content);
		$recordCount++;
	}

	echo 'Total: ' . $recordCount . ' domain records' . PHP_EOL;

} catch (TrafficmindException $e) {
	echo 'Error ' . $e->getStatusCode() . ': ' . $e->getMessage() . PHP_EOL;
}
