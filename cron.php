<?php
/**
 * cron.php
 * Щоденне оновлення кешу каталогу
 *
 * Налаштування cron на TheHost (cPanel → Cron Jobs):
 * Команда: php /var/www/your-path/cron.php
 * Розклад: 0 6 * * *  (щодня о 6:00)
 */

// Запуск тільки з CLI або з секретним токеном
$isCli    = php_sapi_name() === 'cli';
$token    = $_GET['token'] ?? '';
$validToken = 'ЗАМІНИТИ_НА_СВІЙ_СЕКРЕТНИЙ_ТОКЕН'; // наприклад: 'xK9mP2qR5vZ8'

if (!$isCli && $token !== $validToken) {
    http_response_code(403);
    exit('Forbidden');
}

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/catalog.php';

$start = microtime(true);
echo "[" . date('Y-m-d H:i:s') . "] Оновлення каталогу...\n";

$result = Catalog::refreshCache();

$time = round(microtime(true) - $start, 2);

if ($result) {
    $info = Catalog::getCacheInfo();
    echo "[OK] Оновлено: {$info['count']} товарів за {$time}с\n";
} else {
    echo "[ERROR] Не вдалося оновити кеш\n";
}
