<?php

declare(strict_types=1);

namespace AIBridge\Tests\Unit\Execution;

use AIBridge\Execution\ExecutionResult;
use PHPUnit\Framework\TestCase;

final class ExecutionResultTest extends TestCase
{
    public function testSuccessSerializes(): void
    {
        $result = new ExecutionResult(true, 'resource.update', ['resource_id' => 7]);
        self::assertSame(true, $result->toArray()['success']);
        self::assertSame(7, $result->toArray()['data']['resource_id']);
    }

    public function testFailureContainsStableErrorCode(): void
    {
        $result = new ExecutionResult(false, 'resource.delete', [], [], 'operation_disabled', 'Security policy denied.');
        self::assertSame('operation_disabled', $result->toArray()['error']['code']);
    }
}
