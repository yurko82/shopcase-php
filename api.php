<?php
/**
 * api.php
 * Серверний endpoint для прийому замовлень від JS форми
 * POST /api.php
 */

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/endorphone.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

$raw  = file_get_contents('php://input');

// BOM-фікс згідно документації
if (0 === strpos(bin2hex($raw), 'efbbbf')) {
    $raw = substr($raw, 3);
}

$body = json_decode($raw, true);

if (json_last_error() !== JSON_ERROR_NONE || empty($body)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid JSON']);
    exit;
}

$action = $body['action'] ?? '';

switch ($action) {
    case 'createOrder':
        $result = handleCreateOrder($body['data'] ?? []);
        break;
    case 'getOrderStatus':
        $result = handleGetOrderStatus($body['ids'] ?? []);
        break;
    default:
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Unknown action']);
        exit;
}

echo json_encode($result);
exit;

/* ══════════════════════════════
   ТРАНСЛІТЕРАЦІЯ
   Документація #1001 — тільки латиниця
══════════════════════════════ */
function transliterate(string $str): string {
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

/* ══════════════════════════════
   ОБРОБНИК ЗАМОВЛЕННЯ
══════════════════════════════ */
function handleCreateOrder(array $data): array {

    // Валідація обов'язкових полів
    $required = ['surname', 'name', 'phone', 'city', 'warehouse', 'products'];
    foreach ($required as $field) {
        if (empty($data[$field])) {
            return ['success' => false, 'error' => "Поле «{$field}» є обов'язковим"];
        }
    }

    // Валідація телефону
    if (!preg_match('/^\+380\d{9}$/', $data['phone'])) {
        return ['success' => false, 'error' => 'Невірний формат телефону. Приклад: +380501234567'];
    }

    // Валідація email
    if (!empty($data['email']) && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
        return ['success' => false, 'error' => 'Невірний формат email'];
    }

    // Валідація типів оплати
    $paymentType = in_array($data['payment_type'] ?? '', ['1','2'])
        ? $data['payment_type'] : '1';
    $prepayType = in_array($data['perpayment_type'] ?? '', ['card','liqpay','balance'])
        ? $data['perpayment_type'] : 'card';
    $deliveryPayer = in_array($data['delivery_payer'] ?? '', ['1','2'])
        ? $data['delivery_payer'] : '1';
    $cashPayer = in_array($data['cash_delivery_payer'] ?? '', ['1','2'])
        ? $data['cash_delivery_payer'] : '1';

    // Транслітеруємо поля з кирилицею (#1001)
    $orderData = [
        'surname'             => transliterate(sanitize($data['surname'])),
        'name'                => transliterate(sanitize($data['name'])),
        'phone'               => sanitize($data['phone']),
        'email'               => sanitize($data['email'] ?? ''),
        'city'                => sanitize($data['city']),        // код НП — цифри
        'warehouse'           => sanitize($data['warehouse']),   // код НП — цифри
        'payment_type'        => $paymentType,
        'perpayment_type'     => $prepayType,
        'delivery_payer'      => $deliveryPayer,
        'cash_delivery_payer' => $cashPayer,
        'comment'             => transliterate(sanitize($data['comment'] ?? '')),
        'products'            => [],
    ];

    // Валідація товарів
    foreach ((array)$data['products'] as $product) {
        if (empty($product['code'])) continue;

        $price = (int)($product['price'] ?? 0);
        if (!in_array($price, MATERIAL_PRICES)) {
            return ['success' => false, 'error' => 'Невірна ціна товару'];
        }

        $orderData['products'][] = [
            'code'  => sanitize($product['code']),
            'qty'   => '1',
            'price' => (string)$price,
        ];
    }

    if (empty($orderData['products'])) {
        return ['success' => false, 'error' => 'Кошик порожній'];
    }

    // Якщо mock режим — симулюємо успішне замовлення
    if (defined('USE_MOCK_DATA') && USE_MOCK_DATA) {
        $mockId = rand(100000, 999999);
        logOrder($mockId, $orderData);
        return ['success' => true, 'id' => $mockId];
    }

    // Відправляємо на EndorPhone
    $api    = new EndorPhone();
    $result = $api->createOrder($orderData);

    if ($result['success']) {
        logOrder($result['id'], $orderData);
    }

    return $result;
}

function handleGetOrderStatus(array $ids): array {
    if (empty($ids)) {
        return ['success' => false, 'error' => 'ids не вказані'];
    }

    // #1602 — тільки числові значення
    $ids = array_map('strval', array_filter($ids, 'is_numeric'));

    if (defined('USE_MOCK_DATA') && USE_MOCK_DATA) {
        return ['success' => true, 'response' => []];
    }

    $api    = new EndorPhone();
    $orders = $api->getOrdersStatus($ids);

    return ['success' => true, 'response' => $orders];
}

/* ══════════════════════════════
   УТІЛІТИ
══════════════════════════════ */
function sanitize(string $value): string {
    return trim(strip_tags($value));
}

function logOrder(int $orderId, array $data): void {
    $logDir  = __DIR__ . '/logs/';
    $logFile = $logDir . 'orders.log';

    if (!is_dir($logDir)) mkdir($logDir, 0755, true);

    $line = sprintf(
        "[%s] Order #%d | %s %s | %s | %s\n",
        date('Y-m-d H:i:s'),
        $orderId,
        $data['surname'],
        $data['name'],
        $data['phone'],
        implode(', ', array_column($data['products'], 'code'))
    );

    file_put_contents($logFile, $line, FILE_APPEND | LOCK_EX);
}
