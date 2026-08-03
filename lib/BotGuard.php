<?php
/**
 * BotGuard — Heuristik gegen automatisierte Formular-Bots.
 *
 * Uebernommen aus werbestimme.de/lib/BotGuard.php (dort seit Juli 2026 im
 * Einsatz gegen Formular-Bots, die alle Felder mit einem einzelnen
 * Zufalls-Token fuellen). Gleiche Erkennung, hier fuer das Anmeldeformular.
 */

class BotGuard
{
    /**
     * Erkennt ein einzelnes Zufalls-Token: kein Leerzeichen, nur Buchstaben,
     * mindestens 14 Zeichen lang, und "huepft" oft zwischen Gross-/Klein-
     * schreibung hin und her.
     */
    public static function isGibberish(string $s): bool
    {
        $s = trim($s);
        if ($s === '' || preg_match('/\s/', $s) || !preg_match('/^[A-Za-z]{14,}$/', $s)) {
            return false;
        }

        $transitions = 0;
        $len = strlen($s);
        for ($i = 1; $i < $len; $i++) {
            if (ctype_upper($s[$i - 1]) !== ctype_upper($s[$i])) {
                $transitions++;
            }
        }

        return $transitions >= 5;
    }

    /** Telefonnummern bestehen nie aus Buchstaben. */
    public static function phoneLooksBogus(string $phone): bool
    {
        return $phone !== '' && (bool) preg_match('/[A-Za-z]/', $phone);
    }

    /**
     * Verdachts-Score ueber mehrere Felder. Ab 2 gilt die Anfrage als Bot.
     * @param array<string,string> $fields z.B. ['name'=>.., 'phone'=>.., 'firma'=>.., 'aufgabe'=>..]
     */
    public static function suspicionScore(array $fields): int
    {
        $score = 0;

        if (self::phoneLooksBogus($fields['phone'] ?? '')) {
            $score += 2;
        }

        foreach (['name', 'firma', 'aufgabe'] as $key) {
            if (self::isGibberish($fields[$key] ?? '')) {
                $score++;
            }
        }

        return $score;
    }
}
