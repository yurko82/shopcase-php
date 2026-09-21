/**
 * main.js
 * UI ініціалізація — Toast, header, burger, scroll reveal
 * Каталог більше не потрібен тут — він рендериться PHP
 */

/* ══════════════════════════════
   TOAST
══════════════════════════════ */
const Toast = (() => {
  const container = document.getElementById('toastContainer');

  function show(message, type = 'info', duration = 3500) {
    const icons = { success: '✅', error: '❌', info: 'ℹ️' };

    const toast = document.createElement('div');
    toast.className = `toast toast--${type}`;
    toast.innerHTML = `<span>${icons[type]}</span><span>${message}</span>`;
    container.appendChild(toast);

    setTimeout(() => {
      toast.classList.add('hiding');
      toast.addEventListener('animationend', () => toast.remove(), { once: true });
    }, duration);
  }

  return { show };
})();

/* ══════════════════════════════
   HEADER scroll shadow
══════════════════════════════ */
function initHeader() {
  const header = document.getElementById('header');
  const hero   = document.getElementById('hero');
  if (!hero) return;

  new IntersectionObserver(
    ([entry]) => header.classList.toggle('scrolled', !entry.isIntersecting),
    { threshold: 0 }
  ).observe(hero);
}

/* ══════════════════════════════
   BURGER
══════════════════════════════ */
function initBurger() {
  const burgerBtn = document.getElementById('burgerBtn');
  const nav       = document.getElementById('nav');

  burgerBtn.addEventListener('click', () => {
    const isOpen = nav.classList.toggle('open');
    burgerBtn.classList.toggle('open', isOpen);
    burgerBtn.setAttribute('aria-expanded', isOpen);
  });

  nav.querySelectorAll('.nav__link').forEach(link => {
    link.addEventListener('click', () => {
      nav.classList.remove('open');
      burgerBtn.classList.remove('open');
      burgerBtn.setAttribute('aria-expanded', false);
    });
  });

  document.addEventListener('click', e => {
    if (!nav.contains(e.target) && !burgerBtn.contains(e.target)) {
      nav.classList.remove('open');
      burgerBtn.classList.remove('open');
      burgerBtn.setAttribute('aria-expanded', false);
    }
  });
}

/* ══════════════════════════════
   SMOOTH SCROLL
══════════════════════════════ */
function initSmoothScroll() {
  document.querySelectorAll('a[href^="#"]').forEach(link => {
    link.addEventListener('click', e => {
      const href = link.getAttribute('href');
      if (!href || href === '#' || href.length <= 1) return;

      const id = href.slice(1);
      const target = document.getElementById(id);
      if (!target) return;

      e.preventDefault();
      const top = target.getBoundingClientRect().top + window.scrollY - 80;
      window.scrollTo({ top, behavior: 'smooth' });
    });
  });
}

/* ══════════════════════════════
   SCROLL REVEAL
══════════════════════════════ */
function initScrollReveal() {
  const els = document.querySelectorAll('.step, .advantage-card, .section-header');

  const style = document.createElement('style');
  style.textContent = '.revealed { opacity:1 !important; transform:translateY(0) !important; }';
  document.head.appendChild(style);

  const observer = new IntersectionObserver(entries => {
    entries.forEach(entry => {
      if (entry.isIntersecting) {
        entry.target.classList.add('revealed');
        observer.unobserve(entry.target);
      }
    });
  }, { threshold: 0.15 });

  els.forEach((el, i) => {
    el.style.cssText += `opacity:0;transform:translateY(24px);
      transition:opacity .5s ease ${i * 60}ms,transform .5s ease ${i * 60}ms`;
    observer.observe(el);
  });
}

/* ══════════════════════════════
   CARD KEYBOARD ACCESS
   Картки рендеряться PHP — додаємо Enter як клік
══════════════════════════════ */
function initCardKeys() {
  document.querySelectorAll('.card[tabindex="0"]').forEach(card => {
    card.addEventListener('keydown', e => {
      if (e.key === 'Enter' || e.key === ' ') {
        e.preventDefault();
        card.click();
      }
    });
  });
}

/* ══════════════════════════════
   CART COUNTER
══════════════════════════════ */
const Cart = (() => {
  let count  = 0;
  const el   = document.getElementById('cartCount');

  function increment() { count++; _update(); }
  function getCount()  { return count; }

  function _update() {
    if (el) {
      el.textContent   = count;
      el.style.display = count > 0 ? 'flex' : 'none';
    }
  }

  _update();
  return { increment, getCount };
})();

/* ══════════════════════════════
   ГЛОБАЛЬНІ ПОМИЛКИ
══════════════════════════════ */
window.addEventListener('unhandledrejection', event => {
  console.error('[App]', event.reason);
});

/* ══════════════════════════════
   СТАРТ
══════════════════════════════ */
document.addEventListener('DOMContentLoaded', () => {
  initHeader();
  initBurger();
  initSmoothScroll();
  initScrollReveal();
  initCardKeys();

  console.log('%cShopCase 🛍️ PHP edition ready!',
    'color:#7C3AED;font-weight:bold;font-size:14px');
});

/* ══════════════════════════════
   MODEL SEARCH
══════════════════════════════ */
function initModelSearch() {
  const input    = document.getElementById('modelSearchInput');
  const dropdown = document.getElementById('modelDropdown');
  const hidden   = document.getElementById('modelHidden');
  const clearBtn = document.getElementById('modelSearchClear');
  const form     = document.getElementById('filters');

  if (!input || !dropdown || !window.SHOPCASE_MODELS) return;

  const models = window.SHOPCASE_MODELS;
  let activeIdx = -1;

  /* Емодзі за брендом */
  function getEmoji(name) {
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

  /* Підсвічування збігу */
  function highlight(text, query) {
    if (!query) return text;
    const re = new RegExp(`(${query.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')})`, 'gi');
    return text.replace(re, '<mark>$1</mark>');
  }

  /* Фільтрація */
  function filterModels(query) {
    if (!query || query.length < 1) return [];
    const q = query.toLowerCase().trim();
    return models
      .filter(m => m.name.toLowerCase().includes(q))
      .slice(0, 12);
  }

  /* Рендер dropdown */
  function renderDropdown(results, query) {
    dropdown.innerHTML = '';
    activeIdx = -1;

    if (!results.length) {
      dropdown.innerHTML = `<li class="model-search__no-results">Нічого не знайдено 🔍</li>`;
      dropdown.classList.remove('hidden');
      return;
    }

    results.forEach((model, i) => {
      const li = document.createElement('li');
      li.className = 'model-search__item';
      li.setAttribute('role', 'option');
      li.dataset.id   = model.id;
      li.dataset.name = model.name;
      li.innerHTML = `
        <span class="model-search__item-emoji">${getEmoji(model.name)}</span>
        <div>
          <p class="model-search__item-name">${highlight(model.name, query)}</p>
          <p class="model-search__item-sub">${model.brand} · ${model.avail} матеріалів</p>
        </div>
      `;
      li.addEventListener('mousedown', e => {
        e.preventDefault();
        selectModel(model.id, model.name);
      });
      dropdown.appendChild(li);
    });

    if (results.length >= 12) {
      const footer = document.createElement('li');
      footer.className = 'model-search__footer';
      footer.textContent = `Показано 12 з ${models.filter(m => m.name.toLowerCase().includes(query.toLowerCase())).length} результатів`;
      dropdown.appendChild(footer);
    }

    dropdown.classList.remove('hidden');
  }

  /* Вибрати модель */
  function selectModel(id, name) {
    hidden.value  = id;
    input.value   = name;
    activeIdx     = -1;
    dropdown.classList.add('hidden');
    clearBtn.classList.remove('hidden');

    // Сабміт форми
    form.submit();
  }

  /* Скинути вибір */
  function clearModel() {
    hidden.value = '';
    input.value  = '';
    activeIdx    = -1;
    dropdown.classList.add('hidden');
    clearBtn.classList.add('hidden');
    input.focus();
    form.submit();
  }

  /* Навігація клавішами */
  function setActive(idx) {
    const items = dropdown.querySelectorAll('.model-search__item');
    items.forEach((el, i) => el.classList.toggle('model-search__item--active', i === idx));
    activeIdx = idx;
    if (items[idx]) items[idx].scrollIntoView({ block: 'nearest' });
  }

  /* Події */
  input.addEventListener('input', () => {
    const q = input.value.trim();
    hidden.value = '';
    clearBtn.classList.toggle('hidden', !q);

    if (!q) {
      dropdown.classList.add('hidden');
      return;
    }
    renderDropdown(filterModels(q), q);
  });

  input.addEventListener('keydown', e => {
    const items = dropdown.querySelectorAll('.model-search__item');
    if (e.key === 'ArrowDown') {
      e.preventDefault();
      setActive(Math.min(activeIdx + 1, items.length - 1));
    } else if (e.key === 'ArrowUp') {
      e.preventDefault();
      setActive(Math.max(activeIdx - 1, 0));
    } else if (e.key === 'Enter') {
      e.preventDefault();
      if (activeIdx >= 0 && items[activeIdx]) {
        selectModel(items[activeIdx].dataset.id, items[activeIdx].dataset.name);
      } else if (filterModels(input.value.trim()).length === 1) {
        const m = filterModels(input.value.trim())[0];
        selectModel(m.id, m.name);
      }
    } else if (e.key === 'Escape') {
      dropdown.classList.add('hidden');
      input.blur();
    }
  });

  input.addEventListener('focus', () => {
    const q = input.value.trim();
    if (q && !hidden.value) renderDropdown(filterModels(q), q);
  });

  input.addEventListener('blur', () => {
    setTimeout(() => dropdown.classList.add('hidden'), 150);
  });

  clearBtn.addEventListener('click', clearModel);
}

/* Додаємо виклик в DOMContentLoaded */
document.addEventListener('DOMContentLoaded', () => {
  initModelSearch();
});
