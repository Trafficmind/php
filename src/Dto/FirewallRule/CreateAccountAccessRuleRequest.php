<?php

declare(strict_types=1);

namespace Trafficmind\Api\Dto\FirewallRule;

final class CreateAccountAccessRuleRequest
{
    private const ALLOWED_MODES   = ['challenge', 'block', 'allow'];
    private const ALLOWED_TARGETS = ['ip', 'country'];

    private string  $mode   = '';
    private string  $target = '';
    private string  $value  = '';
    private ?string $notes  = null;

    public function setMode(string $mode): self
    {
        if (!in_array($mode, self::ALLOWED_MODES, true)) {
            throw new \InvalidArgumentException(
                sprintf('Invalid mode "%s". Allowed values: %s.', $mode, implode(', ', self::ALLOWED_MODES))
            );
        }
        $this->mode = $mode;
        return $this;
    }

    public function setTarget(string $target): self
    {
        if (!in_array($target, self::ALLOWED_TARGETS, true)) {
            throw new \InvalidArgumentException(
                sprintf('Invalid target "%s". Allowed values: %s.', $target, implode(', ', self::ALLOWED_TARGETS))
            );
        }
        $this->target = $target;
        return $this;
    }

    public function setValue(string $value): self
    {
        $this->value = $value;
        return $this;
    }

    public function setNotes(?string $notes): self
    {
        $this->notes = $notes;
        return $this;
    }

    public function toArray(): array
    {
        if (trim($this->mode) === '') {
            throw new \InvalidArgumentException('mode is required for account firewall rule creation.');
        }
        if (trim($this->target) === '') {
            throw new \InvalidArgumentException('configuration.target is required for account firewall rule creation.');
        }
        if (trim($this->value) === '') {
            throw new \InvalidArgumentException('configuration.value is required for account firewall rule creation.');
        }

        $data = [
            'configuration' => [
                'target' => $this->target,
                'value'  => $this->value,
            ],
            'mode' => $this->mode,
        ];

        if ($this->notes !== null) {
            $data['notes'] = $this->notes;
        }

        return $data;
    }
}
