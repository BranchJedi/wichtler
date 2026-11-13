# Betrieb mit Docker

Die App läuft als Docker-Compose-Stack hinter einem Nginx Proxy Manager, der TLS übernimmt.

## Aufbau

| Service | Aufgabe |
|---|---|
| `wichtler_app` | Apache mit PHP 8.5: liefert die React-App aus und bedient `/api` |
| `wichtler_migrations` | läuft bei jedem Start einmal: Migrationen, abgelaufene Sitzungen aufräumen, Wichtler-Admin anlegen |
| `wichtler_db` | MariaDB 11.8 |

| Netz | Wer hängt daran | Zweck |
|---|---|---|
| `wichtler-internal` | alle drei Services | App und Datenbank unter sich; kein Weg nach aussen |
| `wichtler-app` (extern) | `wichtler_app` und der Proxy Manager | einziger Zugang zur App |

Die Datenbank veröffentlicht keinen Port und ist nur aus dem internen Netz erreichbar.

| Volume | Inhalt |
|---|---|
| `symfony-wichtler_db_data` | Datenbank |
| `symfony-wichtler_jwt_keys` | Schlüssel, mit denen Sitzungen signiert werden |

## Erste Inbetriebnahme

1. Das Netz für den Proxy Manager anlegen (einmalig):

    ```bash
    docker network create wichtler-app
    ```

2. Die Konfiguration anlegen und **alle** `replace-with-…`-Werte ersetzen:

    ```bash
    cp .env.docker.example .env.docker
    ```

    | Variable | Bedeutung |
    |---|---|
    | `DOCKER_APP_SECRET` | 64 zufällige Zeichen, z. B. aus `openssl rand -hex 32` |
    | `DOCKER_DEFAULT_URI` | öffentliche Adresse der App, z. B. `https://wichtler.example.com` |
    | `DOCKER_DB_ROOT_PASSWORD`, `DOCKER_DB_PASSWORD` | Passwörter der Datenbank |
    | `DOCKER_JWT_PASSPHRASE` | schützt den Signaturschlüssel |
    | `DOCKER_ADMIN_USERNAME`, `DOCKER_ADMIN_PASSWORD` | Zugang des Wichtler-Admins (Benutzer 1), Passwort mindestens 10 Zeichen |

    Die Admin-Zugangsdaten gelten nur für das erste Anlegen. Danach ändert der Admin sein Passwort im Profil der App; ein geänderter Wert in `.env.docker` hat keine Wirkung mehr.

3. Starten:

    ```bash
    docker compose up -d --build
    ```

4. Den Proxy Manager ans Netz hängen (Containername anpassen):

    ```bash
    docker network connect wichtler-app nginx-proxy-manager
    ```

5. Im Proxy Manager einen **Proxy Host** anlegen:

    | Feld | Wert |
    |---|---|
    | Domain Names | die Domain aus `DOCKER_DEFAULT_URI` |
    | Scheme | `http` |
    | Forward Hostname | `wichtler_app` |
    | Forward Port | `80` |
    | SSL | Zertifikat wählen, «Force SSL» einschalten |

    Websockets werden nicht gebraucht. Die App setzt ihre Sicherheits-Header selbst, auch `Strict-Transport-Security` für Anfragen, die über HTTPS hereinkommen.

Die Anmeldung funktioniert nur über HTTPS, weil die Sitzungs-Cookies als `Secure` markiert sind. Ausnahme ist `localhost` beim lokalen Testen.

## Starten, stoppen, aktualisieren

```bash
docker compose up -d
```

```bash
docker compose down
```

Nach einem `git pull` neu bauen; Migrationen laufen beim Start automatisch:

```bash
docker compose up -d --build
```

Zustand und Logs:

```bash
docker compose ps
```

```bash
docker compose logs -f wichtler_app
```

`wichtler_app` meldet sich als `healthy`, sobald `/api/health` die Datenbank erreicht. `wichtler_migrations` beendet sich nach getaner Arbeit mit `Exited (0)`; das ist kein Fehler.

## Datensicherung

Sichern:

```bash
docker compose exec -T wichtler_db sh -c 'mariadb-dump -u"$MARIADB_USER" -p"$MARIADB_PASSWORD" --single-transaction "$MARIADB_DATABASE"' > wichtler-backup.sql
```

Zurückspielen (überschreibt den aktuellen Stand):

```bash
docker compose exec -T wichtler_db sh -c 'mariadb -u"$MARIADB_USER" -p"$MARIADB_PASSWORD" "$MARIADB_DATABASE"' < wichtler-backup.sql
```

Die Sicherung enthält die Zuteilungen der Auslosungen im Klartext. Sie gehört so geschützt wie die Datenbank selbst.

Das Volume mit den JWT-Schlüsseln muss nicht gesichert werden. Geht es verloren, werden neue Schlüssel erzeugt und alle Benutzer müssen sich einmal neu anmelden.

## Vergessene Zugangsdaten

**Benutzer:** Der Wichtler-Admin öffnet in der App «Benutzer», sucht die Person, liest ihren Username ab oder klickt «Passwort zurücksetzen». Das temporäre Passwort wird einmal angezeigt; nach der Anmeldung damit muss die Person ein neues wählen.

**Wichtler-Admin selbst:**

```bash
docker compose exec wichtler_app php bin/console app:user:reset-password admin
```

Der Befehl gibt ein temporäres Passwort aus und meldet das Konto überall ab.

## Lokal testen

Ohne Proxy Manager lässt sich der Stack direkt ansprechen. In `.env.docker`:

```dotenv
DOCKER_DEFAULT_URI=http://localhost:8089
DOCKER_HTTP_BIND=127.0.0.1:8089:80
```

Danach ist die App unter `http://localhost:8089` erreichbar. Ohne `DOCKER_HTTP_BIND` bindet Docker einen zufälligen Port auf `127.0.0.1`; `docker compose port wichtler_app 80` zeigt ihn an. Von aussen ist der Port in keinem Fall erreichbar.

Die Browser-Abläufe in `e2e/` laufen gegen genau diese Adresse, siehe [README.md](README.md).

## Sicherheit

- `.env.docker` enthält alle Geheimnisse und wird nicht versioniert.
- Der Debugmodus von Symfony ist im Produktivbetrieb aus; der Container verweigert den Start mit `DOCKER_APP_DEBUG=1`.
- `DOCKER_TRUSTED_PROXIES=REMOTE_ADDR` vertraut den `X-Forwarded-*`-Headern des direkten Gegenübers. Das ist richtig, solange nur der Proxy Manager den Container erreicht. Davon hängt ab, dass die Drosselung von Login und Registrierung die echte Adresse der Besucher zählt.
- Die API-Dokumentation unter `/api/doc` ist im Produktivbetrieb nur für den Wichtler-Admin erreichbar.
- Wer Zugriff auf die Datenbank hat, kann die Zuteilungen lesen. Der Wichtler-Admin kann sie auch in der App einsehen; ein Gruppenadmin nicht.

## Alles entfernen

Stoppt den Stack und löscht Datenbank und Schlüssel unwiderruflich:

```bash
docker compose down -v
```
