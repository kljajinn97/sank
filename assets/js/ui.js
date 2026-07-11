/* ============================================================
   SANK UI KIT — naši toastovi, confirm/prompt, loading/odobreno
   Bez Chrome alert/confirm/prompt.
   ============================================================ */
(function () {
  function el(tag, cls, html) { var e = document.createElement(tag); if (cls) e.className = cls; if (html != null) e.innerHTML = html; return e; }
  function esc(s) { return String(s == null ? '' : s).replace(/[&<>"]/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]; }); }

  var SVG = {
    ok:    '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>',
    err:   '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18M6 6l12 12"/></svg>',
    info:  '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4M12 8h.01"/></svg>',
    warn:  '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.3 3.6 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.6a2 2 0 0 0-3.4 0z"/><path d="M12 9v4M12 17h.01"/></svg>',
    q:     '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M9.1 9a3 3 0 0 1 5.8 1c0 2-3 3-3 3M12 17h.01"/></svg>',
    check: '<svg viewBox="0 0 52 52"><circle class="uk-check__c" cx="26" cy="26" r="23" fill="none"/><path class="uk-check__p" fill="none" d="M15 27l7 7 15-15"/></svg>'
  };

  function ensureWrap() {
    var w = document.getElementById('ukToasts');
    if (!w) { w = el('div', 'uk-toasts'); w.id = 'ukToasts'; document.body.appendChild(w); }
    return w;
  }

  function toast(msg, type, ms) {
    type = type || 'info'; var ic = type === 'success' ? SVG.ok : type === 'error' ? SVG.err : SVG.info;
    var w = ensureWrap();
    var t = el('div', 'uk-toast uk-toast--' + type, '<span class="uk-toast__ic">' + ic + '</span><span class="uk-toast__msg">' + esc(msg) + '</span>');
    w.appendChild(t);
    requestAnimationFrame(function () { t.classList.add('in'); });
    setTimeout(function () { t.classList.add('out'); setTimeout(function () { t.remove(); }, 320); }, ms || 3400);
  }

  function makeOverlay() {
    var o = el('div', 'uk-overlay');
    document.body.appendChild(o);
    requestAnimationFrame(function () { o.classList.add('in'); });
    return o;
  }
  function shut(o) { o.classList.remove('in'); setTimeout(function () { o.remove(); }, 220); }

  function confirmBox(msg, opts) {
    opts = opts || {};
    return new Promise(function (res) {
      var o = makeOverlay();
      var danger = !!opts.danger;
      var d = el('div', 'uk-dialog');
      d.innerHTML =
        '<div class="uk-dialog__ic ' + (danger ? 'is-danger' : 'is-brand') + '">' + (danger ? SVG.warn : SVG.q) + '</div>' +
        '<h3 class="uk-dialog__title">' + esc(opts.title || 'Potvrda') + '</h3>' +
        '<p class="uk-dialog__text">' + esc(msg) + '</p>' +
        '<div class="uk-dialog__actions">' +
          '<button type="button" class="btn btn--ghost" data-no>' + esc(opts.cancel || 'Otkaži') + '</button>' +
          '<button type="button" class="btn ' + (danger ? 'btn--danger' : 'btn--primary') + '" data-yes>' + esc(opts.ok || 'Potvrdi') + '</button>' +
        '</div>';
      o.appendChild(d);
      requestAnimationFrame(function () { d.classList.add('in'); });
      function fin(v) { shut(o); res(v); }
      d.querySelector('[data-yes]').onclick = function () { fin(true); };
      d.querySelector('[data-no]').onclick = function () { fin(false); };
      o.addEventListener('click', function (e) { if (e.target === o) fin(false); });
      document.addEventListener('keydown', function onk(e) { if (e.key === 'Escape') { document.removeEventListener('keydown', onk); fin(false); } });
    });
  }

  function promptBox(msg, opts) {
    opts = opts || {};
    return new Promise(function (res) {
      var o = makeOverlay();
      var d = el('div', 'uk-dialog');
      d.innerHTML =
        '<h3 class="uk-dialog__title">' + esc(opts.title || 'Unos') + '</h3>' +
        '<p class="uk-dialog__text">' + esc(msg) + '</p>' +
        '<input class="input uk-dialog__input" id="ukPromptInp" placeholder="' + esc(opts.placeholder || '') + '" value="' + esc(opts.value || '') + '">' +
        '<div class="uk-dialog__actions">' +
          '<button type="button" class="btn btn--ghost" data-no>Otkaži</button>' +
          '<button type="button" class="btn btn--primary" data-yes>' + esc(opts.ok || 'Potvrdi') + '</button>' +
        '</div>';
      o.appendChild(d);
      requestAnimationFrame(function () { d.classList.add('in'); });
      var inp = d.querySelector('#ukPromptInp');
      setTimeout(function () { inp.focus(); inp.select(); }, 60);
      function ok() { var v = inp.value.trim(); shut(o); res(v || null); }
      d.querySelector('[data-yes]').onclick = ok;
      d.querySelector('[data-no]').onclick = function () { shut(o); res(null); };
      inp.addEventListener('keydown', function (e) { if (e.key === 'Enter') ok(); if (e.key === 'Escape') { shut(o); res(null); } });
      o.addEventListener('click', function (e) { if (e.target === o) { shut(o); res(null); } });
    });
  }

  function loading(msg) {
    var o = makeOverlay(); o.classList.add('uk-overlay--busy');
    var box = el('div', 'uk-loader', '<div class="uk-spinner"></div><div class="uk-loader__msg">' + esc(msg || 'Sačekaj…') + '</div>');
    o.appendChild(box);
    return {
      done: function (m) {
        box.innerHTML = '<div class="uk-check">' + SVG.check + '</div><div class="uk-loader__msg">' + esc(m || 'Gotovo') + '</div>';
        setTimeout(function () { shut(o); }, 950);
      },
      fail: function (m) {
        box.innerHTML = '<div class="uk-check is-fail">' + SVG.err + '</div><div class="uk-loader__msg">' + esc(m || 'Greška') + '</div>';
        setTimeout(function () { shut(o); }, 1400);
      },
      close: function () { shut(o); }
    };
  }

  window.SankUI = { toast: toast, confirm: confirmBox, prompt: promptBox, loading: loading };

  /* Pomoćnici za forme (bez Chrome confirm/prompt) */
  window.ukConfirmSubmit = function (form, msg, opts) {
    confirmBox(msg, opts).then(function (ok) { if (ok) { loading((opts && opts.busy) || 'Obrada…'); form.submit(); } });
    return false;
  };
  window.ukPromptSubmit = function (form, field, msg, opts) {
    promptBox(msg, opts).then(function (v) { if (v) { form[field].value = v; loading((opts && opts.busy) || 'Obrada…'); form.submit(); } });
    return false;
  };
})();
