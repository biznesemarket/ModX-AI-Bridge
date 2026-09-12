<?php

declare(strict_types=1);

namespace AIBridge\Validators;

use AIBridge\Services\ContentQAService;

final class ContentValidator
{
    public function __construct(private readonly ?ContentQAService $qa = null)
    {
    }

    public function validate(array $content, array $contract = []): array
    {
        return ($this->qa ?? new ContentQAService())->validate($content, $contract);
    }
}
