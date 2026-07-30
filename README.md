# endlich-ki.de

Landingpage für KI-Seminare (B2B): Online-Live-Seminar, 1 Tag, eigene KI-Agenten bauen.

**Status: erster Entwurf.** Anmeldeformular ist noch nicht angebunden, Rechtstexte sind ungeprüft,
die Seite ist per `noindex` von Suchmaschinen ausgeschlossen.

## Struktur

| Datei | Inhalt |
|---|---|
| `index.html` | Komplette Landingpage (Hero, Painpoints, Zahlen, Lösung, Ablauf, Trainer, Preis, FAQ, Anmeldung). Enthält die Hero-Grafik als inline-SVG — sie muss inline liegen, damit die Maus-Parallaxe Zugriff auf das SVG-DOM hat. |
| `styles.css` | Design-System (Navy/Gold, Montserrat/Cormorant Garamond/Barlow Condensed) — übernommen von werbestimme.de |
| `impressum.html`, `datenschutz.html` | Rechtstexte, Entwurfsstand |
| `bilder/` | Portrait für den Trainer-Abschnitt, Foto-Banner als og:image |
| `Dockerfile`, `nginx.conf` | Statisches Hosting via nginx für Coolify |

## Deployment

Coolify baut das Dockerfile bei jedem Push auf `main` automatisch (Webhook).

## Offen vor dem echten Livegang

- Domain `endlich-ki.de` registrieren und in Coolify hinterlegen
- Termine bestätigen (aktuell Platzhalter: 24.09. / 22.10. / 19.11.2026)
- Anmeldeformular anbinden (Mailversand + Bestätigung)
- Konditionen prüfen: Umbuchungsfrist, Teamrabatt, Support-Zeitraum, Aufzeichnungsdauer
- Trainer-Text und Referenzen mit echten Angaben füllen
- Google Fonts lokal einbinden (siehe Datenschutzerklärung, Abschnitt 3)
- `noindex` entfernen, Sitemap + Analytics ergänzen
