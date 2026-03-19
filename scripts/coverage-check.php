<?php

declare(strict_types=1);

$file      = $argv[1] ?? null;
$threshold = (int) ($argv[2] ?? 80);

if ($file === null || !file_exists($file)) {
	echo 'Usage: php coverage-check.php <clover.xml> <threshold>' . PHP_EOL;
	exit(1);
}

$xml      = simplexml_load_file($file);
$metrics  = $xml->project->metrics;

$statements = (int) $metrics['statements'];
$covered    = (int) $metrics['coveredstatements'];

if ($statements === 0) {
	echo 'No statements found in coverage report.' . PHP_EOL;
	exit(1);
}

$percentage = round($covered / $statements * 100, 2);

echo sprintf('Coverage: %s%% (threshold: %d%%)' . PHP_EOL, $percentage, $threshold);

if ($percentage < $threshold) {
	echo 'Coverage is below threshold. Failing.' . PHP_EOL;
	exit(1);
}

echo 'Coverage check passed.' . PHP_EOL;
exit(0);