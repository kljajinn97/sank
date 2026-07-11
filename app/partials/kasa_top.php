<?php
/** Shell POS terminala (bez BO sidebara) */
$dev = pos_current_device();
$pu  = pos_current_user();
$lok = $dev ? db_row('SELECT naziv,boja FROM lokali WHERE id=?', [$dev['lokal_id']]) : null;
$kasa_title = $kasa_title ?? 'POS';
?>
<!DOCTYPE html>
<html lang="sr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
<title><?= e($kasa_title) ?> · Sank POS</title>
<script>(function(){try{var t=localStorage.getItem('sank_theme');if(t==='dark')document.documentElement.setAttribute('data-theme','dark');}catch(e){}})();</script>
<?php if (!empty($lok['boja'])): ?><style>:root{--brand: <?= e($lok['boja']) ?>;}</style><?php endif; ?>
<link rel="stylesheet" href="<?= url('assets/css/app.css') ?>">
<link rel="manifest" href="/manifest.webmanifest">
<meta name="theme-color" content="<?= e($lok['boja'] ?? '#0d9488') ?>">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-title" content="<?= e($lok['naziv'] ?? 'Sank POS') ?>">
<link rel="apple-touch-icon" href="/assets/icon.svg">
<link rel="icon" href="/assets/icon.svg" type="image/svg+xml">
<script>if('serviceWorker' in navigator){window.addEventListener('load',function(){navigator.serviceWorker.register('/sw.js').catch(function(){});});}</script>
</head>
<body class="kasa-body">
<header class="kasa-top">
  <div class="kasa-top__brand">
    <div class="sidebar__logo" style="width:34px;height:34px;font-size:17px"><?= e(mb_strtoupper(mb_substr($lok['naziv'] ?? 'S',0,1))) ?></div>
    <div><div style="font-weight:800"><?= e($lok['naziv'] ?? 'Sank POS') ?></div>
      <div style="font-size:.72rem;color:var(--text-3)">POS terminal</div></div>
  </div>
  <div class="kasa-top__right">
    <?php if ($pu): ?>
      <span class="badge badge--teal"><?= e(trim($pu['ime'].' '.($pu['prezime']??''))) ?></span>
      <a class="btn btn--ghost btn--sm" href="<?= url('kasa') ?>?lock=1">🔒 Zaključaj</a>
    <?php endif; ?>
    <button class="iconbtn" onclick="var d=document.documentElement,k=d.getAttribute('data-theme')==='dark';if(k){d.removeAttribute('data-theme');localStorage.setItem('sank_theme','light')}else{d.setAttribute('data-theme','dark');localStorage.setItem('sank_theme','dark')}" title="Tema">
      <svg class="sun" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/></svg>
      <svg class="moon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12.8A9 9 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8z"/></svg>
    </button>
  </div>
</header>
<main class="kasa-main">
<?php foreach (flash_take() as $f): ?>
  <div class="flash flash--<?= $f['type']==='error'?'error':'success' ?>" style="max-width:640px;margin:0 auto 14px"><?= e($f['msg']) ?></div>
<?php endforeach; ?>
