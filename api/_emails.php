<?php
/** Os quatro e-mails que o site manda, em inglês e português. */
declare(strict_types=1);

function km_base_url(): string {
    $c = km_config();
    return rtrim($c['base_url'] ?? 'https://go2ireland.site', '/');
}

function km_send_verify(string $email, string $name, string $token, bool $pt): bool {
    $url   = km_base_url() . '/api/verify.php?t=' . urlencode($token);
    $first = htmlspecialchars(explode(' ', trim($name))[0] ?: 'olá');
    if ($pt) {
        return km_mail($email, $name, 'Confirme seu e-mail · Keymate',
            km_mail_layout('Confirme seu e-mail',
                "Oi, $first! Falta um passo para ativar sua conta no Keymate. Toque no botão abaixo para confirmar que este e-mail é seu.",
                'Confirmar e-mail', $url,
                'Este link vale por 24 horas. Se não foi você que criou a conta, ignore este e-mail — nada acontece sem a confirmação.', true),
            "Oi, $first! Confirme seu e-mail no Keymate: $url (vale por 24h)");
    }
    return km_mail($email, $name, 'Confirm your email · Keymate',
        km_mail_layout('Confirm your email',
            "Hi $first — one step left to activate your Keymate account. Tap the button below to confirm this email is yours.",
            'Confirm email', $url,
            "This link works for 24 hours. If you didn't create an account, just ignore this email — nothing happens without confirmation.", false),
        "Hi $first — confirm your email for Keymate: $url (valid for 24h)");
}

function km_send_reset(string $email, string $name, string $token, bool $pt): bool {
    $url   = km_base_url() . '/reset.html?t=' . urlencode($token);
    $first = htmlspecialchars(explode(' ', trim($name))[0] ?: 'olá');
    if ($pt) {
        return km_mail($email, $name, 'Redefinir sua senha · Keymate',
            km_mail_layout('Redefinir sua senha',
                "Oi, $first! Recebemos um pedido para redefinir a senha da sua conta no Keymate. Toque no botão para escolher uma senha nova.",
                'Criar nova senha', $url,
                'Este link vale por 1 hora e só pode ser usado uma vez. Se não foi você que pediu, ignore este e-mail — sua senha atual continua valendo.', true),
            "Oi, $first! Redefina sua senha do Keymate: $url (vale por 1 hora)");
    }
    return km_mail($email, $name, 'Reset your password · Keymate',
        km_mail_layout('Reset your password',
            "Hi $first — we got a request to reset the password on your Keymate account. Tap the button to choose a new one.",
            'Create new password', $url,
            "This link works for 1 hour and can only be used once. If you didn't ask for it, ignore this email — your current password still works.", false),
        "Hi $first — reset your Keymate password: $url (valid for 1 hour)");
}

/** Quando alguém tenta se cadastrar com um e-mail que já tem conta. O visitante
 *  recebe a mesma resposta genérica; só o dono da caixa fica sabendo. */
function km_send_already_registered(string $email, string $name, bool $pt): bool {
    $url   = km_base_url() . '/login.html';
    $first = htmlspecialchars(explode(' ', trim($name))[0] ?: 'olá');
    if ($pt) {
        return km_mail($email, $name, 'Você já tem uma conta · Keymate',
            km_mail_layout('Você já tem uma conta',
                "Oi, $first! Alguém tentou criar uma conta no Keymate com este e-mail, mas você já tem uma. Se foi você, é só entrar normalmente.",
                'Entrar', $url,
                'Esqueceu a senha? Use a opção "Esqueci a senha" na tela de login. Se não foi você, pode ignorar — nenhuma conta nova foi criada.', true),
            "Oi, $first! Você já tem conta no Keymate. Entre em: $url");
    }
    return km_mail($email, $name, 'You already have an account · Keymate',
        km_mail_layout('You already have an account',
            "Hi $first — someone tried to create a Keymate account with this email, but you already have one. If that was you, just log in.",
            'Log in', $url,
            'Forgot your password? Use "Forgot password" on the login screen. If this wasn\'t you, ignore it — no new account was created.', false),
        "Hi $first — you already have a Keymate account. Log in: $url");
}

function km_send_password_changed(string $email, string $name, bool $pt): bool {
    $first = htmlspecialchars(explode(' ', trim($name))[0] ?: 'olá');
    if ($pt) {
        return km_mail($email, $name, 'Sua senha foi alterada · Keymate',
            km_mail_layout('Sua senha foi alterada',
                "Oi, $first! A senha da sua conta no Keymate acabou de ser alterada.",
                null, null,
                'Se foi você, não precisa fazer nada. <b>Se não foi você</b>, escreva agora para info@mykeymate.com.', true),
            "Oi, $first! Sua senha do Keymate foi alterada. Se não foi você, escreva para info@mykeymate.com");
    }
    return km_mail($email, $name, 'Your password was changed · Keymate',
        km_mail_layout('Your password was changed',
            "Hi $first — the password on your Keymate account was just changed.",
            null, null,
            "If that was you, nothing to do. <b>If it wasn't you</b>, email info@mykeymate.com right away.", false),
        "Hi $first — your Keymate password was changed. If this wasn't you, email info@mykeymate.com");
}
