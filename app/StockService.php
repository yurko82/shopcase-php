<?php
declare(strict_types=1);

namespace App;

/**
 * StockService
 * Управління залишками та моделями телефонів
 */
class StockService {

    public static function getRawStock(): array {
        $api = new EndorPhone();
        return $api->getAllStock();
    }

    public static function syncStock(?array $stockData = null): int {
        if ($stockData === null) {
            $api = new EndorPhone();
            $stockData = $api->getAllStock(true);
        }
        return count($stockData);
    }

    public static function getModelsForJs(): array {
        $stock = self::getRawStock();
        $popularBrands = ['Apple', 'Samsung', 'Xiaomi', 'Google', 'OnePlus', 'Realme', 'Poco', 'Huawei', 'Honor'];

        $list = [];
        foreach ($stock as $item) {
            $id = (string)($item['id'] ?? '');
            $name = trim((string)($item['name'] ?? ''));
            if (!$id || !$name) continue;

            $brand = explode(' ', $name)[0] ?? 'Інше';

            $mats = [
                'mat_u'  => !empty($item['mat_u']),
                'mat_b'  => !empty($item['mat_b']),
                'mat_sp' => !empty($item['mat_sp']),
                'mat_pc' => !empty($item['mat_pc']),
                'mat_pm' => !empty($item['mat_pm']),
                'mat_c'  => !empty($item['mat_c']),
                'mat_m'  => !empty($item['mat_m']),
                'mat_t'  => !empty($item['mat_t']),
            ];

            $availCount = count(array_filter($mats));
            if ($availCount === 0) continue; // показуємо тільки моделі з наявністю

            $list[] = [
                'id'    => $id,
                'name'  => $name,
                'brand' => $brand,
                'avail' => $availCount,
                'mats'  => $mats,
            ];
        }

        // Сортуємо: спочатку популярні бренди
        usort($list, function($a, $b) use ($popularBrands) {
            $posA = array_search($a['brand'], $popularBrands);
            $posB = array_search($b['brand'], $popularBrands);
            $rankA = $posA !== false ? $posA : 999;
            $rankB = $posB !== false ? $posB : 999;

            if ($rankA !== $rankB) return $rankA <=> $rankB;
            return strcmp($a['name'], $b['name']);
        });

        return $list;
    }

    public static function getModelStock(string $modelId): ?array {
        $stock = self::getRawStock();
        foreach ($stock as $item) {
            if ((string)$item['id'] === $modelId) {
                $mats = [];
                $prices = Config::getMaterialPrices();
                foreach (Config::MATERIAL_LABELS as $key => $label) {
                    $mats[$key] = [
                        'key'       => $key,
                        'code'      => Config::MATERIAL_CODES[$key],
                        'label'     => $label,
                        'price'     => $prices[$key] ?? 199,
                        'available' => !empty($item[$key]),
                    ];
                }
                return [
                    'id'    => (string)$item['id'],
                    'name'  => $item['name'],
                    'brand' => explode(' ', $item['name'])[0] ?? '',
                    'mats'  => $mats,
                ];
            }
        }
        return null;
    }
}
