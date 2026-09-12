<?php

declare(strict_types=1);

namespace AIBridge\Services;

use AIBridge\Contracts\SiteContract;

final class SiteFingerprintService
{
    public function fingerprint(SiteContract|array $input): array
    {
        $data = $input instanceof SiteContract ? $input->toArray() : $input;
        $normalized = $this->normalize($data);
        $json = json_encode($normalized, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        return [
            'algorithm' => 'sha256',
            'fingerprint' => hash('sha256', $json),
            'normalized' => $normalized,
        ];
    }

    private function normalize(mixed $value): mixed
    {
        if (!is_array($value)) return $value;
        unset($value['generated_at']);
        foreach ($value as $key => $item) $value[$key] = $this->normalize($item);
        if (array_is_list($value)) return $value;
        ksort($value);
        return $value;
    }
}
