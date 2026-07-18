<?php
/** Računi (BO) — pregled svih POS računa: kopija, storno, povrat.
 *  Wrapper oko pos.php ekrana „Računi". */
$_GET['racuni'] = 1;
$RACUNI_BO = true;
require __DIR__ . '/pos.php';
