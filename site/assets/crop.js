/* 后台二维码：上传即用（默认不裁剪），需要时再手动裁剪为 400×400 */
(function () {
  'use strict';
  var mask = document.getElementById('cropMask');
  if (!mask) return;
  var stage = document.getElementById('cropStage');
  var canvas = document.getElementById('cropCanvas');
  var box = document.getElementById('cropBox');
  var range = document.getElementById('cropSize');
  var ctx = canvas.getContext('2d');

  var img = null, scale = 1, dispW = 0, dispH = 0;
  var size = 160, x = 0, y = 0, slot = '', dragging = false;
  var origin = {};   /* 每个槽位的原图 dataURL，供再次裁剪 */

  function clamp() {
    var minDim = Math.min(dispW, dispH);
    size = Math.max(40, Math.min(size, minDim));
    x = Math.max(0, Math.min(x, dispW - size));
    y = Math.max(0, Math.min(y, dispH - size));
  }

  function layout() {
    clamp();
    box.style.left = x + 'px';
    box.style.top = y + 'px';
    box.style.width = size + 'px';
    box.style.height = size + 'px';
    range.value = String(Math.round(size / Math.min(dispW, dispH) * 100));
  }

  /* 写入隐藏字段 + 更新预览 + 显示裁剪按钮 */
  function apply(slotName, dataUrl) {
    var hidden = document.getElementById('img_' + slotName);
    if (hidden) hidden.value = dataUrl;
    var prev = document.querySelector('.qr-preview[data-slot="' + slotName + '"]');
    if (prev) prev.innerHTML = '<img class="qr-img sm" src="' + dataUrl + '" alt="二维码">';
    var btn = document.querySelector('[data-crop-btn="' + slotName + '"]');
    if (btn) btn.hidden = false;
  }

  /* 读取文件（过大自动压缩到最长边 900px） */
  function readFile(file, slotName) {
    if (!/^image\//.test(file.type)) {
      alert('仅支持 PNG / JPG / WEBP 图片。');
      return;
    }
    var reader = new FileReader();
    reader.onload = function () {
      var src = String(reader.result);
      var im = new Image();
      im.onload = function () {
        var needCompress = file.size > 700 * 1024 || Math.max(im.width, im.height) > 1200;
        if (!needCompress) {
          origin[slotName] = src;
          apply(slotName, src);
          return;
        }
        var k = Math.min(1, 900 / Math.max(im.width, im.height));
        var c = document.createElement('canvas');
        c.width = Math.round(im.width * k);
        c.height = Math.round(im.height * k);
        c.getContext('2d').drawImage(im, 0, 0, c.width, c.height);
        var out = c.toDataURL('image/jpeg', 0.92);
        origin[slotName] = out;
        apply(slotName, out);
      };
      im.onerror = function () { alert('图片读取失败，请换一张图片。'); };
      im.src = src;
    };
    reader.readAsDataURL(file);
  }

  document.querySelectorAll('input[data-pick]').forEach(function (input) {
    input.addEventListener('change', function () {
      var f = input.files && input.files[0];
      if (f) readFile(f, input.getAttribute('data-pick'));
      input.value = '';
    });
  });

  /* 打开裁剪弹层 */
  function openCrop(slotName) {
    var src = origin[slotName] || (document.querySelector('.qr-preview[data-slot="' + slotName + '"] img') || {}).src;
    if (!src) { alert('请先上传图片。'); return; }
    slot = slotName;
    var im = new Image();
    im.onload = function () {
      img = im;
      var maxW = Math.min(520, window.innerWidth - 80);
      var maxH = Math.min(340, window.innerHeight - 320);
      scale = Math.min(maxW / im.width, maxH / im.height, 1);
      dispW = Math.max(60, Math.round(im.width * scale));
      dispH = Math.max(60, Math.round(im.height * scale));
      canvas.width = dispW;
      canvas.height = dispH;
      canvas.style.width = dispW + 'px';
      canvas.style.height = dispH + 'px';
      stage.style.width = dispW + 'px';
      stage.style.height = dispH + 'px';
      ctx.drawImage(im, 0, 0, dispW, dispH);
      size = Math.round(Math.min(dispW, dispH) * 0.7);
      x = Math.round((dispW - size) / 2);
      y = Math.round((dispH - size) / 2);
      layout();
      mask.hidden = false;
    };
    im.onerror = function () { alert('图片读取失败。'); };
    im.src = src;
  }

  document.querySelectorAll('[data-crop-btn]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      openCrop(btn.getAttribute('data-crop-btn'));
    });
  });

  /* 清除二维码：标记 __CLEAR__，保存后服务端删除文件并置空 */
  document.querySelectorAll('[data-clear-btn]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var slotName = btn.getAttribute('data-clear-btn');
      var hidden = document.getElementById('img_' + slotName);
      if (hidden) hidden.value = '__CLEAR__';
      delete origin[slotName];
      var prev = document.querySelector('.qr-preview[data-slot="' + slotName + '"]');
      if (prev) prev.innerHTML = '<span class="muted sm-text">未上传</span>';
      var crop = document.querySelector('[data-crop-btn="' + slotName + '"]');
      if (crop) crop.hidden = true;
      btn.hidden = true;
    });
  });

  /* 拖动选框 */
  function startDrag(e) {
    e.preventDefault();
    dragging = true;
    var point = e.touches ? e.touches[0] : e;
    var sx = point.clientX, sy = point.clientY, ox = x, oy = y;
    function move(ev) {
      if (!dragging) return;
      var p = ev.touches ? ev.touches[0] : ev;
      x = ox + (p.clientX - sx);
      y = oy + (p.clientY - sy);
      layout();
    }
    function up() {
      dragging = false;
      document.removeEventListener('mousemove', move);
      document.removeEventListener('mouseup', up);
      document.removeEventListener('touchmove', move);
      document.removeEventListener('touchend', up);
    }
    document.addEventListener('mousemove', move);
    document.addEventListener('mouseup', up);
    document.addEventListener('touchmove', move, { passive: false });
    document.addEventListener('touchend', up);
  }
  box.addEventListener('mousedown', startDrag);
  box.addEventListener('touchstart', startDrag, { passive: false });

  range.addEventListener('input', function () {
    var minDim = Math.min(dispW, dispH);
    var newSize = Math.round(minDim * (parseInt(range.value, 10) / 100));
    var cx = x + size / 2, cy = y + size / 2;
    size = newSize;
    x = Math.round(cx - size / 2);
    y = Math.round(cy - size / 2);
    layout();
  });

  document.getElementById('cropCancel').addEventListener('click', function () {
    mask.hidden = true;
  });

  document.getElementById('cropOk').addEventListener('click', function () {
    if (!img || !slot) return;
    var sw = size / scale;
    var out = document.createElement('canvas');
    out.width = 400;
    out.height = 400;
    out.getContext('2d').drawImage(img, x / scale, y / scale, sw, sw, 0, 0, 400, 400);
    apply(slot, out.toDataURL('image/png'));
    mask.hidden = true;
  });
})();
