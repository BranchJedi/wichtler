# Wichtler

Wichtel-App für Weihnachten: Gruppen erstellen, Leute einladen, Wünsche erfassen, fair und geheim auslosen.

- **Konzept und Regeln:** [docs/KONZEPT.md](docs/KONZEPT.md)
- **Betrieb mit Docker:** [DOCKER.md](DOCKER.md)

## Aufbau

| Ordner | Inhalt |
|---|---|
| `backend/` | Symfony 8 auf PHP 8.5, reine JSON-API unter `/api` |
| `frontend/` | React 19 mit TypeScript und Vite |
| `e2e/` | Browser-Abläufe gegen eine laufende Instanz |
| `docker/` | Apache- und PHP-Konfiguration, Entrypoint des Images |
| `.ddev/` | lokale Entwicklungsumgebung |

Die App versendet keine E-Mails. Benutzer melden sich mit Username und Passwort an; vergessene Zugangsdaten setzt der Wichtler-Admin zurück.

## Lokale Entwicklung

Voraussetzung: [ddev](https://ddev.com) und Docker.

```bash
ddev start
```

Beim Start werden die PHP-Pakete installiert, die JWT-Schlüssel erzeugt, die Migrationen ausgeführt (auch für die Testdatenbank) und der lokale Wichtler-Admin angelegt. Dessen Zugangsdaten stehen in `.ddev/config.yaml` unter `web_environment`.

| Adresse | Was |
|---|---|
| `https://symfony-wichtler.ddev.site:5173` | die App (Vite-Dev-Server, leitet `/api` an Symfony weiter) |
| `https://symfony-wichtler.ddev.site/api/doc` | Swagger UI |
| `https://symfony-wichtler.ddev.site/api/doc.json` | OpenAPI-Spezifikation |

### API ändern

Die API ist in PHP-Attributen beschrieben. Das Frontend erhält Typen und Hooks daraus über orval:

```bash
ddev api-generate
```

Der Befehl schreibt `frontend/openapi.json` neu und erzeugt `frontend/src/api/generated/`. `openapi.json` wird committet; ein Test schlägt fehl, wenn sie nicht mehr zum Code passt.

Neue Datenbankfelder:

```bash
ddev exec "cd backend && bin/console doctrine:migrations:diff"
```

```bash
ddev exec "cd backend && bin/console doctrine:migrations:migrate"
```

## Tests

| Was | Befehl |
|---|---|
| Backend (PHPUnit, braucht die ddev-Datenbank) | `ddev exec "cd backend && bin/phpunit"` |
| Frontend (Vitest) | `ddev exec "cd frontend && npm test"` |
| Typprüfung und Lint | `ddev exec "cd frontend && npm run typecheck && npm run lint"` |
| Browser-Abläufe | `e2e/run.sh` |

Die Browser-Abläufe steuern ein lokales Chrome ohne Fenster und laufen gegen den Docker-Stack auf `http://127.0.0.1:8089` (siehe [DOCKER.md](DOCKER.md), Abschnitt «Lokal testen»). Sie brauchen Node 22 oder neuer und keine weiteren Pakete. Sie legen Benutzer `e2e.*` und Gruppen `E2E *` an:

```bash
e2e/run.sh
```

```bash
e2e/run.sh cleanup
```

Ein einzelner Ablauf lässt sich über den Anfang des Dateinamens wählen, zum Beispiel `e2e/run.sh 04`. Nur gegen eine Testinstanz laufen lassen.

## Wo was liegt

| Thema | Ort |
|---|---|
| Auslosung (Matching, gleichverteilte Ziehung, Ausschluss-Grenze) | `backend/src/Draw/` |
| Ablauf einer Auslosung, Zurücksetzen | `backend/src/Service/DrawService.php` |
| Wer was sehen und tun darf | `backend/src/Security/Voter/GroupVoter.php`, `backend/config/packages/security.yaml` |
| Drosselung von Login und Registrierung | `backend/config/packages/rate_limiter.yaml`, `backend/src/Security/LoginRateLimiter.php` |
| Aufdecken des Wichtels | `frontend/src/groups/AssignmentReveal.tsx` |
| Alle Texte der Oberfläche | `frontend/src/i18n/de.ts` |
| Design: Farben, Schriften, Schatten (Tokens) | `frontend/src/index.css` |
| Illustrationen | `frontend/src/assets/illustrations/` |
| Inhalte der Login-Seite (Countdown, Schritte, Versprechen, FAQ) | `frontend/src/welcome/` |
