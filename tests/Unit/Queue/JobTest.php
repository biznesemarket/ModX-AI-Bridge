<?php

declare(strict_types=1);

namespace AIBridge\Tests\Unit\Queue;

use AIBridge\Queue\Job;
use AIBridge\Queue\JobDeadline;
use AIBridge\Queue\JobTimeoutException;
use AIBridge\Queue\NonRetryableJobException;
use PHPUnit\Framework\TestCase;

final class JobTest extends TestCase
{
    public function testDefaultsAreSafe(): void
    {
        $job = new Job('resource.update', ['id' => 1]);
        self::assertSame(3, $job->maxAttempts);
        self::assertSame(300, $job->timeoutSeconds);
    }

    public function testInvalidTypeRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new Job('');
    }

    public function testTimeoutIsTerminalAndNeverRetried(): void
    {
        $timeout = new JobTimeoutException('Job exceeded its time budget.');
        self::assertInstanceOf(NonRetryableJobException::class, $timeout);
        self::assertInstanceOf(\RuntimeException::class, $timeout);
    }

    public function testDeadlineDetectsElapsedBudget(): void
    {
        self::assertTrue((new JobDeadline(microtime(true) - 1))->exceeded());
        self::assertFalse((new JobDeadline(microtime(true) + 60))->exceeded());
        self::assertGreaterThan(0, (new JobDeadline(microtime(true) + 10))->remainingSeconds());
        self::assertSame(0, (new JobDeadline(microtime(true) - 1))->remainingSeconds());
    }

    public function testDeadlineWithoutBudgetIsInert(): void
    {
        $deadline = new JobDeadline(0.0);
        self::assertFalse($deadline->exceeded());
        self::assertSame(0, $deadline->remainingSeconds());
    }
}
