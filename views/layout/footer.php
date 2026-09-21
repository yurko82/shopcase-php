
  <!-- ========== FOOTER ========== -->
  <footer class="footer">
    <div class="container footer__inner">
      <div class="footer__brand">
        <a href="/" class="logo">
          <span class="logo__icon">◈</span>
          <span class="logo__text">Shop<span class="logo__accent">Case</span></span>
        </a>
        <p class="footer__tagline">Чохли з душею та якісним друком для 670+ смартфонів</p>
      </div>
      <div class="footer__links">
        <a href="#catalog">Каталог принтів</a>
        <a href="#how-it-works">Як це працює</a>
        <a href="#advantages">Переваги</a>
        <a href="javascript:void(0)" onclick="Tracker.openModal()">Відстежити замовлення</a>
      </div>
      <div class="footer__copy">
        © <?= date('Y') ?> ShopCase. Всі права захищені. Доставка Новою Поштою по всій Україні.
      </div>
    </div>
  </footer>

  <!-- ========== CUSTOMIZER MODAL ========== -->
  <div class="modal-overlay hidden" id="customizerModal" role="dialog" aria-modal="true">
    <div class="modal modal--customizer">
      <button class="modal__close" id="customizerClose" aria-label="Закрити">✕</button>
      <div class="modal__body" id="customizerBody"></div>
    </div>
  </div>

  <!-- ========== ORDER MODAL ========== -->
  <div class="modal-overlay hidden" id="orderModal" role="dialog" aria-modal="true">
    <div class="modal modal--order">
      <button class="modal__close" id="orderModalClose" aria-label="Закрити">✕</button>
      <h2 class="modal__title" id="orderModalTitle">Оформлення замовлення</h2>
      <div id="orderModalBody"></div>
    </div>
  </div>

  <!-- ========== TRACKING MODAL ========== -->
  <div class="modal-overlay hidden" id="trackingModal" role="dialog" aria-modal="true">
    <div class="modal modal--tracking">
      <button class="modal__close" id="trackingModalClose" aria-label="Закрити">✕</button>
      <h2 class="modal__title">Відстеження замовлення</h2>
      <div id="trackingModalBody">
        <form class="tracking-form" id="trackingForm" onsubmit="event.preventDefault();Tracker.search();">
          <p class="tracking-form__desc">Введіть номер телефону, вказаний при оформленні замовлення:</p>
          <div class="tracking-form__input-wrap">
            <input type="tel" class="form-input" id="trackingPhone" placeholder="+380XXXXXXXXX" required />
            <button type="submit" class="btn btn--primary" id="trackingBtn">Знайти</button>
          </div>
        </form>
        <div class="tracking-results" id="trackingResults"></div>
      </div>
    </div>
  </div>

  <!-- ========== ENDORPHONE PHOTO CONSTRUCTOR MODAL ========== -->
  <div class="modal-overlay hidden" id="constructorModal" role="dialog" aria-modal="true">
    <div class="modal modal--constructor">
      <div class="modal__header modal__header--constructor">
        <div class="constructor-header-info">
          <span class="constructor-header-icon">📸</span>
          <div>
            <h2 class="constructor-header-title">Онлайн-конструктор чохлів з власним фото</h2>
            <span class="constructor-header-sub">Оберіть свій смартфон та завантажте фото або малюнок</span>
          </div>
        </div>
        <div class="constructor-header-actions">
          <a href="https://endorphone.com.ua/conf-drop/32465/start" target="_blank" rel="noopener" class="btn btn--outline btn--sm constructor-fullscreen-btn" title="Відкрити на весь екран у новій вкладці">
            <span>⤢</span> <span>На весь екран</span>
          </a>
          <button class="modal__close" id="constructorClose" aria-label="Закрити конструктор">✕</button>
        </div>
      </div>
      <div class="modal__body modal__body--constructor">
        <div class="constructor-loader" id="constructorLoader">
          <div class="spinner"></div>
          <p>Завантажуємо 3D-конструктор чохлів...</p>
        </div>
        <iframe
          id="constructorIframe"
          src=""
          data-src="https://endorphone.com.ua/conf-drop/32465/start"
          title="Онлайн-конструктор чохлів з фото"
          class="constructor-iframe"
          allow="camera; microphone; clipboard-write"
          loading="lazy"
        ></iframe>
      </div>
    </div>
  </div>

  <!-- ========== TOAST CONTAINER ========== -->
  <div class="toast-container" id="toastContainer" aria-live="polite"></div>

  <?php if (!empty($marketingSettings['gtm_id'])): ?>
  <!-- Google Tag Manager (noscript) -->
  <noscript><iframe src="https://www.googletagmanager.com/ns.html?id=<?= htmlspecialchars($marketingSettings['gtm_id']) ?>"
  height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>
  <!-- End Google Tag Manager (noscript) -->
  <?php endif; ?>

  <!-- JS Data & Scripts -->
  <script>
    window.SHOPCASE_MODELS = <?= json_encode($modelsForJs ?? [], JSON_UNESCAPED_UNICODE) ?>;
    window.SHOPCASE_MATERIALS = <?= json_encode(\App\Config::MATERIAL_LABELS, JSON_UNESCAPED_UNICODE) ?>;
    window.SHOPCASE_PRICES = <?= json_encode(\App\Config::getMaterialPrices(), JSON_UNESCAPED_UNICODE) ?>;
  </script>

  <script src="/js/analytics.js?v=3.3"></script>
  <script src="/js/app.js?v=3.3"></script>
  <script src="/js/customizer.js?v=3.3"></script>
  <script src="/js/constructor.js?v=3.3"></script>
  <script src="/js/order.js?v=3.3"></script>
  <script src="/js/tracking.js?v=3.3"></script>
</body>
</html>
