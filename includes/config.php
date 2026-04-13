<?php
/**
 * config.php
 * Конфігурація проекту
 */

// 🔑 API ключ EndorPhone
define('ENDORPHONE_API_KEY', 'GhJo3lylMmf73pBvIhiJZlpieAsvn69vH');
define('ENDORPHONE_BASE_URL', 'https://api.endorphone.com.ua/1.0');

// ─────────────────────────────────────────
// 🛠 РЕЖИМ ДАНИХ
//   true  — тестові дані (поки API недоступне)
//   false — реальний EndorPhone API
// ─────────────────────────────────────────
define('USE_MOCK_DATA', true);

// Назви матеріалів для UI
define('MATERIAL_LABELS', [
    'mat_u'  => 'Силікон',
    'mat_c'  => '3D глянець',
    'mat_m'  => '3D мат',
    'mat_t'  => '2D пластик',
    'mat_b'  => 'TPU чорний',
    'mat_sp' => 'Силікон+кути',
    'mat_pc' => 'Bumper',
    'mat_pm' => 'Bumper MagSafe',
]);

// Ціни за матеріал (грн)
define('MATERIAL_PRICES', [
    'mat_u'  => 199,
    'mat_c'  => 249,
    'mat_m'  => 249,
    'mat_t'  => 179,
    'mat_b'  => 229,
    'mat_sp' => 269,
    'mat_pc' => 299,
    'mat_pm' => 349,
]);

// Кешування stock (секунди)
define('STOCK_CACHE_TTL', 300);
define('CACHE_DIR', __DIR__ . '/../cache/');
