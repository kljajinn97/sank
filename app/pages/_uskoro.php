<?php
/**
 * Privremena „u pripremi“ stranica.
 * Uključuje je konkretna stranica preko $uskoro_naslov / $uskoro_opis.
 */
require_login();
require __DIR__ . '/../partials/layout_top.php';
?>
<div class="page-head"><div><h1><?= e($uskoro_naslov ?? 'U pripremi') ?></h1><p><?= e($uskoro_opis ?? '') ?></p></div></div>
<div class="card"><div class="card__body">
  <div class="empty">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2v4M12 18v4M4.9 4.9l2.8 2.8M16.3 16.3l2.8 2.8M2 12h4M18 12h4M4.9 19.1l2.8-2.8M16.3 7.7l2.8-2.8"/></svg>
    <h3>Modul stiže uskoro</h3>
    <p>Ovaj deo sistema gradimo sledeći. Reci mi kada želiš da ga uključimo.</p>
  </div>
</div></div>
<?php require __DIR__ . '/../partials/layout_bottom.php'; ?>
