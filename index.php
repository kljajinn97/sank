<?php
// ============================================================
//  SANK — Front controller / router
//  Sve stranice prolaze kroz ovaj fajl.
// ============================================================

require __DIR__ . '/app/core.php';

// Ruta iz ?r= parametra (postavlja .htaccess) ili default
$route = trim($_GET['r'] ?? '', '/');
if ($route === '') {
    $route = is_logged_in() ? 'dashboard' : 'login';
}

// Whitelist ruta -> fajlovi u app/pages/
$routes = [
    'login'     => 'auth/login.php',
    'logout'    => 'auth/logout.php',
    'dashboard' => 'dashboard.php',

    // Super admin
    'admin/lokali'   => 'admin/lokali.php',
    'admin/korisnici'=> 'admin/korisnici.php',

    // Javno (bez prijave)
    'meni'       => 'meni.php',

    // POS terminal (odvojena aplikacija)
    'kasa'       => 'kasa.php',
    'uredjaji'   => 'uredjaji.php',
    'qrmeni'     => 'qrmeni.php',

    // Lokal
    'pos'        => 'pos.php',
    'artikli'    => 'artikli.php',
    'pazar'      => 'pazar.php',
    'fakture'    => 'fakture.php',
    'troskovi'   => 'troskovi.php',
    'izvestaji'  => 'izvestaji.php',
    'dan'        => 'dan.php',
    'pdv'        => 'pdv.php',
    'kep'        => 'kep.php',
    'audit'      => 'audit.php',
    'zalihe'     => 'zalihe.php',
    'dobavljaci' => 'dobavljaci.php',
    'narudzbenice'=> 'narudzbenice.php',
    'cene'       => 'cene.php',
    'normativi'  => 'normativi.php',
    'popis'      => 'popis.php',
    'smene'      => 'smene.php',
    'plate'      => 'plate.php',
    'baksis'     => 'baksis.php',
    'korisnici'  => 'korisnici.php',
    'podesavanja'=> 'podesavanja.php',
    'fiskalizacija'=> 'fiskalizacija.php',
    'backup'     => 'backup.php',
    'onboarding' => 'onboarding.php',
];

$file = $routes[$route] ?? null;

if ($file === null) {
    http_response_code(404);
    $route = '404';
    require __DIR__ . '/app/pages/404.php';
    exit;
}

require __DIR__ . '/app/pages/' . $file;
