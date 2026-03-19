<?php

declare(strict_types=1);

namespace Trafficmind\Api\Hook;

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Trafficmind\Api\Exception\TrafficmindException;

interface HookInterface
{
    public function onRequest(RequestInterface $request, int $attempt): void;

    public function onResponse(RequestInterface $request, ResponseInterface $response, float $duration, int $attempt): void;

    public function onError(RequestInterface $request, TrafficmindException $exception, float $duration, int $attempt): void;
}
