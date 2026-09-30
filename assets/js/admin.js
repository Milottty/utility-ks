(function () {
  'use strict';

  /* ---- modal konfirmimi për DELETE ---- */
  var modal = document.getElementById('confirm-modal');
  var pending = null;
  if (modal) {
    document.addEventListener('submit', function (ev) {
      var f = ev.target;
      if (f.dataset && f.dataset.confirm && !f.dataset.ok) {
        ev.preventDefault();
        pending = f;
        document.getElementById('confirm-msg').textContent = f.dataset.confirm;
        modal.hidden = false;
        document.getElementById('confirm-cancel').focus();
      }
    });
    document.getElementById('confirm-cancel').addEventListener('click', function () { modal.hidden = true; pending = null; });
    modal.addEventListener('click', function (e) { if (e.target === modal) { modal.hidden = true; pending = null; } });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') { modal.hidden = true; pending = null; } });
    document.getElementById('confirm-ok').addEventListener('click', function () {
      if (pending) { pending.dataset.ok = '1'; pending.submit(); }
    });
  }

  /* ---- live search (debounce) ---- */
  var ls = document.getElementById('live-search');
  if (ls && ls.form) {
    var t;
    ls.addEventListener('input', function () {
      clearTimeout(t);
      t = setTimeout(function () { ls.form.submit(); }, 450);
    });
    if (ls.value) { var v = ls.value; ls.focus(); ls.value = ''; ls.value = v; }
  }

  /* ---- renditja e fotove ---- */
  var list = document.getElementById('imglist');
  if (list) {
    list.addEventListener('click', function (e) {
      var b = e.target.closest('.mv');
      if (!b) return;
      var li = b.closest('li');
      if (b.dataset.dir === '-1' && li.previousElementSibling) list.insertBefore(li, li.previousElementSibling);
      if (b.dataset.dir === '1' && li.nextElementSibling) list.insertBefore(li.nextElementSibling, li);
    });
  }

  /* ---- subkategoritë sipas kategorisë ---- */
  var cat = document.getElementById('cat-select'), sub = document.getElementById('sub-select');
  if (cat && sub) {
    var apply = function () {
      Array.prototype.forEach.call(sub.options, function (o) {
        if (!o.value) return;
        var show = !cat.value || o.dataset.parent === cat.value;
        o.hidden = !show; o.disabled = !show;
        if (!show && o.selected) sub.value = '';
      });
    };
    cat.addEventListener('change', apply); apply();
  }

  /* ---- grafiku i shitjeve ---- */
  var canvas = document.getElementById('sales-chart');
  if (canvas && window.SALES_API) {
    var tip = document.getElementById('chart-tip');
    var totalEl = document.getElementById('chart-total');
    var data = null, points = [];

    function money(n) { return '€' + Number(n).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); }

    function draw() {
      if (!data) return;
      var dpr = window.devicePixelRatio || 1;
      var W = canvas.clientWidth, H = 280;
      canvas.width = W * dpr; canvas.height = H * dpr;
      var c = canvas.getContext('2d');
      c.scale(dpr, dpr);
      c.clearRect(0, 0, W, H);
      var padL = 56, padR = 44, padT = 14, padB = 34;
      var vals = data.values, n = vals.length;
      var max = Math.max.apply(null, vals.concat([0]));
      var top;
      if (max <= 0) { top = 100; }
      else {
        var step = Math.pow(10, Math.floor(Math.log10(max)));
        top = Math.ceil(max / step) * step; if (top < max * 1.05) top += step;
        if (top / max > 2) top = Math.ceil(max / (step / 2)) * (step / 2);
        if (top < 4) top = 4;
      }
      var x = function (i) { return padL + (n === 1 ? (W - padL - padR) / 2 : i * (W - padL - padR) / (n - 1)); };
      var y = function (v) { return padT + (H - padT - padB) * (1 - v / top); };

      c.font = '11px system-ui,sans-serif';
      c.strokeStyle = '#2a2a2a'; c.fillStyle = '#999'; c.lineWidth = 1;
      for (var g = 0; g <= 4; g++) {
        var gv = top * g / 4, gy = Math.round(y(gv)) + .5;
        c.beginPath(); c.moveTo(padL, gy); c.lineTo(W - padR, gy); c.stroke();
        c.textAlign = 'right'; c.fillText('€' + Math.round(gv), padL - 8, gy + 4);
      }
      var every = Math.ceil(n / 8);
      c.textAlign = 'center';
      data.labels.forEach(function (l, i) { if (i % every === 0 || i === n - 1) c.fillText(l, x(i), H - 12); });

      var grad = c.createLinearGradient(0, padT, 0, H - padB);
      grad.addColorStop(0, 'rgba(255,255,255,.22)'); grad.addColorStop(1, 'rgba(255,255,255,0)');
      c.beginPath();
      vals.forEach(function (v, i) { i ? c.lineTo(x(i), y(v)) : c.moveTo(x(i), y(v)); });
      c.lineTo(x(n - 1), y(0)); c.lineTo(x(0), y(0)); c.closePath(); c.fillStyle = grad; c.fill();

      c.beginPath(); c.strokeStyle = '#fff'; c.lineWidth = 2;
      vals.forEach(function (v, i) { i ? c.lineTo(x(i), y(v)) : c.moveTo(x(i), y(v)); });
      c.stroke();

      points = vals.map(function (v, i) { return { x: x(i), y: y(v), v: v, l: data.labels[i], o: data.orders[i] }; });
      c.fillStyle = '#fff';
      if (n <= 31) points.forEach(function (p) { c.beginPath(); c.arc(p.x, p.y, 3, 0, 7); c.fill(); });
    }

    canvas.addEventListener('mousemove', function (e) {
      if (!points.length) return;
      var r = canvas.getBoundingClientRect(), mx = e.clientX - r.left, best = points[0];
      points.forEach(function (p) { if (Math.abs(p.x - mx) < Math.abs(best.x - mx)) best = p; });
      tip.hidden = false; tip.style.left = best.x + 'px'; tip.style.top = best.y + 'px';
      tip.textContent = best.l + ' — ' + money(best.v) + ' (' + best.o + ' orders)';
    });
    canvas.addEventListener('mouseleave', function () { tip.hidden = true; });
    window.addEventListener('resize', draw);

    function load(range) {
      fetch(window.SALES_API + '?range=' + range, { credentials: 'same-origin' })
        .then(function (r) { return r.json(); })
        .then(function (d) {
          data = d; draw();
          totalEl.innerHTML = 'Total në këtë periudhë: <b>' + money(d.total) + '</b>';
        });
    }
    var tabs = document.querySelectorAll('#range-tabs button');
    tabs.forEach(function (b) {
      b.addEventListener('click', function () {
        tabs.forEach(function (x) { x.classList.remove('on'); });
        b.classList.add('on'); load(b.dataset.range);
      });
    });
    var active = document.querySelector('#range-tabs button.on');
    load(active ? active.dataset.range : '7d');
  }
})();
