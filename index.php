<?php
declare(strict_types=1);

require_once __DIR__ . '/app/Config.php';
require_once __DIR__ . '/app/Database.php';
require_once __DIR__ . '/app/EndorPhone.php';
require_once __DIR__ . '/app/StockService.php';
require_once __DIR__ . '/app/CatalogService.php';
require_once __DIR__ . '/app/OrderService.php';

use App\Config;
use App\CatalogService;
use App\StockService;

Config::load();

// Фільтри
$currentCategory = trim((string)($_GET['cat'] ?? ''));
$currentSearch   = trim((string)($_GET['q'] ?? ''));
$rawPage         = max(1, (int)($_GET['page'] ?? 1));
$perPage         = 24;

// Отримання даних з бази SQLite / JSON
$categories = CatalogService::getCategories();
$totalDesignsCount = array_sum(array_column($categories, 'count'));

// Пошук активної категорії
$activeCategoryName = '';
$activeCategoryIcon = '';
foreach ($categories as $cat) {
    if ($cat['slug'] === $currentCategory) {
        $activeCategoryName = $cat['name'];
        $activeCategoryIcon = $cat['icon'];
        break;
    }
}

// Отримання дизайнів
$offset = ($rawPage - 1) * $perPage;
$catalogData = CatalogService::getDesigns($currentCategory, $currentSearch, $perPage, $offset);
$totalItems = $catalogData['total'];
$totalPages = max(1, (int)ceil($totalItems / $perPage));
$currentPage = min($rawPage, $totalPages);

// Якщо запитана сторінка більша за наявні сторінки
if ($currentPage !== $rawPage && $totalItems > 0) {
    $offset = ($currentPage - 1) * $perPage;
    $catalogData = CatalogService::getDesigns($currentCategory, $currentSearch, $perPage, $offset);
}
$designs = $catalogData['items'];

// Діапазон відображених товарів
$fromItem = $totalItems > 0 ? $offset + 1 : 0;
$toItem   = min($totalItems, $offset + count($designs));

// Базовий URL для SEO
$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'https';
$host = $_SERVER['HTTP_HOST'] ?? 'shopcase.top';
$baseUrl = "{$scheme}://{$host}";

// Динамічні SEO метадані
if ($currentCategory !== '' && $activeCategoryName !== '') {
    $pageTitle = "Чохли з принтами «{$activeCategoryName}» — купити чохол на телефон | ShopCase";
    $metaDescription = "Купити стильні чохли з колекції «{$activeCategoryName}» для 670+ моделей телефонів (iPhone, Samsung, Xiaomi, Poco тощо). УФ-друк, ударостійкі матеріали, швидка доставка Новою Поштою.";
    $canonicalUrl = ($currentPage > 1)
        ? "{$baseUrl}/category/" . rawurlencode($currentCategory) . "/page/{$currentPage}"
        : "{$baseUrl}/category/" . rawurlencode($currentCategory);
} elseif ($currentSearch !== '') {
    $pageTitle = "Пошук принтів «" . htmlspecialchars($currentSearch) . "» — Каталог чохлів | ShopCase";
    $metaDescription = "Результати пошуку чохлів за запитом «" . htmlspecialchars($currentSearch) . "». Знайдено {$totalItems} принтів. Обирайте дизайн та свій смартфон у каталозі ShopCase.";
    $canonicalUrl = "{$baseUrl}/?q=" . urlencode($currentSearch);
} elseif ($currentPage > 1) {
    $pageTitle = "Каталог авторських чохлів для смартфонів (Сторінка {$currentPage}) | ShopCase";
    $metaDescription = "Сторінка {$currentPage} каталогу авторських принтів на чохли для 670+ моделей телефонів. Висока якість друку, матеріали від силікону до MagSafe.";
    $canonicalUrl = "{$baseUrl}/page/{$currentPage}";
} else {
    $pageTitle = "ShopCase — Авторські чохли для 670+ смартфонів з доставкою по Україні";
    $metaDescription = "Яскраві чохли з якісним УФ-друком для 670+ моделей телефонів (iPhone, Samsung, Xiaomi, Pixel). Силікон, TPU, Bumper MagSafe, 3D пластик. Онлайн 3D-конструктор з власним фото!";
    $canonicalUrl = "{$baseUrl}/";
}

$ogImage = (!empty($designs[0]['image_path'])) ? "{$baseUrl}" . $designs[0]['image_path'] : "{$baseUrl}/design/made-in-ukraine/5293u-4029.jpg";

// Функція формування ЧПУ URL сторінки пагінації
function getCatalogPageUrl(int $page, string $category, string $search): string {
    if ($search !== '') {
        $params = ['q' => $search];
        if ($category !== '') {
            $params['cat'] = $category;
        }
        if ($page > 1) {
            $params['page'] = $page;
        }
        return '?' . http_build_query($params) . '#catalog';
    }

    if ($category !== '') {
        if ($page > 1) {
            return '/category/' . rawurlencode($category) . '/page/' . $page . '#catalog';
        }
        return '/category/' . rawurlencode($category) . '#catalog';
    }

    if ($page > 1) {
        return '/page/' . $page . '#catalog';
    }

    return '/#catalog';
}

// Функція генерації номерів сторінок із крапками
function getPaginationElements(int $currentPage, int $totalPages): array {
    if ($totalPages <= 7) {
        return range(1, $totalPages);
    }
    if ($currentPage <= 4) {
        return [1, 2, 3, 4, 5, '...', $totalPages];
    }
    if ($currentPage >= $totalPages - 3) {
        return [1, '...', $totalPages - 4, $totalPages - 3, $totalPages - 2, $totalPages - 1, $totalPages];
    }
    return [1, '...', $currentPage - 1, $currentPage, $currentPage + 1, '...', $totalPages];
}

// Дані моделей для швидкого клієнтського автокомпліту
$modelsForJs = StockService::getModelsForJs();

// Підключення шапки
include __DIR__ . '/views/layout/header.php';

// Секція Hero
include __DIR__ . '/views/components/hero.php';
?>

<!-- ========== MAIN CATALOG ========== -->
<section class="catalog" id="catalog">
  <div class="container">
    <div class="section-header">
      <h2 class="section-title">Каталог авторських принтів</h2>
      <p class="section-subtitle">Обирай принт, свій смартфон та матеріал чохла</p>
    </div>

    <!-- Фільтри та пошук -->
    <?php include __DIR__ . '/views/components/categories.php'; ?>

    <!-- Статистика пошуку -->
    <?php if ($currentSearch !== '' || $currentCategory !== ''): ?>
      <div class="catalog__results-info">
        <p>
          Знайдено: <strong><?= $totalItems ?></strong> принтів
          <?php if ($currentCategory !== ''): ?>
            · Категорія: <strong><?= htmlspecialchars($currentCategory) ?></strong>
          <?php endif; ?>
          <?php if ($currentSearch !== ''): ?>
            · Запит: <em>"<?= htmlspecialchars($currentSearch) ?>"</em>
          <?php endif; ?>
        </p>
        <a href="/#catalog" class="catalog__reset-link">✕ Скинути всі фільтри</a>
      </div>
    <?php endif; ?>

    <!-- Лічильник результатів -->
    <?php if ($totalItems > 0): ?>
      <div class="catalog__count">
        Показано <strong><?= $fromItem ?>–<?= $toItem ?></strong> з <strong><?= $totalItems ?></strong> принтів
        <?php if ($totalPages > 1): ?>
          · Сторінка <strong><?= $currentPage ?></strong> з <strong><?= $totalPages ?></strong>
        <?php endif; ?>
      </div>
    <?php endif; ?>

    <!-- Сітка принтів -->
    <div class="designs-grid" id="designsGrid">
      <?php if (empty($designs)): ?>
        <div class="catalog__empty" style="grid-column: 1 / -1;">
          <span class="catalog__empty-icon">🔍</span>
          <h3>Нічого не знайдено</h3>
          <p>Спробуйте змінити пошуковий запит або обрати іншу категорію</p>
          <a href="/#catalog" class="btn btn--outline" style="margin-top: 16px;">Показати всі принти</a>
        </div>
      <?php else: ?>
        <?php foreach ($designs as $i => $design):
          $designJson = htmlspecialchars(json_encode([
            'id'            => (int)$design['id'],
            'name'          => $design['name'],
            'category_slug' => $design['category_slug'],
            'category_name' => $design['category_name'],
            'image'         => $design['image_path'],
          ], JSON_UNESCAPED_UNICODE), ENT_QUOTES);
        ?>
          <div class="design-card"
               style="animation-delay: <?= ($i % 12) * 30 ?>ms"
               onclick="Customizer.open(<?= $designJson ?>)"
               role="button"
               tabindex="0"
               aria-label="<?= htmlspecialchars($design['name']) ?>">

            <div class="design-card__image-wrap">
              <img
                class="design-card__image"
                src="<?= htmlspecialchars($design['image_path']) ?>"
                alt="<?= htmlspecialchars($design['name']) ?>"
                loading="lazy"
                onerror="this.onerror=null; this.src='/design/made-in-ukraine/5293u-4029.jpg';"
              />
              <?php if (!empty($design['is_top'])): ?>
                <span class="design-card__badge">🔥 Топ</span>
              <?php endif; ?>
              <span class="design-card__category"><?= htmlspecialchars($design['category_name']) ?></span>
              <div class="design-card__overlay">
                <button class="btn btn--primary btn--sm" onclick="event.stopPropagation(); Customizer.open(<?= $designJson ?>)">
                  Обрати свій телефон
                </button>
              </div>
            </div>

            <div class="design-card__body">
              <h3 class="design-card__title"><?= htmlspecialchars($design['name']) ?></h3>
              <div class="design-card__footer">
                <span class="design-card__price">від <?= \App\Config::getMinPrice() ?> ₴</span>
                <span class="design-card__action">Обрати →</span>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>

    <!-- Пагінація -->
    <?php if ($totalPages > 1): ?>
      <nav class="pagination" aria-label="Сторінки каталогу">
        <?php if ($currentPage > 1): ?>
          <a href="<?= getCatalogPageUrl($currentPage - 1, $currentCategory, $currentSearch) ?>"
             class="pagination__btn pagination__btn--prev"
             aria-label="Попередня сторінка">← Назад</a>
        <?php endif; ?>

        <?php foreach (getPaginationElements($currentPage, $totalPages) as $elem): ?>
          <?php if ($elem === '...'): ?>
            <span class="pagination__dots" aria-hidden="true">…</span>
          <?php else: ?>
            <a href="<?= getCatalogPageUrl((int)$elem, $currentCategory, $currentSearch) ?>"
               class="pagination__btn <?= ($elem === $currentPage) ? 'pagination__btn--active' : '' ?>"
               <?= ($elem === $currentPage) ? 'aria-current="page"' : '' ?>
               aria-label="Сторінка <?= $elem ?><?= ($elem === $currentPage) ? ', поточна' : '' ?>">
              <?= $elem ?>
            </a>
          <?php endif; ?>
        <?php endforeach; ?>

        <?php if ($currentPage < $totalPages): ?>
          <a href="<?= getCatalogPageUrl($currentPage + 1, $currentCategory, $currentSearch) ?>"
             class="pagination__btn pagination__btn--next"
             aria-label="Наступна сторінка">Далі →</a>
        <?php endif; ?>
      </nav>
    <?php endif; ?>

  </div>
</section>

<?php
// Банер онлайн-конструктора чохлів з власним фото
include __DIR__ . '/views/components/custom-banner.php';

// Як це працює
include __DIR__ . '/views/components/how-it-works.php';

// Переваги
include __DIR__ . '/views/components/advantages.php';

// Підвал та модальні вікна
include __DIR__ . '/views/layout/footer.php';
