<?php
/** Poređenje cena dobavljača (iz istorije faktura) */
require_role(['vlasnik','menadzer']);
$lid = current_lokal_id();

$rows = db_all(
   'SELECT fs.artikal_id, a.naziv AS art, a.jedinica_mere, f.dobavljac_id,
           COALESCE(d.naziv,"(bez dobavljača)") AS dob, fs.cena, f.datum
    FROM faktura_stavke fs
    JOIN fakture f ON f.id=fs.faktura_id
    JOIN artikli a ON a.id=fs.artikal_id
    LEFT JOIN dobavljaci d ON d.id=f.dobavljac_id
    WHERE f.lokal_id=? AND fs.artikal_id IS NOT NULL
    ORDER BY a.naziv, f.datum DESC, f.id DESC', [$lid]);

// Grupiši: artikal -> dobavljac -> poslednja cena
$grupe = [];
foreach ($rows as $r) {
    $aid = $r['artikal_id']; $key = $r['dobavljac_id'] ?? 0;
    if (!isset($grupe[$aid])) $grupe[$aid] = ['naziv'=>$r['art'],'jm'=>$r['jedinica_mere'],'dob'=>[]];
    if (!isset($grupe[$aid]['dob'][$key]))
        $grupe[$aid]['dob'][$key] = ['naziv'=>$r['dob'],'cena'=>(float)$r['cena'],'datum'=>$r['datum']]; // prvi = najnoviji
}

$page_title = 'Poređenje cena';
$active = 'cene';
require __DIR__ . '/../partials/layout_top.php';
?>
<div class="page-head"><div><h1>Poređenje cena dobavljača</h1><p>Poslednje nabavne cene po dobavljaču — vidiš gde je najjeftinije.</p></div></div>

<?php if (!$grupe): ?>
  <div class="card"><div class="card__body"><div class="empty">Još nema istorije nabavke. Kada uneseš fakture sa artiklima, ovde ćeš videti poređenje cena.</div></div></div>
<?php else: ?>
  <div class="card"><div class="table-wrap">
    <table class="table">
      <thead><tr><th>Sirovina</th><th>Dobavljači i poslednja cena</th><th class="num">Najjeftinije</th></tr></thead>
      <tbody>
      <?php foreach ($grupe as $g):
        $min = null; foreach ($g['dob'] as $x) $min = ($min===null)?$x['cena']:min($min,$x['cena']);
      ?>
        <tr>
          <td><strong><?= e($g['naziv']) ?></strong> <span class="muted" style="font-size:.8rem"><?= e($g['jm']) ?></span></td>
          <td>
            <div class="flex gap-2" style="flex-wrap:wrap">
            <?php foreach ($g['dob'] as $x):
              $best = abs($x['cena']-$min) < 0.001; ?>
              <span class="badge badge--<?= $best?'ok':'muted' ?>" title="<?= datum($x['datum']) ?>">
                <?= e($x['naziv']) ?>: <?= novac($x['cena']) ?></span>
            <?php endforeach; ?>
            </div>
          </td>
          <td class="num in"><?= novac($min) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div></div>
<?php endif; ?>

<?php require __DIR__ . '/../partials/layout_bottom.php'; ?>
