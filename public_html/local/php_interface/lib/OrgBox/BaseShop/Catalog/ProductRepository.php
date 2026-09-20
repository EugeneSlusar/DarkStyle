<?php

declare(strict_types=1);

namespace OrgBox\BaseShop\Catalog;

use Bitrix\Main\Loader;
use RuntimeException;

final class ProductRepository
{
    public function __construct(private readonly int $iblockId)
    {
        if ($iblockId <= 0) {
            throw new RuntimeException('Не настроен инфоблок товаров.');
        }
        if (!Loader::includeModule('iblock')) {
            throw new RuntimeException('Модуль iblock недоступен.');
        }
    }

    public function getSections(): array
    {
        $sections = [];
        $result = \CIBlockSection::GetList(
            ['SORT' => 'ASC', 'NAME' => 'ASC'],
            ['IBLOCK_ID' => $this->iblockId, 'ACTIVE' => 'Y', 'GLOBAL_ACTIVE' => 'Y'],
            false,
            ['ID', 'NAME', 'CODE', 'DESCRIPTION', 'PICTURE', 'SECTION_PAGE_URL']
        );

        while ($section = $result->GetNext()) {
            $sections[] = [
                'ID' => (int) $section['ID'],
                'NAME' => (string) $section['NAME'],
                'CODE' => (string) $section['CODE'],
                'DESCRIPTION' => trim(strip_tags((string) $section['DESCRIPTION'])),
                'PICTURE' => $this->filePath((int) $section['PICTURE']),
                'URL' => '/catalog/' . rawurlencode((string) $section['CODE']) . '/',
            ];
        }

        return $sections;
    }

    public function getProducts(?string $sectionCode = null, int $limit = 0): array
    {
        $filter = ['IBLOCK_ID' => $this->iblockId, 'ACTIVE' => 'Y', 'ACTIVE_DATE' => 'Y'];
        if ($sectionCode !== null && $sectionCode !== '') {
            $filter['SECTION_CODE'] = $sectionCode;
            $filter['INCLUDE_SUBSECTIONS'] = 'Y';
        }

        $navigation = $limit > 0 ? ['nTopCount' => $limit] : false;
        $result = \CIBlockElement::GetList(
            ['SORT' => 'ASC', 'ID' => 'DESC'],
            $filter,
            false,
            $navigation,
            ['ID', 'IBLOCK_ID', 'IBLOCK_SECTION_ID', 'NAME', 'CODE', 'PREVIEW_TEXT', 'DETAIL_TEXT', 'PREVIEW_PICTURE', 'DETAIL_PICTURE']
        );

        $items = [];
        while ($element = $result->GetNextElement()) {
            $items[] = $this->normalize($element->GetFields(), $element->GetProperties());
        }

        return $items;
    }

    public function getProduct(string $sectionCode, string $productCode): ?array
    {
        $result = \CIBlockElement::GetList(
            [],
            [
                'IBLOCK_ID' => $this->iblockId,
                'ACTIVE' => 'Y',
                'ACTIVE_DATE' => 'Y',
                'SECTION_CODE' => $sectionCode,
                'CODE' => $productCode,
            ],
            false,
            ['nTopCount' => 1],
            ['ID', 'IBLOCK_ID', 'IBLOCK_SECTION_ID', 'NAME', 'CODE', 'PREVIEW_TEXT', 'DETAIL_TEXT', 'PREVIEW_PICTURE', 'DETAIL_PICTURE']
        );
        $element = $result->GetNextElement();

        return $element ? $this->normalize($element->GetFields(), $element->GetProperties()) : null;
    }

    public function getProductById(int $productId): ?array
    {
        $result = \CIBlockElement::GetList(
            [],
            ['IBLOCK_ID' => $this->iblockId, 'ID' => $productId, 'ACTIVE' => 'Y', 'ACTIVE_DATE' => 'Y'],
            false,
            ['nTopCount' => 1],
            ['ID', 'IBLOCK_ID', 'IBLOCK_SECTION_ID', 'NAME', 'CODE', 'PREVIEW_TEXT', 'DETAIL_TEXT', 'PREVIEW_PICTURE', 'DETAIL_PICTURE']
        );
        $element = $result->GetNextElement();

        return $element ? $this->normalize($element->GetFields(), $element->GetProperties()) : null;
    }

    private function normalize(array $fields, array $properties): array
    {
        $section = $this->getSection((int) $fields['IBLOCK_SECTION_ID']);
        $gallery = [];
        foreach ((array) ($properties['GALLERY']['VALUE'] ?? []) as $fileId) {
            $path = $this->filePath((int) $fileId);
            if ($path !== '') {
                $gallery[] = $path;
            }
        }

        $primaryImage = $this->filePath((int) ($fields['DETAIL_PICTURE'] ?: $fields['PREVIEW_PICTURE']));
        if ($primaryImage !== '') {
            array_unshift($gallery, $primaryImage);
        }
        $gallery = array_values(array_unique($gallery));

        return [
            'ID' => (int) $fields['ID'],
            'NAME' => (string) $fields['NAME'],
            'CODE' => (string) $fields['CODE'],
            'SECTION' => $section,
            'PREVIEW_TEXT' => trim(strip_tags((string) $fields['PREVIEW_TEXT'])),
            'DETAIL_TEXT' => trim(strip_tags((string) $fields['DETAIL_TEXT'])),
            'IMAGE' => $primaryImage,
            'GALLERY' => $gallery,
            'PRICE' => $this->number($properties, 'PRICE'),
            'OLD_PRICE' => $this->number($properties, 'OLD_PRICE'),
            'ARTICLE' => $this->string($properties, 'ARTICLE'),
            'WEIGHT' => $this->number($properties, 'WEIGHT'),
            'LENGTH' => $this->number($properties, 'LENGTH'),
            'WIDTH' => $this->number($properties, 'WIDTH'),
            'HEIGHT' => $this->number($properties, 'HEIGHT'),
            'BADGE' => $this->string($properties, 'BADGE'),
            'URL' => '/catalog/' . rawurlencode($section['CODE']) . '/' . rawurlencode((string) $fields['CODE']) . '/',
        ];
    }

    private function getSection(int $sectionId): array
    {
        static $cache = [];
        if (!isset($cache[$sectionId])) {
            $row = $sectionId > 0 ? \CIBlockSection::GetByID($sectionId)->GetNext() : false;
            $cache[$sectionId] = [
                'ID' => $sectionId,
                'NAME' => $row ? (string) $row['NAME'] : '',
                'CODE' => $row ? (string) $row['CODE'] : 'all',
            ];
        }

        return $cache[$sectionId];
    }

    private function filePath(int $fileId): string
    {
        return $fileId > 0 ? (string) \CFile::GetPath($fileId) : '';
    }

    private function number(array $properties, string $code): float
    {
        return (float) str_replace(',', '.', (string) ($properties[$code]['VALUE'] ?? 0));
    }

    private function string(array $properties, string $code): string
    {
        return trim((string) ($properties[$code]['VALUE'] ?? ''));
    }
}
