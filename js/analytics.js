/**
 * analytics.js
 * Комплексний модуль аналітики ShopCase:
 * - Автоматичний збір та збереження UTM-параметрів (utm_source, utm_medium, utm_campaign, utm_content, utm_term, gclid, fbclid, ttclid)
 * - Збереження джерела першого входу (First Touch Attribution) та поточного сеансу (Last Touch)
 * - Відстеження e-commerce подій: PageView, ViewContent, InitiateCheckout, Purchase, ConstructorOpen
 * - Інтеграція з Google Tag Manager (dataLayer), Google Analytics 4, Meta Pixel (fbq), TikTok Pixel (ttq)
 */

const Analytics = (() => {
  const STORAGE_KEY = 'shopcase_utm';
  const COOKIE_NAME = 'sc_utm';

  /**
   * Отримати значення cookie
   */
  function _getCookie(name) {
    const matches = document.cookie.match(new RegExp('(?:^|; )' + name.replace(/([\.$?*|{}\(\)\[\]\\\/\+^])/g, '\\$1') + '=([^;]*)'));
    return matches ? decodeURIComponent(matches[1]) : undefined;
  }

  /**
   * Встановити cookie (на 30 днів)
   */
  function _setCookie(name, value, days = 30) {
    const date = new Date();
    date.setTime(date.getTime() + (days * 24 * 60 * 60 * 1000));
    document.cookie = `${name}=${encodeURIComponent(value)};expires=${date.toUTCString()};path=/;SameSite=Lax`;
  }

  /**
   * Визначення джерела за document.referrer (якщо немає явних UTM)
   */
  function _detectOrganicSource(ref) {
    if (!ref) return { source: 'direct', medium: 'none' };
    const r = ref.toLowerCase();
    if (r.includes('instagram.com') || r.includes('ig.me')) return { source: 'instagram', medium: 'social' };
    if (r.includes('facebook.com') || r.includes('fb.com')) return { source: 'facebook', medium: 'social' };
    if (r.includes('tiktok.com')) return { source: 'tiktok', medium: 'social' };
    if (r.includes('google.com') || r.includes('google.com.ua')) return { source: 'google', medium: 'organic' };
    if (r.includes('t.me') || r.includes('telegram')) return { source: 'telegram', medium: 'messenger' };
    if (r.includes('viber')) return { source: 'viber', medium: 'messenger' };
    if (r.includes('youtube.com')) return { source: 'youtube', medium: 'social' };
    try {
      const url = new URL(ref);
      return { source: url.hostname.replace(/^www\./, ''), medium: 'referral' };
    } catch (e) {
      return { source: 'referral', medium: 'referral' };
    }
  }

  /**
   * Ініціалізація та захоплення UTM-параметрів з URL
   */
  function _initUtm() {
    try {
      const params = new URLSearchParams(window.location.search);
      const utmSource = params.get('utm_source');
      const utmMedium = params.get('utm_medium');
      const utmCampaign = params.get('utm_campaign');
      const utmContent = params.get('utm_content');
      const utmTerm = params.get('utm_term');
      const gclid = params.get('gclid');
      const fbclid = params.get('fbclid');
      const ttclid = params.get('ttclid');

      let currentData = null;

      // Якщо в URL є параметри рекламного переходу
      if (utmSource || gclid || fbclid || ttclid) {
        let detectedSource = utmSource;
        let detectedMedium = utmMedium || 'cpc';

        if (!detectedSource && gclid) {
          detectedSource = 'google';
          detectedMedium = 'cpc';
        } else if (!detectedSource && fbclid) {
          detectedSource = 'meta';
          detectedMedium = 'cpc';
        } else if (!detectedSource && ttclid) {
          detectedSource = 'tiktok';
          detectedMedium = 'cpc';
        }

        currentData = {
          source: detectedSource || 'direct',
          medium: detectedMedium || 'none',
          campaign: utmCampaign || '',
          content: utmContent || '',
          term: utmTerm || '',
          gclid: gclid || '',
          fbclid: fbclid || '',
          ttclid: ttclid || '',
          referrer: document.referrer || '',
          landing_page: window.location.pathname + window.location.search,
          timestamp: new Date().toISOString()
        };
      } else {
        // Перевіряємо, чи вже збережено дані з попереднього візиту
        const saved = _loadSavedUtm();
        if (saved && saved.source) {
          currentData = saved;
        } else {
          // Якщо це перший візит без UTM
          const detected = _detectOrganicSource(document.referrer);
          currentData = {
            source: detected.source,
            medium: detected.medium,
            campaign: '',
            content: '',
            term: '',
            gclid: '',
            fbclid: '',
            ttclid: '',
            referrer: document.referrer || '',
            landing_page: window.location.pathname + window.location.search,
            timestamp: new Date().toISOString()
          };
        }
      }

      if (currentData) {
        _saveUtm(currentData);
      }
    } catch (e) {
      console.warn('[Analytics] Error initializing UTM:', e);
    }
  }

  function _saveUtm(data) {
    try {
      const json = JSON.stringify(data);
      localStorage.setItem(STORAGE_KEY, json);
      sessionStorage.setItem(STORAGE_KEY, json);
      _setCookie(COOKIE_NAME, json, 30);
    } catch (e) {}
  }

  function _loadSavedUtm() {
    try {
      const sess = sessionStorage.getItem(STORAGE_KEY);
      if (sess) return JSON.parse(sess);
      const loc = localStorage.getItem(STORAGE_KEY);
      if (loc) return JSON.parse(loc);
      const cook = _getCookie(COOKIE_NAME);
      if (cook) return JSON.parse(cook);
    } catch (e) {}
    return null;
  }

  /**
   * Отримати об'єкт UTM даних для замовлення
   */
  function getUtmData() {
    const data = _loadSavedUtm();
    if (data) return data;
    const detected = _detectOrganicSource(document.referrer);
    return {
      source: detected.source,
      medium: detected.medium,
      campaign: '',
      content: '',
      term: '',
      referrer: document.referrer || '',
      landing_page: window.location.pathname + window.location.search
    };
  }

  /**
   * 1. Відстеження перегляду товару/принта (ViewContent / view_item)
   */
  function trackViewContent(design) {
    if (!design) return;
    const designId = String(design.id || '');
    const name = design.name || `Принт #${designId}`;
    const category = design.category_name || design.category_slug || 'Чохли';
    const price = Number(design.price || 199);

    // Google Tag Manager / GA4 dataLayer
    window.dataLayer = window.dataLayer || [];
    window.dataLayer.push({
      event: 'view_item',
      ecommerce: {
        currency: 'UAH',
        value: price,
        items: [{
          item_id: designId,
          item_name: name,
          item_category: category,
          price: price,
          quantity: 1
        }]
      }
    });

    // Meta Pixel (Facebook/Instagram)
    if (typeof window.fbq === 'function') {
      window.fbq('track', 'ViewContent', {
        content_name: name,
        content_category: category,
        content_ids: [designId],
        content_type: 'product',
        value: price,
        currency: 'UAH'
      });
    }

    // TikTok Pixel
    if (typeof window.ttq === 'object' && typeof window.ttq.track === 'function') {
      window.ttq.track('ViewContent', {
        content_id: designId,
        content_type: 'product',
        content_name: name,
        content_category: category,
        value: price,
        currency: 'UAH'
      });
    }

    // Direct Google Analytics gtag
    if (typeof window.gtag === 'function') {
      window.gtag('event', 'view_item', {
        currency: 'UAH',
        value: price,
        items: [{ item_id: designId, item_name: name, item_category: category, price: price }]
      });
    }
  }

  /**
   * 2. Відстеження початку оформлення замовлення (InitiateCheckout / begin_checkout)
   */
  function trackInitiateCheckout(design, model, material, price) {
    if (!design) return;
    const designId = String(design.id || '');
    const name = design.name || `Принт #${designId}`;
    const modelName = model ? model.name : '';
    const matLabel = (window.SHOPCASE_MATERIALS && window.SHOPCASE_MATERIALS[material]) || material;
    const finalPrice = Number(price || 199);

    // GTM dataLayer
    window.dataLayer = window.dataLayer || [];
    window.dataLayer.push({
      event: 'begin_checkout',
      ecommerce: {
        currency: 'UAH',
        value: finalPrice,
        items: [{
          item_id: designId,
          item_name: name,
          item_category: matLabel,
          item_variant: modelName,
          price: finalPrice,
          quantity: 1
        }]
      }
    });

    // Meta Pixel
    if (typeof window.fbq === 'function') {
      window.fbq('track', 'InitiateCheckout', {
        content_name: `${name} (${modelName})`,
        content_category: matLabel,
        content_ids: [designId],
        num_items: 1,
        value: finalPrice,
        currency: 'UAH'
      });
    }

    // TikTok Pixel
    if (typeof window.ttq === 'object' && typeof window.ttq.track === 'function') {
      window.ttq.track('InitiateCheckout', {
        content_id: designId,
        content_name: `${name} (${modelName})`,
        value: finalPrice,
        currency: 'UAH',
        quantity: 1
      });
    }

    // Direct gtag
    if (typeof window.gtag === 'function') {
      window.gtag('event', 'begin_checkout', {
        currency: 'UAH',
        value: finalPrice,
        items: [{ item_id: designId, item_name: name, item_variant: modelName, price: finalPrice }]
      });
    }
  }

  /**
   * 3. Відстеження успішної покупки (Purchase)
   */
  function trackPurchase(order) {
    if (!order) return;
    const orderId = String(order.order_id || '');
    const amount = Number(order.total_amount || 199);
    const productCode = String(order.product_code || '');
    const productName = String(order.product_name || order.product_code || 'Чохол ShopCase');

    // GTM dataLayer
    window.dataLayer = window.dataLayer || [];
    window.dataLayer.push({
      event: 'purchase',
      ecommerce: {
        transaction_id: orderId,
        value: amount,
        currency: 'UAH',
        items: [{
          item_id: productCode,
          item_name: productName,
          price: amount,
          quantity: 1
        }]
      }
    });

    // Meta Pixel
    if (typeof window.fbq === 'function') {
      window.fbq('track', 'Purchase', {
        content_name: productName,
        content_ids: [productCode],
        content_type: 'product',
        value: amount,
        currency: 'UAH',
        num_items: 1
      });
    }

    // TikTok Pixel
    if (typeof window.ttq === 'object' && typeof window.ttq.track === 'function') {
      window.ttq.track('CompletePayment', {
        content_id: productCode,
        content_type: 'product',
        content_name: productName,
        value: amount,
        currency: 'UAH'
      });
    }

    // Direct gtag
    if (typeof window.gtag === 'function') {
      window.gtag('event', 'purchase', {
        transaction_id: orderId,
        currency: 'UAH',
        value: amount,
        items: [{ item_id: productCode, item_name: productName, price: amount }]
      });
    }
  }

  /**
   * 4. Відстеження відкриття 3D-конструктора зі своїм фото
   */
  function trackConstructorOpen(source = 'button') {
    window.dataLayer = window.dataLayer || [];
    window.dataLayer.push({
      event: 'constructor_open',
      constructor_source: source
    });

    if (typeof window.fbq === 'function') {
      window.fbq('trackCustom', 'ConstructorOpen', { source: source });
    }

    if (typeof window.ttq === 'object' && typeof window.ttq.track === 'function') {
      window.ttq.track('ClickButton', { button_name: 'open_photo_constructor' });
    }
  }

  // Ініціалізація при завантаженні скрипта
  _initUtm();

  return {
    getUtmData,
    trackViewContent,
    trackInitiateCheckout,
    trackPurchase,
    trackConstructorOpen
  };
})();
