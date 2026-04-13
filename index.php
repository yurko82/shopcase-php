<?php
/**
 * index.php
 * Головна сторінка ShopCase
 * Каталог рендериться на сервері через EndorPhone API
 */

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/endorphone.php';
require_once __DIR__ . '/includes/catalog.php';

$pageTitle = 'ShopCase — Яскраві чохли для вашого телефону';

/* ── Завантажуємо stock з API (або кеш) ── */
$api      = new EndorPhone();
$stock    = $api->getAllStock();

/* ── Завантажуємо каталог дизайнів з CSV ── */
$csvProducts  = Catalog::getProducts();
$csvCategories = Catalog::getCategories($csvProducts);
$filterDesignCat = trim($_GET['design_cat'] ?? '');
$randomDesigns = Catalog::getRandomDesigns($csvProducts, 12, $filterDesignCat);
$hasStock = !empty($stock);

/* ── Фільтрація через GET параметри ── */
$filterModel = trim($_GET['model'] ?? '');
$filterMat   = trim($_GET['mat'] ?? '');

$filtered = $stock;

if ($filterModel !== '') {
    $filtered = array_filter($filtered, fn($item) =>
        (string)$item['id'] === $filterModel
    );
}

if ($filterMat !== '' && isset(MATERIAL_LABELS[$filterMat])) {
    $filtered = array_filter($filtered, fn($item) =>
        !empty($item[$filterMat])
    );
}

$filtered = array_values($filtered);

/* ── Групуємо моделі для select ── */
$modelGroups = [];
foreach ($stock as $item) {
    $brand = explode(' ', $item['name'])[0];
    $modelGroups[$brand][] = $item;
}
ksort($modelGroups);

/* ── Пагінація ── */
$perPage     = 12;
$totalItems  = count($filtered);
$totalPages  = max(1, (int)ceil($totalItems / $perPage));
$currentPage = max(1, min((int)($_GET['page'] ?? 1), $totalPages));
$offset      = ($currentPage - 1) * $perPage;
$pageItems   = array_slice($filtered, $offset, $perPage);

include __DIR__ . '/includes/header.php';
?>

<!-- ========== HERO ========== -->
<section class="hero" id="hero">
  <div class="hero__bg-blobs" aria-hidden="true">
    <div class="blob blob--1"></div>
    <div class="blob blob--2"></div>
    <div class="blob blob--3"></div>
  </div>
  <div class="container hero__inner">
    <div class="hero__content">
      <span class="hero__badge">🔥 Новинки вже в каталозі</span>
      <h1 class="hero__title">
        Чохол — це твій<br/>
        <span class="hero__title-accent">стиль і захист</span>
      </h1>
      <p class="hero__subtitle">
        Друкуємо яскраві чохли для будь-якого смартфона.<br/>
        Висока якість, швидка доставка Новою Поштою.
      </p>
      <div class="hero__actions">
        <a href="#catalog" class="btn btn--primary btn--lg">Переглянути каталог</a>
        <a href="#how-it-works" class="btn btn--outline btn--lg">Як це працює</a>
      </div>
      <div class="hero__stats">
        <div class="hero__stat">
          <span class="hero__stat-num"><?= count($stock) ?>+</span>
          <span class="hero__stat-label">моделей</span>
        </div>
        <div class="hero__stat-divider"></div>
        <div class="hero__stat">
          <span class="hero__stat-num">8</span>
          <span class="hero__stat-label">матеріалів</span>
        </div>
        <div class="hero__stat-divider"></div>
        <div class="hero__stat">
          <span class="hero__stat-num">1–3</span>
          <span class="hero__stat-label">дні доставки</span>
        </div>
      </div>
    </div>
    <div class="hero__visual" aria-hidden="true">
      <div class="hero__phone-mockup">
        <div class="phone-frame">
          <div class="phone-screen">
            <div class="phone-gradient"></div>
          </div>
        </div>
        <div class="hero__float hero__float--1">силікон</div>
        <div class="hero__float hero__float--2">3D пластик</div>
        <div class="hero__float hero__float--3">TPU</div>
      </div>
    </div>
  </div>
</section>


<!-- ========== DESIGNS ========== -->
<section class="designs" id="designs">
  <div class="container">
    <div class="section-header">
      <h2 class="section-title">Дизайни чохлів</h2>
      <p class="section-subtitle">Обирайте з <?= count(Catalog::getUniqueDesigns($csvProducts)) ?>+ унікальних дизайнів для будь-якого смартфона</p>
    </div>

    <!-- Категорії дизайнів -->
    <div class="designs__cats">
      <a href="/?#designs" class="chip <?= $filterDesignCat === '' ? 'chip--active' : '' ?>">Всі</a>
      <?php foreach ($csvCategories as $cat => $cnt): ?>
        <a href="/?design_cat=<?= urlencode($cat) ?>#designs"
           class="chip <?= $filterDesignCat === $cat ? 'chip--active' : '' ?>">
          <?= htmlspecialchars($cat) ?>
          <span class="chip__count"><?= $cnt ?></span>
        </a>
      <?php endforeach; ?>
    </div>

    <!-- Сітка дизайнів -->
    <?php if (empty($randomDesigns)): ?>
      <div class="catalog__empty">
        <span class="catalog__empty-icon">🎨</span>
        <p>Дизайни завантажуються...</p>
      </div>
    <?php else: ?>
    <div class="designs__grid" id="designsGrid">
      <?php foreach ($randomDesigns as $i => $design): ?>
        <div class="design-card" style="animation-delay:<?= $i * 50 ?>ms"
             onclick="openDesignModal(<?= htmlspecialchars(json_encode([
               'design'   => $design['design'],
               'category' => $design['category'],
               'image'    => $design['image'],
               'price'    => $design['price'],
               'count'    => $design['count'],
             ]), ENT_QUOTES) ?>)"
             role="button" tabindex="0">
          <div class="design-card__image-wrap">
            <img
              class="design-card__image"
              src="<?= htmlspecialchars($design['image']) ?>"
              alt="<?= htmlspecialchars($design['design']) ?>"
              loading="lazy"
              onerror="this.parentElement.innerHTML='<div class='design-card__img-fallback'>🎨</div>'"
            />
            <?php if ($design['top']): ?>
              <span class="design-card__top">🔥 Топ</span>
            <?php endif; ?>
            <div class="design-card__overlay">
              <button class="design-card__order-btn">Замовити</button>
            </div>
          </div>
          <div class="design-card__body">
            <p class="design-card__cat"><?= htmlspecialchars($design['category']) ?></p>
            <p class="design-card__name"><?= htmlspecialchars($design['design']) ?></p>
            <p class="design-card__price">від <?= $design['price'] ?> ₴</p>
          </div>
        </div>
      <?php endforeach; ?>
    </div>

    <div class="designs__footer">
      <a href="/?design_cat=<?= urlencode($filterDesignCat) ?>#designs"
         class="btn btn--outline btn--lg" id="shuffleDesigns"
         onclick="event.preventDefault();window.location.reload()">
        🔀 Показати інші дизайни
      </a>
    </div>
    <?php endif; ?>
  </div>
</section>

<!-- DESIGN MODAL -->
<div class="modal-overlay hidden" id="designModal" role="dialog" aria-modal="true">
  <div class="modal modal--design">
    <button class="modal__close" id="designModalClose" aria-label="Закрити">✕</button>
    <div id="designModalBody"></div>
  </div>
</div>

<!-- ========== CATALOG ========== -->
<section class="catalog" id="catalog">
  <div class="container">
    <div class="section-header">
      <h2 class="section-title">Каталог чохлів</h2>
      <p class="section-subtitle">Оберіть модель телефону та тип матеріалу</p>
    </div>

    <!-- Filters — form GET -->
    <form class="filters" id="filters" method="GET" action="/#catalog">

      <!-- Прихований input для передачі model в GET -->
      <input type="hidden" name="model" id="modelHidden" value="<?= htmlspecialchars($filterModel) ?>" />

      <div class="filter-group">
        <label class="filter-label" for="modelSearch">Модель телефону</label>
        <div class="model-search" id="modelSearch">
          <div class="model-search__input-wrap">
            <svg class="model-search__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
            <input
              class="model-search__input"
              id="modelSearchInput"
              type="text"
              placeholder="Введіть назву: iPhone 15, Samsung S24..."
              autocomplete="off"
              value="<?php
                if ($filterModel !== '') {
                  foreach ($stock as $s) {
                    if ((string)$s['id'] === $filterModel) {
                      echo htmlspecialchars($s['name']);
                      break;
                    }
                  }
                }
              ?>"
            />
            <button type="button" class="model-search__clear <?= $filterModel === '' ? 'hidden' : '' ?>" id="modelSearchClear" title="Скинути">✕</button>
          </div>
          <ul class="model-search__dropdown hidden" id="modelDropdown" role="listbox"></ul>
        </div>
      </div>

      <div class="filter-group">
        <label class="filter-label">Матеріал</label>
        <div class="filter-chips">
          <a href="<?= '/?model=' . urlencode($filterModel) . '#catalog' ?>"
             class="chip <?= $filterMat === '' ? 'chip--active' : '' ?>">
            Всі
          </a>
          <?php foreach (MATERIAL_LABELS as $key => $label): ?>
            <a href="<?= '/?model=' . urlencode($filterModel) . '&mat=' . $key . '#catalog' ?>"
               class="chip <?= $filterMat === $key ? 'chip--active' : '' ?>">
              <?= htmlspecialchars($label) ?>
            </a>
          <?php endforeach; ?>
        </div>
      </div>
    </form>

    <!-- Результат фільтрації -->
    <?php if ($hasStock && $totalItems > 0): ?>
      <p class="catalog__count">
        Знайдено: <strong><?= $totalItems ?></strong> моделей
        <?php if ($filterMat !== ''): ?>
          · матеріал: <strong><?= htmlspecialchars(MATERIAL_LABELS[$filterMat]) ?></strong>
        <?php endif; ?>
      </p>
    <?php endif; ?>

    <!-- Product Grid -->
    <div class="catalog__grid" id="catalogGrid">

      <?php if (!$hasStock): ?>
        <!-- Помилка завантаження -->
        <div class="catalog__empty" style="grid-column:1/-1">
          <span class="catalog__empty-icon">⚠️</span>
          <p>Не вдалося завантажити товари. Спробуйте оновити сторінку.</p>
        </div>

      <?php elseif (empty($pageItems)): ?>
        <!-- Порожній результат фільтрації -->
        <div class="catalog__empty" style="grid-column:1/-1">
          <span class="catalog__empty-icon">🔍</span>
          <p>Нічого не знайдено. Спробуйте змінити фільтри.</p>
          <a href="/" class="btn btn--outline" style="margin-top:16px">Скинути фільтри</a>
        </div>

      <?php else: ?>
        <!-- Картки товарів -->
        <?php foreach ($pageItems as $i => $item):
          $availableMats = EndorPhone::getAvailableMaterials($item);
          $minPrice      = EndorPhone::getMinPrice($item);
          $isAvailable   = !empty($availableMats);
          $firstMat      = $availableMats[0] ?? null;
          $emoji         = EndorPhone::getBrandEmoji($item['name']);
          $brand         = explode(' ', $item['name'])[0];
          $modelName     = implode(' ', array_slice(explode(' ', $item['name']), 1));

          // Шукаємо фото для цієї моделі з CSV (будь-який дизайн)
          $csvPhoto = '';
          foreach ($csvProducts as $csvP) {
              $parts = explode('-', $csvP['code']);
              $csvModelId = end($parts);
              if ($csvModelId === (string)$item['id'] && !empty($csvP['image'])) {
                  $csvPhoto = $csvP['image'];
                  break;
              }
          }

          // JSON для JS модалки — будуємо mats явно
          $mats = [];
          foreach (array_keys(MATERIAL_LABELS) as $matKey) {
              $mats[$matKey] = isset($item[$matKey]) && $item[$matKey] === true;
          }
          $itemJson = htmlspecialchars(json_encode([
            'id'   => $item['id'],
            'name' => $item['name'],
            'mats' => $mats,
          ]), ENT_QUOTES);
        ?>
        <div class="card <?= !$isAvailable ? 'card--unavailable' : '' ?>"
             style="animation-delay:<?= ($i % $perPage) * 40 ?>ms"
             <?= $isAvailable ? "onclick=\"Modal.openProduct($itemJson)\"" : '' ?>
             role="<?= $isAvailable ? 'button' : 'article' ?>"
             tabindex="<?= $isAvailable ? '0' : '-1' ?>"
             aria-label="<?= htmlspecialchars($item['name']) ?>">

          <!-- Зображення -->
          <div class="card__image-wrap">
            <?php if ($firstMat): ?>
              <span class="card__badge">
                <?= htmlspecialchars(MATERIAL_LABELS[$firstMat]) ?>
              </span>
            <?php endif; ?>

            <?php if ($csvPhoto): ?>
              <img class="card__image"
                   src="<?= htmlspecialchars($csvPhoto) ?>"
                   alt="<?= htmlspecialchars($item['name']) ?>"
                   loading="lazy"
                   onerror="this.style.display='none';this.nextElementSibling.style.display='flex'" />
              <div class="card__image-placeholder" style="display:none"><?= $emoji ?></div>
            <?php else: ?>
              <div class="card__image-placeholder"><?= $emoji ?></div>
            <?php endif; ?>

            <?php if (!$isAvailable): ?>
              <div class="card__out-of-stock">
                <span>Немає в наявності</span>
              </div>
            <?php endif; ?>
          </div>

          <!-- Body -->
          <div class="card__body">
            <p class="card__model"><?= htmlspecialchars($brand) ?></p>
            <p class="card__name"><?= htmlspecialchars($modelName) ?></p>

            <!-- Крапки матеріалів -->
            <div class="card__materials">
              <?php foreach (MATERIAL_LABELS as $key => $label): ?>
                <span class="card__mat-dot <?= !empty($item[$key]) ? 'card__mat-dot--available' : '' ?>"
                      title="<?= htmlspecialchars($label) ?>"></span>
              <?php endforeach; ?>
            </div>

            <!-- Footer картки -->
            <div class="card__footer">
              <div>
                <p class="card__price">
                  <?= $isAvailable ? 'від ' . $minPrice . ' ₴' : '—' ?>
                </p>
                <p class="card__price-sub">
                  <?= $isAvailable ? 'накладений або передоплата' : '' ?>
                </p>
              </div>
              <button class="card__btn"
                      <?= !$isAvailable ? 'disabled' : '' ?>
                      <?= $isAvailable ? "onclick=\"event.stopPropagation();Modal.openProduct($itemJson)\"" : '' ?>>
                <?= $isAvailable ? 'Обрати' : 'Немає' ?>
              </button>
            </div>
          </div>

        </div>
        <?php endforeach; ?>

      <?php endif; ?>
    </div><!-- /catalogGrid -->

    <!-- Пагінація -->
    <?php if ($totalPages > 1): ?>
      <nav class="pagination" aria-label="Сторінки каталогу">
        <?php if ($currentPage > 1): ?>
          <a href="?model=<?= urlencode($filterModel) ?>&mat=<?= urlencode($filterMat) ?>&page=<?= $currentPage - 1 ?>#catalog"
             class="pagination__btn">← Назад</a>
        <?php endif; ?>

        <?php for ($p = 1; $p <= $totalPages; $p++): ?>
          <a href="?model=<?= urlencode($filterModel) ?>&mat=<?= urlencode($filterMat) ?>&page=<?= $p ?>#catalog"
             class="pagination__btn <?= $p === $currentPage ? 'pagination__btn--active' : '' ?>">
            <?= $p ?>
          </a>
        <?php endfor; ?>

        <?php if ($currentPage < $totalPages): ?>
          <a href="?model=<?= urlencode($filterModel) ?>&mat=<?= urlencode($filterMat) ?>&page=<?= $currentPage + 1 ?>#catalog"
             class="pagination__btn">Далі →</a>
        <?php endif; ?>
      </nav>
    <?php endif; ?>

  </div>
</section>

<!-- Дані моделей для JS пошуку -->
<script>
window.SHOPCASE_MODELS = <?php
  $modelsForJs = array_map(fn($item) => [
    'id'    => (string)$item['id'],
    'name'  => $item['name'],
    'brand' => explode(' ', $item['name'])[0],
    'avail' => count(EndorPhone::getAvailableMaterials($item)),
  ], $stock);
  echo json_encode($modelsForJs, JSON_UNESCAPED_UNICODE);
?>;
</script>

<!-- ========== HOW IT WORKS ========== -->
<section class="how-it-works" id="how-it-works">
  <div class="container">
    <div class="section-header">
      <h2 class="section-title">Як це працює</h2>
      <p class="section-subtitle">Три простих кроки до вашого ідеального чохла</p>
    </div>
    <div class="steps">
      <div class="step">
        <div class="step__num">01</div>
        <div class="step__icon">🔍</div>
        <h3 class="step__title">Оберіть чохол</h3>
        <p class="step__text">Знайдіть модель свого телефону та оберіть тип матеріалу до смаку</p>
      </div>
      <div class="step__arrow" aria-hidden="true">→</div>
      <div class="step">
        <div class="step__num">02</div>
        <div class="step__icon">📦</div>
        <h3 class="step__title">Оформіть замовлення</h3>
        <p class="step__text">Заповніть форму — ім'я, телефон та відділення Нової Пошти</p>
      </div>
      <div class="step__arrow" aria-hidden="true">→</div>
      <div class="step">
        <div class="step__num">03</div>
        <div class="step__icon">🚀</div>
        <h3 class="step__title">Отримайте швидко</h3>
        <p class="step__text">Відправляємо протягом 1–3 днів. Доставка по всій Україні</p>
      </div>
    </div>
  </div>
</section>

<!-- ========== ADVANTAGES ========== -->
<section class="advantages" id="advantages">
  <div class="container">
    <div class="section-header">
      <h2 class="section-title">Чому ми?</h2>
    </div>
    <div class="advantages__grid">
      <div class="advantage-card">
        <div class="advantage-card__icon">🎨</div>
        <h3 class="advantage-card__title">Яскравий друк</h3>
        <p class="advantage-card__text">УФ-друк з насиченими кольорами, стійкий до стирання</p>
      </div>
      <div class="advantage-card">
        <div class="advantage-card__icon">🛡️</div>
        <h3 class="advantage-card__title">Надійний захист</h3>
        <p class="advantage-card__text">Матеріали амортизують удари та захищають від подряпин</p>
      </div>
      <div class="advantage-card">
        <div class="advantage-card__icon">📮</div>
        <h3 class="advantage-card__title">Нова Пошта</h3>
        <p class="advantage-card__text">Доставка по всій Україні. Оплата при отриманні або онлайн</p>
      </div>
      <div class="advantage-card">
        <div class="advantage-card__icon">⚡</div>
        <h3 class="advantage-card__title">Швидко</h3>
        <p class="advantage-card__text">Виробництво та відправка протягом 1–3 робочих днів</p>
      </div>
    </div>
  </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
