<?php
/**
 * Anmeldeformular-Handler für endlich-ki.de
 * Empfängt Seminar-Anmeldungen und sendet Admin- + Bestätigungsmail per SMTP.
 *
 * Selber Bot-Schutz-Standard wie werbestimme.de/send-contact.php: vier
 * unabhängige Prüfungen, die erste, die anschlägt, gewinnt. Wird ein Bot
 * erkannt: Fake-Success-Antwort + kurze Info-Mail an Jonathan.
 */

ini_set('display_errors', '0');
error_reporting(E_ALL);
ini_set('log_errors', '1');

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/lib/BotGuard.php';
require_once __DIR__ . '/lib/RateLimiter.php';
require_once __DIR__ . '/lib/TorGuard.php';
require_once __DIR__ . '/lib/BotAlertMail.php';

use PHPMailer\PHPMailer\PHPMailer;

// ─── SMTP-Credentials aus Coolify-Umgebungsvariablen ────────────────────

$smtpCreds = [
    'host'       => getenv('SMTP_HOST') ?: 'smtp.strato.de',
    'port'       => (int) (getenv('SMTP_PORT') ?: 465),
    'username'   => getenv('SMTP_USERNAME'),
    'password'   => getenv('SMTP_PASSWORD'),
    'from_email' => getenv('SMTP_FROM_EMAIL') ?: 'info@endlich-ki.de',
    'from_name'  => getenv('SMTP_FROM_NAME') ?: 'Jonathan Enns',
];

if (!$smtpCreds['username'] || !$smtpCreds['password']) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'SMTP-Konfiguration fehlt']);
    exit;
}

function sendSignupMail(array $smtpCreds, string $to, string $subject, string $htmlBody, ?string $replyToEmail = null, ?string $replyToName = null, ?string $altBody = null): void {
    $mail = new PHPMailer(true);
    $mail->isSMTP();
    $mail->Host       = $smtpCreds['host'];
    $mail->SMTPAuth   = true;
    $mail->Username   = $smtpCreds['username'];
    $mail->Password   = $smtpCreds['password'];
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
    $mail->Port       = $smtpCreds['port'];
    $mail->CharSet    = 'UTF-8';

    $mail->setFrom($smtpCreds['from_email'], $smtpCreds['from_name']);
    $mail->addAddress($to);

    if ($replyToEmail) {
        $mail->addReplyTo($replyToEmail, $replyToName ?? '');
    }

    $mail->isHTML(true);
    $mail->Subject = $subject;
    $mail->Body    = $htmlBody;
    if ($altBody) {
        $mail->AltBody = $altBody;
    }

    $mail->send();
}

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

$data = $_POST;
if (!$data) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Ungültige Daten']);
    exit;
}

// ─── Bot-Erkennung ──────────────────────────────────────────────────────

$honeypot = trim($data['website'] ?? '');
$clientIp = RateLimiter::clientIp();
$botReason = null;

if (TorGuard::isTorExit($clientIp)) {
    $botReason = 'tor-exit';
}

if ($botReason === null && $honeypot !== '') {
    $botReason = 'honeypot';
}

if ($botReason === null) {
    $rateLimiter = new RateLimiter(__DIR__ . '/data/signup-ratelimit.json');
    $rl = $rateLimiter->checkRateLimit($clientIp, 10);
    if (!$rl['ok']) {
        $botReason = 'rate-limit';
    }
}

if ($botReason === null) {
    $dailyLimiter = new RateLimiter(__DIR__ . '/data/signup-ratelimit-daily.json');
    $rlDaily = $dailyLimiter->checkRateLimit($clientIp, 3, 86400);
    if (!$rlDaily['ok']) {
        $botReason = 'rate-limit-daily';
    }
}

if ($botReason === null) {
    $score = BotGuard::suspicionScore([
        'name'    => trim($data['name'] ?? ''),
        'phone'   => trim($data['telefon'] ?? ''),
        'firma'   => trim($data['firma'] ?? ''),
        'aufgabe' => trim($data['aufgabe'] ?? ''),
    ]);
    if ($score >= 2) {
        $botReason = 'content';
    }
}

if ($botReason !== null) {
    error_log("[send-signup] Bot erkannt ($botReason) von IP $clientIp");

    try {
        notifyAdminBotBlocked($smtpCreds, $botReason, $clientIp, $data);
    } catch (\Exception $e) {
        error_log('[send-signup] Bot-Alert-Mail fehlgeschlagen: ' . $e->getMessage());
    }

    echo json_encode(['success' => true]);
    exit;
}

// ─── Pflichtfelder prüfen ────────────────────────────────────────────────

$required = ['name', 'email'];
foreach ($required as $field) {
    if (empty(trim($data[$field] ?? ''))) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => "Feld '$field' ist erforderlich"]);
        exit;
    }
}

if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Ungültige E-Mail-Adresse']);
    exit;
}

if (($data['consent'] ?? '') !== 'on') {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Bitte bestätigen Sie die Datenschutzerklärung']);
    exit;
}

// ─── Daten bereinigen ────────────────────────────────────────────────────

$firma   = htmlspecialchars(trim($data['firma'] ?? ''));
$name    = htmlspecialchars(trim($data['name']));
$email   = htmlspecialchars(trim($data['email']));
$telefon = htmlspecialchars(trim($data['telefon'] ?? ''));
$anzahl  = htmlspecialchars(trim($data['anzahl'] ?? ''));
$termin  = htmlspecialchars(trim($data['termin'] ?? ''));
$aufgabe = htmlspecialchars(trim($data['aufgabe'] ?? ''));

// ─── Admin-E-Mail aufbauen ───────────────────────────────────────────────

function mailField(string $label, string $value): string {
    if ($value === '') return '';
    return "
            <div class='field'>
                <span class='label'>$label:</span>
                <div class='value'>" . nl2br($value) . "</div>
            </div>
            ";
}

$adminSubject = "📬 Neue Seminar-Anmeldung von $name";

$adminHtml = "
<!DOCTYPE html>
<html>
<head>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: #1a2332; color: white; padding: 20px; text-align: center; border-radius: 8px 8px 0 0; }
        .content { background: #f8f9fa; padding: 30px; border-radius: 0 0 8px 8px; }
        .field { margin-bottom: 20px; }
        .label { font-weight: bold; color: #1a2332; margin-bottom: 5px; display: block; }
        .value { background: white; padding: 12px; border-radius: 5px; border-left: 3px solid #c9a66b; word-break: break-word; }
        .footer { text-align: center; margin-top: 20px; color: #666; font-size: 12px; }
    </style>
</head>
<body>
    <div class='container'>
        <div class='header'>
            <h2>Seminar-Anmeldung</h2>
            <p>endlich-ki.de</p>
        </div>
        <div class='content'>
            " . mailField('Unternehmen', $firma) . "
            <div class='field'>
                <span class='label'>Name:</span>
                <div class='value'>$name</div>
            </div>
            <div class='field'>
                <span class='label'>E-Mail:</span>
                <div class='value'><a href='mailto:$email'>$email</a></div>
            </div>
            " . mailField('Telefon', $telefon) . "
            " . mailField('Teilnehmer', $anzahl) . "
            " . mailField('Wunschtermin', $termin) . "
            " . mailField('Aufgabe, die am meisten Zeit kostet', $aufgabe) . "
        </div>
        <div class='footer'>
            <p>Diese Nachricht wurde über das Anmeldeformular auf endlich-ki.de gesendet.</p>
            <p>Datum: " . date('d.m.Y H:i:s') . "</p>
        </div>
    </div>
</body>
</html>
";

// ─── Bestätigungsmail an den Anmelder ────────────────────────────────────

$confirmHtml = "
<!DOCTYPE html>
<html>
<head><meta charset='UTF-8'></head>
<body style='font-family:Arial,sans-serif;line-height:1.6;color:#333;margin:0;padding:0;'>
<table width='100%' cellpadding='0' cellspacing='0' style='max-width:600px;margin:0 auto;'>
<tr><td style='background:#1a2332;color:#fff;padding:20px;text-align:center;border-radius:8px 8px 0 0;'>
<h2 style='margin:0;'>Anmeldung erhalten</h2>
<p style='margin:4px 0 0;'>endlich-ki.de</p>
</td></tr>
<tr><td style='background:#f8f9fa;padding:30px;border-radius:0 0 8px 8px;'>
<p>Hallo $name,</p>
<p>vielen Dank für Ihre Anmeldung zum Seminar. Wir melden uns innerhalb eines Werktags, um alles weitere zu klären.</p>
<p>Fragen vorab? Antworten Sie einfach auf diese E-Mail.</p>
<p>Viele Grüße,<br>Jonathan Enns</p>
</td></tr>
</table>
</body>
</html>";

$confirmAltBody = "Hallo $name,\n\nvielen Dank für Ihre Anmeldung zum Seminar. Wir melden uns innerhalb eines Werktags, um alles weitere zu klären.\n\nFragen vorab? Antworten Sie einfach auf diese E-Mail.\n\nViele Grüße,\nJonathan Enns";

// ─── E-Mails senden ──────────────────────────────────────────────────────

try {
    sendSignupMail($smtpCreds, $smtpCreds['from_email'], $adminSubject, $adminHtml, $data['email'], trim($data['name']));

    try {
        sendSignupMail($smtpCreds, $data['email'], 'endlich-ki.de: Ihre Anmeldung ist eingegangen', $confirmHtml, null, null, $confirmAltBody);
    } catch (\Exception $e) {
        error_log('Bestätigungsmail (Anmeldung) fehlgeschlagen: ' . $e->getMessage());
    }

    echo json_encode(['success' => true]);

} catch (\Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Beim Versenden ist ein Fehler aufgetreten. Bitte versuchen Sie es später erneut.'
    ]);
    error_log('SMTP-Fehler (Anmeldung): ' . $e->getMessage());
}
