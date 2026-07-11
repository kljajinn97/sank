<?php
// ============================================================
//  SANK — Core: konfiguracija, PDO konekcija, helperi, auth
//  Uključuje se na početku svake stranice.
// ============================================================

declare(strict_types=1);

$CONFIG = require __DIR__ . '/config.php';

date_default_timezone_set($CONFIG['timezone'] ?? 'Europe/Belgrade');

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_name('sank_sid');
    session_start();
}

// ---------- Baza (PDO singleton) ----------
function db(): PDO
{
    static $pdo = null;
    global $CONFIG;
    if ($pdo === null) {
        $c = $CONFIG['db'];
        $dsn = "mysql:host={$c['host']};dbname={$c['name']};charset={$c['charset']}";
        $pdo = new PDO($dsn, $c['user'], $c['pass'], [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    }
    return $pdo;
}

// ---------- Kratke DB funkcije ----------
function db_all(string $sql, array $p = []): array { $s = db()->prepare($sql); $s->execute($p); return $s->fetchAll(); }
function db_row(string $sql, array $p = []): ?array { $s = db()->prepare($sql); $s->execute($p); $r = $s->fetch(); return $r ?: null; }
function db_val(string $sql, array $p = []) { $s = db()->prepare($sql); $s->execute($p); return $s->fetchColumn(); }
function db_run(string $sql, array $p = []): PDOStatement { $s = db()->prepare($sql); $s->execute($p); return $s; }

// ---------- Osnovni helperi ----------

/** Bezbedno čitanje POST vrednosti */
function post(string $key, $default = ''): string { return trim((string)($_POST[$key] ?? $default)); }

/** Parsiranje broja/novca (podržava i zarez kao decimalu) */
function to_num($v): float {
    $v = trim((string)$v);
    if ($v === '') return 0.0;
    if (strpos($v, ',') !== false && strpos($v, '.') !== false) {
        $v = str_replace('.', '', $v);     // tačke = hiljade
        $v = str_replace(',', '.', $v);
    } elseif (strpos($v, ',') !== false) {
        $v = str_replace(',', '.', $v);
    }
    return (float)$v;
}


/** Bezbedan ispis (XSS zaštita) */
function e(?string $s): string
{
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

/** Preusmeravanje */
function redirect(string $path): void
{
    header('Location: ' . $path);
    exit;
}

/** Formatiranje novca */
function novac($iznos, string $valuta = 'RSD'): string
{
    return number_format((float)$iznos, 2, ',', '.') . ' ' . $valuta;
}

/** Formatiranje datuma d.m.Y. */
function datum(?string $d): string
{
    if (!$d) return '';
    $t = strtotime($d);
    return $t ? date('d.m.Y.', $t) : '';
}

/** URL do stranice unutar aplikacije */
function url(string $path = ''): string
{
    return '/' . ltrim($path, '/');
}

// ---------- CSRF zaštita ----------
function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . csrf_token() . '">';
}

function csrf_check(): void
{
    $t = $_POST['_csrf'] ?? '';
    if (!hash_equals($_SESSION['csrf'] ?? '', (string)$t)) {
        http_response_code(419);
        die('Bezbednosni token je istekao. Osveži stranicu i pokušaj ponovo.');
    }
}

// ---------- Flash poruke ----------
function flash(string $type, string $msg): void
{
    $_SESSION['flash'][] = ['type' => $type, 'msg' => $msg];
}

function flash_take(): array
{
    $f = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $f;
}

// ---------- Autentifikacija ----------

function current_user(): ?array
{
    static $cache = null;
    if ($cache !== null) return $cache ?: null;
    if (empty($_SESSION['uid'])) { $cache = false; return null; }

    $st = db()->prepare(
        'SELECT k.*, l.naziv AS lokal_naziv, l.status AS lokal_status,
                l.boja AS lokal_boja, l.logo AS lokal_logo
         FROM korisnici k
         LEFT JOIN lokali l ON l.id = k.lokal_id
         WHERE k.id = ? AND k.status = "aktivan" LIMIT 1'
    );
    $st->execute([$_SESSION['uid']]);
    $u = $st->fetch();
    $cache = $u ?: false;
    return $u ?: null;
}

function is_logged_in(): bool { return current_user() !== null; }

function is_super_admin(): bool
{
    $u = current_user();
    return $u && $u['uloga'] === 'super_admin';
}

/** ID lokala kojem pripada trenutni korisnik (null za super_admina) */
function current_lokal_id(): ?int
{
    $u = current_user();
    return $u && $u['lokal_id'] !== null ? (int)$u['lokal_id'] : null;
}

/** Da li korisnik ima jednu od datih uloga */
function user_has_role(array $roles): bool
{
    $u = current_user();
    return $u && in_array($u['uloga'], $roles, true);
}

/** Zahtevaj prijavu — inače na login */
function require_login(): void
{
    if (!is_logged_in()) {
        redirect(url('login'));
    }
}

/** Zahtevaj određenu ulogu — inače 403 */
function require_role(array $roles): void
{
    require_login();
    if (!user_has_role($roles)) {
        http_response_code(403);
        die('Nemaš dozvolu za ovu stranicu.');
    }
}

/** Prijava po username-u i lozinci */
function attempt_login(string $username, string $password): bool
{
    $st = db()->prepare('SELECT * FROM korisnici WHERE username = ? AND status = "aktivan" LIMIT 1');
    $st->execute([$username]);
    $u = $st->fetch();
    if (!$u || !password_verify($password, $u['password_hash'])) {
        return false;
    }
    // Suspendovan lokal ne može da se prijavi (osim super_admina)
    if ($u['uloga'] !== 'super_admin' && $u['lokal_id']) {
        $ls = db()->prepare('SELECT status FROM lokali WHERE id = ?');
        $ls->execute([$u['lokal_id']]);
        if ($ls->fetchColumn() === 'suspendovan') {
            return false;
        }
    }
    session_regenerate_id(true);
    $_SESSION['uid'] = (int)$u['id'];
    db()->prepare('UPDATE korisnici SET last_login = NOW() WHERE id = ?')->execute([$u['id']]);
    return true;
}

function logout(): void
{
    $_SESSION = [];
    session_destroy();
}

// ---------- POS terminal ----------

/** Nasumičan token */
function gen_token(): string { return bin2hex(random_bytes(32)); }

/** Uređaj sa kojim je ovaj browser aktiviran (preko kolačića), ako je važeći */
function pos_current_device(): ?array
{
    $t = $_COOKIE['sank_pos_token'] ?? '';
    if ($t === '') return null;
    $d = db_row('SELECT * FROM pos_uredjaji WHERE token=? AND status="aktivan" LIMIT 1', [$t]);
    return $d ?: null;
}

/** Trenutno prijavljen radnik na POS terminalu (PIN sesija) */
function pos_current_user(): ?array
{
    if (empty($_SESSION['pos_uid'])) return null;
    $u = db_row('SELECT * FROM korisnici WHERE id=? AND status="aktivan" LIMIT 1', [$_SESSION['pos_uid']]);
    return $u ?: null;
}

function pos_terminal_active(): bool
{
    return pos_current_device() !== null && pos_current_user() !== null
        && (int)($_SESSION['pos_lokal'] ?? 0) === (int)(pos_current_device()['lokal_id'] ?? -1);
}

// Fiskalni sloj (ESIR integracija)
require_once __DIR__ . '/fiskal.php';

// ---------- Audit log ----------
/** Zabeleži radnju u dnevnik izmena (ko, šta, kada). Best-effort. */
function audit(string $radnja, string $entitet = '', $id = null, string $detalji = '', ?int $lokalId = null): void
{
    try {
        // Akter: radnik na POS terminalu ili prijavljeni BO korisnik
        $actor = pos_current_user() ?: current_user();
        if (!$actor) return;
        $lid = $lokalId;
        if ($lid === null) {
            $lid = !empty($_SESSION['pos_uid']) ? (int)($_SESSION['pos_lokal'] ?? 0) : (int)($actor['lokal_id'] ?? 0);
        }
        if (!$lid) $lid = null;
        $ime = trim(($actor['ime'] ?? '') . ' ' . ($actor['prezime'] ?? ''));
        db_run('INSERT INTO audit_log (lokal_id,korisnik_id,korisnik_ime,radnja,entitet,entitet_id,detalji,ip)
                VALUES (?,?,?,?,?,?,?,?)',
               [$lid, (int)$actor['id'], $ime, $radnja, $entitet ?: null, $id ? (int)$id : null,
                $detalji !== '' ? $detalji : null, $_SERVER['REMOTE_ADDR'] ?? null]);
    } catch (Throwable $e) { /* nikad ne ruši glavnu radnju */ }
}
