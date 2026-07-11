<?php
/** Dnevnik izmena (audit log) */
require_role(['vlasnik','menadzer']);
$lid = current_lokal_id();

$RADNJE = [
  'naplata'         => ['Naplata','ok'],
  'storno'          => ['Storno','danger'],
  'refund'          => ['Povrat','danger'],
  'brisanje'        => ['Brisanje','danger'],
  'izmena_cene'     => ['Izmena cene','warn'],
  'popust'          => ['Popust','warn'],
  'uklonjena_stavka'=> ['Uklonjena stavka','warn'],
  'status'          => ['Promena statusa','info'],
];

function urlq(string $path, array $q): string { return url($path) . (($qs=http_build_query($q))?'?'.$qs:''); }

$f = $_GET['tip'] ?? '';   // filter radnje ('r' je rezervisan za ruter)
$where = 'lokal_id=?'; $par = [$lid];
if (isset($RADNJE[$f])) { $where .= ' AND radnja=?'; $par[] = $f; }

$logovi = db_all("SELECT * FROM audit_log WHERE $where ORDER BY created_at DESC, id DESC LIMIT 300", $par);

// Brzi rezime (poslednjih 30 dana)
$rez = db_all('SELECT radnja, COUNT(*) c FROM audit_log WHERE lokal_id=? AND created_at>=DATE_SUB(NOW(),INTERVAL 30 DAY) GROUP BY radnja', [$lid]);
$rezMap = []; foreach ($rez as $x) $rezMap[$x['radnja']] = (int)$x['c'];

$page_title = 'Dnevnik izmena';
$active = 'audit';
require __DIR__ . '/../partials/layout_top.php';
?>
<div class="page-head">
  <div><h1>Dnevnik izmena</h1><p>Ko je šta radio — naplate, storna, brisanja, izmene cena. Zaštita od zloupotreba.</p></div>
</div>

<div class="stats mb-2">
  <div class="stat"><div class="stat__label">Storna (30 dana)</div><div class="stat__value <?= ($rezMap['storno']??0)?'out':'' ?>"><?= $rezMap['storno'] ?? 0 ?></div></div>
  <div class="stat"><div class="stat__label">Brisanja (30 dana)</div><div class="stat__value"><?= $rezMap['brisanje'] ?? 0 ?></div></div>
  <div class="stat"><div class="stat__label">Izmene cena (30 dana)</div><div class="stat__value"><?= $rezMap['izmena_cene'] ?? 0 ?></div></div>
  <div class="stat"><div class="stat__label">Naplate (30 dana)</div><div class="stat__value in"><?= $rezMap['naplata'] ?? 0 ?></div></div>
</div>

<div class="toolbar">
  <div class="tabs" style="flex-wrap:wrap">
    <a href="<?= url('audit') ?>" class="<?= $f===''?'is-active':'' ?>">Sve</a>
    <?php foreach ($RADNJE as $k=>$v): ?>
      <a href="<?= urlq('audit',['tip'=>$k]) ?>" class="<?= $f===$k?'is-active':'' ?>"><?= e($v[0]) ?></a>
    <?php endforeach; ?>
  </div>
</div>

<div class="card"><div class="table-wrap">
  <table class="table">
    <thead><tr><th>Vreme</th><th>Radnik</th><th>Radnja</th><th>Detalji</th><th>IP</th></tr></thead>
    <tbody>
    <?php if (!$logovi): ?>
      <tr><td colspan="5"><div class="empty">Još nema zabeleženih radnji.</div></td></tr>
    <?php else: foreach ($logovi as $l):
      [$lbl,$cls] = $RADNJE[$l['radnja']] ?? [$l['radnja'],'muted'];
    ?>
      <tr>
        <td class="muted" style="white-space:nowrap"><?= date('d.m.Y. H:i', strtotime($l['created_at'])) ?></td>
        <td><strong><?= e($l['korisnik_ime'] ?: '—') ?></strong></td>
        <td><span class="badge badge--<?= $cls ?>"><?= e($lbl) ?></span></td>
        <td><?= e($l['detalji'] ?: '') ?><?php if($l['entitet']):?> <span class="muted" style="font-size:.8rem">(<?= e($l['entitet']) ?> #<?= (int)$l['entitet_id'] ?>)</span><?php endif;?></td>
        <td class="muted" style="font-size:.8rem"><?= e($l['ip'] ?: '') ?></td>
      </tr>
    <?php endforeach; endif; ?>
    </tbody>
  </table>
</div></div>

<?php require __DIR__ . '/../partials/layout_bottom.php'; ?>
