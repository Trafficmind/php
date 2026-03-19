<?php

declare(strict_types=1);

namespace Trafficmind\Api\Endpoint;

use Trafficmind\Api\Dto\Domain\DomainSettingResponse;
use Trafficmind\Api\Dto\Domain\ResponseDomainSetting;
use Trafficmind\Api\Dto\Domain\UpdateDomainSettingRequest;
use Trafficmind\Api\Dto\Response\ApiResponseStatus;
use Trafficmind\Api\Dto\Response\ResponseMeta;
use Trafficmind\Api\Option\RequestOptions;
use Trafficmind\Api\TrafficmindClient;

/**
 * Domain Settings.
 */
final class DomainSettingsEndpoint
{
    /** @codeCoverageIgnore */
    public function __construct(private readonly TrafficmindClient $client)
    {
    }

    /**
     * Fetch a single domain setting by name.
     */
    public function getDomainSetting(string $domainId, string $settingId, ?RequestOptions $options = null): DomainSettingResponse
    {
        $resp = $this->client->get("domains/$domainId/settings/$settingId", [], $options);

        return new DomainSettingResponse(
            setting: ResponseDomainSetting::fromArray($resp['payload']['setting'] ?? []),
            meta:    ResponseMeta::fromArray($resp['meta'] ?? []),
            status:  ApiResponseStatus::fromArray($resp['status'] ?? []),
        );
    }

    /**
     * Updates a single domain setting by the identifier.
     */
    public function updateDomainSetting(string $domainId, string $settingId, UpdateDomainSettingRequest $request, ?RequestOptions $options = null): DomainSettingResponse
    {
        $resp = $this->client->patch(
            "domains/$domainId/settings/$settingId",
            ['value' => $request->value],
            $options
        );

        return new DomainSettingResponse(
            setting: ResponseDomainSetting::fromArray($resp['payload']['setting'] ?? []),
            meta:    ResponseMeta::fromArray($resp['meta'] ?? []),
            status:  ApiResponseStatus::fromArray($resp['status'] ?? []),
        );
    }
}
