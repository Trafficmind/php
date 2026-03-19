<?php

declare(strict_types=1);

namespace Trafficmind\Api\Tests\Unit\Endpoint;

use PHPUnit\Framework\TestCase;
use Trafficmind\Api\Dto\FirewallRule\ActionAckResponse;
use Trafficmind\Api\Dto\FirewallRule\CreateAccountAccessRuleRequest;
use Trafficmind\Api\Dto\FirewallRule\CreateDomainAccessRuleRequest;
use Trafficmind\Api\Dto\Response\ApiResponseStatus;
use Trafficmind\Api\Dto\Response\ResponseMeta;
use Trafficmind\Api\Tests\Unit\MockClientFactory;

class FirewallRuleEndpointTest extends TestCase
{
    public function testCreateAccountWafRuleSendsCorrectRequestAndReturnsResult(): void
    {
        $history = [];
        $client  = MockClientFactory::create([
            MockClientFactory::success(['acknowledged' => true]),
        ], $history);

        $request = (new CreateAccountAccessRuleRequest())
            ->setMode('block')
            ->setTarget('ip')
            ->setValue('1.2.3.4')
            ->setNotes('test rule');

        $result = $client->firewallRules()->createAccountFirewallRule($request, 'a1');

        $httpRequest = $history[0]['request'];
        $this->assertSame('POST', $httpRequest->getMethod());
        $this->assertStringContainsString('accounts/a1/firewall_rules', $httpRequest->getUri()->getPath());

        $body = json_decode((string) $httpRequest->getBody(), true);
        $this->assertSame('block', $body['mode']);
        $this->assertSame('ip', $body['configuration']['target']);
        $this->assertSame('1.2.3.4', $body['configuration']['value']);
        $this->assertSame('test rule', $body['notes']);

        $this->assertInstanceOf(ActionAckResponse::class, $result);
        $this->assertTrue($result->acknowledged);
        $this->assertInstanceOf(ResponseMeta::class, $result->meta);
        $this->assertSame('test-request-id', $result->meta->requestId);
    }

    public function testCreateAccountWafRuleReturnsApiResponseStatus(): void
    {
        $client = MockClientFactory::create([
            MockClientFactory::success(['acknowledged' => true]),
        ]);

        $request = (new CreateAccountAccessRuleRequest())
            ->setMode('block')
            ->setTarget('ip')
            ->setValue('1.2.3.4');

        $result = $client->firewallRules()->createAccountFirewallRule($request, 'a1');

        $this->assertInstanceOf(ApiResponseStatus::class, $result->status);
        $this->assertSame('ok', $result->status->code);
        $this->assertSame('Request processed successfully', $result->status->message);
    }

    public function testCreateAccountWafRuleReturnsFalseWhenNotAcknowledged(): void
    {
        $client = MockClientFactory::create([
            MockClientFactory::success(['acknowledged' => false]),
        ]);

        $request = (new CreateAccountAccessRuleRequest())
            ->setMode('block')
            ->setTarget('ip')
            ->setValue('1.2.3.4');

        $result = $client->firewallRules()->createAccountFirewallRule($request, 'a1');

        $this->assertInstanceOf(ActionAckResponse::class, $result);
        $this->assertFalse($result->acknowledged);
    }

    public function testCreateAccountWafRuleOmitsNotesKeyWhenNotSet(): void
    {
        $history = [];
        $client  = MockClientFactory::create([
            MockClientFactory::success(['acknowledged' => true]),
        ], $history);

        $request = (new CreateAccountAccessRuleRequest())
            ->setMode('block')
            ->setTarget('ip')
            ->setValue('1.2.3.4');

        $client->firewallRules()->createAccountFirewallRule($request, 'a1');

        $body = json_decode((string) $history[0]['request']->getBody(), true);
        $this->assertArrayNotHasKey('notes', $body);
    }

    public function testCreateAccountWafRuleReturnsFalseWhenPayloadEmpty(): void
    {
        $client = MockClientFactory::create([
            MockClientFactory::success([]),
        ]);

        $request = (new CreateAccountAccessRuleRequest())
            ->setMode('block')
            ->setTarget('ip')
            ->setValue('1.2.3.4');

        $result = $client->firewallRules()->createAccountFirewallRule($request, 'a1');

        $this->assertInstanceOf(ActionAckResponse::class, $result);
        $this->assertFalse($result->acknowledged);
    }

    public function testAccountRuleSetModeThrowsOnInvalidValue(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid mode "deny". Allowed values: challenge, block, allow.');

        (new CreateAccountAccessRuleRequest())->setMode('deny');
    }

    public function testAccountRuleSetTargetThrowsOnInvalidValue(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid target "region". Allowed values: ip, country.');

        (new CreateAccountAccessRuleRequest())->setTarget('region');
    }

    public function testAccountRuleToArrayThrowsWhenModeEmpty(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('mode is required for account firewall rule creation.');

        (new CreateAccountAccessRuleRequest())
            ->setTarget('ip')
            ->setValue('1.2.3.4')
            ->toArray();
    }

    public function testAccountRuleToArrayThrowsWhenTargetEmpty(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('configuration.target is required for account firewall rule creation.');

        (new CreateAccountAccessRuleRequest())
            ->setMode('block')
            ->setValue('1.2.3.4')
            ->toArray();
    }

    public function testAccountRuleToArrayThrowsWhenValueEmpty(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('configuration.value is required for account firewall rule creation.');

        (new CreateAccountAccessRuleRequest())
            ->setMode('block')
            ->setTarget('ip')
            ->toArray();
    }

    /**
     * @dataProvider accountRuleModeProvider
     */
    public function testAccountRuleAcceptsAllValidModes(string $mode): void
    {
        $request = (new CreateAccountAccessRuleRequest())
            ->setMode($mode)
            ->setTarget('ip')
            ->setValue('1.2.3.4');

        $body = $request->toArray();
        $this->assertSame($mode, $body['mode']);
    }

    public static function accountRuleModeProvider(): array
    {
        return [['challenge'], ['block'], ['allow']];
    }

    /**
     * @dataProvider accountRuleTargetProvider
     */
    public function testAccountRuleAcceptsAllValidTargets(string $target): void
    {
        $request = (new CreateAccountAccessRuleRequest())
            ->setMode('block')
            ->setTarget($target)
            ->setValue('1.2.3.4');

        $body = $request->toArray();
        $this->assertSame($target, $body['configuration']['target']);
    }

    public static function accountRuleTargetProvider(): array
    {
        return [['ip'], ['country']];
    }

    public function testCreateDomainWafRuleSendsCorrectRequestAndReturnsResult(): void
    {
        $history = [];
        $client  = MockClientFactory::create([
            MockClientFactory::success(['acknowledged' => true]),
        ], $history);

        $request = (new CreateDomainAccessRuleRequest())
            ->setMode('allow')
            ->setTarget('ip')
            ->setValue('1.2.3.4');

        $result = $client->firewallRules()->createDomainFirewallRule($request, 'd1');

        $httpRequest = $history[0]['request'];
        $this->assertSame('POST', $httpRequest->getMethod());
        $this->assertStringContainsString('domains/d1/firewall_rules', $httpRequest->getUri()->getPath());

        $body = json_decode((string) $httpRequest->getBody(), true);
        $this->assertSame('allow', $body['mode']);
        $this->assertSame('ip', $body['configuration']['target']);
        $this->assertSame('1.2.3.4', $body['configuration']['value']);

        $this->assertInstanceOf(ActionAckResponse::class, $result);
        $this->assertTrue($result->acknowledged);
        $this->assertInstanceOf(ResponseMeta::class, $result->meta);
        $this->assertSame('test-request-id', $result->meta->requestId);
    }

    public function testCreateDomainWafRuleReturnsApiResponseStatus(): void
    {
        $client = MockClientFactory::create([
            MockClientFactory::success(['acknowledged' => true]),
        ]);

        $request = (new CreateDomainAccessRuleRequest())
            ->setMode('allow')
            ->setTarget('ip')
            ->setValue('1.2.3.4');

        $result = $client->firewallRules()->createDomainFirewallRule($request, 'd1');

        $this->assertInstanceOf(ApiResponseStatus::class, $result->status);
        $this->assertSame('ok', $result->status->code);
        $this->assertSame('Request processed successfully', $result->status->message);
    }

    public function testCreateDomainWafRuleWithNotesSendsNotes(): void
    {
        $history = [];
        $client  = MockClientFactory::create([
            MockClientFactory::success(['acknowledged' => true]),
        ], $history);

        $request = (new CreateDomainAccessRuleRequest())
            ->setMode('block')
            ->setTarget('ip')
            ->setValue('5.6.7.8')
            ->setNotes('blocked for abuse');

        $client->firewallRules()->createDomainFirewallRule($request, 'd1');

        $body = json_decode((string) $history[0]['request']->getBody(), true);
        $this->assertSame('blocked for abuse', $body['notes']);
    }

    public function testCreateDomainWafRuleReturnsFalseWhenNotAcknowledged(): void
    {
        $client = MockClientFactory::create([
            MockClientFactory::success(['acknowledged' => false]),
        ]);

        $request = (new CreateDomainAccessRuleRequest())
            ->setMode('block')
            ->setTarget('ip')
            ->setValue('5.6.7.8');

        $result = $client->firewallRules()->createDomainFirewallRule($request, 'd1');

        $this->assertInstanceOf(ActionAckResponse::class, $result);
        $this->assertFalse($result->acknowledged);
    }

    public function testCreateDomainWafRuleOmitsNotesKeyWhenNotSet(): void
    {
        $history = [];
        $client  = MockClientFactory::create([
            MockClientFactory::success(['acknowledged' => true]),
        ], $history);

        $request = (new CreateDomainAccessRuleRequest())
            ->setMode('block')
            ->setTarget('ip')
            ->setValue('5.6.7.8');

        $client->firewallRules()->createDomainFirewallRule($request, 'd1');

        $body = json_decode((string) $history[0]['request']->getBody(), true);
        $this->assertArrayNotHasKey('notes', $body);
    }

    public function testCreateDomainWafRuleReturnsFalseWhenPayloadEmpty(): void
    {
        $client = MockClientFactory::create([
            MockClientFactory::success([]),
        ]);

        $request = (new CreateDomainAccessRuleRequest())
            ->setMode('block')
            ->setTarget('ip')
            ->setValue('5.6.7.8');

        $result = $client->firewallRules()->createDomainFirewallRule($request, 'd1');

        $this->assertInstanceOf(ActionAckResponse::class, $result);
        $this->assertFalse($result->acknowledged);
    }

    public function testDomainRuleSetModeThrowsOnInvalidValue(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid mode "deny". Allowed values: challenge, block, allow.');

        (new CreateDomainAccessRuleRequest())->setMode('deny');
    }

    public function testDomainRuleSetTargetThrowsOnInvalidValue(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid target "region". Allowed values: ip, country.');

        (new CreateDomainAccessRuleRequest())->setTarget('region');
    }

    public function testDomainRuleToArrayThrowsWhenModeEmpty(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('mode is required for domain firewall rule creation.');

        (new CreateDomainAccessRuleRequest())
            ->setTarget('ip')
            ->setValue('1.2.3.4')
            ->toArray();
    }

    public function testDomainRuleToArrayThrowsWhenTargetEmpty(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('configuration.target is required for domain firewall rule creation.');

        (new CreateDomainAccessRuleRequest())
            ->setMode('block')
            ->setValue('1.2.3.4')
            ->toArray();
    }

    public function testDomainRuleToArrayThrowsWhenValueEmpty(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('configuration.value is required for domain firewall rule creation.');

        (new CreateDomainAccessRuleRequest())
            ->setMode('block')
            ->setTarget('ip')
            ->toArray();
    }

    /**
     * @dataProvider domainRuleModeProvider
     */
    public function testDomainRuleAcceptsAllValidModes(string $mode): void
    {
        $request = (new CreateDomainAccessRuleRequest())
            ->setMode($mode)
            ->setTarget('ip')
            ->setValue('1.2.3.4');

        $body = $request->toArray();
        $this->assertSame($mode, $body['mode']);
    }

    public static function domainRuleModeProvider(): array
    {
        return [['challenge'], ['block'], ['allow']];
    }

    /**
     * @dataProvider domainRuleTargetProvider
     */
    public function testDomainRuleAcceptsAllValidTargets(string $target): void
    {
        $request = (new CreateDomainAccessRuleRequest())
            ->setMode('block')
            ->setTarget($target)
            ->setValue('1.2.3.4');

        $body = $request->toArray();
        $this->assertSame($target, $body['configuration']['target']);
    }

    public static function domainRuleTargetProvider(): array
    {
        return [['ip'], ['country']];
    }
}
