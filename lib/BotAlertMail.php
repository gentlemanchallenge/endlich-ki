<?php
/**
 * BotAlertMail — Info-Mail an Jonathan, wenn send-signup.php einen Bot
 * erkannt und stillschweigend abgefangen hat.
 *
 * Uebernommen aus werbestimme.de/lib/BotAlertMail.php, auf ein Formular
 * (Seminar-Anmeldung) und dessen Felder zugeschnitten.
 */

// $reason ist einer von: 'tor-exit', 'honeypot', 'rate-limit', 'rate-limit-daily', 'content'
function notifyAdminBotBlocked(array $smtpCreds, string $reason, string $ip, array $rawData): void {
    $reasonLabels = [
        'tor-exit'         => 'Anfrage kam ueber einen oeffentlichen Tor-Exit-Node (anonymisierter Netzwerk-Ausgang) – fuer eine Geschaeftsanfrage absolut unueblich',
        'honeypot'         => 'Verstecktes Honeypot-Feld wurde ausgefuellt (das macht nur Software, kein Mensch sieht das Feld)',
        'rate-limit'       => 'Mehr als 10 Anfragen pro Stunde von dieser IP',
        'rate-limit-daily' => 'Mehr als 3 Anfragen innerhalb von 24 Stunden von dieser IP',
        'content'          => 'Formularfelder enthielten Zufalls-Zeichenketten statt echtem Text',
    ];
    $reasonLabel = $reasonLabels[$reason] ?? $reason;

    $userAgent = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 200);
    $dateStr = (new DateTime('now', new DateTimeZone('Europe/Berlin')))->format('d.m.Y H:i');

    $fieldRows = '';
    foreach (['firma', 'name', 'email', 'telefon', 'anzahl', 'termin', 'aufgabe'] as $field) {
        $value = trim($rawData[$field] ?? '');
        if ($value === '') continue;
        $fieldRows .= '<tr><td style="color:#64748b;vertical-align:top;padding-right:12px">' . htmlspecialchars($field) . '</td>'
                    . '<td>' . htmlspecialchars(mb_substr($value, 0, 300)) . '</td></tr>';
    }

    $html = '<!DOCTYPE html><html><body style="font-family:sans-serif;color:#1a2332">'
          . '<h2 style="color:#1a2332;border-bottom:2px solid #c9a66b;padding-bottom:8px">Formular-Bot abgewehrt</h2>'
          . '<p>Eine Anfrage ueber die <strong>Seminar-Anmeldung</strong> wurde als Bot eingestuft und '
          . 'stillschweigend abgefangen: der Absender bekam eine normale Erfolgsmeldung zurueck, es wurde aber '
          . '<strong>keine E-Mail verschickt</strong>.</p>'
          . '<table cellpadding="6" style="border-collapse:collapse;margin:12px 0">'
          . '<tr><td style="color:#64748b">Grund</td><td><strong>' . htmlspecialchars($reasonLabel) . '</strong></td></tr>'
          . '<tr><td style="color:#64748b">Zeit</td><td>' . $dateStr . '</td></tr>'
          . '<tr><td style="color:#64748b">IP</td><td>' . htmlspecialchars($ip) . '</td></tr>'
          . '<tr><td style="color:#64748b">User-Agent</td><td style="font-size:0.85em">' . htmlspecialchars($userAgent) . '</td></tr>'
          . $fieldRows
          . '</table>'
          . '<p style="color:#64748b;font-size:0.9em">Falls das eine echte Anfrage war: bitte kurz melden, '
          . 'dann justieren wir die Erkennung nach.</p>'
          . '</body></html>';

    $mail = new PHPMailer\PHPMailer\PHPMailer(true);
    $mail->isSMTP();
    $mail->Host       = $smtpCreds['host'];
    $mail->SMTPAuth   = true;
    $mail->Username   = $smtpCreds['username'];
    $mail->Password   = $smtpCreds['password'];
    $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
    $mail->Port       = $smtpCreds['port'];
    $mail->CharSet    = 'UTF-8';
    $mail->setFrom($smtpCreds['from_email'], $smtpCreds['from_name']);
    $mail->addAddress($smtpCreds['from_email']);
    $mail->isHTML(true);
    $mail->Subject = '🤖 Formular-Bot abgewehrt (Seminar-Anmeldung)';
    $mail->Body    = $html;
    $mail->send();
}
