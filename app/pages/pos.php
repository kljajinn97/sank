<?php
/** POS / Kasa — interni (bez fiskalizacije, dodaje se kasnije)
 *  Radi u dva režima: BO (vlasnik/menadzer/konobar preko lozinke) i
 *  TERMINAL (radnik preko PIN-a na aktiviranom uređaju). */
if (pos_terminal_active()) {
    $POS_TERMINAL = true;
    $pu  = pos_current_user();
    $lid = (int)$_SESSION['pos_lokal'];
    $uid = (int)$pu['id'];
    $sef = false;  // radnik na terminalu nema menadžerske opcije
    $SHELL_TOP = __DIR__ . '/../partials/kasa_top.php';
    $SHELL_BOT = __DIR__ . '/../partials/kasa_bottom.php';
} elseif (!empty($_SESSION['pos_uid'])) {
    // Ima POS sesiju ali uređaj/kolačić nije važeći → nazad na terminal prijavu
    redirect(url('kasa'));
} else {
    require_role(['vlasnik','menadzer','konobar']);
    $POS_TERMINAL = false;
    $lid = current_lokal_id();
    $uid = current_user()['id'];
    $sef = user_has_role(['vlasnik','menadzer']);
    $SHELL_TOP = __DIR__ . '/../partials/layout_top.php';
    $SHELL_BOT = __DIR__ . '/../partials/layout_bottom.php';
}

/** Preračun i keš podataka računa */
function racun_total(int $rid, int $lid): array {
    $r = db_row('SELECT * FROM racuni WHERE id=? AND lokal_id=?', [$rid,$lid]);
    if (!$r) return [];
    $stavke = db_all('SELECT * FROM racun_stavke WHERE racun_id=? ORDER BY id', [$rid]);
    $sub = 0.0; foreach ($stavke as $s) $sub += (float)$s['iznos'];
    $total = round($sub * (1 - (float)$r['popust_pct']/100), 2);
    db_run('UPDATE racuni SET ukupno=? WHERE id=?', [$total,$rid]);
    return ['racun'=>$r,'stavke'=>$stavke,'sub'=>$sub,'total'=>$total];
}
function racun_json(int $rid, int $lid): void {
    $d = racun_total($rid,$lid);
    header('Content-Type: application/json');
    echo json_encode([
        'stavke' => array_map(fn($s)=>[
            'id'=>(int)$s['id'],'naziv'=>$s['naziv'],'cena'=>(float)$s['cena'],
            'kolicina'=>(float)$s['kolicina'],'iznos'=>(float)$s['iznos']
        ], $d['stavke'] ?? []),
        'sub'=>$d['sub'] ?? 0, 'popust'=>(float)($d['racun']['popust_pct'] ?? 0), 'total'=>$d['total'] ?? 0,
    ]);
    exit;
}

// ---------------- Akcije ----------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $akcija = $_POST['akcija'] ?? '';
    $ajax = !empty($_POST['ajax']);

    if ($akcija === 'add_sto' && $sef) {
        $naziv = post('naziv'); if ($naziv==='') $naziv='Sto';
        $oblik = ($_POST['oblik'] ?? '')==='kvadrat' ? 'kvadrat' : 'krug';
        db_run('INSERT INTO stolovi (lokal_id,naziv,zona,oblik) VALUES (?,?,?,?)', [$lid,$naziv,post('zona')?:null,$oblik]);
        flash('success','Sto je dodat.'); redirect(url('pos'));
    }
    if ($akcija === 'del_sto' && $sef) {
        db_run('DELETE FROM stolovi WHERE id=? AND lokal_id=?', [(int)$_POST['id'],$lid]);
        flash('success','Sto je obrisan.'); redirect(url('pos'));
    }
    if ($akcija === 'pozicija' && $sef) {
        $sid = (int)($_POST['id'] ?? 0);
        $x = max(2, min(98, (float)($_POST['x'] ?? 0)));
        $y = max(2, min(98, (float)($_POST['y'] ?? 0)));
        db_run('UPDATE stolovi SET pos_x=?, pos_y=? WHERE id=? AND lokal_id=?', [$x,$y,$sid,$lid]);
        if (!empty($_POST['ajax'])) { echo 'ok'; exit; }
        redirect(url('pos'));
    }

    if ($akcija === 'open') {
        $stoId = (int)($_POST['sto_id'] ?? 0) ?: null;
        $rid = 0;
        if ($stoId) $rid = (int)(db_val('SELECT id FROM racuni WHERE lokal_id=? AND sto_id=? AND status="otvoren" ORDER BY id DESC LIMIT 1', [$lid,$stoId]) ?: 0);
        if (!$rid) {
            db_run('INSERT INTO racuni (lokal_id,sto_id,status,korisnik_id) VALUES (?,?,"otvoren",?)', [$lid,$stoId,$uid]);
            $rid = (int)db()->lastInsertId();
        }
        redirect(url('pos').'?racun='.$rid);
    }

    // Radi samo na otvorenom računu
    $rid = (int)($_POST['racun_id'] ?? 0);
    $r = $rid ? db_row('SELECT * FROM racuni WHERE id=? AND lokal_id=? AND status="otvoren"', [$rid,$lid]) : null;

    if ($akcija === 'add_item' && $r) {
        $art = db_row('SELECT * FROM artikli WHERE id=? AND lokal_id=?', [(int)$_POST['artikal_id'],$lid]);
        if ($art) {
            $post = db_row('SELECT * FROM racun_stavke WHERE racun_id=? AND artikal_id=? ORDER BY id DESC LIMIT 1', [$rid,$art['id']]);
            if ($post) {
                $nova = (float)$post['kolicina'] + 1;
                db_run('UPDATE racun_stavke SET kolicina=?, iznos=? WHERE id=?', [$nova, round($nova*(float)$post['cena'],2), $post['id']]);
            } else {
                db_run('INSERT INTO racun_stavke (racun_id,artikal_id,naziv,cena,kolicina,iznos) VALUES (?,?,?,?,1,?)',
                       [$rid,$art['id'],$art['naziv'],$art['prodajna_cena'],$art['prodajna_cena']]);
            }
        }
        if ($ajax) racun_json($rid,$lid); redirect(url('pos').'?racun='.$rid);
    }

    if ($akcija === 'set_qty' && $r) {
        $sid = (int)($_POST['stavka_id'] ?? 0);
        $kol = to_num($_POST['kolicina'] ?? 0);
        $st = db_row('SELECT * FROM racun_stavke WHERE id=? AND racun_id=?', [$sid,$rid]);
        if ($st) {
            if ($kol <= 0) { db_run('DELETE FROM racun_stavke WHERE id=?', [$sid]); audit('uklonjena_stavka','racun',$rid,$st['naziv']); }
            else db_run('UPDATE racun_stavke SET kolicina=?, iznos=? WHERE id=?', [$kol, round($kol*(float)$st['cena'],2), $sid]);
        }
        if ($ajax) racun_json($rid,$lid); redirect(url('pos').'?racun='.$rid);
    }

    if ($akcija === 'popust' && $r) {
        $p = max(0, min(100, to_num($_POST['popust_pct'] ?? 0)));
        $stari = (float)$r['popust_pct'];
        db_run('UPDATE racuni SET popust_pct=? WHERE id=?', [$p,$rid]);
        if ($p != $stari && $p > 0) audit('popust','racun',$rid,$p.'%');
        if ($ajax) racun_json($rid,$lid); redirect(url('pos').'?racun='.$rid);
    }

    if ($akcija === 'storno' && $r) {
        $razlog = post('razlog');
        if ($razlog === '') { flash('error','Obavezan je razlog storniranja.'); redirect(url('pos').'?racun='.$rid); }
        db_run('UPDATE racuni SET status="storniran", storno_razlog=?, closed_at=NOW() WHERE id=?', [$razlog,$rid]);
        audit('storno','racun',$rid,$razlog);
        flash('success','Račun je storniran.'); redirect(url('pos'));
    }

    // Povrat (refund) plaćenog računa — samo menadžer/vlasnik
    if ($akcija === 'refund' && $sef) {
        $refId = (int)($_POST['id'] ?? 0);
        $razlog = post('razlog');
        $rr = db_row('SELECT * FROM racuni WHERE id=? AND lokal_id=? AND status="placen"', [$refId,$lid]);
        if ($rr && $razlog !== '') {
            $st = db_all('SELECT * FROM racun_stavke WHERE racun_id=?', [$refId]);
            $pdo = db(); $pdo->beginTransaction();
            try {
                // Vrati zalihe (obrnuto od prodaje)
                foreach ($st as $s) {
                    if (!$s['artikal_id']) continue;
                    $qty = (float)$s['kolicina'];
                    $normId = (int)(db_val('SELECT id FROM normativi WHERE lokal_id=? AND artikal_id=?', [$lid,$s['artikal_id']]) ?: 0);
                    if ($normId) {
                        foreach (db_all('SELECT * FROM normativ_stavke WHERE normativ_id=?', [$normId]) as $ns) {
                            $uk = $qty * (float)$ns['kolicina'];
                            db_run('UPDATE artikli SET zaliha=zaliha+? WHERE id=? AND lokal_id=?', [$uk,$ns['sastojak_id'],$lid]);
                            db_run('INSERT INTO zalihe_promet (lokal_id,artikal_id,tip,kolicina,razlog,korisnik_id) VALUES (?,?,"ulaz",?,?,?)', [$lid,$ns['sastojak_id'],$uk,'Povrat račun #'.$refId,$uid]);
                        }
                    } else {
                        db_run('UPDATE artikli SET zaliha=zaliha+? WHERE id=? AND lokal_id=?', [$qty,$s['artikal_id'],$lid]);
                        db_run('INSERT INTO zalihe_promet (lokal_id,artikal_id,tip,kolicina,razlog,korisnik_id) VALUES (?,?,"ulaz",?,?,?)', [$lid,$s['artikal_id'],$qty,'Povrat račun #'.$refId,$uid]);
                    }
                }
                // Umanji dnevni POS pazar (za dan kad je račun naplaćen)
                $dan = date('Y-m-d', strtotime($rr['closed_at'] ?: 'now'));
                $expaz = db_row('SELECT id FROM pazar WHERE lokal_id=? AND datum=? AND napomena="POS promet" LIMIT 1', [$lid,$dan]);
                if ($expaz) db_run('UPDATE pazar SET iznos=iznos-?, kes=kes-?, kartica=kartica-? WHERE id=?',
                                   [(float)$rr['ukupno'],(float)$rr['placeno_kes'],(float)$rr['placeno_kartica'],$expaz['id']]);
                db_run('UPDATE racuni SET status="refundiran", refund_razlog=? WHERE id=?', [$razlog,$refId]);
                $pdo->commit();
                audit('refund','racun',$refId,$razlog.' ('.novac($rr['ukupno']).')');
                // NAPOMENA: fiskalni povrat (transactionType=Refund) je zaseban fiskalni dokument — radi se sa pravim PFR-om.
                flash('success','Povrat evidentiran za račun #'.$refId.'.');
            } catch (Throwable $e) { $pdo->rollBack(); flash('error','Greška: '.$e->getMessage()); }
        } else {
            flash('error','Povrat nije moguć (nepostojeći plaćen račun ili nedostaje razlog).');
        }
        redirect(url('pos'));
    }

    if ($akcija === 'premesti' && $r) {
        $target = (int)($_POST['sto_id'] ?? 0) ?: null;
        if ($target) {
            $busy = db_val('SELECT id FROM racuni WHERE lokal_id=? AND sto_id=? AND status="otvoren" AND id<>?', [$lid,$target,$rid]);
            if ($busy) { flash('error','Taj sto je zauzet — koristi „Spoji".'); redirect(url('pos').'?racun='.$rid); }
        }
        db_run('UPDATE racuni SET sto_id=? WHERE id=?', [$target,$rid]);
        flash('success','Račun je premešten.'); redirect(url('pos').'?racun='.$rid);
    }

    if ($akcija === 'spoji' && $r) {
        $cilj = (int)($_POST['cilj_racun_id'] ?? 0);
        $c = db_row('SELECT * FROM racuni WHERE id=? AND lokal_id=? AND status="otvoren" AND id<>?', [$cilj,$lid,$rid]);
        if ($c) {
            db_run('UPDATE racun_stavke SET racun_id=? WHERE racun_id=?', [$cilj,$rid]);
            db_run('DELETE FROM racuni WHERE id=?', [$rid]);
            racun_total($cilj,$lid);
            flash('success','Računi su spojeni.'); redirect(url('pos').'?racun='.$cilj);
        }
        redirect(url('pos').'?racun='.$rid);
    }

    if ($akcija === 'podeli' && $r) {
        $ids = array_values(array_filter(array_map('intval', $_POST['stavke'] ?? [])));
        if ($ids) {
            db_run('INSERT INTO racuni (lokal_id,sto_id,status,korisnik_id,napomena) VALUES (?,NULL,"otvoren",?,?)', [$lid,$uid,'Podela računa #'.$rid]);
            $new = (int)db()->lastInsertId();
            $in = implode(',', array_fill(0,count($ids),'?'));
            db_run("UPDATE racun_stavke SET racun_id=? WHERE racun_id=? AND id IN ($in)", array_merge([$new,$rid],$ids));
            racun_total($new,$lid); racun_total($rid,$lid);
            flash('success','Izabrane stavke prebačene na novi šank račun #'.$new.'.');
        }
        redirect(url('pos').'?racun='.$rid);
    }

    if ($akcija === 'naplati' && $r) {
        $d = racun_total($rid,$lid);
        $total = $d['total'];
        if (!$d['stavke']) { flash('error','Račun je prazan.'); redirect(url('pos').'?racun='.$rid); }
        // Podeljeno plaćanje: keš + kartica (ili brzo preko 'nacin')
        $kes = to_num($_POST['kes'] ?? 0);
        $kartica = to_num($_POST['kartica'] ?? 0);
        if (!empty($_POST['nacin'])) {
            if ($_POST['nacin'] === 'kartica') { $kartica = $total; $kes = 0; }
            else { $kes = $total; $kartica = 0; }
        }
        if (abs(round($kes+$kartica,2) - $total) > 0.01) {
            flash('error','Zbir plaćanja ('.novac($kes+$kartica).') ne odgovara ukupnom ('.novac($total).').');
            redirect(url('pos').'?racun='.$rid);
        }
        $nacin = ($kes>0 && $kartica>0) ? 'mesovito' : ($kartica>0 ? 'kartica' : 'kes');
        $pdo = db(); $pdo->beginTransaction();
        try {
            // Razduženje zaliha (normativ ako postoji, inače sam artikal)
            foreach ($d['stavke'] as $s) {
                if (!$s['artikal_id']) continue;
                $qty = (float)$s['kolicina'];
                $normId = (int)(db_val('SELECT id FROM normativi WHERE lokal_id=? AND artikal_id=?', [$lid,$s['artikal_id']]) ?: 0);
                if ($normId) {
                    foreach (db_all('SELECT * FROM normativ_stavke WHERE normativ_id=?', [$normId]) as $ns) {
                        $uk = $qty * (float)$ns['kolicina'];
                        db_run('UPDATE artikli SET zaliha=zaliha-? WHERE id=? AND lokal_id=?', [$uk,$ns['sastojak_id'],$lid]);
                        db_run('INSERT INTO zalihe_promet (lokal_id,artikal_id,tip,kolicina,razlog,korisnik_id) VALUES (?,?,"izlaz",?,?,?)',
                               [$lid,$ns['sastojak_id'],$uk,'Prodaja račun #'.$rid,$uid]);
                    }
                } else {
                    db_run('UPDATE artikli SET zaliha=zaliha-? WHERE id=? AND lokal_id=?', [$qty,$s['artikal_id'],$lid]);
                    db_run('INSERT INTO zalihe_promet (lokal_id,artikal_id,tip,kolicina,razlog,korisnik_id) VALUES (?,?,"izlaz",?,?,?)',
                           [$lid,$s['artikal_id'],$qty,'Prodaja račun #'.$rid,$uid]);
                }
            }
            db_run('UPDATE racuni SET status="placen", nacin_placanja=?, placeno_kes=?, placeno_kartica=?, ukupno=?, closed_at=NOW() WHERE id=?',
                   [$nacin,$kes,$kartica,$total,$rid]);
            // Pazar = DNEVNI zbir (ceo dan), NE red po računu. Upsert jednog reda za danas.
            $expaz = db_row('SELECT id FROM pazar WHERE lokal_id=? AND datum=CURDATE() AND napomena="POS promet" LIMIT 1', [$lid]);
            if ($expaz) db_run('UPDATE pazar SET iznos=iznos+?, kes=kes+?, kartica=kartica+? WHERE id=?', [$total,$kes,$kartica,$expaz['id']]);
            else db_run('INSERT INTO pazar (lokal_id,datum,smena,korisnik_id,iznos,kes,kartica,napomena) VALUES (?,CURDATE(),"cela",?,?,?,?,"POS promet")', [$lid,$uid,$total,$kes,$kartica]);
            $pdo->commit();
            audit('naplata','racun',$rid,'Iznos '.novac($total).' · '.$nacin);
            flash('success','Račun #'.$rid.' naplaćen ('.novac($total).' · '.$nacin.').');
            // Fiskalizacija (ako je uključena za lokal)
            if (fiskal_aktivna($lid)) {
                $rr = db_row('SELECT * FROM racuni WHERE id=?', [$rid]);
                if (fiskal_posalji($rid, $lid, $rr, $d['stavke'], $kes, $kartica))
                    flash('success','Fiskalni račun izdat ✓');
                else
                    flash('error','Fiskalizacija nije uspela — proveri PFR/podešavanja.');
            }
        } catch (Throwable $e) { $pdo->rollBack(); flash('error','Greška: '.$e->getMessage()); }
        redirect(url('pos'));
    }

    redirect(url('pos'));
}

$page_title = 'POS / Kasa';
$active = 'pos';

// ==================== ŠTAMPA RAČUNA (predračun) ====================
$rid = (int)($_GET['racun'] ?? 0);

// Nazad na početni: obriši prazan otvoren račun (da se ne gomilaju)
if ($rid && isset($_GET['nazad'])) {
    if ((int)db_val('SELECT COUNT(*) FROM racun_stavke WHERE racun_id=?', [$rid]) === 0)
        db_run('DELETE FROM racuni WHERE id=? AND lokal_id=? AND status="otvoren"', [$rid,$lid]);
    redirect(url('pos'));
}

if ($rid && !empty($_GET['stampa'])) {
    $rr = db_row('SELECT r.*, s.naziv AS sto FROM racuni r LEFT JOIN stolovi s ON s.id=r.sto_id WHERE r.id=? AND r.lokal_id=?', [$rid,$lid]);
    if ($rr) {
        $lok = db_row('SELECT * FROM lokali WHERE id=?', [$lid]);
        $st = db_all('SELECT * FROM racun_stavke WHERE racun_id=? ORDER BY id', [$rid]);
        $sub = 0.0; foreach ($st as $x) $sub += (float)$x['iznos'];
        $total = round($sub*(1-(float)$rr['popust_pct']/100),2);
        $placen = $rr['status']==='placen';
        ?><!DOCTYPE html><html lang="sr"><head><meta charset="utf-8"><title>Račun #<?= $rid ?></title>
        <style>
          *{margin:0;padding:0;box-sizing:border-box;font-family:'Segoe UI',Arial,sans-serif}
          body{width:80mm;margin:0 auto;padding:10px;color:#000;font-size:13px}
          .c{text-align:center}.b{font-weight:700}.r{text-align:right}
          h2{font-size:17px}hr{border:none;border-top:1px dashed #999;margin:8px 0}
          table{width:100%;border-collapse:collapse}td{padding:2px 0;vertical-align:top}
          .tot{font-size:16px;font-weight:800}
          @media print{@page{margin:4mm}}
        </style></head><body onload="window.print()">
          <div class="c"><h2><?= e($lok['naziv']) ?></h2>
            <?php if($lok['adresa']||$lok['grad']):?><div><?= e(trim(($lok['adresa']??'').' '.($lok['grad']??''))) ?></div><?php endif;?>
            <?php if($lok['pib']):?><div>PIB: <?= e($lok['pib']) ?></div><?php endif;?></div>
          <hr>
          <div class="c b"><?= $placen?'RAČUN':'PREDRAČUN' ?> #<?= $rid ?></div>
          <div class="c"><?= e($rr['sto'] ?: 'Šank') ?> · <?= date('d.m.Y. H:i') ?></div>
          <hr>
          <table>
            <?php foreach($st as $x): ?>
              <tr><td colspan="2" class="b"><?= e($x['naziv']) ?></td></tr>
              <tr><td><?= rtrim(rtrim(number_format((float)$x['kolicina'],3,',','.'),'0'),',') ?> × <?= novac($x['cena']) ?></td><td class="r"><?= novac($x['iznos']) ?></td></tr>
            <?php endforeach; ?>
          </table>
          <hr>
          <?php if($rr['popust_pct']>0): ?><table><tr><td>Popust <?= (int)$rr['popust_pct'] ?>%</td><td class="r">−<?= novac($sub-$total) ?></td></tr></table><?php endif; ?>
          <table><tr><td class="tot">UKUPNO</td><td class="r tot"><?= novac($total) ?></td></tr></table>
          <?php if($placen):?><div class="c" style="margin-top:6px">Plaćeno: <?= e(ucfirst($rr['nacin_placanja'] ?: '')) ?></div><?php endif;?>
          <?php if(!empty($rr['fiskalizovan'])): ?>
            <hr>
            <div class="c" style="font-size:11px">
              <span class="b">ФИСКАЛНИ РАЧУН</span><br>
              ПФР број: <?= e($rr['pfr_broj']) ?><br>
              Бројач: <?= e($rr['pfr_brojac']) ?><br>
              <?= e($rr['pfr_vreme']) ?><br>
              <?php if(!empty($rr['pfr_qr'])): ?><img src="<?= e($rr['pfr_qr']) ?>" style="width:120px;height:120px;margin:6px 0"><br><?php endif; ?>
              <?php if(!empty($rr['pfr_url_ver'])): ?><span style="word-break:break-all;font-size:9px"><?= e($rr['pfr_url_ver']) ?></span><?php endif; ?>
            </div>
          <?php endif; ?>
          <hr><div class="c" style="font-size:11px">Hvala i doviđenja!<br><?php if(empty($rr['fiskalizovan'])):?>Ovo nije fiskalni račun.<?php endif;?></div>
        </body></html>
        <?php
        exit;
    }
}

// ==================== EKRAN RAČUNA ====================
if ($rid) {
    $d = racun_total($rid,$lid);
    if (!$d || $d['racun']['status'] !== 'otvoren') { redirect(url('pos')); }
    $r = $d['racun'];
    $stoNaziv = $r['sto_id'] ? db_val('SELECT naziv FROM stolovi WHERE id=?', [$r['sto_id']]) : 'Šank / brza prodaja';
    $kategorije = db_all('SELECT * FROM kategorije WHERE lokal_id=? ORDER BY naziv', [$lid]);
    $artikli = db_all('SELECT * FROM artikli WHERE lokal_id=? AND aktivan=1 ORDER BY naziv', [$lid]);
    $freeTables = db_all('SELECT s.* FROM stolovi s WHERE s.lokal_id=? AND s.id NOT IN
                          (SELECT sto_id FROM racuni WHERE lokal_id=? AND status="otvoren" AND sto_id IS NOT NULL) ORDER BY s.naziv', [$lid,$lid]);
    $openOther = db_all('SELECT r.id, COALESCE(s.naziv,"Šank") AS naziv, r.ukupno FROM racuni r
                         LEFT JOIN stolovi s ON s.id=r.sto_id WHERE r.lokal_id=? AND r.status="otvoren" AND r.id<>? ORDER BY r.id', [$lid,$rid]);

    require $SHELL_TOP;
    ?>
    <div class="page-head">
      <div><a href="<?= url('pos') ?>?racun=<?= $rid ?>&nazad=1" class="btn btn--ghost btn--sm" style="margin-bottom:8px"><?= ico('back',16) ?> Nazad</a>
        <h1><?= e($stoNaziv) ?></h1><p>Račun #<?= $rid ?> · otvoren</p></div>
    </div>

    <div class="toolbar" style="margin-bottom:14px">
      <a class="btn btn--ghost btn--sm" href="<?= url('pos') ?>?racun=<?= $rid ?>&stampa=1" target="_blank"><?= ico('print',16) ?> Predračun</a>
      <button class="btn btn--ghost btn--sm" onclick="mPremesti.showModal()"><?= ico('move',16) ?> Premesti sto</button>
      <?php if ($openOther): ?><button class="btn btn--ghost btn--sm" onclick="mSpoji.showModal()"><?= ico('merge',16) ?> Spoji</button><?php endif; ?>
      <button class="btn btn--ghost btn--sm" onclick="openPodeli()"><?= ico('split',16) ?> Podeli</button>
    </div>

    <div class="pos-layout">
      <!-- Korpa -->
      <div class="pos-cart card">
        <div class="card__head"><div class="card__title">Račun</div>
          <form method="post" onsubmit="return ukPromptSubmit(this,'razlog','Unesi razlog storniranja računa:',{title:'Storno računa',ok:'Storniraj',busy:'Storniram…'})" style="margin:0"><?= csrf_field() ?><input type="hidden" name="akcija" value="storno"><input type="hidden" name="racun_id" value="<?= $rid ?>"><input type="hidden" name="razlog">
            <button class="btn btn--ghost btn--sm" style="color:var(--danger)"><?= ico('storno',15) ?> Storno</button></form></div>
        <div id="cartItems" class="pos-cart__items"></div>
        <div class="pos-cart__foot">
          <div class="pos-cart__line"><span>Međuzbir</span><span id="cartSub">0,00</span></div>
          <div class="pos-cart__line">
            <span>Popust</span>
            <span class="flex items-center gap-2"><input class="input" id="popustInp" type="number" min="0" max="100" step="1" value="<?= (int)$r['popust_pct'] ?>" style="width:64px;padding:6px 8px;text-align:right" onchange="setPopust(this.value)"> %</span>
          </div>
          <div class="pos-cart__total"><span>Ukupno</span><span class="amt" id="cartTotal">0,00</span></div>
          <div class="pos-pay">
            <button class="btn btn--ghost" onclick="naplati('kes')"><?= ico('cash',18) ?> Keš</button>
            <button class="btn btn--ghost" onclick="naplati('kartica')"><?= ico('card',18) ?> Kartica</button>
            <button class="pos-pay__split" onclick="openSplit()"><?= ico('split',18) ?> Podeli plaćanje</button>
          </div>
        </div>
      </div>

      <!-- Proizvodi -->
      <div class="pos-products">
        <div class="tabs" id="catTabs" style="margin-bottom:14px;flex-wrap:wrap">
          <a href="#" class="is-active" data-cat="0" onclick="filterCat(0,this);return false">Sve</a>
          <?php foreach ($kategorije as $k): ?><a href="#" data-cat="<?= $k['id'] ?>" onclick="filterCat(<?= $k['id'] ?>,this);return false"><?= e($k['naziv']) ?></a><?php endforeach; ?>
        </div>
        <div class="pgrid" id="prodGrid">
          <?php foreach ($artikli as $a):
            $b = $a['boja'] ?: '#0d9488';
            $style = $a['slika'] ? "background-image:url('".e($a['slika'])."')" : "background:linear-gradient(135deg,$b,{$b}cc)";
          ?>
            <div class="ptile" data-cat="<?= (int)$a['kategorija_id'] ?>" onclick="addItem(<?= $a['id'] ?>)">
              <div class="ptile__top" style="height:70px;<?= $style ?>">
                <?php if(!$a['slika']): ?><span class="ptile__initial" style="font-size:1.5rem"><?= e(mb_strtoupper(mb_substr($a['naziv'],0,1))) ?></span><?php endif; ?>
              </div>
              <div class="ptile__body" style="padding:8px 10px">
                <div class="ptile__name" style="font-size:.82rem"><?= e($a['naziv']) ?></div>
                <div class="ptile__price" style="font-size:.9rem"><?= novac($a['prodajna_cena']) ?></div>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>

    <dialog id="mPremesti" class="modal">
      <form method="post" action="<?= url('pos') ?>"><?= csrf_field() ?><input type="hidden" name="akcija" value="premesti"><input type="hidden" name="racun_id" value="<?= $rid ?>">
        <div class="card__head"><div class="card__title">Premesti račun</div><button type="button" class="btn btn--ghost btn--sm" onclick="mPremesti.close()">✕</button></div>
        <div class="card__body"><div class="field"><label class="label">Novi sto</label>
          <select class="select" name="sto_id"><option value="0">Šank (bez stola)</option>
            <?php foreach ($freeTables as $t): if($t['id']==$r['sto_id'])continue; ?><option value="<?= $t['id'] ?>"><?= e($t['naziv']) ?></option><?php endforeach; ?></select>
          <div class="help">Prikazani su samo slobodni stolovi.</div></div></div>
        <div class="modal__foot"><button type="button" class="btn btn--ghost" onclick="mPremesti.close()">Otkaži</button><button class="btn btn--primary">Premesti</button></div>
      </form>
    </dialog>

    <?php if ($openOther): ?>
    <dialog id="mSpoji" class="modal">
      <form method="post" action="<?= url('pos') ?>"><?= csrf_field() ?><input type="hidden" name="akcija" value="spoji"><input type="hidden" name="racun_id" value="<?= $rid ?>">
        <div class="card__head"><div class="card__title">Spoji sa računom</div><button type="button" class="btn btn--ghost btn--sm" onclick="mSpoji.close()">✕</button></div>
        <div class="card__body"><p class="muted" style="margin-top:0">Stavke ovog računa prelaze na izabrani, a ovaj se zatvara.</p>
          <div class="field"><label class="label">Spoji u</label>
            <select class="select" name="cilj_racun_id">
              <?php foreach ($openOther as $o): ?><option value="<?= $o['id'] ?>"><?= e($o['naziv']) ?> · račun #<?= $o['id'] ?> (<?= novac($o['ukupno']) ?>)</option><?php endforeach; ?></select></div></div>
        <div class="modal__foot"><button type="button" class="btn btn--ghost" onclick="mSpoji.close()">Otkaži</button><button class="btn btn--primary">Spoji</button></div>
      </form>
    </dialog>
    <?php endif; ?>

    <dialog id="mPodeli" class="modal">
      <form method="post" action="<?= url('pos') ?>"><?= csrf_field() ?><input type="hidden" name="akcija" value="podeli"><input type="hidden" name="racun_id" value="<?= $rid ?>">
        <div class="card__head"><div class="card__title">Podeli račun</div><button type="button" class="btn btn--ghost btn--sm" onclick="mPodeli.close()">✕</button></div>
        <div class="card__body"><p class="muted" style="margin-top:0">Izaberi stavke koje idu na novi (šank) račun.</p>
          <div id="podeliList"></div></div>
        <div class="modal__foot"><button type="button" class="btn btn--ghost" onclick="mPodeli.close()">Otkaži</button><button class="btn btn--primary">Podeli izabrane</button></div>
      </form>
    </dialog>

    <form id="naplataForm" method="post" style="display:none"><?= csrf_field() ?><input type="hidden" name="akcija" value="naplati"><input type="hidden" name="racun_id" value="<?= $rid ?>"><input type="hidden" name="nacin"><input type="hidden" name="kes"><input type="hidden" name="kartica"></form>

    <dialog id="mSplit" class="modal">
      <div class="card__head"><div class="card__title">Podeljeno plaćanje</div><button type="button" class="btn btn--ghost btn--sm" onclick="mSplit.close()">✕</button></div>
      <div class="card__body">
        <div style="text-align:right;font-size:1.3rem;font-weight:800;margin-bottom:14px">Ukupno: <span id="splitTotal">0,00</span></div>
        <div class="form-row">
          <div class="field"><label class="label"><?= ico('cash',16) ?> Keš</label><input class="input" type="number" step="0.01" id="splitKes" oninput="splitCalc('kes')"></div>
          <div class="field"><label class="label"><?= ico('card',16) ?> Kartica</label><input class="input" type="number" step="0.01" id="splitKartica" oninput="splitCalc('kartica')"></div>
        </div>
        <div class="help" id="splitInfo"></div>
      </div>
      <div class="modal__foot"><button type="button" class="btn btn--ghost" onclick="mSplit.close()">Otkaži</button><button type="button" class="btn btn--primary" onclick="naplatiSplit()">Naplati</button></div>
    </dialog>

    <script>
    const RID=<?= $rid ?>, CSRF=<?= json_encode(csrf_token()) ?>;
    function fmt(n){return (n||0).toLocaleString('sr-RS',{minimumFractionDigits:2,maximumFractionDigits:2});}
    async function api(akcija,extra){
      const body=new URLSearchParams(Object.assign({akcija,ajax:1,racun_id:RID,_csrf:CSRF},extra||{}));
      const res=await fetch('<?= url('pos') ?>',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body});
      return res.json();
    }
    function render(d){
      window.CART=d;
      const box=document.getElementById('cartItems');
      if(!d.stavke.length){box.innerHTML='<div class="empty" style="padding:30px 10px">Dodaj proizvode dodirom →</div>';}
      else{box.innerHTML=d.stavke.map(s=>`
        <div class="cart-row">
          <div class="cart-row__info"><div class="cart-row__name">${s.naziv}</div><div class="cart-row__price muted">${fmt(s.cena)} × ${(+s.kolicina).toString().replace('.',',')}</div></div>
          <div class="cart-row__qty">
            <button onclick="setQty(${s.id},${(+s.kolicina-1)})">−</button>
            <span>${(+s.kolicina).toString().replace('.',',')}</span>
            <button onclick="setQty(${s.id},${(+s.kolicina+1)})">+</button>
          </div>
          <div class="cart-row__sum">${fmt(s.iznos)}</div>
          <button class="cart-row__del" onclick="setQty(${s.id},0)">×</button>
        </div>`).join('');}
      var ct=document.getElementById('cartTotal'); ct.textContent=fmt(d.total);
      ct.classList.remove('bump'); void ct.offsetWidth; ct.classList.add('bump');
      var sub=document.getElementById('cartSub'); if(sub) sub.textContent=fmt(d.sub);
      document.getElementById('popustInp').value=Math.round(d.popust);
    }
    async function addItem(id){render(await api('add_item',{artikal_id:id}));}
    async function setQty(sid,kol){render(await api('set_qty',{stavka_id:sid,kolicina:kol}));}
    async function setPopust(p){render(await api('popust',{popust_pct:p}));}
    function filterCat(cat,el){
      document.querySelectorAll('#catTabs a').forEach(a=>a.classList.remove('is-active'));el.classList.add('is-active');
      document.querySelectorAll('#prodGrid .ptile').forEach(t=>{t.style.display=(cat==0||t.dataset.cat==cat)?'':'none';});
    }
    function naplati(nacin){
      if(!document.querySelectorAll('#cartItems .cart-row').length){SankUI.toast('Račun je prazan.','error');return;}
      SankUI.confirm('Naplatiti račun ('+(nacin==='kes'?'keš':'kartica')+')?',{title:'Naplata',ok:'Naplati'}).then(function(ok){
        if(!ok)return; var f=document.getElementById('naplataForm'); f.nacin.value=nacin; f.kes.value=''; f.kartica.value='';
        SankUI.loading('Naplaćujem…'); f.submit();
      });
    }
    function openSplit(){
      if(!window.CART||!window.CART.stavke.length){SankUI.toast('Račun je prazan.','error');return;}
      var t=window.CART.total; document.getElementById('splitTotal').textContent=fmt(t);
      document.getElementById('splitKes').value=''; document.getElementById('splitKartica').value=t.toFixed(2);
      splitCalc('kartica'); document.getElementById('mSplit').showModal();
    }
    function splitCalc(edited){
      var t=window.CART.total;
      var kes=parseFloat(document.getElementById('splitKes').value)||0;
      var kar=parseFloat(document.getElementById('splitKartica').value)||0;
      if(edited==='kes'){ kar=Math.max(0,+(t-kes).toFixed(2)); document.getElementById('splitKartica').value=kar.toFixed(2); }
      else { kes=Math.max(0,+(t-kar).toFixed(2)); document.getElementById('splitKes').value=kes.toFixed(2); }
      var zbir=+(kes+kar).toFixed(2);
      document.getElementById('splitInfo').innerHTML = Math.abs(zbir-t)<0.01 ? '<span style="color:var(--ok);font-weight:700">Zbir se poklapa</span>' : ('Zbir: '+fmt(zbir)+' (treba '+fmt(t)+')');
    }
    function naplatiSplit(){
      var kes=parseFloat(document.getElementById('splitKes').value)||0, kar=parseFloat(document.getElementById('splitKartica').value)||0;
      var f=document.getElementById('naplataForm'); f.nacin.value=''; f.kes.value=kes; f.kartica.value=kar;
      SankUI.loading('Naplaćujem…'); f.submit();
    }
    function openPodeli(){
      const st=(window.CART&&window.CART.stavke)||[];
      if(!st.length){SankUI.toast('Račun je prazan.','error');return;}
      document.getElementById('podeliList').innerHTML=st.map(s=>`
        <label class="flex items-center gap-2" style="padding:8px;border:1px solid var(--border);border-radius:9px;margin-bottom:6px;cursor:pointer">
          <input type="checkbox" name="stavke[]" value="${s.id}">
          <span style="flex:1">${s.naziv} <span class="muted">×${(+s.kolicina).toString().replace('.',',')}</span></span>
          <strong>${fmt(s.iznos)}</strong></label>`).join('');
      document.getElementById('mPodeli').showModal();
    }
    // Init
    (async()=>{render(await api('popust',{popust_pct:<?= (int)$r['popust_pct'] ?>}));})();
    </script>
    <?php
    require $SHELL_BOT;
    return;
}

// ==================== SVI RAČUNI ====================
if (isset($_GET['racuni'])) {
    $fst = $_GET['status'] ?? 'sve';
    $dan = $_GET['dan'] ?? date('Y-m-d');
    $where = 'r.lokal_id=?'; $par = [$lid];
    if ($dan !== 'sve') { $where .= ' AND DATE(r.created_at)=?'; $par[] = $dan; }
    if (in_array($fst, ['otvoren','placen','storniran','refundiran'], true)) { $where .= ' AND r.status=?'; $par[] = $fst; }
    $lista = db_all("SELECT r.*, COALESCE(s.naziv,'Šank') AS sto, TRIM(CONCAT(COALESCE(k.ime,''),' ',COALESCE(k.prezime,''))) AS konobar
                     FROM racuni r LEFT JOIN stolovi s ON s.id=r.sto_id LEFT JOIN korisnici k ON k.id=r.korisnik_id
                     WHERE $where ORDER BY r.id DESC LIMIT 300", $par);
    $stBadge = ['otvoren'=>['Otvoren','warn'],'placen'=>['Plaćen','ok'],'storniran'=>['Storniran','muted'],'refundiran'=>['Refundiran','danger']];
    $qbase = url('pos').'?racuni=1&dan='.urlencode($dan);

    require $SHELL_TOP;
    ?>
    <div class="page-head">
      <div><a href="<?= url('pos') ?>" class="btn btn--ghost btn--sm" style="margin-bottom:8px"><?= ico('back',16) ?> Nazad</a>
        <h1>Računi</h1><p>Pregled računa, kopija, storno i povrat.</p></div>
    </div>
    <form class="toolbar" method="get" action="<?= url('pos') ?>">
      <input type="hidden" name="racuni" value="1">
      <input class="input" type="date" name="dan" value="<?= $dan==='sve'?'':e($dan) ?>" onchange="this.form.submit()" style="width:auto">
      <a class="btn btn--ghost btn--sm" href="<?= url('pos') ?>?racuni=1&dan=sve&status=<?= e($fst) ?>">Svi datumi</a>
      <div class="spacer"></div>
      <div class="tabs">
        <?php foreach (['sve'=>'Svi','otvoren'=>'Otvoreni','placen'=>'Plaćeni','storniran'=>'Storno','refundiran'=>'Povrat'] as $k=>$lbl): ?>
          <a href="<?= $qbase ?>&status=<?= $k ?>" class="<?= $fst===$k?'is-active':'' ?>"><?= $lbl ?></a>
        <?php endforeach; ?>
      </div>
    </form>
    <div class="card"><div class="table-wrap"><table class="table">
      <thead><tr><th>#</th><th>Sto</th><th>Vreme</th><th>Konobar</th><th class="num">Iznos</th><th>Način</th><th>Status</th><th></th></tr></thead>
      <tbody>
      <?php if (!$lista): ?><tr><td colspan="8"><div class="empty">Nema računa za izabrani filter.</div></td></tr>
      <?php else: foreach ($lista as $rc): [$sl,$sc] = $stBadge[$rc['status']] ?? [$rc['status'],'muted']; ?>
        <tr>
          <td><strong>#<?= (int)$rc['id'] ?></strong> <?php if($rc['fiskalizovan']):?><span class="badge badge--ok" title="Fiskalizovan"><?= ico('check',12) ?></span><?php endif;?></td>
          <td><?= e($rc['sto']) ?></td>
          <td class="muted"><?= date('d.m. H:i', strtotime($rc['closed_at'] ?: $rc['created_at'])) ?></td>
          <td class="muted"><?= e($rc['konobar'] ?: '—') ?></td>
          <td class="num"><?= novac($rc['ukupno']) ?></td>
          <td><?= $rc['nacin_placanja'] ? '<span class="badge badge--muted">'.e(ucfirst($rc['nacin_placanja'])).'</span>' : '—' ?></td>
          <td><span class="badge badge--<?= $sc ?>"><?= e($sl) ?></span></td>
          <td class="text-right" style="white-space:nowrap">
            <a class="btn btn--ghost btn--sm" href="<?= url('pos') ?>?racun=<?= (int)$rc['id'] ?>&stampa=1" target="_blank" title="Kopija"><?= ico('print',15) ?></a>
            <?php if ($rc['status']==='otvoren'): ?>
              <a class="btn btn--ghost btn--sm" href="<?= url('pos') ?>?racun=<?= (int)$rc['id'] ?>">Otvori</a>
              <form method="post" style="display:inline" onsubmit="return ukPromptSubmit(this,'razlog','Razlog storniranja:',{title:'Storno',ok:'Storniraj',danger:true})"><?= csrf_field() ?><input type="hidden" name="akcija" value="storno"><input type="hidden" name="racun_id" value="<?= (int)$rc['id'] ?>"><input type="hidden" name="razlog">
                <button class="btn btn--ghost btn--sm" style="color:var(--danger)"><?= ico('storno',15) ?></button></form>
            <?php elseif ($rc['status']==='placen' && $sef): ?>
              <form method="post" style="display:inline" onsubmit="return ukPromptSubmit(this,'razlog','Razlog povrata:',{title:'Povrat',ok:'Povrat',danger:true})"><?= csrf_field() ?><input type="hidden" name="akcija" value="refund"><input type="hidden" name="id" value="<?= (int)$rc['id'] ?>"><input type="hidden" name="razlog">
                <button class="btn btn--ghost btn--sm" style="color:var(--danger)"><?= ico('refund',15) ?> Povrat</button></form>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; endif; ?>
      </tbody>
    </table></div></div>
    <?php
    require $SHELL_BOT;
    return;
}

// ==================== POČETNI EKRAN (STOLOVI) ====================
$stolovi = db_all('SELECT s.*, r.id AS racun_id, r.ukupno AS racun_ukupno
                   FROM stolovi s
                   LEFT JOIN racuni r ON r.sto_id=s.id AND r.status="otvoren"
                   WHERE s.lokal_id=? ORDER BY s.redosled, s.naziv', [$lid]);
$sankRacuni = db_all('SELECT * FROM racuni WHERE lokal_id=? AND sto_id IS NULL AND status="otvoren" ORDER BY id DESC', [$lid]);
$brOtvorenih = (int)db_val('SELECT COUNT(*) FROM racuni WHERE lokal_id=? AND status="otvoren"', [$lid]);
$otvorenoUk = (float)db_val('SELECT COALESCE(SUM(ukupno),0) FROM racuni WHERE lokal_id=? AND status="otvoren"', [$lid]);
$danasProdato = (float)db_val('SELECT COALESCE(SUM(ukupno),0) FROM racuni WHERE lokal_id=? AND status="placen" AND DATE(closed_at)=CURDATE()', [$lid]);
$danasnji = $sef ? db_all('SELECT r.*, COALESCE(s.naziv,"Šank") AS sto FROM racuni r LEFT JOIN stolovi s ON s.id=r.sto_id
                           WHERE r.lokal_id=? AND r.status="placen" AND DATE(r.closed_at)=CURDATE() ORDER BY r.closed_at DESC LIMIT 30', [$lid]) : [];

require $SHELL_TOP;
?>
<div class="page-head">
  <div><h1>POS / Kasa</h1><p>Brzi račun, stolovi i računi.</p></div>
  <div class="flex gap-2">
    <a class="btn btn--ghost" href="<?= url('pos') ?>?racuni=1"><?= ico('receipt',18) ?> Računi</a>
    <form method="post" style="margin:0" onsubmit="SankUI.loading('Otvaram…')"><?= csrf_field() ?><input type="hidden" name="akcija" value="open"><input type="hidden" name="sto_id" value="0">
      <button class="btn btn--primary"><?= ico('bolt',18) ?> Brzi račun</button></form>
  </div>
</div>

<div class="stats mb-2">
  <div class="stat"><div class="stat__label">Otvoreni računi</div><div class="stat__value"><?= $brOtvorenih ?></div>
    <div class="stat__delta muted"><?= novac($otvorenoUk) ?> u toku</div></div>
  <div class="stat"><div class="stat__label">Prodato danas (POS)</div><div class="stat__value in"><?= novac($danasProdato) ?></div></div>
</div>

<?php if ($sankRacuni): ?>
<div class="card mb-2"><div class="card__head"><div class="card__title">Otvoreni šank računi</div></div>
  <div class="card__body"><div class="flex gap-2" style="flex-wrap:wrap">
    <?php foreach ($sankRacuni as $sr): ?>
      <a class="btn btn--ghost" href="<?= url('pos') ?>?racun=<?= $sr['id'] ?>">Račun #<?= $sr['id'] ?> · <?= novac($sr['ukupno']) ?></a>
    <?php endforeach; ?>
  </div></div>
</div>
<?php endif; ?>

<?php if ($danasnji): ?>
<div class="card mb-2">
  <div class="card__head"><div class="card__title">Današnji računi (naplaćeni)</div></div>
  <div class="table-wrap"><table class="table">
    <thead><tr><th>#</th><th>Sto</th><th>Vreme</th><th>Način</th><th class="num">Iznos</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($danasnji as $rc): ?>
      <tr>
        <td><strong>#<?= (int)$rc['id'] ?></strong> <?php if($rc['fiskalizovan']):?><span class="badge badge--ok" title="Fiskalizovan">F</span><?php endif;?></td>
        <td><?= e($rc['sto']) ?></td>
        <td class="muted"><?= date('H:i', strtotime($rc['closed_at'])) ?></td>
        <td><span class="badge badge--muted"><?= e(ucfirst($rc['nacin_placanja'] ?: '')) ?></span></td>
        <td class="num"><?= novac($rc['ukupno']) ?></td>
        <td class="text-right" style="white-space:nowrap">
          <a class="btn btn--ghost btn--sm" href="<?= url('pos') ?>?racun=<?= (int)$rc['id'] ?>&stampa=1" target="_blank"><?= ico('print',15) ?></a>
          <form method="post" style="display:inline" onsubmit="return ukPromptSubmit(this,'razlog','Razlog povrata računa:',{title:'Povrat računa',ok:'Povrat',danger:true,busy:'Obrađujem povrat…'})"><?= csrf_field() ?><input type="hidden" name="akcija" value="refund"><input type="hidden" name="id" value="<?= (int)$rc['id'] ?>"><input type="hidden" name="razlog">
            <button class="btn btn--ghost btn--sm" style="color:var(--danger)"><?= ico('refund',15) ?> Povrat</button></form>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
</div>
<?php endif; ?>

<?php
  // zone za filter + default pozicije za stolove na 0,0
  $zone = [];
  foreach ($stolovi as $s) { $z = trim((string)($s['zona'] ?? '')); if ($z!=='' && !in_array($z,$zone,true)) $zone[] = $z; }
  $defIdx = 0;
?>
<div class="card">
  <div class="card__head">
    <div class="card__title"><?= ico('tables',18) ?> Mapa lokala</div>
    <div class="flex gap-2 items-center" style="flex-wrap:wrap">
      <?php if ($zone): ?>
      <div class="tabs" id="zoneTabs">
        <a href="#" class="is-active" data-zona="" onclick="filterZona('',this);return false">Sve</a>
        <?php foreach ($zone as $z): ?><a href="#" data-zona="<?= e($z) ?>" onclick="filterZona('<?= e($z) ?>',this);return false"><?= e($z) ?></a><?php endforeach; ?>
      </div>
      <?php endif; ?>
      <?php if ($sef): ?>
        <button class="btn btn--ghost btn--sm" id="editBtn" onclick="toggleEdit()"><?= ico('move',15) ?> Rasporedi</button>
        <button class="btn btn--ghost btn--sm" onclick="mSto.showModal()"><?= ico('plus',16) ?> Dodaj sto</button>
      <?php endif; ?>
    </div>
  </div>
  <div class="card__body">
    <?php if (!$stolovi): ?>
      <div class="empty">Nema stolova. <?= $sef?'Dodaj sto pa ga rasporedi po tlocrtu (ili koristi „Brzi račun" za šank).':'' ?></div>
    <?php else: ?>
      <div class="floor" id="floor">
        <?php foreach ($stolovi as $s):
          $zauzet = !empty($s['racun_id']);
          $x=(float)$s['pos_x']; $y=(float)$s['pos_y'];
          if ($x==0 && $y==0) { $col=$defIdx%6; $row=intdiv($defIdx,6); $x=10+$col*15; $y=15+$row*24; $defIdx++; }
        ?>
          <div class="floor-table <?= $zauzet?'is-busy':'' ?> <?= $s['oblik']==='kvadrat'?'is-square':'' ?>"
               data-id="<?= $s['id'] ?>" data-zona="<?= e($s['zona']) ?>"
               style="left:<?= $x ?>%;top:<?= $y ?>%" onclick="stoClick(<?= $s['id'] ?>)">
            <?php if($sef): ?><span class="floor-table__del" onclick="event.stopPropagation();delSto(event,<?= $s['id'] ?>)"><?= ico('x',13) ?></span><?php endif; ?>
            <span class="floor-table__name"><?= e($s['naziv']) ?></span>
            <span class="floor-table__st"><?= $zauzet?novac($s['racun_ukupno']):'slobodan' ?></span>
          </div>
        <?php endforeach; ?>
      </div>
      <?php if ($sef): ?><div class="help no-print" id="editHint" style="display:none;margin-top:10px">Prevuci stolove da ih rasporediš po sali. Klikni „Rasporedi" ponovo da završiš (čuva se automatski).</div><?php endif; ?>
    <?php endif; ?>
  </div>
</div>
<form id="openStoForm" method="post" style="display:none" onsubmit="SankUI.loading('Otvaram…')"><?= csrf_field() ?><input type="hidden" name="akcija" value="open"><input type="hidden" name="sto_id" id="openStoId"></form>
<script>
var CSRF_TOKEN=<?= json_encode(csrf_token()) ?>, EDIT=false;
function stoClick(id){ if(EDIT)return; document.getElementById('openStoId').value=id; document.getElementById('openStoForm').submit(); }
function filterZona(z,el){ document.querySelectorAll('#zoneTabs a').forEach(function(a){a.classList.remove('is-active');}); el.classList.add('is-active');
  document.querySelectorAll('#floor .floor-table').forEach(function(t){ t.style.display=(!z||t.dataset.zona===z)?'':'none'; }); }
function toggleEdit(){ EDIT=!EDIT; var f=document.getElementById('floor'); if(f)f.classList.toggle('is-edit',EDIT);
  var h=document.getElementById('editHint'); if(h)h.style.display=EDIT?'block':'none';
  document.getElementById('editBtn').classList.toggle('btn--primary',EDIT);
  if(!EDIT)SankUI.toast('Raspored sačuvan','success'); }
(function(){ var floor=document.getElementById('floor'); if(!floor)return; var drag=null;
  floor.addEventListener('pointerdown',function(e){ if(!EDIT)return; var t=e.target.closest('.floor-table'); if(!t)return; drag=t; try{t.setPointerCapture(e.pointerId);}catch(x){} t.classList.add('dragging'); e.preventDefault(); });
  floor.addEventListener('pointermove',function(e){ if(!drag)return; var r=floor.getBoundingClientRect(); var x=(e.clientX-r.left)/r.width*100, y=(e.clientY-r.top)/r.height*100; x=Math.max(4,Math.min(96,x)); y=Math.max(7,Math.min(93,y)); drag.style.left=x+'%'; drag.style.top=y+'%'; });
  floor.addEventListener('pointerup',function(e){ if(!drag)return; var t=drag; drag=null; t.classList.remove('dragging'); var x=parseFloat(t.style.left), y=parseFloat(t.style.top);
    fetch('<?= url('pos') ?>',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:new URLSearchParams({akcija:'pozicija',ajax:1,id:t.dataset.id,x:x.toFixed(2),y:y.toFixed(2),_csrf:CSRF_TOKEN})}); });
})();
</script>

<?php if ($sef): ?>
<dialog id="mSto" class="modal">
  <form method="post" action="<?= url('pos') ?>"><?= csrf_field() ?><input type="hidden" name="akcija" value="add_sto">
    <div class="card__head"><div class="card__title">Novi sto</div><button type="button" class="btn btn--ghost btn--sm" onclick="mSto.close()">✕</button></div>
    <div class="card__body">
      <div class="form-row">
        <div class="field"><label class="label">Naziv / broj *</label><input class="input" name="naziv" required placeholder="npr. Sto 1"></div>
        <div class="field"><label class="label">Zona</label><input class="input" name="zona" placeholder="bašta, sala, sprat…"></div>
      </div>
      <div class="field"><label class="label">Oblik</label>
        <select class="select" name="oblik"><option value="krug">Okrugli</option><option value="kvadrat">Kvadratni</option></select>
        <div class="help">Sto ćeš rasporediti prevlačenjem na mapi.</div></div>
    </div>
    <div class="modal__foot"><button type="button" class="btn btn--ghost" onclick="mSto.close()">Otkaži</button><button class="btn btn--primary">Dodaj</button></div>
  </form>
</dialog>
<form id="delStoForm" method="post" style="display:none"><?= csrf_field() ?><input type="hidden" name="akcija" value="del_sto"><input type="hidden" name="id" id="delStoId"></form>
<script>
function delSto(e,id){e.preventDefault();SankUI.confirm('Obrisati ovaj sto?',{title:'Brisanje stola',ok:'Obriši',danger:true}).then(function(ok){if(!ok)return;document.getElementById('delStoId').value=id;SankUI.loading('Brišem…');document.getElementById('delStoForm').submit();});}
</script>
<?php endif; ?>

<?php require $SHELL_BOT; ?>
