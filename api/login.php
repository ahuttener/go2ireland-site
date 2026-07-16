<?php
/** Login por e-mail + senha, com trava por tentativas. */
declare(strict_types=1);
require __DIR__ . '/_boot.php';

$in    = km_input();
$email = mb_strtolower(km_str($in, 'email', 190));
$pass  = (string) ($in['password'] ?? '');

km_rate_limit('login:ip:' . km_ip(), 30, 900);
if ($email === '' || $pass === '') km_fail('credentials_required');
km_rate_limit('login:em:' . $email, 10, 900);

$u  = km_user_by_email($email);
$db = km_db();

// Hash descartável quando o usuário não existe: sem isso, a resposta volta
// mais rápido para e-mail inexistente e vira um oráculo de enumeração.
$hash = $u['password_hash'] ?? km_dummy_hash();

if ($u && $u['locked_until'] !== null && $u['locked_until'] > km_now()) {
    km_fail('account_locked', 423, ['until' => $u['locked_until']]);
}

if (!password_verify($pass, $hash) || !$u) {
    if ($u) {
        $fails = (int) $u['failed_logins'] + 1;
        if ($fails >= KM_MAX_FAILED) {
            $db->prepare('UPDATE km_users SET failed_logins = 0, locked_until = ? WHERE id = ?')
               ->execute([km_at(KM_LOCK_SECONDS), (int) $u['id']]);
            km_fail('account_locked', 423, ['until' => km_at(KM_LOCK_SECONDS)]);
        }
        $db->prepare('UPDATE km_users SET failed_logins = ? WHERE id = ?')->execute([$fails, (int) $u['id']]);
    }
    km_fail('invalid_credentials', 401);
}

if ($u['email_verified_at'] === null) km_fail('email_not_verified', 403);

$db->prepare('UPDATE km_users SET failed_logins = 0, locked_until = NULL WHERE id = ?')->execute([(int) $u['id']]);

km_session();
session_regenerate_id(true);   // impede fixação de sessão
$_SESSION['uid'] = (int) $u['id'];
km_ok(['user' => ['name' => $u['name'], 'email' => $u['email']]]);
