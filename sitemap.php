<?php
declare(strict_types=1);

require_once __DIR__ . '/app/Config.php';
require_once __DIR__ . '/app/Database.php';
require_once __DIR__ . '/app/CatalogService.php';

use App\Config;
use App\CatalogService;

Config::load();

header('Content-Type: application/xml; charset=utf-8');
header('X-Robots-Tag: noindex, follow');

$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'https';
$host = $_SERVER['HTTP_HOST'] ?? 'shopcase.top';
$baseUrl = "{$scheme}://{$host}";

$perPage = 24;
$categories = CatalogService::getCategories();
$allDesigns = App\Database::getDesignsData();
$totalDesigns = count($allDesigns);
$totalMainPages = max(1, (int)ceil($totalDesigns / $perPage));

$now = date('Y-m-d');

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"
        xmlns:image="http://www.google.com/schemas/sitemap-image/1.1">

  <!-- Головна сторінка -->
  <url>
    <loc><?= $baseUrl ?>/</loc>
    <lastmod><?= $now ?></lastmod>
    <changefreq>daily</changefreq>
    <priority>1.0</priority>
  </url>

  <!-- Сторінки головного каталогу -->
  <?php for ($p = 2; $p <= $totalMainPages; $p++): ?>
  <url>
    <loc><?= $baseUrl ?>/page/<?= $p ?></loc>
    <lastmod><?= $now ?></lastmod>
    <changefreq>weekly</changefreq>
    <priority>0.6</priority>
  </url>
  <?php endfor; ?>

  <!-- Категорії та їх пагінація -->
  <?php foreach ($categories as $cat): 
    $catSlug = $cat['slug'];
    $catCount = (int)$cat['count'];
    $catPages = max(1, (int)ceil($catCount / $perPage));
  ?>
  <url>
    <loc><?= $baseUrl ?>/category/<?= htmlspecialchars($catSlug) ?></loc>
    <lastmod><?= $now ?></lastmod>
    <changefreq>weekly</changefreq>
    <priority>0.8</priority>
  </url>
  <?php if ($catPages > 1): ?>
    <?php for ($cp = 2; $cp <= $catPages; $cp++): ?>
    <url>
      <loc><?= $baseUrl ?>/category/<?= htmlspecialchars($catSlug) ?>/page/<?= $cp ?></loc>
      <lastmod><?= $now ?></lastmod>
      <changefreq>weekly</changefreq>
      <priority>0.5</priority>
    </url>
    <?php endfor; ?>
  <?php endif; ?>
  <?php endforeach; ?>

</urlset>
