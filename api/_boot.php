<?php
/**
 * Base de todos os endpoints: config, banco, sessão, limites e respostas.
 *
 * O km-config.php mora FORA do public_html de propósito: se um dia o PHP parar
 * de executar, o servidor entrega os .php como texto puro — e as credenciais
 * não podem estar num arquivo servível quando isso acontecer.
 */
declare(strict_types=1);

const KM_TOKEN_TTL_RESET  = 3600;      // 1h
const KM_TOKEN_TTL_VERIFY = 86400;     // 24h
const KM_MAX_FAILED       = 5;         // erros de senha antes de travar
const KM_LOCK_SECONDS     = 900;       // 15min de trava
const KM_PASS_MIN         = 8;

/**
 * Custo do bcrypt, fixado de propósito.
 *
 * Precisa ser o MESMO das senhas reais e do hash-isca do login.php. Com
 * PASSWORD_DEFAULT (custo 10) contra uma isca de custo 12, o login de um e-mail
 * inexistente demorava ~288ms e o de um e-mail existente ~73ms — a diferença
 * revelava quem tem conta. Um número só, usado nos dois lados, elimina isso.
 */
const KM_BCRYPT_COST = 12;

function km_hash(string $password): string {
    return password_hash($password, PASSWORD_BCRYPT, ['cost' => KM_BCRYPT_COST]);
}

/** Hash descartável para quando o e-mail não existe. Gerado com KM_BCRYPT_COST,
 *  então password_verify gasta exatamente o mesmo tempo de um usuário real. */
function km_dummy_hash(): string {
    return '$2y$12$0000000000000000000000000000000000000000000000000000u';
}

function km_config(): array {
    static $c = null;
    if ($c !== null) return $c;
    $path = getenv('KM_CONFIG') ?: dirname(__DIR__, 2) . '/km-config.php';
    if (!is_file($path)) km_fail('server_not_configured', 500);
    return $c = require $path;
}

function km_now(): string { return gmdate('Y-m-d H:i:s'); }
function km_at(int $secondsFromNow): string { return gmdate('Y-m-d H:i:s', time() + $secondsFromNow); }

function km_db(): PDO {
    static $db = null;
    if ($db !== null) return $db;
    $c = km_config();
    try {
        $db = new PDO($c['dsn'], $c['db_user'] ?? null, $c['db_pass'] ?? null, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    } catch (Throwable $e) {
        error_log('[km] db: ' . $e->getMessage());
        km_fail('db_unavailable', 500);
    }
    return $db;
}

/* ── Respostas ───────────────────────────────────────────────────────────── */
function km_json(array $data, int $code = 200): never {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}
function km_ok(array $extra = []): never { km_json(['ok' => true] + $extra); }
function km_fail(string $error, int $code = 400, array $extra = []): never {
    km_json(['ok' => false, 'error' => $error] + $extra, $code);
}

/* ── Sessão ──────────────────────────────────────────────────────────────── */
function km_session(): void {
    if (session_status() === PHP_SESSION_ACTIVE) return;
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => (($_SERVER['HTTPS'] ?? '') !== '') || (km_config()['force_secure_cookie'] ?? true),
        'httponly' => true,
        'samesite' => 'Strict',
    ]);
    session_name('km_sess');
    session_start();
}
function km_user_id(): ?int { km_session(); return isset($_SESSION['uid']) ? (int) $_SESSION['uid'] : null; }
function km_require_login(): int {
    $id = km_user_id();
    if ($id === null) km_fail('not_authenticated', 401);
    return $id;
}

/* ── Entrada ─────────────────────────────────────────────────────────────── */
/**
 * Só aceita POST + JSON + header X-KM. O header é a defesa contra CSRF: um site
 * de terceiros não consegue mandá-lo sem preflight, e nós não devolvemos CORS
 * nenhum — então o preflight falha. Somado ao cookie SameSite=Strict, um POST
 * forjado de fora nunca chega autenticado.
 */
function km_input(): array {
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') km_fail('method_not_allowed', 405);
    if (($_SERVER['HTTP_X_KM'] ?? '') === '')            km_fail('bad_request', 400);
    $raw = file_get_contents('php://input') ?: '';
    if (strlen($raw) > 8192) km_fail('payload_too_large', 413);
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}
function km_str(array $in, string $key, int $max = 255): string {
    $v = $in[$key] ?? '';
    if (!is_string($v)) return '';
    $v = trim($v);
    return mb_substr($v, 0, $max);
}
function km_ip(): string { return (string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'); }

/* ── Limite de tentativas ────────────────────────────────────────────────── */
/** Janela fixa por chave. Mantido no banco: LiteSpeed roda vários processos,
 *  então guardar em memória não seguraria nada. */
function km_rate_limit(string $key, int $max, int $windowSeconds): void {
    $db = km_db();
    $k  = substr(hash('sha256', $key), 0, 64);
    $db->prepare('DELETE FROM km_rate WHERE reset_at < ?')->execute([km_now()]);
    $row = $db->prepare('SELECT hits, reset_at FROM km_rate WHERE rk = ?');
    $row->execute([$k]);
    $cur = $row->fetch();
    if (!$cur) {
        $db->prepare('INSERT INTO km_rate (rk, hits, reset_at) VALUES (?, 1, ?)')
           ->execute([$k, km_at($windowSeconds)]);
        return;
    }
    if ((int) $cur['hits'] >= $max) km_fail('rate_limited', 429);
    $db->prepare('UPDATE km_rate SET hits = hits + 1 WHERE rk = ?')->execute([$k]);
}

/* ── Tokens ──────────────────────────────────────────────────────────────── */
/** Devolve o token em claro (vai no e-mail); no banco fica só o hash, para que
 *  um dump do banco não permita resetar a senha de ninguém. */
function km_token_issue(int $userId, string $kind, int $ttl): string {
    $db = km_db();
    $db->prepare('UPDATE km_tokens SET used_at = ? WHERE user_id = ? AND kind = ? AND used_at IS NULL')
       ->execute([km_now(), $userId, $kind]);
    $plain = bin2hex(random_bytes(32));
    $db->prepare('INSERT INTO km_tokens (user_id, kind, token_hash, expires_at, created_at) VALUES (?,?,?,?,?)')
       ->execute([$userId, $kind, hash('sha256', $plain), km_at($ttl), km_now()]);
    return $plain;
}
/** Lê o token SEM gastá-lo. Serve para validar o resto do formulário antes de
 *  queimar o token — senão uma senha recusada custa um e-mail novo ao usuário. */
function km_token_peek(string $plain, string $kind): ?array {
    if ($plain === '' || !preg_match('/^[a-f0-9]{64}$/', $plain)) return null;
    $st = km_db()->prepare('SELECT id, user_id FROM km_tokens WHERE token_hash = ? AND kind = ? AND used_at IS NULL AND expires_at > ?');
    $st->execute([hash('sha256', $plain), $kind, km_now()]);
    $row = $st->fetch();
    return $row ?: null;
}

/** Gasta o token. O "AND used_at IS NULL" é o que garante o uso único mesmo se
 *  dois pedidos chegarem juntos: só um dos UPDATEs afeta uma linha. */
function km_token_burn(int $tokenId): bool {
    $st = km_db()->prepare('UPDATE km_tokens SET used_at = ? WHERE id = ? AND used_at IS NULL');
    $st->execute([km_now(), $tokenId]);
    return $st->rowCount() === 1;
}

/** Lê e gasta de uma vez. Para fluxos sem nada a validar depois (confirmação). */
function km_token_consume(string $plain, string $kind): ?int {
    $row = km_token_peek($plain, $kind);
    if (!$row) return null;
    if (!km_token_burn((int) $row['id'])) return null;
    return (int) $row['user_id'];
}

/* ── Usuários ────────────────────────────────────────────────────────────── */
function km_valid_email(string $e): bool {
    return $e !== '' && mb_strlen($e) <= 190 && filter_var($e, FILTER_VALIDATE_EMAIL) !== false;
}
function km_user_by_email(string $email): ?array {
    $st = km_db()->prepare('SELECT * FROM km_users WHERE email = ?');
    $st->execute([mb_strtolower($email)]);
    return $st->fetch() ?: null;
}
function km_password_problem(string $pass, string $email): ?string {
    if (mb_strlen($pass) < KM_PASS_MIN)                return 'password_too_short';
    if (mb_strlen($pass) > 200)                        return 'password_too_long';
    if (mb_strtolower($pass) === mb_strtolower($email)) return 'password_is_email';
    return null;
}
