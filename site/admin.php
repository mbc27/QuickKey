<?php
declare(strict_types=1);

/**
 * 隐藏管理员入口：/admin.php（不挂任何前台链接，robots.txt 已屏蔽）
 * 安全措施：password_hash 存储、CSRF 校验、登录防爆破（5次失败锁15分钟）、
 *          session 隔离、数据原子写入 + 自动备份、输出全部转义。
 */
session_start([
    'cookie_httponly' => true,
    'cookie_samesite' => 'Lax',
]);

require __DIR__ . '/lib.php';

const DEFAULT_PASS = 'admin123456';

/* ---------- 管理员配置（首次自动生成，请尽快改密码） ---------- */
function admin_cfg(): array
{
    $f = DATA_DIR . '/admin_config.php';
    if (!is_file($f)) {
        @mkdir(DATA_DIR, 0775, true);
        $php = "<?php\n// 管理员配置：请登录后台后在“设置”中修改密码\nreturn [\n    'user' => 'admin',\n    'hash' => '" . password_hash(DEFAULT_PASS, PASSWORD_DEFAULT) . "',\n];\n";
        file_put_contents($f, $php, LOCK_EX);
    }
    $cfg = include $f;
    return is_array($cfg) ? $cfg : ['user' => 'admin', 'hash' => ''];
}

function save_admin_cfg(array $cfg): void
{
    $php = "<?php\n// 管理员配置\nreturn " . var_export($cfg, true) . ";\n";
    file_put_contents(DATA_DIR . '/admin_config.php', $php, LOCK_EX);
}

/** 保存前端裁剪后的二维码（data URL）到可公开访问的 assets/qr/ */
function save_qr_image(string $dataUrl, string $slot): ?string
{
    if ($dataUrl === '' || !preg_match('#^data:image/(png|jpeg|webp);base64,([A-Za-z0-9+/=\s]+)$#', $dataUrl, $m)) {
        return null;
    }
    $bin = base64_decode(preg_replace('/\s+/', '', $m[2]), true);
    if ($bin === false || $bin === '' || strlen($bin) > 800 * 1024) {
        return null;
    }
    $ext = $m[1] === 'jpeg' ? 'jpg' : $m[1];
    $dir = __DIR__ . '/assets/qr';
    @mkdir($dir, 0775, true);
    foreach (glob($dir . '/' . $slot . '.*') ?: [] as $old) {
        @unlink($old);
    }
    $file = $dir . '/' . $slot . '.' . $ext;
    if (@file_put_contents($file, $bin) === false) {
        return null;
    }
    @chmod($file, 0644);
    clearstatcache(true, $file);
    return 'assets/qr/' . $slot . '.' . $ext;
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(16));
    }
    return $_SESSION['csrf'];
}

function csrf_ok(): bool
{
    return isset($_POST['csrf'], $_SESSION['csrf']) && hash_equals($_SESSION['csrf'], (string)$_POST['csrf']);
}

/* ---------- 登录防爆破 ---------- */
function login_guard(): array
{
    return read_json_file(DATA_DIR . '/login_guard.json', ['fails' => 0, 'lock_until' => 0]);
}

function guard_fail(): void
{
    $g = login_guard();
    $g['fails'] = ($g['fails'] ?? 0) + 1;
    if ($g['fails'] >= 5) {
        $g['lock_until'] = time() + 900; // 锁15分钟
        $g['fails'] = 0;
    }
    write_json_file(DATA_DIR . '/login_guard.json', $g);
}

function guard_reset(): void
{
    write_json_file(DATA_DIR . '/login_guard.json', ['fails' => 0, 'lock_until' => 0]);
}

/* ---------- 入口网关：必须由前台「关于」5 连击携带一次性令牌进入 ---------- */
$logged = !empty($_SESSION['admin_ok']);
$gateOk = !empty($_SESSION['admin_gate']) && (time() - (int)$_SESSION['admin_gate']) < 1800;

if (!$logged && !$gateOk) {
    $k = (string)($_GET['k'] ?? '');
    $token = (string)($_SESSION['entry_token'] ?? '');
    $tTime = (int)($_SESSION['entry_time'] ?? 0);
    if ($k !== '' && $token !== '' && hash_equals($token, $k) && (time() - $tTime) <= 60) {
        unset($_SESSION['entry_token'], $_SESSION['entry_time']);
        $_SESSION['admin_gate'] = time();
        $gateOk = true;
        header('Location: admin.php');
        exit;
    }
    /* 直接输入地址访问：伪装成不存在的页面 */
    http_response_code(404);
    ?>
<!DOCTYPE html><html lang="zh-CN"><head><meta charset="UTF-8"><title>404 Not Found</title></head>
<body style="font-family:system-ui,sans-serif;padding:12vh 8vw;color:#555">
<h1 style="font-size:2rem;margin:0 0 .6rem">404 Not Found</h1>
<p>请求的页面不存在。</p>
<p style="margin-top:2rem"><a href="index.php" style="color:#3f5e80">返回首页</a></p>
</body></html>
    <?php
    exit;
}

$action = (string)($_GET['a'] ?? 'dashboard');
$msg = '';
$err = '';

if (!$logged && $_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'login') {
    $g = login_guard();
    if (time() < ($g['lock_until'] ?? 0)) {
        $err = '失败次数过多，请 ' . ceil((($g['lock_until'] ?? 0) - time()) / 60) . ' 分钟后再试。';
    } elseif (!csrf_ok()) {
        $err = '会话已过期，请重试。';
    } else {
        $cfg = admin_cfg();
        $u = (string)($_POST['user'] ?? '');
        $p = (string)($_POST['pass'] ?? '');
        if (hash_equals($cfg['user'], $u) && password_verify($p, $cfg['hash'])) {
            session_regenerate_id(true);
            $_SESSION['admin_ok'] = true;
            guard_reset();
            $logged = true;
            $action = 'dashboard';
        } else {
            guard_fail();
            $err = '账号或密码错误。';
        }
    }
}

/* ---------- 登出 ---------- */
if ($logged && $action === 'logout') {
    $_SESSION = [];
    session_destroy();
    header('Location: index.php');
    exit;
}

/* ---------- 后台布局 ---------- */
function admin_header(string $title): void
{
    ?>
<!DOCTYPE html>
<html lang="zh-CN" data-theme="light">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex, nofollow">
<title><?= e($title) ?> - 管理后台</title>
<link rel="icon" href="assets/favicon.ico" sizes="any">
<link rel="icon" type="image/svg+xml" href="assets/favicon.svg">
<link rel="apple-touch-icon" href="assets/apple-touch-icon.png">
<link rel="stylesheet" href="assets/style.css?v=7">
<script src="assets/lock.js?v=7"></script>
</head>
<body class="admin-body">
<header class="site-header admin-header">
  <div class="container head-row">
    <a class="logo" href="admin.php"><span class="logo-mark">⚙</span> 管理后台</a>
    <nav class="top-nav admin-nav">
      <a href="admin.php">数据看板</a>
      <a href="admin.php?a=swlist">软件管理</a>
      <a href="admin.php?a=submissions">投稿管理<?php $subNew = count(array_filter(get_submissions(), static fn ($s) => ($s['status'] ?? '') === 'new')); if ($subNew > 0): ?><span class="nav-badge"><?= $subNew > 99 ? '99+' : $subNew ?></span><?php endif; ?></a>
      <a href="admin.php?a=settings">设置</a>
      <a class="back-front" href="index.php">← 返回前台</a>
      <a href="admin.php?a=logout">退出</a>
    </nav>
  </div>
</header>
<main class="container">
    <?php
}

function admin_footer(): void
{
    ?>
</main>
</body>
</html>
    <?php
}

function require_post_csrf(): void
{
    if (!csrf_ok()) {
        http_response_code(403);
        exit('CSRF 校验失败');
    }
}

/* ---------- 快捷键文本解析：每行“按键 | 功能 | 分组(可选)” ---------- */
function parse_hotkeys_text(string $t): array
{
    $out = [];
    foreach (preg_split('/\r?\n/', trim($t)) as $line) {
        $line = trim($line);
        if ($line === '') {
            continue;
        }
        $parts = array_map('trim', explode('|', $line));
        $item = ['key' => mb_substr($parts[0], 0, 60), 'desc' => mb_substr($parts[1] ?? '', 0, 120)];
        if ($item['key'] === '') {
            continue;
        }
        if (!empty($parts[2])) {
            $item['group'] = mb_substr($parts[2], 0, 30);
        }
        $out[] = $item;
    }
    return $out;
}

function hotkeys_to_text(array $hotkeys): string
{
    return implode("\n", array_map(static fn ($h) => $h['key'] . ' | ' . ($h['desc'] ?? '') . (isset($h['group']) ? ' | ' . $h['group'] : ''), $hotkeys));
}

function load_raw_entries(): array
{
    $raw = json_decode((string)@file_get_contents(data_file('softwares.json')), true);
    return is_array($raw['app_hotkeys'] ?? null) ? $raw['app_hotkeys'] : [];
}

function save_raw_entries(array $rows): bool
{
    $file = data_file('softwares.json');
    $existing = json_decode((string)@file_get_contents($file), true) ?: [];
    $existing['app_hotkeys'] = $rows;
    return write_json_file($file, $existing, true); // 自动备份
}

function remove_pending(string $name): void
{
    $file = data_file('pending.json');
    $d = json_decode((string)@file_get_contents($file), true);
    if (!is_array($d['software'] ?? null)) {
        return;
    }
    $d['software'] = array_values(array_filter($d['software'], static fn ($s) => ($s['name'] ?? '') !== $name));
    write_json_file($file, $d);
}

/* ================= 未登录：登录页 ================= */
if (!$logged) {
    admin_header('登录');
    ?>
<div class="login-wrap">
  <h1>后台登录</h1>
  <section class="card form-card">
  <?php if ($err !== ''): ?><div class="notice-box err"><?= e($err) ?></div><?php endif; ?>
  <form method="post" action="admin.php?a=login">
    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
    <label>账号<input type="text" name="user" required autofocus></label>
    <label>密码<input type="password" name="pass" required></label>
    <button class="btn" type="submit">登录</button>
  </form>
  <p class="muted sm-text">仅站长使用 · 连续登录失败 5 次将锁定 15 分钟</p>
  </section>
</div>
    <?php
    admin_footer();
    exit;
}

/* ================= 已登录：各功能页 ================= */

/* ---------- 保存软件条目 ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($action, ['save', 'add'], true)) {
    require_post_csrf();
    $entry = [
        'app_name' => clean_text((string)($_POST['app_name'] ?? ''), 60),
        'aliases'  => array_values(array_filter(array_map('trim', explode(',', clean_text((string)($_POST['aliases'] ?? ''), 120))))),
        'icon'     => clean_text((string)($_POST['icon'] ?? ''), 60),
        'platform' => in_array($_POST['platform'] ?? '', ['Windows', 'Mac', 'Linux'], true) ? $_POST['platform'] : 'Windows',
        'category' => clean_text((string)($_POST['category'] ?? '未分类'), 30),
        'version'  => clean_text((string)($_POST['version'] ?? '默认'), 40),
        'tags'     => array_values(array_filter(array_map('trim', explode(',', clean_text((string)($_POST['tags'] ?? ''), 120))))),
        'updated'  => date('Y-m-d'),
        'source'   => ($_POST['source'] ?? '') === '官方文档' ? '官方文档' : '社区整理',
        'hotkeys'  => parse_hotkeys_text((string)($_POST['hotkeys'] ?? '')),
    ];
    if (!empty($_POST['note'])) {
        $entry['note'] = clean_text((string)$_POST['note'], 200);
    }
    $rows = load_raw_entries();
    if ($entry['app_name'] !== '' && count($entry['hotkeys']) > 0) {
        if ($action === 'add') {
            $rows[] = $entry;
        } else {
            $idx = (int)($_POST['idx'] ?? -1);
            if (isset($rows[$idx])) {
                $rows[$idx] = $entry;
            }
        }
        if (save_raw_entries($rows)) {
            if ($action === 'add' && !empty($_POST['remove_pending'])) {
                remove_pending($entry['app_name']);
            }
            header('Location: admin.php?a=swlist&ok=1');
            exit;
        }
        $err = '保存失败，请检查目录写权限。';
    } else {
        $err = '软件名称和快捷键内容不能为空（至少一行快捷键）。';
    }
}

/* ---------- 删除条目 / 整个软件 ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'delete') {
    require_post_csrf();
    $rows = load_raw_entries();
    if (($_POST['scope'] ?? '') === 'app') {
        $name = clean_text((string)($_POST['app_name'] ?? ''), 60);
        $rows = array_values(array_filter($rows, static fn ($r) => ($r['app_name'] ?? '') !== $name));
    } else {
        $idx = (int)($_POST['idx'] ?? -1);
        if (isset($rows[$idx])) {
            array_splice($rows, $idx, 1);
        }
    }
    save_raw_entries($rows);
    header('Location: admin.php?a=swlist&ok=1');
    exit;
}

/* ---------- 待补充清单：移除单条 ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'pending_delete') {
    require_post_csrf();
    remove_pending(clean_text((string)($_POST['name'] ?? ''), 60));
    header('Location: admin.php?a=swlist&tab=pending&ok=1');
    exit;
}

/* ---------- 投稿处理 ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'submission') {
    require_post_csrf();
    $list = get_submissions();
    $id = (string)($_POST['id'] ?? '');
    if (($_POST['op'] ?? '') === 'delete') {
        $list = array_values(array_filter($list, static fn ($s, $i) => (string)$i !== $id, ARRAY_FILTER_USE_BOTH));
    } else {
        if (isset($list[(int)$id])) {
            $list[(int)$id]['status'] = 'handled';
        }
    }
    save_submissions($list);
    header('Location: admin.php?a=submissions');
    exit;
}

/* ---------- 设置：联系方式 / 二维码 / 改密码 ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'settings') {
    require_post_csrf();
    $cfgFile = DATA_DIR . '/site_config.json';
    $cfg = site_config();
    if (($_POST['form'] ?? '') === 'contact') {
        $cfg['qq'] = clean_text((string)($_POST['qq'] ?? ''), 40);
        $cfg['wechat'] = clean_text((string)($_POST['wechat'] ?? ''), 40);
        $cfg['notice'] = clean_text((string)($_POST['notice'] ?? ''), 200);
        foreach (['wechat', 'qq'] as $slot) {
            $imgVal = (string)($_POST[$slot . '_img'] ?? '');
            if ($imgVal === '__CLEAR__') {
                foreach (glob(__DIR__ . '/assets/qr/' . $slot . '.*') ?: [] as $old) {
                    @unlink($old);
                }
                $cfg['qr_' . $slot] = '';
                continue;
            }
            $saved = save_qr_image($imgVal, $slot);
            if ($saved !== null) {
                $cfg['qr_' . $slot] = $saved;
            } elseif (($_POST[$slot . '_img'] ?? '') !== '') {
                $err = '二维码图片处理失败（仅支持 PNG/JPG/WEBP，且不超过 800KB）。';
            }
        }
        write_json_file($cfgFile, $cfg);
        if ($err === '') {
            clearstatcache();
            header('Location: admin.php?a=settings&ok=1'); // PRG：避免重复提交，且重新读取最新配置
            exit;
        }
    } elseif (($_POST['form'] ?? '') === 'password') {
        $old = (string)($_POST['old'] ?? '');
        $new = (string)($_POST['new'] ?? '');
        $admin = admin_cfg();
        if (!password_verify($old, $admin['hash'])) {
            $err = '原密码不正确。';
        } elseif (strlen($new) < 8) {
            $err = '新密码至少 8 位。';
        } else {
            $admin['hash'] = password_hash($new, PASSWORD_DEFAULT);
            save_admin_cfg($admin);
            $msg = '密码已修改。';
        }
    }
}

/* ---------- 页面渲染 ---------- */
switch ($action) {
    case 'dashboard':
    default:
        $st = get_stats();
        $subs = get_submissions();
        $newCount = count(array_filter($subs, static fn ($s) => ($s['status'] ?? '') === 'new'));
        $allApps = load_data();
        admin_header('数据看板');
        ?>
<h1>数据看板</h1>
<?php if ($msg): ?><div class="notice-box ok"><?= e($msg) ?></div><?php endif; ?>
<?php if ($err): ?><div class="notice-box err"><?= e($err) ?></div><?php endif; ?>
<div class="stat-cards">
  <div class="card stat-card"><h3>今日浏览量</h3><p class="stat-num"><?= (int)$st['today'] ?></p></div>
  <div class="card stat-card"><h3>今日访客</h3><p class="stat-num"><?= (int)$st['today_uv'] ?></p></div>
  <div class="card stat-card"><h3>累计浏览量</h3><p class="stat-num"><?= (int)$st['total'] ?></p></div>
  <div class="card stat-card"><h3>累计访客</h3><p class="stat-num"><?= (int)$st['total_uv'] ?></p></div>
  <div class="card stat-card"><h3>收录软件</h3><p class="stat-num"><?= count($allApps) ?></p></div>
  <div class="card stat-card"><h3>快捷键总数</h3><p class="stat-num"><?= hotkey_total($allApps) ?></p></div>
  <div class="card stat-card"><h3>待处理投稿</h3><p class="stat-num"><?= $newCount ?></p></div>
</div>

<section class="card" style="margin-top:16px">
  <h2>访客 IP 列表 <small class="muted">点击 IP 查看该访客的访问明细</small></h2>
  <table class="hk-table">
    <thead><tr><th>IP 地址</th><th>访问次数</th><th>最近访问</th><th></th></tr></thead>
    <tbody>
    <?php
    $byIp = [];
    foreach ($st['log'] ?? [] as $v) {
        $ip = (string)($v['ip'] ?? '未知');
        if (!isset($byIp[$ip])) {
            $byIp[$ip] = ['count' => 0, 'last' => (string)($v['time'] ?? '')];
        }
        $byIp[$ip]['count']++;
        if (($v['time'] ?? '') > $byIp[$ip]['last']) {
            $byIp[$ip]['last'] = (string)$v['time'];
        }
    }
    uasort($byIp, static fn ($a, $b) => $b['count'] <=> $a['count']);
    foreach ($byIp as $ip => $info):
    ?>
    <tr>
      <td><a href="admin.php?a=visitor&ip=<?= e(urlencode((string)$ip)) ?>"><code><?= e((string)$ip) ?></code></a></td>
      <td><?= (int)$info['count'] ?></td>
      <td class="muted sm-text"><?= e($info['last']) ?></td>
      <td><a class="btn sm ghost" href="admin.php?a=visitor&ip=<?= e(urlencode((string)$ip)) ?>">查看明细</a></td>
    </tr>
    <?php endforeach; ?>
    <?php if (!$byIp): ?><tr><td colspan="4" class="muted">暂无访客记录</td></tr><?php endif; ?>
    </tbody>
  </table>
</section>

<section class="card" style="margin-top:16px">
  <h2>最近 14 天访问量 <small class="muted">今天 <?= e(date('Y-m-d')) ?> 至 <?= e(date('Y-m-d', strtotime('-13 day'))) ?></small></h2>
  <?php
  /* 固定 14 天窗口：今天 + 往前 13 天，无数据日期补 0，逐日向前滚动 */
  $days = $st['days'];
  $chart = [];
  for ($k = 13; $k >= 0; $k--) {
      $d = date('Y-m-d', strtotime("-{$k} day"));
      $chart[$d] = (int)($days[$d] ?? 0);
  }
  $maxVal = max($chart) ?: 0;
  $today = date('Y-m-d');
  ?>
  <div class="bar-chart">
    <?php foreach ($chart as $day => $n): ?>
    <div class="bar-col <?= $day === $today ? 'is-today' : '' ?>" title="<?= e((string)$day) ?>：浏览 <?= (int)$n ?> 次 · 访客 <?= (int)($st['days_uv'][$day] ?? 0) ?> 人">
      <span class="bar-value"><?= (int)$n ?></span>
      <div class="bar" style="height: <?= $maxVal > 0 ? max(4, (int)round($n / $maxVal * 100)) : 4 ?>%"></div>
      <span class="bar-label"><?= $day === $today ? '今天' : e(substr((string)$day, 5)) ?></span>
    </div>
    <?php endforeach; ?>
  </div>
</section>

<section class="card" style="margin-top:16px">
  <h2>软件访问 Top10</h2>
  <table class="hk-table">
    <thead><tr><th>#</th><th>软件</th><th>访问量</th></tr></thead>
    <tbody>
    <?php $i = 0; foreach (top_software(10) as $name => $n): $i++; ?>
    <tr><td><?= $i ?></td><td><a href="<?= e(sw_url((string)$name)) ?>"><?= e((string)$name) ?></a></td><td><?= (int)$n ?></td></tr>
    <?php endforeach; ?>
    <?php if (!top_software(10)): ?><tr><td colspan="3" class="muted">暂无数据</td></tr><?php endif; ?>
    </tbody>
  </table>
</section>
        <?php
        admin_footer();
        break;

    case 'visitor':
        $ip = clean_text((string)($_GET['ip'] ?? ''), 60);
        $st = get_stats();
        $appsAll = load_data();
        $hits = [];
        foreach ($st['log'] ?? [] as $v) {
            if ((string)($v['ip'] ?? '') === $ip) {
                $hits[] = $v;
            }
        }
        admin_header('访客明细');
        ?>
<h1>访客明细：<code><?= e($ip) ?></code> <small class="muted">共 <?= count($hits) ?> 条记录</small></h1>
<div class="toolbar"><a class="btn sm ghost" href="admin.php">← 返回看板</a></div>
<section class="card">
  <table class="hk-table">
    <thead><tr><th>#</th><th>时间</th><th>访问页面</th></tr></thead>
    <tbody>
    <?php foreach ($hits as $i => $v): ?>
    <tr>
      <td class="muted"><?= $i + 1 ?></td>
      <td><?= e($v['time'] ?? '') ?></td>
      <td>
        <?php $pv = (string)($v['page'] ?? ''); ?>
        <?php if (isset($appsAll[$pv])): ?>
        <a href="<?= e(sw_url($pv)) ?>"><?= e($pv) ?></a>
        <?php else: ?>
        <?= e($pv === 'home' ? '首页' : $pv) ?>
        <?php endif; ?>
      </td>
    </tr>
    <?php endforeach; ?>
    <?php if (!$hits): ?><tr><td colspan="3" class="muted">该 IP 暂无记录</td></tr><?php endif; ?>
    </tbody>
  </table>
</section>
        <?php
        admin_footer();
        break;

    case 'swlist':
        $apps = load_data();
        $raw = load_raw_entries();
        $pending = load_pending();
        $tab = ($_GET['tab'] ?? '') === 'pending' ? 'pending' : 'data';
        $q = clean_text((string)($_GET['q'] ?? ''), 40);
        admin_header('软件管理');
        ?>
<h1>软件管理 <small class="muted">共 <?= count($apps) + count($pending) ?> 款（已收录 <?= count($apps) ?> · 待补充 <?= count($pending) ?>）/ <?= count($raw) ?> 条平台版本记录</small></h1>
<?php if (isset($_GET['ok'])): ?><div class="notice-box ok">操作成功。</div><?php endif; ?>

<div class="admin-tabs">
  <a class="admin-tab <?= $tab === 'data' ? 'active' : '' ?>" href="admin.php?a=swlist">已收录数据库（<?= count($apps) ?>）</a>
  <a class="admin-tab <?= $tab === 'pending' ? 'active' : '' ?>" href="admin.php?a=swlist&tab=pending">待补充清单（<?= count($pending) ?>）</a>
</div>

<?php if ($tab === 'data'): ?>
<div class="toolbar"><a class="btn" href="admin.php?a=add">+ 新增软件条目</a></div>
<?php
$byApp = [];
foreach ($raw as $idx => $r) {
    $byApp[$r['app_name']][] = ['idx' => $idx, 'row' => $r];
}
foreach ($byApp as $name => $rows):
?>
<section class="card compact" style="margin-bottom:12px">
  <div class="admin-app-head">
    <h3><?= e((string)$name) ?> <span class="muted sm-text"><?= count($rows) ?> 条记录</span></h3>
    <form method="post" action="admin.php?a=delete" onsubmit="return confirm('确定删除软件「<?= e((string)$name) ?>」的全部记录？')">
      <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
      <input type="hidden" name="scope" value="app">
      <input type="hidden" name="app_name" value="<?= e((string)$name) ?>">
      <button class="btn sm ghost danger" type="submit">删除整个软件</button>
    </form>
  </div>
  <ul class="admin-entry-list">
    <?php foreach ($rows as $r): $row = $r['row']; ?>
    <li>
      <span class="tag"><?= e($row['platform'] ?? 'Windows') ?></span>
      <span class="tag"><?= e($row['version'] ?? '默认') ?></span>
      <span class="muted sm-text"><?= count($row['hotkeys'] ?? []) ?> 条快捷键 · <?= e($row['category'] ?? '') ?></span>
      <span class="admin-entry-ops">
        <a class="btn sm ghost" href="admin.php?a=edit&idx=<?= (int)$r['idx'] ?>">编辑</a>
        <form method="post" action="admin.php?a=delete" onsubmit="return confirm('删除这条平台/版本记录？')">
          <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
          <input type="hidden" name="idx" value="<?= (int)$r['idx'] ?>">
          <button class="btn sm ghost danger" type="submit">删除</button>
        </form>
      </span>
    </li>
    <?php endforeach; ?>
  </ul>
</section>
<?php endforeach; ?>

<?php else: ?>
<form class="toolbar" method="get" action="admin.php">
  <input type="hidden" name="a" value="swlist">
  <input type="hidden" name="tab" value="pending">
  <input class="admin-search" type="search" name="q" value="<?= e($q) ?>" placeholder="搜索软件名称 / 分类…">
  <button class="btn sm" type="submit">搜索</button>
  <a class="btn sm ghost" href="admin.php?a=add&name=<?= e($q) ?>">+ 直接新增条目</a>
</form>
<?php
$list = $pending;
if ($q !== '') {
    $list = array_filter($pending, static fn ($s) => stripos($s['name'], $q) !== false || stripos($s['category'], $q) !== false);
}
$total = count($list);
$perPage = 80;
$page = max(1, (int)($_GET['page'] ?? 1));
$pages = max(1, (int)ceil($total / $perPage));
$page = min($page, $pages);
$slice = array_slice(array_values($list), ($page - 1) * $perPage, $perPage);
?>
<p class="muted sm-text">共 <?= $total ?> 款待补充软件<?= $q !== '' ? '（关键词：' . e($q) . '）' : '' ?>，点击「收录」进入快捷键录入页。</p>
<section class="card compact">
  <ul class="admin-entry-list">
    <?php foreach ($slice as $s): ?>
    <li>
      <strong><?= e($s['name']) ?></strong>
      <span class="muted sm-text"><?= e($s['category']) ?><?= $s['platforms'] ? ' · ' . e(implode('/', $s['platforms'])) : '' ?><?= $s['doc_url'] !== '' ? ' · 有官方链接' : '' ?></span>
      <span class="admin-entry-ops">
        <a class="btn sm ghost" href="admin.php?a=add&name=<?= e(urlencode($s['name'])) ?>">收录</a>
        <form method="post" action="admin.php?a=pending_delete" onsubmit="return confirm('从待补充清单移除「<?= e($s['name']) ?>」？')">
          <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
          <input type="hidden" name="name" value="<?= e($s['name']) ?>">
          <button class="btn sm ghost danger" type="submit">移除</button>
        </form>
      </span>
    </li>
    <?php endforeach; ?>
  </ul>
</section>
<?php if ($pages > 1): ?>
<div class="pager">
  <?php for ($i = 1; $i <= $pages; $i++): ?>
  <a class="chip <?= $i === $page ? 'active' : '' ?>" href="admin.php?a=swlist&tab=pending&page=<?= $i ?><?= $q !== '' ? '&q=' . urlencode($q) : '' ?>"><?= $i ?></a>
  <?php endfor; ?>
</div>
<?php endif; ?>
<?php endif; ?>
        <?php
        admin_footer();
        break;

    case 'edit':
    case 'add':
        $raw = load_raw_entries();
        $row = $action === 'edit' ? ($raw[(int)($_GET['idx'] ?? -1)] ?? null) : null;
        $idx = $action === 'edit' ? (int)($_GET['idx'] ?? -1) : -1;
        $prefillName = $action === 'add' ? clean_text((string)($_GET['name'] ?? ''), 60) : '';
        admin_header($action === 'edit' ? '编辑软件' : '新增软件');
        ?>
<h1><?= $action === 'edit' ? '编辑：' . e($row['app_name'] ?? '') : '新增软件条目' ?></h1>
<?php if ($err): ?><div class="notice-box err"><?= e($err) ?></div><?php endif; ?>
<section class="card form-card">
  <form method="post" action="admin.php?a=<?= $action === 'edit' ? 'save' : 'add' ?>">
    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
    <input type="hidden" name="idx" value="<?= $idx ?>">
    <div class="form-row">
      <label>软件名称 *<input type="text" name="app_name" value="<?= e($row['app_name'] ?? $prefillName) ?>" required></label>
      <label>别名（逗号分隔）<input type="text" name="aliases" value="<?= e(implode(',', $row['aliases'] ?? [])) ?>"></label>
    </div>
    <div class="form-row">
      <label>平台
        <select name="platform">
          <?php foreach (['Windows', 'Mac', 'Linux'] as $p): ?>
          <option value="<?= $p ?>" <?= ($row['platform'] ?? 'Windows') === $p ? 'selected' : '' ?>><?= $p ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <label>版本<input type="text" name="version" value="<?= e($row['version'] ?? '默认') ?>" placeholder="如 2021-2025 / 全版本"></label>
      <label>分类<input type="text" name="category" value="<?= e($row['category'] ?? '') ?>" list="catList">
        <datalist id="catList">
          <?php foreach (all_categories(load_data()) as $c => $n): ?><option value="<?= e((string)$c) ?>"><?php endforeach; ?>
        </datalist>
      </label>
    </div>
    <div class="form-row">
      <label>标签（逗号分隔）<input type="text" name="tags" value="<?= e(implode(',', $row['tags'] ?? [])) ?>"></label>
      <label>来源
        <select name="source">
          <option value="官方文档" <?= ($row['source'] ?? '') === '官方文档' ? 'selected' : '' ?>>官方文档</option>
          <option value="社区整理" <?= ($row['source'] ?? '') === '社区整理' ? 'selected' : '' ?>>社区整理</option>
        </select>
      </label>
      <label>图标文件名<input type="text" name="icon" value="<?= e($row['icon'] ?? '') ?>" placeholder="如 photoshop.png"></label>
    </div>
    <label>备注<input type="text" name="note" value="<?= e($row['note'] ?? '') ?>"></label>
    <label>快捷键 *（每行一条：按键 | 功能 | 分组可选）<textarea name="hotkeys" rows="14" required placeholder="Ctrl + S | 保存 | 文件"><?= e(isset($row['hotkeys']) ? hotkeys_to_text($row['hotkeys']) : '') ?></textarea></label>
    <?php if ($action === 'add'): ?>
    <label class="check-line"><input type="checkbox" name="remove_pending" value="1" checked> 同时从「待补充清单」中移除该软件</label>
    <?php endif; ?>
    <div class="toolbar">
      <button class="btn" type="submit">保存</button>
      <a class="btn ghost" href="admin.php?a=swlist">返回列表</a>
    </div>
  </form>
</section>
        <?php
        admin_footer();
        break;

    case 'submissions':
        $subs = get_submissions();
        admin_header('投稿管理');
        ?>
<h1>投稿管理 <small class="muted">共 <?= count($subs) ?> 条</small></h1>
<?php if (!$subs): ?>
<section class="empty-state"><h2>暂无投稿</h2><p class="muted">前台「纠错与投稿」页提交的内容会显示在这里。</p></section>
<?php endif; ?>
<?php foreach ($subs as $i => $s): ?>
<section class="card submission-card <?= ($s['status'] ?? '') === 'new' ? 'is-new' : '' ?>">
  <div class="admin-app-head">
    <div>
      <span class="tag <?= ($s['type'] ?? '') === '纠错' ? 'pending-tag' : 'cat' ?>"><?= e($s['type'] ?? '') ?></span>
      <strong><?= e($s['app_name'] ?? '') ?></strong>
      <span class="muted sm-text"><?= e($s['time'] ?? '') ?> · 状态：<?= ($s['status'] ?? '') === 'new' ? '待处理' : '已处理' ?> · IP：<?= e($s['ip'] ?? '未知') ?></span>
    </div>
    <div class="toolbar">
      <?php if (($s['status'] ?? '') === 'new'): ?>
      <form method="post" action="admin.php?a=submission">
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="id" value="<?= (int)$i ?>">
        <input type="hidden" name="op" value="done">
        <button class="btn sm" type="submit">标记已处理</button>
      </form>
      <?php endif; ?>
      <form method="post" action="admin.php?a=submission" onsubmit="return confirm('确定删除该投稿？')">
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="id" value="<?= (int)$i ?>">
        <input type="hidden" name="op" value="delete">
        <button class="btn sm ghost danger" type="submit">删除</button>
      </form>
    </div>
  </div>
  <?php if (($s['correct'] ?? '') !== ''): ?><p><span class="muted">补充/正确内容：</span><?= nl2br(e($s['correct'])) ?></p><?php endif; ?>
  <?php if (($s['content'] ?? '') !== ''): ?><pre class="sub-content"><?= e($s['content']) ?></pre><?php endif; ?>
  <?php if (($s['contact'] ?? '') !== ''): ?><p class="muted sm-text">联系方式：<?= e($s['contact']) ?></p><?php endif; ?>
</section>
<?php endforeach; ?>
        <?php
        admin_footer();
        break;

    case 'settings':
        $cfg = site_config();
        admin_header('设置');
        ?>
<h1>设置</h1>
<?php if (isset($_GET['ok'])): ?><div class="notice-box ok">联系方式与二维码已更新，前台刷新即可看到最新内容。</div><?php endif; ?>
<?php if ($msg): ?><div class="notice-box ok"><?= e($msg) ?></div><?php endif; ?>
<?php if ($err): ?><div class="notice-box err"><?= e($err) ?></div><?php endif; ?>

<section class="card form-card">
  <h2>投稿联系方式（前台展示）</h2>
  <form method="post" action="admin.php?a=settings" id="contactForm">
    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
    <input type="hidden" name="form" value="contact">
    <div class="form-row">
      <label>QQ 号码<input type="text" name="qq" value="<?= e($cfg['qq']) ?>"></label>
      <label>微信号<input type="text" name="wechat" value="<?= e($cfg['wechat']) ?>"></label>
    </div>

    <div class="qr-manage">
      <?php foreach ([['wechat', '微信二维码'], ['qq', 'QQ 二维码']] as [$slot, $label]): ?>
      <div class="qr-slot">
        <h3><?= $label ?></h3>
        <div class="qr-preview" data-slot="<?= $slot ?>">
          <?php if ($cfg['qr_' . $slot] !== ''): ?>
          <img class="qr-img sm" src="<?= e(qr_src($cfg['qr_' . $slot])) ?>" alt="<?= $label ?>">
          <?php else: ?>
          <span class="muted sm-text">未上传</span>
          <?php endif; ?>
        </div>
        <div class="toolbar">
          <label class="btn sm ghost" style="cursor:pointer;margin:0">
            <?= $cfg['qr_' . $slot] !== '' ? '替换图片' : '上传图片' ?>
            <input type="file" accept="image/png,image/jpeg,image/webp" hidden data-pick="<?= $slot ?>">
          </label>
          <button class="btn sm ghost" type="button" data-crop-btn="<?= $slot ?>" <?= $cfg['qr_' . $slot] === '' ? 'hidden' : '' ?>>裁剪</button>
          <button class="btn sm ghost danger" type="button" data-clear-btn="<?= $slot ?>" <?= $cfg['qr_' . $slot] === '' ? 'hidden' : '' ?>>清除二维码</button>
        </div>
        <p class="muted sm-text" style="margin:6px 0 0">上传即替换旧图；如需精确定位可点「裁剪」。</p>
        <input type="hidden" name="<?= $slot ?>_img" id="img_<?= $slot ?>" value="">
      </div>
      <?php endforeach; ?>
    </div>

    <label>公告文字（选填，显示在前台顶部与投稿页）<input type="text" name="notice" value="<?= e($cfg['notice']) ?>" maxlength="200" placeholder="如：工作日晚上统一处理投稿"></label>
    <button class="btn" type="submit">保存联系方式</button>
  </form>
</section>

<!-- 图片裁剪弹层 -->
<div class="crop-mask" id="cropMask" hidden>
  <div class="crop-dialog">
    <h3>裁剪二维码</h3>
    <p class="muted sm-text">拖动方框选择二维码区域，滚轮或下方滑块调整大小；方框内区域将保存为 400×400 图片。</p>
    <div class="crop-stage" id="cropStage">
      <canvas id="cropCanvas"></canvas>
      <div class="crop-box" id="cropBox"></div>
    </div>
    <div class="crop-zoom">
      <span class="sm-text muted">选框大小</span>
      <input type="range" id="cropSize" min="60" max="100" value="60">
    </div>
    <div class="toolbar" style="justify-content:flex-end">
      <button class="btn ghost sm" type="button" id="cropCancel">取消</button>
      <button class="btn sm" type="button" id="cropOk">确定裁剪</button>
    </div>
  </div>
</div>
<script src="assets/crop.js?v=4"></script>

<section class="card form-card">
  <h2>修改密码</h2>
  <form method="post" action="admin.php?a=settings">
    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
    <input type="hidden" name="form" value="password">
    <label>原密码<input type="password" name="old" required></label>
    <label>新密码（至少 8 位）<input type="password" name="new" minlength="8" required></label>
    <button class="btn" type="submit">修改密码</button>
  </form>
</section>

<section class="card form-card">
  <h2>数据安全</h2>
  <p class="muted">所有数据修改都会自动备份到 <code>site/data/backups/</code>（保留最近 20 份）。数据文件已通过 .htaccess 禁止 Web 直接访问；Nginx 需在站点配置中补充：</p>
  <pre class="sub-content">location ^~ /data/ { deny all; return 404; }</pre>
</section>
        <?php
        admin_footer();
        break;
}
