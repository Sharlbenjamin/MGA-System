<?php

namespace App\Services\Communications;

use App\Support\Communications\CommunicationContext;

class CommunicationTemplateRenderer
{
    /**
     * Render a template with safe {{ dot.notation }} placeholders only.
     */
    public function render(?string $template, CommunicationContext $context): string
    {
        if ($template === null || trim($template) === '') {
            return '';
        }

        $data = $context->toTemplateData();

        return preg_replace_callback('/\{\{\s*([a-zA-Z0-9_.]+)\s*\}\}/', function (array $matches) use ($data): string {
            $value = data_get($data, $matches[1]);

            if (is_array($value)) {
                return '';
            }

            return (string) ($value ?? '');
        }, $template) ?? $template;
    }
}
