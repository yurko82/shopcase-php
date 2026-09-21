<?php
declare(strict_types=1);

namespace App;

use Exception;

/**
 * OrderService
 * Оформлення, валідація, збереження та відправка замовлень в EndorPhone
 */
class OrderService {

    public static function createOrder(array $data): array {
        $required = ['surname', 'name', 'phone', 'city', 'warehouse', 'model_id', 'mat_key'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                return ['success' => false, 'error' => "Поле «{$field}» є обов'язковим"];
            }
        }

        $surname = trim(strip_tags((string)$data['surname']));
        $name = trim(strip_tags((string)$data['name']));
        $phone = preg_replace('/[^\d+]/', '', (string)$data['phone']);
        $email = trim(strip_tags((string)($data['email'] ?? '')));
        $city = trim(strip_tags((string)$data['city']));
        $cityRef = trim(strip_tags((string)($data['city_ref'] ?? '')));
        $warehouse = trim(strip_tags((string)$data['warehouse']));
        $warehouseRef = trim(strip_tags((string)($data['warehouse_ref'] ?? '')));
        $comment = trim(strip_tags((string)($data['comment'] ?? '')));
        $paymentType = in_array((string)($data['payment_type'] ?? '1'), ['1', '2'], true) ? (string)$data['payment_type'] : '1';
        $prepayType = in_array((string)($data['prepayment_type'] ?? 'card'), ['card', 'liqpay', 'balance'], true) ? (string)$data['prepayment_type'] : 'card';
        $deliveryPayer = in_array((string)($data['delivery_payer'] ?? '1'), ['1', '2'], true) ? (string)$data['delivery_payer'] : '1';

        if (!preg_match('/^\+380\d{9}$/', $phone)) {
            return ['success' => false, 'error' => 'Введіть коректний номер телефону (+380XXXXXXXXX)'];
        }

        $designId = (int)($data['design_id'] ?? 0);
        $modelId = trim((string)$data['model_id']);
        $matKey = trim((string)$data['mat_key']);

        if (!isset(Config::MATERIAL_CODES[$matKey])) {
            return ['success' => false, 'error' => 'Невірний тип матеріалу'];
        }

        $matCode = Config::MATERIAL_CODES[$matKey];
        $prices = Config::getMaterialPrices();
        $price = $prices[$matKey] ?? 199;

        // Генерація валідного коду: {design_id}{mat_code}-{model_id}
        $designPrefix = $designId > 0 ? (string)$designId : '825';
        $fullProductCode = "{$designPrefix}{$matCode}-{$modelId}";

        $localOrderId = time() . rand(10, 99);

        // Санітизація маркетингових UTM даних
        $rawUtm = is_array($data['utm'] ?? null) ? $data['utm'] : [];
        $utm = [
            'source'       => trim(strip_tags((string)($rawUtm['source'] ?? 'direct'))),
            'medium'       => trim(strip_tags((string)($rawUtm['medium'] ?? 'none'))),
            'campaign'     => trim(strip_tags((string)($rawUtm['campaign'] ?? ''))),
            'content'      => trim(strip_tags((string)($rawUtm['content'] ?? ''))),
            'term'         => trim(strip_tags((string)($rawUtm['term'] ?? ''))),
            'referrer'     => trim(strip_tags((string)($rawUtm['referrer'] ?? ''))),
            'landing_page' => trim(strip_tags((string)($rawUtm['landing_page'] ?? ''))),
        ];

        $orderRecord = [
            'id'                  => $localOrderId,
            'endorphone_order_id' => null,
            'surname'             => $surname,
            'name'                => $name,
            'phone'               => $phone,
            'email'               => $email,
            'city'                => $city,
            'city_ref'            => $cityRef,
            'warehouse'           => $warehouse,
            'warehouse_ref'       => $warehouseRef,
            'payment_type'        => $paymentType,
            'prepayment_type'     => $prepayType,
            'delivery_payer'      => $deliveryPayer,
            'total_amount'        => $price,
            'product_code'        => $fullProductCode,
            'design_id'           => $designId,
            'model_id'            => $modelId,
            'status'              => 'new',
            'ttn'                 => '',
            'comment'             => $comment,
            'utm'                 => $utm,
            'created_at'          => date('Y-m-d H:i:s'),
        ];

        Database::saveOrder($orderRecord);

        // Відправка в EndorPhone API (передаємо Ref UUID або транслітеровану назву)
        $epCity = (strlen($cityRef) === 36) ? $cityRef : EndorPhone::transliterate($city);
        $epWarehouse = (strlen($warehouseRef) === 36) ? $warehouseRef : EndorPhone::transliterate($warehouse);

        $endorphoneOrderData = [
            'surname'             => EndorPhone::transliterate($surname),
            'name'                => EndorPhone::transliterate($name),
            'phone'               => $phone,
            'email'               => $email,
            'city'                => $epCity,
            'warehouse'           => $epWarehouse,
            'payment_type'        => $paymentType,
            'prepayment_type'     => $prepayType,
            'delivery_payer'      => $deliveryPayer,
            'cash_delivery_payer' => '1',
            'comment'             => EndorPhone::transliterate($comment),
            'products'            => [
                [
                    'code'  => $fullProductCode,
                    'qty'   => '1',
                    'price' => (string)$price,
                ]
            ],
        ];

        $api = new EndorPhone();
        $apiResult = $api->createOrder($endorphoneOrderData);
        $vendorId = (!empty($apiResult['success']) && !empty($apiResult['id'])) ? (int)$apiResult['id'] : 0;

        // Зберігаємо EndorPhone ID якщо отримано
        if ($vendorId > 0) {
            Database::updateOrder($localOrderId, ['endorphone_order_id' => $vendorId]);
        }

        self::logOrder($localOrderId, $vendorId, $surname, $name, $phone, $fullProductCode, $price);

        // Надсилаємо Telegram сповіщення адміністратору
        try {
            TelegramService::notifyNewOrder($orderRecord, $vendorId);
        } catch (\Throwable $e) {
            error_log('[Telegram] Error sending order notification: ' . $e->getMessage());
        }

        if ($vendorId > 0) {
            return [
                'success'       => true,
                'order_id'      => $localOrderId,
                'endorphone_id' => $vendorId,
                'product_code'  => $fullProductCode,
                'total_amount'  => $price,
            ];
        }

        return [
            'success'  => true,
            'order_id' => $localOrderId,
            'note'     => 'Замовлення збережено і передано менеджеру',
        ];
    }

    public static function trackByPhone(string $phone): array {
        $cleanPhone = preg_replace('/[^\d+]/', '', $phone);
        if (strlen($cleanPhone) < 9) return [];

        $allOrders = Database::getOrders();
        $tail = substr($cleanPhone, -9);

        $matched = array_filter($allOrders, fn($o) => str_contains($o['phone'] ?? '', $tail));
        return array_values($matched);
    }

    private static function logOrder(mixed $localId, int $vendorId, string $surname, string $name, string $phone, string $code, int $price): void {
        $logFile = Config::getLogsDir() . 'orders.log';
        if (!is_dir(Config::getLogsDir())) mkdir(Config::getLogsDir(), 0755, true);

        $line = sprintf(
            "[%s] Local #%s | EndorPhone #%d | %s %s | %s | Code: %s | %d грн\n",
            date('Y-m-d H:i:s'),
            (string)$localId,
            $vendorId,
            $surname,
            $name,
            $phone,
            $code,
            $price
        );
        file_put_contents($logFile, $line, FILE_APPEND | LOCK_EX);
    }
}
