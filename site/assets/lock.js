/* 前端防护：屏蔽开发者工具快捷键、右键菜单，并在检测到调试面板时提示关闭
   说明：浏览器端无法 100% 阻止查看源码（可禁用 JS / 使用其他客户端），此处为阻断与威慑措施 */
(function () {
  'use strict';

  /* 1. 屏蔽快捷键 */
  document.addEventListener('keydown', function (e) {
    var k = (e.key || '').toUpperCase();
    var blocked =
      e.key === 'F12' ||
      (e.ctrlKey && e.shiftKey && ['I', 'J', 'C', 'K'].indexOf(k) > -1) ||
      (e.metaKey && e.altKey && ['I', 'J', 'C', 'K'].indexOf(k) > -1) ||
      (e.ctrlKey && !e.shiftKey && ['U', 'S', 'P'].indexOf(k) > -1);
    if (blocked) {
      e.preventDefault();
      e.stopPropagation();
    }
  }, true);

  /* 2. 屏蔽右键菜单 */
  document.addEventListener('contextmenu', function (e) {
    e.preventDefault();
  });

  /* 3. 开发者工具检测（仅桌面端，阈值较大避免误伤） */
  var mask = null;
  function showMask() {
    if (mask || document.body.dataset.noLock === '1') return;
    mask = document.createElement('div');
    mask.className = 'devtools-mask';
    mask.innerHTML =
      '<div class="devtools-box"><h3>请关闭开发者工具</h3>' +
      '<p>为保护站点内容，本页面不支持调试与查看源码。关闭开发者工具后页面会自动恢复。</p></div>';
    document.body.appendChild(mask);
  }
  function hideMask() {
    if (mask) {
      mask.parentNode && mask.parentNode.removeChild(mask);
      mask = null;
    }
  }

  if (window.innerWidth > 900) {
    setInterval(function () {
      var w = window.outerWidth - window.innerWidth;
      var h = window.outerHeight - window.innerHeight;
      if (w > 200 || h > 220) {
        showMask();
      } else {
        hideMask();
      }
    }, 1500);
  }
})();
