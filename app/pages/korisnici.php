<?php
/** Zaposleni — nalozi unutar lokala */
require_role(['vlasnik','menadzer']);
$lid = current_lokal_id();
$ja = current_user();

$ULOGE = ['menadzer'=>'Menadžer','konobar'=>'Konobar'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $akcija = $_POST['akcija'] ?? '';

    if ($akcija === 'sacuvaj') {
        $id = (int)($_POST['id'] ?? 0);
        $ime = post('ime'); $prezime = post('prezime');
        $email = post('email'); $username = post('username');
        $pass = (string)($_POST['password'] ?? '');
        $uloga = isset($ULOGE[$_POST['uloga'] ?? '']) ? $_POST['uloga'] : 'konobar';

        if ($ime==='' || $email==='' || $username==='') { flash('error','Popuni ime, email i korisničko ime.'); redirect(url('korisnici')); }

        // Jedinstvenost username/email (osim samog sebe)
        $dup = db_val('SELECT COUNT(*) FROM korisnici WHERE (username=? OR email=?) AND id<>?', [$username,$email,$id]);
        if ($dup > 0) { flash('error','Korisničko ime ili email već postoji.'); redirect(url('korisnici')); }

        // PIN (opciono, 4 cifre, jedinstven u lokalu)
        $pin = preg_replace('/\D/', '', $_POST['pin'] ?? '');
        $clearPin = isset($_POST['obrisi_pin']);
        if ($pin !== '' && strlen($pin) !== 4) { flash('error','PIN mora imati tačno 4 cifre.'); redirect(url('korisnici')); }
        if ($pin !== '') {
            foreach (db_all('SELECT pin FROM korisnici WHERE lokal_id=? AND id<>? AND pin IS NOT NULL', [$lid,$id]) as $o)
                if (password_verify($pin, $o['pin'])) { flash('error','Taj PIN već koristi drugi radnik.'); redirect(url('korisnici')); }
        }

        if ($id > 0) {
            $cilj = db_row('SELECT * FROM korisnici WHERE id=? AND lokal_id=?', [$id,$lid]);
            if (!$cilj || $cilj['uloga']==='vlasnik') { flash('error','Nije dozvoljeno.'); redirect(url('korisnici')); }
            if ($pass !== '') {
                if (strlen($pass) < 6) { flash('error','Lozinka mora imati bar 6 karaktera.'); redirect(url('korisnici')); }
                db_run('UPDATE korisnici SET ime=?,prezime=?,email=?,username=?,uloga=?,password_hash=? WHERE id=? AND lokal_id=?',
                       [$ime,$prezime,$email,$username,$uloga,password_hash($pass,PASSWORD_DEFAULT),$id,$lid]);
            } else {
                db_run('UPDATE korisnici SET ime=?,prezime=?,email=?,username=?,uloga=? WHERE id=? AND lokal_id=?',
                       [$ime,$prezime,$email,$username,$uloga,$id,$lid]);
            }
            $targetId = $id;
            flash('success','Nalog je izmenjen.');
        } else {
            if (strlen($pass) < 6) { flash('error','Lozinka mora imati bar 6 karaktera.'); redirect(url('korisnici')); }
            db_run('INSERT INTO korisnici (lokal_id,ime,prezime,email,username,password_hash,uloga,status)
                    VALUES (?,?,?,?,?,?,?,"aktivan")', [$lid,$ime,$prezime,$email,$username,password_hash($pass,PASSWORD_DEFAULT),$uloga]);
            $targetId = (int)db()->lastInsertId();
            flash('success','Zaposleni je dodat.');
        }

        if ($clearPin) db_run('UPDATE korisnici SET pin=NULL WHERE id=? AND lokal_id=?', [$targetId,$lid]);
        elseif ($pin !== '') db_run('UPDATE korisnici SET pin=? WHERE id=? AND lokal_id=?', [password_hash($pin,PASSWORD_DEFAULT),$targetId,$lid]);

        redirect(url('korisnici'));
    }

    if ($akcija === 'status') {
        $id = (int)($_POST['id'] ?? 0);
        $cilj = db_row('SELECT * FROM korisnici WHERE id=? AND lokal_id=?', [$id,$lid]);
        if ($cilj && $cilj['uloga']!=='vlasnik' && $cilj['id']!=$ja['id']) {
            $novi = $cilj['status']==='aktivan' ? 'neaktivan' : 'aktivan';
            db_run('UPDATE korisnici SET status=? WHERE id=? AND lokal_id=?', [$novi,$id,$lid]);
            audit('status','korisnik',$id, trim($cilj['ime'].' '.($cilj['prezime']??'')).' → '.$novi);
            flash('success','Status je promenjen.');
        }
        redirect(url('korisnici'));
    }

    if ($akcija === 'obrisi') {
        $id = (int)($_POST['id'] ?? 0);
        $cilj = db_row('SELECT * FROM korisnici WHERE id=? AND lokal_id=?', [$id,$lid]);
        if ($cilj && $cilj['uloga']!=='vlasnik' && $cilj['id']!=$ja['id']) {
            db_run('DELETE FROM korisnici WHERE id=? AND lokal_id=?', [$id,$lid]);
            audit('brisanje','korisnik',$id, trim($cilj['ime'].' '.($cilj['prezime']??'')));
            flash('success','Nalog je obrisan.');
        } else { flash('error','Ovaj nalog se ne može obrisati.'); }
        redirect(url('korisnici'));
    }
}

$korisnici = db_all('SELECT * FROM korisnici WHERE lokal_id=? ORDER BY uloga="vlasnik" DESC, uloga, ime', [$lid]);
$sviULOGE = ['vlasnik'=>'Vlasnik'] + $ULOGE;

$page_title = 'Zaposleni';
$active = 'korisnici';
require __DIR__ . '/../partials/layout_top.php';
?>
<div class="page-head">
  <div><h1>Zaposleni</h1><p>Nalozi za konobare i menadžere. Svako se prijavljuje svojim podacima.</p></div>
  <button class="btn btn--primary" onclick="openKor()">+ Novi zaposleni</button>
</div>

<div class="card"><div class="table-wrap">
  <table class="table">
    <thead><tr><th>Ime</th><th>Korisničko ime</th><th>Email</th><th>Uloga</th><th>Status</th><th>Poslednja prijava</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($korisnici as $k):
      $jaSam = $k['id']==$ja['id']; $jeVlasnik = $k['uloga']==='vlasnik';
    ?>
      <tr>
        <td><strong><?= e($k['ime'].' '.($k['prezime']??'')) ?></strong> <?php if($jaSam):?><span class="badge badge--info">ti</span><?php endif;?></td>
        <td class="muted"><?= e($k['username']) ?></td>
        <td class="muted"><?= e($k['email']) ?></td>
        <td><span class="badge badge--teal"><?= e($sviULOGE[$k['uloga']] ?? $k['uloga']) ?></span><?php if(!empty($k['pin'])):?> <span class="badge badge--ok" title="Ima POS PIN">PIN</span><?php endif;?></td>
        <td><span class="badge badge--<?= $k['status']==='aktivan'?'ok':'muted' ?>"><?= e(ucfirst($k['status'])) ?></span></td>
        <td class="muted"><?= $k['last_login'] ? datum($k['last_login']) : 'nikad' ?></td>
        <td class="text-right" style="white-space:nowrap">
          <?php if (!$jeVlasnik): ?>
            <button class="btn btn--ghost btn--sm" onclick='openKor(<?= json_encode([
              "id"=>$k["id"],"ime"=>$k["ime"],"prezime"=>$k["prezime"],"email"=>$k["email"],
              "username"=>$k["username"],"uloga"=>$k["uloga"]
            ], JSON_HEX_APOS|JSON_HEX_QUOT) ?>)'>Izmeni</button>
            <?php if (!$jaSam): ?>
            <form method="post" style="display:inline"><?= csrf_field() ?><input type="hidden" name="akcija" value="status"><input type="hidden" name="id" value="<?= $k['id'] ?>">
              <button class="btn btn--ghost btn--sm"><?= $k['status']==='aktivan'?'Deaktiviraj':'Aktiviraj' ?></button></form>
            <form method="post" style="display:inline" onsubmit="return ukConfirmSubmit(this,'Obrisati nalog?',{danger:true,ok:'Obriši'})"><?= csrf_field() ?><input type="hidden" name="akcija" value="obrisi"><input type="hidden" name="id" value="<?= $k['id'] ?>">
              <button class="btn btn--ghost btn--sm" style="color:var(--danger)">×</button></form>
            <?php endif; ?>
          <?php else: ?><span class="muted">—</span><?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div></div>

<dialog id="mKor" class="modal">
  <form method="post" action="<?= url('korisnici') ?>">
    <?= csrf_field() ?><input type="hidden" name="akcija" value="sacuvaj"><input type="hidden" name="id" id="k_id" value="0">
    <div class="card__head"><div class="card__title" id="k_title">Novi zaposleni</div>
      <button type="button" class="btn btn--ghost btn--sm" onclick="mKor.close()">✕</button></div>
    <div class="card__body">
      <div class="form-row">
        <div class="field"><label class="label">Ime *</label><input class="input" name="ime" id="k_ime" required></div>
        <div class="field"><label class="label">Prezime</label><input class="input" name="prezime" id="k_prezime"></div>
      </div>
      <div class="field"><label class="label">Email *</label><input class="input" type="email" name="email" id="k_email" required></div>
      <div class="form-row">
        <div class="field"><label class="label">Korisničko ime *</label><input class="input" name="username" id="k_username" required></div>
        <div class="field"><label class="label">Uloga</label>
          <select class="select" name="uloga" id="k_uloga">
            <?php foreach ($ULOGE as $v=>$l): ?><option value="<?= $v ?>"><?= e($l) ?></option><?php endforeach; ?>
          </select></div>
      </div>
      <div class="field"><label class="label">Lozinka <span id="k_passhint" class="muted"></span></label>
        <input class="input" type="password" name="password" id="k_password" placeholder="min. 6 karaktera">
        <div class="help" id="k_passhelp">Pri izmeni ostavi prazno da zadržiš postojeću lozinku.</div></div>
      <div class="field"><label class="label">PIN za POS terminal (4 cifre)</label>
        <input class="input" name="pin" id="k_pin" inputmode="numeric" maxlength="4" pattern="[0-9]*" placeholder="npr. 1234" autocomplete="off">
        <label class="flex items-center gap-2" id="k_pinclear" style="margin-top:6px;display:none;cursor:pointer"><input type="checkbox" name="obrisi_pin" value="1"><span class="help" style="margin:0">Ukloni PIN</span></label>
        <div class="help">Ovim PIN-om se radnik prijavljuje na POS. Ostavi prazno da zadržiš postojeći.</div></div>
    </div>
    <div class="modal__foot">
      <button type="button" class="btn btn--ghost" onclick="mKor.close()">Otkaži</button>
      <button class="btn btn--primary">Sačuvaj</button>
    </div>
  </form>
</dialog>
<script>
function openKor(k){
  k=k||{};
  k_id.value=k.id||0; k_title.textContent=k.id?'Izmena naloga':'Novi zaposleni';
  k_ime.value=k.ime||''; k_prezime.value=k.prezime||''; k_email.value=k.email||'';
  k_username.value=k.username||''; k_uloga.value=k.uloga||'konobar'; k_password.value='';
  k_pin.value=''; document.getElementById('k_pinclear').style.display=k.id?'flex':'none';
  k_passhelp.style.display=k.id?'block':'none';
  mKor.showModal();
}
</script>

<?php require __DIR__ . '/../partials/layout_bottom.php'; ?>
