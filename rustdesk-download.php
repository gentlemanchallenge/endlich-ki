<?php
/**
 * Stabiler Download-Link für das RustDesk-Tool.
 * pCloud-Publink-Downloads sind nur wenige Stunden gültig und an die IP
 * gebunden, die den Direct-Download-Link angefordert hat. Deshalb holt der
 * Server bei jedem Aufruf einen frischen Link über die pCloud-API und
 * streamt die Datei selbst an den Besucher durch (statt zu redirecten).
 */

ini_set('display_errors', '0');
error_reporting(E_ALL);
ini_set('log_errors', '1');
set_time_limit(120);

const PCLOUD_CODE = 'XZK17k7ZJ51Sh3l3erYXqMBWzyq8S5L6k2uX';
const FILENAME = 'Setup-RustDesk.zip';

$response = @file_get_contents(
    'https://eapi.pcloud.com/getpublinkdownload?code=' . PCLOUD_CODE
);

$data = $response ? json_decode($response, true) : null;

if (!$data || ($data['result'] ?? null) !== 0 || empty($data['hosts'][0]) || empty($data['path'])) {
    http_response_code(502);
    echo 'Download aktuell nicht verfügbar. Bitte später erneut versuchen.';
    exit;
}

$downloadUrl = 'https://' . $data['hosts'][0] . $data['path'];

$ch = curl_init($downloadUrl);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => false,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_HEADERFUNCTION => function ($curl, $headerLine) {
        if (preg_match('/^content-length:\s*(\d+)/i', $headerLine, $m)) {
            header('Content-Length: ' . $m[1]);
        }
        return strlen($headerLine);
    },
    CURLOPT_WRITEFUNCTION => function ($curl, $chunk) {
        echo $chunk;
        return strlen($chunk);
    },
]);

header('Content-Type: application/zip');
header('Content-Disposition: attachment; filename="' . FILENAME . '"');
header('X-Content-Type-Options: nosniff');

$success = curl_exec($ch);
$curlError = curl_error($ch);
curl_close($ch);

if ($success === false) {
    error_log('rustdesk-download.php: curl error: ' . $curlError);
}
