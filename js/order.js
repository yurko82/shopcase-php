/**
 * order.js
 * Форма замовлення — POST на /api.php (серверний endpoint)
 * Жодних прямих запитів до EndorPhone з браузера
 */

const Order = (() => {

  /* ── DOM ── */
  const overlay  = document.getElementById('orderModal');
  const closeBtn = document.getElementById('orderModalClose');
  const bodyEl   = document.getElementById('orderModalBody');

  /* ── Стан ── */
  let currentItem      = null;
  let currentMaterial  = null;
  let materialPrices   = {};
  let materialLabels   = {};
  let paymentType      = '1';
  let prepayType       = 'card';
  let deliveryPayer    = '1';

  /* ══════════════════════════════
     ВІДКРИТИ ФОРМУ
  ══════════════════════════════ */

  function openForm(item, material, prices, labels) {
    currentItem     = item;
    currentMaterial = material;
    materialPrices  = prices;
    materialLabels  = labels;
    paymentType     = '1';
    prepayType      = 'card';
    deliveryPayer   = '1';

    _render();

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
  }

  /* ══════════════════════════════
     РЕНДЕР ФОРМИ
  ══════════════════════════════ */

  function _render() {
    const price   = materialPrices[currentMaterial];
    const matName = materialLabels[currentMaterial];

    bodyEl.innerHTML = `
      <div class="order-summary">
        <span class="order-summary__name">
          📱 ${_esc(currentItem.name)} · ${_esc(matName)}
        </span>
        <span class="order-summary__price">${price} ₴</span>
      </div>

      <form class="order-form" id="orderForm" novalidate>

        <div class="form-row">
          <div class="form-group">
            <label class="form-label required" for="fSurname">Прізвище</label>
            <input class="form-input" id="fSurname" name="surname"
              type="text" placeholder="Іваненко" autocomplete="family-name" />
            <span class="form-error" id="eSurname"></span>
          </div>
          <div class="form-group">
            <label class="form-label required" for="fName">Ім'я</label>
            <input class="form-input" id="fName" name="name"
              type="text" placeholder="Іван" autocomplete="given-name" />
            <span class="form-error" id="eName"></span>
          </div>
        </div>

        <div class="form-group">
          <label class="form-label required" for="fPhone">Телефон</label>
          <input class="form-input" id="fPhone" name="phone"
            type="tel" placeholder="+380XXXXXXXXX" autocomplete="tel" />
          <span class="form-error" id="ePhone"></span>
        </div>

        <div class="form-group">
          <label class="form-label" for="fEmail">Email</label>
          <input class="form-input" id="fEmail" name="email"
            type="email" placeholder="mail@example.com" autocomplete="email" />
          <span class="form-error" id="eEmail"></span>
        </div>

        <div class="form-section">
          <p class="form-section-title">🚚 Доставка — Нова Пошта</p>
          <div class="form-group">
            <label class="form-label required" for="fCity">Місто</label>
            <input class="form-input" id="fCity" name="city"
              type="text" placeholder="Київ" />
            <span class="form-error" id="eCity"></span>
          </div>
          <div class="form-group">
            <label class="form-label required" for="fWarehouse">Відділення НП</label>
            <input class="form-input" id="fWarehouse" name="warehouse"
              type="text" placeholder="Відділення №1" />
            <span class="form-error" id="eWarehouse"></span>
          </div>
        </div>

        <div class="form-section">
          <p class="form-section-title">💳 Спосіб оплати</p>
          <div class="payment-toggle" id="paymentToggle">
            <div class="payment-option">
              <input type="radio" name="payment" id="payNakladena" value="1" checked />
              <label class="payment-option__label" for="payNakladena">
                <span class="payment-option__icon">📦</span>
                <span class="payment-option__name">Накладений платіж</span>
                <span class="payment-option__desc">Оплата при отриманні</span>
              </label>
            </div>
            <div class="payment-option">
              <input type="radio" name="payment" id="payPrepay" value="2" />
              <label class="payment-option__label" for="payPrepay">
                <span class="payment-option__icon">💳</span>
                <span class="payment-option__name">Передоплата</span>
                <span class="payment-option__desc">Картка / LiqPay / Баланс</span>
              </label>
            </div>
          </div>

          <div class="prepayment-type hidden-soft" id="prepayTypeWrap">
            <button type="button" class="prepay-chip active" data-prepay="card">💳 Картка</button>
            <button type="button" class="prepay-chip" data-prepay="liqpay">📱 LiqPay</button>
            <button type="button" class="prepay-chip" data-prepay="balance">🏦 Баланс</button>
          </div>
        </div>

        <div class="form-section">
          <p class="form-section-title">📮 Хто оплачує доставку?</p>
          <div class="payer-toggle" id="deliveryPayerToggle">
            <button type="button" class="payer-btn active" data-payer="1">Я (отримувач)</button>
            <button type="button" class="payer-btn" data-payer="2">Магазин</button>
          </div>
        </div>

        <div class="form-group">
          <label class="form-label" for="fComment">Коментар</label>
          <textarea class="form-textarea" id="fComment" name="comment"
            placeholder="Побажання до замовлення..." rows="3"></textarea>
        </div>

        <div class="form-submit">
          <button type="submit" class="btn btn--primary" id="submitOrderBtn">
            Оформити замовлення
          </button>
        </div>

      </form>
    `;

    _bindFormEvents();
  }

  /* ══════════════════════════════
     ПОДІЇ ФОРМИ
  ══════════════════════════════ */

  function _bindFormEvents() {
    /* Оплата */
    document.getElementById('paymentToggle').addEventListener('change', e => {
      if (e.target.name !== 'payment') return;
      paymentType = e.target.value;
      document.getElementById('prepayTypeWrap')
        .classList.toggle('hidden-soft', paymentType !== '2');
    });

    /* Тип передоплати */
    document.getElementById('prepayTypeWrap').addEventListener('click', e => {
      const chip = e.target.closest('.prepay-chip');
      if (!chip) return;
      prepayType = chip.dataset.prepay;
      document.querySelectorAll('.prepay-chip').forEach(c =>
        c.classList.toggle('active', c === chip)
      );
    });

    /* Платник доставки */
    document.getElementById('deliveryPayerToggle').addEventListener('click', e => {
      const btn = e.target.closest('.payer-btn');
      if (!btn) return;
      deliveryPayer = btn.dataset.payer;
      document.querySelectorAll('.payer-btn').forEach(b =>
        b.classList.toggle('active', b === btn)
      );
    });

    /* Live-валідація */
    document.querySelectorAll('#orderForm .form-input').forEach(input => {
      input.addEventListener('blur',  () => _validateField(input));
      input.addEventListener('input', () => {
        if (input.classList.contains('error')) _validateField(input);
      });
    });

    /* Відправка */
    document.getElementById('orderForm').addEventListener('submit', async e => {
      e.preventDefault();
      if (_validateAll()) await _submit();
    });
  }

  /* ══════════════════════════════
     ВАЛІДАЦІЯ
  ══════════════════════════════ */

  const rules = {
    surname:   { required: true,  min: 2,  label: 'Прізвище' },
    name:      { required: true,  min: 2,  label: "Ім'я" },
    phone:     { required: true,  pattern: /^\+380\d{9}$/, label: 'Телефон (+380XXXXXXXXX)' },
    email:     { required: false, pattern: /^[^\s@]+@[^\s@]+\.[^\s@]+$/, label: 'Email' },
    city:      { required: true,  min: 2,  label: 'Місто' },
    warehouse: { required: true,  min: 2,  label: 'Відділення' },
  };

  function _validateField(input) {
    const rule = rules[input.name];
    if (!rule) return true;

    const val = input.value.trim();
    let error  = '';

    if (rule.required && !val) {
      error = `${rule.label} — обов'язкове поле`;
    } else if (val && rule.min && val.length < rule.min) {
      error = `Мінімум ${rule.min} символи`;
    } else if (val && rule.pattern && !rule.pattern.test(val)) {
      error = `Невірний формат: ${rule.label}`;
    }

    const errId = 'e' + input.name.charAt(0).toUpperCase() + input.name.slice(1);
    const errEl = document.getElementById(errId);
    if (errEl) errEl.textContent = error;

    input.classList.toggle('error', !!error);
    input.classList.toggle('valid', !error && !!val);

    return !error;
  }

  function _validateAll() {
    let valid = true;
    document.querySelectorAll('#orderForm .form-input').forEach(input => {
      if (!_validateField(input)) valid = false;
    });
    return valid;
  }

  /* ══════════════════════════════
     ВІДПРАВКА на /api.php
  ══════════════════════════════ */

  async function _submit() {
    const submitBtn = document.getElementById('submitOrderBtn');
    submitBtn.classList.add('loading');
    submitBtn.textContent = 'Надсилаємо...';
    submitBtn.disabled    = true;

    const price       = materialPrices[currentMaterial];
    const productCode = `${currentItem.id}-${currentMaterial}`;

    const payload = {
      action: 'createOrder',
      data: {
        surname:             _val('fSurname'),
        name:                _val('fName'),
        phone:               _val('fPhone'),
        email:               _val('fEmail'),
        city:                _val('fCity'),
        warehouse:           _val('fWarehouse'),
        payment_type:        paymentType,
        perpayment_type:     paymentType === '2' ? prepayType : 'card',
        delivery_payer:      deliveryPayer,
        cash_delivery_payer: '1',
        comment:             _val('fComment'),
        products: [
          { code: productCode, qty: '1', price: String(price) },
        ],
      },
    };

    try {
      const res  = await fetch('/api.php', {
        method:  'POST',
        headers: { 'Content-Type': 'application/json' },
        body:    JSON.stringify(payload),
      });

      const data = await res.json();

      if (data.success) {
        _showSuccess(data.id);
        Toast.show(`Замовлення #${data.id} оформлено!`, 'success');
      } else {
        throw new Error(data.error || 'Помилка сервера');
      }

    } catch (err) {
      Toast.show(err.message || 'Помилка. Спробуйте ще раз.', 'error');
      submitBtn.classList.remove('loading');
      submitBtn.textContent = 'Оформити замовлення';
      submitBtn.disabled    = false;
    }
  }

  /* ══════════════════════════════
     УСПІХ
  ══════════════════════════════ */

  function _showSuccess(orderId) {
    bodyEl.innerHTML = `
      <div class="modal-success">
        <span class="modal-success__icon">🎉</span>
        <h3 class="modal-success__title">Замовлення прийнято!</h3>
        <p class="modal-success__text">
          Ми зв'яжемося з вами найближчим часом.<br/>
          Доставка Новою Поштою по всій Україні.
        </p>
        <div class="modal-success__order-id">
          Номер вашого замовлення
          <span>#${orderId}</span>
        </div>
        <button class="btn btn--outline" id="successCloseBtn">
          Повернутися до каталогу
        </button>
      </div>
    `;
    document.getElementById('successCloseBtn').addEventListener('click', close);
  }

  /* ══════════════════════════════
     УТІЛІТИ
  ══════════════════════════════ */

  function _val(id) {
    const el = document.getElementById(id);
    return el ? el.value.trim() : '';
  }

  function _esc(str) {
    return String(str)
      .replace(/&/g,'&amp;').replace(/</g,'&lt;')
      .replace(/>/g,'&gt;').replace(/"/g,'&quot;');
  }

  /* Overlay події */
  closeBtn.addEventListener('click', close);
  overlay.addEventListener('click', e => { if (e.target === overlay) close(); });
  document.addEventListener('keydown', e => {
    if (e.key === 'Escape' && !overlay.classList.contains('hidden')) close();
  });

  return { openForm, close };
})();
