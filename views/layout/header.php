<?php
$marketingSettings = \App\Config::getMarketingSettings();
?>
<!DOCTYPE html>
<html lang="uk">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title><?= htmlspecialchars($pageTitle ?? 'ShopCase — Авторські чохли для 670+ смартфонів') ?></title>
  <meta name="description" content="<?= htmlspecialchars($metaDescription ?? 'Яскраві чохли з якісним УФ-друком для 670+ моделей телефонів. Доставка Новою Поштою по Україні.') ?>" />
  <?php if (!empty($canonicalUrl)): ?>
    <link rel="canonical" href="<?= htmlspecialchars($canonicalUrl) ?>" />
  <?php endif; ?>

  <?php if (!empty($marketingSettings['gtm_id'])): ?>
  <!-- Google Tag Manager -->
  <script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':
  new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],
  j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src=
  'https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);
  })(window,document,'script','dataLayer','<?= htmlspecialchars($marketingSettings['gtm_id']) ?>');</script>
  <!-- End Google Tag Manager -->
  <?php endif; ?>

  <?php if (!empty($marketingSettings['ga4_id']) && empty($marketingSettings['gtm_id'])): ?>
  <!-- Google Analytics 4 (gtag.js) -->
  <script async src="https://www.googletagmanager.com/gtag/js?id=<?= htmlspecialchars($marketingSettings['ga4_id']) ?>"></script>
  <script>
    window.dataLayer = window.dataLayer || [];
    function gtag(){dataLayer.push(arguments);}
    gtag('js', new Date());
    gtag('config', '<?= htmlspecialchars($marketingSettings['ga4_id']) ?>');
  </script>
  <?php endif; ?>

  <?php if (!empty($marketingSettings['meta_pixel_id'])): ?>
  <!-- Meta Pixel Code -->
  <script>
  !function(f,b,e,v,n,t,s)
  {if(f.fbq)return;n=f.fbq=function(){n.callMethod?
  n.callMethod.apply(n,arguments):n.queue.push(arguments)};
  if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';
  n.queue=[];t=b.createElement(e);t.async=!0;
  t.src=v;s=b.getElementsByTagName(e)[0];
  s.parentNode.insertBefore(t,s)}(window, document,'script',
  'https://connect.facebook.net/en_US/fbevents.js');
  fbq('init', '<?= htmlspecialchars($marketingSettings['meta_pixel_id']) ?>');
  fbq('track', 'PageView');
  </script>
  <noscript><img height="1" width="1" style="display:none"
  src="https://www.facebook.com/tr?id=<?= htmlspecialchars($marketingSettings['meta_pixel_id']) ?>&ev=PageView&noscript=1"
  /></noscript>
  <!-- End Meta Pixel Code -->
  <?php endif; ?>

  <?php if (!empty($marketingSettings['tiktok_pixel_id'])): ?>
  <!-- TikTok Pixel Code -->
  <script>
  !function (w, d, t) {
    w.TiktokAnalyticsObject=t;var ttq=w[t]=w[t]||[];ttq.methods=["page","track","identify","instances","debug","on","off","once","ready","alias","group","enableCookie","disableCookie","holdConsent","revokeConsent","grantConsent"],ttq.setAndDefer=function(t,e){t[e]=function(){t.push([e].concat(Array.prototype.slice.call(arguments,0)))}};for(var i=0;i<ttq.methods.length;i++)ttq.setAndDefer(ttq,ttq.methods[i]);ttq.instance=function(t){for(var e=ttq._i[t]||[],n=0;n<ttq.methods.length;n++)ttq.setAndDefer(e,ttq.methods[n]);return e},ttq.load=function(e,n){var r="https://analytics.tiktok.com/i18n/pixel/events.js",o=n&&n.partner;ttq._i=ttq._i||{},ttq._i[e]=[],ttq._i[e]._u=r,ttq._t=ttq._t||{},ttq._t[e]=+new Date,ttq._o=ttq._o||{},ttq._o[e]=n||{};var a=document.createElement("script");a.type="text/javascript",a.async=!0,a.src=r+"?sdkid="+e+"&lib="+t;var c=document.getElementsByTagName("script")[0];c.parentNode.insertBefore(a,c)};
    ttq.load('<?= htmlspecialchars($marketingSettings['tiktok_pixel_id']) ?>');
    ttq.page();
  }(window, document, 'ttq');
  </script>
  <!-- End TikTok Pixel Code -->
  <?php endif; ?>

  <!-- OpenGraph / Facebook / Telegram -->
  <meta property="og:site_name" content="ShopCase" />
  <meta property="og:type" content="website" />
  <meta property="og:locale" content="uk_UA" />
  <meta property="og:title" content="<?= htmlspecialchars($pageTitle ?? 'ShopCase') ?>" />
  <meta property="og:description" content="<?= htmlspecialchars($metaDescription ?? '') ?>" />
  <meta property="og:url" content="<?= htmlspecialchars($canonicalUrl ?? 'https://shopcase.top/') ?>" />
  <meta property="og:image" content="<?= htmlspecialchars($ogImage ?? 'https://shopcase.top/design/made-in-ukraine/5293u-4029.jpg') ?>" />

  <!-- Twitter Card -->
  <meta name="twitter:card" content="summary_large_image" />
  <meta name="twitter:title" content="<?= htmlspecialchars($pageTitle ?? 'ShopCase') ?>" />
  <meta name="twitter:description" content="<?= htmlspecialchars($metaDescription ?? '') ?>" />
  <meta name="twitter:image" content="<?= htmlspecialchars($ogImage ?? 'https://shopcase.top/design/made-in-ukraine/5293u-4029.jpg') ?>" />

  <!-- Schema.org JSON-LD -->
  <script type="application/ld+json">
  {
    "@context": "https://schema.org",
    "@graph": [
      {
        "@type": "Organization",
        "@id": "<?= $baseUrl ?? 'https://shopcase.top' ?>/#organization",
        "name": "ShopCase",
        "url": "<?= $baseUrl ?? 'https://shopcase.top' ?>/",
        "description": "Інтернет-магазин авторських чохлів з якісним УФ-друком для 670+ смартфонів"
      },
      {
        "@type": "WebSite",
        "@id": "<?= $baseUrl ?? 'https://shopcase.top' ?>/#website",
        "url": "<?= $baseUrl ?? 'https://shopcase.top' ?>/",
        "name": "ShopCase",
        "potentialAction": {
          "@type": "SearchAction",
          "target": "<?= $baseUrl ?? 'https://shopcase.top' ?>/?q={search_term_string}#catalog",
          "query-input": "required name=search_term_string"
        }
      }
    ]
  }
  </script>

  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Unbounded:wght@400;600;700;900&family=Nunito:wght@400;600;700;800&display=swap" rel="stylesheet" />

  <link rel="stylesheet" href="/css/reset.css?v=3.3" />
  <link rel="stylesheet" href="/css/variables.css?v=3.3" />
  <link rel="stylesheet" href="/css/main.css?v=3.3" />
  <link rel="stylesheet" href="/css/hero.css?v=3.3" />
  <link rel="stylesheet" href="/css/catalog.css?v=3.3" />
  <link rel="stylesheet" href="/css/card.css?v=3.3" />
  <link rel="stylesheet" href="/css/modal.css?v=3.3" />
  <link rel="stylesheet" href="/css/form.css?v=3.3" />
  <link rel="stylesheet" href="/css/loader.css?v=3.3" />
  <link rel="stylesheet" href="/css/pagination.css?v=3.3" />
  <link rel="stylesheet" href="/css/designs.css?v=3.3" />
  <link rel="stylesheet" href="/css/customizer.css?v=3.3" />
</head>
<body>

<header class="header" id="header">
  <div class="container header__inner">
    <a href="/" class="logo">
      <span class="logo__icon">◈</span>
      <span class="logo__text">Shop<span class="logo__accent">Case</span></span>
    </a>

    <nav class="nav" id="nav">
      <a href="#catalog" class="nav__link">Каталог принтів</a>
      <a href="javascript:void(0)" onclick="Constructor.open()" class="nav__link nav__link--highlight">📸 Чохол з фото</a>
      <a href="#how-it-works" class="nav__link">Як це працює</a>
      <a href="#advantages" class="nav__link">Переваги</a>
      <button class="nav__link nav__link--track" onclick="Tracker.openModal()">📦 Відстежити замовлення</button>
    </nav>

    <div class="header__actions">
      <button class="burger" id="burgerBtn" aria-label="Меню">
        <span></span><span></span><span></span>
      </button>
    </div>
  </div>
</header>
