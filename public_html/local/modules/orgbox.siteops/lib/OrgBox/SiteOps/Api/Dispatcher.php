<?php

declare(strict_types=1);

namespace OrgBox\SiteOps\Api;

use OrgBox\SiteOps\Seo\TemplateService;
use RuntimeException;

final class Dispatcher
{
    public function handle(array $request): array
    {
        $operation = (string) ($request['operation'] ?? '');
        $seo = new TemplateService();

        return match ($operation) {
            'seo.templates.get' => ['templates' => $seo->getProductTemplates(), 'defaults' => $seo->defaults()],
            'seo.templates.update' => ['templates' => $seo->updateProductTemplates((array) ($request['templates'] ?? []))],
            default => throw new RuntimeException('Неизвестная операция.'),
        };
    }
}
