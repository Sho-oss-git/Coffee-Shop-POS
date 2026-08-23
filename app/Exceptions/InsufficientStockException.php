<?php

namespace App\Exceptions;

use Exception;

class InsufficientStockException extends Exception
{
    /**
     * @param  array<int, array{ingredient: string, required: float, available: float, unit: string}>  $shortfalls
     */
    public function __construct(string $message, private readonly array $shortfalls = [])
    {
        parent::__construct($message);
    }

    public function shortfalls(): array
    {
        return $this->shortfalls;
    }
}