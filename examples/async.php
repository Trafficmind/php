<?php

declare(strict_types=1);

require __DIR__ . '/../bootstrap.php';

use GuzzleHttp\Client;
use GuzzleHttp\Pool;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;

/**
 * Example: fetch domain records for multiple domains concurrently using Guzzle Pool.
 *
 * This approach sends up to $concurrency requests in parallel without blocking.
 * Each request is independent — no shared mutable state between coroutines.
 */

$email  = $_ENV['TRAFFICMIND_ACCESS_USER'] ?? 'YOUR_X_ACCESS_USER';
$apiKey = $_ENV['TRAFFICMIND_ACCESS_KEY']   ?? 'YOUR_X_ACCESS_KEY';
$baseUrl = $_ENV['TRAFFICMIND_BASE_URL']   ?? 'https://api.trafficmind.com/public/v1/';

$domainIds = [
	'domain-id-1',
	'domain-id-2',
	'domain-id-3',
];

$httpClient = new Client([
	'base_uri' => $baseUrl,
	'timeout'  => 30.0,
	'headers'  => [
		'X-Access-User' => $email,
		'X-Access-Key'   => $apiKey,
		'Accept'       => 'application/json',
		'Content-Type' => 'application/json',
	],
]);

$requests = static function (array $domainIds): \Generator {
	foreach ($domainIds as $domainId) {
		yield $domainId => new Request('GET', "domains/$domainId/records");
	}
};

$results = [];
$errors  = [];

$pool = new Pool($httpClient, $requests($domainIds), [
	'concurrency' => 5,
	'fulfilled'   => function (Response $response, string $domainId) use (&$results): void {
		$body    = (string) $response->getBody();
		$decoded = json_decode($body, true);
		$records = $decoded['payload']['records'] ?? [];

		$results[$domainId] = count($records);
		echo "domain $domainId: " . count($records) . " Domain records" . PHP_EOL;
	},
	'rejected'    => function (\Throwable $reason, string $domainId) use (&$errors): void {
		$errors[$domainId] = $reason->getMessage();
		echo "domain $domainId failed: " . $reason->getMessage() . PHP_EOL;
	},
]);

// Start the pool — blocks until all requests complete
$pool->promise()->wait();

echo PHP_EOL;
echo 'Completed: ' . count($results) . ' domains' . PHP_EOL;
echo 'Failed:    ' . count($errors)  . ' domains' . PHP_EOL;