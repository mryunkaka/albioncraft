<?php

declare(strict_types=1);

namespace App\Support;

use RuntimeException;

final class CalculationException extends RuntimeException
{
    /**
     * @param array<string, string> $errors
     */
    public function __construct(
        private readonly array $errors,
        string $message = 'Calculation validation failed.'
    ) {
        parent::__construct($message);
    }

    /**
     * @return array<string, string>
     */
    public function errors(): array
    {
        return $this->errors;
    }
}
