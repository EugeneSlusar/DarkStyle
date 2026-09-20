<?php

declare(strict_types=1);

namespace Orgbox\BaseShop\Catalog;

use Bitrix\Main\Loader;
use JsonException;
use RuntimeException;

final class CatalogImportService
{
    private const SECTIONS = [
        ['code' => 'vaz-2104-2107', 'name' => 'ВАЗ 2104–2107', 'pattern' => '/2107|2105|2104/iu'],
        ['code' => 'vaz-2109-2114', 'name' => 'ВАЗ 2109–2114', 'pattern' => '/2114|2115|2109|21099/iu'],
        ['code' => 'chevrolet-niva-travel', 'name' => 'Chevrolet Niva Travel', 'pattern' => '/Niva Travel|шевроле нива/iu'],
        ['code' => 'kia-rio-hyundai-solaris', 'name' => 'Kia Rio / Hyundai Solaris', 'pattern' => '/Kia Rio|Hyundai Solaris/iu'],
        ['code' => 'lada-granta-kalina', 'name' => 'Lada Granta / Kalina', 'pattern' => '/Granta|Гранта|Kalina|Калина|Datsun/iu'],
        ['code' => 'lada-niva', 'name' => 'Lada Niva', 'pattern' => '/лада Нива|Lada Niva/iu'],
        ['code' => 'lada-priora', 'name' => 'Lada Priora', 'pattern' => '/Priora|Приора|2110|2111|2112/iu'],
        ['code' => 'lada-vesta', 'name' => 'Lada Vesta', 'pattern' => '/Vesta|Веста/iu'],
    ];

    public function __construct(private readonly int $iblockId)
    {
        if ($iblockId <= 0) {
            throw new RuntimeException('Не настроен инфоблок товаров.');
        }
        if (!Loader::includeModule('iblock')) {
            throw new RuntimeException('Модуль iblock недоступен.');
        }
    }

    /** @throws JsonException */
    public function readSource(string $sourceFile): array
    {
        if (!is_file($sourceFile)) {
            throw new RuntimeException('Файл данных каталога не найден.');
        }

        $content = (string) file_get_contents($sourceFile);
        $equalsPosition = strpos($content, '=');
        if ($equalsPosition === false) {
            throw new RuntimeException('Некорректный формат данных каталога.');
        }

        $json = rtrim(trim(substr($content, $equalsPosition + 1)), "; \t\n\r\0\x0B");
        $products = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

        if (!is_array($products)) {
            throw new RuntimeException('Каталог не содержит список товаров.');
        }

        return array_values(array_filter($products, static fn (mixed $item): bool =>
            is_array($item) && !empty($item['s']) && !empty($item['t'])
        ));
    }

    public function import(string $sourceFile, string $imagesRoot, array $defaults, bool $replaceImages, bool $deleteDemo): array
    {
        $result = ['total' => 0, 'created' => 0, 'updated' => 0, 'failed' => 0, 'deleted_demo' => 0, 'errors' => []];
        $products = $this->readSource($sourceFile);
        $result['total'] = count($products);

        if ($deleteDemo) {
            $result['deleted_demo'] = $this->deleteByArticle('DEMO-NIVA-05');
        }

        $sectionIds = [];
        foreach (self::SECTIONS as $section) {
            $sectionIds[$section['code']] = $this->ensureSection($section['code'], $section['name']);
        }

        foreach ($products as $index => $source) {
            $sku = preg_replace('/\D+/', '', (string) $source['s']);
            try {
                if ($sku === '') {
                    throw new RuntimeException('У товара отсутствует корректный артикул.');
                }

                $section = $this->resolveSection((string) $source['t']);
                $existing = $this->findByArticle($sku);
                $images = $this->findImages(
                    $imagesRoot,
                    $sku,
                    (string) ($source['e'] ?? ''),
                    max(1, (int) ($source['n'] ?? 1))
                );

                $fields = [
                    'IBLOCK_ID' => $this->iblockId,
                    'IBLOCK_SECTION_ID' => $sectionIds[$section['code']],
                    'ACTIVE' => 'Y',
                    'SORT' => 100 + ($index * 10),
                    'NAME' => $this->normalizeTitle((string) $source['t']),
                    'CODE' => 'product-' . $sku,
                    'XML_ID' => 'ORGBOX_BASESHOP_' . $sku,
                    'PREVIEW_TEXT' => 'Многоразовая съёмная тонировка для установки без клея.',
                    'DETAIL_TEXT' => 'Комплект съёмной тонировки, подготовленный под форму стекла автомобиля. Характеристики и совместимость уточняйте перед оформлением заказа.',
                ];
                $properties = [
                    'PRICE' => $this->price((string) ($source['p'] ?? '')),
                    'OLD_PRICE' => $this->price((string) ($source['o'] ?? '')),
                    'ARTICLE' => $sku,
                    'WEIGHT' => max(1, (float) ($defaults['weight'] ?? 700)),
                    'LENGTH' => max(1, (float) ($defaults['length'] ?? 80)),
                    'WIDTH' => max(1, (float) ($defaults['width'] ?? 15)),
                    'HEIGHT' => max(1, (float) ($defaults['height'] ?? 15)),
                ];

                $element = new \CIBlockElement();
                if ($existing === null) {
                    if ($images !== []) {
                        $fields['PREVIEW_PICTURE'] = \CFile::MakeFileArray($images[0]);
                    }
                    $gallery = $this->galleryValues(array_slice($images, 1));
                    if ($gallery !== []) {
                        $properties['GALLERY'] = $gallery;
                    }
                    $fields['PROPERTY_VALUES'] = $properties;
                    $elementId = $element->Add($fields);
                    if (!$elementId) {
                        throw new RuntimeException($element->LAST_ERROR ?: 'Не удалось создать товар.');
                    }
                    $result['created']++;
                    continue;
                }

                if ($replaceImages && $images !== []) {
                    $fields['PREVIEW_PICTURE'] = \CFile::MakeFileArray($images[0]);
                }
                if (!$element->Update((int) $existing['ID'], $fields)) {
                    throw new RuntimeException($element->LAST_ERROR ?: 'Не удалось обновить товар.');
                }
                if ($replaceImages) {
                    $properties['GALLERY'] = $this->galleryValues(array_slice($images, 1));
                }
                \CIBlockElement::SetPropertyValuesEx((int) $existing['ID'], $this->iblockId, $properties);
                $result['updated']++;
            } catch (\Throwable $exception) {
                $result['failed']++;
                $result['errors'][] = sprintf('Артикул %s: %s', $sku ?: 'не указан', $exception->getMessage());
            }
        }

        if (is_callable(['CIBlock', 'clearIblockTagCache'])) {
            \CIBlock::clearIblockTagCache($this->iblockId);
        }

        return $result;
    }

    private function ensureSection(string $code, string $name): int
    {
        $row = \CIBlockSection::GetList([], ['IBLOCK_ID' => $this->iblockId, '=CODE' => $code], false, ['ID'])->Fetch();
        if ($row) {
            return (int) $row['ID'];
        }

        $section = new \CIBlockSection();
        $sectionId = $section->Add([
            'IBLOCK_ID' => $this->iblockId,
            'ACTIVE' => 'Y',
            'NAME' => $name,
            'CODE' => $code,
        ]);
        if (!$sectionId) {
            throw new RuntimeException($section->LAST_ERROR ?: 'Не удалось создать раздел ' . $name . '.');
        }

        return (int) $sectionId;
    }

    private function resolveSection(string $title): array
    {
        foreach (self::SECTIONS as $section) {
            if (preg_match($section['pattern'], $title)) {
                return $section;
            }
        }

        throw new RuntimeException('Не удалось определить раздел товара.');
    }

    private function findByArticle(string $article): ?array
    {
        $row = \CIBlockElement::GetList(
            [],
            ['IBLOCK_ID' => $this->iblockId, '=PROPERTY_ARTICLE' => $article],
            false,
            ['nTopCount' => 1],
            ['ID', 'CODE']
        )->Fetch();

        return $row ?: null;
    }

    private function deleteByArticle(string $article): int
    {
        $deleted = 0;
        $rows = \CIBlockElement::GetList([], ['IBLOCK_ID' => $this->iblockId, '=PROPERTY_ARTICLE' => $article], false, false, ['ID']);
        while ($row = $rows->Fetch()) {
            if (\CIBlockElement::Delete((int) $row['ID'])) {
                $deleted++;
            }
        }

        return $deleted;
    }

    private function findImages(string $imagesRoot, string $sku, string $primaryExtension, int $count): array
    {
        $directory = rtrim($imagesRoot, '/\\') . DIRECTORY_SEPARATOR . $sku;
        if (!is_dir($directory)) {
            return [];
        }

        $images = [];
        for ($index = 1; $index <= $count; $index++) {
            $base = str_pad((string) $index, 2, '0', STR_PAD_LEFT);
            $preferred = $index === 1 && $primaryExtension !== '' ? $directory . DIRECTORY_SEPARATOR . $base . '.' . $primaryExtension : '';
            if ($preferred !== '' && is_file($preferred)) {
                $images[] = $preferred;
                continue;
            }
            $matches = glob($directory . DIRECTORY_SEPARATOR . $base . '.*') ?: [];
            if ($matches !== []) {
                sort($matches);
                $images[] = $matches[0];
            }
        }

        return $images;
    }

    private function galleryValues(array $images): array
    {
        $values = [];
        foreach ($images as $index => $image) {
            $values['n' . $index] = ['VALUE' => \CFile::MakeFileArray($image)];
        }

        return $values;
    }

    private function normalizeTitle(string $title): string
    {
        $title = preg_replace('/\s+/u', ' ', trim($title)) ?? trim($title);
        $title = preg_replace('/^тонировка\s+съемная/iu', 'Съёмная тонировка', $title) ?? $title;
        $title = str_ireplace('съемная', 'съёмная', $title);

        return mb_strtoupper(mb_substr($title, 0, 1)) . mb_substr($title, 1);
    }

    private function price(string $value): float
    {
        return (float) preg_replace('/\D+/u', '', $value);
    }
}
