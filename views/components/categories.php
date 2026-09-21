<!-- ========== CATEGORIES & SEARCH ========== -->
<div class="catalog-filters">
  <!-- Search Bar -->
  <div class="catalog-search-wrap">
    <div class="catalog-search">
      <span class="catalog-search__icon">🔍</span>
      <input
        type="text"
        class="catalog-search__input"
        id="designSearchInput"
        placeholder="Пошук принту (наприклад: герб, кіт, сакура, аніме, bmw...)"
        value="<?= htmlspecialchars($currentSearch ?? '') ?>"
      />
      <button type="button" class="catalog-search__clear <?= empty($currentSearch) ? 'hidden' : '' ?>" id="designSearchClear">✕</button>
    </div>

    <!-- Quick Search Tags -->
    <div class="quick-tags">
      <span class="quick-tags__title">Часті запити:</span>
      <a href="?q=<?= urlencode('кіт') ?>#catalog" class="quick-tag <?= ($currentSearch === 'кіт') ? 'quick-tag--active' : '' ?>">🐱 Котики</a>
      <a href="?q=<?= urlencode('герб') ?>#catalog" class="quick-tag <?= ($currentSearch === 'герб') ? 'quick-tag--active' : '' ?>">🇺🇦 Герб України</a>
      <a href="?q=<?= urlencode('собака') ?>#catalog" class="quick-tag <?= ($currentSearch === 'собака') ? 'quick-tag--active' : '' ?>">🐶 Песики</a>
      <a href="?q=<?= urlencode('сакура') ?>#catalog" class="quick-tag <?= ($currentSearch === 'сакура') ? 'quick-tag--active' : '' ?>">🌸 Сакура</a>
      <a href="?q=<?= urlencode('аніме') ?>#catalog" class="quick-tag <?= ($currentSearch === 'аніме') ? 'quick-tag--active' : '' ?>">⚡ Аніме</a>
      <a href="?q=<?= urlencode('капібара') ?>#catalog" class="quick-tag <?= ($currentSearch === 'капібара') ? 'quick-tag--active' : '' ?>">🦫 Капібара</a>
      <a href="?q=<?= urlencode('бмв') ?>#catalog" class="quick-tag <?= ($currentSearch === 'бмв') ? 'quick-tag--active' : '' ?>">🏎️ Авто</a>
    </div>
  </div>

  <!-- Category Chips -->
  <div class="category-chips" id="categoryChips">
    <a href="/#catalog" class="chip <?= ($currentCategory === '' && $currentSearch === '') ? 'chip--active' : '' ?>" data-slug="">
      <span>🔥</span>
      <span>Всі принти</span>
      <span class="chip__count"><?= (int)$totalDesignsCount ?></span>
    </a>
    <?php foreach ($categories as $cat): ?>
      <a href="/category/<?= rawurlencode($cat['slug']) ?>#catalog"
         class="chip <?= ($currentCategory === $cat['slug']) ? 'chip--active' : '' ?>"
         data-slug="<?= htmlspecialchars($cat['slug']) ?>">
        <span><?= htmlspecialchars($cat['icon']) ?></span>
        <span><?= htmlspecialchars($cat['name']) ?></span>
        <span class="chip__count"><?= (int)$cat['count'] ?></span>
      </a>
    <?php endforeach; ?>
  </div>
</div>
