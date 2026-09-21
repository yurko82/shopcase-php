<!-- ========== HERO ========== -->
<section class="hero" id="hero">
  <div class="hero__bg-blobs" aria-hidden="true">
    <div class="blob blob--1"></div>
    <div class="blob blob--2"></div>
    <div class="blob blob--3"></div>
  </div>

  <div class="container hero__inner">
    <!-- Ліва колонка: Текст, підбір моделі та дії -->
    <div class="hero__content">
      <div class="hero__badge-wrap">
        <span class="hero__badge">🇺🇦 Якісний УФ-друк в Україні</span>
        <span class="hero__badge-promo">🔥 670+ моделей у наявності</span>
      </div>

      <h1 class="hero__title">
        Чохол — це твій<br/>
        <span class="hero__title-accent">стиль і надійний захист</span>
      </h1>

      <p class="hero__subtitle">
        Обирай авторський принт з каталогу або завантажуй власне фото у 3D-конструкторі.
        Японський стійкий друк, відправка Новою Поштою по всій Україні без передоплати.
      </p>

      <!-- Швидкий підбір моделі смартфона прямо в Hero -->
      <div class="hero__finder" id="heroFinder">
        <div class="hero__finder-head">
          <label class="hero__finder-label" for="heroModelInput">
            <span>📱 Оберіть ваш смартфон:</span>
          </label>
          <span class="hero__finder-count">670+ моделей</span>
        </div>

        <div class="hero__finder-box">
          <div class="hero__finder-input-wrap">
            <span class="hero__finder-icon">🔍</span>
            <input
              type="text"
              id="heroModelInput"
              class="hero__finder-input"
              placeholder="Введіть: iPhone 15, Galaxy S24, Redmi Note..."
              autocomplete="off"
            />
            <button type="button" class="hero__finder-clear hidden" id="heroModelClear" title="Очистити">✕</button>
          </div>
          <ul class="hero__finder-dropdown hidden" id="heroModelDropdown" role="listbox"></ul>
        </div>

        <div class="hero__finder-selected hidden" id="heroSelectedNotice">
          <div class="hero__finder-selected-left">
            <span class="hero__finder-selected-icon">✅</span>
            <span id="heroSelectedText">Обрано модель</span>
          </div>
          <button type="button" class="hero__finder-change-btn" id="heroChangeModelBtn">Змінити</button>
        </div>
      </div>

      <!-- Кнопки дій -->
      <div class="hero__actions">
        <a href="#catalog" class="btn btn--primary btn--lg">
          <span>Обрати готовий принт</span>
          <span class="btn__arrow">↓</span>
        </a>
        <a href="#how-it-works" class="btn btn--outline btn--lg">Як це працює</a>
      </div>

      <!-- Переваги в 1 рядок -->
      <div class="hero__trust-pills">
        <div class="trust-pill">
          <span class="trust-pill__icon">⚡</span>
          <span>Друк 1–3 дні</span>
        </div>
        <div class="trust-pill">
          <span class="trust-pill__icon">📦</span>
          <span>Оплата при отриманні</span>
        </div>
        <div class="trust-pill">
          <span class="trust-pill__icon">🛡️</span>
          <span>Гарантія якості</span>
        </div>
      </div>

      <!-- Статистика -->
      <div class="hero__stats">
        <div class="hero__stat">
          <span class="hero__stat-num">238+</span>
          <span class="hero__stat-label">принтів</span>
        </div>
        <div class="hero__stat-divider"></div>
        <div class="hero__stat">
          <span class="hero__stat-num">670+</span>
          <span class="hero__stat-label">моделей телефонів</span>
        </div>
        <div class="hero__stat-divider"></div>
        <div class="hero__stat">
          <span class="hero__stat-num">8</span>
          <span class="hero__stat-label">матеріалів</span>
        </div>
      </div>
    </div>

    <!-- Права колонка: Яскравий інтерактивний банер "Чохол зі своїм фото" -->
    <div class="hero__showcase">
      <div class="hero-photo-card" onclick="Constructor.open()" role="button" tabindex="0" title="Натисніть, щоб відкрити онлайн-конструктор">
        <!-- Floating Badge -->
        <div class="photo-card__badge-top">🔥 Хіт · Свій дизайн</div>

        <div class="photo-card__header">
          <span class="photo-card__icon">📸</span>
          <div>
            <h2 class="photo-card__title">Чохол з твоїм фото</h2>
            <p class="photo-card__sub">Онлайн 3D-конструктор за 1 хв</p>
          </div>
        </div>

        <!-- Phone Mockup with Custom Photo Showcase -->
        <div class="photo-card__mockup-wrap">
          <div class="photo-card__case">
            <img
              class="photo-card__img"
              src="/img/custom-case-hero.jpg"
              alt="Чохол з твоїм фото"
            />
            <div class="photo-card__shine"></div>
            <div class="photo-card__price-tag">від <?= \App\Config::getMinPrice() ?> ₴ · 670+ моделей</div>
          </div>
        </div>

        <!-- Features list -->
        <div class="photo-card__features">
          <span class="photo-card__pill">🖼️ Будь-яке фото</span>
          <span class="photo-card__pill">📱 Точний розкрій</span>
          <span class="photo-card__pill">⚡ Друк 1-3 дні</span>
        </div>

        <!-- Primary CTA Button -->
        <button type="button" class="btn btn--primary photo-card__btn" onclick="event.stopPropagation(); Constructor.open();">
          <span>📸 Створити свій чохол</span>
          <span class="btn__arrow">→</span>
        </button>

        <div class="photo-card__proof">
          <span class="photo-card__stars">★★★★★</span>
          <span>1200+ надрукованих фото-чохлів</span>
        </div>
      </div>
    </div>
  </div>
</section>
