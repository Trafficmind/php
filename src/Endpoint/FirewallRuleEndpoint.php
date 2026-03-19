<?php

declare(strict_types=1);

namespace Trafficmind\Api\Endpoint;

use Trafficmind\Api\Dto\FirewallRule\ActionAckResponse;
use Trafficmind\Api\Dto\FirewallRule\CreateAccountAccessRuleRequest;
use Trafficmind\Api\Dto\FirewallRule\CreateDomainAccessRuleRequest;
use Trafficmind\Api\Dto\Response\ApiResponseStatus;
use Trafficmind\Api\Dto\Response\ResponseMeta;
use Trafficmind\Api\Option\RequestOptions;
use Trafficmind\Api\TrafficmindClient;

final class FirewallRuleEndpoint
{
    /** @codeCoverageIgnore */
    public function __construct(private readonly TrafficmindClient $client)
    {
    }

    /**
     * Account Firewall Rule.
     * Creates a new IP Access rule for account. The rule will apply to all domains in the account.
     */
    public function createAccountFirewallRule(CreateAccountAccessRuleRequest $request, string $accountId, ?RequestOptions $options = null): ActionAckResponse
    {
        $resp = $this->client->post("accounts/$accountId/firewall_rules", $request->toArray(), $options);

        return new ActionAckResponse(
            acknowledged: (bool) ($resp['payload']['acknowledged'] ?? false),
            meta:         ResponseMeta::fromArray($resp['meta'] ?? []),
            status:  ApiResponseStatus::fromArray($resp['status'] ?? []),
        );
    }

    /**
     * Domain Firewall Rule.
     * Creates a new IP Access rule for domain. The rule will apply to the domain.
     */
    public function createDomainFirewallRule(CreateDomainAccessRuleRequest $request, string $domainId, ?RequestOptions $options = null): ActionAckResponse
    {
        $resp = $this->client->post("domains/$domainId/firewall_rules", $request->toArray(), $options);

        return new ActionAckResponse(
            acknowledged: (bool) ($resp['payload']['acknowledged'] ?? false),
            meta:         ResponseMeta::fromArray($resp['meta'] ?? []),
            status:  ApiResponseStatus::fromArray($resp['status'] ?? []),
        );
    }
}
