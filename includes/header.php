<!DOCTYPE html>
<html lang="uk">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title><?= htmlspecialchars($pageTitle ?? 'ShopCase — Чохли з душею') ?></title>
  <meta name="description" content="Яскраві чохли для телефонів. Друк на замовлення, доставка Новою Поштою по всій Україні." />

  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Unbounded:wght@400;700;900&family=Nunito:wght@400;600;700&display=swap" rel="stylesheet" />

  <link rel="stylesheet" href="/css/reset.css" />
  <link rel="stylesheet" href="/css/variables.css" />
  <link rel="stylesheet" href="/css/main.css" />
  <link rel="stylesheet" href="/css/hero.css" />
  <link rel="stylesheet" href="/css/catalog.css" />
  <link rel="stylesheet" href="/css/card.css" />
  <link rel="stylesheet" href="/css/modal.css" />
  <link rel="stylesheet" href="/css/form.css" />
  <link rel="stylesheet" href="/css/loader.css" />
  <link rel="stylesheet" href="/css/pagination.css" />
  <link rel="stylesheet" href="/css/designs.css" />
</head>
<body>

<header class="header" id="header">
  <div class="container header__inner">
    <a href="/" class="logo">
      <span class="logo__icon">◈</span>
      <span class="logo__text">Shop<span class="logo__accent">Case</span></span>
    </a>

    <nav class="nav" id="nav">
      <a href="/#catalog" class="nav__link">Каталог</a>
      <a href="/#how-it-works" class="nav__link">Як це працює</a>
      <a href="/#advantages" class="nav__link">Переваги</a>
    </nav>

    <button class="btn btn--ghost header__cart" id="cartBtn" aria-label="Кошик">
      <span class="cart-icon">🛍</span>
      <span class="cart-count" id="cartCount">0</span>
    </button>

    <button class="burger" id="burgerBtn" aria-label="Меню">
      <span></span><span></span><span></span>
    </button>
  </div>
</header>
