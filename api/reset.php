<?php
/** Redefine a senha a partir do token do e-mail. */
declare(strict_types=1);
require __DIR__ . '/_boot.php';
require __DIR__ . '/_mailer.php';
require __DIR__ . '/_emails.php';

$in    = km_input();
$token = km_str($in, 'token', 64);
$pass  = (string) ($in['password'] ?? '');
$pt    = (km_str($in, 'lang', 4) === 'pt');

km_rate_limit('reset:ip:' . km_ip(), 20, 3600);

// Confere o token sem gastá-lo: uma senha recusada não pode custar ao usuário
// um novo pedido de e-mail. O token só é queimado quando o reset vai de fato
// acontecer.
$row = km_token_peek($token, 'reset');
if ($row === null) km_fail('token_invalid', 400);
$uid = (int) $row['user_id'];

$st = km_db()->prepare('SELECT * FROM km_users WHERE id = ?');
$st->execute([$uid]);
$u = $st->fetch();
if (!$u) km_fail('token_invalid', 400);

if ($p = km_password_problem($pass, (string) $u['email'])) km_fail($p);

// Daqui em diante o reset acontece — agora sim o token é gasto. Se outro
// pedido tiver gasto no meio-tempo, este perde a corrida e para aqui.
if (!km_token_burn((int) $row['id'])) km_fail('token_invalid', 400);

// Redefinir também destrava a conta e confirma o e-mail: quem abriu o link
// provou que controla a caixa, que é exatamente o que a verificação testa.
km_db()->prepare('UPDATE km_users SET password_hash = ?, failed_logins = 0, locked_until = NULL, email_verified_at = COALESCE(email_verified_at, ?) WHERE id = ?')
       ->execute([km_hash($pass), km_now(), $uid]);

km_send_password_changed((string) $u['email'], (string) $u['name'], $pt);

// Sessões antigas não continuam valendo depois de trocar a senha.
km_session();
session_regenerate_id(true);
$_SESSION['uid'] = $uid;
km_ok(['user' => ['name' => $u['name'], 'email' => $u['email']]]);
