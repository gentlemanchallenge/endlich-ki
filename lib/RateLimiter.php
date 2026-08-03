<?php
/**
 * RateLimiter — IP-basiertes Zeitfenster-Limit fuer Formular-Einsendungen.
 *
 * Schlanker Auszug aus werbestimme.de/lib/SuppressionList.php: nur die
 * Rate-Limit- und Client-IP-Methoden, ohne die dortige Suppression-/
 * Unsubscribe-Logik, die es hier nicht braucht.
 */

class RateLimiter
{
    private string $rateLimitPath;

    public function __construct(?string $rateLimitPath = null)
    {
        $this->rateLimitPath = $rateLimitPath ?: __DIR__ . '/../data/signup-ratelimit.json';
    }

    public static function clientIp(): string
    {
        $candidates = [
            $_SERVER['HTTP_CF_CONNECTING_IP'] ?? null,
            $_SERVER['HTTP_X_FORWARDED_FOR'] ?? null,
            $_SERVER['REMOTE_ADDR'] ?? null,
        ];
        foreach ($candidates as $candidate) {
            if (!$candidate) continue;
            $ip = trim(explode(',', $candidate)[0]);
            if (filter_var($ip, FILTER_VALIDATE_IP)) return $ip;
        }
        return 'unknown';
    }

    public function checkRateLimit(string $ip, int $maxRequests = 5, int $windowSeconds = 3600): array
    {
        $now = time();
        $windowStart = $now - $windowSeconds;
        $entries = $this->loadRateLimit();

        $entries = array_filter($entries, fn($t) => $t >= $windowStart);

        $ipEntries = array_values(array_filter(
            $entries,
            fn($e, $k) => str_starts_with($k, $ip . ':'),
            ARRAY_FILTER_USE_BOTH
        ));

        if (count($ipEntries) >= $maxRequests) {
            $oldest = min($ipEntries);
            return ['ok' => false, 'retryIn' => max(60, ($oldest + $windowSeconds) - $now)];
        }

        $key = $ip . ':' . $now . ':' . bin2hex(random_bytes(4));
        $entries[$key] = $now;
        $this->saveRateLimit($entries);

        return ['ok' => true];
    }

    private function loadRateLimit(): array
    {
        if (!file_exists($this->rateLimitPath)) return [];
        $content = file_get_contents($this->rateLimitPath);
        $data = json_decode($content, true);
        return is_array($data) ? $data : [];
    }

    private function saveRateLimit(array $entries): void
    {
        $dir = dirname($this->rateLimitPath);
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        file_put_contents($this->rateLimitPath, json_encode($entries), LOCK_EX);
    }
}
