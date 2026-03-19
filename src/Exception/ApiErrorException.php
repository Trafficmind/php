<?php

declare(strict_types=1);

namespace Trafficmind\Api\Exception;

use Trafficmind\Api\Dto\Error\ErrorDetail;

/**
 * Thrown when the API returns a structured error response.
 * Carries per-field validation details from dto.ErrorBody / dto.ErrorDetail.
 */
class ApiErrorException extends TrafficmindException
{
    /**
     * @param ErrorDetail[] $details
     */
    public function __construct(
        string $message,
        int $statusCode = 0,
        private readonly ?string $errorType = null,
        private readonly array $details = [],
        ?\Throwable $previous = null,
        ?string $requestId = null,
    ) {
        parent::__construct($message, $statusCode, $previous, $requestId);
    }

    /**
     * Machine-readable error type from dto.ErrorBody.type (e.g. "validation_error").
     */
    public function getErrorType(): ?string
    {
        return $this->errorType;
    }

    /**
     * Per-field validation details. Empty when the error is not field-level.
     *
     * @return ErrorDetail[]
     */
    public function getDetails(): array
    {
        return $this->details;
    }

    public static function fromResponseData(array $data, int $statusCode, ?string $requestId = null): self
    {
        $error   = $data['error']    ?? [];
        $message = $error['message'] ?? ($data['message'] ?? "HTTP $statusCode");
        $type    = $error['type']    ?? null;
        $details = array_map(
            fn (array $d) => ErrorDetail::fromArray($d),
            is_array($error['details'] ?? null) ? $error['details'] : []
        );

        return new self(
            message:    $message,
            statusCode: $statusCode,
            errorType:  $type,
            details:    $details,
            requestId:  $requestId,
        );
    }
}
