<?php
declare(strict_types=1);

require_once __DIR__ . '/app/Config.php';
require_once __DIR__ . '/app/Database.php';
require_once __DIR__ . '/app/EndorPhone.php';
require_once __DIR__ . '/app/StockService.php';
require_once __DIR__ . '/app/CatalogService.php';
require_once __DIR__ . '/app/NovaPoshta.php';
require_once __DIR__ . '/app/OrderService.php';

use App\Config;
use App\StockService;
use App\CatalogService;
use App\NovaPoshta;
use App\OrderService;

Config::load();

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

// Читаємо POST / GET запит
$raw = file_get_contents('php://input');
if (0 === strpos(bin2hex($raw), 'efbbbf')) {
    $raw = substr($raw, 3);
}

$body = json_decode($raw, true) ?? [];
$action = (string)($_POST['action'] ?? $body['action'] ?? $_GET['action'] ?? '');

try {
    switch ($action) {
        // Створення замовлення
        case 'createOrder':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                http_response_code(405);
                echo json_encode(['success' => false, 'error' => 'Method not allowed']);
                exit;
            }
            $orderData = $body['data'] ?? $_POST ?? [];
            $result = OrderService::createOrder($orderData);
            echo json_encode($result, JSON_UNESCAPED_UNICODE);
            break;

        // Пошук міст Нової Пошти
        case 'searchCities':
            $q = (string)($body['query'] ?? $_GET['q'] ?? '');
            $cities = NovaPoshta::searchCities($q);
            echo json_encode(['success' => true, 'data' => $cities], JSON_UNESCAPED_UNICODE);
            break;

        // Отримання відділень Нової Пошти
        case 'getWarehouses':
            $cityRef = (string)($body['cityRef'] ?? $_GET['cityRef'] ?? '');
            $cityName = (string)($body['cityName'] ?? $_GET['cityName'] ?? '');
            $warehouses = NovaPoshta::getWarehouses($cityRef, $cityName);
            echo json_encode(['success' => true, 'data' => $warehouses], JSON_UNESCAPED_UNICODE);
            break;

        // Отримання залишків матеріалів для моделі
        case 'getModelStock':
            $modelId = (string)($body['model_id'] ?? $_GET['model_id'] ?? '');
            $stock = StockService::getModelStock($modelId);
            if ($stock) {
                echo json_encode(['success' => true, 'data' => $stock], JSON_UNESCAPED_UNICODE);
            } else {
                echo json_encode(['success' => false, 'error' => 'Модель не знайдена'], JSON_UNESCAPED_UNICODE);
            }
            break;

        // Отримання списку всіх моделей
        case 'getModels':
            $models = StockService::getModelsForJs();
            echo json_encode(['success' => true, 'data' => $models], JSON_UNESCAPED_UNICODE);
            break;

        // Відстеження замовлення за телефоном
        case 'trackOrder':
            $phone = (string)($body['phone'] ?? $_POST['phone'] ?? $_GET['phone'] ?? '');
            $orders = OrderService::trackByPhone($phone);
            echo json_encode(['success' => true, 'data' => $orders], JSON_UNESCAPED_UNICODE);
            break;

        // Отримання списку дизайнів (для швидкого пошуку/фільтрів)
        case 'getDesigns':
            $cat = (string)($body['cat'] ?? $_GET['cat'] ?? '');
            $q = (string)($body['q'] ?? $_GET['q'] ?? '');
            $limit = max(1, min(100, (int)($_GET['limit'] ?? 24)));
            $page = max(1, (int)($_GET['page'] ?? 1));
            $offset = ($page - 1) * $limit;
            $data = CatalogService::getDesigns($cat, $q, $limit, $offset);
            echo json_encode(['success' => true, 'data' => $data], JSON_UNESCAPED_UNICODE);
            break;

        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Unknown action: ' . htmlspecialchars($action)]);
            break;
    }
} catch (\Throwable $e) {
    http_response_code(500);
    error_log('[API Error] ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Внутрішня помилка сервера'], JSON_UNESCAPED_UNICODE);
}
