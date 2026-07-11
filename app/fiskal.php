<?php
/**
 * SANK — Fiskalni sloj (ESIR strana), SUF v3 model.
 *
 * VAŽNO (pravno): Ovo je integracioni sloj. Za legalnu upotrebu u Srbiji
 * potreban je odobren ESIR (Poreska uprava), PFR (L-PFR/V-PFR) i bezbednosni
 * element. Simulacioni režim služi ISKLJUČIVO za razvoj/test toka i NE izdaje
 * važeći fiskalni račun. Pre produkcije: uskladiti telo/putanju/hedere zahteva
 * sa aktuelnim SUF tehničkim uputstvom i konkretnim PFR-om, pa proći odobrenje.
 */

function fiskal_config(int $lid): array {
    $l = db_row('SELECT fisk_aktivna,fisk_mode,pfr_url,esir_broj,pdv_obveznik FROM lokali WHERE id=?', [$lid]);
    return $l ?: [];
}

function fiskal_aktivna(int $lid): bool {
    $c = fiskal_config($lid);
    return !empty($c['fisk_aktivna']);
}

/** Poreska oznaka artikla (default Ђ = 20%) */
function fiskal_oznaka(?int $artId, int $lid): string {
    if (!$artId) return 'Ђ';
    $o = db_val('SELECT poreska_oznaka FROM artikli WHERE id=? AND lokal_id=?', [$artId,$lid]);
    return $o ?: 'Ђ';
}

/**
 * Fiskalizuje već naplaćen račun. Puni pfr_* polja. Vraća true ako uspešno.
 * $stavke = redovi iz racun_stavke (naziv, cena, kolicina, iznos, artikal_id).
 */
function fiskal_posalji(int $rid, int $lid, array $racun, array $stavke, float $kes = 0, float $kartica = 0, string $transakcija = 'Sale'): bool {
    $c = fiskal_config($lid);
    if (empty($c['fisk_aktivna'])) return false;

    $total = (float)$racun['ukupno'];
    $items = [];
    foreach ($stavke as $s) {
        $items[] = [
            'name'        => $s['naziv'],
            'labels'      => [ fiskal_oznaka(isset($s['artikal_id']) ? (int)$s['artikal_id'] : null, $lid) ],
            'quantity'    => (float)$s['kolicina'],
            'unitPrice'   => (float)$s['cena'],
            'totalAmount' => (float)$s['iznos'],
        ];
    }
    $payment = [];
    if ($kes > 0)     $payment[] = ['amount' => round($kes,2),     'paymentType' => 'Cash'];
    if ($kartica > 0) $payment[] = ['amount' => round($kartica,2), 'paymentType' => 'Card'];
    if (!$payment)    $payment[] = ['amount' => $total,            'paymentType' => 'Cash'];

    $req = ['invoiceRequest' => [
        'invoiceType'     => 'Normal',
        'transactionType' => $transakcija,   // Sale ili Refund
        'payment'         => $payment,
        'items'           => $items,
        'cashier'         => (string)($racun['korisnik_id'] ?? ''),
    ]];

    $resp = ($c['fisk_mode'] === 'simulacija')
        ? fiskal_simulacija($total)
        : fiskal_posalji_pfr((string)$c['pfr_url'], $req);

    if (!$resp || empty($resp['ok'])) return false;

    db_run('UPDATE racuni SET fiskalizovan=1, pfr_broj=?, pfr_brojac=?, pfr_vreme=?, pfr_qr=?, pfr_url_ver=? WHERE id=? AND lokal_id=?',
        [$resp['broj'], $resp['brojac'], $resp['vreme'], $resp['qr'], $resp['url'], $rid, $lid]);
    return true;
}

/** Simulacija PFR odgovora — SAMO za test toka, nije važeći fiskalni račun. */
function fiskal_simulacija(float $total): array {
    $n = random_int(1, 99999);
    return [
        'ok'     => true,
        'broj'   => strtoupper(bin2hex(random_bytes(4))) . '-' . strtoupper(bin2hex(random_bytes(4))) . '-' . $n,
        'brojac' => $n . '/' . $n . 'ПП',
        'vreme'  => date('Y-m-d H:i:s'),
        'qr'     => null,
        'url'    => 'https://suf.purs.gov.rs/v/?vl=SIMULACIJA-' . $n,
    ];
}

/**
 * Realni PFR (L-PFR/V-PFR). Struktura spremna — USKLADITI sa SUF v3 uputstvom
 * i konkretnim PFR-om pre upotrebe. Bezbednosni element/PIN se prosleđuje
 * prema dokumentaciji PFR-a (obično kroz sam PFR uređaj/servis).
 */
function fiskal_posalji_pfr(string $url, array $req): ?array {
    if ($url === '' || !function_exists('curl_init')) return null;
    $endpoint = rtrim($url, '/') . '/api/v3/invoices';
    $ch = curl_init($endpoint);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json', 'Accept: application/json'],
        CURLOPT_POSTFIELDS     => json_encode($req, JSON_UNESCAPED_UNICODE),
    ]);
    $out  = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($code < 200 || $code >= 300 || !$out) return null;
    $j = json_decode($out, true);
    if (!is_array($j)) return null;
    return [
        'ok'     => true,
        'broj'   => $j['invoiceNumber']   ?? '',
        'brojac' => $j['invoiceCounter']  ?? '',
        'vreme'  => isset($j['sdcDateTime']) ? date('Y-m-d H:i:s', strtotime($j['sdcDateTime'])) : date('Y-m-d H:i:s'),
        'qr'     => isset($j['verificationQRCode']) ? ('data:image/png;base64,' . $j['verificationQRCode']) : null,
        'url'    => $j['verificationUrl'] ?? '',
    ];
}
