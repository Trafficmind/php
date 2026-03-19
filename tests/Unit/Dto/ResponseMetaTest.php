<?php

declare(strict_types=1);

namespace Trafficmind\Api\Tests\Unit\Dto;

use PHPUnit\Framework\TestCase;
use Trafficmind\Api\Dto\Response\ResponseMeta;

class ResponseMetaTest extends TestCase
{
    public function testFromArrayPopulatesAllFields(): void
    {
        $meta = ResponseMeta::fromArray([
            'request_id' => 'abc-123',
            'timestamp'  => '2024-01-15T12:00:00Z',
        ]);

        $this->assertSame('abc-123', $meta->requestId);
        $this->assertInstanceOf(\DateTimeImmutable::class, $meta->timestamp);
        $this->assertSame('2024-01-15', $meta->timestamp->format('Y-m-d'));
    }

    public function testFromArrayWithMissingFieldsReturnsNulls(): void
    {
        $meta = ResponseMeta::fromArray([]);

        $this->assertNull($meta->requestId);
        $this->assertNull($meta->timestamp);
    }

    public function testFromArrayWithMalformedTimestampReturnsNull(): void
    {
        $meta = ResponseMeta::fromArray([
            'request_id' => 'abc-123',
            'timestamp'  => 'not-a-date',
        ]);

        $this->assertSame('abc-123', $meta->requestId);
        $this->assertNull($meta->timestamp);
    }
}
