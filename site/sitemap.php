<?php
declare(strict_types=1);

require __DIR__ . '/lib.php';

header('Content-Type: application/xml; charset=utf-8');

$apps = load_data();
$cats = all_categories($apps);
$today = date('Y-m-d');

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

$url = static function (string $loc, string $freq, string $pri, string $lastmod = '') use ($today): void {
    echo "  <url>\n    <loc>" . e($loc) . "</loc>\n";
    echo "    <lastmod>" . ($lastmod !== '' ? e($lastmod) : $today) . "</lastmod>\n";
    echo "    <changefreq>{$freq}</changefreq>\n    <priority>{$pri}</priority>\n  </url>\n";
};

$url('index.php', 'daily', '1.0');
$url('index.php?p=browse', 'weekly', '0.5');

/* 待补充软件详情页（框架页，含官方文档入口） */
$pending = load_pending();
foreach ($pending as $s) {
    $url(sw_url($s['name']), 'monthly', '0.3', $s['updated']);
}
foreach ($cats as $cat => $count) {
    $url(cat_url((string)$cat), 'weekly', '0.8');
}
foreach ($apps as $app) {
    $url(sw_url($app['name']), 'monthly', '0.6', $app['updated']);
}
$url('index.php?p=compare', 'monthly', '0.3');
$url('index.php?p=feedback', 'monthly', '0.3');
$url('index.php?p=about', 'monthly', '0.3');

echo '</urlset>';
