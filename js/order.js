/**
 * order.js
 * Оформлення замовлення: валідація, вибір міст та відділень Нової Пошти, відправка в API
 */

const Order = (() => {
  const overlay  = document.getElementById('orderModal');
  const closeBtn = document.getElementById('orderModalClose');
  const bodyEl   = document.getElementById('orderModalBody');

  let currentDesign   = null;
  let currentModel    = null;
  let currentMaterial = null;
  let currentPrice    = 199;

  let selectedCityName = '';
  let selectedCityRef  = '';
  let selectedWarehouseName = '';
  let selectedWarehouseRef  = '';
  let warehousesList = [];

  let paymentType      = '1';
  let prepayType       = 'card';
  let deliveryPayer    = '1';

  function openForm(design, model, material, price) {
    currentDesign   = design;
    currentModel    = model;
    currentMaterial = material;
    currentPrice    = price;
    paymentType     = '1';
    prepayType      = 'card';
    deliveryPayer   = '1';

    selectedCityName = '';
    selectedCityRef  = '';
    selectedWarehouseName = '';
    selectedWarehouseRef  = '';
    warehousesList   = [];

    _render();

    // Аналітика: початок оформлення замовлення
    if (typeof Analytics !== 'undefined' && typeof Analytics.trackInitiateCheckout === 'function') {
      Analytics.trackInitiateCheckout(design, model, material, price);
    }

    overlay.classList.remove('hidden');
    document.body.style.overflow = 'hidden';
    setTimeout(() => closeBtn && closeBtn.focus(), 50);
  }

  function close() {
    overlay.classList.add('hidden');
    document.body.style.overflow = '';
  }

  function _render() {
    const matLabel = (window.SHOPCASE_MATERIALS && window.SHOPCASE_MATERIALS[currentMaterial]) || 'Силікон';

    bodyEl.innerHTML = `
      <div class="order-summary">
        <img class="order-summary__img" src="${_esc(currentDesign.image)}" alt="${_esc(currentDesign.name)}" />
        <div class="order-summary__info">
          <p class="order-summary__title">${_esc(currentDesign.name)}</p>
          <p class="order-summary__meta">📱 ${_esc(currentModel.name)} · ${_esc(matLabel)}</p>
        </div>
        <div class="order-summary__price">${currentPrice} ₴</div>
      </div>

      <form class="order-form" id="orderForm" novalidate>
        <div class="form-row">
          <div class="form-group">
            <label class="form-label required" for="fSurname">Прізвище</label>
            <input class="form-input" id="fSurname" name="surname" type="text" placeholder="Шевченко" required />
            <span class="form-error" id="eSurname"></span>
          </div>
          <div class="form-group">
            <label class="form-label required" for="fName">Ім'я</label>
            <input class="form-input" id="fName" name="name" type="text" placeholder="Тарас" required />
            <span class="form-error" id="eName"></span>
          </div>
        </div>

        <div class="form-group">
          <label class="form-label required" for="fPhone">Номер телефону</label>
          <input class="form-input" id="fPhone" name="phone" type="tel" placeholder="+380501234567" required />
          <span class="form-error" id="ePhone"></span>
        </div>

        <div class="form-group">
          <label class="form-label" for="fEmail">Email (необов'язково)</label>
          <input class="form-input" id="fEmail" name="email" type="email" placeholder="taras@example.com" />
        </div>

        <!-- Секція доставки Нової Пошти -->
        <div class="form-section">
          <p class="form-section-title">📮 Доставка — Нова Пошта</p>
          
          <div class="form-group">
            <label class="form-label required" for="fCity">Населений пункт (місто / селище)</label>
            <div class="np-search-box">
              <input class="form-input" id="fCity" name="city" type="text" placeholder="Почніть вводити: Київ, Львів, Одеса..." autocomplete="off" required />
              <ul class="np-dropdown hidden" id="cityDropdown"></ul>
            </div>
            <span class="form-error" id="eCity"></span>
          </div>

          <div class="form-group">
            <label class="form-label required" for="fWarehouse">Відділення або поштомат НП</label>
            <div class="np-search-box">
              <input class="form-input" id="fWarehouse" name="warehouse" type="text" placeholder="Оберіть або введіть номер відділення/поштомату" autocomplete="off" required />
              <ul class="np-dropdown hidden" id="whDropdown"></ul>
            </div>
            <span class="form-error" id="eWarehouse"></span>
          </div>
        </div>

        <!-- Оплата -->
        <div class="form-section">
          <p class="form-section-title">💳 Спосіб оплати</p>
          <div class="payment-toggle">
            <label class="payment-option">
              <input type="radio" name="payment" value="1" checked />
              <span class="payment-option__card">
                <span class="payment-option__icon">📦</span>
                <span class="payment-option__name">Накладений платіж</span>
                <span class="payment-option__sub">Оплата при отриманні на пошті</span>
              </span>
            </label>
            <label class="payment-option">
              <input type="radio" name="payment" value="2" />
              <span class="payment-option__card">
                <span class="payment-option__icon">💳</span>
                <span class="payment-option__name">Передоплата онлайн</span>
                <span class="payment-option__sub">Картка / LiqPay</span>
              </span>
            </label>
          </div>
        </div>

        <div class="form-group">
          <label class="form-label" for="fComment">Коментар до замовлення</label>
          <textarea class="form-textarea" id="fComment" name="comment" rows="2" placeholder="Додаткові побажання (необов'язково)..."></textarea>
        </div>

        <button type="submit" class="btn btn--primary btn--block" id="submitOrderBtn">
          Підтвердити замовлення (${currentPrice} ₴)
        </button>
      </form>
    `;

    _bindFormEvents();
  }

  function _bindFormEvents() {
    const form = document.getElementById('orderForm');
    const cityInput = document.getElementById('fCity');
    const cityDropdown = document.getElementById('cityDropdown');
    const whInput = document.getElementById('fWarehouse');
    const whDropdown = document.getElementById('whDropdown');

    // Обробка пошуку міст
    if (cityInput && cityDropdown) {
      let cityTimer = null;

      cityInput.addEventListener('input', () => {
        const q = cityInput.value.trim();
        clearTimeout(cityTimer);

        if (q.length < 2) {
          cityDropdown.classList.add('hidden');
          return;
        }

        cityTimer = setTimeout(async () => {
          try {
            const res = await fetch(`/api.php?action=searchCities&q=${encodeURIComponent(q)}`);
            const data = await res.json();
            if (data.success && data.data && data.data.length > 0) {
              cityDropdown.innerHTML = '';
              data.data.forEach(item => {
                const li = document.createElement('li');
                li.className = 'np-dropdown-item';
                li.innerHTML = `
                  <span class="np-dropdown-item__main">${_esc(item.name)}</span>
                  ${item.area ? `<span class="np-dropdown-item__sub">${_esc(item.area)}</span>` : ''}
                `;
                li.addEventListener('mousedown', () => {
                  cityInput.value = item.name;
                  selectedCityName = item.name;
                  selectedCityRef  = item.ref || '';
                  cityDropdown.classList.add('hidden');
                  
                  // Завантажуємо відділення для обраного міста
                  _loadWarehouses(selectedCityRef, selectedCityName);
                  if (whInput) {
                    whInput.value = '';
                    selectedWarehouseRef = '';
                    selectedWarehouseName = '';
                    setTimeout(() => whInput.focus(), 100);
                  }
                });
                cityDropdown.appendChild(li);
              });
              cityDropdown.classList.remove('hidden');
            } else {
              cityDropdown.classList.add('hidden');
            }
          } catch (err) {
            console.error('NP error:', err);
          }
        }, 200);
      });

      cityInput.addEventListener('blur', () => {
        setTimeout(() => cityDropdown.classList.add('hidden'), 200);
      });
    }

    // Обробка вибору відділень / поштоматів
    if (whInput && whDropdown) {
      whInput.addEventListener('focus', () => {
        _renderWarehousesDropdown(whInput.value.trim());
      });

      whInput.addEventListener('input', () => {
        _renderWarehousesDropdown(whInput.value.trim());
      });

      whInput.addEventListener('blur', () => {
        setTimeout(() => whDropdown.classList.add('hidden'), 200);
      });
    }

    if (form) {
      form.addEventListener('submit', async e => {
        e.preventDefault();
        if (_validateForm()) {
          await _submitOrder();
        }
      });
    }
  }

  async function _loadWarehouses(cityRef, cityName) {
    if (!cityRef && !cityName) return;
    try {
      const res = await fetch(`/api.php?action=getWarehouses&cityRef=${encodeURIComponent(cityRef)}&cityName=${encodeURIComponent(cityName)}`);
      const data = await res.json();
      if (data.success && Array.isArray(data.data)) {
        warehousesList = data.data;
        const whInput = document.getElementById('fWarehouse');
        if (whInput && document.activeElement === whInput) {
          _renderWarehousesDropdown(whInput.value.trim());
        }
      }
    } catch (err) {
      console.warn('Warehouses load error:', err);
    }
  }

  function _renderWarehousesDropdown(filterQuery) {
    const whDropdown = document.getElementById('whDropdown');
    const whInput = document.getElementById('fWarehouse');
    if (!whDropdown || !whInput) return;

    if (!warehousesList || warehousesList.length === 0) {
      whDropdown.classList.add('hidden');
      return;
    }

    const q = (filterQuery || '').toLowerCase();
    const filtered = warehousesList.filter(wh => 
      !q || wh.name.toLowerCase().includes(q) || (wh.number && wh.number.includes(q))
    ).slice(0, 40);

    if (filtered.length === 0) {
      whDropdown.classList.add('hidden');
      return;
    }

    whDropdown.innerHTML = '';
    filtered.forEach(wh => {
      const li = document.createElement('li');
      li.className = 'np-dropdown-item';
      li.innerHTML = `
        <span class="np-dropdown-item__main">${_esc(wh.name)}</span>
        ${wh.number ? `<span class="np-dropdown-item__sub">№ ${wh.number}</span>` : ''}
      `;
      li.addEventListener('mousedown', () => {
        whInput.value = wh.name;
        selectedWarehouseName = wh.name;
        selectedWarehouseRef  = wh.ref || '';
        whDropdown.classList.add('hidden');
      });
      whDropdown.appendChild(li);
    });

    whDropdown.classList.remove('hidden');
  }

  function _validateForm() {
    let valid = true;

    const reqs = [
      { id: 'fSurname', err: 'eSurname', msg: 'Введіть прізвище' },
      { id: 'fName', err: 'eName', msg: "Введіть ім'я" },
      { id: 'fPhone', err: 'ePhone', msg: 'Введіть телефон (+380XXXXXXXXX)', pattern: /^\+380\d{9}$/ },
      { id: 'fCity', err: 'eCity', msg: 'Вкажіть населений пункт' },
      { id: 'fWarehouse', err: 'eWarehouse', msg: 'Вкажіть відділення або поштомат' },
    ];

    reqs.forEach(r => {
      const el = document.getElementById(r.id);
      const errEl = document.getElementById(r.err);
      if (!el || !errEl) return;

      const val = el.value.trim();
      if (!val) {
        errEl.textContent = r.msg;
        el.classList.add('error');
        valid = false;
      } else if (r.pattern && !r.pattern.test(val)) {
        errEl.textContent = r.msg;
        el.classList.add('error');
        valid = false;
      } else {
        errEl.textContent = '';
        el.classList.remove('error');
      }
    });

    return valid;
  }

  async function _submitOrder() {
    const btn = document.getElementById('submitOrderBtn');
    if (btn) {
      btn.disabled = true;
      btn.textContent = 'Оформлюємо замовлення...';
    }

    const utmData = (typeof Analytics !== 'undefined' && typeof Analytics.getUtmData === 'function')
      ? Analytics.getUtmData()
      : {};

    const payload = {
      action: 'createOrder',
      data: {
        design_id: currentDesign.id,
        model_id: currentModel.id,
        mat_key: currentMaterial,
        surname: _val('fSurname'),
        name: _val('fName'),
        phone: _val('fPhone'),
        email: _val('fEmail'),
        city: _val('fCity'),
        city_ref: selectedCityRef || '',
        warehouse: _val('fWarehouse'),
        warehouse_ref: selectedWarehouseRef || '',
        payment_type: document.querySelector('input[name="payment"]:checked')?.value || '1',
        comment: _val('fComment'),
        utm: utmData
      }
    };

    try {
      const res = await fetch('/api.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
      });

      const data = await res.json();

      if (data.success) {
        // Аналітика: успішна покупка (Purchase)
        if (typeof Analytics !== 'undefined' && typeof Analytics.trackPurchase === 'function') {
          Analytics.trackPurchase({
            order_id: data.order_id || data.endorphone_id,
            total_amount: currentPrice,
            product_code: data.product_code || `${currentDesign.id}-${currentModel.id}`,
            product_name: currentDesign.name || `Принт #${currentDesign.id}`,
            model_name: currentModel.name
          });
        }

        _renderSuccess(data.order_id || data.endorphone_id);
        Toast.show(`Замовлення #${data.order_id} успішно прийнято!`, 'success');
      } else {
        throw new Error(data.error || 'Помилка при створенні замовлення');
      }
    } catch (err) {
      Toast.show(err.message || 'Помилка відправки', 'error');
      if (btn) {
        btn.disabled = false;
        btn.textContent = `Підтвердити замовлення (${currentPrice} ₴)`;
      }
    }
  }

  function _renderSuccess(orderId) {
    bodyEl.innerHTML = `
      <div class="order-success">
        <div class="order-success__icon">🎉</div>
        <h3 class="order-success__title">Дякуємо за замовлення!</h3>
        <p class="order-success__text">
          Ваш чохол відправлено у друк. Наш менеджер незабаром зв'яжеться з вами для уточнення деталей.
        </p>
        <div class="order-success__num">
          Номер вашого замовлення: <strong>#${orderId}</strong>
        </div>
        <button class="btn btn--outline btn--block" onclick="Order.close()">Повернутися до каталогу</button>
      </div>
    `;
  }

  function _val(id) {
    const el = document.getElementById(id);
    return el ? el.value.trim() : '';
  }

  function _esc(str) {
    return String(str || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
  }

  if (closeBtn) closeBtn.addEventListener('click', close);
  if (overlay) {
    overlay.addEventListener('click', e => { if (e.target === overlay) close(); });
  }

  return { openForm, close };
})();
