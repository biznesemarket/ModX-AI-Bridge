<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class OperationsConsoleSecurityTest extends TestCase
{
    public function testSensitiveTokenFieldsAreNotPartOfConsoleContract(): void
    {
        $source = file_get_contents(__DIR__ . '/../../../core/components/aibridge/src/Manager/OperationsConsoleService.php');
        self::assertStringNotContainsString("'token' =>", $source);
        self::assertStringContainsString('token_hash is deliberately never returned', $source);
    }

    public function testManagerProcessorRequiresExplicitPermission(): void
    {
        $source = file_get_contents(__DIR__ . '/../../../core/components/aibridge/src/Manager/AdminProcessor.php');
        self::assertStringContainsString("hasPermission('aibridge_manage')", $source);
        self::assertStringContainsString("isAuthenticated('mgr')", $source);
    }
}
