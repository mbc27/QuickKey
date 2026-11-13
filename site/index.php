<?php
declare(strict_types=1);

require __DIR__ . '/lib.php';

/* 输出混淆：默认去注释 + 压缩空白；首页/详情页额外做文本实体化 */
ob_start('qk_output_buffer');

$apps = load_data();
$page = (string)($_GET['p'] ?? 'home');

switch ($page) {
    case 'home':
        page_home($apps);
        break;
    case 'category':
        page_category($apps);
        break;
    case 'software':
        page_software($apps);
        break;
    case 'browse':
        page_browse($apps);
        break;
    case 'search':
        page_search($apps);
        break;
    case 'favorites':
        page_favorites();
        break;
    case 'compare':
        page_compare();
        break;
    case 'feedback':
        page_feedback();
        break;
    case 'about':
        page_about();
        break;
    case 'api':
        page_api($apps);
        break;
    default:
        page_404();
}

/* ---------------- 首页 ---------------- */
function page_home(array $apps): void
{
    $GLOBALS['qk_deep_obfuscate'] = true; // 首页文本实体化
    page_header(SITE_NAME . ' | 专业软件快捷键知识库，工程师设计师速查手册');
    $cats = all_categories($apps);
    $pendingCount = count(load_pending());
    $recent = array_values($apps);
    usort($recent, static fn ($a, $b) => strcmp($b['updated'], $a['updated']));
    $recent = array_slice($recent, 0, 12);
    $top = top_software(8);
    $st = get_stats();
    ?>
<h1 class="sr-only"><?= e(SITE_NAME) ?> - 软件快捷键速查</h1>
<div class="intro-bar">
  <span class="pill">已收录 <b><?= count($apps) ?></b> 款</span>
  <span class="pill">快捷键 <b><?= hotkey_total($apps) ?></b> 条</span>
  <span class="pill">待补充 <b><?= $pendingCount ?></b> 款</span>
  <span class="pill">今日访客 <b id="statUv"><?= (int)$st['today_uv'] ?></b> 人</span>
  <span class="pill">累计访客 <b id="statTotalUv"><?= (int)$st['total_uv'] ?></b> 人</span>
  <span class="pill">今日浏览 <b id="statToday"><?= (int)$st['today'] ?></b> 次</span>
  <a class="intro-link" href="index.php?p=browse">浏览全部目录 →</a>
</div>

<section class="section">
  <h2 class="sec-title">热门排行 <small>实时更新 · 按访问量排序</small></h2>
  <div class="top-row" id="topRow">
    <?php if ($top): $i = 0; foreach ($top as $name => $visits): $i++; $app = $apps[$name] ?? null; ?>
    <a class="top-card" href="<?= e(sw_url((string)$name)) ?>">
      <span class="top-rank r<?= $i <= 3 ? $i : 'n' ?>"><?= $i ?></span>
      <span class="top-name" title="<?= e((string)$name) ?>"><?= e((string)$name) ?></span>
      <span class="top-meta"><?= $app ? e($app['category']) : '热门' ?></span>
      <span class="top-visits"><?= (int)$visits ?> 次访问</span>
    </a>
    <?php endforeach; ?>
    <?php else: ?>
    <p class="muted" id="topEmpty">暂无访问数据，浏览软件详情页后会自动生成排行。</p>
    <?php endif; ?>
  </div>
</section>

<section class="section">
  <h2 class="sec-title">分类导航</h2>
  <div class="cat-cloud">
    <?php foreach ($cats as $cat => $count): ?>
    <a class="cat-chip" href="<?= e(cat_url((string)$cat)) ?>"><?= e((string)$cat) ?><b><?= (int)$count ?></b></a>
    <?php endforeach; ?>
  </div>
</section>

<section class="section">
  <h2 class="sec-title">最近更新</h2>
  <div class="card-grid">
    <?php foreach ($recent as $app) app_card($app); ?>
  </div>
</section>
    <?php
    page_footer();
}

/* ---------------- 全部软件（含待补充） ---------------- */
function page_browse(array $apps): void
{
    $pending = load_pending();
    page_header('全部软件目录 - ' . SITE_NAME, '全站收录的全部软件目录：已有快捷键数据与待补充软件，点击进入详情页。');
    ?>
<h1>全部软件目录 <small class="muted">已有数据 <?= count($apps) ?> 款 · 待补充 <?= count($pending) ?> 款</small></h1>
<p class="muted">已收录软件排在前面；待补充软件详情页已建好框架，含官方文档入口，欢迎投稿。</p>

<section class="section">
  <h2>已收录软件 <small class="muted">点击查看完整快捷键</small></h2>
  <div class="card-grid">
    <?php foreach ($apps as $app) app_card($app); ?>
  </div>
</section>
    <?php
    $groups = [];
    foreach ($pending as $s) {
        $groups[$s['category']][] = $s;
    }
    ksort($groups);
    foreach ($groups as $cat => $list):
        usort($list, static fn ($a, $b) => strcasecmp($a['name'], $b['name']));
    ?>
<section class="section">
  <h2><?= e((string)$cat) ?> <small class="muted"><?= count($list) ?> 款待补充</small></h2>
  <div class="card-grid">
    <?php foreach ($list as $s) pending_card($s); ?>
  </div>
</section>
    <?php
    endforeach;
    page_footer();
}

/* ---------------- 分类页 ---------------- */
function page_category(array $apps): void
{
    $cat = trim((string)($_GET['c'] ?? ''));
    $plat = (string)($_GET['plat'] ?? '');
    $sort = (string)($_GET['sort'] ?? 'updated');
    $cats = all_categories($apps);
    if ($cat === '' || !isset($cats[$cat])) {
        page_404();
        return;
    }
    $list = array_values(array_filter($apps, static fn ($a) => $a['category'] === $cat));
    if ($plat !== '') {
        $list = array_values(array_filter($list, static fn ($a) => in_array($plat, platforms_of($a), true)));
    }
    usort($list, $sort === 'name'
        ? static fn ($a, $b) => strcasecmp($a['name'], $b['name'])
        : static fn ($a, $b) => strcmp($b['updated'], $a['updated']));
    page_header("{$cat}类软件快捷键大全 - " . SITE_NAME, "{$cat}分类下全部软件快捷键速查，支持平台筛选与排序。");
    ?>
<nav class="breadcrumb"><a href="index.php">首页</a> / <span><?= e($cat) ?></span></nav>
<h1><?= e($cat) ?> <small class="muted">共 <?= count($list) ?> 款软件</small></h1>

<div class="filter-bar">
  <div class="filter-group">
    <span class="muted">平台：</span>
    <a class="chip <?= $plat === '' ? 'active' : '' ?>" href="<?= e(cat_url($cat)) ?>">全部</a>
    <?php foreach (['Windows', 'Mac', 'Linux'] as $p): ?>
    <a class="chip <?= $plat === $p ? 'active' : '' ?>" href="<?= e(cat_url($cat) . '&plat=' . $p) ?>"><?= $p ?></a>
    <?php endforeach; ?>
  </div>
  <div class="filter-group">
    <span class="muted">排序：</span>
    <a class="chip <?= $sort === 'updated' ? 'active' : '' ?>" href="<?= e(cat_url($cat) . ($plat !== '' ? '&plat=' . $plat : '') . '&sort=updated') ?>">按更新时间</a>
    <a class="chip <?= $sort === 'name' ? 'active' : '' ?>" href="<?= e(cat_url($cat) . ($plat !== '' ? '&plat=' . $plat : '') . '&sort=name') ?>">名称 A-Z</a>
  </div>
</div>

<div class="card-grid">
  <?php foreach ($list as $app) app_card($app); ?>
</div>
    <?php
    page_footer();
}

/* ---------------- 软件详情页 ---------------- */
function page_software(array $apps): void
{
    $name = trim((string)($_GET['s'] ?? ''));
    $pending = load_pending();
    if (!isset($apps[$name])) {
        if (isset($pending[$name])) {
            pending_detail($pending[$name]);
        } else {
            page_404();
        }
        return;
    }
    $app = $apps[$name];
    $GLOBALS['qk_deep_obfuscate'] = true; // 详情页文本实体化
    bump_stats($name); // 软件详情页访问，用于热门排行
    $payload = [
        'name' => $app['name'],
        'aliases' => $app['aliases'],
        'category' => $app['category'],
        'tags' => $app['tags'],
        'icon' => $app['icon'],
        'platforms' => platforms_of($app),
        'entries' => $app['entries'],
    ];
    $related = array_filter($apps, static fn ($a) => $a['category'] === $app['category'] && $a['name'] !== $app['name']);
    $related = array_slice($related, 0, 8);
    page_header("{$name}快捷键大全 | Windows Mac 默认快捷键 - " . SITE_NAME,
        "{$name}全套快捷键，按功能分组，支持Windows/Mac平台切换、多版本切换、复制导出与对比。");
    ?>
<nav class="breadcrumb">
  <a href="index.php">首页</a> / <a href="<?= e(cat_url($app['category'])) ?>"><?= e($app['category']) ?></a> / <span><?= e($name) ?></span>
</nav>

<section id="sw-detail" class="sw-layout">
  <div class="sw-main">
    <div class="sw-info card">
      <span class="app-icon lg" aria-hidden="true"><?= mb_substr($name, 0, 1) ?></span>
      <div class="sw-info-text">
        <h1><?= e($name) ?> 快捷键</h1>
        <p class="muted">
          <?= e($app['category']) ?>
          <?php if ($app['aliases']): ?> · 别名：<?= e(implode('、', $app['aliases'])) ?><?php endif; ?>
          · 共 <?= hotkey_count($app) ?> 条
        </p>
        <div class="tag-row">
          <?php foreach ($app['tags'] as $t): ?><span class="tag"><?= e((string)$t) ?></span><?php endforeach; ?>
        </div>
      </div>
      <div class="sw-actions">
        <button class="btn sm" id="btnFav" type="button">☆ 加入收藏</button>
        <button class="btn sm" id="btnCompare" type="button">加入对比</button>
        <button class="btn sm ghost" id="btnCopyAll" type="button">复制全部</button>
        <button class="btn sm ghost" id="btnExportMd" type="button">导出Markdown</button>
        <button class="btn sm ghost" id="btnExportPng" type="button">导出图片</button>
      </div>
    </div>
    <div id="hk-panel"><!-- 平台Tab / 版本切换 / 分组表格 由 app.js 渲染 --></div>
  </div>
  <aside class="sw-side">
    <div class="card">
      <h3>相关软件</h3>
      <ul class="rel-list">
        <?php foreach ($related as $r): ?>
        <li><a href="<?= e(sw_url($r['name'])) ?>"><?= e($r['name']) ?> <span class="muted sm-text"><?= hotkey_count($r) ?> 条</span></a></li>
        <?php endforeach; ?>
        <?php if (!$related): ?><li class="muted">暂无同分类其他软件</li><?php endif; ?>
      </ul>
    </div>
    <div class="card notice">
      <h3>说明</h3>
      <p class="muted">快捷键以软件官方文档为准，版本间可能存在差异；不确定内容标注【待验证】。</p>
    </div>
  </aside>
</section>

<script id="sw-data" type="application/json"><?= json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?></script>
    <?php
    page_footer();
}

/* ---------------- 搜索页 ---------------- */
function page_search(array $apps): void
{
    $q = trim((string)($_GET['q'] ?? ''));
    $results = search_apps($apps, $q);
    $pendingHits = [];
    if ($q !== '') {
        foreach (load_pending() as $s) {
            if (stripos($s['name'], $q) !== false || stripos($s['category'], $q) !== false) {
                $pendingHits[] = $s;
            }
        }
        $pendingHits = array_slice($pendingHits, 0, 24);
    }
    $total = count($results) + count($pendingHits);
    page_header("“{$q}”的搜索结果 - " . SITE_NAME);
    ?>
<nav class="breadcrumb"><a href="index.php">首页</a> / <span>搜索：<?= e($q) ?></span></nav>
<h1>搜索结果 <small class="muted">关键词：<?= e($q) ?>，共 <?= $total ?> 款软件</small></h1>
<?php if ($results): ?>
<div class="card-grid">
  <?php foreach ($results as $app) app_card($app); ?>
</div>
<?php endif; ?>
<?php if ($pendingHits): ?>
<section class="section">
  <h2>待补充软件 <small class="muted">以下软件详情页已建好框架，欢迎投稿</small></h2>
  <div class="card-grid">
    <?php foreach ($pendingHits as $s) pending_card($s); ?>
  </div>
</section>
<?php endif; ?>
<?php if (!$results && !$pendingHits): ?>
<section class="empty-state">
  <h2>暂未收录该软件</h2>
  <p class="muted">没有找到与“<?= e($q) ?>”相关的内容，欢迎提交收录建议。</p>
  <a class="btn" href="index.php?p=feedback">前往投稿</a>
</section>
<?php endif; ?>
    <?php
    page_footer();
}

/* ---------------- 收藏页 ---------------- */
function page_favorites(): void
{
    page_header('我的收藏 - ' . SITE_NAME);
    ?>
<h1>我的收藏</h1>
<p class="muted notice-line">收藏保存在浏览器本地，清理缓存或更换设备会丢失。</p>
<div class="toolbar"><button class="btn sm ghost" id="btnClearFavs" type="button">清空收藏</button></div>
<div id="favList" class="card-grid"></div>
    <?php
    page_footer();
}

/* ---------------- 对比页 ---------------- */
function page_compare(): void
{
    page_header('快捷键对比 - ' . SITE_NAME);
    ?>
<h1>快捷键对比</h1>
<p class="muted">从软件详情页点击“加入对比”，最多对比 2 款软件；各栏可独立切换平台与版本。</p>
<div class="toolbar">
  <select id="cmpSelect"><option value="">选择软件加入对比…</option></select>
  <button class="btn sm ghost" id="btnClearCompare" type="button">清空对比</button>
</div>
<div id="cmpGrid" class="cmp-grid"></div>
    <?php
    page_footer();
}

/* ---------------- 反馈投稿页 ---------------- */
function page_feedback(): void
{
    $sw = trim((string)($_GET['s'] ?? ''));
    $err = '';

    /* 处理平台内提交（在输出前完成，可安全重定向） */
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $honeypot = trim((string)($_POST['website'] ?? '')); // 蜜罐字段：真人不会填
        if ($honeypot !== '') {
            header('Location: index.php?p=feedback&ok=1'); // 静默丢弃机器人
            exit;
        }
        $type = ($_POST['type'] ?? '') === 'correction' ? '纠错' : '投稿';
        $item = [
            'type'      => $type,
            'app_name'  => clean_text((string)($_POST['app_name'] ?? ''), 80),
            'content'   => clean_text((string)($_POST['content'] ?? ''), 2000),
            'correct'   => clean_text((string)($_POST['correct'] ?? ''), 2000),
            'contact'   => clean_text((string)($_POST['contact'] ?? ''), 120),
            'time'      => date('Y-m-d H:i:s'),
            'ip'        => client_ip(),
            'status'    => 'new',
        ];
        if ($item['app_name'] === '' || ($item['content'] === '' && $item['correct'] === '')) {
            $err = '请至少填写软件名称和内容。';
        } elseif (!rate_limit('submit', 5, 600)) {
            $err = '提交过于频繁（10 分钟内最多 5 条），请稍后再试。';
        } elseif (add_submission($item)) {
            header('Location: index.php?p=feedback&ok=1');
            exit;
        } else {
            $err = '保存失败，请稍后重试。';
        }
    }

    page_header('纠错与投稿 - ' . SITE_NAME);
    $cfg = site_config();
    ?>
<h1>纠错 &amp; 投稿</h1>
<?php if (isset($_GET['ok'])): ?>
<div class="notice-box ok">提交成功，感谢你的贡献！我们会在后台尽快处理，审核通过后上架。</div>
<?php endif; ?>
<?php if ($err !== ''): ?><div class="notice-box err"><?= e($err) ?></div><?php endif; ?>

<section class="card contact-card">
  <h2>批量投稿 / 长期合作</h2>
  <p class="muted">投稿内容较多时，欢迎扫码或添加站长 QQ / 微信直接发送表格文件，批量收录更快。</p>
  <div class="contact-body">
    <?php if ($cfg['qr_wechat'] !== '' || $cfg['qr_qq'] !== ''): ?>
    <div class="qr-grid">
      <?php if ($cfg['qr_wechat'] !== ''): ?>
      <figure class="qr-item">
        <img class="qr-img" src="<?= e(qr_src($cfg['qr_wechat'])) ?>" alt="微信二维码">
        <figcaption>微信</figcaption>
      </figure>
      <?php endif; ?>
      <?php if ($cfg['qr_qq'] !== ''): ?>
      <figure class="qr-item">
        <img class="qr-img" src="<?= e(qr_src($cfg['qr_qq'])) ?>" alt="QQ 二维码">
        <figcaption>QQ</figcaption>
      </figure>
      <?php endif; ?>
    </div>
    <?php endif; ?>
    <ul class="contact-list">
      <?php if ($cfg['qq'] !== ''): ?><li>QQ：<?= e($cfg['qq']) ?></li><?php endif; ?>
      <?php if ($cfg['wechat'] !== ''): ?><li>微信：<?= e($cfg['wechat']) ?></li><?php endif; ?>
      <?php if ($cfg['qq'] === '' && $cfg['wechat'] === '' && $cfg['qr_wechat'] === '' && $cfg['qr_qq'] === ''): ?>
      <li class="muted">联系方式暂未配置，请通过下方表单投稿。</li>
      <?php endif; ?>
      <?php if ($cfg['notice'] !== ''): ?><li class="muted"><?= e($cfg['notice']) ?></li><?php endif; ?>
    </ul>
  </div>
</section>

<section class="card form-card">
  <h2>快捷键纠错</h2>
  <form method="post" action="index.php?p=feedback">
    <input type="hidden" name="type" value="correction">
    <input type="text" name="website" class="hp-field" tabindex="-1" autocomplete="off" aria-hidden="true">
    <label>软件名称<input type="text" name="app_name" value="<?= e($sw) ?>" required></label>
    <label>错误描述<input type="text" name="content" placeholder="如：Ctrl+J 的功能描述有误" required></label>
    <label>正确内容<input type="text" name="correct" placeholder="如：Ctrl+J 应为 通过拷贝新建图层"></label>
    <label>联系方式（选填）<input type="text" name="contact" placeholder="QQ / 微信 / 邮箱"></label>
    <button class="btn" type="submit">提交纠错</button>
  </form>
</section>

<section class="card form-card">
  <h2>投稿收录</h2>
  <p class="muted">推荐新软件或提交快捷键表（每行一条：按键 | 功能 | 分组可选），审核通过后上架。</p>
  <form method="post" action="index.php?p=feedback">
    <input type="hidden" name="type" value="submit">
    <input type="text" name="website" class="hp-field" tabindex="-1" autocomplete="off" aria-hidden="true">
    <label>软件名称<input type="text" name="app_name" value="<?= e($sw) ?>" required></label>
    <label>平台 / 版本<input type="text" name="correct" placeholder="如：Windows / 2023-2025"></label>
    <label>快捷键内容<textarea name="content" rows="6" placeholder="Ctrl + S | 保存 | 文件"></textarea></label>
    <label>联系方式（选填）<input type="text" name="contact"></label>
    <button class="btn" type="submit">提交投稿</button>
  </form>
</section>
    <?php
    page_footer();
}

/* ---------------- 关于页 ---------------- */
function page_about(): void
{
    page_header('关于' . SITE_NAME);
    ?>
<h1>关于 <?= e(SITE_NAME) ?></h1>
<section class="card">
  <h2>站点介绍</h2>
  <p><?= e(SITE_NAME) ?>（<?= e(SITE_SLOGAN) ?>）是面向程序员、设计师与工程师的软件快捷键速查手册，重点补齐国内冷门工业、工程、科研软件的快捷键资料。</p>
  <h2>收录规则</h2>
  <ul>
    <li>仅收录桌面客户端软件（Windows / macOS / Linux），纯网页端不收录；</li>
    <li>快捷键优先参考官方帮助文档，社区整理内容标注来源；</li>
    <li>不确定的快捷键统一标注【待验证】；</li>
    <li>不收录破解、外挂类软件相关内容。</li>
  </ul>
  <h2>免责声明</h2>
  <p>快捷键仅供参考，以软件官方为准，不保证版本完全一致；收藏数据保存在浏览器本地，清理缓存会丢失。</p>
  <h2>更新计划</h2>
  <p>按“热门优先、行业刚需其次、冷门随缘补充、用户投稿辅助”策略持续更新，欢迎通过反馈页投稿。</p>
</section>
    <?php
    page_footer();
}

/* ---------------- API：供收藏页/对比页获取软件数据 ---------------- */
function page_api(array $apps): void
{
    $GLOBALS['qk_plain_output'] = true; // JSON 不做混淆
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    /* 未登录接口限流：每 IP 每分钟最多 30 次 */
    if (!rate_limit('api', 30, 60)) {
        http_response_code(429);
        echo json_encode(['error' => 'rate_limited', 'message' => '请求过于频繁，请稍后再试']);
        return;
    }
    if (isset($_GET['list'])) {
        echo json_encode(array_keys($apps), JSON_UNESCAPED_UNICODE);
        return;
    }
    /* 热门排行实时接口：index.php?p=api&top=8 */
    if (isset($_GET['top'])) {
        $n = max(1, min(12, (int)$_GET['top']));
        $st = get_stats();
        $sw = $st['software'];
        arsort($sw);
        $out = [];
        $i = 0;
        foreach (array_slice($sw, 0, $n, true) as $name => $v) {
            $i++;
            $app = $apps[$name] ?? null;
            $out[] = [
                'rank'     => $i,
                'name'     => (string)$name,
                'category' => $app ? $app['category'] : '热门',
                'visits'   => (int)$v,
                'url'      => sw_url((string)$name),
            ];
        }
        echo json_encode([
            'top'       => $out,
            'today'     => (int)$st['today'],
            'today_uv'  => (int)$st['today_uv'],
            'total_uv'  => (int)$st['total_uv'],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        return;
    }
    $name = trim((string)($_GET['name'] ?? ''));
    if (!isset($apps[$name])) {
        http_response_code(404);
        echo json_encode(['error' => 'not found'], JSON_UNESCAPED_UNICODE);
        return;
    }
    $app = $apps[$name];
    echo json_encode([
        'name' => $app['name'],
        'aliases' => $app['aliases'],
        'category' => $app['category'],
        'tags' => $app['tags'],
        'icon' => $app['icon'],
        'platforms' => platforms_of($app),
        'entries' => $app['entries'],
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}
