/**
 * modal.js
 * Модалка товару — дані приходять з PHP onclick атрибуту
 */

const Modal = (() => {

  const MATERIAL_LABELS = {
    mat_u:  'Силікон',
    mat_c:  '3D глянець',
    mat_m:  '3D мат',
    mat_t:  '2D пластик',
    mat_b:  'TPU чорний',
    mat_sp: 'Силікон+кути',
    mat_pc: 'Bumper',
    mat_pm: 'Bumper MagSafe',
  };

  const MATERIAL_PRICES = {
    mat_u:  199,
    mat_c:  249,
    mat_m:  249,
    mat_t:  179,
    mat_b:  229,
    mat_sp: 269,
    mat_pc: 299,
    mat_pm: 349,
  };

  const overlay  = document.getElementById('productModal');
  const closeBtn = document.getElementById('modalClose');
  const bodyEl   = document.getElementById('modalBody');

  let currentItem      = null;
  let selectedMaterial = null;

  /* ══════════════════════════════
     ВІДКРИТИ
  ══════════════════════════════ */
  function openProduct(item) {
    currentItem      = item;
    selectedMaterial = null;

    _render(item);

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
    currentItem      = null;
    selectedMaterial = null;
  }

  /* ══════════════════════════════
     РЕНДЕР
  ══════════════════════════════ */
  function _render(item) {
    // Збираємо доступні матеріали
    const availableMats = Object.keys(MATERIAL_LABELS).filter(k => item.mats && item.mats[k] === true);

    console.log('[Modal] item:', item.name);
    console.log('[Modal] mats raw:', item.mats);
    console.log('[Modal] available:', availableMats);

    const emoji = _getEmoji(item.name);
    const brand = item.name.split(' ')[0];

    // Авто-вибір першого доступного
    selectedMaterial = availableMats.length > 0 ? availableMats[0] : null;

    bodyEl.innerHTML = '';

    /* Зображення */
    const imgWrap = _el('div', 'modal-product__image-wrap');
    imgWrap.innerHTML = `<div class="modal-product__image-placeholder">${emoji}</div>`;

    /* Бренд */
    const brandEl = _el('p', 'modal-product__model', brand);

    /* Назва */
    const nameEl = _el('h2', 'modal-product__name', item.name);
    nameEl.id = 'modalTitle';

    /* Лейбл матеріалів */
    const matLabel = _el('p', 'modal-product__mat-label',
      availableMats.length > 0 ? 'Оберіть матеріал' : 'Немає матеріалів в наявності'
    );

    /* Кнопки матеріалів */
    const matsWrap = _el('div', 'modal-product__materials');

    Object.entries(MATERIAL_LABELS).forEach(([key, label]) => {
      const isAvail = item.mats && item.mats[key] === true;
      const btn     = _el('button', 'mat-option', label);
      btn.dataset.mat = key;
      btn.disabled    = !isAvail;

      if (isAvail) {
        btn.title = `${MATERIAL_PRICES[key]} ₴`;
      } else {
        btn.title = 'Немає в наявності';
      }

      // Підсвічуємо перший доступний
      if (key === selectedMaterial) {
        btn.classList.add('mat-option--selected');
      }

      btn.addEventListener('click', () => {
        if (!isAvail) return;
        _selectMaterial(key, matsWrap, priceEl, orderBtn);
      });

      matsWrap.appendChild(btn);
    });

    /* Ціна */
    const priceRow  = _el('div', 'modal-product__price-row');
    const priceWrap = _el('div');

    const priceEl = _el(
      'p',
      'modal-product__price',
      selectedMaterial ? `${MATERIAL_PRICES[selectedMaterial]} ₴` : '—'
    );

    const priceNote = _el('p', 'modal-product__price-note', 'Доставка Новою Поштою');
    priceWrap.append(priceEl, priceNote);

    /* Кнопка замовити */
    const orderBtn = _el('button', 'btn btn--primary', 'Замовити');
    orderBtn.disabled = !selectedMaterial;

    orderBtn.addEventListener('click', () => {
      if (!selectedMaterial) return;
      close();
      Order.openForm(item, selectedMaterial, MATERIAL_PRICES, MATERIAL_LABELS);
    });

    priceRow.append(priceWrap, orderBtn);

    bodyEl.append(imgWrap, brandEl, nameEl, matLabel, matsWrap, priceRow);
  }

  /* ══════════════════════════════
     ВИБІР МАТЕРІАЛУ
  ══════════════════════════════ */
  function _selectMaterial(key, matsWrap, priceEl, orderBtn) {
    selectedMaterial = key;

    matsWrap.querySelectorAll('.mat-option').forEach(btn => {
      btn.classList.toggle('mat-option--selected', btn.dataset.mat === key);
    });

    priceEl.textContent = `${MATERIAL_PRICES[key]} ₴`;
    orderBtn.disabled   = false;
  }

  /* ══════════════════════════════
     ПОДІЇ
  ══════════════════════════════ */
  function _bindEvents() {
    closeBtn.addEventListener('click', close);
    overlay.addEventListener('click', e => { if (e.target === overlay) close(); });
    document.addEventListener('keydown', e => {
      if (e.key === 'Escape' && !overlay.classList.contains('hidden')) close();
    });
  }

  /* ══════════════════════════════
     ХЕЛПЕРИ
  ══════════════════════════════ */
  function _el(tag, className = '', text = '') {
    const el = document.createElement(tag);
    if (className) el.className = className;
    if (text)      el.textContent = text;
    return el;
  }

  function _getEmoji(name) {
    const n = name.toLowerCase();
    if (n.includes('apple') || n.includes('iphone'))  return '🍎';
    if (n.includes('samsung'))                         return '💎';
    if (n.includes('xiaomi') || n.includes('redmi') || n.includes('poco')) return '⚡';
    if (n.includes('huawei') || n.includes('honor'))  return '🌸';
    if (n.includes('google') || n.includes('pixel'))  return '🔍';
    if (n.includes('oneplus'))                         return '🔴';
    if (n.includes('oppo')   || n.includes('realme')) return '🌊';
    return '📱';
  }

  _bindEvents();

  return { openProduct, close };
})();
