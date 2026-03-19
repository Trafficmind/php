<?php

declare(strict_types=1);

namespace Trafficmind\Api\Tests\Unit\Dto;

use PHPUnit\Framework\TestCase;
use Trafficmind\Api\Dto\Response\ApiResponseStatus;

class ApiResponseStatusTest extends TestCase
{
    public function testFromArrayPopulatesAllFields(): void
    {
        $status = ApiResponseStatus::fromArray([
            'code'    => 'ok',
            'message' => 'Request processed successfully',
        ]);

        $this->assertSame('ok', $status->code);
        $this->assertSame('Request processed successfully', $status->message);
    }

    public function testFromArrayWithMissingFieldsReturnsNulls(): void
    {
        $status = ApiResponseStatus::fromArray([]);

        $this->assertNull($status->code);
        $this->assertNull($status->message);
    }
}
