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
<link rel="stylesheet" href="<?= asset('assets/css/app.css') ?>">
<link rel="manifest" href="/manifest.webmanifest">
<meta name="theme-color" content="<?= e($lok['boja'] ?? '#0d9488') ?>">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-title" content="<?= e($lok['naziv'] ?? 'Sank POS') ?>">
<link rel="apple-touch-icon" href="/assets/icon.svg">
<link rel="icon" href="/assets/icon.svg" type="image/svg+xml">
<script>if('serviceWorker' in navigator){window.addEventListener('load',function(){navigator.serviceWorker.register('/sw.js').catch(function(){});});}</script>
<script src="<?= asset('assets/js/ui.js') ?>"></script>
</head>
<body class="kasa-body">
<header class="kasa-top">
  <div class="kasa-top__brand">
    <div class="sidebar__logo" style="width:34px;height:34px;font-size:17px"><?= e(mb_strtoupper(mb_substr($lok['naziv'] ?? 'S',0,1))) ?></div>
    <div><div style="font-weight:800"><?= e($lok['naziv'] ?? 'Sank POS') ?></div>
      <div style="font-size:.72rem;color:var(--text-3)">POS terminal</div></div>
  </div>
  <div class="kasa-top__right">
    <span class="kasa-clock" id="topClock">--:--</span>
    <?php if ($pu): ?>
      <a class="badge badge--teal" href="<?= url('kasa') ?>?lock=1" style="text-decoration:none;display:inline-flex;align-items:center;gap:4px" title="Promeni radnika"><?= ico('user',13) ?> <?= e(trim($pu['ime'].' '.($pu['prezime']??''))) ?></a>
      <a class="btn btn--ghost btn--sm" href="<?= url('kasa') ?>?lock=1"><?= ico('lock',16) ?> Zaključaj</a>
    <?php endif; ?>
    <button class="iconbtn" onclick="var d=document.documentElement,k=d.getAttribute('data-theme')==='dark';if(k){d.removeAttribute('data-theme');localStorage.setItem('sank_theme','light')}else{d.setAttribute('data-theme','dark');localStorage.setItem('sank_theme','dark')}" title="Tema">
      <svg class="sun" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/></svg>
      <svg class="moon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12.8A9 9 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8z"/></svg>
    </button>
  </div>
</header>
<script>
(function(){
  var dani=['Nedelja','Ponedeljak','Utorak','Sreda','Četvrtak','Petak','Subota'];
  function p(n){return String(n).padStart(2,'0');}
  function tick(){
    var d=new Date(), t=p(d.getHours())+':'+p(d.getMinutes());
    var tc=document.getElementById('topClock'); if(tc) tc.textContent=t;
    var c=document.getElementById('clock'); if(c) c.textContent=t;
    var cd=document.getElementById('cdate'); if(cd) cd.textContent=dani[d.getDay()]+', '+p(d.getDate())+'.'+p(d.getMonth()+1)+'.'+d.getFullYear()+'.';
  }
  tick(); setInterval(tick,1000);
})();
</script>
<main class="kasa-main">
<?php $flashes = flash_take(); if ($flashes): ?>
<script>document.addEventListener('DOMContentLoaded',function(){<?php foreach ($flashes as $f): ?>SankUI.toast(<?= json_encode($f['msg']) ?>,<?= json_encode($f['type']==='error'?'error':'success') ?>);<?php endforeach; ?>});</script>
<?php endif; ?>
