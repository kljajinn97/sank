<?php
// ============================================================
//  SANK — Primer konfiguracije
//  Kopiraj u app/config.php i popuni prave vrednosti.
//  app/config.php se NE čuva u git-u (ostaje na serveru).
// ============================================================

return [
    'db' => [
        'host'    => 'localhost',        // na cPanel-u je obično 'localhost'
        'name'    => 'kljajinc_sank',
        'user'    => 'kljajinc_sankuser',
        'pass'    => 'OVDE_LOZINKA',      // <-- unesi pravu lozinku baze
        'charset' => 'utf8mb4',
    ],

    'app_name' => 'Sank',

    // Posle prvog super_admin naloga stavi false (ili obriši setup.php).
    'allow_setup' => true,

    'timezone' => 'Europe/Belgrade',
];
