<?php
declare(strict_types=1);

namespace App;

/**
 * TelegramService
 * Сервіс сповіщень адміністратора в Telegram про нові замовлення та оновлення статусів/ТТН
 */
class TelegramService {

    /**
     * Безпечне екранування тексту для Telegram HTML parse_mode
     * Telegram вимагає екранувати лише <, >, & (апострофи та лапки не перетворюємо на &#039; / &quot;)
     */
    public static function escapeHtml(string $text): string {
        return htmlspecialchars($text, ENT_NOQUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /**
     * Відправка повідомлення в Telegram Bot
     */
    public static function send(string $message, string $parseMode = 'HTML'): bool {
        $token = Config::getTelegramBotToken();
        $chatId = Config::getTelegramChatId();

        if (empty($token) || empty($chatId)) {
            return false;
        }

        $url = "https://api.telegram.org/bot{$token}/sendMessage";
        $payload = [
            'chat_id'                  => $chatId,
            'text'                     => $message,
            'parse_mode'               => $parseMode,
            'disable_web_page_preview' => true,
        ];

        $jsonPayload = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $jsonPayload,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json; charset=utf-8',
                'Accept: application/json',
                'Content-Length: ' . strlen((string)$jsonPayload),
            ],
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
        ]);

        $res = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError) {
            error_log('[Telegram] cURL error: ' . $curlError);
            return false;
        }

        if ($httpCode === 200 && $res) {
            $data = json_decode((string)$res, true);
            return !empty($data['ok']);
        }

        error_log('[Telegram] HTTP ' . $httpCode . ' response: ' . $res);
        return false;
    }

    /**
     * Сповіщення про нове замовлення
     */
    public static function notifyNewOrder(array $order, int $vendorId = 0): bool {
        $id        = self::escapeHtml((string)($order['id'] ?? '—'));
        $name      = self::escapeHtml(trim(($order['surname'] ?? '') . ' ' . ($order['name'] ?? '')));
        $phone     = self::escapeHtml((string)($order['phone'] ?? ''));
        $city      = self::escapeHtml((string)($order['city'] ?? ''));
        $warehouse = self::escapeHtml((string)($order['warehouse'] ?? ''));
        $code      = self::escapeHtml((string)($order['product_code'] ?? ''));
        $price     = (int)($order['total_amount'] ?? 199);
        $payText   = ($order['payment_type'] ?? '1') === '1' ? '📦 Післяплата (накладений)' : '💳 Онлайн передплата';
        $comment   = self::escapeHtml((string)($order['comment'] ?? ''));

        $text = "🎉 <b>Нове замовлення на ShopCase!</b>\n\n";
        $text .= "🆔 <b>Замовлення:</b> #{$id}\n";
        if ($vendorId > 0) {
            $text .= "🏭 <b>EndorPhone ID:</b> #{$vendorId}\n";
        }
        $text .= "👤 <b>Покупець:</b> {$name}\n";
        $text .= "📞 <b>Телефон:</b> <a href=\"tel:{$phone}\">{$phone}</a>\n";
        $text .= "📱 <b>Артикул товару:</b> <code>{$code}</code>\n";
        $text .= "💰 <b>Сума до сплати:</b> <b>{$price} ₴</b>\n";
        $text .= "💳 <b>Спосіб оплати:</b> {$payText}\n";
        $text .= "📮 <b>Доставка:</b> м. {$city}, {$warehouse}\n";
        if ($comment !== '') {
            $text .= "💬 <b>Коментар:</b> <i>{$comment}</i>\n";
        }

        // Маркетингові UTM дані
        if (!empty($order['utm']) && is_array($order['utm'])) {
            $utmSource   = self::escapeHtml((string)($order['utm']['source'] ?? ''));
            $utmMedium   = self::escapeHtml((string)($order['utm']['medium'] ?? ''));
            $utmCampaign = self::escapeHtml((string)($order['utm']['campaign'] ?? ''));
            $utmContent  = self::escapeHtml((string)($order['utm']['content'] ?? ''));

            if ($utmSource !== '' && $utmSource !== 'direct') {
                $srcStr = "🎯 <b>Джерело:</b> {$utmSource}";
                if ($utmMedium !== '' && $utmMedium !== 'none') $srcStr .= " / {$utmMedium}";
                if ($utmCampaign !== '') $srcStr .= " (Кампанія: {$utmCampaign})";
                if ($utmContent !== '') $srcStr .= " [Креатив: {$utmContent}]";
                $text .= "\n{$srcStr}\n";
            }
        }

        $text .= "\n⏱ " . date('d.m.Y H:i:s');

        return self::send($text);
    }

    /**
     * Сповіщення про зміну статусу або появу ТТН
     */
    public static function notifyStatusUpdate(string $localId, int $vendorId, string $statusText, string $ttn = ''): bool {
        $localIdEsc    = self::escapeHtml($localId);
        $statusTextEsc = self::escapeHtml($statusText);
        $ttnEsc        = self::escapeHtml($ttn);

        $text = "🔄 <b>Оновлення статусу замовлення!</b>\n\n";
        $text .= "🆔 <b>Замовлення:</b> #{$localIdEsc} (EndorPhone #{$vendorId})\n";
        $text .= "📊 <b>Статус:</b> <b>{$statusTextEsc}</b>\n";
        if ($ttn !== '') {
            $text .= "🚚 <b>ТТН Нової Пошти:</b> <code>{$ttnEsc}</code>\n";
            $text .= "🔗 <a href=\"https://tracking.novaposhta.ua/#/uk?cargo_number={$ttnEsc}\">Відстежити на сайті Нової Пошти</a>\n";
        }
        $text .= "\n⏱ " . date('d.m.Y H:i:s');

        return self::send($text);
    }
}
