<?php
/**
 * MODELO. Copie para  /home/u105898088/domains/go2ireland.site/km-config.php
 * — ou seja, UM NÍVEL ACIMA do public_html — e preencha.
 *
 * NÃO coloque este arquivo dentro do public_html e NÃO o mande para o Git:
 * fora do public_html ele nunca é servível, nem se o PHP parar de executar.
 */
return [
    // ---- Banco (hPanel -> Bancos de dados MySQL) --------------------------
    'dsn'     => 'mysql:host=localhost;dbname=SEU_BANCO;charset=utf8mb4',
    'db_user' => 'SEU_USUARIO',
    'db_pass' => 'SUA_SENHA',

    // ---- SMTP (hPanel -> E-mails). Porta 465 = SSL, 587 = STARTTLS -------
    'smtp_host'      => 'smtp.hostinger.com',
    'smtp_port'      => 465,
    'smtp_user'      => 'info@mykeymate.com',
    'smtp_pass'      => 'SENHA_DA_CAIXA_DE_EMAIL',
    'mail_from'      => 'info@mykeymate.com',   // precisa bater com smtp_user
    'mail_from_name' => 'Keymate',
    'mail_reply_to'  => 'info@mykeymate.com',

    // ---- Site ------------------------------------------------------------
    'base_url' => 'https://go2ireland.site',
];
