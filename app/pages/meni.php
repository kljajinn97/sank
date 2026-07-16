<?php
/** Javni QR meni — bez prijave, po tokenu lokala */
$t = trim($_GET['t'] ?? '');
$lok = $t !== '' ? db_row('SELECT * FROM lokali WHERE javni_token=? LIMIT 1', [$t]) : null;
$dostupno = $lok && !empty($lok['meni_aktivan']) && modul_aktivan('qrmeni', (int)$lok['id']);
$boja = $lok['boja'] ?? '#b1662c';

$kat = $art = [];
if ($dostupno) {
    $kat = db_all('SELECT * FROM kategorije WHERE lokal_id=? ORDER BY naziv', [$lok['id']]);
    foreach (db_all('SELECT * FROM artikli WHERE lokal_id=? AND aktivan=1 AND prodajna_cena>0 ORDER BY naziv', [$lok['id']]) as $a)
        $art[(int)$a['kategorija_id']][] = $a;
}
?>
<!DOCTYPE html>
<html lang="sr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
<title><?= e($lok['naziv'] ?? 'Meni') ?> · Meni</title>
<meta name="theme-color" content="<?= e($boja) ?>">
<link rel="stylesheet" href="<?= asset('assets/css/app.css') ?>">
<style>:root{--brand: <?= e($boja) ?>;}</style>
</head>
<body style="background:var(--bg)">
<?php if (!$dostupno): ?>
  <div style="min-height:100vh;display:grid;place-items:center;text-align:center;padding:24px">
    <div><img src="<?= url('img/w_logo_color.png') ?>" alt="Waiter" style="height:64px;margin:0 auto 14px;display:block">
      <h2>Meni trenutno nije dostupan</h2><p class="muted">Obrati se osoblju.</p></div>
  </div>
<?php else: ?>
  <div class="meni">
    <header class="meni__head">
      <?php if (!empty($lok['logo'])): ?><img class="meni__logo" src="<?= e($lok['logo']) ?>" alt="">
      <?php else: ?><div class="meni__logo meni__logo--txt"><?= e(mb_strtoupper(mb_substr($lok['naziv'],0,1))) ?></div><?php endif; ?>
      <div><h1 class="meni__title"><?= e($lok['naziv']) ?></h1>
        <?php if($lok['grad']||$lok['adresa']):?><div class="meni__sub"><?= e(trim(($lok['adresa']??'').' '.($lok['grad']??''))) ?></div><?php endif;?></div>
    </header>

    <?php if (empty($art)): ?>
      <div class="empty" style="margin:40px 16px">Meni se uskoro dopunjava.</div>
    <?php else: ?>
      <?php
      // kategorije koje imaju artikle + "ostalo" (bez kategorije)
      foreach ($kat as $k):
        if (empty($art[(int)$k['id']])) continue; ?>
        <section class="meni__sec">
          <h2 class="meni__cat"><?= e($k['naziv']) ?></h2>
          <?php foreach ($art[(int)$k['id']] as $a): ?>
            <div class="meni__item">
              <?php if (!empty($a['slika'])): ?><div class="meni__img" style="background-image:url('<?= e($a['slika']) ?>')"></div>
              <?php else: ?><div class="meni__img meni__img--c" style="background:<?= e($a['boja'] ?: $k['boja'] ?: $boja) ?>"></div><?php endif; ?>
              <div class="meni__info"><div class="meni__name"><?= e($a['naziv']) ?></div>
                <?php if(!empty($a['opis'])):?><div class="meni__desc"><?= e($a['opis']) ?></div><?php endif;?></div>
              <div class="meni__price"><?= novac($a['prodajna_cena'], $lok['valuta'] ?: 'RSD') ?></div>
            </div>
          <?php endforeach; ?>
        </section>
      <?php endforeach; ?>
      <?php if (!empty($art[0])): ?>
        <section class="meni__sec"><h2 class="meni__cat">Ostalo</h2>
          <?php foreach ($art[0] as $a): ?>
            <div class="meni__item">
              <?php if (!empty($a['slika'])): ?><div class="meni__img" style="background-image:url('<?= e($a['slika']) ?>')"></div>
              <?php else: ?><div class="meni__img meni__img--c" style="background:<?= e($a['boja'] ?: $boja) ?>"></div><?php endif; ?>
              <div class="meni__info"><div class="meni__name"><?= e($a['naziv']) ?></div>
                <?php if(!empty($a['opis'])):?><div class="meni__desc"><?= e($a['opis']) ?></div><?php endif;?></div>
              <div class="meni__price"><?= novac($a['prodajna_cena'], $lok['valuta'] ?: 'RSD') ?></div>
            </div>
          <?php endforeach; ?>
        </section>
      <?php endif; ?>
    <?php endif; ?>

    <footer class="meni__foot">Digitalni meni · <strong>Waiter</strong></footer>
  </div>
<?php endif; ?>
</body>
</html>
