<?php
/** Kontrolna tabla — različita za super_admina i za lokal (prilagodljiva widgetima) */
require_login();
$u = current_user();

function stat_svg(string $k): string {
    $m = [
      'lokali'   => '<path d="M3 21h18M5 21V7l8-4v18M19 21V11l-6-4"/>',
      'korisnici'=> '<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8z"/>',
      'novac'    => '<path d="M12 1v22M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>',
      'artikli'  => '<path d="M20 7 12 3 4 7m16 0-8 4m8-4v10l-8 4m0-10L4 7"/>',
      'kalendar' => '<path d="M8 2v4M16 2v4M3 10h18M5 4h14a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2z"/>',
    ];
    return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">'.($m[$k]??'').'</svg>';
}

// Dostupni widgeti za lokal (ključ => [naziv, veličina])
$WIDGETS = [
  'pazar_danas'        => ['Pazar danas', 'stat'],
  'pazar_mesec'        => ['Pazar ovog meseca', 'stat'],
  'bilans'             => ['Bilans meseca', 'stat'],
  'neplacene_fakture'  => ['Neplaćene fakture', 'stat'],
  'neplaceni_troskovi' => ['Neplaćeni troškovi', 'stat'],
  'niska_zaliha'       => ['Niska zaliha', 'stat'],
  'grafikon_prometa'   => ['Grafikon dnevnog prometa', 'wide'],
  'poslednji_pazari'   => ['Poslednji uneti pazari', 'wide'],
];

// Čuvanje konfiguracije (pre bilo kakvog ispisa)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['akcija'] ?? '') === 'dashboard_config' && !is_super_admin()) {
    csrf_check();
    $arr = json_decode($_POST['config'] ?? '', true);
    if (!is_array($arr)) $arr = [];
    $arr = array_values(array_filter($arr, fn($k)=>isset($WIDGETS[$k])));
    db_run('UPDATE korisnici SET dashboard_config=? WHERE id=?', [json_encode($arr), $u['id']]);
    flash('success','Kontrolna tabla je prilagođena.');
    redirect(url('dashboard'));
}

$page_title = 'Kontrolna tabla';
$active = 'dashboard';
require __DIR__ . '/../partials/layout_top.php';

if (is_super_admin()):
    // ---- SUPER ADMIN PREGLED ----
    $brLokala   = (int)db()->query('SELECT COUNT(*) FROM lokali')->fetchColumn();
    $brAktivnih = (int)db()->query("SELECT COUNT(*) FROM lokali WHERE status='aktivan'")->fetchColumn();
    $brKorisnika= (int)db()->query("SELECT COUNT(*) FROM korisnici WHERE uloga<>'super_admin'")->fetchColumn();
    $isticu     = (int)db()->query("SELECT COUNT(*) FROM lokali WHERE pretplata_do IS NOT NULL AND pretplata_do <= DATE_ADD(CURDATE(), INTERVAL 7 DAY) AND pretplata_do >= CURDATE() AND status='aktivan'")->fetchColumn();
    $istekle    = (int)db()->query("SELECT COUNT(*) FROM lokali WHERE pretplata_do IS NOT NULL AND pretplata_do < CURDATE() AND status='aktivan'")->fetchColumn();
    $prometMesec= (float)db()->query("SELECT COALESCE(SUM(iznos),0) FROM pazar WHERE YEAR(datum)=YEAR(CURDATE()) AND MONTH(datum)=MONTH(CURDATE())")->fetchColumn();
    $brUredjaja = (int)db()->query("SELECT COUNT(*) FROM pos_uredjaji WHERE status='aktivan'")->fetchColumn();
    $noviLokali = db()->query('SELECT l.*, (SELECT COALESCE(SUM(iznos),0) FROM pazar p WHERE p.lokal_id=l.id AND YEAR(p.datum)=YEAR(CURDATE()) AND MONTH(p.datum)=MONTH(CURDATE())) AS promet FROM lokali l ORDER BY l.created_at DESC LIMIT 8')->fetchAll();
?>
  <div class="page-head">
    <div><h1>Zdravo, <?= e($u['ime']) ?></h1><p>Pregled celog sistema — svi lokali i nalozi.</p></div>
    <a class="btn btn--primary" href="<?= url('admin/lokali') ?>">+ Novi lokal</a>
  </div>
  <div class="stats mb-2">
    <div class="stat"><div class="stat__icon i-teal"><?= stat_svg('lokali') ?></div>
      <div class="stat__label">Ukupno lokala</div><div class="stat__value"><?= $brLokala ?></div>
      <div class="stat__delta up"><?= $brAktivnih ?> aktivnih</div></div>
    <div class="stat"><div class="stat__icon i-green"><?= stat_svg('novac') ?></div>
      <div class="stat__label">Promet svih lokala (mesec)</div><div class="stat__value in"><?= novac($prometMesec) ?></div></div>
    <div class="stat"><div class="stat__icon i-blue"><?= stat_svg('korisnici') ?></div>
      <div class="stat__label">Korisnika · POS uređaja</div><div class="stat__value"><?= $brKorisnika ?> · <?= $brUredjaja ?></div></div>
    <div class="stat"><div class="stat__icon <?= $istekle?'i-amber':'i-teal' ?>"><?= stat_svg('kalendar') ?></div>
      <div class="stat__label">Pretplate</div><div class="stat__value <?= $istekle?'out':'' ?>"><?= $istekle ?> isteklo</div>
      <div class="stat__delta"><?= $isticu ?> ističe za 7 dana</div></div>
  </div>
  <div class="card">
    <div class="card__head"><div class="card__title">Najnoviji lokali</div>
      <a class="btn btn--ghost btn--sm" href="<?= url('admin/lokali') ?>">Svi lokali →</a></div>
    <div class="table-wrap"><table class="table">
      <thead><tr><th>Lokal</th><th>Grad</th><th class="num">Promet (mesec)</th><th>Status</th><th>Pretplata do</th></tr></thead>
      <tbody>
      <?php if (!$noviLokali): ?><tr><td colspan="5"><div class="empty">Još nema lokala.</div></td></tr>
      <?php else: foreach ($noviLokali as $l): $istekla=$l['pretplata_do']&&strtotime($l['pretplata_do'])<strtotime('today'); ?>
        <tr><td><strong><?= e($l['naziv']) ?></strong><?php if($l['tip']):?><div class="muted" style="font-size:.8rem"><?= e($l['tip']) ?></div><?php endif;?></td>
          <td><?= e($l['grad'] ?? '—') ?></td>
          <td class="num"><?= novac($l['promet']) ?></td>
          <td><span class="badge badge--<?= $l['status']==='aktivan'?'ok':'danger' ?>"><?= e(ucfirst($l['status'])) ?></span></td>
          <td><?= $l['pretplata_do'] ? (($istekla?'<span class="out">':'<span class="muted">').datum($l['pretplata_do']).'</span>') : '<span class="muted">—</span>' ?></td></tr>
      <?php endforeach; endif; ?>
      </tbody>
    </table></div>
  </div>

<?php else:
    // ---- LOKAL PREGLED (widgeti) ----
    $lid = current_lokal_id();
    $v = fn($sql,$p=[]) => db_val($sql,$p);

    $danas = (float)$v('SELECT COALESCE(SUM(iznos),0) FROM pazar WHERE lokal_id=? AND datum=CURDATE()', [$lid]);
    $mesec = (float)$v('SELECT COALESCE(SUM(iznos),0) FROM pazar WHERE lokal_id=? AND YEAR(datum)=YEAR(CURDATE()) AND MONTH(datum)=MONTH(CURDATE())', [$lid]);
    $nabavkaMesec = (float)$v('SELECT COALESCE(SUM(iznos),0) FROM fakture WHERE lokal_id=? AND YEAR(datum)=YEAR(CURDATE()) AND MONTH(datum)=MONTH(CURDATE())', [$lid]);
    $troskoviMesec = (float)$v('SELECT COALESCE(SUM(iznos),0) FROM troskovi WHERE lokal_id=? AND YEAR(datum)=YEAR(CURDATE()) AND MONTH(datum)=MONTH(CURDATE())', [$lid]);
    $bilans = $mesec - $nabavkaMesec - $troskoviMesec;
    $neplFakt = (float)$v('SELECT COALESCE(SUM(iznos-placeno),0) FROM fakture WHERE lokal_id=? AND status<>"placena"', [$lid]);
    $neplFaktBr = (int)$v('SELECT COUNT(*) FROM fakture WHERE lokal_id=? AND status<>"placena"', [$lid]);
    $neplTro = (float)$v('SELECT COALESCE(SUM(iznos),0) FROM troskovi WHERE lokal_id=? AND status="neplacen"', [$lid]);
    $neplTroBr = (int)$v('SELECT COUNT(*) FROM troskovi WHERE lokal_id=? AND status="neplacen"', [$lid]);
    $niskaZaliha = (int)$v('SELECT COUNT(*) FROM artikli WHERE lokal_id=? AND aktivan=1 AND min_zaliha>0 AND zaliha<=min_zaliha', [$lid]);
    $poslednji = db_all('SELECT p.*, k.ime, k.prezime FROM pazar p LEFT JOIN korisnici k ON k.id=p.korisnik_id WHERE p.lokal_id=? ORDER BY p.datum DESC, p.id DESC LIMIT 6', [$lid]);

    $dani = (int)date('t'); $dnevni = array_fill(1,$dani,0.0);
    foreach (db_all('SELECT DAY(datum) d, SUM(iznos) s FROM pazar WHERE lokal_id=? AND YEAR(datum)=YEAR(CURDATE()) AND MONTH(datum)=MONTH(CURDATE()) GROUP BY DAY(datum)',[$lid]) as $r) $dnevni[(int)$r['d']]=(float)$r['s'];
    $maxD = max(1, max($dnevni));

    // Konfiguracija widgeta
    $cfg = json_decode($u['dashboard_config'] ?? '', true);
    if (!is_array($cfg) || !$cfg) $cfg = array_keys($WIDGETS);
    $cfg = array_values(array_filter($cfg, fn($k)=>isset($WIDGETS[$k])));
    if (!$cfg) $cfg = array_keys($WIDGETS);

    // Render funkcije
    $renderStat = function($k) use ($danas,$mesec,$bilans,$nabavkaMesec,$troskoviMesec,$neplFakt,$neplFaktBr,$neplTro,$neplTroBr,$niskaZaliha) {
        switch ($k) {
          case 'pazar_danas': return '<div class="stat"><div class="stat__icon i-green">'.stat_svg('novac').'</div><div class="stat__label">Pazar danas</div><div class="stat__value in">'.novac($danas).'</div></div>';
          case 'pazar_mesec': return '<div class="stat"><div class="stat__icon i-teal">'.stat_svg('novac').'</div><div class="stat__label">Pazar ovog meseca</div><div class="stat__value">'.novac($mesec).'</div></div>';
          case 'bilans': return '<div class="stat"><div class="stat__icon '.($bilans>=0?'i-green':'i-amber').'">'.stat_svg('novac').'</div><div class="stat__label">Bilans meseca</div><div class="stat__value '.($bilans>=0?'in':'out').'">'.novac($bilans).'</div><div class="stat__delta muted">Nabavka '.novac($nabavkaMesec).' · Troškovi '.novac($troskoviMesec).'</div></div>';
          case 'neplacene_fakture': return '<a class="stat" href="'.url('fakture').'?tab=neplacene" style="text-decoration:none"><div class="stat__icon i-amber">'.stat_svg('novac').'</div><div class="stat__label">Neplaćene fakture</div><div class="stat__value '.($neplFaktBr?'out':'').'">'.novac($neplFakt).'</div><div class="stat__delta">'.$neplFaktBr.' faktura →</div></a>';
          case 'neplaceni_troskovi': return '<a class="stat" href="'.url('troskovi').'?tab=neplaceni" style="text-decoration:none"><div class="stat__icon i-amber">'.stat_svg('novac').'</div><div class="stat__label">Neplaćeni troškovi</div><div class="stat__value '.($neplTroBr?'out':'').'">'.novac($neplTro).'</div><div class="stat__delta">'.$neplTroBr.' računa →</div></a>';
          case 'niska_zaliha': return '<a class="stat" href="'.url('zalihe').'" style="text-decoration:none"><div class="stat__icon i-blue">'.stat_svg('artikli').'</div><div class="stat__label">Niska zaliha</div><div class="stat__value '.($niskaZaliha?'out':'').'">'.$niskaZaliha.'</div><div class="stat__delta">artikala →</div></a>';
        }
        return '';
    };
    $renderWide = function($k) use ($poslednji,$dnevni,$dani,$maxD) {
        if ($k === 'grafikon_prometa') {
            $h = '<div class="card mb-2"><div class="card__head"><div class="card__title">Promet ovog meseca</div></div><div class="card__body">';
            if (array_sum($dnevni)==0) { $h .= '<div class="empty">Nema unetog pazara.</div>'; }
            else {
              $h .= '<div style="display:flex;align-items:flex-end;gap:3px;height:150px">';
              for ($d=1;$d<=$dani;$d++){ $bh=round($dnevni[$d]/$maxD*100);
                $h .= '<div style="flex:1;display:flex;flex-direction:column;justify-content:flex-end;height:100%" title="'.$d.'. — '.novac($dnevni[$d]).'"><div style="height:'.$bh.'%;background:linear-gradient(180deg,var(--brand),var(--brand-700));border-radius:4px 4px 0 0;min-height:'.($dnevni[$d]>0?2:0).'px"></div></div>'; }
              $h .= '</div>';
            }
            return $h.'</div></div>';
        }
        if ($k === 'poslednji_pazari') {
            $h = '<div class="card mb-2"><div class="card__head"><div class="card__title">Poslednji uneti pazari</div><a class="btn btn--ghost btn--sm" href="'.url('pazar').'">Ceo pregled →</a></div><div class="table-wrap"><table class="table"><thead><tr><th>Datum</th><th>Smena</th><th>Uneo</th><th class="num">Iznos</th></tr></thead><tbody>';
            if (!$poslednji) $h .= '<tr><td colspan="4"><div class="empty">Još nema unetih pazara.</div></td></tr>';
            else foreach ($poslednji as $p) $h .= '<tr><td><strong>'.datum($p['datum']).'</strong></td><td><span class="badge badge--muted">'.e(ucfirst($p['smena'])).'</span></td><td class="muted">'.(e(trim(($p['ime']??'').' '.($p['prezime']??''))) ?: '—').'</td><td class="num">'.novac($p['iznos']).'</td></tr>';
            return $h.'</tbody></table></div></div>';
        }
        return '';
    };
?>
  <div class="page-head">
    <div><h1>Zdravo, <?= e($u['ime']) ?></h1><p><?= e($u['lokal_naziv']) ?> — evo kako stoje stvari danas.</p></div>
    <div class="flex gap-2">
      <button class="btn btn--ghost" onclick="mWidgets.showModal()"><?= ico('settings',16) ?> Prilagodi</button>
      <a class="btn btn--primary" href="<?= url('pazar') ?>">+ Unesi pazar</a>
    </div>
  </div>

  <?php
    $brArtUk = (int)$v('SELECT COUNT(*) FROM artikli WHERE lokal_id=?', [$lid]);
    if ($brArtUk === 0 && user_has_role(['vlasnik','menadzer'])): ?>
    <div class="card mb-2" style="border:1px solid var(--brand);background:linear-gradient(120deg,var(--brand-soft),var(--surface))">
      <div class="card__body flex items-center justify-between" style="gap:16px;flex-wrap:wrap">
        <div><strong style="font-size:1.05rem">Dobrodošao! Podesimo tvoj lokal.</strong>
          <div class="muted" style="font-size:.9rem">Dodaj kategorije, artikle i boju brenda za par minuta.</div></div>
        <a class="btn btn--primary" href="<?= url('onboarding') ?>">Pokreni brzo podešavanje →</a>
      </div>
    </div>
  <?php endif; ?>

  <?php
    $buf = [];
    $flush = function() use (&$buf) { if ($buf) { echo '<div class="stats mb-2">'.implode('',$buf).'</div>'; $buf=[]; } };
    foreach ($cfg as $k) {
        if ($WIDGETS[$k][1] === 'stat') { $buf[] = $renderStat($k); }
        else { $flush(); echo $renderWide($k); }
    }
    $flush();
  ?>

  <dialog id="mWidgets" class="modal">
    <form method="post" action="<?= url('dashboard') ?>" onsubmit="return collectWidgets()">
      <?= csrf_field() ?><input type="hidden" name="akcija" value="dashboard_config"><input type="hidden" name="config" id="w_config">
      <div class="card__head"><div class="card__title">Prilagodi kontrolnu tablu</div>
        <button type="button" class="btn btn--ghost btn--sm" onclick="mWidgets.close()">✕</button></div>
      <div class="card__body">
        <p class="muted" style="margin-top:0">Uključi/isključi i promeni redosled (strelicama).</p>
        <ul id="wlist" style="list-style:none;padding:0;margin:0">
          <?php
            $order = array_merge($cfg, array_diff(array_keys($WIDGETS), $cfg));
            foreach ($order as $k): $on = in_array($k,$cfg,true); ?>
            <li data-key="<?= $k ?>" style="display:flex;align-items:center;gap:10px;padding:9px 10px;border:1px solid var(--border);border-radius:10px;margin-bottom:8px;background:var(--surface-2)">
              <input type="checkbox" class="w_on" <?= $on?'checked':'' ?>>
              <span style="flex:1;font-weight:600"><?= e($WIDGETS[$k][0]) ?> <span class="muted" style="font-weight:400;font-size:.8rem">(<?= $WIDGETS[$k][1]==='stat'?'kartica':'panel' ?>)</span></span>
              <button type="button" class="btn btn--ghost btn--sm" onclick="moveW(this,-1)">▲</button>
              <button type="button" class="btn btn--ghost btn--sm" onclick="moveW(this,1)">▼</button>
            </li>
          <?php endforeach; ?>
        </ul>
      </div>
      <div class="modal__foot"><button type="button" class="btn btn--ghost" onclick="mWidgets.close()">Otkaži</button><button class="btn btn--primary">Sačuvaj raspored</button></div>
    </form>
  </dialog>
  <script>
    function moveW(btn,dir){const li=btn.closest('li'),ul=li.parentNode;if(dir<0&&li.previousElementSibling)ul.insertBefore(li,li.previousElementSibling);if(dir>0&&li.nextElementSibling)ul.insertBefore(li.nextElementSibling,li);}
    function collectWidgets(){const keys=[];document.querySelectorAll('#wlist li').forEach(li=>{if(li.querySelector('.w_on').checked)keys.push(li.dataset.key);});document.getElementById('w_config').value=JSON.stringify(keys);return true;}
  </script>
<?php endif;

require __DIR__ . '/../partials/layout_bottom.php';
