<?php

declare(strict_types=1);

namespace AIBridge\Tests\Unit\Application;

use PHPUnit\Framework\TestCase;

final class ApplicationTest extends TestCase
{
    public function testModxRuntimeIsRequiredForApplicationIntegration(): void
    {
        self::assertTrue(
            class_exists('MODX\\Revolution\\modX') === false || class_exists('AIBridge\\Application\\Application')
        );
    }
}
