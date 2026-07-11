<?php
// ============================================================
//  SANK — Instal čarobnjak (JEDNOKRATNO)
//  1) Kreira tabele iz sql/schema.sql
//  2) Pravi prvi super_admin nalog sa ispravnom lozinkom
//  Posle uspešnog setup-a: u app/config.php stavi allow_setup=false
//  ili obriši ovaj fajl.
// ============================================================

require __DIR__ . '/app/core.php';

if (empty($CONFIG['allow_setup'])) {
    die('Setup je onemogućen. Za ponovno pokretanje postavi allow_setup=true u app/config.php.');
}

$step_msg = null; $error = null; $done = false;

// Da li baza već ima super admina?
function ima_admina(): bool {
    try {
        return (bool)db()->query("SELECT COUNT(*) FROM korisnici WHERE uloga='super_admin'")->fetchColumn();
    } catch (Throwable $e) { return false; }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    try {
        // 1) Pusti šemu + sve migracije (idempotentno, IF NOT EXISTS)
        $files = ['sql/schema.sql'];
        foreach (glob(__DIR__ . '/sql/[0-9]*.sql') ?: [] as $mf) $files[] = 'sql/' . basename($mf);
        $files = array_values(array_unique($files));
        foreach ($files as $f) {
            $sql = @file_get_contents(__DIR__ . '/' . $f);
            if ($sql === false) throw new RuntimeException('Ne mogu da pročitam ' . $f);
            db()->exec($sql);
        }

        if (ima_admina()) {
            throw new RuntimeException('Super admin već postoji. Setup je verovatno već odrađen.');
        }

        $ime = trim($_POST['ime'] ?? '');
        $prezime = trim($_POST['prezime'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $username = trim($_POST['username'] ?? '');
        $pass = (string)($_POST['password'] ?? '');

        if ($ime==='' || $email==='' || $username==='' || strlen($pass) < 6) {
            throw new RuntimeException('Popuni sva polja. Lozinka mora imati bar 6 karaktera.');
        }

        $st = db()->prepare(
            'INSERT INTO korisnici (lokal_id,ime,prezime,email,username,password_hash,uloga,status)
             VALUES (NULL,?,?,?,?,?,"super_admin","aktivan")'
        );
        $st->execute([$ime,$prezime,$email,$username, password_hash($pass, PASSWORD_DEFAULT)]);
        $done = true;
        $step_msg = 'Sve je spremno! Baza je kreirana i napravljen je tvoj admin nalog.';
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

// Provera konekcije za prikaz
$db_ok = false; $db_err = null;
try { db()->query('SELECT 1'); $db_ok = true; }
catch (Throwable $e) { $db_err = $e->getMessage(); }
?>
<!DOCTYPE html>
<html lang="sr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Instalacija · Sank</title>
<link rel="stylesheet" href="<?= asset('assets/css/app.css') ?>">
</head>
<body style="background:var(--bg);">
<div style="max-width:520px;margin:6vh auto;padding:20px;">
  <div style="display:flex;align-items:center;gap:14px;justify-content:center;margin-bottom:26px;">
    <div class="sidebar__logo" style="width:46px;height:46px;font-size:24px;">S</div>
    <div style="font-size:1.6rem;font-weight:800;">Sank — instalacija</div>
  </div>

  <div class="card"><div class="card__body">
    <div class="flash flash--<?= $db_ok?'info':'error' ?>" style="margin-bottom:20px;">
      <?php if ($db_ok): ?>✔ Konekcija sa bazom <strong><?= e($CONFIG['db']['name']) ?></strong> uspešna.
      <?php else: ?>✖ Nema konekcije sa bazom: <?= e($db_err) ?>
      <?php endif; ?>
    </div>

    <?php if ($done): ?>
      <div class="flash flash--success"><?= e($step_msg) ?></div>
      <div class="help mb-2">⚠️ Iz bezbednosnih razloga sada u <code>app/config.php</code> postavi <code>'allow_setup' =&gt; false</code> ili obriši <code>setup.php</code>.</div>
      <a class="btn btn--primary btn--block" href="<?= url('login') ?>">Idi na prijavu →</a>
    <?php else: ?>
      <?php if ($error): ?><div class="flash flash--error"><?= e($error) ?></div><?php endif; ?>
      <h3 style="margin-top:0;">Napravi glavni admin nalog</h3>
      <p class="muted" style="margin-bottom:18px;">Ovo je tvoj head-admin nalog za upravljanje svim lokalima.</p>
      <form method="post">
        <?= csrf_field() ?>
        <div class="form-row">
          <div class="field"><label class="label">Ime</label><input class="input" name="ime" value="<?= e($_POST['ime']??'') ?>" required></div>
          <div class="field"><label class="label">Prezime</label><input class="input" name="prezime" value="<?= e($_POST['prezime']??'') ?>"></div>
        </div>
        <div class="field"><label class="label">Email</label><input class="input" type="email" name="email" value="<?= e($_POST['email']??'') ?>" required></div>
        <div class="field"><label class="label">Korisničko ime</label><input class="input" name="username" value="<?= e($_POST['username']??'admin') ?>" required></div>
        <div class="field"><label class="label">Lozinka</label><input class="input" type="password" name="password" placeholder="min. 6 karaktera" required>
          <div class="help">Zapamti je dobro — njome se prijavljuješ.</div></div>
        <button class="btn btn--primary btn--block" <?= $db_ok?'':'disabled' ?>>Instaliraj i kreiraj nalog</button>
      </form>
    <?php endif; ?>
  </div></div>
</div>
</body>
</html>
