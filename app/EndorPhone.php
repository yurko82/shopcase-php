<?php
declare(strict_types=1);

namespace App;

/**
 * EndorPhone
 * API клієнт для інтеграції з сервісом дропшипінгу EndorPhone
 */
class EndorPhone {
    private string $apiKey;
    private string $baseUrl;

    public function __construct() {
        $this->apiKey = Config::getApiKey();
        $this->baseUrl = Config::getBaseUrl();
    }

    /**
     * cURL POST запит до EndorPhone API
     */
    private function post(string $endpoint, array $payload): array {
        $url = $this->baseUrl . '/' . $endpoint;
        $body = json_encode($payload);

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $body,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Accept: application/json',
                'Content-Length: ' . strlen($body),
            ],
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError) {
            error_log('[EndorPhone] cURL error: ' . $curlError);
            return ['success' => false, 'error' => 'cURL: ' . $curlError];
        }

        if ($httpCode !== 200) {
            error_log('[EndorPhone] HTTP ' . $httpCode . ' for ' . $endpoint);
            return ['success' => false, 'error' => 'HTTP ' . $httpCode];
        }

        $response = preg_replace('/^ï»¿/', '', trim((string)$response));
        $data = json_decode($response, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log('[EndorPhone] JSON error: ' . json_last_error_msg());
            return ['success' => false, 'error' => 'JSON decode error'];
        }

        return $data;
    }

    /**
     * Отримати весь модельний ряд із залишками матеріалів
     */
    public function getAllStock(bool $forceRefresh = false): array {
        $cacheFile = Config::getCacheDir() . 'stock.json';

        if (!$forceRefresh && file_exists($cacheFile) && (time() - filemtime($cacheFile)) < 300) {
            $cached = json_decode(file_get_contents($cacheFile), true);
            if (!empty($cached)) return $cached;
        }

        if (Config::isMock()) {
            return $this->getMockStock();
        }

        $data = $this->post('stock', [
            'apikey'       => $this->apiKey,
            'calledMethod' => 'get',
        ]);

        if (!empty($data['success']) && !empty($data['response'])) {
            if (!is_dir(Config::getCacheDir())) {
                mkdir(Config::getCacheDir(), 0755, true);
            }
            file_put_contents($cacheFile, json_encode($data['response'], JSON_UNESCAPED_UNICODE));
            return $data['response'];
        }

        if (file_exists($cacheFile)) {
            $cached = json_decode(file_get_contents($cacheFile), true);
            if (!empty($cached)) return $cached;
        }

        // Запасний варіант: якщо кеш відсутній, беремо з bundled файлу
        $bundledFile = dirname(__DIR__) . '/data/cache/stock.json';
        if (file_exists($bundledFile)) {
            $cached = json_decode(file_get_contents($bundledFile), true);
            if (!empty($cached)) return $cached;
        }

        return $this->getMockStock();
    }

    /**
     * Створити замовлення в EndorPhone
     */
    public function createOrder(array $orderData): array {
        if (Config::isMock()) {
            return ['success' => true, 'id' => rand(100000, 999999)];
        }

        $data = $this->post('orders', [
            'apikey'       => $this->apiKey,
            'calledMethod' => 'post',
            'properties'   => $orderData,
        ]);

        if (!empty($data['success']) && !empty($data['response']['id'])) {
            return ['success' => true, 'id' => (int)$data['response']['id']];
        }

        return ['success' => false, 'error' => $data['error'] ?? 'Невідома помилка EndorPhone'];
    }

    /**
     * Отримати статуси замовлень за ID (/1.0/orders get)
     */
    public function getOrdersStatus(array $ids): array {
        if (empty($ids)) return [];

        $data = $this->post('orders', [
            'apikey'       => $this->apiKey,
            'calledMethod' => 'get',
            'ids'          => array_map('strval', $ids),
        ]);

        return $data['response'] ?? [];
    }

    /**
     * Отримати довідник статусів замовлень (/1.0/getinfo)
     */
    public function getOrderStatuses(): array {
        $data = $this->post('getinfo', [
            'apikey'       => $this->apiKey,
            'calledMethod' => 'get',
            'option'       => 'orderStatus',
        ]);

        return $data['response'] ?? [];
    }

    /**
     * Отримати детальний список замовлень з ТТН (/1.0/getorderslist)
     * Підтримує фільтрацію за: ids, датами stdate/endate (ДД.ММ.РРРР), або статусом state
     */
    public function getOrdersList(array $ids = [], string $stDate = '', string $endDate = '', string $state = ''): array {
        $payload = [
            'apikey'       => $this->apiKey,
            'calledMethod' => 'get',
        ];

        if (!empty($ids)) {
            $payload['ids'] = array_map('strval', $ids);
        } elseif ($stDate !== '' && $endDate !== '') {
            $payload['stdate'] = $stDate;
            $payload['endate'] = $endDate;
        }

        if ($state !== '') {
            $payload['state'] = $state;
        }

        $data = $this->post('getorderslist', $payload);
        return $data['response'] ?? [];
    }

    /**
     * Отримати інформацію про виплати (/1.0/payouts)
     */
    public function getPayouts(string $stDate, string $endDate): array {
        $data = $this->post('payouts', [
            'apikey'       => $this->apiKey,
            'calledMethod' => 'get',
            'stdate'       => $stDate,
            'endate'       => $endDate,
        ]);

        return $data['response'] ?? [];
    }

    /**
     * Отримати інформацію про транзакції / рух коштів (/1.0/statements)
     */
    public function getStatements(string $stDate, string $endDate): array {
        $data = $this->post('statements', [
            'apikey'       => $this->apiKey,
            'calledMethod' => 'get',
            'stdate'       => $stDate,
            'endate'       => $endDate,
        ]);

        return $data['response'] ?? [];
    }

    /**
     * Отримати наявність матеріалів для конкретних артикулів товарів (/1.0/stock)
     */
    public function getStockByProducts(array $productCodes): array {
        if (empty($productCodes)) return [];

        $products = array_map(fn($c) => ['code' => (string)$c], $productCodes);
        $data = $this->post('stock', [
            'apikey'       => $this->apiKey,
            'calledMethod' => 'get',
            'properties'   => ['products' => $products],
        ]);

        return $data['response'] ?? [];
    }

    /**
     * Транслітерація українських літер для API (#1001)
     */
    public static function transliterate(string $str): string {
        $map = [
            'а'=>'a',  'б'=>'b',  'в'=>'v',  'г'=>'g',  'д'=>'d',
            'е'=>'e',  'є'=>'ye', 'ж'=>'zh', 'з'=>'z',  'и'=>'i',
            'і'=>'i',  'ї'=>'yi', 'й'=>'y',  'к'=>'k',  'л'=>'l',
            'м'=>'m',  'н'=>'n',  'о'=>'o',  'п'=>'p',  'р'=>'r',
            'с'=>'s',  'т'=>'t',  'у'=>'u',  'ф'=>'f',  'х'=>'kh',
            'ц'=>'ts', 'ч'=>'ch', 'ш'=>'sh', 'щ'=>'sch','ь'=>'',
            'ю'=>'yu', 'я'=>'ya', 'ъ'=>'',   'ы'=>'y',  'э'=>'e',
            'ё'=>'yo',
            'А'=>'A',  'Б'=>'B',  'В'=>'V',  'Г'=>'G',  'Д'=>'D',
            'Е'=>'E',  'Є'=>'Ye', 'Ж'=>'Zh', 'З'=>'Z',  'И'=>'I',
            'І'=>'I',  'Ї'=>'Yi', 'Й'=>'Y',  'К'=>'K',  'Л'=>'L',
            'М'=>'M',  'Н'=>'N',  'О'=>'O',  'П'=>'P',  'Р'=>'R',
            'С'=>'S',  'Т'=>'T',  'У'=>'U',  'Ф'=>'F',  'Х'=>'Kh',
            'Ц'=>'Ts', 'Ч'=>'Ch', 'Ш'=>'Sh', 'Щ'=>'Sch','Ь'=>'',
            'Ю'=>'Yu', 'Я'=>'Ya', 'Ъ'=>'',   'Ы'=>'Y',  'Э'=>'E',
            'Ё'=>'Yo',
        ];
        return strtr($str, $map);
    }

    private function getMockStock(): array {
        return [
            ['id' => '2648', 'name' => 'Apple iPhone 14', 'mat_u' => true, 'mat_b' => true, 'mat_sp' => true, 'mat_pc' => true, 'mat_pm' => true, 'mat_c' => false, 'mat_m' => false, 'mat_t' => false],
            ['id' => '3098', 'name' => 'Apple iPhone 15 Pro Max', 'mat_u' => true, 'mat_b' => false, 'mat_sp' => true, 'mat_pc' => false, 'mat_pm' => true, 'mat_c' => false, 'mat_m' => false, 'mat_t' => false],
            ['id' => '3388', 'name' => 'Samsung Galaxy S24 Plus', 'mat_u' => true, 'mat_b' => true, 'mat_sp' => false, 'mat_pc' => false, 'mat_pm' => false, 'mat_c' => false, 'mat_m' => false, 'mat_t' => false],
        ];
    }
}
