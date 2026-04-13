<?php
/**
 * endorphone.php
 * Клас для роботи з EndorPhone API через cURL
 * Всі запити — POST з JSON body (згідно документації)
 */

class EndorPhone {

    private string $apiKey;
    private string $baseUrl;

    public function __construct() {
        $this->apiKey  = ENDORPHONE_API_KEY;
        $this->baseUrl = ENDORPHONE_BASE_URL;
    }

    /* ══════════════════════════════
       БАЗОВИЙ POST ЗАПИТ
    ══════════════════════════════ */

    private function post(string $endpoint, array $payload): array {
        $url  = $this->baseUrl . '/' . $endpoint;
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

        $response  = curl_exec($ch);
        $httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
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

        $response = preg_replace('/^\xEF\xBB\xBF/', '', trim($response));
        $data     = json_decode($response, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log('[EndorPhone] JSON error: ' . json_last_error_msg());
            return ['success' => false, 'error' => 'JSON: ' . json_last_error_msg()];
        }

        return $data;
    }

    /* ══════════════════════════════
       STOCK
    ══════════════════════════════ */

    public function getAllStock(): array {
        // Тестовий режим — повертаємо mock дані
        if (defined('USE_MOCK_DATA') && USE_MOCK_DATA) {
            require_once __DIR__ . '/mock_stock.php';
            return getMockStock();
        }

        $cacheFile = CACHE_DIR . 'stock.json';

        if (file_exists($cacheFile) && (time() - filemtime($cacheFile)) < STOCK_CACHE_TTL) {
            $cached = json_decode(file_get_contents($cacheFile), true);
            if (!empty($cached)) return $cached;
        }

        $data = $this->post('stock', [
            'apikey'       => $this->apiKey,
            'calledMethod' => 'get',
        ]);

        if (!empty($data['success']) && !empty($data['response'])) {
            if (!is_dir(CACHE_DIR)) mkdir(CACHE_DIR, 0755, true);
            file_put_contents($cacheFile, json_encode($data['response']));
            return $data['response'];
        }

        return [];
    }

    /* ══════════════════════════════
       ORDERS
    ══════════════════════════════ */

    public function createOrder(array $orderData): array {
        $data = $this->post('orders', [
            'apikey'       => $this->apiKey,
            'calledMethod' => 'post',
            'properties'   => $orderData,
        ]);

        if (!empty($data['success']) && !empty($data['response']['id'])) {
            return ['success' => true, 'id' => $data['response']['id']];
        }

        return ['success' => false, 'error' => $data['error'] ?? 'Невідома помилка'];
    }

    public function getOrdersStatus(array $ids): array {
        $data = $this->post('orders', [
            'apikey'       => $this->apiKey,
            'calledMethod' => 'get',
            'ids'          => $ids,
        ]);

        return $data['response'] ?? [];
    }

    /* ══════════════════════════════
       HELPERS
    ══════════════════════════════ */

    public static function getAvailableMaterials(array $item): array {
        return array_keys(array_filter(
            MATERIAL_LABELS,
            fn($label, $key) => !empty($item[$key]),
            ARRAY_FILTER_USE_BOTH
        ));
    }

    public static function getMinPrice(array $item): int {
        $available = self::getAvailableMaterials($item);
        if (empty($available)) return 0;
        return min(array_map(fn($key) => MATERIAL_PRICES[$key] ?? 0, $available));
    }

    public static function getBrandEmoji(string $name): string {
        $n = mb_strtolower($name);
        if (str_contains($n, 'apple') || str_contains($n, 'iphone'))  return '🍎';
        if (str_contains($n, 'samsung'))                               return '💎';
        if (str_contains($n, 'xiaomi') || str_contains($n, 'redmi'))  return '⚡';
        if (str_contains($n, 'huawei') || str_contains($n, 'honor'))  return '🌸';
        if (str_contains($n, 'google') || str_contains($n, 'pixel'))  return '🔍';
        if (str_contains($n, 'oneplus'))                               return '🔴';
        if (str_contains($n, 'oppo')   || str_contains($n, 'realme')) return '🌊';
        return '📱';
    }
}
