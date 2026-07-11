<?php
// ============================================================
//  SANK — Migracije baze (za server bez SSH-a)
//  Pušta schema.sql + sve sql/NN_*.sql (idempotentno: IF NOT EXISTS…).
//  Pristup: samo prijavljen SUPER ADMIN.
// ============================================================

require __DIR__ . '/app/core.php';
require_login();
if (!is_super_admin()) { http_response_code(403); die('Samo super admin može da pušta migracije.'); }

$rezultat = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $files = ['sql/schema.sql'];
    foreach (glob(__DIR__ . '/sql/[0-9]*.sql') ?: [] as $mf) $files[] = 'sql/' . basename($mf);
    $files = array_values(array_unique($files));
    foreach ($files as $f) {
        try {
            $sql = @file_get_contents(__DIR__ . '/' . $f);
            if ($sql === false) throw new RuntimeException('ne mogu da pročitam fajl');
            db()->exec($sql);
            $rezultat[] = ['f' => $f, 'ok' => true, 'msg' => 'primenjeno'];
        } catch (Throwable $e) {
            $rezultat[] = ['f' => $f, 'ok' => false, 'msg' => $e->getMessage()];
        }
    }
}

$sqlFajlovi = array_merge(['sql/schema.sql'], array_map(fn($p)=>'sql/'.basename($p), glob(__DIR__.'/sql/[0-9]*.sql') ?: []));

$page_title = 'Migracije baze';
$active = '';
require __DIR__ . '/app/partials/layout_top.php';
?>
<div class="page-head"><div><h1>Migracije baze</h1><p>Primeni šemu i sve migracije na bazu (bezbedno je pokrenuti više puta).</p></div></div>

<div class="card"><div class="card__body">
  <?php if ($rezultat): ?>
    <?php $greske = array_filter($rezultat, fn($r)=>!$r['ok']); ?>
    <div class="flash flash--<?= $greske?'error':'success' ?>" style="margin-bottom:16px">
      <?= $greske ? 'Neke migracije nisu prošle — vidi ispod.' : '✔ Sve migracije su uspešno primenjene.' ?>
    </div>
    <div class="table-wrap"><table class="table">
      <thead><tr><th>Fajl</th><th>Status</th></tr></thead>
      <tbody>
      <?php foreach ($rezultat as $r): ?>
        <tr><td><code><?= e($r['f']) ?></code></td>
          <td><?= $r['ok'] ? '<span class="badge badge--ok">OK</span>' : '<span class="badge badge--danger">'.e($r['msg']).'</span>' ?></td></tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
    <a class="btn btn--ghost mt-2" href="<?= url('dashboard') ?>">← Nazad na tablu</a>
  <?php else: ?>
    <p class="muted" style="margin-top:0">Biće primenjeno <?= count($sqlFajlovi) ?> SQL fajlova (redom):</p>
    <ul class="muted" style="margin:8px 0 18px 18px;line-height:1.7">
      <?php foreach ($sqlFajlovi as $f): ?><li><code><?= e($f) ?></code></li><?php endforeach; ?>
    </ul>
    <form method="post"><?= csrf_field() ?>
      <button class="btn btn--primary">Pokreni migracije</button>
    </form>
  <?php endif; ?>
</div></div>

<?php require __DIR__ . '/app/partials/layout_bottom.php'; ?>
