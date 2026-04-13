<?php
/**
 * catalog.php
 * Завантаження та парсинг CSV з EndorPhone
 * CSV оновлюється щодня — кешуємо на 12 годин
 */

class Catalog {

    const CSV_URL   = 'https://export.endorphone.com.ua/partners/30095/GvMRmjpLNSrgdHML0VEbXvoxoUIGqimwS_opencart.csv';
    const CACHE_TTL = 43200; // 12 годин
    const CACHE_FILE = 'catalog.json';

    /* ══════════════════════════════
       ЗАВАНТАЖИТИ ВСІ ТОВАРИ
    ══════════════════════════════ */

    /**
     * Повертає масив товарів (з кешу або свіжий CSV)
     */
    public static function getProducts(): array {
        $cacheFile = CACHE_DIR . self::CACHE_FILE;

        // Перевіряємо кеш
        if (
            file_exists($cacheFile) &&
            (time() - filemtime($cacheFile)) < self::CACHE_TTL
        ) {
            $data = json_decode(file_get_contents($cacheFile), true);
            if (!empty($data)) return $data;
        }

        // Завантажуємо свіжий CSV
        $products = self::fetchAndParse();

        if (!empty($products)) {
            if (!is_dir(CACHE_DIR)) mkdir(CACHE_DIR, 0755, true);
            file_put_contents($cacheFile, json_encode($products, JSON_UNESCAPED_UNICODE));
        }

        return $products;
    }

    /* ══════════════════════════════
       ЗАВАНТАЖИТИ ТА ПАРСИТИ CSV
    ══════════════════════════════ */

    private static function fetchAndParse(): array {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => self::CSV_URL,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_USERAGENT      => 'ShopCase/1.0',
        ]);

        $raw      = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error    = curl_error($ch);
        curl_close($ch);

        if ($error || $httpCode !== 200 || empty($raw)) {
            error_log('[Catalog] CSV fetch error: ' . ($error ?: "HTTP $httpCode"));
            return [];
        }

        return self::parseCsv($raw);
    }

    /* ══════════════════════════════
       ПАРСИНГ CSV
    ══════════════════════════════ */

    private static function parseCsv(string $raw): array {
        // BOM-фікс
        $raw = preg_replace('/^\xEF\xBB\xBF/', '', $raw);

        $lines    = explode("\n", $raw);
        $headers  = str_getcsv(array_shift($lines), ';');
        $products = [];

        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) continue;

            $cols = str_getcsv($line, ';');

            // Вирівнюємо кількість колонок
            while (count($cols) < count($headers)) {
                $cols[] = '';
            }

            $row = array_combine($headers, array_slice($cols, 0, count($headers)));
            if (empty($row['Артикул'])) continue;

            $products[] = [
                'code'       => trim($row['Артикул']),
                'model'      => trim($row['Модель']),
                'title'      => trim($row['Назва товару']),
                'price'      => (int)$row['Ціна'],
                'image'      => trim($row['Фото']),
                'category'   => trim($row['Категорія дизайну']),
                'design'     => trim($row['Назва дизайну']),
                'material'   => trim($row['Матеріал']),
                'tag'        => trim($row['Тег']),
                'in_stock'   => (int)$row['Залишок'] > 0,
                'top'        => !empty(trim($row['Топ продажів дизайнів'])),
                'phone_model'=> trim($row['Модель телефону']),
                'brand'      => trim($row['Виробник']),
            ];
        }

        return $products;
    }

    /* ══════════════════════════════
       ВИБІРКИ
    ══════════════════════════════ */

    /**
     * Унікальні категорії дизайну з кількістю
     */
    public static function getCategories(array $products): array {
        $cats = [];
        foreach ($products as $p) {
            $cat = $p['category'];
            if (!$cat) continue;
            if (!isset($cats[$cat])) $cats[$cat] = 0;
            $cats[$cat]++;
        }
        arsort($cats);
        return $cats;
    }

    /**
     * Унікальні дизайни (по назві) з одним фото-прикладом
     */
    public static function getUniqueDesigns(array $products, string $category = ''): array {
        $designs = [];

        foreach ($products as $p) {
            if ($category && $p['category'] !== $category) continue;
            if (!$p['design'] || !$p['image']) continue;

            $key = $p['design'];
            if (!isset($designs[$key])) {
                $designs[$key] = [
                    'design'   => $p['design'],
                    'category' => $p['category'],
                    'image'    => $p['image'],
                    'price'    => $p['price'],
                    'count'    => 0,
                    'top'      => $p['top'],
                ];
            }
            $designs[$key]['count']++;
        }

        return array_values($designs);
    }

    /**
     * Випадкова добірка дизайнів для секції на головній
     * @param int $limit — кількість дизайнів
     * @param string $category — фільтр по категорії ('' = всі)
     */
    public static function getRandomDesigns(array $products, int $limit = 12, string $category = ''): array {
        $designs = self::getUniqueDesigns($products, $category);

        // Спочатку топові, потім решта
        $top    = array_filter($designs, fn($d) => $d['top']);
        $others = array_filter($designs, fn($d) => !$d['top']);

        shuffle($top);
        shuffle($others);

        $merged = array_merge(array_values($top), array_values($others));

        return array_slice($merged, 0, $limit);
    }

    /**
     * Товари конкретного дизайну для конкретної моделі телефону
     * Використовується в модалці замовлення
     */
    public static function getDesignForModel(array $products, string $design, string $modelId): ?array {
        foreach ($products as $p) {
            // modelId — це id з EndorPhone stock API
            // в CSV є 'code' типу '825u-433' де 433 = id моделі
            $parts = explode('-', $p['code']);
            $csvModelId = end($parts);

            if ($p['design'] === $design && $csvModelId === $modelId) {
                return $p;
            }
        }
        return null;
    }

    /**
     * Знайти товари по назві дизайну
     */
    public static function findByDesign(array $products, string $design): array {
        return array_values(array_filter(
            $products,
            fn($p) => $p['design'] === $design
        ));
    }

    /**
     * Примусово оновити кеш (для cron)
     */
    public static function refreshCache(): bool {
        $products = self::fetchAndParse();

        if (empty($products)) return false;

        if (!is_dir(CACHE_DIR)) mkdir(CACHE_DIR, 0755, true);
        file_put_contents(
            CACHE_DIR . self::CACHE_FILE,
            json_encode($products, JSON_UNESCAPED_UNICODE)
        );

        return true;
    }

    /**
     * Інфо про кеш
     */
    public static function getCacheInfo(): array {
        $file = CACHE_DIR . self::CACHE_FILE;
        if (!file_exists($file)) {
            return ['exists' => false];
        }

        $age  = time() - filemtime($file);
        $data = json_decode(file_get_contents($file), true);

        return [
            'exists'   => true,
            'age_min'  => round($age / 60),
            'count'    => count($data ?? []),
            'fresh'    => $age < self::CACHE_TTL,
            'next_update' => date('H:i', filemtime($file) + self::CACHE_TTL),
        ];
    }
}
