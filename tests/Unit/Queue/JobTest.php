<?php

declare(strict_types=1);

namespace AIBridge\Tests\Unit\Queue;

use AIBridge\Queue\Job;
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
}
