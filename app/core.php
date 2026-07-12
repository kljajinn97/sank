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

/** URL do statičkog fajla sa verzijom (cache-busting posle deploy-a) */
function asset(string $path): string
{
    $full = __DIR__ . '/../' . ltrim($path, '/');   // core.php je u /app, assets u rootu
    $v = @filemtime($full) ?: time();
    return url($path) . '?v=' . $v;
}

/** Inline SVG ikonica (stroke, currentColor). Zamena za emoji. */
function ico(string $name, int $size = 20): string
{
    static $p = [
      'back'     => '<path d="M19 12H5M12 19l-7-7 7-7"/>',
      'cash'     => '<rect x="2" y="6" width="20" height="12" rx="2"/><circle cx="12" cy="12" r="2.5"/><path d="M6 12h.01M18 12h.01"/>',
      'card'     => '<rect x="2" y="5" width="20" height="14" rx="2"/><path d="M2 10h20"/>',
      'split'    => '<path d="M5 12h14M12 5v.01M12 19v.01"/><circle cx="12" cy="12" r="9"/>',
      'print'    => '<path d="M6 9V2h12v7M6 18H4a2 2 0 0 1-2-2v-4a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v4a2 2 0 0 1-2 2h-2M6 14h12v8H6z"/>',
      'move'     => '<path d="M5 9l-3 3 3 3M9 5l3-3 3 3M15 19l-3 3-3-3M19 9l3 3-3 3M2 12h20M12 2v20"/>',
      'merge'    => '<circle cx="6" cy="6" r="3"/><circle cx="6" cy="18" r="3"/><path d="M6 9v6M18 8a6 6 0 0 1-6 6H6"/><circle cx="18" cy="6" r="3"/>',
      'plus'     => '<path d="M12 5v14M5 12h14"/>',
      'minus'    => '<path d="M5 12h14"/>',
      'x'        => '<path d="M18 6 6 18M6 6l12 12"/>',
      'lock'     => '<rect x="4" y="11" width="16" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/>',
      'receipt'  => '<path d="M4 2v20l2.5-1.5L9 22l3-1.5L15 22l2.5-1.5L20 22V2l-2.5 1.5L15 2l-3 1.5L9 2 6.5 3.5 4 2z"/><path d="M8 8h8M8 12h8M8 16h5"/>',
      'tables'   => '<rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/>',
      'settings' => '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/>',
      'refund'   => '<path d="M3 7v6h6"/><path d="M3.5 13a9 9 0 1 0 2.1-7.4L3 7"/>',
      'storno'   => '<circle cx="12" cy="12" r="9"/><path d="M15 9l-6 6M9 9l6 6"/>',
      'check'    => '<path d="M20 6 9 17l-5-5"/>',
      'bolt'     => '<path d="M13 2 3 14h7l-1 8 10-12h-7l1-8z"/>',
      'user'     => '<path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>',
      'plusbill' => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6M12 12v6M9 15h6"/>',
      'trash'    => '<path d="M3 6h18M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6"/>',
      'download' => '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4M7 10l5 5 5-5M12 15V3"/>',
      'warn'     => '<path d="M10.3 3.6 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.6a2 2 0 0 0-3.4 0z"/><path d="M12 9v4M12 17h.01"/>',
      'wave'     => '<path d="M4 12a4 4 0 0 0 8 0 4 4 0 0 1 8 0"/>',
      'send'     => '<path d="M22 2 11 13M22 2l-7 20-4-9-9-4 20-7z"/>',
      'kuhinja'  => '<path d="M6 2v7a3 3 0 0 0 6 0V2M9 9v13M17 2c-1.7 0-3 2-3 5s1 4 3 4v11"/>',
    ];
    $d = $p[$name] ?? '';
    return '<svg class="ico" width="'.$size.'" height="'.$size.'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'.$d.'</svg>';
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
