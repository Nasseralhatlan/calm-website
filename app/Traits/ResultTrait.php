<?php

namespace App\Traits;

use App\Exceptions\ResultException;

trait ResultTrait
{
    protected function success(
        string $message = 'OK',
        mixed $data = null,
        int $status = 200
    ): array {
        return [
            'success' => true,
            'message' => $message,
            'data' => $data,
            'status' => $status,
        ];
    }

    protected function fail(
        string $message = 'Something went wrong, please try again later.',
        mixed $data = null,
        int $status = 400
    ): never {
        throw new ResultException($message, $status, $data);
    }
}
