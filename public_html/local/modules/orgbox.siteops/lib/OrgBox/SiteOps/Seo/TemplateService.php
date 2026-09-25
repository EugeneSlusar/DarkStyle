<?php

declare(strict_types=1);

namespace OrgBox\SiteOps\Seo;

use Bitrix\Main\Loader;
use RuntimeException;

final class TemplateService
{
    public const TEMPLATE_KEYS = [
        'SECTION_META_TITLE', 'SECTION_META_DESCRIPTION', 'SECTION_META_KEYWORDS', 'SECTION_PAGE_TITLE',
        'ELEMENT_META_TITLE', 'ELEMENT_META_DESCRIPTION', 'ELEMENT_META_KEYWORDS', 'ELEMENT_PAGE_TITLE',
    ];

    public function __construct()
    {
        if (!Loader::includeModule('iblock')) {
            throw new RuntimeException('Модуль инфоблоков недоступен.');
        }
    }

    public function getProductTemplates(): array
    {
        $iblock = $this->getProductIblock();
        $templates = (new \Bitrix\Iblock\InheritedProperty\IblockTemplates((int) $iblock['ID']))->findTemplates();

        return array_intersect_key($templates, array_flip(self::TEMPLATE_KEYS));
    }

    public function updateProductTemplates(array $templates): array
    {
        $iblock = $this->getProductIblock();
        $templateManager = new \Bitrix\Iblock\InheritedProperty\IblockTemplates((int) $iblock['ID']);
        $current = $templateManager->findTemplates();
        foreach ($templates as $key => $value) {
            if (!in_array($key, self::TEMPLATE_KEYS, true) || !is_string($value)) {
                throw new RuntimeException('Недопустимое SEO-поле.');
            }
            if (mb_strlen($value) > 500) {
                throw new RuntimeException('SEO-шаблон не может быть длиннее 500 символов.');
            }
            $current[$key] = trim($value);
        }

        $templateManager->set($current);
        (new \Bitrix\Iblock\InheritedProperty\IblockValues((int) $iblock['ID']))->clearValues();
        \CIBlock::clearIblockTagCache((int) $iblock['ID']);

        return $this->getProductTemplates();
    }

    public function defaults(): array
    {
        return [
            'SECTION_META_TITLE' => 'Съёмная тонировка для {=this.NAME} — купить | Тёмный стиль',
            'SECTION_META_DESCRIPTION' => 'Купить съёмную тонировку для {=this.NAME}. Подберите комплект по светопропускаемости и оформите заказ с доставкой.',
            'SECTION_META_KEYWORDS' => '',
            'SECTION_PAGE_TITLE' => 'Съёмная тонировка для {=this.NAME}',
            'ELEMENT_META_TITLE' => '{=this.NAME} — купить | Тёмный стиль',
            'ELEMENT_META_DESCRIPTION' => '{=this.NAME}. Съёмная силиконовая тонировка для автомобиля. Заказ с доставкой.',
            'ELEMENT_META_KEYWORDS' => '',
            'ELEMENT_PAGE_TITLE' => '{=this.NAME}',
        ];
    }

    private function getProductIblock(): array
    {
        $result = \CIBlock::GetList([], ['TYPE' => 'orgbox_baseshop', 'CODE' => 'orgbox_baseshop_products']);
        $iblock = $result->GetNext();
        if (!$iblock) {
            throw new RuntimeException('Инфоблок товаров orgBox: BaseShop не найден.');
        }

        return $iblock;
    }

}
