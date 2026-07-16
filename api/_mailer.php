<?php
/**
 * Envio de e-mail via SMTP autenticado.
 *
 * Por que SMTP e não mail(): a sonda mostrou localhost:25 bloqueado nesse
 * servidor, então mail() não tem MTA local para entregar. smtp.hostinger.com
 * :465 e :587 estão abertos. SMTP autenticado também alinha SPF/DKIM do
 * domínio, o que tira o e-mail do spam.
 */
declare(strict_types=1);

require_once __DIR__ . '/lib/PHPMailer/Exception.php';
require_once __DIR__ . '/lib/PHPMailer/PHPMailer.php';
require_once __DIR__ . '/lib/PHPMailer/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;

/**
 * Ponto único de envio. Em teste, km-config.php pode definir
 * 'mail_sink' => '/caminho/arquivo.jsonl' e nada é enviado de verdade.
 */
function km_mail(string $toEmail, string $toName, string $subject, string $htmlBody, string $textBody): bool {
    $c = km_config();

    if (!empty($c['mail_sink'])) {
        file_put_contents($c['mail_sink'], json_encode([
            'to' => $toEmail, 'name' => $toName, 'subject' => $subject,
            'html' => $htmlBody, 'text' => $textBody, 'at' => km_now(),
        ], JSON_UNESCAPED_UNICODE) . "\n", FILE_APPEND);
        return true;
    }

    $m = new PHPMailer(true);
    try {
        $m->isSMTP();
        $m->Host        = $c['smtp_host'];
        $m->Port        = (int) $c['smtp_port'];
        $m->SMTPAuth    = true;
        $m->Username    = $c['smtp_user'];
        $m->Password    = $c['smtp_pass'];
        $m->SMTPSecure  = ((int) $c['smtp_port'] === 465)
            ? PHPMailer::ENCRYPTION_SMTPS
            : PHPMailer::ENCRYPTION_STARTTLS;
        $m->Timeout     = 15;
        $m->CharSet     = 'UTF-8';

        $m->setFrom($c['mail_from'], $c['mail_from_name'] ?? 'Keymate');
        $m->addAddress($toEmail, $toName !== '' ? $toName : $toEmail);
        if (!empty($c['mail_reply_to'])) $m->addReplyTo($c['mail_reply_to']);

        // Logo embutida: clientes de e-mail bloqueiam imagem remota por padrão,
        // então anexar por CID é o que faz a marca realmente aparecer.
        $logo = dirname(__DIR__) . '/logo.png';
        if (is_file($logo)) $m->addEmbeddedImage($logo, 'kmlogo', 'keymate.png');

        $m->isHTML(true);
        $m->Subject = $subject;
        $m->Body    = $htmlBody;
        $m->AltBody = $textBody;
        $m->send();
        return true;
    } catch (Throwable $e) {
        error_log('[km] mail: ' . $e->getMessage());
        return false;
    }
}

/** Moldura visual dos e-mails. Tabela + estilo inline porque cliente de e-mail
 *  não entende flex/grid e ignora <style> externo. */
function km_mail_layout(string $title, string $intro, ?string $btnLabel, ?string $btnUrl, string $footNote, bool $pt): string {
    $logo   = '<img src="cid:kmlogo" width="132" alt="Keymate" style="display:block;border:0;outline:none;max-width:132px;height:auto">';
    $button = '';
    if ($btnLabel !== null && $btnUrl !== null) {
        $button = '<tr><td style="padding:26px 0 6px">'
            . '<a href="' . htmlspecialchars($btnUrl, ENT_QUOTES) . '" '
            . 'style="background:#2563eb;color:#ffffff;text-decoration:none;font-weight:700;font-size:15px;'
            . 'padding:13px 26px;border-radius:10px;display:inline-block;font-family:Arial,Helvetica,sans-serif">'
            . htmlspecialchars($btnLabel) . '</a></td></tr>';
    }
    $fallback = ($btnUrl !== null)
        ? '<tr><td style="padding-top:20px;font:12px Arial,Helvetica,sans-serif;color:#8a94a6;line-height:1.6">'
          . ($pt ? 'Se o botão não funcionar, copie e cole este endereço no navegador:' : "If the button doesn't work, copy and paste this address into your browser:")
          . '<br><span style="color:#2563eb;word-break:break-all">' . htmlspecialchars($btnUrl) . '</span></td></tr>'
        : '';

    return '<!doctype html><html><body style="margin:0;padding:0;background:#f4f6fb">'
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f4f6fb;padding:28px 12px">'
        . '<tr><td align="center">'
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:520px;background:#ffffff;border-radius:14px;padding:32px 30px;border:1px solid #e6eaf2">'
        . '<tr><td>' . $logo . '</td></tr>'
        . '<tr><td style="padding-top:22px;font:700 21px Arial,Helvetica,sans-serif;color:#0f172a">' . htmlspecialchars($title) . '</td></tr>'
        . '<tr><td style="padding-top:12px;font:15px Arial,Helvetica,sans-serif;color:#475569;line-height:1.6">' . $intro . '</td></tr>'
        . $button
        . $fallback
        . '<tr><td style="padding-top:26px;border-top:1px solid #eef1f7;font:12px Arial,Helvetica,sans-serif;color:#8a94a6;line-height:1.6">'
        . $footNote . '</td></tr>'
        . '<tr><td style="padding-top:14px;font:11px Arial,Helvetica,sans-serif;color:#aab2c0">Keymate · go2ireland.site</td></tr>'
        . '</table></td></tr></table></body></html>';
}
