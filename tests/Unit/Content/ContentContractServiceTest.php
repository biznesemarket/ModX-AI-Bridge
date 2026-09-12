<?php

declare(strict_types=1);

namespace AIBridge\Tests\Unit\Content;

use AIBridge\Services\ContentContractService;
use PHPUnit\Framework\TestCase;

final class ContentContractServiceTest extends TestCase
{
    public function testBuildsConservativeResourceContract(): void
    {
        $schema = [
            'templates' => [['id' => 2, 'name' => 'Article']],
            'template_variables' => [['id' => 10, 'name' => 'hero_image', 'type' => 'image']],
        ];
        $contract = (new ContentContractService())->resource($schema, 2)->toArray();

        self::assertSame('modx-ai-bridge/content', $contract['contract']);
        self::assertTrue($contract['fields']['pagetitle']['required']);
        self::assertSame('asset', $contract['fields']['tv:hero_image']['type']);
        self::assertFalse($contract['fields']['tv:hero_image']['required']);
    }
}
