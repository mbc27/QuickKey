/* 速键库 - 前端交互：主题、详情渲染、复制、导出、收藏、对比 */
(function () {
  'use strict';

  var $ = function (sel, root) { return (root || document).querySelector(sel); };
  var $$ = function (sel, root) { return Array.prototype.slice.call((root || document).querySelectorAll(sel)); };

  /* ---------- 主题切换 ---------- */
  $('#themeToggle') && $('#themeToggle').addEventListener('click', function () {
    var t = document.documentElement.dataset.theme === 'dark' ? 'light' : 'dark';
    document.documentElement.dataset.theme = t;
    localStorage.setItem('qk_theme', t);
  });

  /* ---------- 隐藏入口：连续点击「关于」5 次进入后台登录 ---------- */
  var aboutLink = $('#navAbout');
  if (aboutLink) {
    var clicks = 0, jumpTimer = null, resetTimer = null;
    aboutLink.addEventListener('click', function (ev) {
      ev.preventDefault();
      clicks++;
      clearTimeout(jumpTimer);
      clearTimeout(resetTimer);
      if (clicks >= 5) {
        var k = aboutLink.getAttribute('data-entry') || '';
        window.location.href = 'admin.php?k=' + encodeURIComponent(k);
        return;
      }
      jumpTimer = setTimeout(function () {
        window.location.href = aboutLink.getAttribute('href');
      }, 400);
      resetTimer = setTimeout(function () { clicks = 0; }, 1500);
    });
  }

  /* ---------- Toast ---------- */
  var toastTimer = null;
  function toast(msg) {
    var el = $('#toast');
    if (!el) return;
    el.textContent = msg;
    el.hidden = false;
    clearTimeout(toastTimer);
    toastTimer = setTimeout(function () { el.hidden = true; }, 1600);
  }

  /* ---------- 复制 ---------- */
  function copyText(text) {
    if (navigator.clipboard && navigator.clipboard.writeText) {
      navigator.clipboard.writeText(text).then(function () { toast('已复制'); }, function () { fallbackCopy(text); });
    } else {
      fallbackCopy(text);
    }
  }
  function fallbackCopy(text) {
    var ta = document.createElement('textarea');
    ta.value = text;
    ta.style.position = 'fixed';
    ta.style.opacity = '0';
    document.body.appendChild(ta);
    ta.select();
    try { document.execCommand('copy'); toast('已复制'); } catch (e) { toast('复制失败'); }
    document.body.removeChild(ta);
  }

  /* ---------- localStorage 工具 ---------- */
  function getList(key) {
    try { return JSON.parse(localStorage.getItem(key) || '[]'); } catch (e) { return []; }
  }
  function setList(key, arr) { localStorage.setItem(key, JSON.stringify(arr)); }
  function toggleListItem(key, name, max) {
    var arr = getList(key);
    var i = arr.indexOf(name);
    if (i >= 0) { arr.splice(i, 1); }
    else {
      if (max && arr.length >= max) { toast('最多添加 ' + max + ' 项'); return false; }
      arr.push(name);
    }
    setList(key, arr);
    return true;
  }
  window.QK = {
    favs: function () { return getList('qk_favs'); },
    toggleFav: function (name) { return toggleListItem('qk_favs', name); },
    compare: function () { return getList('qk_compare'); },
    toggleCompare: function (name) { return toggleListItem('qk_compare', name, 2); },
    toast: toast,
    copyText: copyText
  };

  /* ---------- 按键文本渲染 ---------- */
  function renderKeys(key) {
    return key.split(/ \+ | \/ /g).map(function (part, i) {
      return '<kbd>' + escapeHtml(part.trim()) + '</kbd>';
    }).join('<span class="muted"> + </span>');
  }
  function escapeHtml(s) {
    return s.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
  }

  /* ---------- 通用渲染：平台Tab + 版本切换 + 分组折叠面板 ---------- */
  function renderHotkeyPanel(root, app, state) {
    state = state || { platform: app.platforms[0], version: null };
    if (!state.version) {
      var vs = versionsFor(app, state.platform);
      state.version = vs[0] || null;
    }
    var entry = entryFor(app, state.platform, state.version);

    var html = '<div class="hk-tabs">';
    app.platforms.forEach(function (p) {
      html += '<button class="hk-tab' + (p === state.platform ? ' active' : '') + '" data-plat="' + escapeHtml(p) + '">' + escapeHtml(p) + '</button>';
    });
    var vs = versionsFor(app, state.platform);
    if (vs.length > 1) {
      html += '<span class="hk-version"><select id="verSelect">';
      vs.forEach(function (v) {
        html += '<option value="' + escapeHtml(v) + '"' + (v === state.version ? ' selected' : '') + '>' + escapeHtml(v) + '</option>';
      });
      html += '</select></span>';
    }
    html += '</div>';

    html += '<div class="hk-tools">'
      + '<button class="btn sm ghost" data-act="expand">全部展开</button>'
      + '<button class="btn sm ghost" data-act="collapse">全部收起</button>'
      + '<button class="btn sm ghost" data-act="copyall">复制本页全部</button>'
      + '<span class="muted">来源：' + escapeHtml(entry.source || '社区整理') + ' · 更新：' + escapeHtml(entry.updated || '—') + '</span>'
      + '</div>';

    var groups = groupEntry(entry);
    groups.forEach(function (g) {
      html += '<div class="panel-group"><div class="panel-head"><h3>' + escapeHtml(g.name)
        + '</h3><span class="muted">' + g.items.length + ' 条</span></div>'
        + '<div class="panel-body"><div class="hk-table-wrap"><table class="hk-table"><thead><tr><th>按键</th><th>功能</th><th></th></tr></thead><tbody>';
      g.items.forEach(function (hk) {
        var desc = escapeHtml(hk.desc || '');
        if (hk.note) desc += ' <span class="muted sm-text">（' + escapeHtml(hk.note) + '）</span>';
        html += '<tr><td class="key-col">' + renderKeys(hk.key || '') + '</td><td>' + desc
          + '</td><td><button class="row-copy" data-copy="' + escapeHtml((hk.key || '') + ' — ' + (hk.desc || '')) + '" title="复制">⧉</button></td></tr>';
      });
      html += '</tbody></table></div></div></div>';
    });
    if (entry.note) html += '<p class="muted">备注：' + escapeHtml(entry.note) + '</p>';

    root.innerHTML = html;

    /* 事件绑定 */
    $$('.hk-tab', root).forEach(function (btn) {
      btn.addEventListener('click', function () {
        renderHotkeyPanel(root, app, { platform: btn.dataset.plat, version: null });
      });
    });
    var verSel = $('#verSelect', root);
    verSel && verSel.addEventListener('change', function () {
      renderHotkeyPanel(root, app, { platform: state.platform, version: verSel.value });
    });
    $$('.panel-head', root).forEach(function (head) {
      head.addEventListener('click', function () {
        $('.panel-body', head.parentElement).classList.toggle('collapsed');
      });
    });
    $$('[data-act]', root).forEach(function (btn) {
      btn.addEventListener('click', function () {
        var act = btn.dataset.act;
        if (act === 'expand' || act === 'collapse') {
          $$('.panel-body', root).forEach(function (b) { b.classList.toggle('collapsed', act === 'collapse'); });
        } else if (act === 'copyall') {
          copyText(allText(app, state));
        }
      });
    });
    $$('[data-copy]', root).forEach(function (btn) {
      btn.addEventListener('click', function (ev) {
        ev.stopPropagation();
        copyText(btn.dataset.copy);
      });
    });

    root._state = state;
    root._app = app;
  }

  function versionsFor(app, platform) {
    var out = [];
    app.entries.forEach(function (en) {
      if (en.platform === platform && out.indexOf(en.version) < 0) out.push(en.version);
    });
    return out;
  }
  function entryFor(app, platform, version) {
    var list = app.entries.filter(function (en) {
      return en.platform === platform && (version == null || en.version === version);
    });
    return list[0] || app.entries[0] || { hotkeys: [], platform: platform, version: version || '默认' };
  }
  function groupEntry(entry) {
    var map = new Map();
    entry.hotkeys.forEach(function (hk) {
      var g = hk.group || '常用';
      if (!map.has(g)) map.set(g, []);
      map.get(g).push(hk);
    });
    var arr = [];
    map.forEach(function (items, name) { arr.push({ name: name, items: items }); });
    return arr;
  }
  function allText(app, state) {
    var entry = entryFor(app, state.platform, state.version);
    var lines = [app.name + '（' + state.platform + ' · ' + state.version + '）快捷键'];
    groupEntry(entry).forEach(function (g) {
      lines.push('', '## ' + g.name);
      g.items.forEach(function (hk) { lines.push((hk.key || '') + '\t' + (hk.desc || '')); });
    });
    return lines.join('\n');
  }

  /* ---------- 导出 ---------- */
  function exportMarkdown(app, state) {
    var entry = entryFor(app, state.platform, state.version);
    var md = '# ' + app.name + '（' + state.platform + ' · ' + state.version + '）快捷键\n\n';
    groupEntry(entry).forEach(function (g) {
      md += '## ' + g.name + '\n\n| 按键 | 功能 |\n| --- | --- |\n';
      g.items.forEach(function (hk) { md += '| ' + (hk.key || '') + ' | ' + (hk.desc || '') + ' |\n'; });
      md += '\n';
    });
    download(app.name + '-shortcut.md', md, 'text/markdown');
    toast('已导出 Markdown');
  }
  function exportPng(app, state) {
    var entry = entryFor(app, state.platform, state.version);
    var groups = groupEntry(entry);
    var W = 860, rowH = 40, headH = 64, groupHeadH = 44;
    var rows = 0;
    groups.forEach(function (g) { rows += g.items.length + 1; });
    var H = headH + rows * rowH + 30;
    var canvas = document.createElement('canvas');
    var scale = 2;
    canvas.width = W * scale; canvas.height = H * scale;
    var ctx = canvas.getContext('2d');
    ctx.scale(scale, scale);
    var dark = document.documentElement.dataset.theme === 'dark';
    var bg = dark ? '#1e232b' : '#ffffff';
    var fg = dark ? '#d5dae2' : '#2c3340';
    var sub = dark ? '#8b95a6' : '#7a8496';
    var line = dark ? '#2d3440' : '#e3e8ef';
    var head = dark ? '#2a3140' : '#eef1f6';
    ctx.fillStyle = bg; ctx.fillRect(0, 0, W, H);
    ctx.fillStyle = fg;
    ctx.font = 'bold 26px "PingFang SC","Microsoft YaHei",sans-serif';
    ctx.fillText(app.name + ' 快捷键', 30, 42);
    ctx.fillStyle = sub;
    ctx.font = '14px sans-serif';
    ctx.fillText(state.platform + ' · ' + state.version + ' · 来源：速键库', 30, 60);
    var y = headH;
    groups.forEach(function (g) {
      ctx.fillStyle = head; ctx.fillRect(0, y, W, rowH);
      ctx.fillStyle = sub; ctx.font = 'bold 15px sans-serif';
      ctx.fillText(g.name, 30, y + 26);
      y += rowH;
      ctx.font = '14px sans-serif';
      g.items.forEach(function (hk) {
        ctx.strokeStyle = line; ctx.beginPath(); ctx.moveTo(0, y + rowH); ctx.lineTo(W, y + rowH); ctx.stroke();
        ctx.fillStyle = fg;
        ctx.fillText(hk.key || '', 30, y + 26, 360);
        ctx.fillText(hk.desc || '', 420, y + 26, W - 450);
        y += rowH;
      });
    });
    canvas.toBlob(function (blob) {
      var a = document.createElement('a');
      a.href = URL.createObjectURL(blob);
      a.download = app.name + '-shortcut.png';
      a.click();
      URL.revokeObjectURL(a.href);
    });
    toast('已导出图片');
  }
  function download(filename, text, mime) {
    var blob = new Blob([text], { type: mime + ';charset=utf-8' });
    var a = document.createElement('a');
    a.href = URL.createObjectURL(blob);
    a.download = filename;
    a.click();
    URL.revokeObjectURL(a.href);
  }

  /* ---------- 收藏按钮状态 ---------- */
  function syncFavBtn(name, btn) {
    var favs = getList('qk_favs');
    var on = favs.indexOf(name) >= 0;
    btn.textContent = on ? '★ 已收藏' : '☆ 加入收藏';
  }

  /* ---------- 软件详情页初始化 ---------- */
  var dataEl = $('#sw-data');
  if (dataEl) {
    var app = JSON.parse(dataEl.textContent);
    var panel = $('#hk-panel');
    renderHotkeyPanel(panel, app);
    var name = app.name;
    var favBtn = $('#btnFav');
    syncFavBtn(name, favBtn);
    favBtn.addEventListener('click', function () {
      if (QK.toggleFav(name)) { toast('收藏状态已更新'); }
      syncFavBtn(name, favBtn);
    });
    $('#btnCompare').addEventListener('click', function () {
      if (QK.toggleCompare(name)) { toast('对比队列已更新'); window.location.href = 'index.php?p=compare'; }
    });
    $('#btnCopyAll').addEventListener('click', function () { copyText(allText(panel._app, panel._state)); });
    $('#btnExportMd').addEventListener('click', function () { exportMarkdown(panel._app, panel._state); });
    $('#btnExportPng').addEventListener('click', function () { exportPng(panel._app, panel._state); });
  }

  /* ---------- 卡片收藏按钮（首页/分类/搜索） ---------- */
  $$('.fav-btn').forEach(function (btn) {
    var name = btn.dataset.fav;
    if (getList('qk_favs').indexOf(name) >= 0) btn.textContent = '★ 已收藏';
    btn.addEventListener('click', function (ev) {
      ev.preventDefault();
      ev.stopPropagation();
      QK.toggleFav(name);
      btn.textContent = getList('qk_favs').indexOf(name) >= 0 ? '★ 已收藏' : '☆ 收藏';
      toast('收藏状态已更新');
    });
  });

  /* ---------- 收藏页 ---------- */
  var favList = $('#favList');
  if (favList) {
    $('#btnClearFavs').addEventListener('click', function () {
      if (confirm('确定清空全部收藏？')) {
        setList('qk_favs', []);
        renderFavs();
        toast('已清空收藏');
      }
    });
    function renderFavs() {
      var favs = getList('qk_favs');
      if (!favs.length) {
        favList.innerHTML = '<p class="muted">还没有收藏，去软件详情页点击“加入收藏”吧。</p>';
        return;
      }
      favList.innerHTML = favs.map(function (n) {
        return '<div class="card" data-name="' + escapeHtml(n) + '"><h3><a href="index.php?p=software&s=' + encodeURIComponent(n) + '">' + escapeHtml(n) + '</a></h3>'
          + '<button class="btn sm ghost rm-fav" data-name="' + escapeHtml(n) + '">取消收藏</button></div>';
      }).join('');
      $$('.rm-fav', favList).forEach(function (btn) {
        btn.addEventListener('click', function () {
          QK.toggleFav(btn.dataset.name);
          renderFavs();
        });
      });
      loadAppsInto(favs, favList, function (app, host) {
        var div = host.querySelector('.card[data-name="' + cssEscape(app.name) + '"]');
        if (!div) return;
        div.innerHTML = '<h3><a href="index.php?p=software&s=' + encodeURIComponent(app.name) + '">' + escapeHtml(app.name) + '</a></h3>'
          + '<p class="muted">' + escapeHtml(app.category) + ' · ' + app.entries.length + ' 套平台/版本</p>'
          + '<button class="btn sm ghost rm-fav" data-name="' + escapeHtml(app.name) + '">取消收藏</button>';
        $('.rm-fav', div).addEventListener('click', function () { QK.toggleFav(app.name); renderFavs(); });
      });
    }
    renderFavs();
  }

  /* ---------- 对比页 ---------- */
  var cmpGrid = $('#cmpGrid');
  if (cmpGrid) {
    var sel = $('#cmpSelect');
    $('#btnClearCompare').addEventListener('click', function () {
      setList('qk_compare', []);
      renderCompare();
      toast('已清空对比队列');
    });
    sel.addEventListener('change', function () {
      if (!sel.value) return;
      if (QK.toggleCompare(sel.value)) renderCompare();
      sel.value = '';
    });
    function renderCompare() {
      var list = getList('qk_compare');
      /* 填充下拉选项：拉取全部软件名 */
      fetch('index.php?p=api&list=1').then(function (r) { return r.json(); }).then(function (all) {
        var inQueue = list;
        sel.innerHTML = '<option value="">选择软件加入对比…</option>' + all.map(function (n) {
          return '<option value="' + escapeHtml(n) + '">' + escapeHtml(n) + '</option>';
        }).join('');
      });
      if (!list.length) {
        cmpGrid.innerHTML = '<p class="muted">对比队列为空。请在软件详情页点击“加入对比”。</p>';
        return;
      }
      cmpGrid.innerHTML = list.map(function (n) {
        return '<div class="card cmp-col" data-name="' + escapeHtml(n) + '"><p class="muted">加载中…</p></div>';
      }).join('');
      loadAppsInto(list, cmpGrid, renderCompareColumn);
    }
    function renderCompareColumn(app, host) {
      var div = host.querySelector('.cmp-col[data-name="' + cssEscape(app.name) + '"]');
      if (!div) return;
      div.innerHTML = '<div class="cmp-head"><h3>' + escapeHtml(app.name) + '</h3>'
        + '<button class="btn sm ghost rm-cmp" data-name="' + escapeHtml(app.name) + '">移除</button></div>'
        + '<div class="cmp-panel"></div>';
      $('.rm-cmp', div).addEventListener('click', function () { QK.toggleCompare(app.name); renderCompare(); });
      var panelDiv = $('.cmp-panel', div);
      renderHotkeyPanel(panelDiv, app);
    }
    renderCompare();
  }

  /* ---------- 从 API 批量加载软件数据 ---------- */
  function loadAppsInto(names, host, cb) {
    names.forEach(function (n) {
      fetch('index.php?p=api&name=' + encodeURIComponent(n))
        .then(function (r) { return r.json(); })
        .then(function (app) { cb(app, host); })
        .catch(function () { toast('加载失败：' + n); });
    });
  }
  function cssEscape(s) {
    return (window.CSS && CSS.escape) ? CSS.escape(s) : s.replace(/["\\]/g, '\\$&');
  }

  /* ---------- 热门排行实时刷新 ---------- */
  var topRow = $('#topRow');
  if (topRow) {
    var renderTop = function (data) {
      var list = (data && data.top) || [];
      if (!list.length) return;
      topRow.innerHTML = list.map(function (t) {
        var cls = t.rank <= 3 ? 'r' + t.rank : 'n';
        return '<a class="top-card" href="' + t.url + '">'
          + '<span class="top-rank ' + cls + '">' + t.rank + '</span>'
          + '<span class="top-name" title="' + escapeHtml(t.name) + '">' + escapeHtml(t.name) + '</span>'
          + '<span class="top-meta">' + escapeHtml(t.category) + '</span>'
          + '<span class="top-visits">' + t.visits + ' 次访问</span>'
          + '</a>';
      }).join('');
      var tu = $('#statUv'), ttu = $('#statTotalUv'), td = $('#statToday');
      if (tu) tu.textContent = data.today_uv;
      if (ttu) ttu.textContent = data.total_uv;
      if (td) td.textContent = data.today;
    };
    var refreshTop = function () {
      fetch('index.php?p=api&top=8', { cache: 'no-store' })
        .then(function (r) { return r.json(); })
        .then(renderTop)
        .catch(function () {});
    };
    setInterval(refreshTop, 15000);
    document.addEventListener('visibilitychange', function () {
      if (!document.hidden) refreshTop();
    });
  }
})();
