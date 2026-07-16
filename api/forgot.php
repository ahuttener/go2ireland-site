<?php
/** "Esqueci a senha": manda o link de redefinição.
 *  Responde SEMPRE igual, exista o e-mail ou não — senão vira um jeito de
 *  descobrir quem tem conta no site. */
declare(strict_types=1);
require __DIR__ . '/_boot.php';
require __DIR__ . '/_mailer.php';
require __DIR__ . '/_emails.php';

$in    = km_input();
$email = mb_strtolower(km_str($in, 'email', 190));
$pt    = (km_str($in, 'lang', 4) === 'pt');

km_rate_limit('forgot:ip:' . km_ip(), 12, 3600);
if (km_valid_email($email)) {
    // Limite por e-mail: sem isso dá para bombardear a caixa de alguém.
    km_rate_limit('forgot:em:' . $email, 4, 3600);
    if ($u = km_user_by_email($email)) {
        $t = km_token_issue((int) $u['id'], 'reset', KM_TOKEN_TTL_RESET);
        km_send_reset($email, (string) $u['name'], $t, $pt);
    }
}
km_ok(['sent' => true]);
