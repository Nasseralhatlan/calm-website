<?php

namespace App\Exceptions;

use Exception;

class ResultException extends Exception
{
    protected array $result;

    public function __construct(
        string $message,
        int $status = 400,
        mixed $data = null
    ) {
        parent::__construct($message, $status);

        $this->result = [
            'success' => false,
            'message' => $message,
            'data' => $data,
            'status' => $status,
        ];
    }

    public function result(): array
    {
        return $this->result;
    }
}
