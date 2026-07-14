<?php
/**
 * WAITER — Offline sync API (POS uređaj).
 *  GET  ?katalog=1  → katalog (artikli/kategorije/lokal) + CSRF za sync
 *  POST data=JSON   → prijem offline računa (idempotentno preko uuid-a)
 * Autorizacija: aktiviran POS uređaj (kolačić sank_pos_token).
 */
header('Content-Type: application/json; charset=utf-8');

$device = pos_current_device();
if (!$device) { http_response_code(403); echo json_encode(['err'=>'Uređaj nije aktiviran.']); exit; }
$lid = (int)$device['lokal_id'];

// ---------- KATALOG ----------
if (isset($_GET['katalog'])) {
    echo json_encode([
        'csrf'       => csrf_token(),
        'lokal'      => db_row('SELECT naziv,boja,valuta,adresa,grad,pib FROM lokali WHERE id=?', [$lid]),
        'kategorije' => db_all('SELECT id,naziv,boja FROM kategorije WHERE lokal_id=? ORDER BY naziv', [$lid]),
        'artikli'    => db_all('SELECT id,naziv,prodajna_cena,kategorija_id,boja,jedinica_mere FROM artikli
                                WHERE lokal_id=? AND aktivan=1 AND prodajna_cena>0 ORDER BY naziv', [$lid]),
        'ts'         => date('c'),
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// ---------- SYNC RAČUNA ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $data = json_decode((string)($_POST['data'] ?? ''), true);
    $pu = pos_current_user();
    $uid = $pu ? (int)$pu['id'] : null;
    $rez = [];

    foreach ((array)($data['racuni'] ?? []) as $rc) {
        $uuid = substr(preg_replace('/[^a-fA-F0-9\-]/', '', (string)($rc['uuid'] ?? '')), 0, 36);
        if (strlen($uuid) < 10) { $rez[] = ['uuid'=>$uuid, 'status'=>'error', 'msg'=>'neispravan uuid']; continue; }
        if (db_val('SELECT id FROM racuni WHERE uuid=?', [$uuid])) { $rez[] = ['uuid'=>$uuid, 'status'=>'duplikat']; continue; }

        $kes = round((float)($rc['kes'] ?? 0), 2);
        $kartica = round((float)($rc['kartica'] ?? 0), 2);
        $stavkeIn = is_array($rc['stavke'] ?? null) ? $rc['stavke'] : [];

        // Vreme naplate sa klijenta (validno, ne u budućnosti)
        $t = strtotime((string)($rc['created_at'] ?? ''));
        $closed = ($t && $t <= time() + 300) ? date('Y-m-d H:i:s', $t) : date('Y-m-d H:i:s');

        // Validne stavke + ukupno
        $stavke = []; $total = 0.0;
        foreach ($stavkeIn as $s) {
            $naziv = trim((string)($s['naziv'] ?? ''));
            $kol = (float)($s['kolicina'] ?? 0);
            $cena = round((float)($s['cena'] ?? 0), 2);
            if ($naziv === '' || $kol <= 0) continue;
            $artId = (int)($s['artikal_id'] ?? 0) ?: null;
            if ($artId && !db_val('SELECT id FROM artikli WHERE id=? AND lokal_id=?', [$artId,$lid])) $artId = null;
            $iznos = round($kol * $cena, 2);
            $total += $iznos;
            $stavke[] = compact('naziv','kol','cena','artId','iznos') + ['napomena' => trim((string)($s['napomena'] ?? '')) ?: null];
        }
        if (!$stavke || $total <= 0) { $rez[] = ['uuid'=>$uuid, 'status'=>'error', 'msg'=>'prazan račun']; continue; }
        if (abs(($kes + $kartica) - $total) > 0.01) { $kes = $total; $kartica = 0; }   // fallback: sve keš
        $nacin = ($kes > 0 && $kartica > 0) ? 'mesovito' : ($kartica > 0 ? 'kartica' : 'kes');

        $pdo = db(); $pdo->beginTransaction();
        try {
            db_run('INSERT INTO racuni (uuid,lokal_id,sto_id,status,popust_pct,ukupno,nacin_placanja,fiskalizovan,placeno_kes,placeno_kartica,korisnik_id,napomena,created_at,closed_at)
                    VALUES (?,?,NULL,"placen",0,?,?,0,?,?,?,?,?,?)',
                   [$uuid,$lid,$total,$nacin,$kes,$kartica,$uid,'Offline (sinhronizovano)',$closed,$closed]);
            $rid = (int)$pdo->lastInsertId();

            foreach ($stavke as $s) {
                db_run('INSERT INTO racun_stavke (racun_id,artikal_id,naziv,cena,kolicina,iznos,napomena,poslato,spremljeno,poslato_at)
                        VALUES (?,?,?,?,?,?,?,1,1,?)',
                       [$rid,$s['artId'],$s['naziv'],$s['cena'],$s['kol'],$s['iznos'],$s['napomena'],$closed]);
                // Razduženje zaliha (normativ ili direktno)
                if ($s['artId']) {
                    $normId = (int)(db_val('SELECT id FROM normativi WHERE lokal_id=? AND artikal_id=?', [$lid,$s['artId']]) ?: 0);
                    if ($normId) {
                        foreach (db_all('SELECT * FROM normativ_stavke WHERE normativ_id=?', [$normId]) as $ns) {
                            $uk = $s['kol'] * (float)$ns['kolicina'];
                            db_run('UPDATE artikli SET zaliha=zaliha-? WHERE id=? AND lokal_id=?', [$uk,$ns['sastojak_id'],$lid]);
                            db_run('INSERT INTO zalihe_promet (lokal_id,artikal_id,tip,kolicina,razlog,korisnik_id) VALUES (?,?,"izlaz",?,?,?)',
                                   [$lid,$ns['sastojak_id'],$uk,'Prodaja račun #'.$rid.' (offline)',$uid]);
                        }
                    } else {
                        db_run('UPDATE artikli SET zaliha=zaliha-? WHERE id=? AND lokal_id=?', [$s['kol'],$s['artId'],$lid]);
                        db_run('INSERT INTO zalihe_promet (lokal_id,artikal_id,tip,kolicina,razlog,korisnik_id) VALUES (?,?,"izlaz",?,?,?)',
                               [$lid,$s['artId'],$s['kol'],'Prodaja račun #'.$rid.' (offline)',$uid]);
                    }
                }
            }
            // Dnevni POS pazar (za dan naplate)
            $dan = substr($closed, 0, 10);
            $expaz = db_row('SELECT id FROM pazar WHERE lokal_id=? AND datum=? AND napomena="POS promet" LIMIT 1', [$lid,$dan]);
            if ($expaz) db_run('UPDATE pazar SET iznos=iznos+?, kes=kes+?, kartica=kartica+? WHERE id=?', [$total,$kes,$kartica,$expaz['id']]);
            else db_run('INSERT INTO pazar (lokal_id,datum,smena,korisnik_id,iznos,kes,kartica,napomena) VALUES (?,?,"cela",?,?,?,?,"POS promet")', [$lid,$dan,$uid,$total,$kes,$kartica]);

            $pdo->commit();
            audit('naplata','racun',$rid,'(offline sync) '.novac($total).' · '.$nacin, $lid);
            $rez[] = ['uuid'=>$uuid, 'status'=>'ok', 'id'=>$rid];
        } catch (Throwable $e) {
            $pdo->rollBack();
            $rez[] = ['uuid'=>$uuid, 'status'=>'error', 'msg'=>$e->getMessage()];
        }
    }
    echo json_encode(['rezultat'=>$rez], JSON_UNESCAPED_UNICODE);
    exit;
}

http_response_code(400);
echo json_encode(['err'=>'Neispravan zahtev.']);
