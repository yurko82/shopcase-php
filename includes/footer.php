
  <!-- ========== FOOTER ========== -->
  <footer class="footer">
    <div class="container footer__inner">
      <div class="footer__brand">
        <a href="/" class="logo">
          <span class="logo__icon">◈</span>
          <span class="logo__text">Shop<span class="logo__accent">Case</span></span>
        </a>
        <p class="footer__tagline">Чохли з душею для кожного телефону</p>
      </div>
      <div class="footer__links">
        <a href="/#catalog">Каталог</a>
        <a href="/#how-it-works">Як це працює</a>
        <a href="/#advantages">Переваги</a>
      </div>
      <div class="footer__copy">
        © <?= date('Y') ?> ShopCase. Всі права захищені.
      </div>
    </div>
  </footer>

  <!-- ========== PRODUCT MODAL ========== -->
  <div class="modal-overlay hidden" id="productModal" role="dialog" aria-modal="true">
    <div class="modal">
      <button class="modal__close" id="modalClose" aria-label="Закрити">✕</button>
      <div class="modal__body" id="modalBody"></div>
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

  <!-- ========== TOAST ========== -->
  <div class="toast-container" id="toastContainer" aria-live="polite"></div>

  <!-- JS -->
  <script src="/js/designs.js"></script>
  <script src="/js/modal.js"></script>
  <script src="/js/order.js"></script>
  <script src="/js/main.js"></script>
</body>
</html>
