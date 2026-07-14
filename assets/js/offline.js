/* ============================================================
   WAITER OFFLINE — katalog keš (IndexedDB), red offline računa,
   auto-sinhronizacija, indikator mreže.
   ============================================================ */
(function () {
  var DBN = 'waiter', VER = 1;

  function idb() {
    return new Promise(function (res, rej) {
      var r = indexedDB.open(DBN, VER);
      r.onupgradeneeded = function () {
        var d = r.result;
        if (!d.objectStoreNames.contains('kv')) d.createObjectStore('kv');
        if (!d.objectStoreNames.contains('queue')) d.createObjectStore('queue', { keyPath: 'uuid' });
      };
      r.onsuccess = function () { res(r.result); };
      r.onerror = function () { rej(r.error); };
    });
  }
  function tx(store, mode, fn) {
    return idb().then(function (d) {
      return new Promise(function (res, rej) {
        var t = d.transaction(store, mode), s = t.objectStore(store), out = fn(s);
        t.oncomplete = function () { res(out && out.result !== undefined ? out.result : undefined); };
        t.onerror = function () { rej(t.error); };
      });
    });
  }
  function kvSet(k, v) { return tx('kv', 'readwrite', function (s) { s.put(v, k); }); }
  function kvGet(k) {
    return idb().then(function (d) {
      return new Promise(function (res) {
        var q = d.transaction('kv').objectStore('kv').get(k);
        q.onsuccess = function () { res(q.result); }; q.onerror = function () { res(null); };
      });
    });
  }
  function qAll() {
    return idb().then(function (d) {
      return new Promise(function (res) {
        var q = d.transaction('queue').objectStore('queue').getAll();
        q.onsuccess = function () { res(q.result || []); }; q.onerror = function () { res([]); };
      });
    });
  }
  function qPut(r) { return tx('queue', 'readwrite', function (s) { s.put(r); }); }
  function qDel(uuid) { return tx('queue', 'readwrite', function (s) { s.delete(uuid); }); }

  // ---------- indikator ----------
  function updateDot(online, qn) {
    var d = document.getElementById('netDot');
    if (d) { d.classList.toggle('off', !online); d.title = online ? 'Na mreži' : 'Nema mreže'; }
    var b = document.getElementById('netQueue');
    if (b) { b.textContent = qn; b.style.display = qn > 0 ? '' : 'none'; }
    var q2 = document.getElementById('offQueueCount');
    if (q2) q2.textContent = qn;
  }
  function refreshDot() { qAll().then(function (q) { updateDot(navigator.onLine, q.length); }); }

  // ---------- katalog ----------
  function refreshKatalog() {
    if (!navigator.onLine) return Promise.resolve(null);
    return fetch('/possync?katalog=1', { cache: 'no-store' })
      .then(function (r) { if (!r.ok) throw 0; return r.json(); })
      .then(function (k) { if (k && k.artikli) return kvSet('katalog', k).then(function(){ return k; }); return null; })
      .catch(function () { return null; });
  }

  // ---------- sync ----------
  var syncing = false;
  function trySync() {
    if (syncing || !navigator.onLine) return Promise.resolve(0);
    syncing = true;
    return qAll().then(function (queue) {
      if (!queue.length) { syncing = false; refreshDot(); return 0; }
      return refreshKatalog().then(function (k) {          // svež CSRF + katalog
        return kvGet('katalog').then(function (kat) {
          var csrf = (k && k.csrf) || (kat && kat.csrf);
          if (!csrf) { syncing = false; return 0; }
          var body = new URLSearchParams({ _csrf: csrf, data: JSON.stringify({ racuni: queue }) });
          return fetch('/possync', { method: 'POST', headers: {'Content-Type':'application/x-www-form-urlencoded'}, body: body })
            .then(function (r) { if (!r.ok) throw 0; return r.json(); })
            .then(function (out) {
              var done = 0, ops = [];
              (out.rezultat || []).forEach(function (x) {
                if (x.status === 'ok' || x.status === 'duplikat') { ops.push(qDel(x.uuid)); if (x.status === 'ok') done++; }
              });
              return Promise.all(ops).then(function () {
                syncing = false; refreshDot();
                if (done > 0 && window.SankUI) SankUI.toast('Sinhronizovano ' + done + ' offline računa', 'success');
                return done;
              });
            });
        });
      }).catch(function () { syncing = false; refreshDot(); return 0; });
    });
  }

  function queueRacun(r) { return qPut(r).then(function () { refreshDot(); }); }

  window.WaiterOffline = {
    getKatalog: function () { return kvGet('katalog'); },
    refreshKatalog: refreshKatalog,
    queueRacun: queueRacun,
    getQueue: qAll,
    trySync: trySync,
    refreshDot: refreshDot,
    uuid: function () {
      if (window.crypto && crypto.randomUUID) return crypto.randomUUID();
      return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function (c) {
        var r = Math.random() * 16 | 0; return (c === 'x' ? r : (r & 0x3 | 0x8)).toString(16);
      });
    }
  };

  window.addEventListener('online', function () { refreshDot(); trySync(); });
  window.addEventListener('offline', refreshDot);
  document.addEventListener('DOMContentLoaded', function () {
    refreshDot();
    if (navigator.onLine) { refreshKatalog(); trySync(); }
  });
})();
