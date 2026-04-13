/**
 * designs.js
 * Модалка дизайну — показує фото, назву, ціну
 * та веде до вибору моделі телефону → замовлення
 */

const DesignModal = (() => {

  const overlay  = document.getElementById('designModal');
  const closeBtn = document.getElementById('designModalClose');
  const bodyEl   = document.getElementById('designModalBody');

  let currentDesign = null;

  /* ══════════════════════════════
     ВІДКРИТИ
  ══════════════════════════════ */
  function open(design) {
    currentDesign = design;
    _render(design);
    overlay.classList.remove('hidden');
    document.body.style.overflow = 'hidden';
    setTimeout(() => closeBtn.focus(), 50);
  }

  /* ══════════════════════════════
     ЗАКРИТИ
  ══════════════════════════════ */
  function close() {
    overlay.classList.add('hidden');
    document.body.style.overflow = '';
    currentDesign = null;
  }

  /* ══════════════════════════════
     РЕНДЕР
  ══════════════════════════════ */
  function _render(design) {
    bodyEl.innerHTML = '';

    const body = document.createElement('div');
    body.style.cssText = 'padding: var(--space-xl)';

    // Фото
    if (design.image) {
      const img = document.createElement('img');
      img.className = 'design-modal__image';
      img.src = design.image;
      img.alt = design.design;
      img.onerror = function() {
        this.style.display = 'none';
        const ph = document.createElement('div');
        ph.className = 'design-modal__image-placeholder';
        ph.textContent = '🎨';
        this.parentNode.insertBefore(ph, this.nextSibling);
      };
      body.appendChild(img);
    } else {
      const ph = document.createElement('div');
      ph.className = 'design-modal__image-placeholder';
      ph.textContent = '🎨';
      body.appendChild(ph);
    }

    // Категорія
    const cat = document.createElement('p');
    cat.className = 'design-modal__cat';
    cat.textContent = design.category;
    body.appendChild(cat);

    // Назва
    const name = document.createElement('h2');
    name.className = 'design-modal__name';
    name.textContent = design.design;
    body.appendChild(name);

    // Мета
    const meta = document.createElement('p');
    meta.className = 'design-modal__meta';
    meta.textContent = `Доступний для ${design.count} моделей телефонів`;
    body.appendChild(meta);

    // Ціна + кнопка
    const priceRow = document.createElement('div');
    priceRow.className = 'design-modal__price-row';

    const priceWrap = document.createElement('div');
    const price = document.createElement('p');
    price.className = 'design-modal__price';
    price.textContent = `від ${design.price} ₴`;
    const note = document.createElement('p');
    note.className = 'design-modal__note';
    note.textContent = 'Ціна залежить від матеріалу';
    priceWrap.appendChild(price);
    priceWrap.appendChild(note);

    const btn = document.createElement('button');
    btn.className = 'btn btn--primary';
    btn.textContent = 'Обрати модель телефону';
    btn.addEventListener('click', () => {
      close();
      // Прокручуємо до каталогу і підсвічуємо пошук
      const catalogSection = document.getElementById('catalog');
      if (catalogSection) {
        const top = catalogSection.getBoundingClientRect().top + window.scrollY - 80;
        window.scrollTo({ top, behavior: 'smooth' });
      }
      // Фокус на поле пошуку моделі
      setTimeout(() => {
        const searchInput = document.getElementById('modelSearchInput');
        if (searchInput) {
          searchInput.focus();
          searchInput.scrollIntoView({ block: 'center', behavior: 'smooth' });
          // Підсвічуємо поле
          searchInput.style.boxShadow = '0 0 0 4px rgba(255, 78, 205, 0.25)';
          setTimeout(() => searchInput.style.boxShadow = '', 2000);
        }
      }, 600);
    });

    priceRow.appendChild(priceWrap);
    priceRow.appendChild(btn);
    body.appendChild(priceRow);

    bodyEl.appendChild(body);
  }

  /* ══════════════════════════════
     ПОДІЇ
  ══════════════════════════════ */
  closeBtn.addEventListener('click', close);
  overlay.addEventListener('click', e => { if (e.target === overlay) close(); });
  document.addEventListener('keydown', e => {
    if (e.key === 'Escape' && !overlay.classList.contains('hidden')) close();
  });

  return { open, close };
})();

/* Глобальна функція — викликається з PHP onclick */
function openDesignModal(design) {
  DesignModal.open(design);
}
