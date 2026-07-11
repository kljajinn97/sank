<?php
require_role(['super_admin']);
$active = 'korisnici'; $page_title = 'Svi korisnici';

$korisnici = db()->query(
    'SELECT k.*, l.naziv AS lokal_naziv FROM korisnici k
     LEFT JOIN lokali l ON l.id=k.lokal_id
     ORDER BY k.uloga="super_admin" DESC, l.naziv, k.ime'
)->fetchAll();

require __DIR__ . '/../../partials/layout_top.php';
?>
<div class="page-head"><div><h1>Svi korisnici</h1><p>Pregled svih naloga u sistemu, po lokalima.</p></div></div>
<div class="card"><div class="table-wrap">
  <table class="table">
    <thead><tr><th>Ime</th><th>Korisničko ime</th><th>Email</th><th>Lokal</th><th>Uloga</th><th>Status</th><th>Poslednja prijava</th></tr></thead>
    <tbody>
    <?php foreach ($korisnici as $k): ?>
      <tr>
        <td><strong><?= e($k['ime'].' '.($k['prezime']??'')) ?></strong></td>
        <td class="muted"><?= e($k['username']) ?></td>
        <td class="muted"><?= e($k['email']) ?></td>
        <td><?= e($k['lokal_naziv'] ?: '— (sistem)') ?></td>
        <td><span class="badge badge--teal"><?= e(ucfirst($k['uloga'])) ?></span></td>
        <td><span class="badge badge--<?= $k['status']==='aktivan'?'ok':'danger' ?>"><?= e(ucfirst($k['status'])) ?></span></td>
        <td class="muted"><?= $k['last_login'] ? datum($k['last_login']) : 'nikad' ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div></div>
<?php require __DIR__ . '/../../partials/layout_bottom.php'; ?>
