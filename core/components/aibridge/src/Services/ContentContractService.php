<?php

declare(strict_types=1);

namespace AIBridge\Services;

use AIBridge\Content\ResourceContentContract;

final class ContentContractService
{
    /**
     * Builds a machine-readable content contract from discovered site data.
     * Requirements are conservative: fields are not made mandatory merely because
     * they are common on MODX sites. Explicit policy can tighten them later.
     */
    public function resource(array $siteSchema, ?int $templateId = null): ResourceContentContract
    {
        $templates = $siteSchema['templates'] ?? [];
        $tvs = $siteSchema['template_variables'] ?? [];
        $template = null;
        foreach ($templates as $candidate) {
            if ($templateId !== null && (int) ($candidate['id'] ?? 0) === $templateId) {
                $template = $candidate;
                break;
            }
        }

        $fields = [
            'pagetitle' => ['type' => 'string', 'required' => true, 'max_length' => 255],
            'longtitle' => ['type' => 'string', 'required' => false, 'max_length' => 255],
            'description' => ['type' => 'string', 'required' => false, 'max_length' => 1000],
            'introtext' => ['type' => 'string', 'required' => false, 'max_length' => 1000],
            'content' => ['type' => 'html', 'required' => false],
            'alias' => ['type' => 'string', 'required' => false, 'max_length' => 255],
            'template' => ['type' => 'integer', 'required' => $templateId !== null],
        ];

        $templateTvIds = $this->templateTvIds($templateId, $tvs);
        foreach ($tvs as $tv) {
            $id = (int) ($tv['id'] ?? 0);
            if ($templateId !== null && $templateTvIds !== [] && !in_array($id, $templateTvIds, true)) {
                continue;
            }
            $name = (string) ($tv['name'] ?? '');
            if ($name === '') {
                continue;
            }
            $fields['tv:' . $name] = [
                'type' => $this->mapTvType((string) ($tv['type'] ?? 'text')),
                'required' => false,
                'tv_id' => $id,
                'caption' => (string) ($tv['caption'] ?? ''),
            ];
        }

        return new ResourceContentContract([
            'contract' => 'modx-ai-bridge/content',
            'version' => '1.0',
            'type' => 'resource',
            'template' => $template,
            'fields' => $fields,
            'seo' => [
                'title' => ['source' => 'pagetitle', 'required' => true, 'min_length' => 10, 'max_length' => 70],
                'description' => ['source' => 'description', 'required' => false, 'min_length' => 50, 'max_length' => 170],
                'h1' => ['required' => true, 'exact_count' => 1],
            ],
            'html' => [
                'require_single_h1' => true,
                'allow_inline_styles' => true,
                'allow_scripts' => false,
                'allow_iframes' => false,
            ],
            'structured_data' => [
                'json_ld' => ['enabled' => true, 'validation' => 'syntax'],
            ],
        ]);
    }

    private function templateTvIds(?int $templateId, array $tvs): array
    {
        // Discovery does not yet expose template-TV relations. Returning an empty
        // set means all discovered TVs are described; future relation discovery can
        // narrow this without changing the contract format.
        return [];
    }

    private function mapTvType(string $type): string
    {
        return match (strtolower($type)) {
            'image', 'file' => 'asset',
            'date' => 'datetime',
            'number' => 'number',
            'listbox', 'list-multiple' => 'enum',
            'richtext', 'textarea' => 'text',
            default => 'string',
        };
    }
}
