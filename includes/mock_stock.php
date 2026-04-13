<?php
/**
 * mock_stock.php
 * Тестові дані каталогу — використовуються поки API недоступне
 * Щоб переключитись на реальний API — в config.php змініть:
 *   define('USE_MOCK_DATA', false);
 */

function getMockStock(): array {
    return [
        // Apple iPhone
        ['id' => '1050', 'name' => 'Apple iPhone X',    'mat_c' => true,  'mat_m' => true,  'mat_u' => true,  'mat_t' => false, 'mat_b' => false, 'mat_sp' => true,  'mat_pc' => true,  'mat_pm' => false],
        ['id' => '1100', 'name' => 'Apple iPhone XS',   'mat_c' => true,  'mat_m' => true,  'mat_u' => true,  'mat_t' => true,  'mat_b' => true,  'mat_sp' => true,  'mat_pc' => true,  'mat_pm' => false],
        ['id' => '1150', 'name' => 'Apple iPhone XR',   'mat_c' => true,  'mat_m' => false, 'mat_u' => true,  'mat_t' => true,  'mat_b' => false, 'mat_sp' => false, 'mat_pc' => true,  'mat_pm' => false],
        ['id' => '1200', 'name' => 'Apple iPhone 11',   'mat_c' => true,  'mat_m' => true,  'mat_u' => true,  'mat_t' => true,  'mat_b' => true,  'mat_sp' => true,  'mat_pc' => true,  'mat_pm' => false],
        ['id' => '1210', 'name' => 'Apple iPhone 11 Pro','mat_c'=> true,  'mat_m' => true,  'mat_u' => true,  'mat_t' => false, 'mat_b' => true,  'mat_sp' => true,  'mat_pc' => true,  'mat_pm' => false],
        ['id' => '1220', 'name' => 'Apple iPhone 11 Pro Max','mat_c'=>true,'mat_m'=>true,  'mat_u' => true,  'mat_t' => false, 'mat_b' => false, 'mat_sp' => true,  'mat_pc' => true,  'mat_pm' => false],
        ['id' => '1300', 'name' => 'Apple iPhone 12',   'mat_c' => true,  'mat_m' => true,  'mat_u' => true,  'mat_t' => true,  'mat_b' => true,  'mat_sp' => true,  'mat_pc' => true,  'mat_pm' => true],
        ['id' => '1310', 'name' => 'Apple iPhone 12 Pro','mat_c'=> true,  'mat_m' => true,  'mat_u' => true,  'mat_t' => true,  'mat_b' => true,  'mat_sp' => true,  'mat_pc' => true,  'mat_pm' => true],
        ['id' => '1320', 'name' => 'Apple iPhone 12 Pro Max','mat_c'=>true,'mat_m'=>true,  'mat_u' => true,  'mat_t' => false, 'mat_b' => true,  'mat_sp' => false, 'mat_pc' => true,  'mat_pm' => true],
        ['id' => '1330', 'name' => 'Apple iPhone 12 mini','mat_c'=>true,  'mat_m' => false, 'mat_u' => true,  'mat_t' => true,  'mat_b' => false, 'mat_sp' => true,  'mat_pc' => false, 'mat_pm' => true],
        ['id' => '1400', 'name' => 'Apple iPhone 13',   'mat_c' => true,  'mat_m' => true,  'mat_u' => true,  'mat_t' => true,  'mat_b' => true,  'mat_sp' => true,  'mat_pc' => true,  'mat_pm' => true],
        ['id' => '1410', 'name' => 'Apple iPhone 13 Pro','mat_c'=> true,  'mat_m' => true,  'mat_u' => true,  'mat_t' => true,  'mat_b' => true,  'mat_sp' => true,  'mat_pc' => true,  'mat_pm' => true],
        ['id' => '1420', 'name' => 'Apple iPhone 13 Pro Max','mat_c'=>true,'mat_m'=>true,  'mat_u' => true,  'mat_t' => true,  'mat_b' => false, 'mat_sp' => true,  'mat_pc' => true,  'mat_pm' => true],
        ['id' => '1430', 'name' => 'Apple iPhone 13 mini','mat_c'=>true,  'mat_m' => true,  'mat_u' => true,  'mat_t' => false, 'mat_b' => false, 'mat_sp' => false, 'mat_pc' => true,  'mat_pm' => false],
        ['id' => '1500', 'name' => 'Apple iPhone 14',   'mat_c' => true,  'mat_m' => true,  'mat_u' => true,  'mat_t' => true,  'mat_b' => true,  'mat_sp' => true,  'mat_pc' => true,  'mat_pm' => true],
        ['id' => '1510', 'name' => 'Apple iPhone 14 Plus','mat_c'=>true,  'mat_m' => true,  'mat_u' => true,  'mat_t' => true,  'mat_b' => false, 'mat_sp' => true,  'mat_pc' => true,  'mat_pm' => true],
        ['id' => '1520', 'name' => 'Apple iPhone 14 Pro','mat_c'=> true,  'mat_m' => true,  'mat_u' => true,  'mat_t' => true,  'mat_b' => true,  'mat_sp' => true,  'mat_pc' => true,  'mat_pm' => true],
        ['id' => '1530', 'name' => 'Apple iPhone 14 Pro Max','mat_c'=>true,'mat_m'=>true,  'mat_u' => true,  'mat_t' => true,  'mat_b' => true,  'mat_sp' => true,  'mat_pc' => true,  'mat_pm' => true],
        ['id' => '1600', 'name' => 'Apple iPhone 15',   'mat_c' => true,  'mat_m' => true,  'mat_u' => true,  'mat_t' => false, 'mat_b' => true,  'mat_sp' => true,  'mat_pc' => true,  'mat_pm' => true],
        ['id' => '1610', 'name' => 'Apple iPhone 15 Plus','mat_c'=>true,  'mat_m' => true,  'mat_u' => true,  'mat_t' => false, 'mat_b' => true,  'mat_sp' => true,  'mat_pc' => true,  'mat_pm' => true],
        ['id' => '1620', 'name' => 'Apple iPhone 15 Pro','mat_c'=> true,  'mat_m' => true,  'mat_u' => true,  'mat_t' => false, 'mat_b' => true,  'mat_sp' => true,  'mat_pc' => true,  'mat_pm' => true],
        ['id' => '1630', 'name' => 'Apple iPhone 15 Pro Max','mat_c'=>true,'mat_m'=>true,  'mat_u' => true,  'mat_t' => false, 'mat_b' => true,  'mat_sp' => true,  'mat_pc' => true,  'mat_pm' => true],

        // Samsung Galaxy
        ['id' => '336',  'name' => 'Samsung Galaxy S21',  'mat_c' => true,  'mat_m' => true,  'mat_u' => true,  'mat_t' => true,  'mat_b' => false, 'mat_sp' => false, 'mat_pc' => true,  'mat_pm' => false],
        ['id' => '337',  'name' => 'Samsung Galaxy S21+', 'mat_c' => true,  'mat_m' => false, 'mat_u' => true,  'mat_t' => true,  'mat_b' => false, 'mat_sp' => false, 'mat_pc' => false, 'mat_pm' => false],
        ['id' => '338',  'name' => 'Samsung Galaxy S21 Ultra','mat_c'=>true,'mat_m'=>true,  'mat_u' => true,  'mat_t' => false, 'mat_b' => true,  'mat_sp' => true,  'mat_pc' => true,  'mat_pm' => false],
        ['id' => '400',  'name' => 'Samsung Galaxy S22',  'mat_c' => true,  'mat_m' => true,  'mat_u' => true,  'mat_t' => true,  'mat_b' => true,  'mat_sp' => true,  'mat_pc' => true,  'mat_pm' => false],
        ['id' => '401',  'name' => 'Samsung Galaxy S22+', 'mat_c' => true,  'mat_m' => true,  'mat_u' => true,  'mat_t' => false, 'mat_b' => false, 'mat_sp' => false, 'mat_pc' => true,  'mat_pm' => false],
        ['id' => '402',  'name' => 'Samsung Galaxy S22 Ultra','mat_c'=>true,'mat_m'=>true,  'mat_u' => true,  'mat_t' => false, 'mat_b' => true,  'mat_sp' => true,  'mat_pc' => true,  'mat_pm' => false],
        ['id' => '500',  'name' => 'Samsung Galaxy S23',  'mat_c' => true,  'mat_m' => true,  'mat_u' => true,  'mat_t' => true,  'mat_b' => true,  'mat_sp' => true,  'mat_pc' => true,  'mat_pm' => false],
        ['id' => '501',  'name' => 'Samsung Galaxy S23+', 'mat_c' => true,  'mat_m' => true,  'mat_u' => true,  'mat_t' => false, 'mat_b' => false, 'mat_sp' => true,  'mat_pc' => true,  'mat_pm' => false],
        ['id' => '502',  'name' => 'Samsung Galaxy S23 Ultra','mat_c'=>true,'mat_m'=>true,  'mat_u' => true,  'mat_t' => false, 'mat_b' => true,  'mat_sp' => true,  'mat_pc' => true,  'mat_pm' => false],
        ['id' => '600',  'name' => 'Samsung Galaxy S24',  'mat_c' => true,  'mat_m' => true,  'mat_u' => true,  'mat_t' => true,  'mat_b' => true,  'mat_sp' => true,  'mat_pc' => true,  'mat_pm' => true],
        ['id' => '601',  'name' => 'Samsung Galaxy S24+', 'mat_c' => true,  'mat_m' => true,  'mat_u' => true,  'mat_t' => false, 'mat_b' => true,  'mat_sp' => true,  'mat_pc' => true,  'mat_pm' => true],
        ['id' => '602',  'name' => 'Samsung Galaxy S24 Ultra','mat_c'=>true,'mat_m'=>true,  'mat_u' => true,  'mat_t' => false, 'mat_b' => true,  'mat_sp' => true,  'mat_pc' => true,  'mat_pm' => true],
        ['id' => '700',  'name' => 'Samsung Galaxy A54', 'mat_c' => true,  'mat_m' => true,  'mat_u' => true,  'mat_t' => true,  'mat_b' => false, 'mat_sp' => false, 'mat_pc' => false, 'mat_pm' => false],
        ['id' => '701',  'name' => 'Samsung Galaxy A34', 'mat_c' => true,  'mat_m' => false, 'mat_u' => true,  'mat_t' => true,  'mat_b' => false, 'mat_sp' => false, 'mat_pc' => false, 'mat_pm' => false],
        ['id' => '702',  'name' => 'Samsung Galaxy A14', 'mat_c' => false, 'mat_m' => false, 'mat_u' => true,  'mat_t' => true,  'mat_b' => false, 'mat_sp' => false, 'mat_pc' => false, 'mat_pm' => false],

        // Xiaomi
        ['id' => '800',  'name' => 'Xiaomi Redmi Note 12', 'mat_c' => true, 'mat_m' => true,  'mat_u' => true,  'mat_t' => true,  'mat_b' => false, 'mat_sp' => false, 'mat_pc' => false, 'mat_pm' => false],
        ['id' => '801',  'name' => 'Xiaomi Redmi Note 11', 'mat_c' => true, 'mat_m' => false, 'mat_u' => true,  'mat_t' => true,  'mat_b' => false, 'mat_sp' => false, 'mat_pc' => false, 'mat_pm' => false],
        ['id' => '802',  'name' => 'Xiaomi Redmi Note 10', 'mat_c' => true, 'mat_m' => false, 'mat_u' => true,  'mat_t' => false, 'mat_b' => false, 'mat_sp' => false, 'mat_pc' => false, 'mat_pm' => false],
        ['id' => '810',  'name' => 'Xiaomi 13',            'mat_c' => true, 'mat_m' => true,  'mat_u' => true,  'mat_t' => false, 'mat_b' => true,  'mat_sp' => true,  'mat_pc' => true,  'mat_pm' => false],
        ['id' => '811',  'name' => 'Xiaomi 13 Pro',        'mat_c' => true, 'mat_m' => true,  'mat_u' => true,  'mat_t' => false, 'mat_b' => true,  'mat_sp' => false, 'mat_pc' => true,  'mat_pm' => false],
        ['id' => '820',  'name' => 'Xiaomi Poco X5 Pro',   'mat_c' => true, 'mat_m' => false, 'mat_u' => true,  'mat_t' => true,  'mat_b' => false, 'mat_sp' => false, 'mat_pc' => false, 'mat_pm' => false],

        // Google Pixel
        ['id' => '900',  'name' => 'Google Pixel 7',      'mat_c' => true,  'mat_m' => true,  'mat_u' => true,  'mat_t' => false, 'mat_b' => true,  'mat_sp' => false, 'mat_pc' => true,  'mat_pm' => false],
        ['id' => '901',  'name' => 'Google Pixel 7 Pro',  'mat_c' => true,  'mat_m' => true,  'mat_u' => true,  'mat_t' => false, 'mat_b' => true,  'mat_sp' => true,  'mat_pc' => true,  'mat_pm' => false],
        ['id' => '910',  'name' => 'Google Pixel 8',      'mat_c' => true,  'mat_m' => true,  'mat_u' => true,  'mat_t' => false, 'mat_b' => true,  'mat_sp' => true,  'mat_pc' => true,  'mat_pm' => true],
        ['id' => '911',  'name' => 'Google Pixel 8 Pro',  'mat_c' => true,  'mat_m' => true,  'mat_u' => true,  'mat_t' => false, 'mat_b' => true,  'mat_sp' => true,  'mat_pc' => true,  'mat_pm' => true],

        // Huawei
        ['id' => '950',  'name' => 'Huawei P50 Pro',      'mat_c' => true,  'mat_m' => false, 'mat_u' => true,  'mat_t' => true,  'mat_b' => false, 'mat_sp' => false, 'mat_pc' => false, 'mat_pm' => false],
        ['id' => '951',  'name' => 'Huawei Nova 10',      'mat_c' => true,  'mat_m' => false, 'mat_u' => true,  'mat_t' => true,  'mat_b' => false, 'mat_sp' => false, 'mat_pc' => false, 'mat_pm' => false],

        // OnePlus
        ['id' => '960',  'name' => 'OnePlus 11',          'mat_c' => true,  'mat_m' => true,  'mat_u' => true,  'mat_t' => false, 'mat_b' => true,  'mat_sp' => false, 'mat_pc' => true,  'mat_pm' => false],
        ['id' => '961',  'name' => 'OnePlus Nord CE 3',   'mat_c' => true,  'mat_m' => false, 'mat_u' => true,  'mat_t' => true,  'mat_b' => false, 'mat_sp' => false, 'mat_pc' => false, 'mat_pm' => false],
    ];
}
