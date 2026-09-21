/**
 * customizer.js
 * Візуальний конструктор чохла: вибір принта, розумний пошук моделі телефону та вибір матеріалу
 */

const Customizer = (() => {
  const overlay  = document.getElementById('customizerModal');
  const closeBtn = document.getElementById('customizerClose');
  const bodyEl   = document.getElementById('customizerBody');

  let currentDesign   = null;
  let selectedModel    = null;
  let selectedMaterial = null;

  const FALLBACK_MODELS = [
    { id: '3500', name: 'Apple iPhone 16', brand: 'Apple', avail: 5, mats: { mat_u: true, mat_b: true, mat_sp: true, mat_pc: true, mat_pm: true } },
    { id: '3501', name: 'Apple iPhone 16 Pro', brand: 'Apple', avail: 5, mats: { mat_u: true, mat_b: true, mat_sp: true, mat_pc: true, mat_pm: true } },
    { id: '3502', name: 'Apple iPhone 16 Pro Max', brand: 'Apple', avail: 5, mats: { mat_u: true, mat_b: true, mat_sp: true, mat_pc: true, mat_pm: true } },
    { id: '3098', name: 'Apple iPhone 15', brand: 'Apple', avail: 5, mats: { mat_u: true, mat_b: true, mat_sp: true, mat_pc: true, mat_pm: true } },
    { id: '3099', name: 'Apple iPhone 15 Pro', brand: 'Apple', avail: 5, mats: { mat_u: true, mat_b: true, mat_sp: true, mat_pc: true, mat_pm: true } },
    { id: '3100', name: 'Apple iPhone 15 Pro Max', brand: 'Apple', avail: 5, mats: { mat_u: true, mat_b: true, mat_sp: true, mat_pc: true, mat_pm: true } },
    { id: '2648', name: 'Apple iPhone 14', brand: 'Apple', avail: 5, mats: { mat_u: true, mat_b: true, mat_sp: true, mat_pc: true, mat_pm: true } },
    { id: '2649', name: 'Apple iPhone 14 Pro', brand: 'Apple', avail: 5, mats: { mat_u: true, mat_b: true, mat_sp: true, mat_pc: true, mat_pm: true } },
    { id: '2400', name: 'Apple iPhone 13', brand: 'Apple', avail: 5, mats: { mat_u: true, mat_b: true, mat_sp: true, mat_pc: true, mat_pm: true } },
    { id: '2200', name: 'Apple iPhone 12', brand: 'Apple', avail: 5, mats: { mat_u: true, mat_b: true, mat_sp: true, mat_pc: true, mat_pm: true } },
    { id: '2000', name: 'Apple iPhone 11', brand: 'Apple', avail: 5, mats: { mat_u: true, mat_b: true, mat_sp: true, mat_pc: true, mat_pm: true } },
    { id: '3388', name: 'Samsung Galaxy S24', brand: 'Samsung', avail: 4, mats: { mat_u: true, mat_b: true, mat_sp: true, mat_pc: true } },
    { id: '3389', name: 'Samsung Galaxy S24 Ultra', brand: 'Samsung', avail: 4, mats: { mat_u: true, mat_b: true, mat_sp: true, mat_pc: true } },
    { id: '3120', name: 'Samsung Galaxy S23', brand: 'Samsung', avail: 4, mats: { mat_u: true, mat_b: true, mat_sp: true, mat_pc: true } },
    { id: '3200', name: 'Samsung Galaxy A55', brand: 'Samsung', avail: 3, mats: { mat_u: true, mat_b: true, mat_sp: true } },
    { id: '3201', name: 'Samsung Galaxy A54', brand: 'Samsung', avail: 3, mats: { mat_u: true, mat_b: true, mat_sp: true } },
    { id: '3250', name: 'Xiaomi Redmi Note 13 Pro', brand: 'Xiaomi', avail: 3, mats: { mat_u: true, mat_b: true, mat_sp: true } },
    { id: '3251', name: 'Xiaomi Redmi Note 12', brand: 'Xiaomi', avail: 3, mats: { mat_u: true, mat_b: true, mat_sp: true } },
    { id: '3260', name: 'Xiaomi Poco X6 Pro', brand: 'Xiaomi', avail: 3, mats: { mat_u: true, mat_b: true, mat_sp: true } }
  ];

  function getModels() {
    if (window.SHOPCASE_MODELS && Array.isArray(window.SHOPCASE_MODELS) && window.SHOPCASE_MODELS.length > 0) {
      return window.SHOPCASE_MODELS;
    }
    return FALLBACK_MODELS;
  }

  function getSelectedModel() {
    if (selectedModel) return selectedModel;
    try {
      const saved = localStorage.getItem('shopcase_selected_model');
      if (saved) {
        selectedModel = JSON.parse(saved);
        return selectedModel;
      }
    } catch (e) {}
    return null;
  }

  function setSelectedModel(model) {
    selectedModel = model;
    try {
      if (model) {
        localStorage.setItem('shopcase_selected_model', JSON.stringify(model));
      } else {
        localStorage.removeItem('shopcase_selected_model');
      }
    } catch (e) {}
  }

  const MATERIALS = window.SHOPCASE_MATERIALS || {
    mat_u: 'Силікон', mat_b: 'TPU чорний', mat_sp: 'Силікон+кути',
    mat_pc: 'Bumper', mat_pm: 'Bumper MagSafe', mat_c: '3D глянець',
    mat_m: '3D мат', mat_t: '2D пластик'
  };
  const PRICES = window.SHOPCASE_PRICES || {
    mat_u: 199, mat_b: 229, mat_sp: 269, mat_pc: 299, mat_pm: 349, mat_c: 249, mat_m: 249, mat_t: 179
  };

  function open(design) {
    currentDesign = design;
    const models = getModels();

    // Авто-вибір збереженої або популярної моделі
    selectedModel = getSelectedModel();
    if (!selectedModel && models.length > 0) {
      selectedModel = models.find(m => m.name.includes('iPhone 15') || m.name.includes('iPhone 14') || m.name.includes('iPhone 13')) || models[0];
    }

    _render();

    // Аналітика: перегляд товару
    if (typeof Analytics !== 'undefined' && typeof Analytics.trackViewContent === 'function') {
      Analytics.trackViewContent(design);
    }

    overlay.classList.remove('hidden');
    document.body.style.overflow = 'hidden';
    setTimeout(() => {
      if (closeBtn) closeBtn.focus();
    }, 50);
  }

  function close() {
    overlay.classList.add('hidden');
    document.body.style.overflow = '';
  }

  function _render() {
    if (!currentDesign) return;

    const models = getModels();
    const availableMats = _getAvailableMats(selectedModel);
    if (!selectedMaterial || !availableMats.includes(selectedMaterial)) {
      selectedMaterial = availableMats[0] || 'mat_u';
    }

    const currentPrice = PRICES[selectedMaterial] || 199;
    const modelCountText = models.length > 50 ? `${models.length}+ моделей` : '670+ моделей';

    bodyEl.innerHTML = `
      <div class="customizer-layout">
        <!-- Ліва колонка / Прев'ю чохла -->
        <div class="customizer-preview">
          <div class="case-mockup">
            <img class="case-mockup__print" src="${_esc(currentDesign.image)}" alt="${_esc(currentDesign.name)}" />
            <div class="case-mockup__badge">${_esc(MATERIALS[selectedMaterial] || 'Чохол')}</div>
          </div>
          <div class="customizer-preview__info">
            <span class="customizer-preview__cat">${_esc(currentDesign.category_name || 'Принт')}</span>
            <h3 class="customizer-preview__title">${_esc(currentDesign.name)}</h3>
            <span class="customizer-preview__mat-badge">✨ ${_esc(MATERIALS[selectedMaterial] || 'Силікон')}</span>
          </div>
        </div>

        <!-- Права колонка: Налаштування та замовлення -->
        <div class="customizer-controls">
          <div class="customizer-scroll-area">
            <div class="customizer-header">
              <span class="customizer-cat">${_esc(currentDesign.category_name || 'Принт')}</span>
              <h2 class="customizer-title">${_esc(currentDesign.name)}</h2>
            </div>

            <!-- Крок 1: Модель телефону -->
            <div class="control-group">
              <label class="control-label" for="customizerModelInput">
                <span>1. Модель вашого смартфона</span>
                <span class="control-label__hint">${modelCountText}</span>
              </label>

              <div class="model-select-box">
                <div class="model-select-input-wrap">
                  <span class="model-select-icon">📱</span>
                  <input
                    type="text"
                    class="model-select-input"
                    id="customizerModelInput"
                    placeholder="Введіть модель: iPhone 15, Samsung S24..."
                    value="${selectedModel ? _esc(selectedModel.name) : ''}"
                    autocomplete="off"
                  />
                  <button type="button" class="model-select-clear ${selectedModel ? '' : 'hidden'}" id="customizerModelClear" title="Очистити вибір">✕</button>
                </div>
                <ul class="model-select-dropdown hidden" id="customizerModelDropdown" role="listbox"></ul>
              </div>
            </div>

            <!-- Крок 2: Тип матеріалу -->
            <div class="control-group">
              <label class="control-label">
                <span>2. Матеріал чохла</span>
                <span class="control-label__hint">всі матеріали якісні</span>
              </label>

              <div class="material-options" id="customizerMaterialOptions">
                ${Object.entries(MATERIALS).map(([key, label]) => {
                  const isAvail = availableMats.includes(key);
                  const isSelected = (key === selectedMaterial);
                  const price = PRICES[key] || 199;
                  return `
                    <button
                      type="button"
                      class="mat-chip ${isSelected ? 'mat-chip--selected' : ''} ${!isAvail ? 'mat-chip--disabled' : ''}"
                      data-mat="${key}"
                      ${!isAvail ? 'disabled' : ''}
                      title="${isAvail ? label + ' — ' + price + ' ₴' : 'Немає в наявності для цієї моделі'}"
                    >
                      <span class="mat-chip__name">${_esc(label)}</span>
                      <span class="mat-chip__price">${price} ₴</span>
                    </button>
                  `;
                }).join('')}
              </div>
            </div>
          </div>

          <!-- Завжди видимий закріплений футер із кнопкою замовлення -->
          <div class="customizer-footer">
            <div class="customizer-price-block">
              <span class="customizer-price-label">Сума замовлення:</span>
              <span class="customizer-price-val" id="customizerPriceVal">${currentPrice} ₴</span>
            </div>
            <button class="btn btn--primary btn--lg" id="customizerOrderBtn">
              <span>Купити</span><span class="order-btn-model">${selectedModel ? ' · ' + _esc(selectedModel.name.split(' ').slice(1).join(' ') || selectedModel.name) : ''}</span>
            </button>
          </div>
        </div>
      </div>
    `;

    _bindEvents();
  }

  function _getAvailableMats(model) {
    if (!model || !model.mats) return Object.keys(MATERIALS);
    return Object.keys(MATERIALS).filter(k => model.mats[k] === true);
  }

  function _bindEvents() {
    const input    = document.getElementById('customizerModelInput');
    const dropdown = document.getElementById('customizerModelDropdown');
    const clearBtn = document.getElementById('customizerModelClear');
    const matWrap  = document.getElementById('customizerMaterialOptions');
    const orderBtn = document.getElementById('customizerOrderBtn');

    // Клік по матеріалах
    if (matWrap) {
      matWrap.addEventListener('click', e => {
        const btn = e.target.closest('.mat-chip');
        if (!btn || btn.disabled) return;

        const matKey = btn.dataset.mat;
        selectedMaterial = matKey;

        matWrap.querySelectorAll('.mat-chip').forEach(b => {
          b.classList.toggle('mat-chip--selected', b.dataset.mat === matKey);
        });

        const price = PRICES[matKey] || 199;
        const priceEl = document.getElementById('customizerPriceVal');
        if (priceEl) priceEl.textContent = `${price} ₴`;

        const badgeEl = bodyEl.querySelector('.case-mockup__badge');
        if (badgeEl) badgeEl.textContent = MATERIALS[matKey] || 'Чохол';

        const mobileBadgeEl = bodyEl.querySelector('.customizer-preview__mat-badge');
        if (mobileBadgeEl) mobileBadgeEl.textContent = '✨ ' + (MATERIALS[matKey] || 'Силікон');
      });
    }

    // Клік замовити
    if (orderBtn) {
      orderBtn.addEventListener('click', () => {
        if (!selectedModel) {
          Toast.show('Будь ласка, оберіть модель телефону', 'error');
          if (input) {
            input.focus();
            _renderModelDropdown('', dropdown);
          }
          return;
        }
        close();
        Order.openForm(currentDesign, selectedModel, selectedMaterial, PRICES[selectedMaterial] || 199);
      });
    }

    // Пошук моделі телефону
    if (input && dropdown) {
      input.addEventListener('input', () => {
        const q = input.value.trim();
        if (clearBtn) clearBtn.classList.toggle('hidden', !q);
        _renderModelDropdown(q, dropdown);
      });

      input.addEventListener('focus', () => {
        _renderModelDropdown(input.value.trim(), dropdown);
      });

      input.addEventListener('click', () => {
        _renderModelDropdown(input.value.trim(), dropdown);
      });

      // Клавіатурна навігація
      input.addEventListener('keydown', e => {
        if (e.key === 'Escape') {
          dropdown.classList.add('hidden');
        } else if (e.key === 'Enter') {
          const firstItem = dropdown.querySelector('.model-select-item');
          if (firstItem) {
            e.preventDefault();
            firstItem.dispatchEvent(new MouseEvent('mousedown'));
          }
        }
      });

      if (clearBtn) {
        clearBtn.addEventListener('click', (e) => {
          e.stopPropagation();
          input.value = '';
          selectedModel = null;
          clearBtn.classList.add('hidden');
          input.focus();
          _renderModelDropdown('', dropdown);
        });
      }
    }
  }

  function _renderModelDropdown(query, dropdown) {
    if (!dropdown) return;
    dropdown.innerHTML = '';
    const q = query.toLowerCase().trim();
    const models = getModels();

    let filtered = models;
    if (q) {
      filtered = models.filter(m => 
        (m.name && m.name.toLowerCase().includes(q)) || 
        (m.brand && m.brand.toLowerCase().includes(q))
      );
    }

    if (filtered.length === 0) {
      dropdown.innerHTML = `<li class="model-select-item--empty">Модель не знайдена 🔍 Спробуйте, наприклад: iPhone 15, S24, Redmi</li>`;
      dropdown.classList.remove('hidden');
      return;
    }

    // Показуємо перші 20 результатів
    filtered.slice(0, 20).forEach(model => {
      const li = document.createElement('li');
      li.className = 'model-select-item';
      li.innerHTML = `
        <span class="model-select-item__icon">${_getBrandEmoji(model.name)}</span>
        <div>
          <p class="model-select-item__name">${_highlight(model.name, query)}</p>
          <span class="model-select-item__sub">${_esc(model.brand || '')} · ${model.avail || '3+'} матеріалів у наявності</span>
        </div>
      `;
      li.addEventListener('mousedown', e => {
        e.preventDefault();
        selectedModel = model;
        _render(); // Перерендерюємо для оновлення доступних матеріалів
      });
      dropdown.appendChild(li);
    });

    dropdown.classList.remove('hidden');
  }

  // Закриття випадаючого списку при кліку поза ним
  document.addEventListener('click', e => {
    const box = e.target.closest('.model-select-box');
    if (!box) {
      const dropdown = document.getElementById('customizerModelDropdown');
      if (dropdown) dropdown.classList.add('hidden');
    }
  });

  function _getBrandEmoji(name) {
    const n = (name || '').toLowerCase();
    if (n.includes('apple') || n.includes('iphone'))  return '🍎';
    if (n.includes('samsung'))                         return '💎';
    if (n.includes('xiaomi') || n.includes('redmi') || n.includes('poco')) return '⚡';
    if (n.includes('huawei') || n.includes('honor'))  return '🌸';
    if (n.includes('google') || n.includes('pixel'))  return '🔍';
    if (n.includes('oneplus') || n.includes('realme')) return '🔴';
    return '📱';
  }

  function _highlight(text, q) {
    if (!q) return _esc(text);
    const re = new RegExp(`(${q.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')})`, 'gi');
    return _esc(text).replace(re, '<mark>$1</mark>');
  }

  function _esc(str) {
    return String(str || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
  }

  if (closeBtn) closeBtn.addEventListener('click', close);
  if (overlay) {
    overlay.addEventListener('click', e => { if (e.target === overlay) close(); });
  }
  document.addEventListener('keydown', e => {
    if (e.key === 'Escape' && overlay && !overlay.classList.contains('hidden')) close();
  });

  return { open, close, setSelectedModel, getSelectedModel, getModels };
})();
