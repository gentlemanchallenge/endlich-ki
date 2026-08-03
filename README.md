# endlich-ki.de

Landingpage für KI-Seminare (B2B): Online-Live-Seminar, 1 Tag, eigene KI-Agenten bauen.

**Status: erster Entwurf.** Rechtstexte sind ungeprüft, die Seite ist per `noindex` von
Suchmaschinen ausgeschlossen.

## Struktur

| Datei | Inhalt |
|---|---|
| `index.html` | Komplette Landingpage (Hero, Painpoints, Zahlen, Lösung, Ablauf, Trainer, Preis, FAQ, Anmeldung). Enthält die Hero-Grafik als inline-SVG — sie muss inline liegen, damit die Maus-Parallaxe Zugriff auf das SVG-DOM hat. |
| `styles.css` | Design-System (Navy/Gold, Montserrat/Cormorant Garamond/Barlow Condensed) — übernommen von werbestimme.de |
| `impressum.html`, `datenschutz.html` | Rechtstexte, Entwurfsstand |
| `bilder/` | Portrait für den Trainer-Abschnitt, Foto-Banner als og:image |
| `send-signup.php` | Handler fürs Anmeldeformular: Bot-Schutz (Honeypot, Rate-Limit, Tor-Guard, Gibberish-Erkennung), Admin- + Bestätigungsmail per PHPMailer/SMTP. Gleiches Muster wie `send-contact.php` bei werbestimme.de. |
| `lib/` | Wiederverwendbare PHP-Helfer (BotGuard, TorGuard, RateLimiter, BotAlertMail) |
| `Dockerfile`, `.htaccess` | Hosting via php:8-apache für Coolify |

## SMTP-Konfiguration

`send-signup.php` liest die Zugangsdaten aus Umgebungsvariablen (Coolify Environment Variables,
nicht im Repo): `SMTP_HOST`, `SMTP_PORT`, `SMTP_USERNAME`, `SMTP_PASSWORD`, `SMTP_FROM_EMAIL`,
`SMTP_FROM_NAME`.

## Deployment

Coolify baut das Dockerfile bei jedem Push auf `main` automatisch (Webhook).

## Offen vor dem echten Livegang

- Domain `endlich-ki.de` registrieren und in Coolify hinterlegen
- Termine bestätigen (aktuell Platzhalter: 24.09. / 22.10. / 19.11.2026)
- Konditionen prüfen: Umbuchungsfrist, Teamrabatt, Support-Zeitraum, Aufzeichnungsdauer
- Trainer-Text und Referenzen mit echten Angaben füllen
- Google Fonts lokal einbinden (siehe Datenschutzerklärung, Abschnitt 3)
- `noindex` entfernen, Sitemap + Analytics ergänzen
