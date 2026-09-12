<?php

declare(strict_types=1);

namespace AIBridge\Validators;

final class RequestValidator
{
    public function validate(array $payload, array $rules = []): array
    {
        return [
            'valid' => true,
            'errors' => [],
        ];
    }
}
