/**
 * constructor.js
 * Інтеграція онлайн 3D-конструктора EndorPhone "Чохол з власним фото"
 */

const Constructor = (() => {
  const overlay  = document.getElementById('constructorModal');
  const closeBtn = document.getElementById('constructorClose');
  const iframe   = document.getElementById('constructorIframe');
  const loader   = document.getElementById('constructorLoader');

  const CONSTRUCTOR_URL = 'https://endorphone.com.ua/conf-drop/32465/start';

  function open() {
    if (!overlay) return;

    // Ліниве завантаження iframe лише при першому відкритті
    if (iframe && (!iframe.src || iframe.src === 'about:blank' || iframe.src === window.location.href)) {
      if (loader) loader.classList.remove('hidden');
      iframe.src = CONSTRUCTOR_URL;

      iframe.onload = () => {
        if (loader) loader.classList.add('hidden');
      };
    }

    // Аналітика: відкриття онлайн-конструктора
    if (typeof Analytics !== 'undefined' && typeof Analytics.trackConstructorOpen === 'function') {
      Analytics.trackConstructorOpen('modal');
    }

    overlay.classList.remove('hidden');
    document.body.style.overflow = 'hidden';

    setTimeout(() => {
      if (closeBtn) closeBtn.focus();
    }, 100);
  }

  function close() {
    if (!overlay) return;
    overlay.classList.add('hidden');
    document.body.style.overflow = '';
  }

  if (closeBtn) closeBtn.addEventListener('click', close);
  if (overlay) {
    overlay.addEventListener('click', e => {
      if (e.target === overlay) close();
    });
  }

  document.addEventListener('keydown', e => {
    if (e.key === 'Escape' && overlay && !overlay.classList.contains('hidden')) {
      close();
    }
  });

  return { open, close };
})();
