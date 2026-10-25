<?php

declare(strict_types=1);

namespace Ewk\Cms\Blocks\Http;

enum ErrorCode: string
{
    case BAD_REQUEST = 'bad_request';
    case NOT_FOUND = 'not_found';
    case TOO_MANY_REQUESTS = 'too_many_requests';
    case VALIDATION_FAILED = 'validation_failed';

    /**
     * @return array{message: string, code: string}
     */
    public function body(string $message): array
    {
        return ['message' => $message, 'code' => $this->value];
    }
}
