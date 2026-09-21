<?php
declare(strict_types=1);

namespace App;

/**
 * NovaPoshta
 * Інтеграція з API Нової Пошти v2.0 (пошук міст, відділень та поштоматів)
 * з підтримкою CityRef/WarehouseRef UUIDs для EndorPhone API та розумним кешуванням.
 */
class NovaPoshta {

    private const API_URL = 'https://api.novaposhta.ua/v2.0/json/';

    // Вбудовані відомі Ref UUID для головних міст України
    public const POPULAR_CITIES = [
        ['name' => 'Київ',             'ref' => '8d5a980d-391c-11dd-90d9-001a92567626', 'area' => 'Київська обл.'],
        ['name' => 'Львів',            'ref' => 'db5c88f5-391c-11dd-90d9-001a92567626', 'area' => 'Львівська обл.'],
        ['name' => 'Одеса',            'ref' => 'db5c88d0-391c-11dd-90d9-001a92567626', 'area' => 'Одеська обл.'],
        ['name' => 'Харків',           'ref' => 'db5c88e0-391c-11dd-90d9-001a92567626', 'area' => 'Харківська обл.'],
        ['name' => 'Дніпро',           'ref' => 'db5c88f0-391c-11dd-90d9-001a92567626', 'area' => 'Дніпропетровська обл.'],
        ['name' => 'Запоріжжя',        'ref' => 'db5c88c6-391c-11dd-90d9-001a92567626', 'area' => 'Запорізька обл.'],
        ['name' => 'Вінниця',          'ref' => 'db5c88de-391c-11dd-90d9-001a92567626', 'area' => 'Вінницька обл.'],
        ['name' => 'Полтава',          'ref' => 'db5c88d6-391c-11dd-90d9-001a92567626', 'area' => 'Полтавська обл.'],
        ['name' => 'Черкаси',          'ref' => 'db5c88f8-391c-11dd-90d9-001a92567626', 'area' => 'Черкаська обл.'],
        ['name' => 'Чернігів',         'ref' => 'db5c88ea-391c-11dd-90d9-001a92567626', 'area' => 'Чернігівська обл.'],
        ['name' => 'Суми',             'ref' => 'db5c88e4-391c-11dd-90d9-001a92567626', 'area' => 'Сумська обл.'],
        ['name' => 'Житомир',          'ref' => 'db5c88d8-391c-11dd-90d9-001a92567626', 'area' => 'Житомирська обл.'],
        ['name' => 'Івано-Франківськ', 'ref' => 'db5c88ec-391c-11dd-90d9-001a92567626', 'area' => 'Івано-Франківська обл.'],
        ['name' => 'Тернопіль',        'ref' => 'db5c88f3-391c-11dd-90d9-001a92567626', 'area' => 'Тернопільська обл.'],
        ['name' => 'Луцьк',            'ref' => 'db5c88f1-391c-11dd-90d9-001a92567626', 'area' => 'Волинська обл.'],
        ['name' => 'Рівне',            'ref' => 'db5c88e2-391c-11dd-90d9-001a92567626', 'area' => 'Рівненська обл.'],
        ['name' => 'Хмельницький',     'ref' => 'db5c88f7-391c-11dd-90d9-001a92567626', 'area' => 'Хмельницька обл.'],
        ['name' => 'Чернівці',         'ref' => 'db5c88d4-391c-11dd-90d9-001a92567626', 'area' => 'Чернівецька обл.'],
        ['name' => 'Ужгород',          'ref' => 'db5c88ef-391c-11dd-90d9-001a92567626', 'area' => 'Закарпатська обл.'],
        ['name' => 'Миколаїв',         'ref' => 'db5c88e9-391c-11dd-90d9-001a92567626', 'area' => 'Миколаївська обл.'],
        ['name' => 'Херсон',           'ref' => 'db5c88e6-391c-11dd-90d9-001a92567626', 'area' => 'Херсонська обл.'],
        ['name' => 'Кропивницький',    'ref' => 'db5c88d2-391c-11dd-90d9-001a92567626', 'area' => 'Кіровоградська обл.'],
        ['name' => 'Кривий Ріг',       'ref' => 'db5c88c7-391c-11dd-90d9-001a92567626', 'area' => 'Дніпропетровська обл.'],
        ['name' => 'Кременчук',        'ref' => 'db5c88cf-391c-11dd-90d9-001a92567626', 'area' => 'Полтавська обл.'],
        ['name' => 'Біла Церква',      'ref' => 'db5c88ee-391c-11dd-90d9-001a92567626', 'area' => 'Київська обл.'],
        ['name' => 'Бровари',          'ref' => 'db5c88b9-391c-11dd-90d9-001a92567626', 'area' => 'Київська обл.'],
        ['name' => 'Ірпінь',           'ref' => 'db5c88cd-391c-11dd-90d9-001a92567626', 'area' => 'Київська обл.'],
        ['name' => 'Буча',             'ref' => 'db5c88ba-391c-11dd-90d9-001a92567626', 'area' => 'Київська обл.'],
        ['name' => 'Бориспіль',        'ref' => 'db5c88b8-391c-11dd-90d9-001a92567626', 'area' => 'Київська обл.'],
        ['name' => 'Кам\'янець-Подільський', 'ref' => 'db5c88f6-391c-11dd-90d9-001a92567626', 'area' => 'Хмельницька обл.'],
    ];

    /**
     * Пошук міст та населених пунктів
     */
    public static function searchCities(string $query): array {
        $q = trim($query);
        if ($q === '') {
            return array_slice(self::POPULAR_CITIES, 0, 10);
        }

        $apiKey = Config::getNovaPoshtaApiKey();
        if (!empty($apiKey)) {
            $apiResults = self::searchCitiesApi($q, $apiKey);
            if (!empty($apiResults)) {
                return $apiResults;
            }
        }

        // Автономний пошук по локальній базі
        $qLower = mb_strtolower($q);
        $results = [];
        foreach (self::POPULAR_CITIES as $city) {
            if (str_contains(mb_strtolower($city['name']), $qLower)) {
                $results[] = $city;
            }
        }

        if (mb_strlen($q) >= 2) {
            $hasExact = false;
            foreach ($results as $r) {
                if (mb_strtolower($r['name']) === $qLower) {
                    $hasExact = true;
                    break;
                }
            }
            if (!$hasExact) {
                array_unshift($results, [
                    'name' => $q,
                    'ref'  => $q, // fallback plain name
                    'area' => 'Україна',
                ]);
            }
        }

        return $results;
    }

    /**
     * Отримання відділень та поштоматів для обраного міста
     */
    public static function getWarehouses(string $cityRef, string $cityName = ''): array {
        $apiKey = Config::getNovaPoshtaApiKey();
        if (empty($apiKey) || empty($cityRef)) {
            return [];
        }

        $cacheKey = md5($cityRef . '_' . $cityName);
        $cacheFile = Config::getCacheDir() . "np_wh_{$cacheKey}.json";

        if (file_exists($cacheFile) && (time() - filemtime($cacheFile)) < 86400) {
            $cached = json_decode((string)file_get_contents($cacheFile), true);
            if (is_array($cached)) return $cached;
        }

        $payload = [
            'apiKey'        => $apiKey,
            'modelName'     => 'AddressGeneral',
            'calledMethod'  => 'getWarehouses',
            'methodProperties' => [
                'CityRef'   => (strlen($cityRef) === 36) ? $cityRef : '',
                'CityName'  => (strlen($cityRef) !== 36) ? ($cityName ?: $cityRef) : '',
                'Limit'     => '150',
            ],
        ];

        $response = self::requestApi($payload);
        if (!empty($response['success']) && !empty($response['data'])) {
            $warehouses = [];
            foreach ($response['data'] as $wh) {
                $warehouses[] = [
                    'ref'         => (string)($wh['Ref'] ?? ''),
                    'name'        => (string)($wh['Description'] ?? ''),
                    'number'      => (string)($wh['Number'] ?? ''),
                    'type'        => (string)($wh['TypeOfWarehouse'] ?? ''),
                    'total_weight'=> (string)($wh['TotalMaxWeightAllowed'] ?? ''),
                ];
            }

            if (!is_dir(Config::getCacheDir())) {
                mkdir(Config::getCacheDir(), 0755, true);
            }
            file_put_contents($cacheFile, json_encode($warehouses, JSON_UNESCAPED_UNICODE));
            return $warehouses;
        }

        return [];
    }

    /**
     * Пошук міст через офіційне API Нової Пошти (searchSettlements)
     */
    private static function searchCitiesApi(string $query, string $apiKey): array {
        $cacheKey = md5('city_' . mb_strtolower($query));
        $cacheFile = Config::getCacheDir() . "np_c_{$cacheKey}.json";

        if (file_exists($cacheFile) && (time() - filemtime($cacheFile)) < 86400) {
            $cached = json_decode((string)file_get_contents($cacheFile), true);
            if (is_array($cached)) return $cached;
        }

        $payload = [
            'apiKey'        => $apiKey,
            'modelName'     => 'Address',
            'calledMethod'  => 'searchSettlements',
            'methodProperties' => [
                'CityName'  => $query,
                'Limit'     => '15',
            ],
        ];

        $response = self::requestApi($payload);
        if (!empty($response['success']) && !empty($response['data'][0]['Addresses'])) {
            $cities = [];
            foreach ($response['data'][0]['Addresses'] as $addr) {
                $cities[] = [
                    'name' => (string)($addr['MainDescription'] ?? $addr['Present'] ?? ''),
                    'ref'  => (string)($addr['DeliveryCity'] ?? $addr['Ref'] ?? ''),
                    'area' => (string)($addr['Area'] ?? $addr['Region'] ?? ''),
                ];
            }

            if (!is_dir(Config::getCacheDir())) {
                mkdir(Config::getCacheDir(), 0755, true);
            }
            file_put_contents($cacheFile, json_encode($cities, JSON_UNESCAPED_UNICODE));
            return $cities;
        }

        return [];
    }

    /**
     * cURL запит до API Нової Пошти
     */
    private static function requestApi(array $payload): array {
        $body = json_encode($payload, JSON_UNESCAPED_UNICODE);
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => self::API_URL,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 8,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $body,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Accept: application/json',
                'Content-Length: ' . strlen($body),
            ],
        ]);

        $res = curl_exec($ch);
        curl_close($ch);

        if (!$res) return [];
        return json_decode((string)$res, true) ?: [];
    }
}

