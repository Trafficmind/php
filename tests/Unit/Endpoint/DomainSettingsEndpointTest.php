<?php

declare(strict_types=1);

namespace Trafficmind\Api\Tests\Unit\Endpoint;

use PHPUnit\Framework\TestCase;
use Trafficmind\Api\Dto\Domain\DomainSettingResponse;
use Trafficmind\Api\Dto\Domain\ResponseDomainSetting;
use Trafficmind\Api\Dto\Domain\UpdateDomainSettingRequest;
use Trafficmind\Api\Dto\Response\ApiResponseStatus;
use Trafficmind\Api\Dto\Response\ResponseMeta;
use Trafficmind\Api\Tests\Unit\MockClientFactory;

class DomainSettingsEndpointTest extends TestCase
{
    public function testGetSendsCorrectRequestAndReturnsSettingResult(): void
    {
        $history = [];
        $client  = MockClientFactory::create([
            MockClientFactory::success(['setting' => ['id' => 'ssl', 'value' => 'full']]),
        ], $history);

        $result = $client->domainSettings()->getDomainSetting('d1', 'ssl');

        $request = $history[0]['request'];
        $this->assertSame('GET', $request->getMethod());
        $this->assertStringContainsString('domains/d1/settings/ssl', $request->getUri()->getPath());

        $this->assertInstanceOf(DomainSettingResponse::class, $result);
        $this->assertInstanceOf(ResponseDomainSetting::class, $result->setting);
        $this->assertSame('ssl', $result->setting->id);
        $this->assertSame('full', $result->setting->value);
        $this->assertInstanceOf(ResponseMeta::class, $result->meta);
        $this->assertSame('test-request-id', $result->meta->requestId);
    }

    public function testGetReturnsApiResponseStatus(): void
    {
        $client = MockClientFactory::create([
            MockClientFactory::success(['setting' => ['id' => 'ssl', 'value' => 'full']]),
        ]);

        $result = $client->domainSettings()->getDomainSetting('d1', 'ssl');

        $this->assertInstanceOf(ApiResponseStatus::class, $result->status);
        $this->assertSame('ok', $result->status->code);
        $this->assertSame('Request processed successfully', $result->status->message);
    }

    public function testUpdateSendsPatchWithValueAndReturnsSettingResult(): void
    {
        $history = [];
        $client  = MockClientFactory::create([
            MockClientFactory::success(['setting' => ['id' => 'ssl', 'value' => 'strict']]),
        ], $history);

        $result = $client->domainSettings()->updateDomainSetting('d1', 'ssl', new UpdateDomainSettingRequest(value: 'strict'));

        $request = $history[0]['request'];
        $this->assertSame('PATCH', $request->getMethod());
        $this->assertStringContainsString('domains/d1/settings/ssl', $request->getUri()->getPath());

        $body = json_decode((string) $request->getBody(), true);
        $this->assertSame('strict', $body['value']);

        $this->assertInstanceOf(DomainSettingResponse::class, $result);
        $this->assertInstanceOf(ResponseDomainSetting::class, $result->setting);
        $this->assertSame('strict', $result->setting->value);
        $this->assertInstanceOf(ResponseMeta::class, $result->meta);
        $this->assertSame('test-request-id', $result->meta->requestId);
    }

    public function testUpdateReturnsApiResponseStatus(): void
    {
        $client = MockClientFactory::create([
            MockClientFactory::success(['setting' => ['id' => 'ssl', 'value' => 'strict']]),
        ]);

        $result = $client->domainSettings()->updateDomainSetting('d1', 'ssl', new UpdateDomainSettingRequest(value: 'strict'));

        $this->assertInstanceOf(ApiResponseStatus::class, $result->status);
        $this->assertSame('ok', $result->status->code);
        $this->assertSame('Request processed successfully', $result->status->message);
    }
}
