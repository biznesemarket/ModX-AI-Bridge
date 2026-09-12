<?php
declare(strict_types=1);

namespace AIBridge\Tests\Integration;

use PHPUnit\Framework\TestCase;

final class QueueConcurrencyTest extends TestCase
{
    public function testRuntimeSuiteContainsConcurrencyHarness(): void
    {
        $script = dirname(__DIR__, 2) . '/scripts/test-queue-concurrency.php';
        self::assertFileExists($script);
        self::assertFileIsReadable($script);
    }
}
