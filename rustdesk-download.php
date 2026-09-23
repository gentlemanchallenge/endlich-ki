<?php
/**
 * Stabiler Download-Link für das RustDesk-Tool.
 * pCloud-Publink-Downloads sind nur wenige Stunden gültig, deshalb wird
 * bei jedem Aufruf über die pCloud-API ein frischer Direct-Download-Link
 * geholt und der Besucher dorthin weitergeleitet.
 */

ini_set('display_errors', '0');
error_reporting(E_ALL);
ini_set('log_errors', '1');

const PCLOUD_CODE = 'XZK17k7ZJ51Sh3l3erYXqMBWzyq8S5L6k2uX';

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

header('Location: ' . $downloadUrl, true, 302);
exit;
