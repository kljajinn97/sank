<?php
/** POS uređaji i aktivacioni kodovi */
require_role(['vlasnik','menadzer']);
$lid = current_lokal_id();
$uid = current_user()['id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $akcija = $_POST['akcija'] ?? '';

    if ($akcija === 'gen_kod') {
        do {
            $kod = 'WTR-' . strtoupper(bin2hex(random_bytes(2))) . '-' . strtoupper(bin2hex(random_bytes(2)));
        } while (db_val('SELECT COUNT(*) FROM aktivacioni_kodovi WHERE kod=?', [$kod]) > 0);
        db_run('INSERT INTO aktivacioni_kodovi (lokal_id,kod,created_by) VALUES (?,?,?)', [$lid,$kod,$uid]);
        flash('success','Aktivacioni kod je generisan: '.$kod);
        redirect(url('uredjaji'));
    }
    if ($akcija === 'del_kod') {
        db_run('DELETE FROM aktivacioni_kodovi WHERE id=? AND lokal_id=? AND iskoriscen=0', [(int)$_POST['id'],$lid]);
        redirect(url('uredjaji'));
    }
    if ($akcija === 'toggle_uredjaj') {
        $u = db_row('SELECT * FROM pos_uredjaji WHERE id=? AND lokal_id=?', [(int)$_POST['id'],$lid]);
        if ($u) { $novi = $u['status']==='aktivan'?'blokiran':'aktivan';
            db_run('UPDATE pos_uredjaji SET status=? WHERE id=?', [$novi,$u['id']]);
            flash('success','Status uređaja je promenjen.'); }
        redirect(url('uredjaji'));
    }
    if ($akcija === 'del_uredjaj') {
        db_run('DELETE FROM pos_uredjaji WHERE id=? AND lokal_id=?', [(int)$_POST['id'],$lid]);
        flash('success','Uređaj je uklonjen (moraće ponovnu aktivaciju).');
        redirect(url('uredjaji'));
    }
    if ($akcija === 'rename_uredjaj') {
        db_run('UPDATE pos_uredjaji SET naziv=? WHERE id=? AND lokal_id=?', [post('naziv') ?: 'POS',(int)$_POST['id'],$lid]);
        redirect(url('uredjaji'));
    }
    if ($akcija === 'stampa') {
        db_run('UPDATE lokali SET auto_stampa=? WHERE id=?', [isset($_POST['auto_stampa'])?1:0, $lid]);
        flash('success','Podešavanje štampe je sačuvano.');
        redirect(url('uredjaji'));
    }
}
$autoStampa = (int)(db_val('SELECT auto_stampa FROM lokali WHERE id=?', [$lid]) ?: 0);

$kodovi = db_all('SELECT * FROM aktivacioni_kodovi WHERE lokal_id=? AND iskoriscen=0 ORDER BY created_at DESC', [$lid]);
$uredjaji = db_all('SELECT * FROM pos_uredjaji WHERE lokal_id=? ORDER BY aktiviran_at DESC', [$lid]);
$brPin = (int)db_val('SELECT COUNT(*) FROM korisnici WHERE lokal_id=? AND pin IS NOT NULL AND status="aktivan"', [$lid]);

$page_title = 'POS uređaji';
$active = 'uredjaji';
require __DIR__ . '/../partials/layout_top.php';
?>
<div class="page-head">
  <div><h1>POS uređaji</h1><p>Aktivacioni kodovi i povezani POS terminali tvog lokala.</p></div>
  <form method="post" style="margin:0"><?= csrf_field() ?><input type="hidden" name="akcija" value="gen_kod">
    <button class="btn btn--primary">＋ Generiši aktivacioni kod</button></form>
</div>

<div class="card mb-2" style="border-color:var(--info)"><div class="card__body">
  <strong>Kako aktivirati POS?</strong>
  <ol class="muted" style="margin:8px 0 0 18px;font-size:.9rem;line-height:1.7">
    <li>Generiši aktivacioni kod (dugme gore).</li>
    <li>Na uređaju u lokalu otvori <strong><?= e($_SERVER['HTTP_HOST'] ?? 'tvoj-domen') ?>/kasa</strong> i unesi kod.</li>
    <li>Radnici se prijavljuju svojim <strong>PIN-om</strong> (postavi ga u „Zaposleni"). Trenutno <?= $brPin ?> radnika ima PIN.</li>
    <li><strong>Kuhinjski ekran (KDS):</strong> na tabletu u kuhinji aktiviraj uređaj pa otvori <strong><?= e($_SERVER['HTTP_HOST'] ?? 'tvoj-domen') ?>/kds</strong> — porudžbine stižu uživo (zvuk + tajmer), kuvar klikne „Spremno".</li>
  </ol>
</div></div>

<div class="grid-2">
  <div class="card">
    <div class="card__head"><div class="card__title">Neiskorišćeni kodovi</div></div>
    <div class="table-wrap"><table class="table">
      <thead><tr><th>Kod</th><th>Kreiran</th><th></th></tr></thead>
      <tbody>
      <?php if (!$kodovi): ?><tr><td colspan="3"><div class="empty">Nema aktivnih kodova. Generiši novi.</div></td></tr>
      <?php else: foreach ($kodovi as $k): ?>
        <tr>
          <td><strong style="font-family:monospace;font-size:1rem;letter-spacing:1px"><?= e($k['kod']) ?></strong></td>
          <td class="muted"><?= datum($k['created_at']) ?></td>
          <td class="text-right">
            <button class="btn btn--ghost btn--sm" onclick="navigator.clipboard&&navigator.clipboard.writeText('<?= e($k['kod']) ?>');this.textContent='Kopirano ✓'">Kopiraj</button>
            <form method="post" style="display:inline" onsubmit="return ukConfirmSubmit(this,'Obrisati kod?',{danger:true,ok:'Obriši'})"><?= csrf_field() ?><input type="hidden" name="akcija" value="del_kod"><input type="hidden" name="id" value="<?= $k['id'] ?>">
              <button class="btn btn--ghost btn--sm" style="color:var(--danger)">×</button></form>
          </td>
        </tr>
      <?php endforeach; endif; ?>
      </tbody>
    </table></div>
  </div>

  <div class="card">
    <div class="card__head"><div class="card__title">Povezani uređaji</div></div>
    <div class="table-wrap"><table class="table">
      <thead><tr><th>Uređaj</th><th>Status</th><th>Poslednja aktivnost</th><th></th></tr></thead>
      <tbody>
      <?php if (!$uredjaji): ?><tr><td colspan="4"><div class="empty">Nijedan uređaj još nije aktiviran.</div></td></tr>
      <?php else: foreach ($uredjaji as $u): ?>
        <tr>
          <td><strong><?= e($u['naziv']) ?></strong><div class="muted" style="font-size:.78rem">aktiviran <?= datum($u['aktiviran_at']) ?></div></td>
          <td><span class="badge badge--<?= $u['status']==='aktivan'?'ok':'danger' ?>"><?= e(ucfirst($u['status'])) ?></span></td>
          <td class="muted"><?= $u['poslednja_aktivnost'] ? datum($u['poslednja_aktivnost']) : 'nikad' ?></td>
          <td class="text-right" style="white-space:nowrap">
            <form method="post" style="display:inline"><?= csrf_field() ?><input type="hidden" name="akcija" value="toggle_uredjaj"><input type="hidden" name="id" value="<?= $u['id'] ?>">
              <button class="btn btn--ghost btn--sm"><?= $u['status']==='aktivan'?'Blokiraj':'Odblokiraj' ?></button></form>
            <form method="post" style="display:inline" onsubmit="return ukConfirmSubmit(this,'Ukloniti uređaj? Tražiće ponovnu aktivaciju.',{danger:true,ok:'Obriši'})"><?= csrf_field() ?><input type="hidden" name="akcija" value="del_uredjaj"><input type="hidden" name="id" value="<?= $u['id'] ?>">
              <button class="btn btn--ghost btn--sm" style="color:var(--danger)">×</button></form>
          </td>
        </tr>
      <?php endforeach; endif; ?>
      </tbody>
    </table></div>
  </div>
</div>

<div class="card mt-2">
  <div class="card__head"><div class="card__title"><?= ico('print',17) ?> Štampa računa (termalni štampač)</div>
    <?php if($autoStampa):?><span class="badge badge--ok">Auto-štampa uključena</span><?php endif;?></div>
  <div class="card__body">
    <form method="post" action="<?= url('uredjaji') ?>">
      <?= csrf_field() ?><input type="hidden" name="akcija" value="stampa">
      <label class="flex items-center gap-2" style="cursor:pointer;margin-bottom:14px">
        <input type="checkbox" name="auto_stampa" value="1" <?= $autoStampa?'checked':'' ?> onchange="this.form.submit()">
        <span><strong>Automatski štampaj račun posle naplate</strong> (otvara prozor za štampu)</span>
      </label>
    </form>
    <div class="flash flash--info" style="margin:0">
      <div>
        <strong>Za štampu BEZ pitanja (tiho, direktno na termalni štampač):</strong>
        <ol class="muted" style="margin:8px 0 0 18px;font-size:.88rem;line-height:1.7">
          <li>Instaliraj drajver termalnog štampača na uređaj i postavi ga kao <strong>podrazumevani</strong> (širina papira 80mm).</li>
          <li>Pokreći POS u Chrome-u sa prečicom koja ima parametar: <code>--kiosk-printing</code><br>
              <span class="muted" style="font-size:.82rem">npr. cilj prečice: <code>chrome.exe --kiosk --kiosk-printing https://<?= e($_SERVER['HTTP_HOST'] ?? 'tvoj-domen') ?>/kasa</code></span></li>
          <li>Svaka štampa (račun, predračun, nalog za pripremu) ide odmah na štampač — bez dijaloga.</li>
        </ol>
      </div>
    </div>
  </div>
</div>

<?php require __DIR__ . '/../partials/layout_bottom.php'; ?>
