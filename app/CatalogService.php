<?php
declare(strict_types=1);

namespace App;

/**
 * CatalogService
 * Фільтрація, розумний пошук з урахуванням тегів та синонімів, та отримання принтів
 */
class CatalogService {

    public static function getCategories(): array {
        $designs = Database::getDesignsData();
        $counts = [];
        foreach ($designs as $d) {
            $cat = $d['category_slug'];
            $counts[$cat] = ($counts[$cat] ?? 0) + 1;
        }

        $result = [];
        foreach (Database::getCategoriesData() as $c) {
            $cnt = $counts[$c['slug']] ?? 0;
            if ($cnt > 0) {
                $result[] = [
                    'slug'  => $c['slug'],
                    'name'  => $c['name'],
                    'icon'  => $c['icon'],
                    'count' => $cnt,
                ];
            }
        }

        return $result;
    }

    /**
     * Очистити та нормалізувати слово для пошуку (стемінг та нормалізація)
     */
    private static function normalizeWord(string $word): string {
        $w = mb_strtolower(trim($word));
        // Заміна російських або специфічних символів
        $w = str_replace(['ё', 'ъ', 'ы'], ['е', '', 'и'], $w);
        return $w;
    }

    /**
     * Отримати варіанти коренів для популярних пошукових слів
     */
    private static function getStemVariants(string $term): array {
        $term = self::normalizeWord($term);
        if ($term === '') return [];

        $variants = [$term];

        // Синоніми та форми для котів
        if (preg_match('/^(кіт|кот|котик|коти|кота|котики|котя|котеня|кошеня|кошенята|кішка|киця|котячий|cat|kitten)/iu', $term)) {
            $variants = array_merge($variants, ['кіт', 'кот', 'котик', 'кошеня', 'коти', 'кішка', 'cat', 'kitten']);
        }
        // Собаки
        elseif (preg_match('/^(собак|пес|песик|песики|собака|собаки|собачка|щеня|цуценя|цуцик|dog|puppy)/iu', $term)) {
            $variants = array_merge($variants, ['собака', 'пес', 'песик', 'цуценя', 'йорк', 'спанієль', 'мальтіпу', 'коргі', 'мопс', 'хаскі', 'dog', 'puppy']);
        }
        // Герб / Тризуб / Україна
        elseif (preg_match('/^(герб|тризуб|украін|украин|україна|патріот|зсу|прапор)/iu', $term)) {
            $variants = array_merge($variants, ['герб', 'тризуб', 'україна', 'прапор', 'патріотичний', 'ukraine', 'воля']);
        }
        // Квіти / Сакура
        elseif (preg_match('/^(квіт|цветок|цветы|сакур|рози|троянд|півон|ромашк|лаванд)/iu', $term)) {
            $variants = array_merge($variants, ['квіти', 'квітка', 'сакура', 'троянда', 'півонія', 'ромашка', 'лаванда', 'flowers', 'rose']);
        }
        // Вовк
        elseif (preg_match('/^(вовк|волк|вовки|волки|wolf)/iu', $term)) {
            $variants = array_merge($variants, ['вовк', 'волк', 'wolf']);
        }
        // Авто / Бренди
        elseif (preg_match('/^(авто|машин|бмв|bmw|мерс|audi|спорткар)/iu', $term)) {
            $variants = array_merge($variants, ['авто', 'машина', 'бмв', 'bmw', 'спорткар', 'car']);
        }
        // Аніме
        elseif (preg_match('/^(аніме|аниме|anime|наруто|манга)/iu', $term)) {
            $variants = array_merge($variants, ['аніме', 'anime', 'манга', 'наруто', 'тянка']);
        }
        // Космос
        elseif (preg_match('/^(космос|планет|місяц|зорі|зірк|астронавт|space)/iu', $term)) {
            $variants = array_merge($variants, ['космос', 'планета', 'місяць', 'зорі', 'астронавт', 'space']);
        }
        // Любов
        elseif (preg_match('/^(любов|кохан|серц|сердечк|love)/iu', $term)) {
            $variants = array_merge($variants, ['любов', 'кохання', 'серце', 'love']);
        }
        // Прикольні / Меми
        elseif (preg_match('/^(прикол|смішн|мем|жарт|fun|meme)/iu', $term)) {
            $variants = array_merge($variants, ['прикол', 'мем', 'смішний', 'качка', 'жаба', 'гумор', 'fun']);
        }

        return array_unique($variants);
    }

    public static function getDesigns(
        string $categorySlug = '',
        string $search = '',
        int $limit = 24,
        int $offset = 0
    ): array {
        $all = Database::getDesignsData();
        $filtered = $all;

        if ($categorySlug !== '') {
            $filtered = array_filter($filtered, fn($d) => $d['category_slug'] === $categorySlug);
        }

        if ($search !== '') {
            $rawQuery = trim($search);
            $queryLower = mb_strtolower($rawQuery);
            $queryTokens = preg_split('/\s+/u', $queryLower, -1, PREG_SPLIT_NO_EMPTY);

            $scored = [];

            foreach ($filtered as $d) {
                $designNameLower = mb_strtolower($d['name'] ?? '');
                $categoryNameLower = mb_strtolower($d['category_name'] ?? '');
                $categorySlug = $d['category_slug'] ?? '';
                $designIdStr = (string)($d['id'] ?? '');
                $tags = array_map('mb_strtolower', $d['tags'] ?? []);
                $catKeywords = array_map('mb_strtolower', Database::CATEGORY_KEYWORDS[$categorySlug] ?? []);

                $score = 0;

                // 1. Пошук за ID
                if ($designIdStr === $queryLower) {
                    $score += 1000;
                }

                // 2. Точний збіг назви
                if ($designNameLower === $queryLower) {
                    $score += 500;
                } elseif (str_contains($designNameLower, $queryLower)) {
                    $score += 200;
                }

                // 3. Перевірка кожного токена запиту
                $allTokensMatch = true;

                foreach ($queryTokens as $token) {
                    $tokenVariants = self::getStemVariants($token);
                    $tokenMatched = false;

                    foreach ($tokenVariants as $variant) {
                        // Збіг з ID дизайну
                        if ($designIdStr === $variant || str_contains($designIdStr, $variant)) {
                            $score += 500;
                            $tokenMatched = true;
                        }

                        // Збіг з тегами
                        foreach ($tags as $tag) {
                            if ($tag === $variant) {
                                $score += 150;
                                $tokenMatched = true;
                                break;
                            } elseif (str_contains($tag, $variant) || str_contains($variant, $tag)) {
                                $score += 80;
                                $tokenMatched = true;
                                break;
                            }
                        }

                        // Збіг з назвою
                        if (str_contains($designNameLower, $variant)) {
                            $score += 100;
                            $tokenMatched = true;
                        }

                        // Збіг з назвою категорії
                        if (str_contains($categoryNameLower, $variant) || $categorySlug === $variant) {
                            $score += 60;
                            $tokenMatched = true;
                        }

                        // Збіг з ключовими словами категорії
                        if (in_array($variant, $catKeywords, true)) {
                            $score += 40;
                            $tokenMatched = true;
                        }

                        if ($tokenMatched) break;
                    }

                    if (!$tokenMatched) {
                        $allTokensMatch = false;
                        break;
                    }
                }

                if ($allTokensMatch && $score > 0) {
                    $scored[] = [
                        'design' => $d,
                        'score'  => $score
                    ];
                }
            }

            // Сортуємо за релевантністю
            usort($scored, function($a, $b) {
                if ($a['score'] !== $b['score']) {
                    return $b['score'] <=> $a['score'];
                }
                if ($a['design']['is_top'] !== $b['design']['is_top']) {
                    return $b['design']['is_top'] <=> $a['design']['is_top'];
                }
                return $a['design']['id'] <=> $b['design']['id'];
            });

            $filtered = array_column($scored, 'design');
        } else {
            $filtered = array_values($filtered);

            // Сортуємо: спочатку ТОП
            usort($filtered, function($a, $b) {
                if ($a['is_top'] !== $b['is_top']) {
                    return $b['is_top'] <=> $a['is_top'];
                }
                return $a['id'] <=> $b['id'];
            });
        }

        $total = count($filtered);
        $items = array_slice($filtered, $offset, $limit);

        return [
            'total'  => $total,
            'limit'  => $limit,
            'offset' => $offset,
            'items'  => $items,
        ];
    }

    public static function getDesignById(int $id): ?array {
        $all = Database::getDesignsData();
        foreach ($all as $d) {
            if ($d['id'] === $id) return $d;
        }
        return null;
    }
}
