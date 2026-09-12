<?php

declare(strict_types=1);

namespace AIBridge\Services;

final class ContentQAService
{
    public function validate(array $content = [], array $contract = []): array
    {
        $errors = [];
        $warnings = [];
        $missingRequired = [];
        $fields = $contract['fields'] ?? [];

        foreach ($fields as $name => $rule) {
            if (!is_array($rule)) continue;
            if (($rule['required'] ?? false) && !$this->hasValue($content[$name] ?? null)) {
                $errors[] = $this->issue('required', $name, 'Required field is missing.');
                $missingRequired[] = (string) $name;
            }
            if (isset($content[$name], $rule['max_length']) && is_string($content[$name]) && mb_strlen($content[$name]) > (int) $rule['max_length']) {
                $errors[] = $this->issue('max_length', $name, 'Value exceeds the contract maximum length.');
            }
        }

        $seo = $contract['seo'] ?? [];
        $titleSource = (string) ($seo['title']['source'] ?? 'pagetitle');
        $title = (string) ($content[$titleSource] ?? '');
        if (($seo['title']['required'] ?? false) && $title === '') {
            if (!in_array($titleSource, $missingRequired, true)) {
                $errors[] = $this->issue('seo_title_required', 'pagetitle', 'SEO title source is empty.');
            }
        } elseif ($title !== '') {
            $this->lengthRule($title, $seo['title'] ?? [], 'seo_title', $errors, $warnings);
        }

        $descriptionSource = (string) ($seo['description']['source'] ?? 'description');
        $description = (string) ($content[$descriptionSource] ?? '');
        if (($seo['description']['required'] ?? false) && $description === '') {
            if (!in_array($descriptionSource, $missingRequired, true)) {
                $errors[] = $this->issue('seo_description_required', $descriptionSource, 'SEO description source is empty.');
            }
        } elseif ($description !== '') {
            $this->lengthRule($description, $seo['description'] ?? [], 'seo_description', $errors, $warnings);
        }

        $html = (string) ($content['content'] ?? '');
        if ($html !== '') {
            $this->validateHtml($html, $contract['html'] ?? [], $errors, $warnings);
            $this->validateJsonLd($html, $contract['structured_data']['json_ld'] ?? [], $errors, $warnings);
        } elseif (($seo['h1']['required'] ?? false)) {
            $warnings[] = $this->issue('h1_not_checked', 'content', 'H1 count could not be checked because content is empty.');
        }

        return [
            'valid' => $errors === [],
            'errors' => $errors,
            'warnings' => $warnings,
            'metrics' => [
                'error_count' => count($errors),
                'warning_count' => count($warnings),
            ],
        ];
    }

    private function lengthRule(string $value, array $rule, string $code, array &$errors, array &$warnings): void
    {
        $length = mb_strlen(trim($value));
        if (isset($rule['min_length']) && $length < (int) $rule['min_length']) {
            $warnings[] = $this->issue($code . '_short', $code, 'Value is shorter than the recommended minimum.', ['length' => $length]);
        }
        if (isset($rule['max_length']) && $length > (int) $rule['max_length']) {
            $warnings[] = $this->issue($code . '_long', $code, 'Value is longer than the recommended maximum.', ['length' => $length]);
        }
    }

    private function validateHtml(string $html, array $rules, array &$errors, array &$warnings): void
    {
        $htmlWithoutJsonLd = preg_replace('/<script\b[^>]*type=["\']application\/ld\+json["\'][^>]*>.*?<\/script>/is', '', $html) ?? $html;
        if (($rules['allow_scripts'] ?? false) === false && preg_match('/<script\b/i', $htmlWithoutJsonLd)) {
            $errors[] = $this->issue('forbidden_tag', 'content', 'Script tags are not allowed by the content contract.');
        }
        if (($rules['allow_iframes'] ?? false) === false && preg_match('/<iframe\b/i', $html)) {
            $errors[] = $this->issue('forbidden_tag', 'content', 'Iframe tags are not allowed by the content contract.');
        }

        $h1Count = preg_match_all('/<h1\b[^>]*>/i', $html);
        if (($rules['require_single_h1'] ?? false) && $h1Count !== 1) {
            $errors[] = $this->issue('h1_count', 'content', 'Content must contain exactly one H1.', ['count' => $h1Count]);
        }

        if (preg_match('/href\s*=\s*["\']\s*["\']/i', $html)) {
            $warnings[] = $this->issue('empty_link', 'content', 'An empty href was detected.');
        }
    }

    private function validateJsonLd(string $html, array $rules, array &$errors, array &$warnings): void
    {
        if (($rules['enabled'] ?? false) !== true || ($rules['validation'] ?? '') !== 'syntax') return;
        if (!preg_match_all('/<script\b[^>]*type=["\']application\/ld\+json["\'][^>]*>(.*?)<\/script>/is', $html, $matches)) {
            $warnings[] = $this->issue('jsonld_missing', 'content', 'No JSON-LD block was detected.');
            return;
        }
        foreach ($matches[1] as $json) {
            json_decode(trim(html_entity_decode($json)), true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $errors[] = $this->issue('jsonld_invalid', 'content', 'A JSON-LD block contains invalid JSON.', ['json_error' => json_last_error_msg()]);
            }
        }
    }

    private function hasValue(mixed $value): bool
    {
        return $value !== null && (!is_string($value) || trim($value) !== '');
    }

    private function issue(string $code, string $field, string $message, array $meta = []): array
    {
        return ['code' => $code, 'field' => $field, 'message' => $message, 'meta' => $meta];
    }
}
