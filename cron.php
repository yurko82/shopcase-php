<?php
declare(strict_types=1);

require_once __DIR__ . '/app/Config.php';
require_once __DIR__ . '/app/Database.php';
require_once __DIR__ . '/app/EndorPhone.php';
require_once __DIR__ . '/app/StockService.php';
require_once __DIR__ . '/app/OrderService.php';

use App\Config;
use App\StockService;
use App\EndorPhone;
use App\Database;

Config::load();

$isCli = (php_sapi_name() === 'cli');
$token = (string)($_GET['token'] ?? '');
$validToken = Config::getCronToken();

if (!$isCli && ($token === '' || $token !== $validToken)) {
    http_response_code(403);
    exit('Forbidden');
}

$start = microtime(true);
echo "[" . date('Y-m-d H:i:s') . "] Запуск синхронізації ShopCase...\n";

// 1. Синхронізація залишків Stock
$api = new EndorPhone();
$stockData = $api->getAllStock(true); // force refresh
$syncedCount = StockService::syncStock($stockData);
echo "[OK] Оновлено залишків для {$syncedCount} моделей смартфонів.\n";

require_once __DIR__ . '/app/TelegramService.php';

use App\TelegramService;

// 2. Оновлення статусів замовлень та ТТН
try {
    $allOrders = Database::getOrders();
    $activeOrders = array_filter($allOrders, fn($o) => !empty($o['endorphone_order_id']) && !in_array($o['status'] ?? '', ['12', '13', '14', '28', 'delivered', 'cancelled']));

    if (!empty($activeOrders)) {
        $vendorIds = array_column($activeOrders, 'endorphone_order_id');
        $statuses = $api->getOrdersStatus($vendorIds);
        $updatedCount = 0;

        foreach ($statuses as $st) {
            $vId = (string)($st['id'] ?? '');
            if ($vId === '') continue;

            $statusText = (string)($st['state'] ?? '');
            $ttn = (string)($st['ttn'] ?? '');

            // Знаходимо існуюче замовлення для перевірки змін
            $existing = null;
            foreach ($activeOrders as $ao) {
                if ((string)($ao['endorphone_order_id'] ?? '') === $vId) {
                    $existing = $ao;
                    break;
                }
            }

            $oldStatusText = (string)($existing['status_text'] ?? '');
            $oldTtn = (string)($existing['ttn'] ?? '');

            Database::updateOrder($vId, [
                'status_text' => $statusText,
                'ttn'         => $ttn,
            ]);
            $updatedCount++;

            // Якщо статус змінився або з'явився новий ТТН — надсилаємо сповіщення
            if ($existing && (($statusText !== '' && $statusText !== $oldStatusText) || ($ttn !== '' && $ttn !== $oldTtn))) {
                try {
                    TelegramService::notifyStatusUpdate((string)($existing['id'] ?? ''), (int)$vId, $statusText, $ttn);
                } catch (\Throwable $te) {
                    error_log('[Telegram] Cron status alert error: ' . $te->getMessage());
                }
            }
        }
        echo "[OK] Оновлено інформацію для {$updatedCount} замовлень (статуси/ТТН).\n";
    } else {
        echo "[INFO] Немає активних замовлень для оновлення статусів.\n";
    }
} catch (\Throwable $e) {
    echo "[WARN] Помилка оновлення статусів: " . $e->getMessage() . "\n";
}

$time = round(microtime(true) - $start, 2);

// 3. Синхронізація XML каталогу товарів EndorPhone
try {
    require_once __DIR__ . '/app/XmlSyncService.php';
    $xmlService = new \App\XmlSyncService();
    $xmlRes = $xmlService->sync();
    echo "[OK] XML Каталог: " . ($xmlRes['message'] ?? 'Завершено') . "\n";
} catch (\Throwable $e) {
    echo "[WARN] Помилка синхронізації XML каталогу: " . $e->getMessage() . "\n";
}

echo "[DONE] Синхронізація завершена за {$time}с.\n";
