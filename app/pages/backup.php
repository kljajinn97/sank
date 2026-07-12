<?php
/** Backup / izvoz podataka lokala */
require_role(['vlasnik','menadzer']);
$lid = (int)current_lokal_id();
$lokal = db_row('SELECT * FROM lokali WHERE id=?', [$lid]);

// Tabele lokala i njihov uslov (lid je proveren int → bezbedno umetanje)
$TABELE = [
  'lokali'              => "id = $lid",
  'korisnici'           => "lokal_id = $lid",
  'kategorije'          => "lokal_id = $lid",
  'artikli'             => "lokal_id = $lid",
  'dobavljaci'          => "lokal_id = $lid",
  'pazar'               => "lokal_id = $lid",
  'fakture'             => "lokal_id = $lid",
  'faktura_stavke'      => "faktura_id IN (SELECT id FROM fakture WHERE lokal_id = $lid)",
  'troskovi'            => "lokal_id = $lid",
  'zalihe_promet'       => "lokal_id = $lid",
  'normativi'           => "lokal_id = $lid",
  'normativ_stavke'     => "normativ_id IN (SELECT id FROM normativi WHERE lokal_id = $lid)",
  'popis'               => "lokal_id = $lid",
  'popis_stavke'        => "popis_id IN (SELECT id FROM popis WHERE lokal_id = $lid)",
  'narudzbenice'        => "lokal_id = $lid",
  'narudzbenica_stavke' => "narudzbenica_id IN (SELECT id FROM narudzbenice WHERE lokal_id = $lid)",
  'stolovi'             => "lokal_id = $lid",
  'racuni'              => "lokal_id = $lid",
  'racun_stavke'        => "racun_id IN (SELECT id FROM racuni WHERE lokal_id = $lid)",
  'smene'               => "lokal_id = $lid",
  'plate'               => "lokal_id = $lid",
  'baksis'              => "lokal_id = $lid",
  'audit_log'           => "lokal_id = $lid",
];

// Tabele ponuđene za CSV (Excel) izvoz
$CSV_TABELE = ['artikli'=>'Artikli','pazar'=>'Pazar','fakture'=>'Fakture','faktura_stavke'=>'Stavke faktura',
  'troskovi'=>'Troškovi','racuni'=>'Računi (POS)','dobavljaci'=>'Dobavljači','zalihe_promet'=>'Promet zaliha',
  'plate'=>'Plate','smene'=>'Smene'];

function safe_name(?string $s): string { return preg_replace('/[^a-zA-Z0-9_-]/','', $s ?? 'lokal') ?: 'lokal'; }

// ---------- SQL backup ----------
if (($_GET['dump'] ?? '') === 'sql') {
    $fn = 'waiter-backup-'.safe_name($lokal['naziv']).'-'.date('Y-m-d').'.sql';
    header('Content-Type: application/sql; charset=utf-8');
    header('Content-Disposition: attachment; filename="'.$fn.'"');
    $pdo = db();
    echo "-- Waiter backup — lokal: ".$lokal['naziv']." (#$lid)\n-- Datum: ".date('Y-m-d H:i')."\n";
    echo "-- Uvoz: import u bazu sa istom Waiter šemom.\nSET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\n\n";
    foreach ($TABELE as $t => $w) {
        $rows = $pdo->query("SELECT * FROM `$t` WHERE $w")->fetchAll();
        if (!$rows) continue;
        echo "-- $t (".count($rows).")\n";
        $cols = array_keys($rows[0]);
        $collist = '`'.implode('`,`',$cols).'`';
        foreach ($rows as $r) {
            $vals = array_map(fn($v)=> $v===null ? 'NULL' : $pdo->quote((string)$v), array_values($r));
            echo "INSERT INTO `$t` ($collist) VALUES (".implode(',',$vals).");\n";
        }
        echo "\n";
    }
    echo "SET FOREIGN_KEY_CHECKS=1;\n";
    exit;
}

// ---------- CSV izvoz jedne tabele ----------
if (($_GET['dump'] ?? '') === 'csv') {
    $t = $_GET['t'] ?? '';
    if (!isset($CSV_TABELE[$t])) { flash('error','Nepoznata tabela.'); redirect(url('backup')); }
    $fn = 'waiter-'.$t.'-'.date('Y-m-d').'.csv';
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="'.$fn.'"');
    echo "\xEF\xBB\xBF"; // BOM za Excel
    $out = fopen('php://output','w');
    $rows = db()->query("SELECT * FROM `$t` WHERE {$TABELE[$t]}")->fetchAll();
    if ($rows) {
        fputcsv($out, array_keys($rows[0]), ';');
        foreach ($rows as $r) fputcsv($out, array_values($r), ';');
    } else {
        fputcsv($out, ['(nema podataka)'], ';');
    }
    fclose($out);
    exit;
}

// Broj redova po tabeli (za prikaz)
$brojevi = [];
foreach ($CSV_TABELE as $t=>$_) $brojevi[$t] = (int)db()->query("SELECT COUNT(*) FROM `$t` WHERE {$TABELE[$t]}")->fetchColumn();

$page_title = 'Backup i izvoz';
$active = 'backup';
require __DIR__ . '/../partials/layout_top.php';
?>
<div class="page-head"><div><h1>Backup i izvoz podataka</h1><p>Napravi rezervnu kopiju ili izvezi podatke u Excel.</p></div></div>

<div class="grid-2">
  <div class="card">
    <div class="card__head"><div class="card__title">Kompletna rezervna kopija</div></div>
    <div class="card__body">
      <p class="muted" style="margin-top:0">Preuzmi sve podatke svog lokala kao jedan <strong>.sql</strong> fajl. Čuvaj ga na sigurnom — služi kao rezervna kopija.</p>
      <a class="btn btn--primary" href="<?= url('backup') ?>?dump=sql"><?= ico('download',16) ?> Preuzmi SQL backup</a>
      <div class="help" style="margin-top:12px">Savet: pravi kopiju povremeno (npr. jednom nedeljno) i pre većih izmena.</div>
    </div>
  </div>

  <div class="card">
    <div class="card__head"><div class="card__title">Izvoz u Excel (CSV)</div></div>
    <div class="table-wrap"><table class="table">
      <thead><tr><th>Tabela</th><th class="num">Redova</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($CSV_TABELE as $t=>$naz): ?>
        <tr>
          <td><strong><?= e($naz) ?></strong></td>
          <td class="num"><?= $brojevi[$t] ?></td>
          <td class="text-right"><a class="btn btn--ghost btn--sm" href="<?= url('backup') ?>?dump=csv&t=<?= $t ?>"><?= ico('download',15) ?> CSV</a></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
  </div>
</div>

<?php require __DIR__ . '/../partials/layout_bottom.php'; ?>
