/**
 * app.js
 * Головний UI модуль — Toast, Burger, Header, Hero Showcase, Hero Model Finder
 */

const Toast = (() => {
  const container = document.getElementById('toastContainer');

  function show(message, type = 'info', duration = 3500) {
    if (!container) return;
    const icons = { success: '✅', error: '❌', info: 'ℹ️' };

    const toast = document.createElement('div');
    toast.className = `toast toast--${type}`;
    toast.innerHTML = `<span>${icons[type] || 'ℹ️'}</span><span>${message}</span>`;
    container.appendChild(toast);

    setTimeout(() => {
      toast.classList.add('hiding');
      toast.addEventListener('animationend', () => toast.remove(), { once: true });
    }, duration);
  }

  return { show };
})();

function initHeader() {
  const header = document.getElementById('header');
  const hero = document.getElementById('hero');
  if (!header || !hero) return;

  new IntersectionObserver(
    ([entry]) => header.classList.toggle('scrolled', !entry.isIntersecting),
    { threshold: 0 }
  ).observe(hero);
}

function initBurger() {
  const burgerBtn = document.getElementById('burgerBtn');
  const nav = document.getElementById('nav');
  if (!burgerBtn || !nav) return;

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
}

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

function initDesignSearch() {
  const input = document.getElementById('designSearchInput');
  const clearBtn = document.getElementById('designSearchClear');
  if (!input) return;

  let timer = null;

  const triggerSearch = (q) => {
    const url = new URL(window.location);
    if (q) {
      url.searchParams.set('q', q);
    } else {
      url.searchParams.delete('q');
    }
    url.searchParams.delete('page');
    url.hash = 'catalog';
    window.location.href = url.toString();
  };

  input.addEventListener('input', () => {
    const q = input.value.trim();
    if (clearBtn) clearBtn.classList.toggle('hidden', !q);

    clearTimeout(timer);
    timer = setTimeout(() => {
      triggerSearch(q);
    }, 500);
  });

  input.addEventListener('keydown', (e) => {
    if (e.key === 'Enter') {
      e.preventDefault();
      clearTimeout(timer);
      triggerSearch(input.value.trim());
    }
  });

  if (clearBtn) {
    clearBtn.addEventListener('click', () => {
      input.value = '';
      clearBtn.classList.add('hidden');
      triggerSearch('');
    });
  }
}

/**
 * Hero 3D Interactive Showcase Slider
 */
function initHeroShowcase() {
  const showcaseCard = document.getElementById('heroShowcaseCard');
  const mockupClick  = document.getElementById('heroMockupClick');
  const showcaseImg  = document.getElementById('heroShowcaseImg');
  const metaCat      = document.getElementById('heroShowcaseCat');
  const metaTitle    = document.getElementById('heroShowcaseTitle');
  const matTag       = document.getElementById('heroMaterialTag');
  const switcher     = document.getElementById('heroShowcaseSwitcher');
  if (!showcaseCard || !showcaseImg || !switcher) return;

  const HERO_SHOWCASE_ITEMS = [
    {
      id: 5293,
      name: 'Герб України золотий',
      category_slug: 'made-in-ukraine',
      category_name: 'Made in Ukraine',
      image: '/design/made-in-ukraine/5293u-4029.jpg',
      material: 'Bumper MagSafe · від 199 ₴'
    },
    {
      id: 825,
      name: 'Димчастий кіт',
      category_slug: 'animals',
      category_name: 'Тварини',
      image: '/design/animals/825u-4029.jpg',
      material: 'Силікон преміум · від 199 ₴'
    },
    {
      id: 6075,
      name: 'Пурпурова сакура',
      category_slug: 'flowers',
      category_name: 'Квіти',
      image: '/design/flowers/6075u-4029.jpg',
      material: '3D Глянець · від 249 ₴'
    },
    {
      id: 6098,
      name: 'Спорткар BMW M Power',
      category_slug: 'brands',
      category_name: 'Бренди & Авто',
      image: '/design/brands/6098u-4029.jpg',
      material: 'TPU чорний матовий · від 229 ₴'
    },
    {
      id: 6777,
      name: 'Капібара IT',
      category_slug: 'animals',
      category_name: 'Тварини',
      image: '/design/animals/6777u-4029.jpg',
      material: 'Силікон + кути · від 269 ₴'
    },
    {
      id: 6100,
      name: 'Тандзіро Клинок',
      category_slug: 'anime',
      category_name: 'Аніме',
      image: '/design/anime/6100u-4029.jpg',
      material: 'Bumper MagSafe · від 349 ₴'
    }
  ];

  let currentIndex = 0;
  let autoTimer = null;
  let isHovered = false;

  function setHeroPrint(index, animate = true) {
    if (index < 0 || index >= HERO_SHOWCASE_ITEMS.length) return;
    currentIndex = index;
    const item = HERO_SHOWCASE_ITEMS[index];

    // Update active pill
    switcher.querySelectorAll('.showcase-pill').forEach((btn, idx) => {
      btn.classList.toggle('showcase-pill--active', idx === index);
    });

    if (animate) {
      showcaseImg.style.opacity = '0.3';
      showcaseImg.style.transform = 'scale(0.96)';
      setTimeout(() => {
        showcaseImg.src = item.image;
        showcaseImg.alt = item.name;
        if (metaCat) metaCat.textContent = item.category_name;
        if (metaTitle) metaTitle.textContent = item.name;
        if (matTag) matTag.textContent = item.material;
        showcaseImg.style.opacity = '1';
        showcaseImg.style.transform = 'scale(1)';
      }, 150);
    } else {
      showcaseImg.src = item.image;
      showcaseImg.alt = item.name;
      if (metaCat) metaCat.textContent = item.category_name;
      if (metaTitle) metaTitle.textContent = item.name;
      if (matTag) matTag.textContent = item.material;
    }
  }

  // Pill click handlers
  switcher.addEventListener('click', e => {
    const pill = e.target.closest('.showcase-pill');
    if (!pill) return;
    const idx = parseInt(pill.dataset.index, 10);
    if (!isNaN(idx)) {
      setHeroPrint(idx);
      resetAutoTimer();
    }
  });

  // Click on mockup opens customizer
  if (mockupClick) {
    mockupClick.addEventListener('click', () => {
      const currentItem = HERO_SHOWCASE_ITEMS[currentIndex];
      if (typeof Customizer !== 'undefined') {
        Customizer.open(currentItem);
      }
    });

    mockupClick.addEventListener('keydown', e => {
      if (e.key === 'Enter' || e.key === ' ') {
        e.preventDefault();
        mockupClick.click();
      }
    });
  }

  function startAutoTimer() {
    stopAutoTimer();
    autoTimer = setInterval(() => {
      if (!isHovered) {
        const nextIndex = (currentIndex + 1) % HERO_SHOWCASE_ITEMS.length;
        setHeroPrint(nextIndex);
      }
    }, 4000);
  }

  function stopAutoTimer() {
    if (autoTimer) clearInterval(autoTimer);
  }

  function resetAutoTimer() {
    stopAutoTimer();
    startAutoTimer();
  }

  showcaseCard.addEventListener('mouseenter', () => { isHovered = true; });
  showcaseCard.addEventListener('mouseleave', () => { isHovered = false; });

  startAutoTimer();
}

/**
 * Hero Model Quick Finder Widget
 */
function initHeroModelFinder() {
  const finderBox      = document.getElementById('heroFinder');
  const input          = document.getElementById('heroModelInput');
  const dropdown       = document.getElementById('heroModelDropdown');
  const clearBtn       = document.getElementById('heroModelClear');
  const selectedNotice = document.getElementById('heroSelectedNotice');
  const selectedText   = document.getElementById('heroSelectedText');
  const changeBtn      = document.getElementById('heroChangeModelBtn');
  if (!finderBox || !input || !dropdown) return;

  function getModelsList() {
    if (typeof Customizer !== 'undefined' && typeof Customizer.getModels === 'function') {
      return Customizer.getModels();
    }
    return window.SHOPCASE_MODELS || [];
  }

  // Check if a model is already selected
  function checkSavedModel() {
    if (typeof Customizer === 'undefined') return;
    const saved = Customizer.getSelectedModel();
    if (saved && saved.name) {
      if (selectedNotice && selectedText) {
        selectedText.textContent = `Обрано: ${saved.name} (${saved.avail || '3+'} матеріалів)`;
        selectedNotice.classList.remove('hidden');
        input.value = saved.name;
        if (clearBtn) clearBtn.classList.remove('hidden');
      }
    }
  }

  function renderDropdown(q = '') {
    dropdown.innerHTML = '';
    const query = q.toLowerCase().trim();
    const models = getModelsList();

    let filtered = models;
    if (query) {
      filtered = models.filter(m =>
        (m.name && m.name.toLowerCase().includes(query)) ||
        (m.brand && m.brand.toLowerCase().includes(query))
      );
    }

    if (filtered.length === 0) {
      dropdown.innerHTML = `<li class="hero__finder-item--empty">Модель не знайдена 🔍 Введіть: iPhone, Samsung, Xiaomi...</li>`;
      dropdown.classList.remove('hidden');
      return;
    }

    filtered.slice(0, 15).forEach(model => {
      const li = document.createElement('li');
      li.className = 'hero__finder-item';
      li.innerHTML = `
        <span class="hero__finder-icon-emoji">${getBrandEmoji(model.name)}</span>
        <div>
          <p class="hero__finder-item__name">${highlightMatch(model.name, query)}</p>
          <span class="hero__finder-item__sub">${model.brand || ''} · ${model.avail || '3+'} матеріалів</span>
        </div>
      `;

      li.addEventListener('mousedown', e => {
        e.preventDefault();
        selectModel(model);
      });

      dropdown.appendChild(li);
    });

    dropdown.classList.remove('hidden');
  }

  function selectModel(model) {
    if (typeof Customizer !== 'undefined') {
      Customizer.setSelectedModel(model);
    }
    input.value = model.name;
    dropdown.classList.add('hidden');
    if (clearBtn) clearBtn.classList.remove('hidden');

    if (selectedNotice && selectedText) {
      selectedText.textContent = `Обрано: ${model.name} (${model.avail || '3+'} матеріалів)`;
      selectedNotice.classList.remove('hidden');
    }

    Toast.show(`Смартфон "${model.name}" збережено! Оберіть принт нижче 👇`, 'success');

    // Smooth scroll to catalog
    const catalog = document.getElementById('catalog');
    if (catalog) {
      setTimeout(() => {
        const top = catalog.getBoundingClientRect().top + window.scrollY - 80;
        window.scrollTo({ top, behavior: 'smooth' });
      }, 400);
    }
  }

  input.addEventListener('input', () => {
    const q = input.value.trim();
    if (clearBtn) clearBtn.classList.toggle('hidden', !q);
    renderDropdown(q);
  });

  input.addEventListener('focus', () => {
    renderDropdown(input.value.trim());
  });

  input.addEventListener('click', () => {
    renderDropdown(input.value.trim());
  });

  input.addEventListener('keydown', e => {
    if (e.key === 'Escape') {
      dropdown.classList.add('hidden');
    } else if (e.key === 'Enter') {
      const firstItem = dropdown.querySelector('.hero__finder-item');
      if (firstItem) {
        e.preventDefault();
        firstItem.dispatchEvent(new MouseEvent('mousedown'));
      }
    }
  });

  if (clearBtn) {
    clearBtn.addEventListener('click', e => {
      e.stopPropagation();
      input.value = '';
      clearBtn.classList.add('hidden');
      if (selectedNotice) selectedNotice.classList.add('hidden');
      if (typeof Customizer !== 'undefined') {
        Customizer.setSelectedModel(null);
      }
      input.focus();
      renderDropdown('');
    });
  }

  if (changeBtn) {
    changeBtn.addEventListener('click', () => {
      if (selectedNotice) selectedNotice.classList.add('hidden');
      input.value = '';
      input.focus();
      renderDropdown('');
    });
  }

  document.addEventListener('click', e => {
    if (!finderBox.contains(e.target)) {
      dropdown.classList.add('hidden');
    }
  });

  checkSavedModel();
}

function getBrandEmoji(name) {
  const n = (name || '').toLowerCase();
  if (n.includes('apple') || n.includes('iphone'))  return '🍎';
  if (n.includes('samsung'))                         return '💎';
  if (n.includes('xiaomi') || n.includes('redmi') || n.includes('poco')) return '⚡';
  if (n.includes('huawei') || n.includes('honor'))  return '🌸';
  if (n.includes('google') || n.includes('pixel'))  return '🔍';
  if (n.includes('oneplus') || n.includes('realme')) return '🔴';
  return '📱';
}

function highlightMatch(text, q) {
  if (!q) return escapeHtml(text);
  const re = new RegExp(`(${q.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')})`, 'gi');
  return escapeHtml(text).replace(re, '<mark>$1</mark>');
}

function escapeHtml(str) {
  return String(str || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}

document.addEventListener('DOMContentLoaded', () => {
  initHeader();
  initBurger();
  initSmoothScroll();
  initDesignSearch();
  initHeroShowcase();
  initHeroModelFinder();
  console.log('%cShopCase 🛍️ v2.2 Ready!', 'color:#FF4ECD;font-weight:bold;font-size:14px');
});
