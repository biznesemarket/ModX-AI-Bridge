<?php

declare(strict_types=1);

namespace AIBridge\Inspectors;

use MODX\Revolution\modX;

final class SiteInspector
{
    public function __construct(private readonly modX $modx)
    {
    }

    public function inspect(): array
    {
        $config = $this->modx->config;
        $keys = [
            'site_name', 'site_url', 'base_url', 'base_path', 'cultureKey',
            'friendly_urls', 'friendly_alias_urls', 'use_alias_path', 'container_suffix',
            'cache_resource', 'cache_resource_expires', 'default_template', 'error_page',
            'unauthorized_page', 'unauthorized_group', 'site_start', 'site_status',
        ];
        $data = [];
        foreach ($keys as $key) {
            if (array_key_exists($key, $config)) $data[$key] = $config[$key];
        }
        return [
            'identity' => [
                'site_name' => $data['site_name'] ?? null,
                'site_url' => $data['site_url'] ?? null,
                'culture_key' => $data['cultureKey'] ?? null,
            ],
            'routing' => array_intersect_key($data, array_flip([
                'friendly_urls', 'friendly_alias_urls', 'use_alias_path', 'container_suffix',
            ])),
            'runtime' => [
                'site_status' => $data['site_status'] ?? null,
                'default_template' => isset($data['default_template']) ? (int) $data['default_template'] : null,
                'site_start' => isset($data['site_start']) ? (int) $data['site_start'] : null,
                'error_page' => isset($data['error_page']) ? (int) $data['error_page'] : null,
                'unauthorized_page' => isset($data['unauthorized_page']) ? (int) $data['unauthorized_page'] : null,
            ],
        ];
    }
}
