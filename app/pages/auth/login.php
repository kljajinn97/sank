<?php
/** Prijava na sistem */
if (is_logged_in()) redirect(url('dashboard'));

$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $username = trim($_POST['username'] ?? '');
    $password = (string)($_POST['password'] ?? '');
    if ($username === '' || $password === '') {
        $error = 'Unesi korisničko ime i lozinku.';
    } elseif (attempt_login($username, $password)) {
        redirect(url('dashboard'));
    } else {
        $error = 'Pogrešni podaci ili je nalog neaktivan.';
    }
}
?>
<!DOCTYPE html>
<html lang="sr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Prijava · Waiter</title>
<link rel="stylesheet" href="<?= asset('assets/css/app.css') ?>">
</head>
<body>
<div class="auth">
  <aside class="auth__aside">
    <div class="auth__brand">
      <div class="sidebar__logo">W</div> Waiter
    </div>
    <div class="auth__pitch">
      <h1>Digitalna knjiga šanka</h1>
      <p>Pazar, roba, zalihe i izveštaji — sve na jednom mestu, jednostavno i pregledno.</p>
      <div class="auth__features">
        <div class="auth__feature"><span class="dot"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg></span> Dnevni pazar po smenama</div>
        <div class="auth__feature"><span class="dot"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg></span> Artikli, cene i zalihe</div>
        <div class="auth__feature"><span class="dot"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg></span> Jasni izveštaji za vlasnika</div>
      </div>
    </div>
    <div style="color:rgba(255,255,255,.6);font-size:.82rem;">© <?= date('Y') ?> Waiter — sve za tvoj šank.</div>
  </aside>

  <div class="auth__main">
    <form class="auth__form" method="post" action="<?= url('login') ?>">
      <?= csrf_field() ?>
      <h2>Dobrodošli nazad</h2>
      <p class="muted">Prijavi se na svoj nalog.</p>

      <?php if ($error): ?>
        <div class="flash flash--error"><?= e($error) ?></div>
      <?php endif; ?>

      <div class="field">
        <label class="label" for="username">Korisničko ime</label>
        <input class="input" id="username" name="username" type="text" autofocus
               value="<?= e($_POST['username'] ?? '') ?>" placeholder="npr. vlasnik_marko">
      </div>
      <div class="field">
        <label class="label" for="password">Lozinka</label>
        <input class="input" id="password" name="password" type="password" placeholder="••••••••">
      </div>

      <button class="btn btn--primary btn--block" type="submit">Prijavi se</button>

      <p class="help" style="text-align:center;margin-top:18px;">
        Nemaš nalog? Obrati se administratoru sistema.
      </p>
    </form>
  </div>
</div>
</body>
</html>
