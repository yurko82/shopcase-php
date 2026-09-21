<?php
declare(strict_types=1);

session_start();

require_once __DIR__ . '/app/Config.php';
require_once __DIR__ . '/app/Database.php';
require_once __DIR__ . '/app/EndorPhone.php';
require_once __DIR__ . '/app/StockService.php';
require_once __DIR__ . '/app/CatalogService.php';
require_once __DIR__ . '/app/TelegramService.php';

use App\Config;
use App\Database;
use App\EndorPhone;
use App\StockService;
use App\CatalogService;
use App\TelegramService;

Config::load();

// 1. Обробка виходу
if (isset($_GET['logout'])) {
    unset($_SESSION['is_admin']);
    session_destroy();
    header('Location: /admin.php');
    exit;
}

// 2. Перевірка авторизації
$adminPass = Config::getAdminPassword();
$authError = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login_pass'])) {
    $enteredPass = (string)$_POST['login_pass'];
    if ($enteredPass === $adminPass) {
        $_SESSION['is_admin'] = true;
        header('Location: /admin.php');
        exit;
    } else {
        $authError = 'Невірний пароль доступу';
    }
}

$isLoggedIn = !empty($_SESSION['is_admin']);

// Якщо не авторизований — показуємо екран входу
if (!$isLoggedIn): ?>
<!DOCTYPE html>
<html lang="uk">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Вхід — ShopCase Панель керування</title>
  <link rel="stylesheet" href="/css/main.css?v=2.5">
  <link rel="stylesheet" href="/css/form.css?v=2.5">
  <style>
    body { background: #0f172a; min-height: 100vh; display: flex; align-items: center; justify-content: center; font-family: system-ui, sans-serif; }
    .login-card { background: #1e293b; border: 1px solid #334155; border-radius: 16px; padding: 36px; width: 100%; max-width: 400px; box-shadow: 0 20px 40px rgba(0,0,0,0.5); color: #f8fafc; }
    .login-title { font-size: 1.5rem; font-weight: 800; margin-bottom: 8px; text-align: center; }
    .login-sub { font-size: 0.85rem; color: #94a3b8; text-align: center; margin-bottom: 24px; }
    .login-error { background: rgba(239, 68, 68, 0.15); border: 1px solid #ef4444; color: #fca5a5; padding: 10px 14px; border-radius: 8px; font-size: 0.85rem; margin-bottom: 16px; text-align: center; }
  </style>
</head>
<body>
  <div class="login-card">
    <div style="text-align:center; font-size: 2.8rem; margin-bottom: 12px; color: var(--clr-pink, #f43f5e);">◈</div>
    <h1 class="login-title">ShopCase Admin</h1>
    <p class="login-sub">Введіть пароль для входу в панель керування</p>
    
    <?php if ($authError): ?>
      <div class="login-error"><?= htmlspecialchars($authError) ?></div>
    <?php endif; ?>

    <form method="POST" action="/admin.php">
      <div class="form-group" style="margin-bottom: 20px;">
        <label class="form-label" style="color: #cbd5e1;">Пароль адміністратора</label>
        <input type="password" name="login_pass" class="form-input" placeholder="••••••••" style="background:#0f172a; border-color:#475569; color:#fff;" required autofocus />
      </div>
      <button type="submit" class="btn btn--primary btn--block" style="padding: 14px; font-weight: 700;">Увійти в кабінет</button>
    </form>
  </div>
</body>
</html>
<?php
exit;
endif;

// 3. Експорт замовлень у CSV (Excel UTF-8 BOM)
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $orders = Database::getOrders();
    usort($orders, fn($a, $b) => strcmp((string)($b['created_at'] ?? ''), (string)($a['created_at'] ?? '')));

    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="shopcase_orders_' . date('Y-m-d_H-i') . '.csv"');
    header('Pragma: no-cache');
    header('Expires: 0');

    $out = fopen('php://output', 'w');
    fputs($out, "\xEF\xBB\xBF"); // UTF-8 BOM для коректного відображення українських літер в Excel

    fputcsv($out, [
        'ID замовлення', 'Дата створення', 'Прізвище', 'Ім\'я', 'Телефон', 'Email',
        'Місто', 'Відділення', 'Артикул', 'Модель ID', 'Сума (грн)',
        'Оплата', 'EndorPhone ID', 'Статус', 'ТТН Нової Пошти', 'Коментар',
        'UTM Source', 'UTM Medium', 'UTM Campaign', 'UTM Content', 'UTM Term', 'Referrer'
    ], ';');

    foreach ($orders as $o) {
        $stInfo = Config::getStatusInfo((string)($o['status'] ?? '4'));
        $statusText = $o['status_text'] ?? $stInfo['label'] ?? 'В обробці';
        $payText = ($o['payment_type'] ?? '1') === '2' ? 'Повна передоплата' : 'Накладений платіж';
        $utm = is_array($o['utm'] ?? null) ? $o['utm'] : [];

        fputcsv($out, [
            $o['id'] ?? '',
            $o['created_at'] ?? '',
            $o['surname'] ?? '',
            $o['name'] ?? '',
            $o['phone'] ?? '',
            $o['email'] ?? '',
            $o['city'] ?? '',
            $o['warehouse'] ?? '',
            $o['product_code'] ?? '',
            $o['model_id'] ?? '',
            $o['total_amount'] ?? 199,
            $payText,
            $o['endorphone_order_id'] ?? '',
            $statusText,
            $o['ttn'] ?? '',
            $o['comment'] ?? '',
            $utm['source'] ?? '',
            $utm['medium'] ?? '',
            $utm['campaign'] ?? '',
            $utm['content'] ?? '',
            $utm['term'] ?? '',
            $utm['referrer'] ?? ''
        ], ';');
    }
    fclose($out);
    exit;
}

// 4. Обробка POST дій
$msg = '';
$msgType = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $act = (string)$_POST['action'];

    // Синхронізація залишків
    if ($act === 'sync_stock') {
        $api = new EndorPhone();
        $stockData = $api->getAllStock(true);
        $synced = StockService::syncStock($stockData);
        $msg = "Успішно оновлено залишки для {$synced} моделей смартфонів!";
    }

    // Синхронізація XML каталогу EndorPhone
    if ($act === 'sync_xml') {
        require_once __DIR__ . '/app/XmlSyncService.php';
        $xmlService = new \App\XmlSyncService();
        $res = $xmlService->sync();
        if ($res['success']) {
            $msg = $res['message'];
        } else {
            $msgType = 'danger';
            $msg = $res['message'];
        }
    }

    // Очищення кешу каталогу
    if ($act === 'clear_cache') {
        Database::clearCache();
        $msg = "Кеш каталогу принтів та категорій успішно очищено та оновлено!";
    }

    // Тестове повідомлення в Telegram
    if ($act === 'test_telegram') {
        $res = TelegramService::send("🤖 <b>Тестове сповіщення від ShopCase Admin!</b>\nПеревірка зв'язку: Привіт, Україно! 🇺🇦\nСпецсимволи: ім'я, м'який, «ShopCase».\n⏱ " . date('d.m.Y H:i:s'));
        if ($res) {
            $msg = "Тестове повідомлення успішно надіслано в Telegram!";
        } else {
            $msg = "Помилка відправки в Telegram! Перевірте токен та chat_id.";
            $msgType = 'error';
        }
    }

    // Тестове замовлення в Telegram
    if ($act === 'test_order_notify') {
        $testOrder = [
            'id'           => 'TEST-' . rand(1000, 9999),
            'surname'      => "Мар'яненко",
            'name'         => "В'ячеслав",
            'phone'        => '+380971234567',
            'city'         => "Кам'янець-Подільський",
            'warehouse'    => 'Відділення №1: вул. "Центральна", 10',
            'product_code' => '825u-2648',
            'total_amount' => 199,
            'payment_type' => '1',
            'comment'      => "Тестове замовлення для перевірки апострофів (м'який, зв'язок).",
        ];
        $res = TelegramService::notifyNewOrder($testOrder, 999999);
        if ($res) {
            $msg = "Тестове замовлення успішно надіслано в Telegram!";
        } else {
            $msg = "Помилка відправки замовлення в Telegram!";
            $msgType = 'error';
        }
    }

    // Редагування / Оновлення замовлення
    if ($act === 'update_order' && !empty($_POST['order_id'])) {
        $oId = (string)$_POST['order_id'];
        $nStatus = (string)($_POST['new_status'] ?? '4');
        $nTtn = trim((string)($_POST['new_ttn'] ?? ''));
        $nSurname = trim((string)($_POST['surname'] ?? ''));
        $nName = trim((string)($_POST['name'] ?? ''));
        $nPhone = trim((string)($_POST['phone'] ?? ''));
        $nCity = trim((string)($_POST['city'] ?? ''));
        $nWarehouse = trim((string)($_POST['warehouse'] ?? ''));
        $nComment = trim((string)($_POST['comment'] ?? ''));
        $nAmount = (int)($_POST['total_amount'] ?? 199);

        $updates = [
            'status'       => $nStatus,
            'status_text'  => Config::getStatusInfo($nStatus)['label'] ?? $nStatus,
            'ttn'          => $nTtn,
            'total_amount' => $nAmount,
        ];
        if ($nSurname) $updates['surname'] = $nSurname;
        if ($nName) $updates['name'] = $nName;
        if ($nPhone) $updates['phone'] = $nPhone;
        if ($nCity) $updates['city'] = $nCity;
        if ($nWarehouse) $updates['warehouse'] = $nWarehouse;
        if ($nComment !== '') $updates['comment'] = $nComment;

        if (Database::updateOrder($oId, $updates)) {
            $msg = "Замовлення #{$oId} успішно оновлено!";
        } else {
            $msg = "Помилка оновлення замовлення #{$oId}";
            $msgType = 'error';
        }
    }

    // Видалення замовлення
    if ($act === 'delete_order' && !empty($_POST['order_id'])) {
        $oId = (string)$_POST['order_id'];
        if (Database::deleteOrder($oId)) {
            $msg = "Замовлення #{$oId} видалено!";
        } else {
            $msg = "Не вдалося знайти або видалити замовлення";
            $msgType = 'error';
        }
    }

    // Збереження цін матеріалів
    if ($act === 'save_pricing' && isset($_POST['prices']) && is_array($_POST['prices'])) {
        if (Config::saveMaterialPrices($_POST['prices'])) {
            $msg = "Налаштування цін матеріалів успішно збережено!";
        } else {
            $msg = "Помилка збереження цін";
            $msgType = 'error';
        }
    }

    // Скидання цін до стандартних
    if ($act === 'reset_pricing') {
        $pricingFile = dirname(__DIR__) . '/data/pricing.json';
        if (file_exists($pricingFile)) {
            @unlink($pricingFile);
        }
        $msg = "Ціни успішно скинуто до базових значень!";
    }

    // Збереження налаштувань маркетингу та пікселів
    if ($act === 'save_marketing') {
        $mSettings = [
            'gtm_id'          => $_POST['gtm_id'] ?? '',
            'meta_pixel_id'   => $_POST['meta_pixel_id'] ?? '',
            'tiktok_pixel_id' => $_POST['tiktok_pixel_id'] ?? '',
            'ga4_id'          => $_POST['ga4_id'] ?? '',
        ];
        if (Config::saveMarketingSettings($mSettings)) {
            $msg = "Налаштування пікселів та аналітики успішно збережено!";
        } else {
            $msg = "Помилка збереження налаштувань аналітики";
            $msgType = 'error';
        }
    }

    // Збереження / Створення принта
    if ($act === 'save_design') {
        $dId = (int)($_POST['design_id'] ?? 0);
        $dName = trim((string)($_POST['name'] ?? ''));
        $dCat = trim((string)($_POST['category_slug'] ?? 'other'));
        $dTags = trim((string)($_POST['tags'] ?? ''));
        $dIsTop = !empty($_POST['is_top']) ? 1 : 0;

        if ($dId <= 0) {
            // Автоматична генерація ID якщо не вказано
            $allD = Database::getDesignsData();
            $maxId = 7000;
            foreach ($allD as $d) {
                if ($d['id'] > $maxId) $maxId = $d['id'];
            }
            $dId = $maxId + 1;
        }

        $imagePath = '';
        // Обробка завантаження зображення
        if (isset($_FILES['design_image']) && $_FILES['design_image']['error'] === UPLOAD_ERR_OK) {
            $tmp = $_FILES['design_image']['tmp_name'];
            $ext = strtolower(pathinfo($_FILES['design_image']['name'], PATHINFO_EXTENSION));
            if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true)) {
                $targetDir = dirname(__DIR__) . "/design/{$dCat}";
                if (!is_dir($targetDir)) mkdir($targetDir, 0755, true);
                $fileName = "{$dId}u-4029.jpg";
                $targetFile = "{$targetDir}/{$fileName}";
                if (move_uploaded_file($tmp, $targetFile)) {
                    $imagePath = "/design/{$dCat}/{$fileName}";
                }
            }
        }

        $record = [
            'id'            => $dId,
            'name'          => $dName ?: "Принт #{$dId}",
            'category_slug' => $dCat,
            'tags'          => $dTags,
            'is_top'        => $dIsTop,
        ];
        if ($imagePath) {
            $record['image_path'] = $imagePath;
        }

        if (Database::saveDesign($record)) {
            $msg = "Принт «{$record['name']}» (#{$dId}) успішно збережено!";
        } else {
            $msg = "Помилка збереження принта";
            $msgType = 'error';
        }
    }

    // Видалення принта
    if ($act === 'delete_design' && !empty($_POST['design_id'])) {
        $dId = (int)$_POST['design_id'];
        if (Database::deleteDesign($dId)) {
            $msg = "Принт #{$dId} успішно видалено з каталогу!";
        } else {
            $msg = "Помилка видалення принта";
            $msgType = 'error';
        }
    }

    // Збереження категорії
    if ($act === 'save_category') {
        $cSlug = trim(preg_replace('/[^a-z0-9_-]/i', '', strtolower((string)($_POST['slug'] ?? ''))));
        $cName = trim((string)($_POST['name'] ?? ''));
        $cIcon = trim((string)($_POST['icon'] ?? '🎨'));
        $cSort = (int)($_POST['sort'] ?? 50);

        if ($cSlug && $cName) {
            Database::saveCategory([
                'slug' => $cSlug,
                'name' => $cName,
                'icon' => $cIcon,
                'sort' => $cSort,
            ]);
            $msg = "Категорію «{$cName}» успішно збережено!";
        } else {
            $msg = "Вкажіть Slug та Назву категорії";
            $msgType = 'error';
        }
    }

    // Видалення категорії
    if ($act === 'delete_category' && !empty($_POST['slug'])) {
        $cSlug = (string)$_POST['slug'];
        if (Database::deleteCategory($cSlug)) {
            $msg = "Категорію «{$cSlug}» видалено!";
        } else {
            $msg = "Помилка видалення категорії";
            $msgType = 'error';
        }
    }
}

// 5. Завантаження даних для вкладок
$currentTab = (string)($_GET['tab'] ?? 'orders');

// Замовлення
$allOrders = Database::getOrders();
usort($allOrders, fn($a, $b) => strcmp((string)($b['created_at'] ?? ''), (string)($a['created_at'] ?? '')));

// Фільтрація замовлень
$orderSearch = trim((string)($_GET['q'] ?? ''));
$statusFilter = (string)($_GET['status'] ?? '');
$filteredOrders = $allOrders;

if ($statusFilter !== '') {
    $filteredOrders = array_filter($filteredOrders, fn($o) => (string)($o['status'] ?? '') === $statusFilter);
}

if ($orderSearch !== '') {
    $qL = mb_strtolower($orderSearch);
    $filteredOrders = array_filter($filteredOrders, function($o) use ($qL) {
        return str_contains(mb_strtolower((string)($o['id'] ?? '')), $qL)
            || str_contains(mb_strtolower((string)($o['surname'] ?? '')), $qL)
            || str_contains(mb_strtolower((string)($o['name'] ?? '')), $qL)
            || str_contains(mb_strtolower((string)($o['phone'] ?? '')), $qL)
            || str_contains(mb_strtolower((string)($o['ttn'] ?? '')), $qL)
            || str_contains(mb_strtolower((string)($o['city'] ?? '')), $qL)
            || str_contains(mb_strtolower((string)($o['product_code'] ?? '')), $qL);
    });
}

// Загальна статистика
$totalOrdersCount = count($allOrders);
$totalRevenue = array_sum(array_column($allOrders, 'total_amount'));
$modelsList = StockService::getModelsForJs();
$activeModelsCount = count($modelsList);
$designsList = Database::getDesignsData();
$totalDesignsCount = count($designsList);
$categoriesList = Database::getCategoriesData();
$currentPrices = Config::getMaterialPrices();

// Аналітика розрахунків
$completedStatuses = ['10', '11', '12', '21', '30'];
$canceledStatuses = ['13', '14', '18', '28'];
$completedOrders = array_filter($allOrders, fn($o) => in_array((string)($o['status'] ?? ''), $completedStatuses, true));
$canceledOrders = array_filter($allOrders, fn($o) => in_array((string)($o['status'] ?? ''), $canceledStatuses, true));
$completedCount = count($completedOrders);
$completedRevenue = array_sum(array_column($completedOrders, 'total_amount'));
$avgCheck = $totalOrdersCount > 0 ? (int)round($totalRevenue / $totalOrdersCount) : 0;
$conversionRate = $totalOrdersCount > 0 ? round(($completedCount / $totalOrdersCount) * 100, 1) : 0;

// Маркетинг та аналітика трафіку
$marketingSettings = Config::getMarketingSettings();
$sourcesStats = [];
$campaignsStats = [];

foreach ($allOrders as $ord) {
    $u = is_array($ord['utm'] ?? null) ? $ord['utm'] : [];
    $s = !empty($u['source']) ? strtolower((string)$u['source']) : 'direct';
    $c = !empty($u['campaign']) ? (string)$u['campaign'] : '';
    $amt = (int)($ord['total_amount'] ?? 199);

    if (!isset($sourcesStats[$s])) {
        $sourcesStats[$s] = ['count' => 0, 'revenue' => 0];
    }
    $sourcesStats[$s]['count']++;
    $sourcesStats[$s]['revenue'] += $amt;

    if ($c !== '') {
        if (!isset($campaignsStats[$c])) {
            $campaignsStats[$c] = ['count' => 0, 'revenue' => 0, 'source' => $s];
        }
        $campaignsStats[$c]['count']++;
        $campaignsStats[$c]['revenue'] += $amt;
    }
}
uasort($sourcesStats, fn($a, $b) => $b['count'] <=> $a['count']);
uasort($campaignsStats, fn($a, $b) => $b['count'] <=> $a['count']);

// Фінанси EndorPhone
$payouts = [];
$statements = [];
$stDate = date('01.m.Y');
$endDate = date('d.m.Y');

if ($currentTab === 'finances') {
    $api = new EndorPhone();
    try {
        $payouts = $api->getPayouts($stDate, $endDate);
        $statements = $api->getStatements($stDate, $endDate);
    } catch (\Throwable $e) {}
}
?>
<!DOCTYPE html>
<html lang="uk">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>ShopCase — Панель керування</title>
  <link rel="stylesheet" href="/css/main.css?v=2.5">
  <link rel="stylesheet" href="/css/form.css?v=2.5">
  <style>
    :root {
      --admin-bg: #0b0f19;
      --admin-card: #151e2e;
      --admin-card-hover: #1b263b;
      --admin-border: #243247;
      --admin-text: #f8fafc;
      --admin-muted: #94a3b8;
      --admin-accent: #6366f1;
      --admin-pink: #ec4899;
    }
    body { background: var(--admin-bg); color: var(--admin-text); font-family: system-ui, -apple-system, sans-serif; min-height: 100vh; line-height: 1.5; }
    
    /* Хедер */
    .admin-header { background: var(--admin-card); border-bottom: 1px solid var(--admin-border); position: sticky; top: 0; z-index: 100; box-shadow: 0 4px 20px rgba(0,0,0,0.3); }
    .admin-header__inner { display: flex; align-items: center; justify-content: space-between; max-width: 1400px; margin: 0 auto; padding: 12px 24px; flex-wrap: wrap; gap: 12px; }
    
    /* Навігація */
    .admin-nav { display: flex; gap: 6px; flex-wrap: wrap; }
    .admin-nav-link { padding: 8px 14px; border-radius: 8px; color: var(--admin-muted); font-size: 0.88rem; font-weight: 600; text-decoration: none; transition: 0.2s; display: inline-flex; align-items: center; gap: 6px; }
    .admin-nav-link:hover { background: var(--admin-border); color: #fff; }
    .admin-nav-link.active { background: #312e81; color: #c7d2fe; border: 1px solid #4338ca; }
    
    .admin-content { max-width: 1400px; margin: 24px auto; padding: 0 20px 60px; }
    
    /* Статистичні картки */
    .stat-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(210px, 1fr)); gap: 16px; margin-bottom: 24px; }
    .stat-card { background: var(--admin-card); border: 1px solid var(--admin-border); border-radius: 12px; padding: 18px 20px; transition: transform 0.2s; }
    .stat-card:hover { transform: translateY(-2px); border-color: #3b82f6; }
    .stat-card__val { font-size: 1.7rem; font-weight: 800; margin-top: 4px; }
    .stat-card__lbl { font-size: 0.75rem; color: var(--admin-muted); text-transform: uppercase; font-weight: 700; letter-spacing: 0.05em; }
    .stat-card__sub { font-size: 0.8rem; color: var(--admin-muted); margin-top: 4px; }

    /* Таблиці */
    .admin-table-wrap { background: var(--admin-card); border: 1px solid var(--admin-border); border-radius: 12px; overflow-x: auto; margin-bottom: 30px; box-shadow: 0 4px 15px rgba(0,0,0,0.2); }
    .admin-table { width: 100%; border-collapse: collapse; text-align: left; font-size: 0.88rem; }
    .admin-table th { background: #0f172a; padding: 14px 16px; font-weight: 700; color: var(--admin-muted); border-bottom: 1px solid var(--admin-border); text-transform: uppercase; font-size: 0.75rem; letter-spacing: 0.05em; white-space: nowrap; }
    .admin-table td { padding: 14px 16px; border-bottom: 1px solid var(--admin-border); color: #cbd5e1; vertical-align: middle; }
    .admin-table tr:hover td { background: rgba(255,255,255,0.02); }

    /* Бейджі */
    .badge { display: inline-block; padding: 4px 10px; border-radius: 20px; font-size: 0.75rem; font-weight: 700; white-space: nowrap; }
    .badge--info { background: rgba(59, 130, 246, 0.15); color: #93c5fd; border: 1px solid rgba(59, 130, 246, 0.4); }
    .badge--warning { background: rgba(234, 179, 8, 0.15); color: #fde047; border: 1px solid rgba(234, 179, 8, 0.4); }
    .badge--success { background: rgba(34, 197, 94, 0.15); color: #86efac; border: 1px solid rgba(34, 197, 94, 0.4); }
    .badge--danger { background: rgba(239, 68, 68, 0.15); color: #fca5a5; border: 1px solid rgba(239, 68, 68, 0.4); }
    .badge--primary { background: rgba(168, 85, 247, 0.15); color: #d8b4fe; border: 1px solid rgba(168, 85, 247, 0.4); }

    /* Кнопки */
    .btn-admin { padding: 8px 16px; border-radius: 8px; font-size: 0.85rem; font-weight: 700; cursor: pointer; border: 1px solid var(--admin-border); background: #1e293b; color: #fff; transition: 0.2s; text-decoration: none; display: inline-flex; align-items: center; gap: 6px; }
    .btn-admin:hover { background: #334155; border-color: #475569; }
    .btn-admin--primary { background: #4f46e5; border-color: #6366f1; color: #fff; }
    .btn-admin--primary:hover { background: #4338ca; }
    .btn-admin--success { background: #16a34a; border-color: #22c55e; color: #fff; }
    .btn-admin--success:hover { background: #15803d; }
    .btn-admin--danger { background: rgba(239, 68, 68, 0.2); border-color: #ef4444; color: #fca5a5; }
    .btn-admin--danger:hover { background: #ef4444; color: #fff; }
    .btn-admin--sm { padding: 4px 10px; font-size: 0.78rem; }

    /* Сповіщення */
    .alert { padding: 14px 18px; border-radius: 8px; margin-bottom: 20px; font-weight: 600; font-size: 0.9rem; display: flex; align-items: center; justify-content: space-between; }
    .alert--success { background: rgba(34, 197, 94, 0.15); border: 1px solid #22c55e; color: #86efac; }
    .alert--error { background: rgba(239, 68, 68, 0.15); border: 1px solid #ef4444; color: #fca5a5; }

    /* Фільтри */
    .filter-bar { display: flex; gap: 12px; margin-bottom: 18px; flex-wrap: wrap; align-items: center; justify-content: space-between; background: var(--admin-card); padding: 14px 18px; border-radius: 12px; border: 1px solid var(--admin-border); }
    .filter-group { display: flex; gap: 10px; flex-wrap: wrap; align-items: center; }

    /* Модальні вікна */
    .admin-modal { position: fixed; inset: 0; background: rgba(0,0,0,0.75); z-index: 200; display: flex; align-items: center; justify-content: center; padding: 20px; backdrop-filter: blur(4px); }
    .admin-modal.hidden { display: none; }
    .admin-modal__card { background: #1e293b; border: 1px solid var(--admin-border); border-radius: 16px; width: 100%; max-width: 600px; max-height: 90vh; overflow-y: auto; padding: 28px; box-shadow: 0 25px 50px rgba(0,0,0,0.6); position: relative; }
    .admin-modal__close { position: absolute; top: 20px; right: 20px; background: none; border: none; font-size: 1.3rem; color: var(--admin-muted); cursor: pointer; }
    .admin-modal__close:hover { color: #fff; }

    /* Сітка принтів */
    .designs-admin-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 16px; margin-bottom: 24px; }
    .design-admin-card { background: var(--admin-card); border: 1px solid var(--admin-border); border-radius: 12px; overflow: hidden; display: flex; flex-direction: column; transition: transform 0.2s; position: relative; }
    .design-admin-card:hover { transform: translateY(-2px); border-color: #6366f1; }
    .design-admin-card__img { width: 100%; aspect-ratio: 1; object-fit: cover; background: #0f172a; }
    .design-admin-card__body { padding: 12px; flex-grow: 1; display: flex; flex-direction: column; justify-content: space-between; }
    .design-admin-card__title { font-size: 0.88rem; font-weight: 700; margin-bottom: 4px; line-height: 1.3; }
    .design-admin-card__cat { font-size: 0.75rem; color: var(--admin-muted); margin-bottom: 8px; }
    .design-admin-card__actions { display: flex; gap: 6px; margin-top: 10px; }

    /* Ціновий калькулятор */
    .pricing-table input[type="number"] { width: 100px; padding: 6px 10px; background: #0f172a; border: 1px solid #334155; border-radius: 6px; color: #fff; font-weight: 700; font-size: 0.95rem; text-align: center; }
  </style>
</head>
<body>

  <!-- Хедер -->
  <header class="admin-header">
    <div class="admin-header__inner">
      <div style="display:flex; align-items:center; gap: 14px;">
        <a href="/" target="_blank" style="text-decoration:none; color:#fff; font-weight:800; font-size:1.15rem; display:flex; align-items:center; gap:6px;">
          <span style="color:var(--admin-pink);">◈</span> ShopCase
        </a>
        <span style="background:var(--admin-accent); padding:3px 8px; border-radius:6px; font-size:0.75rem; font-weight:800; letter-spacing:0.05em;">ADMIN v3.0</span>
      </div>

      <nav class="admin-nav">
        <a href="?tab=orders" class="admin-nav-link <?= ($currentTab === 'orders') ? 'active' : '' ?>">📦 Замовлення (<?= $totalOrdersCount ?>)</a>
        <a href="?tab=marketing" class="admin-nav-link <?= ($currentTab === 'marketing') ? 'active' : '' ?>">🎯 Маркетинг & Пікселі</a>
        <a href="?tab=designs" class="admin-nav-link <?= ($currentTab === 'designs') ? 'active' : '' ?>">🎨 Принти (<?= $totalDesignsCount ?>)</a>
        <a href="?tab=categories" class="admin-nav-link <?= ($currentTab === 'categories') ? 'active' : '' ?>">🏷️ Категорії (<?= count($categoriesList) ?>)</a>
        <a href="?tab=pricing" class="admin-nav-link <?= ($currentTab === 'pricing') ? 'active' : '' ?>">💰 Ціни & Націнки</a>
        <a href="?tab=analytics" class="admin-nav-link <?= ($currentTab === 'analytics') ? 'active' : '' ?>">📊 Аналітика</a>
        <a href="?tab=finances" class="admin-nav-link <?= ($currentTab === 'finances') ? 'active' : '' ?>">💳 EndorPhone API</a>
        <a href="?tab=tools" class="admin-nav-link <?= ($currentTab === 'tools') ? 'active' : '' ?>">⚙️ Cron & Тести</a>
        <a href="?logout=1" class="admin-nav-link" style="color:#f87171;" title="Вийти з кабінету">Вийти ✕</a>
      </nav>
    </div>
  </header>

  <main class="admin-content">

    <!-- Сповіщення -->
    <?php if ($msg): ?>
      <div class="alert alert--<?= $msgType ?>">
        <span><?= htmlspecialchars($msg) ?></span>
        <button type="button" onclick="this.parentElement.remove()" style="background:none; border:none; color:inherit; cursor:pointer; font-size:1.1rem;">✕</button>
      </div>
    <?php endif; ?>

    <!-- Статистичні картки (верхній дашборд) -->
    <div class="stat-grid">
      <div class="stat-card">
        <div class="stat-card__lbl">Загальний дохід</div>
        <div class="stat-card__val" style="color:#22c55e;"><?= number_format($totalRevenue, 0, '', ' ') ?> ₴</div>
        <div class="stat-card__sub">Виконано: <?= number_format($completedRevenue, 0, '', ' ') ?> ₴</div>
      </div>
      <div class="stat-card">
        <div class="stat-card__lbl">Всього замовлень</div>
        <div class="stat-card__val"><?= $totalOrdersCount ?></div>
        <div class="stat-card__sub">Успішних: <?= $completedCount ?> (<?= $conversionRate ?>%)</div>
      </div>
      <div class="stat-card">
        <div class="stat-card__lbl">Середній чек</div>
        <div class="stat-card__val" style="color:#60a5fa;"><?= $avgCheck ?> ₴</div>
        <div class="stat-card__sub">Мінімальна ціна: <?= Config::getMinPrice() ?> ₴</div>
      </div>
      <div class="stat-card">
        <div class="stat-card__lbl">Каталог принтів</div>
        <div class="stat-card__val" style="color:var(--admin-pink);"><?= $totalDesignsCount ?></div>
        <div class="stat-card__sub">У <?= count($categoriesList) ?> категоріях</div>
      </div>
      <div class="stat-card">
        <div class="stat-card__lbl">Моделей телефонів</div>
        <div class="stat-card__val" style="color:#a78bfa;"><?= $activeModelsCount ?></div>
        <div class="stat-card__sub">Синхронізовано з EndorPhone</div>
      </div>
    </div>

    <!-- ==================== ВКЛАДКА: ЗАМОВЛЕННЯ ==================== -->
    <?php if ($currentTab === 'orders'): ?>
      <div class="filter-bar">
        <div class="filter-group">
          <form method="GET" style="display:flex; gap:8px; margin:0;">
            <input type="hidden" name="tab" value="orders">
            <input type="text" name="q" value="<?= htmlspecialchars($orderSearch) ?>" placeholder="Пошук (тел, ПІБ, ТТН, ID)..." class="form-input" style="padding:6px 12px; font-size:0.85rem; width:220px; background:#0f172a; border-color:#334155; color:#fff;" />
            <select name="status" class="form-input" style="padding:6px 12px; font-size:0.85rem; background:#0f172a; border-color:#334155; color:#fff;" onchange="this.form.submit()">
              <option value="">Всі статуси</option>
              <?php foreach (Config::ENDORPHONE_STATUSES as $sId => $sMeta): ?>
                <option value="<?= $sId ?>" <?= ($statusFilter === (string)$sId) ? 'selected' : '' ?>><?= htmlspecialchars($sMeta['label']) ?></option>
              <?php endforeach; ?>
            </select>
            <button type="submit" class="btn-admin btn-admin--sm">Знайти</button>
            <?php if ($orderSearch || $statusFilter !== ''): ?>
              <a href="?tab=orders" class="btn-admin btn-admin--sm btn-admin--danger">Скинути</a>
            <?php endif; ?>
          </form>
        </div>

        <div class="filter-group">
          <a href="?export=csv" class="btn-admin btn-admin--success">📥 Експорт у CSV (Excel)</a>
          <form method="POST" style="margin:0;">
            <input type="hidden" name="action" value="sync_stock">
            <button type="submit" class="btn-admin">🔄 Оновити залишки</button>
          </form>
        </div>
      </div>

      <div class="admin-table-wrap">
        <table class="admin-table">
          <thead>
            <tr>
              <th>ID</th>
              <th>Дата</th>
              <th>Клієнт / Телефон</th>
              <th>Товар / Модель</th>
              <th>Сума</th>
              <th>Доставка (Нова Пошта)</th>
              <th>EndorPhone</th>
              <th>Статус</th>
              <th>ТТН</th>
              <th>Дії</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($filteredOrders)): ?>
              <tr>
                <td colspan="10" style="text-align:center; padding:40px; color:var(--admin-muted);">
                  Замовлень не знайдено за заданими критеріями фільтрації.
                </td>
              </tr>
            <?php else: ?>
              <?php foreach ($filteredOrders as $ord): 
                $stInfo = Config::getStatusInfo((string)($ord['status'] ?? '4'));
                $displayStatus = $ord['status_text'] ?? $stInfo['label'] ?? 'В обробці';
                $badgeClass = $stInfo['badge'] ?? 'badge--info';
                $ordJson = htmlspecialchars(json_encode($ord, JSON_UNESCAPED_UNICODE), ENT_QUOTES);

                $oUtm = is_array($ord['utm'] ?? null) ? $ord['utm'] : [];
                $src = strtolower((string)($oUtm['source'] ?? 'direct'));
                $srcLabel = 'Direct / Органіка';
                $srcStyle = 'background:rgba(148,163,184,0.12); color:#94a3b8; border:1px solid rgba(148,163,184,0.25);';

                if (str_contains($src, 'instagram') || str_contains($src, 'ig')) {
                    $srcLabel = '📸 Instagram';
                    $srcStyle = 'background:rgba(236,72,153,0.15); color:#f472b6; border:1px solid rgba(236,72,153,0.4);';
                } elseif (str_contains($src, 'tiktok') || str_contains($src, 'tt')) {
                    $srcLabel = '🎵 TikTok';
                    $srcStyle = 'background:rgba(6,182,212,0.15); color:#22d3ee; border:1px solid rgba(6,182,212,0.4);';
                } elseif (str_contains($src, 'facebook') || str_contains($src, 'fb') || str_contains($src, 'meta')) {
                    $srcLabel = '👥 Facebook';
                    $srcStyle = 'background:rgba(59,130,246,0.15); color:#60a5fa; border:1px solid rgba(59,130,246,0.4);';
                } elseif (str_contains($src, 'google')) {
                    $srcLabel = '🔍 Google';
                    $srcStyle = 'background:rgba(34,197,94,0.15); color:#4ade80; border:1px solid rgba(34,197,94,0.4);';
                } elseif (str_contains($src, 'telegram') || str_contains($src, 'tg')) {
                    $srcLabel = '✈️ Telegram';
                    $srcStyle = 'background:rgba(56,189,248,0.15); color:#38bdf8; border:1px solid rgba(56,189,248,0.4);';
                }
              ?>
                <tr>
                  <td>
                    <strong>#<?= htmlspecialchars((string)($ord['id'] ?? '')) ?></strong>
                    <div style="margin-top:4px;">
                      <span class="badge" style="<?= $srcStyle ?> font-size:0.68rem; padding:2px 6px;"><?= $srcLabel ?></span>
                    </div>
                  </td>
                  <td style="font-size:0.8rem; color:var(--admin-muted); white-space:nowrap;"><?= htmlspecialchars((string)($ord['created_at'] ?? '—')) ?></td>
                  <td>
                    <strong><?= htmlspecialchars(trim(($ord['surname'] ?? '') . ' ' . ($ord['name'] ?? ''))) ?></strong><br>
                    <a href="tel:<?= htmlspecialchars((string)($ord['phone'] ?? '')) ?>" style="color:#93c5fd; text-decoration:none; font-size:0.85rem; font-weight:600;"><?= htmlspecialchars((string)($ord['phone'] ?? '')) ?></a>
                  </td>
                  <td>
                    <code style="background:#0f172a; padding:2px 6px; border-radius:4px; font-size:0.8rem; color:#f472b6;"><?= htmlspecialchars((string)($ord['product_code'] ?? '—')) ?></code>
                  </td>
                  <td><strong style="color:#22c55e;"><?= (int)($ord['total_amount'] ?? 199) ?> ₴</strong></td>
                  <td style="font-size:0.82rem; max-width: 220px;">
                    <strong><?= htmlspecialchars((string)($ord['city'] ?? '')) ?></strong><br>
                    <span style="color:var(--admin-muted);"><?= htmlspecialchars((string)($ord['warehouse'] ?? '')) ?></span>
                  </td>
                  <td>
                    <?php if (!empty($ord['endorphone_order_id'])): ?>
                      <span class="badge badge--primary">#<?= htmlspecialchars((string)$ord['endorphone_order_id']) ?></span>
                    <?php else: ?>
                      <span style="color:var(--admin-muted);">—</span>
                    <?php endif; ?>
                  </td>
                  <td>
                    <span class="badge <?= $badgeClass ?>"><?= htmlspecialchars($displayStatus) ?></span>
                  </td>
                  <td>
                    <?php if (!empty($ord['ttn'])): ?>
                      <a href="https://tracking.novaposhta.ua/#/uk?cargo_number=<?= urlencode((string)$ord['ttn']) ?>" target="_blank" rel="noopener" style="color:#86efac; font-weight:700; text-decoration:underline;">
                        <?= htmlspecialchars((string)$ord['ttn']) ?> ↗
                      </a>
                    <?php else: ?>
                      <span style="color:var(--admin-muted); font-size:0.8rem;">Очікується</span>
                    <?php endif; ?>
                  </td>
                  <td>
                    <div style="display:flex; gap:4px;">
                      <button type="button" class="btn-admin btn-admin--sm" onclick='openOrderEdit(<?= $ordJson ?>)' title="Редагувати">✏️</button>
                      <form method="POST" style="margin:0;" onsubmit="return confirm('Видалити замовлення #<?= htmlspecialchars((string)$ord['id']) ?>?');">
                        <input type="hidden" name="action" value="delete_order">
                        <input type="hidden" name="order_id" value="<?= htmlspecialchars((string)$ord['id']) ?>">
                        <button type="submit" class="btn-admin btn-admin--sm btn-admin--danger" title="Видалити">🗑️</button>
                      </form>
                    </div>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>

      <!-- Модальне вікно редагування замовлення -->
      <div id="orderEditModal" class="admin-modal hidden">
        <div class="admin-modal__card">
          <button type="button" class="admin-modal__close" onclick="closeOrderEdit()">✕</button>
          <h3 style="font-size:1.25rem; font-weight:800; margin-bottom:16px;">Редагування замовлення <span id="editOrderIdDisplay"></span></h3>
          
          <form method="POST">
            <input type="hidden" name="action" value="update_order">
            <input type="hidden" name="order_id" id="editOrderId">

            <div style="background:#0f172a; border:1px solid #334155; border-radius:8px; padding:12px 14px; margin-bottom:16px;">
              <div style="font-size:0.75rem; font-weight:700; color:#94a3b8; text-transform:uppercase; margin-bottom:4px; letter-spacing:0.05em;">🎯 Джерело трафіку & UTM дані</div>
              <div id="editOrderUtmDisplay" style="font-size:0.82rem; color:#cbd5e1; line-height:1.4;"></div>
            </div>

            <div style="display:grid; grid-template-columns: 1fr 1fr; gap:12px; margin-bottom:14px;">
              <div>
                <label class="form-label" style="font-size:0.8rem; color:#94a3b8;">Статус замовлення</label>
                <select name="new_status" id="editOrderStatus" class="form-input" style="background:#0f172a; color:#fff; border-color:#475569;">
                  <?php foreach (Config::ENDORPHONE_STATUSES as $sId => $sMeta): ?>
                    <option value="<?= $sId ?>"><?= htmlspecialchars($sMeta['label']) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div>
                <label class="form-label" style="font-size:0.8rem; color:#94a3b8;">ТТН Нової Пошти</label>
                <input type="text" name="new_ttn" id="editOrderTtn" placeholder="20450XXXXXXXXX" class="form-input" style="background:#0f172a; color:#fff; border-color:#475569;" />
              </div>
            </div>

            <div style="display:grid; grid-template-columns: 1fr 1fr; gap:12px; margin-bottom:14px;">
              <div>
                <label class="form-label" style="font-size:0.8rem; color:#94a3b8;">Прізвище</label>
                <input type="text" name="surname" id="editOrderSurname" class="form-input" style="background:#0f172a; color:#fff; border-color:#475569;" />
              </div>
              <div>
                <label class="form-label" style="font-size:0.8rem; color:#94a3b8;">Ім'я</label>
                <input type="text" name="name" id="editOrderName" class="form-input" style="background:#0f172a; color:#fff; border-color:#475569;" />
              </div>
            </div>

            <div style="display:grid; grid-template-columns: 1fr 1fr; gap:12px; margin-bottom:14px;">
              <div>
                <label class="form-label" style="font-size:0.8rem; color:#94a3b8;">Телефон</label>
                <input type="tel" name="phone" id="editOrderPhone" class="form-input" style="background:#0f172a; color:#fff; border-color:#475569;" />
              </div>
              <div>
                <label class="form-label" style="font-size:0.8rem; color:#94a3b8;">Сума (грн)</label>
                <input type="number" name="total_amount" id="editOrderAmount" class="form-input" style="background:#0f172a; color:#fff; border-color:#475569;" />
              </div>
            </div>

            <div style="margin-bottom:14px;">
              <label class="form-label" style="font-size:0.8rem; color:#94a3b8;">Місто доставки</label>
              <input type="text" name="city" id="editOrderCity" class="form-input" style="background:#0f172a; color:#fff; border-color:#475569;" />
            </div>

            <div style="margin-bottom:14px;">
              <label class="form-label" style="font-size:0.8rem; color:#94a3b8;">Відділення / Поштомат</label>
              <input type="text" name="warehouse" id="editOrderWarehouse" class="form-input" style="background:#0f172a; color:#fff; border-color:#475569;" />
            </div>

            <div style="margin-bottom:20px;">
              <label class="form-label" style="font-size:0.8rem; color:#94a3b8;">Коментар</label>
              <textarea name="comment" id="editOrderComment" rows="2" class="form-input" style="background:#0f172a; color:#fff; border-color:#475569;"></textarea>
            </div>

            <div style="display:flex; justify-content:flex-end; gap:10px;">
              <button type="button" class="btn-admin" onclick="closeOrderEdit()">Скасувати</button>
              <button type="submit" class="btn-admin btn-admin--primary">Зберегти зміни</button>
            </div>
          </form>
        </div>
      </div>

    <!-- ==================== ВКЛАДКА: ПРИНТИ ТА КАТАЛОГ ==================== -->
    <?php elseif ($currentTab === 'designs'): 
      $dSearch = trim((string)($_GET['dq'] ?? ''));
      $dCatFilter = (string)($_GET['dcat'] ?? '');
      $filteredDesigns = $designsList;

      if ($dCatFilter !== '') {
          $filteredDesigns = array_filter($filteredDesigns, fn($d) => ($d['category_slug'] ?? '') === $dCatFilter);
      }
      if ($dSearch !== '') {
          $qL = mb_strtolower($dSearch);
          $filteredDesigns = array_filter($filteredDesigns, function($d) use ($qL) {
              $tagsStr = is_array($d['tags'] ?? null) ? implode(' ', $d['tags']) : (string)($d['tags'] ?? '');
              return str_contains(mb_strtolower((string)($d['id'] ?? '')), $qL)
                  || str_contains(mb_strtolower((string)($d['name'] ?? '')), $qL)
                  || str_contains(mb_strtolower($tagsStr), $qL);
          });
      }
    ?>
      <div class="filter-bar">
        <div class="filter-group">
          <form method="GET" style="display:flex; gap:8px; margin:0;">
            <input type="hidden" name="tab" value="designs">
            <input type="text" name="dq" value="<?= htmlspecialchars($dSearch) ?>" placeholder="Пошук принта (назва, ID, тег)..." class="form-input" style="padding:6px 12px; font-size:0.85rem; width:220px; background:#0f172a; border-color:#334155; color:#fff;" />
            <select name="dcat" class="form-input" style="padding:6px 12px; font-size:0.85rem; background:#0f172a; border-color:#334155; color:#fff;" onchange="this.form.submit()">
              <option value="">Всі категорії (<?= $totalDesignsCount ?>)</option>
              <?php foreach ($categoriesList as $c): ?>
                <option value="<?= htmlspecialchars($c['slug']) ?>" <?= ($dCatFilter === $c['slug']) ? 'selected' : '' ?>>
                  <?= htmlspecialchars($c['icon'] ?? '🎨') ?> <?= htmlspecialchars($c['name']) ?>
                </option>
              <?php endforeach; ?>
            </select>
            <button type="submit" class="btn-admin btn-admin--sm">Знайти</button>
            <?php if ($dSearch || $dCatFilter !== ''): ?>
              <a href="?tab=designs" class="btn-admin btn-admin--sm btn-admin--danger">Скинути</a>
            <?php endif; ?>
          </form>
        </div>

        <div class="filter-group">
          <button type="button" class="btn-admin btn-admin--primary" onclick="openDesignCreate()">➕ Додати новий принт</button>
          <form method="POST" style="margin:0;">
            <input type="hidden" name="action" value="clear_cache">
            <button type="submit" class="btn-admin" title="Очистити кеш каталогу">🔄 Очистити кеш</button>
          </form>
        </div>
      </div>

      <div class="designs-admin-grid">
        <?php foreach ($filteredDesigns as $d): 
          $dJson = htmlspecialchars(json_encode([
            'id'            => $d['id'],
            'name'          => $d['name'],
            'category_slug' => $d['category_slug'],
            'tags'          => is_array($d['tags'] ?? null) ? implode(', ', $d['tags']) : (string)($d['tags'] ?? ''),
            'is_top'        => (int)($d['is_top'] ?? 0),
            'image_path'    => $d['image_path'] ?? '',
          ], JSON_UNESCAPED_UNICODE), ENT_QUOTES);
        ?>
          <div class="design-admin-card">
            <img src="<?= htmlspecialchars($d['image_path']) ?>" alt="<?= htmlspecialchars($d['name']) ?>" class="design-admin-card__img" loading="lazy" onerror="this.src='/design/made-in-ukraine/5293u-4029.jpg';" />
            <?php if (!empty($d['is_top'])): ?>
              <span class="badge badge--warning" style="position:absolute; top:8px; left:8px;">🔥 ТОП</span>
            <?php endif; ?>
            <span class="badge badge--info" style="position:absolute; top:8px; right:8px;">#<?= (int)$d['id'] ?></span>

            <div class="design-admin-card__body">
              <div>
                <h4 class="design-admin-card__title"><?= htmlspecialchars($d['name']) ?></h4>
                <div class="design-admin-card__cat"><?= htmlspecialchars($d['category_name'] ?? $d['category_slug']) ?></div>
                <?php if (!empty($d['tags'])): ?>
                  <div style="font-size:0.7rem; color:#64748b; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">
                    <?= htmlspecialchars(is_array($d['tags']) ? implode(', ', array_slice($d['tags'], 0, 3)) : (string)$d['tags']) ?>
                  </div>
                <?php endif; ?>
              </div>

              <div class="design-admin-card__actions">
                <button type="button" class="btn-admin btn-admin--sm" style="flex:1;" onclick='openDesignEdit(<?= $dJson ?>)'>✏️ Редагувати</button>
                <form method="POST" style="margin:0;" onsubmit="return confirm('Видалити принт #<?= (int)$d['id'] ?>?');">
                  <input type="hidden" name="action" value="delete_design">
                  <input type="hidden" name="design_id" value="<?= (int)$d['id'] ?>">
                  <button type="submit" class="btn-admin btn-admin--sm btn-admin--danger" title="Видалити">🗑️</button>
                </form>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>

      <!-- Модальне вікно додавання / редагування принта -->
      <div id="designModal" class="admin-modal hidden">
        <div class="admin-modal__card">
          <button type="button" class="admin-modal__close" onclick="closeDesignModal()">✕</button>
          <h3 id="designModalTitle" style="font-size:1.25rem; font-weight:800; margin-bottom:16px;">Додати новий принт</h3>
          
          <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="action" value="save_design">

            <div style="display:grid; grid-template-columns: 1fr 2fr; gap:12px; margin-bottom:14px;">
              <div>
                <label class="form-label" style="font-size:0.8rem; color:#94a3b8;">ID принта (число)</label>
                <input type="number" name="design_id" id="designIdInput" class="form-input" style="background:#0f172a; color:#fff; border-color:#475569;" placeholder="Напр. 7001" required />
              </div>
              <div>
                <label class="form-label" style="font-size:0.8rem; color:#94a3b8;">Категорія</label>
                <select name="category_slug" id="designCatInput" class="form-input" style="background:#0f172a; color:#fff; border-color:#475569;">
                  <?php foreach ($categoriesList as $c): ?>
                    <option value="<?= htmlspecialchars($c['slug']) ?>"><?= htmlspecialchars($c['icon'] ?? '🎨') ?> <?= htmlspecialchars($c['name']) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
            </div>

            <div style="margin-bottom:14px;">
              <label class="form-label" style="font-size:0.8rem; color:#94a3b8;">Назва принта</label>
              <input type="text" name="name" id="designNameInput" placeholder="Напр. Космічний кіт у неоні" class="form-input" style="background:#0f172a; color:#fff; border-color:#475569;" required />
            </div>

            <div style="margin-bottom:14px;">
              <label class="form-label" style="font-size:0.8rem; color:#94a3b8;">Пошукові теги (через кому)</label>
              <input type="text" name="tags" id="designTagsInput" placeholder="кіт, космос, неон, зорі, магія" class="form-input" style="background:#0f172a; color:#fff; border-color:#475569;" />
            </div>

            <div style="margin-bottom:14px;">
              <label class="form-label" style="font-size:0.8rem; color:#94a3b8;">Зображення макета (JPG/PNG/WebP)</label>
              <input type="file" name="design_image" accept="image/jpeg,image/png,image/webp" class="form-input" style="background:#0f172a; color:#fff; border-color:#475569;" />
            </div>

            <div style="margin-bottom:20px; display:flex; align-items:center; gap:8px;">
              <input type="checkbox" name="is_top" id="designTopInput" value="1" style="width:18px; height:18px;" />
              <label for="designTopInput" style="font-size:0.9rem; font-weight:600; color:#cbd5e1; cursor:pointer;">🔥 Показувати бейдж «ТОП» на вітрині</label>
            </div>

            <div style="display:flex; justify-content:flex-end; gap:10px;">
              <button type="button" class="btn-admin" onclick="closeDesignModal()">Скасувати</button>
              <button type="submit" class="btn-admin btn-admin--primary">Зберегти принт</button>
            </div>
          </form>
        </div>
      </div>

    <!-- ==================== ВКЛАДКА: КАТЕГОРІЇ ==================== -->
    <?php elseif ($currentTab === 'categories'): ?>
      <div style="display:grid; grid-template-columns: 1fr 340px; gap:24px;">
        <div class="admin-table-wrap">
          <table class="admin-table">
            <thead>
              <tr>
                <th>Іконка</th>
                <th>Назва</th>
                <th>Slug (URL)</th>
                <th>Сортування</th>
                <th>Дії</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($categoriesList as $c): 
                $cJson = htmlspecialchars(json_encode($c, JSON_UNESCAPED_UNICODE), ENT_QUOTES);
              ?>
                <tr>
                  <td style="font-size:1.4rem; text-align:center;"><?= htmlspecialchars($c['icon'] ?? '🎨') ?></td>
                  <td><strong><?= htmlspecialchars($c['name']) ?></strong></td>
                  <td><code>/category/<?= htmlspecialchars($c['slug']) ?></code></td>
                  <td><?= (int)($c['sort'] ?? 50) ?></td>
                  <td>
                    <div style="display:flex; gap:6px;">
                      <button type="button" class="btn-admin btn-admin--sm" onclick='openCategoryEdit(<?= $cJson ?>)'>✏️</button>
                      <form method="POST" style="margin:0;" onsubmit="return confirm('Видалити категорію «<?= htmlspecialchars($c['name']) ?>»?');">
                        <input type="hidden" name="action" value="delete_category">
                        <input type="hidden" name="slug" value="<?= htmlspecialchars($c['slug']) ?>">
                        <button type="submit" class="btn-admin btn-admin--sm btn-admin--danger">🗑️</button>
                      </form>
                    </div>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>

        <div class="stat-card" style="height:fit-content;">
          <h3 id="catFormTitle" style="font-size:1.15rem; font-weight:800; margin-bottom:14px;">➕ Додати категорію</h3>
          <form method="POST">
            <input type="hidden" name="action" value="save_category">

            <div style="margin-bottom:12px;">
              <label class="form-label" style="font-size:0.8rem; color:#94a3b8;">Назва категорії</label>
              <input type="text" name="name" id="catNameInput" placeholder="Напр. Патріотичні" class="form-input" style="background:#0f172a; color:#fff; border-color:#475569;" required />
            </div>

            <div style="margin-bottom:12px;">
              <label class="form-label" style="font-size:0.8rem; color:#94a3b8;">Slug (латиницею без пробілів)</label>
              <input type="text" name="slug" id="catSlugInput" placeholder="patriotic" class="form-input" style="background:#0f172a; color:#fff; border-color:#475569;" required />
            </div>

            <div style="display:grid; grid-template-columns: 1fr 1fr; gap:10px; margin-bottom:18px;">
              <div>
                <label class="form-label" style="font-size:0.8rem; color:#94a3b8;">Емодзі / Іконка</label>
                <input type="text" name="icon" id="catIconInput" value="🎨" class="form-input" style="background:#0f172a; color:#fff; border-color:#475569; text-align:center; font-size:1.2rem;" />
              </div>
              <div>
                <label class="form-label" style="font-size:0.8rem; color:#94a3b8;">Сортування</label>
                <input type="number" name="sort" id="catSortInput" value="25" class="form-input" style="background:#0f172a; color:#fff; border-color:#475569; text-align:center;" />
              </div>
            </div>

            <button type="submit" class="btn-admin btn-admin--primary" style="width:100%;">Зберегти категорію</button>
          </form>
        </div>
      </div>

    <!-- ==================== ВКЛАДКА: ЦІНИ ТА НАЦІНКИ ==================== -->
    <?php elseif ($currentTab === 'pricing'): ?>
      <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px;">
        <div>
          <h2 style="font-size:1.3rem; font-weight:800;">Налаштування цін та маржинальності</h2>
          <p style="font-size:0.85rem; color:var(--admin-muted);">Встановлюйте роздрібні ціни на всі 8 типів матеріалів чохлів. Зміни миттєво відображаються на сайті.</p>
        </div>
        <form method="POST" onsubmit="return confirm('Скинути всі ціни до початкових?');">
          <input type="hidden" name="action" value="reset_pricing">
          <button type="submit" class="btn-admin btn-admin--danger">↺ Скинути до стандартних</button>
        </form>
      </div>

      <form method="POST">
        <input type="hidden" name="action" value="save_pricing">

        <div class="admin-table-wrap">
          <table class="admin-table pricing-table">
            <thead>
              <tr>
                <th>Матеріал чохла</th>
                <th>Код API</th>
                <th>Дроп-собівартість</th>
                <th>Роздрібна ціна (грн)</th>
                <th>Прибуток (грн)</th>
                <th>Маржа (%)</th>
                <th>Статус</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach (Config::MATERIAL_LABELS as $key => $label): 
                $cost = Config::MATERIAL_COSTS[$key] ?? 100;
                $price = $currentPrices[$key] ?? 199;
                $profit = $price - $cost;
                $marginPercent = ($cost > 0) ? round(($profit / $cost) * 100) : 0;
              ?>
                <tr>
                  <td>
                    <strong><?= htmlspecialchars($label) ?></strong>
                  </td>
                  <td><code><?= htmlspecialchars($key) ?> (<?= Config::MATERIAL_CODES[$key] ?>)</code></td>
                  <td style="color:var(--admin-muted);"><?= $cost ?> ₴</td>
                  <td>
                    <input
                      type="number"
                      name="prices[<?= $key ?>]"
                      value="<?= $price ?>"
                      min="50"
                      max="1500"
                      class="price-input"
                      data-cost="<?= $cost ?>"
                      oninput="recalcRow(this)"
                      required
                    /> ₴
                  </td>
                  <td>
                    <strong class="profit-display" style="color:#22c55e;"><?= $profit ?> ₴</strong>
                  </td>
                  <td>
                    <span class="badge badge--success margin-display">+<?= $marginPercent ?>%</span>
                  </td>
                  <td>
                    <span class="badge badge--primary">Активний</span>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>

        <div style="display:flex; justify-content:flex-end; gap:12px;">
          <button type="submit" class="btn-admin btn-admin--primary" style="padding:12px 24px; font-size:1rem;">💾 Зберегти налаштування цін</button>
        </div>
      </form>

      <script>
        function recalcRow(input) {
          const row = input.closest('tr');
          const cost = parseFloat(input.dataset.cost) || 0;
          const price = parseFloat(input.value) || 0;
          const profit = price - cost;
          const margin = (cost > 0) ? Math.round((profit / cost) * 100) : 0;

          const profitEl = row.querySelector('.profit-display');
          const marginEl = row.querySelector('.margin-display');

          if (profitEl) profitEl.textContent = profit + ' ₴';
          if (marginEl) {
            marginEl.textContent = (margin >= 0 ? '+' : '') + margin + '%';
            marginEl.className = 'badge ' + (profit >= 0 ? 'badge--success' : 'badge--danger');
          }
        }
      </script>

    <!-- ==================== ВКЛАДКА: МАРКЕТИНГ & ПІКСЕЛІ ==================== -->
    <?php elseif ($currentTab === 'marketing'): 
      $gtmId    = $marketingSettings['gtm_id'] ?? '';
      $metaId   = $marketingSettings['meta_pixel_id'] ?? '';
      $ttId     = $marketingSettings['tiktok_pixel_id'] ?? '';
      $ga4Id    = $marketingSettings['ga4_id'] ?? '';
    ?>
      <h2 style="font-size:1.3rem; font-weight:800; margin-bottom:16px;">Маркетинг, Аналітика та Рекламні Пікселі</h2>

      <!-- Форма налаштування пікселів -->
      <form method="POST" style="margin-bottom:30px;">
        <input type="hidden" name="action" value="save_marketing">
        
        <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap:18px; margin-bottom:20px;">
          <!-- Meta Pixel -->
          <div class="stat-card" style="border-top:3px solid #3b82f6;">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:10px;">
              <span style="font-weight:800; font-size:1rem; color:#93c5fd;">📸 Meta Pixel (FB & IG)</span>
              <?php if (!empty($metaId)): ?>
                <span class="badge badge--success">🟢 Активний</span>
              <?php else: ?>
                <span class="badge" style="background:rgba(148,163,184,0.15); color:#94a3b8;">⚪ Не задано</span>
              <?php endif; ?>
            </div>
            <p style="font-size:0.8rem; color:var(--admin-muted); margin-bottom:12px;">Ідентифікатор пікселя для трекінгу подій та оптимізації реклами в Instagram/Facebook.</p>
            <input type="text" name="meta_pixel_id" value="<?= htmlspecialchars($metaId) ?>" placeholder="Наприклад: 123456789012345" class="form-input" style="background:#0f172a; border-color:#334155; color:#fff; font-size:0.9rem;" />
          </div>

          <!-- Google Tag Manager -->
          <div class="stat-card" style="border-top:3px solid #22c55e;">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:10px;">
              <span style="font-weight:800; font-size:1rem; color:#86efac;">🏷️ Google Tag Manager</span>
              <?php if (!empty($gtmId)): ?>
                <span class="badge badge--success">🟢 Активний</span>
              <?php else: ?>
                <span class="badge" style="background:rgba(148,163,184,0.15); color:#94a3b8;">⚪ Не задано</span>
              <?php endif; ?>
            </div>
            <p style="font-size:0.8rem; color:var(--admin-muted); margin-bottom:12px;">Контейнер GTM для гнучкого підключення будь-яких тегів та e-commerce подій.</p>
            <input type="text" name="gtm_id" value="<?= htmlspecialchars($gtmId) ?>" placeholder="Наприклад: GTM-XXXXXXX" class="form-input" style="background:#0f172a; border-color:#334155; color:#fff; font-size:0.9rem;" />
          </div>

          <!-- TikTok Pixel -->
          <div class="stat-card" style="border-top:3px solid #06b6d4;">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:10px;">
              <span style="font-weight:800; font-size:1rem; color:#67e8f9;">🎵 TikTok Pixel</span>
              <?php if (!empty($ttId)): ?>
                <span class="badge badge--success">🟢 Активний</span>
              <?php else: ?>
                <span class="badge" style="background:rgba(148,163,184,0.15); color:#94a3b8;">⚪ Не задано</span>
              <?php endif; ?>
            </div>
            <p style="font-size:0.8rem; color:var(--admin-muted); margin-bottom:12px;">Ідентифікатор TikTok Pixel для оптимізації відеокампаній та реклами в TikTok Ads.</p>
            <input type="text" name="tiktok_pixel_id" value="<?= htmlspecialchars($ttId) ?>" placeholder="Наприклад: C1234567890ABCDEF" class="form-input" style="background:#0f172a; border-color:#334155; color:#fff; font-size:0.9rem;" />
          </div>

          <!-- GA4 -->
          <div class="stat-card" style="border-top:3px solid #eab308;">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:10px;">
              <span style="font-weight:800; font-size:1rem; color:#fde047;">📊 Google Analytics 4</span>
              <?php if (!empty($ga4Id)): ?>
                <span class="badge badge--success">🟢 Активний</span>
              <?php else: ?>
                <span class="badge" style="background:rgba(148,163,184,0.15); color:#94a3b8;">⚪ Не задано</span>
              <?php endif; ?>
            </div>
            <p style="font-size:0.8rem; color:var(--admin-muted); margin-bottom:12px;">Прямий ідентифікатор GA4 (якщо не використовується через GTM).</p>
            <input type="text" name="ga4_id" value="<?= htmlspecialchars($ga4Id) ?>" placeholder="Наприклад: G-XXXXXXXXXX" class="form-input" style="background:#0f172a; border-color:#334155; color:#fff; font-size:0.9rem;" />
          </div>
        </div>

        <div style="display:flex; justify-content:flex-end;">
          <button type="submit" class="btn-admin btn-admin--primary" style="padding:10px 22px; font-size:0.95rem;">💾 Зберегти налаштування пікселів</button>
        </div>
      </form>

      <!-- Автоматичні події -->
      <div class="admin-table-wrap" style="margin-bottom:30px;">
        <div style="padding:16px; font-weight:800; border-bottom:1px solid var(--admin-border);">⚡ Автоматичні E-Commerce події магазину</div>
        <table class="admin-table">
          <thead>
            <tr><th>Подія</th><th>Тригер на сайті</th><th>Параметри, що передаються</th><th>Платформи</th></tr>
          </thead>
          <tbody>
            <tr>
              <td><code>PageView</code></td>
              <td>Завантаження будь-якої сторінки</td>
              <td><code>page_path, page_title</code></td>
              <td><span class="badge badge--info">GTM / GA4</span> <span class="badge badge--primary">Meta</span> <span class="badge badge--success">TikTok</span></td>
            </tr>
            <tr>
              <td><code>ViewContent</code> / <code>view_item</code></td>
              <td>Клік на принт (відкриття кастомізатора)</td>
              <td><code>content_name, content_id, category, price, currency: UAH</code></td>
              <td><span class="badge badge--info">GTM / GA4</span> <span class="badge badge--primary">Meta</span> <span class="badge badge--success">TikTok</span></td>
            </tr>
            <tr>
              <td><code>InitiateCheckout</code> / <code>begin_checkout</code></td>
              <td>Клік «Замовити для [модель]» (відкриття форми)</td>
              <td><code>content_name, model_name, material, price, currency: UAH</code></td>
              <td><span class="badge badge--info">GTM / GA4</span> <span class="badge badge--primary">Meta</span> <span class="badge badge--success">TikTok</span></td>
            </tr>
            <tr>
              <td><code>Purchase</code> / <code>CompletePayment</code></td>
              <td>Успішне підтвердження замовлення покупцем</td>
              <td><code>transaction_id, value (сума грн), product_code, currency: UAH</code></td>
              <td><span class="badge badge--info">GTM / GA4</span> <span class="badge badge--primary">Meta</span> <span class="badge badge--success">TikTok</span></td>
            </tr>
            <tr>
              <td><code>ConstructorOpen</code></td>
              <td>Клік кнопки «Чохол зі своїм фото»</td>
              <td><code>source: 'modal' / 'header'</code></td>
              <td><span class="badge badge--info">GTM / GA4</span> <span class="badge badge--primary">Meta</span> <span class="badge badge--success">TikTok</span></td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Інтерактивний Генератор UTM-посилань -->
      <div class="stat-card" style="margin-bottom:30px;">
        <h3 style="font-size:1.15rem; font-weight:800; margin-bottom:6px;">🛠️ Інтерактивний генератор UTM-посилань для реклами</h3>
        <p style="font-size:0.85rem; color:var(--admin-muted); margin-bottom:18px;">
          Створюйте готові посилання для таргетованої реклами в Instagram, TikTok, Facebook та Google Ads з коректною аналітикою.
        </p>

        <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap:14px; margin-bottom:16px;">
          <div>
            <label class="form-label" style="font-size:0.8rem; color:#94a3b8;">Посадкова сторінка</label>
            <select id="utmPage" class="form-input" style="background:#0f172a; color:#fff; border-color:#334155;" onchange="updateUtmBuilder()">
              <option value="/">Головна сторінка (https://shopcase.top/)</option>
              <option value="/#catalog">Каталог принтів (https://shopcase.top/#catalog)</option>
              <option value="/#constructor">3D-Конструктор з фото</option>
              <?php foreach ($categoriesList as $c): ?>
                <option value="/category/<?= htmlspecialchars($c['slug']) ?>">Категорія: <?= htmlspecialchars($c['name']) ?> (/category/<?= htmlspecialchars($c['slug']) ?>)</option>
              <?php endforeach; ?>
            </select>
          </div>

          <div>
            <label class="form-label" style="font-size:0.8rem; color:#94a3b8;">Рекламна платформа (utm_source)</label>
            <select id="utmSource" class="form-input" style="background:#0f172a; color:#fff; border-color:#334155;" onchange="updateUtmBuilder()">
              <option value="instagram">Instagram Ads (instagram)</option>
              <option value="tiktok">TikTok Ads (tiktok)</option>
              <option value="facebook">Facebook Ads (facebook)</option>
              <option value="google">Google Ads (google)</option>
              <option value="telegram">Telegram канал (telegram)</option>
              <option value="influencer">Блогер / Інфлюенсер (influencer)</option>
            </select>
          </div>

          <div>
            <label class="form-label" style="font-size:0.8rem; color:#94a3b8;">Тип трафіку (utm_medium)</label>
            <select id="utmMedium" class="form-input" style="background:#0f172a; color:#fff; border-color:#334155;" onchange="updateUtmBuilder()">
              <option value="cpc">CPC / Таргет (cpc)</option>
              <option value="reels">Reels / Stories (reels)</option>
              <option value="video">TikTok Video (video)</option>
              <option value="bio">Шапка профілю (bio)</option>
              <option value="post">Пост у стрічці (post)</option>
              <option value="pmax">Performance Max (pmax)</option>
              <option value="search">Пошукова реклама (search)</option>
            </select>
          </div>
        </div>

        <div style="display:grid; grid-template-columns: 1fr 1fr; gap:14px; margin-bottom:18px;">
          <div>
            <label class="form-label" style="font-size:0.8rem; color:#94a3b8;">Назва кампанії (utm_campaign)</label>
            <input type="text" id="utmCampaign" placeholder="patriotic_spring або anime_summer" class="form-input" style="background:#0f172a; color:#fff; border-color:#334155;" oninput="updateUtmBuilder()" />
          </div>
          <div>
            <label class="form-label" style="font-size:0.8rem; color:#94a3b8;">Назва креативу / версія (utm_content)</label>
            <input type="text" id="utmContent" placeholder="gold_trident_reels_v1" class="form-input" style="background:#0f172a; color:#fff; border-color:#334155;" oninput="updateUtmBuilder()" />
          </div>
        </div>

        <div>
          <label class="form-label" style="font-size:0.8rem; color:#94a3b8;">Готове згенероване посилання:</label>
          <div style="display:flex; gap:10px;">
            <input type="text" id="utmResult" readonly class="form-input" style="background:#0f172a; color:#86efac; font-weight:700; border-color:#22c55e;" />
            <button type="button" class="btn-admin btn-admin--success" onclick="copyUtmResult()" style="white-space:nowrap;">📋 Скопіювати</button>
          </div>
        </div>
      </div>

      <script>
        function updateUtmBuilder() {
          const page = document.getElementById('utmPage').value;
          const source = document.getElementById('utmSource').value;
          const medium = document.getElementById('utmMedium').value;
          const campaign = (document.getElementById('utmCampaign').value || '').trim().replace(/\s+/g, '_');
          const content = (document.getElementById('utmContent').value || '').trim().replace(/\s+/g, '_');

          let baseUrl = 'https://shopcase.top' + (page.startsWith('/') ? page : '/' + page);
          let hash = '';
          if (baseUrl.includes('#')) {
            const parts = baseUrl.split('#');
            baseUrl = parts[0];
            hash = '#' + parts[1];
          }

          const params = new URLSearchParams();
          if (source) params.set('utm_source', source);
          if (medium) params.set('utm_medium', medium);
          if (campaign) params.set('utm_campaign', campaign);
          if (content) params.set('utm_content', content);

          const queryStr = params.toString();
          const finalUrl = baseUrl + (queryStr ? (baseUrl.includes('?') ? '&' : '?') + queryStr : '') + hash;

          document.getElementById('utmResult').value = finalUrl;
        }

        function copyUtmResult() {
          const input = document.getElementById('utmResult');
          input.select();
          navigator.clipboard.writeText(input.value).then(() => {
            alert('✅ UTM-посилання успішно скопійовано в буфер обміну:\n' + input.value);
          });
        }
        document.addEventListener('DOMContentLoaded', updateUtmBuilder);
        if (document.readyState !== 'loading') updateUtmBuilder();
      </script>

    <!-- ==================== ВКЛАДКА: АНАЛІТИКА ==================== -->
    <?php elseif ($currentTab === 'analytics'): ?>
      <h2 style="font-size:1.3rem; font-weight:800; margin-bottom:16px;">Аналітика та ефективність продажів</h2>

      <div style="display:grid; grid-template-columns: 1fr 1fr; gap:20px; margin-bottom:24px;">
        <div class="stat-card">
          <h3 style="font-size:1.1rem; font-weight:800; margin-bottom:14px;">📦 Статистика замовлень за статусами</h3>
          <div style="display:flex; flex-direction:column; gap:10px;">
            <div style="display:flex; justify-content:space-between; font-size:0.9rem;">
              <span>✅ Успішно виконано (викуплено):</span>
              <strong style="color:#22c55e;"><?= $completedCount ?> (<?= $conversionRate ?>%)</strong>
            </div>
            <div style="display:flex; justify-content:space-between; font-size:0.9rem;">
              <span>⏳ В обробці / В дорозі:</span>
              <strong style="color:#60a5fa;"><?= $totalOrdersCount - $completedCount - count($canceledOrders) ?></strong>
            </div>
            <div style="display:flex; justify-content:space-between; font-size:0.9rem;">
              <span>❌ Скасовано / Відмова:</span>
              <strong style="color:#f87171;"><?= count($canceledOrders) ?></strong>
            </div>
          </div>
        </div>

        <div class="stat-card">
          <h3 style="font-size:1.1rem; font-weight:800; margin-bottom:14px;">💰 Фінансові підсумки</h3>
          <div style="display:flex; flex-direction:column; gap:10px;">
            <div style="display:flex; justify-content:space-between; font-size:0.9rem;">
              <span>Загальний грошовий оборот:</span>
              <strong style="color:#22c55e; font-size:1.1rem;"><?= number_format($totalRevenue, 0, '', ' ') ?> ₴</strong>
            </div>
            <div style="display:flex; justify-content:space-between; font-size:0.9rem;">
              <span>Середній чек одного замовлення:</span>
              <strong style="color:#60a5fa;"><?= $avgCheck ?> ₴</strong>
            </div>
            <div style="display:flex; justify-content:space-between; font-size:0.9rem;">
              <span>Орієнтовна маржинальність:</span>
              <strong style="color:#a78bfa;">~50-60%</strong>
            </div>
          </div>
        </div>
      </div>

      <!-- Аналітика джерел трафіку та UTM -->
      <div style="display:grid; grid-template-columns: 1fr 1fr; gap:20px; margin-bottom:24px;">
        <!-- Джерела замовлень -->
        <div class="admin-table-wrap">
          <div style="padding:16px; font-weight:800; border-bottom:1px solid var(--admin-border);">🎯 Джерела трафіку (utm_source)</div>
          <table class="admin-table">
            <thead>
              <tr><th>Джерело</th><th>Замовлень</th><th>Виручка</th></tr>
            </thead>
            <tbody>
              <?php if (empty($sourcesStats)): ?>
                <tr><td colspan="3" style="text-align:center; padding:20px; color:var(--admin-muted);">Немає даних про замовлення.</td></tr>
              <?php else: ?>
                <?php foreach ($sourcesStats as $sName => $sData): 
                  $sPercent = $totalOrdersCount > 0 ? round(($sData['count'] / $totalOrdersCount) * 100) : 0;
                ?>
                  <tr>
                    <td><strong><?= htmlspecialchars(ucfirst($sName)) ?></strong></td>
                    <td><?= $sData['count'] ?> <span style="color:var(--admin-muted); font-size:0.75rem;">(<?= $sPercent ?>%)</span></td>
                    <td><strong style="color:#22c55e;"><?= number_format($sData['revenue'], 0, '', ' ') ?> ₴</strong></td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>

        <!-- ТОП Кампаній -->
        <div class="admin-table-wrap">
          <div style="padding:16px; font-weight:800; border-bottom:1px solid var(--admin-border);">🚀 ТОП Рекламних Кампаній (utm_campaign)</div>
          <table class="admin-table">
            <thead>
              <tr><th>Кампанія</th><th>Джерело</th><th>Замовлень</th><th>Виручка</th></tr>
            </thead>
            <tbody>
              <?php if (empty($campaignsStats)): ?>
                <tr><td colspan="4" style="text-align:center; padding:20px; color:var(--admin-muted);">Кампанії з UTM-мітками поки що відсутні.</td></tr>
              <?php else: ?>
                <?php foreach ($campaignsStats as $cName => $cData): ?>
                  <tr>
                    <td><code style="color:#f472b6;"><?= htmlspecialchars($cName) ?></code></td>
                    <td><span class="badge badge--info"><?= htmlspecialchars($cData['source']) ?></span></td>
                    <td><?= $cData['count'] ?></td>
                    <td><strong style="color:#22c55e;"><?= number_format($cData['revenue'], 0, '', ' ') ?> ₴</strong></td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>

    <!-- ==================== ВКЛАДКА: ФІНАНСИ ENDORPHONE ==================== -->
    <?php elseif ($currentTab === 'finances'): ?>
      <h2 style="font-size:1.25rem; font-weight:800; margin-bottom:16px;">Фінансові звіти EndorPhone Drop (<?= $stDate ?> – <?= $endDate ?>)</h2>

      <div style="display:grid; grid-template-columns: 1fr 1fr; gap:20px;">
        <div class="admin-table-wrap">
          <div style="padding:16px; font-weight:800; border-bottom:1px solid var(--admin-border);">💰 Історія виплат (/1.0/payouts)</div>
          <table class="admin-table">
            <thead>
              <tr><th>Дата</th><th>Сума</th></tr>
            </thead>
            <tbody>
              <?php if (empty($payouts)): ?>
                <tr><td colspan="2" style="text-align:center; padding:20px; color:var(--admin-muted);">Немає виплат за вказаний період.</td></tr>
              <?php else: ?>
                <?php foreach ($payouts as $p): ?>
                  <tr>
                    <td><?= htmlspecialchars((string)($p['date'] ?? '')) ?></td>
                    <td><strong style="color:#22c55e;"><?= htmlspecialchars((string)($p['amount'] ?? '0')) ?> ₴</strong></td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>

        <div class="admin-table-wrap">
          <div style="padding:16px; font-weight:800; border-bottom:1px solid var(--admin-border);">📜 Рух коштів / Транзакції (/1.0/statements)</div>
          <table class="admin-table">
            <thead>
              <tr><th>Дата</th><th>Замовлення</th><th>Сума</th><th>Призначення</th></tr>
            </thead>
            <tbody>
              <?php if (empty($statements)): ?>
                <tr><td colspan="4" style="text-align:center; padding:20px; color:var(--admin-muted);">Немає транзакцій за вказаний період.</td></tr>
              <?php else: ?>
                <?php foreach ($statements as $s): ?>
                  <tr>
                    <td><?= htmlspecialchars((string)($s['date'] ?? '')) ?></td>
                    <td>#<?= htmlspecialchars((string)($s['order_id'] ?? '—')) ?></td>
                    <td><strong><?= htmlspecialchars((string)($s['amount'] ?? '0')) ?> ₴</strong></td>
                    <td style="font-size:0.8rem; color:var(--admin-muted);"><?= htmlspecialchars((string)($s['message'] ?? '')) ?></td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>

    <!-- ==================== ВКЛАДКА: ІНСТРУМЕНТИ ТА CRON ==================== -->
    <?php elseif ($currentTab === 'tools'): ?>
      <h2 style="font-size:1.25rem; font-weight:800; margin-bottom:16px;">Системні інструменти та автоматизація</h2>

      <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap:20px;">
        <div class="stat-card">
          <h3 style="font-size:1.1rem; font-weight:800; margin-bottom:8px;">📱 Telegram Сповіщення</h3>
          <p style="font-size:0.85rem; color:var(--admin-muted); margin-bottom:16px;">
            Перевірте роботу Telegram-бота адміністратора.
          </p>
          <form method="POST" style="display:flex; flex-direction:column; gap:10px;">
            <button type="submit" name="action" value="test_telegram" class="btn-admin btn-admin--primary">Надіслати тестове повідомлення</button>
            <button type="submit" name="action" value="test_order_notify" class="btn-admin" style="background:#0284c7; color:#fff; border-color:#0284c7;">Надіслати тестове замовлення (UTF-8)</button>
          </form>
        </div>

        <div class="stat-card">
          <h3 style="font-size:1.1rem; font-weight:800; margin-bottom:8px;">⏱ Фоновий Cron-скрипт</h3>
          <p style="font-size:0.85rem; color:var(--admin-muted); margin-bottom:12px;">
            Додайте це посилання в Cron завдання хостингу (кожні 15–30 хв):
          </p>
          <code style="display:block; background:#0f172a; padding:10px 14px; border-radius:6px; font-size:0.8rem; color:#86efac; word-break:break-all;">
            curl -s "https://shopcase.top/cron.php?token=<?= htmlspecialchars(Config::getCronToken()) ?>"
          </code>
        </div>

        <div class="stat-card">
          <h3 style="font-size:1.1rem; font-weight:800; margin-bottom:8px;">📡 EndorPhone XML Фід</h3>
          <?php
            require_once __DIR__ . '/app/XmlSyncService.php';
            $xmlServ = new \App\XmlSyncService();
            $xmlSt = $xmlServ->getStatus();
          ?>
          <p style="font-size:0.85rem; color:var(--admin-muted); margin-bottom:8px;">
            Статус XML: <strong style="color:<?= $xmlSt['success'] ? '#10b981' : '#f43f5e' ?>;"><?= htmlspecialchars($xmlSt['message']) ?></strong>
          </p>
          <p style="font-size:0.8rem; color:#94a3b8; margin-bottom:14px;">
            Останнє оновлення: <strong><?= htmlspecialchars($xmlSt['last_sync']) ?></strong> | Товарів: <strong><?= (int)($xmlSt['total_items'] ?? 0) ?></strong>
          </p>
          <form method="POST" style="margin:0;">
            <input type="hidden" name="action" value="sync_xml">
            <button type="submit" class="btn-admin btn-admin--success" style="width:100%; justify-content:center;">📥 Синхронізувати з XML зараз</button>
          </form>
        </div>

        <div class="stat-card">
          <h3 style="font-size:1.1rem; font-weight:800; margin-bottom:8px;">🔄 Оновлення залишків & Кешу</h3>
          <p style="font-size:0.85rem; color:var(--admin-muted); margin-bottom:14px;">
            Примусово оновити базу залишків або зкинути кеш каталогу.
          </p>
          <div style="display:flex; gap:10px;">
            <form method="POST" style="margin:0;">
              <input type="hidden" name="action" value="sync_stock">
              <button type="submit" class="btn-admin">🔄 Оновити Stock</button>
            </form>
            <form method="POST" style="margin:0;">
              <input type="hidden" name="action" value="clear_cache">
              <button type="submit" class="btn-admin btn-admin--danger">Очистити кеш</button>
            </form>
          </div>
        </div>
      </div>
    <?php endif; ?>

  </main>

  <script>
    // Модалка замовлення
    function openOrderEdit(ord) {
      document.getElementById('editOrderIdDisplay').textContent = '#' + (ord.id || '');
      document.getElementById('editOrderId').value = ord.id || '';
      document.getElementById('editOrderStatus').value = ord.status || '4';
      document.getElementById('editOrderTtn').value = ord.ttn || '';
      document.getElementById('editOrderSurname').value = ord.surname || '';
      document.getElementById('editOrderName').value = ord.name || '';
      document.getElementById('editOrderPhone').value = ord.phone || '';
      document.getElementById('editOrderAmount').value = ord.total_amount || 199;
      document.getElementById('editOrderCity').value = ord.city || '';
      document.getElementById('editOrderWarehouse').value = ord.warehouse || '';
      document.getElementById('editOrderComment').value = ord.comment || '';

      const u = ord.utm || {};
      let utmHtml = `<div><strong>Source:</strong> <span style="color:#38bdf8; font-weight:700;">${u.source || 'direct'}</span> &nbsp;|&nbsp; <strong>Medium:</strong> ${u.medium || 'none'}</div>`;
      if (u.campaign) utmHtml += `<div><strong>Campaign:</strong> <span style="color:#f472b6; font-weight:700;">${u.campaign}</span></div>`;
      if (u.content)  utmHtml += `<div><strong>Creative / Content:</strong> ${u.content}</div>`;
      if (u.term)     utmHtml += `<div><strong>Keyword / Term:</strong> ${u.term}</div>`;
      if (u.referrer) utmHtml += `<div><strong>Referrer:</strong> <span style="color:#94a3b8; font-size:0.75rem;">${u.referrer}</span></div>`;
      if (u.landing_page) utmHtml += `<div><strong>Landing:</strong> <span style="color:#94a3b8; font-size:0.75rem;">${u.landing_page}</span></div>`;
      document.getElementById('editOrderUtmDisplay').innerHTML = utmHtml;

      document.getElementById('orderEditModal').classList.remove('hidden');
    }
    function closeOrderEdit() {
      document.getElementById('orderEditModal').classList.add('hidden');
    }

    // Модалка принта
    function openDesignCreate() {
      document.getElementById('designModalTitle').textContent = 'Додати новий принт';
      document.getElementById('designIdInput').value = '';
      document.getElementById('designNameInput').value = '';
      document.getElementById('designTagsInput').value = '';
      document.getElementById('designTopInput').checked = false;
      document.getElementById('designModal').classList.remove('hidden');
    }
    function openDesignEdit(d) {
      document.getElementById('designModalTitle').textContent = 'Редагувати принт #' + d.id;
      document.getElementById('designIdInput').value = d.id;
      document.getElementById('designNameInput').value = d.name || '';
      document.getElementById('designCatInput').value = d.category_slug || 'other';
      document.getElementById('designTagsInput').value = d.tags || '';
      document.getElementById('designTopInput').checked = !!d.is_top;
      document.getElementById('designModal').classList.remove('hidden');
    }
    function closeDesignModal() {
      document.getElementById('designModal').classList.add('hidden');
    }

    // Редагування категорії
    function openCategoryEdit(c) {
      document.getElementById('catFormTitle').textContent = '✏️ Редагувати категорію';
      document.getElementById('catNameInput').value = c.name || '';
      document.getElementById('catSlugInput').value = c.slug || '';
      document.getElementById('catIconInput').value = c.icon || '🎨';
      document.getElementById('catSortInput').value = c.sort || 50;
      window.scrollTo({ top: document.body.scrollHeight, behavior: 'smooth' });
    }
  </script>

</body>
</html>
