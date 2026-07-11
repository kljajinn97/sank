<?php
/**
 * Zajednički vrh strane (sidebar + topbar).
 * Očekuje promenljive: $page_title (string), $active (string – ključ menija)
 */
require_login();
$u = current_user();
$active = $active ?? '';
$page_title = $page_title ?? 'Sank';
$initials = mb_strtoupper(mb_substr($u['ime'],0,1) . mb_substr($u['prezime'] ?? '',0,1));

// Ikonice (inline SVG) po ključu
function nav_icon(string $k): string {
    $i = [
      'dashboard' => '<path d="M3 13h8V3H3v10zm0 8h8v-6H3v6zm10 0h8V11h-8v10zm0-18v6h8V3h-8z"/>',
      'lokali'    => '<path d="M3 21h18M5 21V7l8-4v18M19 21V11l-6-4"/>',
      'korisnici' => '<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8zm14 10v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/>',
      'artikli'   => '<path d="M20 7 12 3 4 7m16 0-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>',
      'pazar'     => '<path d="M12 1v22M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>',
      'pos'       => '<rect x="4" y="2" width="16" height="20" rx="2"/><path d="M8 6h8M8 10h8M8 14h4"/>',
      'uredjaji'  => '<rect x="5" y="2" width="14" height="20" rx="2"/><path d="M12 18h.01"/>',
      'backup'    => '<path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-7M7 10l5 5 5-5M12 15V3"/>',
      'fiskalizacija' => '<rect x="4" y="3" width="16" height="18" rx="2"/><path d="M8 7h8M8 11h8M8 15h5"/>',
      'fakture'   => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6M8 13h8M8 17h5"/>',
      'troskovi'  => '<path d="M2 5h20v14H2zM2 10h20M6 15h4"/>',
      'izvestaji' => '<path d="M3 3v18h18M7 15l4-4 3 3 5-6"/>',
      'kep'       => '<path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/>',
      'audit'     => '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><path d="m9 12 2 2 4-4"/>',
      'narudzbenice'=> '<path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4zM3 6h18M16 10a4 4 0 0 1-8 0"/>',
      'cene'      => '<path d="M20.6 3.4 12 12l-1.4-1.4M2 12h4M12 2v4M12 18v4M2 12a10 10 0 0 0 10 10"/>',
      'normativi' => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8zM14 2v6h6M9 13h6M9 17h4"/>',
      'popis'     => '<path d="M9 11l3 3L22 4M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/>',
      'smene'     => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
      'plate'     => '<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8zM19 8v6M22 11h-6"/>',
      'baksis'    => '<path d="M12 1v22M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>',
      'zalihe'    => '<path d="M21 8V5a2 2 0 0 0-2-2H5a2 2 0 0 0-2 2v3m18 0H3m18 0v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8m6 5h6"/>',
      'dobavljaci'=> '<path d="M1 3h15v13H1zM16 8h4l3 3v5h-7M5.5 19a2 2 0 1 0 0-4 2 2 0 0 0 0 4zm13 0a2 2 0 1 0 0-4 2 2 0 0 0 0 4z"/>',
      'podesavanja'=>'<path d="M12 15a3 3 0 1 0 0-6 3 3 0 0 0 0 6z"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/>',
      'logout'    => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4M16 17l5-5-5-5M21 12H9"/>',
    ];
    return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">' . ($i[$k] ?? '') . '</svg>';
}
function nav_item(string $key, string $label, string $route, string $active): void {
    $cls = $active === $key ? 'nav__item is-active' : 'nav__item';
    echo '<a class="'.$cls.'" href="'.url($route).'">'.nav_icon($key).'<span>'.e($label).'</span></a>';
}
?>
<!DOCTYPE html>
<html lang="sr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($page_title) ?> · Sank</title>
<script>(function(){try{var t=localStorage.getItem('sank_theme');if(t==='dark')document.documentElement.setAttribute('data-theme','dark');}catch(e){}})();</script>
<?php if (!is_super_admin() && !empty($u['lokal_boja'])): ?>
<style>:root{--brand: <?= e($u['lokal_boja']) ?>;}</style>
<?php endif; ?>
<link rel="stylesheet" href="<?= url('assets/css/app.css') ?>">
</head>
<body>
<div class="app">
  <aside class="sidebar" id="sidebar">
    <div class="sidebar__brand">
      <?php if (!is_super_admin() && !empty($u['lokal_logo'])): ?>
        <img class="sidebar__logo-img" src="<?= e($u['lokal_logo']) ?>" alt="logo">
      <?php else: ?>
        <div class="sidebar__logo"><?= is_super_admin() ? 'S' : e(mb_strtoupper(mb_substr($u['lokal_naziv'] ?? 'S',0,1))) ?></div>
      <?php endif; ?>
      <div>
        <div class="sidebar__name"><?= is_super_admin() ? 'Sank' : e($u['lokal_naziv'] ?? 'Sank') ?></div>
        <div class="sidebar__sub"><?= is_super_admin() ? 'Administracija' : 'Sank sistem' ?></div>
      </div>
    </div>

    <nav class="nav">
      <?php if (is_super_admin()): ?>
        <div class="nav__label">Administracija</div>
        <?php
          nav_item('dashboard','Pregled','dashboard',$active);
          nav_item('lokali','Lokali','admin/lokali',$active);
          nav_item('korisnici','Svi korisnici','admin/korisnici',$active);
        ?>
      <?php else: $sef = user_has_role(['vlasnik','menadzer']); ?>
        <div class="nav__label">Pregled</div>
        <?php nav_item('dashboard','Kontrolna tabla','dashboard',$active); ?>

        <div class="nav__label">Poslovanje</div>
        <?php
          nav_item('pos','POS / Kasa','pos',$active);
          nav_item('pazar','Dnevni pazar','pazar',$active);
          nav_item('fakture','Fakture (prijem robe)','fakture',$active);
          if ($sef) nav_item('narudzbenice','Narudžbenice','narudzbenice',$active);
          nav_item('troskovi','Troškovi i računi','troskovi',$active);
        ?>

        <div class="nav__label">Roba</div>
        <?php
          nav_item('artikli','Artikli i cenovnik','artikli',$active);
          if ($sef) nav_item('normativi','Normativi / recepture','normativi',$active);
          nav_item('zalihe','Zalihe','zalihe',$active);
          if ($sef) nav_item('popis','Popis / inventura','popis',$active);
          nav_item('dobavljaci','Dobavljači','dobavljaci',$active);
          if ($sef) nav_item('cene','Poređenje cena','cene',$active);
        ?>

        <?php if ($sef): ?>
        <div class="nav__label">Analitika</div>
        <?php
          nav_item('izvestaji','Izveštaji','izvestaji',$active);
          nav_item('kep','KEP knjiga','kep',$active);
          nav_item('audit','Dnevnik izmena','audit',$active);
        ?>
        <?php endif; ?>

        <div class="nav__label">Ljudi</div>
        <?php
          if ($sef) nav_item('korisnici','Zaposleni','korisnici',$active);
          if ($sef) nav_item('smene','Radno vreme','smene',$active);
          if ($sef) nav_item('plate','Plate i doprinosi','plate',$active);
          nav_item('baksis','Bakšiš','baksis',$active);
        ?>

        <?php if ($sef): ?>
          <div class="nav__label">Podešavanja</div>
          <?php
            nav_item('uredjaji','POS uređaji','uredjaji',$active);
            nav_item('fiskalizacija','Fiskalizacija','fiskalizacija',$active);
            nav_item('backup','Backup i izvoz','backup',$active);
            nav_item('podesavanja','Podešavanja lokala','podesavanja',$active);
          ?>
        <?php endif; ?>
      <?php endif; ?>
    </nav>

    <div class="sidebar__foot">
      <div class="userbox">
        <div class="avatar"><?= e($initials) ?></div>
        <div style="flex:1;min-width:0;">
          <div class="userbox__name"><?= e($u['ime'].' '.($u['prezime'] ?? '')) ?></div>
          <div class="userbox__role"><?= e(ucfirst($u['uloga'])) ?></div>
        </div>
        <a class="nav__item" style="padding:8px;margin:0;" href="<?= url('logout') ?>" title="Odjava"><?= nav_icon('logout') ?></a>
      </div>
    </div>
  </aside>

  <div class="main">
    <header class="topbar">
      <div class="topbar__title"><?= e($page_title) ?></div>
      <div class="topbar__right">
        <?php foreach (flash_take() as $f): ?>
          <span class="badge badge--<?= $f['type']==='error'?'danger':'ok' ?>"><?= e($f['msg']) ?></span>
        <?php endforeach; ?>
        <button class="iconbtn" onclick="toggleTheme()" title="Svetla / tamna tema">
          <svg class="sun" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/></svg>
          <svg class="moon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12.8A9 9 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8z"/></svg>
        </button>
      </div>
    </header>
    <script>
    function toggleTheme(){var d=document.documentElement;var dark=d.getAttribute('data-theme')==='dark';
      if(dark){d.removeAttribute('data-theme');try{localStorage.setItem('sank_theme','light')}catch(e){}}
      else{d.setAttribute('data-theme','dark');try{localStorage.setItem('sank_theme','dark')}catch(e){}}}
    </script>
    <main class="content">
