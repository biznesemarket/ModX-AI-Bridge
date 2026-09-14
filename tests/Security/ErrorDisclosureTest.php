<?php

declare(strict_types=1);

namespace AIBridge\Tests\Security;

use PHPUnit\Framework\TestCase;

/**
 * Regression guard for Defect #36: internal exception text must never be
 * returned to REST/MCP clients or persisted into a job's `error_json`.
 *
 * Full diagnostics are still kept for operators, but only after being passed
 * through `SecretRedactor::redactText()` and only into internal sinks (audit /
 * MODX log).
 */
final class ErrorDisclosureTest extends TestCase
{
    private static function coreFile(string $relative): string
    {
        $path = dirname(__DIR__, 2) . '/core/components/aibridge/' . $relative;
        self::assertFileExists($path);
        return (string) file_get_contents($path);
    }

    public function testExecutionResultsNeverEmbedRawExceptionMessages(): void
    {
        $source = self::coreFile('src/Execution/ResourceExecutionService.php');
        self::assertStringNotContainsString("'execution_failed', \$e->getMessage()", $source);
        self::assertStringNotContainsString("'preview_failed', \$e->getMessage()", $source);
        self::assertStringNotContainsString("'error' => \$e->getMessage()", $source);
    }

    public function testJobErrorsNeverEmbedRawExceptionMessages(): void
    {
        $source = self::coreFile('src/Queue/Worker.php');
        self::assertStringNotContainsString("'message' => \$e->getMessage()", $source);
        self::assertStringContainsString("'diagnostic' => \$this->redactor->redactText(\$e->getMessage())", $source);
    }

    public function testDiagnosticsAreRedacted(): void
    {
        self::assertStringContainsString(
            'redactText($e->getMessage())',
            self::coreFile('src/Execution/ResourceExecutionService.php')
        );
    }

    public function testManagerWorkflowFailuresAreGeneric(): void
    {
        $source = self::coreFile('processors/manager/workflow.class.php');
        self::assertStringNotContainsString('failure($e->getMessage()', $source);
        self::assertStringContainsString("failure('Workflow operation failed.'", $source);
    }
}
