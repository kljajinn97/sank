<?php
/** POS terminal — aktivacija uređaja + PIN prijava radnika */

// Zaključavanje (odjava radnika, uređaj ostaje aktiviran)
if (isset($_GET['lock'])) {
    unset($_SESSION['pos_uid'], $_SESSION['pos_lokal'], $_SESSION['pos_token']);
    redirect(url('kasa'));
}

$device = pos_current_device();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $akcija = $_POST['akcija'] ?? '';

    // --- Aktivacija uređaja ---
    if ($akcija === 'activate') {
        $kod = strtoupper(trim($_POST['kod'] ?? ''));
        $row = db_row('SELECT * FROM aktivacioni_kodovi WHERE kod=? AND iskoriscen=0 LIMIT 1', [$kod]);
        if (!$row) {
            flash('error','Neispravan ili već iskorišćen aktivacioni kod.');
            redirect(url('kasa'));
        }
        $token = gen_token();
        db_run('INSERT INTO pos_uredjaji (lokal_id,naziv,token) VALUES (?,?,?)',
               [$row['lokal_id'], 'POS '.date('d.m.Y'), $token]);
        $uid = (int)db()->lastInsertId();
        db_run('UPDATE aktivacioni_kodovi SET iskoriscen=1, uredjaj_id=?, used_at=NOW() WHERE id=?', [$uid,$row['id']]);
        setcookie('sank_pos_token', $token, [
            'expires' => time()+60*60*24*365, 'path' => '/', 'httponly' => true, 'samesite' => 'Lax'
        ]);
        flash('success','Uređaj je aktiviran! Unesi PIN.');
        redirect(url('kasa'));
    }

    // --- PIN prijava radnika ---
    if ($akcija === 'pin' && $device) {
        $pin = trim($_POST['pin'] ?? '');
        $kandidati = db_all('SELECT * FROM korisnici WHERE lokal_id=? AND status="aktivan" AND pin IS NOT NULL', [$device['lokal_id']]);
        $ulogovan = null;
        foreach ($kandidati as $k) { if (password_verify($pin, $k['pin'])) { $ulogovan = $k; break; } }
        if ($ulogovan) {
            $_SESSION['pos_uid'] = (int)$ulogovan['id'];
            $_SESSION['pos_lokal'] = (int)$device['lokal_id'];
            $_SESSION['pos_token'] = $device['token'];
            db_run('UPDATE pos_uredjaji SET poslednja_aktivnost=NOW() WHERE id=?', [$device['id']]);
            // Radnik ide PRAVO na novi brzi (šank) račun
            $hh = happy_hour_popust(db_row('SELECT * FROM lokali WHERE id=?', [$device['lokal_id']]));
            db_run('INSERT INTO racuni (lokal_id,sto_id,status,korisnik_id,popust_pct) VALUES (?,NULL,"otvoren",?,?)', [$device['lokal_id'], $ulogovan['id'], $hh]);
            redirect(url('pos') . '?racun=' . (int)db()->lastInsertId());
        }
        flash('error','Pogrešan PIN.');
        redirect(url('kasa'));
    }
    redirect(url('kasa'));
}

// Ako je već sve spremno → u prodaju
if ($device && pos_current_user()) redirect(url('pos'));

$kasa_title = 'Prijava';
$kasa_hide_top = ($device !== null);   // lock ekran = čist full screen, bez top bara
require __DIR__ . '/../partials/kasa_top.php';
?>
<?php $lok = $device ? db_row('SELECT naziv,logo FROM lokali WHERE id=?', [$device['lokal_id']]) : null; ?>
<?php if (!$device): ?>
  <div class="kasa-center"><div class="kasa-box">
    <img class="brand-glyph only-light" src="<?= url('img/w_logo_color.png') ?>" alt="Waiter" style="height:72px;margin:0 auto 18px;display:block">
    <img class="brand-glyph only-dark" src="<?= url('img/w_logo_white.png') ?>" alt="Waiter" style="height:72px;margin:0 auto 18px;display:none">
    <h2>Aktivacija POS uređaja</h2>
    <p class="muted" style="margin-bottom:24px">Unesi aktivacioni kod koji si dobio od administratora. Uređaj se vezuje za tvoj lokal.</p>
    <form method="post" action="<?= url('kasa') ?>">
      <?= csrf_field() ?><input type="hidden" name="akcija" value="activate">
      <input class="input" name="kod" placeholder="npr. WTR-4F2A-9C1B" required autofocus
             style="text-align:center;font-size:1.2rem;letter-spacing:2px;text-transform:uppercase;margin-bottom:16px">
      <button class="btn btn--primary btn--block">Aktiviraj uređaj</button>
    </form>
  </div></div>
<?php else: ?>
  <div class="pin-lock">
    <div class="pin-lock__col">
      <div>
        <div class="pin-lock__clock" id="clock">--:--</div>
        <div class="pin-lock__date" id="cdate"></div>
      </div>
      <div class="pin-dots pin-dots--left" id="pinDots"><i></i><i></i><i></i><i></i></div>
      <form method="post" action="<?= url('kasa') ?>" id="pinForm"><?= csrf_field() ?><input type="hidden" name="akcija" value="pin"><input type="hidden" name="pin" id="pinVal"></form>
      <div class="pinpad pinpad--glass">
        <?php foreach ([1,2,3,4,5,6,7,8,9] as $n): ?><button onclick="pinPush('<?= $n ?>')"><?= $n ?></button><?php endforeach; ?>
        <button class="wide" onclick="pinClear()">C</button>
        <button onclick="pinPush('0')">0</button>
        <button class="wide" onclick="pinBack()">⌫</button>
      </div>
    </div>
  </div>
<?php endif; ?>

<script>
if(document.getElementById('pinForm')){
  var PIN='';
  var paint=function(){document.querySelectorAll('#pinDots i').forEach(function(d,i){d.classList.toggle('on',i<PIN.length);});};
  var submitPin=function(){document.getElementById('pinVal').value=PIN;document.getElementById('pinForm').submit();};
  window.pinPush=function(n){ if(PIN.length>=4)return; PIN+=n; paint(); if(PIN.length===4) setTimeout(submitPin,140); };
  window.pinBack=function(){ PIN=PIN.slice(0,-1); paint(); };
  window.pinClear=function(){ PIN=''; paint(); };
  document.addEventListener('keydown',function(e){ if(e.key>='0'&&e.key<='9')pinPush(e.key); else if(e.key==='Backspace')pinBack(); });
}
</script>

<?php require __DIR__ . '/../partials/kasa_bottom.php'; ?>
