<?php
/** QR meni — podešavanje (vlasnik): link, QR kod, uključivanje */
require_role(['vlasnik','menadzer']);
require_modul('qrmeni');
$lid = current_lokal_id();
$lokal = db_row('SELECT * FROM lokali WHERE id=?', [$lid]);

// Obezbedi javni token
if (empty($lokal['javni_token'])) {
    $tok = strtolower(bin2hex(random_bytes(8)));
    db_run('UPDATE lokali SET javni_token=? WHERE id=?', [$tok,$lid]);
    $lokal['javni_token'] = $tok;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $akcija = $_POST['akcija'] ?? '';
    if ($akcija === 'toggle') {
        db_run('UPDATE lokali SET meni_aktivan=? WHERE id=?', [isset($_POST['aktivan'])?1:0, $lid]);
        flash('success','Sačuvano.');
        redirect(url('qrmeni'));
    }
    if ($akcija === 'novi_token') {
        db_run('UPDATE lokali SET javni_token=? WHERE id=?', [strtolower(bin2hex(random_bytes(8))),$lid]);
        flash('success','Novi link je generisan (stari više ne važi).');
        redirect(url('qrmeni'));
    }
}

$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS']!=='off') ? 'https' : 'http';
$base = $scheme.'://'.($_SERVER['HTTP_HOST'] ?? 'localhost');
$link = $base.'/meni?t='.$lokal['javni_token'];
$brArt = (int)db_val('SELECT COUNT(*) FROM artikli WHERE lokal_id=? AND aktivan=1 AND prodajna_cena>0', [$lid]);

$page_title = 'QR meni';
$active = 'qrmeni';
require __DIR__ . '/../partials/layout_top.php';
?>
<div class="page-head"><div><h1>QR meni</h1><p>Gost skenira QR na stolu i vidi tvoj brendiran meni.</p></div></div>

<div class="grid-2">
  <div class="card"><div class="card__body" style="text-align:center">
    <div id="qr" style="display:inline-block;padding:14px;background:#fff;border-radius:16px;box-shadow:var(--shadow-sm)"></div>
    <div class="help" id="qrFallback" style="margin-top:12px">Ako se QR ne prikaže, koristi link ispod.</div>
    <div style="margin-top:16px">
      <a class="btn btn--primary" href="<?= e($link) ?>" target="_blank"><?= ico('receipt',16) ?> Otvori meni</a>
      <button class="btn btn--ghost" onclick="window.print()"><?= ico('print',16) ?> Štampaj QR</button>
    </div>
    <div class="input" style="margin-top:14px;word-break:break-all;font-size:.85rem;text-align:center" onclick="navigator.clipboard&&navigator.clipboard.writeText('<?= e($link) ?>').then(function(){SankUI.toast('Link kopiran','success');})" title="Klikni da kopiraš"><?= e($link) ?></div>
  </div></div>

  <div class="card"><div class="card__head"><div class="card__title">Podešavanja</div></div>
    <div class="card__body">
      <form method="post" action="<?= url('qrmeni') ?>">
        <?= csrf_field() ?><input type="hidden" name="akcija" value="toggle">
        <label class="flex items-center gap-2" style="cursor:pointer;margin-bottom:14px">
          <input type="checkbox" name="aktivan" value="1" <?= $lokal['meni_aktivan']?'checked':'' ?> onchange="this.form.submit()">
          <span><strong>Meni je uključen</strong> (vidljiv gostima)</span>
        </label>
      </form>
      <p class="muted" style="font-size:.9rem">Prikazuje se <strong><?= $brArt ?></strong> artikala (aktivni sa cenom). Slike i opise dodaješ u <a href="<?= url('artikli') ?>">Artiklima</a>.</p>
      <hr style="border:none;border-top:1px solid var(--border);margin:16px 0">
      <p class="help">Novi link poništava stari QR (ako želiš da promeniš adresu):</p>
      <form method="post" action="<?= url('qrmeni') ?>" onsubmit="return ukConfirmSubmit(this,'Generisati novi link? Stari QR više neće raditi.',{danger:true,ok:'Generiši'})">
        <?= csrf_field() ?><input type="hidden" name="akcija" value="novi_token">
        <button class="btn btn--ghost btn--sm">Generiši novi link</button>
      </form>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/gh/davidshimjs/qrcodejs/qrcode.min.js"></script>
<script>
(function(){
  try {
    new QRCode(document.getElementById('qr'), { text: <?= json_encode($link) ?>, width: 220, height: 220, correctLevel: QRCode.CorrectLevel.M });
    var fb=document.getElementById('qrFallback'); if(fb) fb.style.display='none';
  } catch(e){}
})();
</script>

<?php require __DIR__ . '/../partials/layout_bottom.php'; ?>
