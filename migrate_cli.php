<?php
// ============================================================
//  SANK — CLI migracije (poziva se iz .cpanel.yml posle deploy-a)
//  Pušta schema.sql + sve sql/NN_*.sql idempotentno. SAMO CLI.
// ============================================================
if (PHP_SAPI !== 'cli') { http_response_code(403); die('Samo CLI.'); }

require __DIR__ . '/app/core.php';

$files = ['sql/schema.sql'];
foreach (glob(__DIR__ . '/sql/[0-9]*.sql') ?: [] as $mf) $files[] = 'sql/' . basename($mf);
$files = array_values(array_unique($files));

$err = 0;
foreach ($files as $f) {
    try {
        $sql = @file_get_contents(__DIR__ . '/' . $f);
        if ($sql === false) throw new RuntimeException('ne mogu da pročitam');
        db()->exec($sql);
        echo "OK  $f\n";
    } catch (Throwable $e) {
        echo "ERR $f: " . $e->getMessage() . "\n";
        $err++;
    }
}
echo $err ? "Zavrseno sa $err gresaka.\n" : "Sve migracije primenjene.\n";
