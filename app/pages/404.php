<?php
if (is_logged_in()) {
    $page_title = 'Stranica nije nađena';
    $active = '';
    require __DIR__ . '/../partials/layout_top.php';
    echo '<div class="empty"><h2>404 — Stranica ne postoji</h2><p>Ova adresa ne postoji u sistemu.</p><a class="btn btn--primary" href="'.url('dashboard').'">Nazad na početnu</a></div>';
    require __DIR__ . '/../partials/layout_bottom.php';
} else {
    redirect(url('login'));
}
