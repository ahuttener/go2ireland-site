<?php
/** Link de confirmação do e-mail (aberto direto do e-mail, por isso é GET). */
declare(strict_types=1);
require __DIR__ . '/_boot.php';

$token = (string) ($_GET['t'] ?? '');
$uid   = km_token_consume($token, 'verify');

if ($uid === null) { header('Location: /login.html?verified=0'); exit; }

km_db()->prepare('UPDATE km_users SET email_verified_at = COALESCE(email_verified_at, ?) WHERE id = ?')
       ->execute([km_now(), $uid]);

km_session();
session_regenerate_id(true);
$_SESSION['uid'] = $uid;
header('Location: /feed.html?verified=1');
exit;
