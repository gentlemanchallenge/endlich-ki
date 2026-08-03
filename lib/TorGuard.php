<?php
/**
 * TorGuard — erkennt Anfragen von oeffentlichen Tor-Exit-Nodes.
 *
 * Uebernommen aus werbestimme.de/lib/TorGuard.php: fuer ein B2B-Anmelde-
 * formular ist der Abgleich gegen die offizielle Tor-Exit-Liste ein sehr
 * sicherer Filter, da kaum ein Geschaeftskunde sich ausgerechnet fuer eine
 * Seminar-Anmeldung hinter Tor verstecken wuerde. Die Liste wird lokal
 * gecacht, damit nicht jede Formular-Anfrage einen externen HTTP-Request
 * ausloest.
 */

require_once __DIR__ . '/FilePerms.php';

class TorGuard
{
    private const LIST_URL = 'https://check.torproject.org/torbulkexitlist';
    private const CACHE_TTL = 21600; // 6 Stunden
    private const FETCH_TIMEOUT = 3; // Sekunden — darf eine Formular-Antwort nie spuerbar verzoegern

    private static ?array $cachedIps = null;

    public static function isTorExit(string $ip): bool
    {
        if ($ip === '' || $ip === 'unknown') {
            return false;
        }
        return isset(self::loadExitList()[$ip]);
    }

    private static function loadExitList(): array
    {
        if (self::$cachedIps !== null) {
            return self::$cachedIps;
        }

        $cachePath = __DIR__ . '/../data/tor-exit-nodes.txt';
        $isStale = !file_exists($cachePath) || (time() - filemtime($cachePath)) > self::CACHE_TTL;

        if ($isStale) {
            $fresh = self::fetchExitList();
            if ($fresh !== null) {
                self::ensureDir(dirname($cachePath));
                file_put_contents($cachePath, $fresh, LOCK_EX);
                chownToApacheIfRoot($cachePath);
            } elseif (file_exists($cachePath)) {
                @touch($cachePath);
            } else {
                self::$cachedIps = [];
                return self::$cachedIps;
            }
        }

        $content = file_exists($cachePath) ? file_get_contents($cachePath) : '';
        $ips = array_filter(array_map('trim', explode("\n", $content)));
        self::$cachedIps = array_flip($ips);
        return self::$cachedIps;
    }

    private static function fetchExitList(): ?string
    {
        $ch = curl_init(self::LIST_URL);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, self::FETCH_TIMEOUT);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        $result = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($result === false || $httpCode !== 200 || strlen($result) < 1000) {
            return null;
        }
        return $result;
    }

    private static function ensureDir(string $dir): void
    {
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
    }
}
