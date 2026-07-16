<?php
/** Cadastro. Cria o usuário e manda o e-mail de confirmação.
 *  Nunca revela se o e-mail já existe: resposta idêntica nos dois casos, e a
 *  distinção vai por e-mail para o dono da caixa (quem tem direito de saber). */
declare(strict_types=1);
require __DIR__ . '/_boot.php';
require __DIR__ . '/_mailer.php';
require __DIR__ . '/_emails.php';

$in    = km_input();
$name  = km_str($in, 'name', 120);
$email = mb_strtolower(km_str($in, 'email', 190));
$pass  = (string) ($in['password'] ?? '');
$pt    = (km_str($in, 'lang', 4) === 'pt');

km_rate_limit('signup:ip:' . km_ip(), 10, 3600);

if ($name === '' || mb_strlen($name) < 2) km_fail('name_required');
if (!km_valid_email($email))              km_fail('email_invalid');
if ($p = km_password_problem($pass, $email)) km_fail($p);

km_rate_limit('signup:em:' . $email, 5, 3600);

$db       = km_db();
$existing = km_user_by_email($email);

if ($existing) {
    // Conta já existe: avisa o DONO do e-mail, sem contar ao visitante.
    if ($existing['email_verified_at'] === null) {
        $t = km_token_issue((int) $existing['id'], 'verify', KM_TOKEN_TTL_VERIFY);
        km_send_verify($email, (string) $existing['name'], $t, $pt);
    } else {
        km_send_already_registered($email, (string) $existing['name'], $pt);
    }
    km_ok(['sent' => true]);
}

$db->prepare('INSERT INTO km_users (email, name, password_hash, created_at, failed_logins) VALUES (?,?,?,?,0)')
   ->execute([$email, $name, km_hash($pass), km_now()]);
$uid = (int) $db->lastInsertId();

$t = km_token_issue($uid, 'verify', KM_TOKEN_TTL_VERIFY);
km_send_verify($email, $name, $t, $pt);
km_ok(['sent' => true]);
