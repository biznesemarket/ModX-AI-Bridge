<?php

declare(strict_types=1);

namespace AIBridge\Tests\Unit\Content;

use AIBridge\Services\ContentQAService;
use PHPUnit\Framework\TestCase;

final class ContentQAServiceTest extends TestCase
{
    private function contract(): array
    {
        return [
            'fields' => [
                'pagetitle' => ['type' => 'string', 'required' => true, 'max_length' => 255],
                'content' => ['type' => 'html', 'required' => false],
            ],
            'seo' => [
                'title' => ['source' => 'pagetitle', 'required' => true, 'min_length' => 10, 'max_length' => 70],
                'description' => ['source' => 'description', 'required' => false, 'min_length' => 50, 'max_length' => 170],
                'h1' => ['required' => true, 'exact_count' => 1],
            ],
            'html' => ['require_single_h1' => true, 'allow_scripts' => false, 'allow_iframes' => false],
            'structured_data' => ['json_ld' => ['enabled' => true, 'validation' => 'syntax']],
        ];
    }

    public function testRejectsMissingRequiredFieldAndMultipleH1(): void
    {
        $result = (new ContentQAService())->validate([
            'content' => '<h1>A</h1><h1>B</h1>',
        ], $this->contract());

        self::assertFalse($result['valid']);
        self::assertSame(2, $result['metrics']['error_count']);
    }

    public function testRejectsForbiddenScriptAndInvalidJsonLd(): void
    {
        $result = (new ContentQAService())->validate([
            'pagetitle' => 'Valid enough page title',
            'content' => '<h1>Title</h1><script>alert(1)</script><script type="application/ld+json">{bad}</script>',
        ], $this->contract());

        self::assertFalse($result['valid']);
        self::assertGreaterThanOrEqual(2, $result['metrics']['error_count']);
    }

    public function testAcceptsValidContent(): void
    {
        $result = (new ContentQAService())->validate([
            'pagetitle' => 'A valid page title for testing',
            'description' => str_repeat('Useful description ', 4),
            'content' => '<h1>Title</h1><p>Body</p><script type="application/ld+json">{"@context":"https://schema.org","@type":"Article"}</script>',
        ], $this->contract());

        self::assertTrue($result['valid']);
        self::assertSame(0, $result['metrics']['error_count']);
    }
}
