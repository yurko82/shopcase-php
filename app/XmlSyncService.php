<?php
declare(strict_types=1);

namespace App;

use SimpleXMLElement;
use Exception;

/**
 * XmlSyncService
 * Автоматичне завантаження, парсинг та синхронізація XML/CSV фліду з EndorPhone.
 */
class XmlSyncService {

    private string $xmlUrl;
    private string $cacheFile;
    private string $statusFile;
    private string $logFile;

    public function __construct(?string $xmlUrl = null) {
        $this->xmlUrl = $xmlUrl ?? Config::getXmlExportUrl();
        $this->cacheFile = Config::getCacheDir() . 'xml_catalog.json';
        $this->statusFile = Config::getCacheDir() . 'xml_sync_status.json';
        $this->logFile = Config::getLogsDir() . 'xml_sync.log';
    }

    /**
     * Основна функція синхронізації
     */
    public function sync(): array {
        $startTime = microtime(true);
        $this->log("Початок синхронізації з XML URL: {$this->xmlUrl}");

        $xmlData = $this->downloadFeed($this->xmlUrl);
        $items = [];
        $categories = [];

        if (!empty($xmlData)) {
            $parsed = $this->parseXml($xmlData);
            $items = $parsed['items'];
            $categories = $parsed['categories'];
        }

        // Якщо XML порожній або недоступний, пробуємо обробити локальний CSV або резервний файл
        if (empty($items)) {
            $csvFile = dirname(__DIR__) . '/GvMRmjpLNSrgdHML0VEbXvoxoUIGqimwS_opencart.csv';
            if (file_exists($csvFile)) {
                $this->log("XML не містить товарів або недоступний, завантажуємо локальний фід CSV...");
                $parsedCsv = $this->parseCsvFile($csvFile);
                $items = $parsedCsv['items'];
                $categories = $parsedCsv['categories'];
            }
        }

        $totalCount = count($items);
        $duration = round(microtime(true) - $startTime, 2);

        if ($totalCount === 0) {
            $status = [
                'success' => false,
                'last_sync' => date('Y-m-d H:i:s'),
                'total_items' => 0,
                'categories_count' => 0,
                'duration_sec' => $duration,
                'message' => 'Не вдалося отримати товари з XML або CSV.',
            ];
            $this->saveStatus($status);
            $this->log("Синхронізацію завершено з помилкою: немає даних.");
            return $status;
        }

        // Зберігаємо результати у локальний кеш JSON
        $this->ensureDirectories();
        file_put_contents($this->cacheFile, json_encode([
            'updated_at' => date('Y-m-d H:i:s'),
            'total' => $totalCount,
            'categories' => $categories,
            'items' => $items,
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), LOCK_EX);

        $status = [
            'success' => true,
            'last_sync' => date('Y-m-d H:i:s'),
            'total_items' => $totalCount,
            'categories_count' => count($categories),
            'duration_sec' => $duration,
            'message' => "Синхронізовано {$totalCount} товарів ({$duration} сек).",
        ];

        $this->saveStatus($status);
        $this->log("Успішно синхронізовано {$totalCount} товарів за {$duration} сек.");

        return $status;
    }

    /**
     * Отримати останній статус синхронізації
     */
    public function getStatus(): array {
        if (file_exists($this->statusFile)) {
            $json = file_get_contents($this->statusFile);
            $data = json_decode($json, true);
            if (is_array($data)) return $data;
        }
        return [
            'success' => false,
            'last_sync' => 'Ніколи',
            'total_items' => 0,
            'categories_count' => 0,
            'message' => 'Синхронізація ще не проводилася.',
        ];
    }

    /**
     * Завантажити XML вміст за допомогою cURL
     */
    private function downloadFeed(string $url): string {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_USERAGENT, 'ShopCase-XML-Importer/1.0');
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        
        $content = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode === 200 && !empty($content)) {
            return (string)$content;
        }

        return '';
    }

    /**
     * Парсинг YML/XML форми EndorPhone
     */
    private function parseXml(string $xmlContent): array {
        $items = [];
        $categories = [];

        try {
            $xml = new SimpleXMLElement($xmlContent);

            // Парсинг категорій
            if (isset($xml->shop->categories->category)) {
                foreach ($xml->shop->categories->category as $cat) {
                    $id = (string)$cat['id'];
                    $name = trim((string)$cat);
                    $slug = $this->slugify($name);
                    $categories[$id] = [
                        'id' => $id,
                        'name' => $name,
                        'slug' => $slug,
                    ];
                }
            }

            // Парсинг товарів / оферів
            $offers = $xml->shop->offers->offer ?? $xml->offer ?? [];
            foreach ($offers as $offer) {
                $id = (string)($offer['id'] ?? $offer->id ?? '');
                $name = trim((string)($offer->name ?? ''));
                $price = (float)($offer->price ?? 0);
                $picture = (string)($offer->picture ?? '');
                $categoryId = (string)($offer->categoryId ?? '');
                $categoryName = $categories[$categoryId]['name'] ?? 'Різне';
                $vendor = (string)($offer->vendor ?? 'EndorPhone');
                $available = ((string)($offer['available'] ?? 'true')) === 'true';

                $params = [];
                if (isset($offer->param)) {
                    foreach ($offer->param as $param) {
                        $pName = (string)$param['name'];
                        $pVal = (string)$param;
                        $params[$pName] = $pVal;
                    }
                }

                $items[] = [
                    'id' => $id,
                    'name' => $name,
                    'price' => $price,
                    'picture' => $picture,
                    'category_id' => $categoryId,
                    'category_name' => $categoryName,
                    'category_slug' => $this->slugify($categoryName),
                    'vendor' => $vendor,
                    'available' => $available,
                    'params' => $params,
                ];
            }
        } catch (Exception $e) {
            $this->log("Помилка парсингу XML: " . $e->getMessage());
        }

        return [
            'categories' => array_values($categories),
            'items' => $items,
        ];
    }

    /**
     * Резервний парсер CSV файлу OpenCart/EndorPhone
     */
    private function parseCsvFile(string $csvFilePath): array {
        $items = [];
        $categoriesMap = [];

        if (!file_exists($csvFilePath)) {
            return ['categories' => [], 'items' => []];
        }

        $rawContent = file_get_contents($csvFilePath);
        if ($rawContent === false || trim($rawContent) === '') {
            return ['categories' => [], 'items' => []];
        }

        // Автоматична конвертація кодування Windows-1251 -> UTF-8
        if (!mb_check_encoding($rawContent, 'UTF-8')) {
            $rawContent = mb_convert_encoding($rawContent, 'UTF-8', 'Windows-1251');
        }

        $handle = fopen('php://temp', 'r+');
        fwrite($handle, $rawContent);
        rewind($handle);

        $header = fgetcsv($handle, 0, ';'); // Зчитуємо заголовки CSV
        $colIndex = [];
        if ($header) {
            foreach ($header as $idx => $colName) {
                $cleanCol = trim(str_replace("\xEF\xBB\xBF", '', (string)$colName));
                $colIndex[$cleanCol] = $idx;
            }
        }

        $artIdx    = $colIndex['Артикул'] ?? 0;
        $nameIdx   = $colIndex['Назва товару'] ?? 3;
        $priceIdx  = $colIndex['Ціна'] ?? 5;
        $imgIdx    = $colIndex['Фото'] ?? 8;
        $catIdx    = $colIndex['Категорія дизайну'] ?? 12;
        $modelIdx  = $colIndex['Модель телефону'] ?? 18;
        $matIdx    = $colIndex['Матеріал'] ?? 21;
        $designIdx = $colIndex['Назва дизайну'] ?? 24;

        while (($data = fgetcsv($handle, 0, ';')) !== false) {
            if (count($data) < 5) continue;

            $sku = trim($data[$artIdx] ?? '');
            $title = trim($data[$nameIdx] ?? '');
            $price = (float)str_replace(',', '.', trim($data[$priceIdx] ?? '0'));
            $photo = trim($data[$imgIdx] ?? '');
            $catName = trim($data[$catIdx] ?? 'Дизайни');
            if ($catName === '') $catName = 'Різне';

            $catSlug = $this->slugify($catName);
            $categoriesMap[$catSlug] = [
                'slug' => $catSlug,
                'name' => $catName,
            ];

            $items[] = [
                'id' => $sku,
                'name' => $title !== '' ? $title : trim($data[$designIdx] ?? 'Чохол'),
                'price' => $price > 0 ? $price : 229,
                'picture' => $photo,
                'category_name' => $catName,
                'category_slug' => $catSlug,
                'vendor' => 'EndorPhone',
                'available' => true,
                'params' => [
                    'Модель' => trim($data[$modelIdx] ?? ''),
                    'Матеріал' => trim($data[$matIdx] ?? ''),
                    'Дизайн' => trim($data[$designIdx] ?? ''),
                ]
            ];
        }
        fclose($handle);

        return [
            'categories' => array_values($categoriesMap),
            'items' => $items,
        ];
    }

    private function slugify(string $text): string {
        $text = mb_strtolower(trim($text));
        $map = [
            'а'=>'a','б'=>'b','в'=>'v','г'=>'h','ґ'=>'g','д'=>'d','е'=>'e','є'=>'ye','ж'=>'zh',
            'з'=>'z','и'=>'y','і'=>'i','ї'=>'yi','й'=>'y','к'=>'k','л'=>'l','м'=>'m','н'=>'n',
            'о'=>'o','п'=>'p','р'=>'r','с'=>'s','т'=>'t','у'=>'u','ф'=>'f','х'=>'kh','ц'=>'ts',
            'ч'=>'ch','ш'=>'sh','щ'=>'shch','ь'=>'','ю'=>'yu','я'=>'ya'
        ];
        $text = strtr($text, $map);
        $text = preg_replace('/[^a-z0-9]+/i', '-', $text);
        return trim($text, '-');
    }

    private function saveStatus(array $status): void {
        $this->ensureDirectories();
        file_put_contents($this->statusFile, json_encode($status, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), LOCK_EX);
    }

    private function log(string $msg): void {
        $this->ensureDirectories();
        $entry = "[" . date('Y-m-d H:i:s') . "] " . $msg . PHP_EOL;
        file_put_contents($this->logFile, $entry, FILE_APPEND | LOCK_EX);
    }

    private function ensureDirectories(): void {
        $cacheDir = Config::getCacheDir();
        $logsDir = Config::getLogsDir();
        if (!is_dir($cacheDir)) @mkdir($cacheDir, 0755, true);
        if (!is_dir($logsDir)) @mkdir($logsDir, 0755, true);
    }
}
