/**
 * tracking.js
 * Відстеження замовлення за номером телефону
 */

const Tracker = (() => {
  const overlay = document.getElementById('trackingModal');
  const closeBtn = document.getElementById('trackingModalClose');
  const resultsEl = document.getElementById('trackingResults');
  const phoneInput = document.getElementById('trackingPhone');

  function openModal() {
    if (!overlay) return;
    overlay.classList.remove('hidden');
    document.body.style.overflow = 'hidden';
    if (phoneInput) setTimeout(() => phoneInput.focus(), 50);
  }

  function close() {
    if (!overlay) return;
    overlay.classList.add('hidden');
    document.body.style.overflow = '';
  }

  async function search() {
    const phone = phoneInput ? phoneInput.value.trim() : '';
    if (!phone) return;

    if (resultsEl) {
      resultsEl.innerHTML = '<p class="tracking-loading">Пошук замовлень...</p>';
    }

    try {
      const res = await fetch(`/api.php?action=trackOrder&phone=${encodeURIComponent(phone)}`);
      const data = await res.json();

      if (data.success && data.data.length > 0) {
        resultsEl.innerHTML = data.data.map(order => `
          <div class="tracking-card">
            <div class="tracking-card__header">
              <span class="tracking-card__id">Замовлення #${order.id}</span>
              <span class="tracking-card__status status--${order.status || 'new'}">${order.status_text || _getStatusLabel(order.status)}</span>
            </div>
            <p class="tracking-card__details">
              <strong>Товар:</strong> ${order.design_name || 'Чохол'} (${order.model_name || order.product_code})<br/>
              <strong>Сума:</strong> ${order.total_amount} ₴<br/>
              <strong>Місто:</strong> ${order.city}, ${order.warehouse}
            </p>
            ${order.ttn ? `<p class="tracking-card__ttn">📦 <strong>ТТН Нової Пошти:</strong> <a href="https://tracking.novaposhta.ua/#/uk?cargo_number=${encodeURIComponent(order.ttn)}" target="_blank" rel="noopener" class="ttn-link">${order.ttn} ↗</a></p>` : ''}
          </div>
        `).join('');
      } else {
        resultsEl.innerHTML = `
          <div class="tracking-empty">
            <span>🔍</span>
            <p>За цим номером телефону замовлень не знайдено.</p>
          </div>
        `;
      }
    } catch (err) {
      resultsEl.innerHTML = `<p class="tracking-error">Помилка пошуку. Спробуйте пізніше.</p>`;
    }
  }

  function _getStatusLabel(st) {
    const map = {
      'new': 'Прийнято в обробку ⏳',
      'in_production': 'У друці 🎨',
      'sent': 'Відправлено Новою Поштою 🚚',
      'delivered': 'Отримано ✅',
      'cancelled': 'Скасовано ❌',
    };
    return map[st] || 'В обробці ⏳';
  }

  if (closeBtn) closeBtn.addEventListener('click', close);
  if (overlay) {
    overlay.addEventListener('click', e => { if (e.target === overlay) close(); });
  }

  return { openModal, close, search };
})();
