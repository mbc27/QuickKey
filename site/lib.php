<?php
declare(strict_types=1);

/**
 * 速键库 - 数据层与公共函数
 * 数据源：根目录 清单.json（兼容旧格式 + 新增 version/group/tags/aliases 扩展字段）
 */

const DATA_FILE = __DIR__ . '/../清单.json';
const PENDING_FILE = __DIR__ . '/../待补充清单.json';
const SITE_NAME = '速键库';
const SITE_SLOGAN = '专业软件快捷键知识库';
const PLATFORM_ORDER = ['Windows' => 0, 'Mac' => 1, 'Linux' => 2];

/** 数据文件定位：优先 site 的上一级（开发目录结构），兼容单目录部署（JSON 与 index.php 同级）
 *  新文件名为 ASCII（便于上传/部署），自动兼容旧的中文文件名 */
function data_file(string $name): string
{
    $alias = [
        'softwares.json' => '清单.json',
        'pending.json'   => '待补充清单.json',
    ];
    $names = [$name];
    if (isset($alias[$name])) {
        $names[] = $alias[$name]; // 旧中文名兜底
    }
    foreach ($names as $n) {
        foreach ([__DIR__ . '/../', __DIR__ . '/'] as $dir) {
            if (is_file($dir . $n)) {
                return $dir . $n;
            }
        }
    }
    return __DIR__ . '/' . $name; // 不存在时返回站内新名，供首次写入
}

/** 读取并归一化数据：按软件名聚合，一个软件拥有多个平台/版本条目 */
function load_data(): array
{
    static $apps = null;
    if ($apps !== null) {
        return $apps;
    }
    $raw = json_decode((string)@file_get_contents(data_file('softwares.json')), true);
    if (!is_array($raw)) {
        $raw = [];
    }
    $apps = build_apps(is_array($raw['app_hotkeys'] ?? null) ? $raw['app_hotkeys'] : []);
    ksort($apps);
    return $apps;
}

function build_apps(array $rows): array
{
    $apps = [];
    foreach ($rows as $e) {
        $name = trim((string)($e['app_name'] ?? ''));
        if ($name === '') {
            continue;
        }
        if (!isset($apps[$name])) {
            $apps[$name] = [
                'name'     => $name,
                'aliases'  => is_array($e['aliases'] ?? null) ? $e['aliases'] : [],
                'icon'     => (string)($e['icon'] ?? ''),
                'category' => (string)($e['category'] ?? '未分类'),
                'tags'     => is_array($e['tags'] ?? null) ? $e['tags'] : [],
                'updated'  => (string)($e['updated'] ?? ''),
                'entries'  => [],
            ];
        }
        $apps[$name]['entries'][] = [
            'platform' => (string)($e['platform'] ?? 'Windows'),
            'version'  => (string)($e['version'] ?? '默认'),
            'source'   => (string)($e['source'] ?? '社区整理'),
            'note'     => (string)($e['note'] ?? ''),
            'updated'  => (string)($e['updated'] ?? $apps[$name]['updated']),
            'hotkeys'  => is_array($e['hotkeys'] ?? null) ? $e['hotkeys'] : [],
        ];
    }
    foreach ($apps as &$a) {
        usort($a['entries'], static function ($x, $y) {
            $px = PLATFORM_ORDER[$x['platform']] ?? 9;
            $py = PLATFORM_ORDER[$y['platform']] ?? 9;
            return [$px, $x['version']] <=> [$py, $y['version']];
        });
    }
    unset($a);
    return $apps;
}

/** 读取待补充软件清单（来自 软件清单.md，尚未录入快捷键） */
function load_pending(): array
{
    static $pending = null;
    if ($pending !== null) {
        return $pending;
    }
    $pending = [];
    $raw = json_decode((string)@file_get_contents(data_file('pending.json')), true);
    if (is_array($raw['software'] ?? null)) {
        foreach ($raw['software'] as $s) {
            $name = trim((string)($s['name'] ?? ''));
            if ($name === '') {
                continue;
            }
            $pending[$name] = [
                'name'      => $name,
                'category'  => (string)($s['category'] ?? '待补充'),
                'platforms' => is_array($s['platforms'] ?? null) ? $s['platforms'] : [],
                'doc_url'   => (string)($s['doc_url'] ?? ''),
                'updated'   => (string)($s['updated'] ?? ''),
            ];
        }
    }
    return $pending;
}

function platforms_of(array $app): array
{
    $out = [];
    foreach ($app['entries'] as $en) {
        if (!in_array($en['platform'], $out, true)) {
            $out[] = $en['platform'];
        }
    }
    usort($out, static fn ($a, $b) => (PLATFORM_ORDER[$a] ?? 9) <=> (PLATFORM_ORDER[$b] ?? 9));
    return $out;
}

function versions_of(array $app, string $platform): array
{
    $out = [];
    foreach ($app['entries'] as $en) {
        if ($en['platform'] === $platform && !in_array($en['version'], $out, true)) {
            $out[] = $en['version'];
        }
    }
    return $out;
}

function hotkey_count(array $app): int
{
    $n = 0;
    foreach ($app['entries'] as $en) {
        $n += count($en['hotkeys']);
    }
    return $n;
}

function e(string $s): string
{
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

function cat_url(string $c): string
{
    return 'index.php?p=category&c=' . urlencode($c);
}

function sw_url(string $n): string
{
    return 'index.php?p=software&s=' . urlencode($n);
}

function all_categories(array $apps): array
{
    $cats = [];
    foreach ($apps as $app) {
        $cats[$app['category']] = ($cats[$app['category']] ?? 0) + 1;
    }
    arsort($cats);
    return $cats;
}

function page_header(string $title, string $desc = ''): void
{
    no_cache_headers(); // 配置/二维码更新后前台立即生效
    bump_stats(null); // 公开页面访问统计
    if (session_status() === PHP_SESSION_NONE) {
        session_start(['cookie_httponly' => true, 'cookie_samesite' => 'Lax']);
    }
    /* 后台入口令牌：仅能通过前台「关于」5 连击携带，有效期 60 秒且一次性 */
    $entryToken = bin2hex(random_bytes(16));
    $_SESSION['entry_token'] = $entryToken;
    $_SESSION['entry_time'] = time();

    $desc = $desc !== '' ? $desc : SITE_NAME . '，收录常用与行业软件快捷键，支持平台切换、版本切换、复制导出与快捷键对比。';
    $q = isset($_GET['q']) ? trim((string)$_GET['q']) : '';
    ?>
<!DOCTYPE html>
<html lang="zh-CN" data-theme="light">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= e($title) ?></title>
<meta name="description" content="<?= e($desc) ?>">
<link rel="icon" href="assets/favicon.ico" sizes="any">
<link rel="icon" type="image/svg+xml" href="assets/favicon.svg">
<link rel="apple-touch-icon" href="assets/apple-touch-icon.png">
<link rel="stylesheet" href="assets/style.css?v=7">
<script src="assets/lock.js?v=7"></script>
<script>
(function () {
  var t = localStorage.getItem('qk_theme');
  if (!t) t = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
  document.documentElement.dataset.theme = t;
})();
</script>
</head>
<body>
<div class="top-alert">
  <div class="container">
    <strong>声明：</strong>本站快捷键数据整理自软件官方文档与社区公开资料，仅供学习参考，最终以软件官方为准；如涉及版权问题请联系删除。收藏与对比记录仅保存在你自己的浏览器中。
  </div>
</div>
<?php $cfgTop = site_config(); ?>
<?php if ($cfgTop['notice'] !== ''): ?>
<div class="notice-bar">
  <div class="container"><span class="notice-label">公告</span><?= e($cfgTop['notice']) ?></div>
</div>
<?php endif; ?>
<header class="site-header">
  <div class="container head-row">
    <a class="logo" href="index.php"><span class="logo-mark">⌨</span> <?= e(SITE_NAME) ?></a>
    <form class="search-form head-search" action="index.php" method="get">
      <input type="hidden" name="p" value="search">
      <input type="search" name="q" value="<?= e($q) ?>" placeholder="搜索软件 / 功能 / 标签…" aria-label="搜索">
      <button type="submit">搜索</button>
    </form>
    <nav class="top-nav">
      <a href="index.php?p=browse">目录</a>
      <a href="index.php?p=compare">对比</a>
      <a href="index.php?p=favorites">收藏</a>
      <a href="index.php?p=feedback">反馈投稿</a>
      <a href="index.php?p=about" id="navAbout" data-entry="<?= e($entryToken) ?>">关于</a>
      <button id="themeToggle" class="icon-btn" type="button" aria-label="切换暗色模式">🌓</button>
    </nav>
  </div>
</header>
<main class="container">
    <?php
}

function page_footer(): void
{
    ?>
</main>
<footer class="site-footer">
  <div class="container">
    <p>快捷键仅供参考，以软件官方文档为准，不保证版本完全一致；不确定内容已标注【待验证】。</p>
    <p>收藏数据保存在浏览器本地（localStorage），清理缓存或更换设备会丢失。</p>
    <?php $st = get_stats(); ?>
    <p>今日访客 <?= (int)$st['today_uv'] ?> 人 · 累计访客 <?= (int)$st['total_uv'] ?> 人 · 今日浏览 <?= (int)$st['today'] ?> 次</p>
    <p>&copy; <?= date('Y') ?> <?= e(SITE_NAME) ?> · <a href="index.php?p=about">关于</a> · <a href="index.php?p=feedback">纠错与投稿</a> · <a href="sitemap.php">Sitemap</a></p>
  </div>
</footer>
<div id="toast" class="toast" hidden></div>
<script src="assets/app.js?v=9"></script>
</body>
</html>
    <?php
}

function page_404(): void
{
    http_response_code(404);
    page_header('页面不存在 - ' . SITE_NAME);
    echo '<section class="empty-state"><h1>404</h1><p>页面不存在或软件尚未收录。</p>';
    echo '<p><a class="btn" href="index.php">返回首页</a> <a class="btn ghost" href="index.php?p=feedback">提交收录建议</a></p></section>';
    page_footer();
}

function app_card(array $app): void
{
    $tags = implode('', array_map(static fn ($t) => '<span class="tag">' . e((string)$t) . '</span>', array_slice($app['tags'], 0, 3)));
    $plats = implode(' / ', platforms_of($app));
    $versions = [];
    foreach ($app['entries'] as $en) {
        if (!in_array($en['version'], $versions, true)) {
            $versions[] = $en['version'];
        }
    }
    ?>
<a class="card app-card" href="<?= e(sw_url($app['name'])) ?>" data-name="<?= e($app['name']) ?>">
  <div class="app-card-head">
    <span class="app-icon" aria-hidden="true"><?= mb_substr($app['name'], 0, 1) ?></span>
    <div class="app-card-title">
      <h3><?= e($app['name']) ?></h3>
      <p class="muted app-sub"><?= e($plats) ?> · <?= hotkey_count($app) ?> 条快捷键</p>
    </div>
    <span class="app-count"><?= hotkey_count($app) ?></span>
  </div>
  <div class="app-card-meta">
    <span class="tag cat"><?= e($app['category']) ?></span>
    <?= $tags ?>
  </div>
  <div class="app-card-foot muted">
    <span>版本：<?= e(implode('、', array_slice($versions, 0, 3))) ?><?= count($versions) > 3 ? ' 等' : '' ?></span>
    <button class="btn ghost sm fav-btn" type="button" data-fav="<?= e($app['name']) ?>">☆ 收藏</button>
  </div>
</a>
    <?php
}

/** 待补充软件卡片 */
function pending_card(array $s): void
{
    ?>
<a class="card app-card pending" href="<?= e(sw_url($s['name'])) ?>">
  <div class="app-card-head">
    <span class="app-icon ghost-icon" aria-hidden="true"><?= mb_substr($s['name'], 0, 1) ?></span>
    <div class="app-card-title">
      <h3><?= e($s['name']) ?></h3>
      <p class="muted app-sub"><?= $s['platforms'] ? e(implode(' / ', $s['platforms'])) : '平台待确认' ?> · 待补充</p>
    </div>
  </div>
  <div class="app-card-meta">
    <span class="tag pending-tag">待补充</span>
    <span class="tag"><?= e(mb_substr($s['category'], 0, 14)) ?></span>
  </div>
  <div class="app-card-foot muted">
    <span><?= $s['doc_url'] !== '' ? '已找到官方文档' : '官方文档待查找' ?></span>
    <span class="btn ghost sm">查看</span>
  </div>
</a>
    <?php
}

/** 待补充详情页框架：基础信息 + 官方文档链接 + 投稿提示 */
function pending_detail(array $s): void
{
    page_header("{$s['name']}快捷键（待补充） - " . SITE_NAME,
        "{$s['name']}的快捷键表待补充，页面提供软件基础信息与官方文档入口，欢迎投稿完整快捷键表。");
    $bing = 'https://www.bing.com/search?q=' . urlencode($s['name'] . ' 快捷键 官方');
    $baidu = 'https://www.baidu.com/s?wd=' . urlencode($s['name'] . ' 快捷键 官方文档');
    ?>
<nav class="breadcrumb">
  <a href="index.php">首页</a> / <a href="index.php?p=browse">全部软件</a> / <span><?= e($s['name']) ?></span>
</nav>

<section class="sw-info card">
  <span class="app-icon ghost-icon lg" aria-hidden="true"><?= mb_substr($s['name'], 0, 1) ?></span>
  <div class="sw-info-text">
    <h1><?= e($s['name']) ?> 快捷键 <span class="tag pending-tag">待补充</span></h1>
    <p class="muted">
      分类：<?= e($s['category']) ?>
      · 平台：<?= $s['platforms'] ? e(implode(' / ', $s['platforms'])) : '待确认' ?>
      · 状态：完整快捷键表待补充
    </p>
  </div>
  <div class="sw-actions">
    <?php if ($s['doc_url'] !== ''): ?>
    <a class="btn sm" href="<?= e($s['doc_url']) ?>" target="_blank" rel="noopener nofollow">官方文档/官网 ↗</a>
    <?php endif; ?>
    <a class="btn sm" href="<?= e('index.php?p=feedback&s=' . urlencode($s['name'])) ?>">投稿该软件快捷键</a>
  </div>
</section>

<section class="card pending-body">
  <h2>完整快捷键表待补充，欢迎投稿！</h2>
  <p class="muted">该软件尚未录入快捷键数据。我们正按「热门优先、行业刚需其次」的策略陆续补充，你也可以帮助我们加快进度：</p>
  <ul>
    <li>打开上方官方文档/官网，查找 Keyboard shortcuts / 快捷键 页面；</li>
    <?php if ($s['doc_url'] === ''): ?>
    <li>或通过搜索引擎查找： <a href="<?= e($bing) ?>" target="_blank" rel="noopener nofollow">必应搜索 ↗</a> · <a href="<?= e($baidu) ?>" target="_blank" rel="noopener nofollow">百度搜索 ↗</a></li>
    <?php endif; ?>
    <li>整理成「按键 - 功能」列表，通过 <a href="<?= e('index.php?p=feedback&s=' . urlencode($s['name'])) ?>">反馈投稿页</a> 提交，审核通过后即上架。</li>
  </ul>
  <div class="sw-actions">
    <?php if ($s['doc_url'] === ''): ?>
    <a class="btn sm ghost" href="<?= e($bing) ?>" target="_blank" rel="noopener nofollow">必应搜索</a>
    <a class="btn sm ghost" href="<?= e($baidu) ?>" target="_blank" rel="noopener nofollow">百度搜索</a>
    <?php endif; ?>
    <a class="btn sm" href="<?= e('index.php?p=feedback&s=' . urlencode($s['name'])) ?>">立即投稿</a>
  </div>
</section>
    <?php
    page_footer();
}

/** 搜索：优先软件名称，其次别名、标签、分类，最后功能描述 */
function search_apps(array $apps, string $q): array
{
    $q = trim($q);
    if ($q === '') {
        return [];
    }
    $res = [];
    foreach ($apps as $app) {
        $score = 0;
        if (stripos($app['name'], $q) !== false) {
            $score += 100;
        }
        foreach ($app['aliases'] as $al) {
            if (stripos((string)$al, $q) !== false) {
                $score += 60;
            }
        }
        if (stripos($app['category'], $q) !== false) {
            $score += 20;
        }
        foreach ($app['tags'] as $t) {
            if (stripos((string)$t, $q) !== false) {
                $score += 20;
            }
        }
        foreach ($app['entries'] as $en) {
            foreach ($en['hotkeys'] as $hk) {
                if (stripos((string)($hk['desc'] ?? ''), $q) !== false) {
                    $score += 5;
                    break 2;
                }
            }
        }
        if ($score > 0) {
            $res[] = ['score' => $score, 'app' => $app];
        }
    }
    usort($res, static fn ($a, $b) => $b['score'] <=> $a['score']);
    return array_column($res, 'app');
}

/* ================= 运行数据：访问统计 / 站点配置 / 用户投稿 ================= */

const DATA_DIR = __DIR__ . '/data';

function read_json_file(string $file, array $default): array
{
    $raw = @file_get_contents($file);
    $d = json_decode((string)$raw, true);
    return is_array($d) ? $d : $default;
}

/** 原子写入 + 可选备份（保留最近20份），避免并发写坏数据 */
function write_json_file(string $file, array $data, bool $backup = false): bool
{
    if ($backup && is_file($file)) {
        $bdir = DATA_DIR . '/backups';
        if (!is_dir($bdir)) {
            @mkdir($bdir, 0775, true);
        }
        @copy($file, $bdir . '/' . basename($file, '.json') . '-' . date('Ymd-His') . '.json');
        $backups = glob($bdir . '/*.json') ?: [];
        if (count($backups) > 20) {
            usort($backups, static fn ($a, $b) => strcmp($a, $b));
            foreach (array_slice($backups, 0, count($backups) - 20) as $old) {
                @unlink($old);
            }
        }
    }
    $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($json === false) {
        return false;
    }
    $tmp = $file . '.tmp';
    if (file_put_contents($tmp, $json, LOCK_EX) === false) {
        return false;
    }
    return rename($tmp, $file);
}

/** 访问统计（双口径）：
 *  PV 浏览量：每次公开页面请求 +1（page_header 调用时统计）
 *  UV 访客数：同一会话 10 分钟内只算 1 次访问
 *  软件热度：同一会话对同一软件 10 分钟内只 +1（防反复刷新详情页刷排行）
 *  访客日志：同 IP 60 秒内超过 15 次不再记录（防刷），最多保留 500 条
 */
function bump_stats(?string $software = null): void
{
    $file = DATA_DIR . '/stats.json';
    $fp = @fopen($file, 'c+');
    if (!$fp) {
        return;
    }
    @flock($fp, LOCK_EX);
    $data = json_decode((string)stream_get_contents($fp), true);
    if (!is_array($data)) {
        $data = ['days' => [], 'total' => 0, 'days_uv' => [], 'total_uv' => 0, 'software' => [], 'log' => []];
    }
    $today = date('Y-m-d');
    $now = time();

    if (session_status() === PHP_SESSION_NONE) {
        session_start(['cookie_httponly' => true, 'cookie_samesite' => 'Lax']);
    }

    if ($software === null) {
        /* PV：页面每次请求 +1 */
        $data['days'][$today] = ($data['days'][$today] ?? 0) + 1;
        $data['total'] = ($data['total'] ?? 0) + 1;
        /* UV：10 分钟会话窗口去重 */
        if (empty($_SESSION['uv_time']) || ($now - (int)$_SESSION['uv_time']) > 600) {
            $_SESSION['uv_time'] = $now;
            $data['days_uv'][$today] = ($data['days_uv'][$today] ?? 0) + 1;
            $data['total_uv'] = ($data['total_uv'] ?? 0) + 1;
        }
    } elseif ($software !== '') {
        /* 软件热度：同一会话对同一软件 10 分钟内只 +1 */
        $seen = is_array($_SESSION['sw_seen'] ?? null) ? $_SESSION['sw_seen'] : [];
        if (($now - (int)($seen[$software] ?? 0)) > 600) {
            $seen[$software] = $now;
            if (count($seen) > 50) { // 仅保留最近 50 个，防 session 膨胀
                asort($seen);
                $seen = array_slice($seen, -50, null, true);
            }
            $_SESSION['sw_seen'] = $seen;
            $data['software'][$software] = ($data['software'][$software] ?? 0) + 1;
        }
    }

    /* 访客日志：仅页面级统计时写一条（详情页的软件名从 URL 取），防刷限流 */
    $data['log'] = is_array($data['log'] ?? null) ? $data['log'] : [];
    if ($software === null && rate_limit('visit_log', 15, 60)) {
        $page = (string)($_GET['p'] ?? 'home');
        if ($page === 'software' && !empty($_GET['s'])) {
            $page = '详情：' . clean_text((string)$_GET['s'], 60);
        }
        array_unshift($data['log'], [
            'ip'   => client_ip(),
            'time' => date('Y-m-d H:i:s'),
            'page' => $page,
        ]);
    }
    if (count($data['log']) > 500) {
        $data['log'] = array_slice($data['log'], 0, 500);
    }
    if (count($data['days']) > 400) {
        ksort($data['days']);
        $data['days'] = array_slice($data['days'], -400, null, true);
    }
    if (count($data['days_uv'] ?? []) > 400) {
        ksort($data['days_uv']);
        $data['days_uv'] = array_slice($data['days_uv'], -400, null, true);
    }
    ftruncate($fp, 0);
    rewind($fp);
    fwrite($fp, json_encode($data, JSON_UNESCAPED_UNICODE));
    flock($fp, LOCK_UN);
    fclose($fp);
}

function get_stats(): array
{
    $d = read_json_file(DATA_DIR . '/stats.json', ['days' => [], 'total' => 0, 'days_uv' => [], 'total_uv' => 0, 'software' => []]);
    $d['days'] = is_array($d['days'] ?? null) ? $d['days'] : [];
    $d['days_uv'] = is_array($d['days_uv'] ?? null) ? $d['days_uv'] : [];
    $d['software'] = is_array($d['software'] ?? null) ? $d['software'] : [];
    $d['today'] = (int)($d['days'][date('Y-m-d')] ?? 0);      // 今日浏览量 PV
    $d['today_uv'] = (int)($d['days_uv'][date('Y-m-d')] ?? 0); // 今日访客 UV
    $d['total_uv'] = (int)($d['total_uv'] ?? 0);
    return $d;
}

/** 热门软件排行（按详情页访问量） */
function top_software(int $n): array
{
    $sw = get_stats()['software'];
    arsort($sw);
    return array_slice($sw, 0, $n, true);
}

/** 站点配置：联系方式 / 二维码 / 公告 */
function site_config(): array
{
    $c = read_json_file(DATA_DIR . '/site_config.json', []);
    foreach (['qq', 'wechat', 'qr_wechat', 'qr_qq', 'notice'] as $k) {
        $c[$k] = (string)($c[$k] ?? '');
    }
    /* 兼容旧版单二维码：qr 视为微信二维码，并迁移到可公开访问的 assets/qr/ */
    $legacy = (string)($c['qr'] ?? '');
    if ($legacy !== '') {
        if ($c['qr_wechat'] === '') {
            $src = __DIR__ . '/' . preg_replace('/\?.*$/', '', $legacy);
            if (is_file($src)) {
                $ext = strtolower((string)pathinfo($src, PATHINFO_EXTENSION)) ?: 'png';
                $dir = __DIR__ . '/assets/qr';
                @mkdir($dir, 0775, true);
                if (@copy($src, $dir . '/wechat.' . $ext)) {
                    $c['qr_wechat'] = 'assets/qr/wechat.' . $ext;
                }
            }
        }
        unset($c['qr']); // 清理旧键，避免重复迁移
        write_json_file(DATA_DIR . '/site_config.json', $c);
    }
    return $c;
}

/** 客户端 IP（兼容反向代理） */
function client_ip(): string
{
    foreach (['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'REMOTE_ADDR'] as $k) {
        if (!empty($_SERVER[$k])) {
            $ip = trim(explode(',', (string)$_SERVER[$k])[0]);
            if (filter_var($ip, FILTER_VALIDATE_IP)) {
                return $ip;
            }
        }
    }
    return '0.0.0.0';
}

/** 简易 IP 限流：$window 秒内最多 $limit 次，超限返回 false */
function rate_limit(string $bucket, int $limit, int $window): bool
{
    $file = DATA_DIR . '/rate_limit.json';
    @mkdir(DATA_DIR, 0775, true);
    $fp = @fopen($file, 'c+');
    if (!$fp) {
        return true;
    }
    @flock($fp, LOCK_EX);
    $data = json_decode((string)stream_get_contents($fp), true);
    if (!is_array($data)) {
        $data = [];
    }
    $now = time();
    $key = $bucket . '|' . client_ip();
    $hits = is_array($data[$key] ?? null) ? $data[$key] : [];
    $hits = array_values(array_filter($hits, static fn ($t) => (int)$t > $now - $window));
    $ok = count($hits) < $limit;
    if ($ok) {
        $hits[] = $now;
    }
    foreach ($data as $k => $v) {
        if (!is_array($v)) {
            unset($data[$k]);
            continue;
        }
        $v = array_values(array_filter($v, static fn ($t) => (int)$t > $now - 3600));
        if ($v) {
            $data[$k] = $v;
        } else {
            unset($data[$k]);
        }
    }
    $data[$key] = $hits;
    ftruncate($fp, 0);
    rewind($fp);
    fwrite($fp, (string)json_encode($data));
    fflush($fp);
    @flock($fp, LOCK_UN);
    fclose($fp);
    return $ok;
}

/** 输出缓冲：去注释 + 压缩空白；关键页面（$GLOBALS['qk_deep_obfuscate']）额外把文本转数字实体 */
function qk_output_buffer(string $html): string
{
    if (!empty($GLOBALS['qk_plain_output'])) {
        return $html;
    }
    $html = (string)preg_replace('/<!--(?!\[if)[\s\S]*?-->/', '', $html);
    if (!empty($GLOBALS['qk_deep_obfuscate'])) {
        $html = qk_entify_text($html);
    }
    return (string)preg_replace('/\n\s+/', "\n", $html);
}

/** 把标签之间的文本转成 HTML 数字实体，源码不可直读，浏览器正常显示 */
function qk_entify_text(string $html): string
{
    $chunks = preg_split('/(<(?:script|style|pre|textarea)\b[\s\S]*?<\/(?:script|style|pre|textarea)>)/i', $html, -1, PREG_SPLIT_DELIM_CAPTURE);
    if (!is_array($chunks)) {
        return $html;
    }
    foreach ($chunks as $i => $chunk) {
        if ($i % 2 === 1) {
            continue; // script/style/pre/textarea 原样保留
        }
        $chunks[$i] = (string)preg_replace_callback('/>([^<>]{2,})</u', static function (array $m): string {
            $text = $m[1];
            if (trim($text) === '') {
                return '>' . $text . '<';
            }
            /* 已有 HTML 实体（&copy; &#169; &#xA9;）原样保留，避免被二次转义成可见文本 */
            $parts = preg_split('/(&[a-zA-Z][a-zA-Z0-9]{1,31};|&#\d{1,7};|&#x[0-9a-fA-F]{1,6};)/', $text, -1, PREG_SPLIT_DELIM_CAPTURE);
            if (!is_array($parts)) {
                return '>' . $text . '<';
            }
            $out = '';
            foreach ($parts as $k => $part) {
                if ($k % 2 === 1) {
                    $out .= $part;
                    continue;
                }
                $chars = preg_split('//u', $part, -1, PREG_SPLIT_NO_EMPTY) ?: [];
                foreach ($chars as $ch) {
                    $out .= ctype_space($ch) ? $ch : '&#x' . strtoupper(dechex(mb_ord($ch, 'UTF-8'))) . ';';
                }
            }
            return '>' . $out . '<';
        }, $chunk);
    }
    return implode('', $chunks);
}

/** 已收录快捷键总条数（所有软件所有平台/版本记录求和） */
function hotkey_total(array $apps): int
{
    $n = 0;
    foreach ($apps as $app) {
        foreach (($app['entries'] ?? []) as $en) {
            $n += count($en['hotkeys'] ?? []);
        }
    }
    return $n;
}

/** 二维码地址：自动按文件修改时间加版本号，替换图片后浏览器立即刷新 */
function qr_src(string $path): string
{
    if ($path === '') {
        return '';
    }
    $rel = (string)preg_replace('/\?.*$/', '', $path);
    $file = __DIR__ . '/' . ltrim($rel, '/');
    $v = is_file($file) ? (string)filemtime($file) : '0';
    return $rel . '?v=' . $v;
}

/** 简单页面缓存控制：保证设置改动立刻生效 */
function no_cache_headers(): void
{
    if (!headers_sent()) {
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');
    }
}

/** 用户投稿/纠错 */
function add_submission(array $item): bool
{
    $file = DATA_DIR . '/submissions.json';
    $fp = @fopen($file, 'c+');
    if (!$fp) {
        return false;
    }
    @flock($fp, LOCK_EX);
    $list = json_decode((string)stream_get_contents($fp), true);
    if (!is_array($list)) {
        $list = [];
    }
    array_unshift($list, $item);
    if (count($list) > 500) {
        $list = array_slice($list, 0, 500);
    }
    ftruncate($fp, 0);
    rewind($fp);
    fwrite($fp, json_encode($list, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    flock($fp, LOCK_UN);
    fclose($fp);
    return true;
}

function get_submissions(): array
{
    return read_json_file(DATA_DIR . '/submissions.json', []);
}

function save_submissions(array $list): bool
{
    return write_json_file(DATA_DIR . '/submissions.json', $list);
}

function clean_text(string $s, int $max = 500): string
{
    return mb_substr(trim(strip_tags($s)), 0, $max);
}
