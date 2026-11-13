# Wichtler – Konzept

Wichtel-App für Weihnachten: Gruppen erstellen, Teilnehmer einladen, Wünsche erfassen, fair und geheim auslosen.

Stand: 13.11.2026 · Status: umgesetzt (Etappen 1 bis 8)

---

## 1. Ziele und Abgrenzung

**Im Umfang**

- Registrierung und Login mit Username und Passwort, ohne E-Mail
- Wichtelgruppen erstellen, bearbeiten, löschen
- Einladung per Username oder Share-Link
- Wünsche pro Teilnehmer und Gruppe
- Ausschlüsse («diese Person will ich nicht ziehen»)
- Geheime, gleichverteilte Auslosung mit Blur-/Reveal-Anzeige
- Wichtler-Admin (User 1) mit Vollzugriff, integriert ins normale UI

**Nicht im Umfang**

- E-Mail-Versand jeglicher Art (kein Passwort-Reset per Mail, keine Benachrichtigungen)
- Push-Benachrichtigungen, Chat, Bezahlfunktionen
- Mehrsprachigkeit (UI nur Deutsch, Texte aber von Beginn an in Übersetzungsdateien)

---

## 2. Rollen

| Rolle | Wer | Darf |
|---|---|---|
| **Gast** | nicht angemeldet | registrieren, anmelden, Share-Link öffnen (führt zu Login/Registrierung) |
| **User** | jeder registrierte Account | Gruppen erstellen, Einladungen annehmen/ablehnen, eigenes Profil und Passwort ändern |
| **Teilnehmer** | aktives Mitglied einer Gruppe | Teilnehmerliste sehen, eigene Wünsche und Ausschlüsse pflegen, nach der Auslosung den eigenen Wichtel aufdecken |
| **Gruppenadmin** | Ersteller der Gruppe | zusätzlich: Gruppe bearbeiten/löschen, einladen, Teilnehmer entfernen/inaktiv setzen, Auslosung starten/löschen. Ist selbst Teilnehmer. |
| **Wichtler-Admin** | User 1 (`ROLE_ADMIN`) | alles: alle Gruppen listen, ansehen, bearbeiten, löschen; alle Auslosungen einsehen; Username und Passwort anderer User zurücksetzen |

Der Gruppenadmin ist keine globale Rolle, sondern ergibt sich aus `WichtelGroup.owner`. Der Wichtler-Admin wird per Seed-Command angelegt (ergibt in einer frischen Datenbank ID 1) und über `ROLE_ADMIN` erkannt, nicht über die ID.

---

## 3. Datenmodell

```
User 1 ──< GroupMember >── 1 WichtelGroup
              │ 1                 │ 1
              ├──< Wish           └──< Assignment (giver → receiver)
              └──< Exclusion (member → excludedMember)
```

### 3.1 User

| Feld | Typ | Bemerkung |
|---|---|---|
| `id` | int | |
| `firstName` | string(80) | Pflicht |
| `lastName` | string(80) | Pflicht |
| `username` | string(30), unique | Pflicht. 3–30 Zeichen, `a-z 0-9 . _ -`, kleingeschrieben gespeichert |
| `password` | string | Argon2id-Hash, mindestens 10 Zeichen |
| `email` | string(180), nullable, unique | **ungenutzt**, nur für spätere Erweiterung. Nicht im UI, nicht in Registrierung |
| `roles` | json | `ROLE_USER`, zusätzlich `ROLE_ADMIN` für User 1 |
| `mustChangePassword` | bool | wird nach Admin-Reset gesetzt, erzwingt Passwortwechsel beim nächsten Login |
| `createdAt`, `updatedAt` | datetime | |

### 3.2 WichtelGroup

| Feld | Typ | Bemerkung |
|---|---|---|
| `id` | int | |
| `title` | string(120) | Pflicht |
| `description` | text, nullable | *zusätzlich:* Freitext (Ort, Regeln) |
| `year` | smallint | Pflicht, Default aktuelles Jahr |
| `budgetMin` | int, nullable | ganze Einheiten der Gruppenwährung |
| `budgetMax` | int, nullable | `budgetMin ≤ budgetMax` |
| `currency` | string(3) | *zusätzlich:* ISO-4217-Code, Default `CHF`. Auswahl im UI: CHF, EUR, USD, GBP |
| `eventDate` | date | Wichteldatum, Pflicht |
| `minWishAmount` | smallint | Pflicht, 0–20, Default 3 |
| `isDrawed` | bool | ob ausgelost wurde |
| `drawnAt` | datetime, nullable | *zusätzlich:* Zeitpunkt der Auslosung |
| `owner` | → User | *zusätzlich:* Gruppenadmin |
| `inviteToken` | string(43), unique, nullable | *zusätzlich:* Token des Share-Links (256 Bit, URL-safe) |
| `createdAt`, `updatedAt` | datetime | |

**`drawable`** wird in der API als Boolean ausgeliefert, aber **nicht gespeichert**, sondern bei jedem Lesen berechnet (siehe 6.1). Ein gespeichertes Flag müsste bei jeder Wunsch-, Mitglieder- und Ausschluss-Änderung nachgeführt werden und könnte veralten.

Budget im UI: ein Range-Slider mit zwei Griffen (Min/Max), über einen Schalter «Budget festlegen» ein- und ausblendbar. Ausgeschaltet = beide Felder `null`. Die Währung wird neben dem Slider gewählt und überall zusammen mit dem Budget angezeigt.

### 3.3 GroupMember

| Feld | Typ | Bemerkung |
|---|---|---|
| `id` | int | |
| `group` | → WichtelGroup | |
| `user` | → User | unique zusammen mit `group` |
| `status` | enum | `invited` · `active` · `inactive` |
| `invitedVia` | enum | `username` · `link` · `owner` |
| `joinedAt` | datetime, nullable | |

- `invited`: per Username eingeladen, noch nicht bestätigt. Zählt nirgends mit.
- `active`: nimmt an der Auslosung teil.
- `inactive`: vom Gruppenadmin deaktiviert. Bleibt in der Liste sichtbar (ausgegraut), wird nicht ausgelost, muss keine Wünsche erfüllen.

### 3.4 Wish

| Feld | Typ | Bemerkung |
|---|---|---|
| `id` | int | |
| `member` | → GroupMember | Wünsche gelten pro Gruppe, nicht global |
| `title` | string(160) | Pflicht |
| `description` | text, nullable | |
| `url` | string(500), nullable | nur `http(s)` |
| `position` | smallint | Reihenfolge |

### 3.5 Exclusion

| Feld | Typ | Bemerkung |
|---|---|---|
| `member` | → GroupMember | wer ausschliesst |
| `excludedMember` | → GroupMember | wer nicht gezogen werden soll. Unique zusammen mit `member` |
| `createdAt` | datetime | für die Bereinigung bei schrumpfender Gruppe |

### 3.6 Assignment

| Feld | Typ | Bemerkung |
|---|---|---|
| `group` | → WichtelGroup | |
| `giver` | → GroupMember | unique pro Gruppe |
| `receiver` | → GroupMember | unique pro Gruppe |

---

## 4. Authentifizierung

- Registrierung: Vorname, Nachname, Username, Passwort, Passwort-Wiederholung. Keine E-Mail.
- Login: Username + Passwort.
- Technik: LexikJWT wie im `symfony-dart-helper`, Access-Token (15 min) und Refresh-Token (14 Tage) als `HttpOnly`-, `Secure`-, `SameSite=Strict`-Cookies. Kein Token im JavaScript-Speicher.
- Login und Registrierung sind per `symfony/rate-limiter` gedrosselt, weil es keine E-Mail-Verifikation gibt: 5 falsche Passwörter pro Username und Adresse, 25 pro Adresse über alle Usernames (je 15 Minuten), 30 Registrierungen pro Adresse und Stunde. Gezählt werden nur echte Passwort-Fehlversuche, nicht fehlgeschlagene Sitzungs-Erneuerungen oder abgelaufene Tokens.
- **Vergessene Zugangsdaten:** Der Wichtler-Admin sucht den User auf der Seite «Benutzer» (nach Name oder Username), sieht den Username und kann ihn ändern. Für ein vergessenes Passwort erzeugt der Server ein temporäres Passwort und zeigt es dem Admin genau einmal an; gespeichert wird nur der Hash. Der User wird dabei auf allen Geräten sofort abgemeldet und `mustChangePassword` gesetzt. Die Übergabe des temporären Passworts geschieht ausserhalb der App.
- **Erzwungener Passwortwechsel:** Solange `mustChangePassword` gesetzt ist, lässt die API nur das eigene Profil, den Passwortwechsel und das Abmelden zu; alles andere beantwortet sie mit `403`.
- **Sofortiges Abmelden:** Jedes Zugriffstoken trägt die Sitzungsversion des Users. Passwortwechsel, Reset und Username-Änderung erhöhen sie, ältere Tokens werden dann sofort abgelehnt und nicht erst nach Ablauf.
- **Veraltete Cookies:** Login, Registrierung, Erneuerung und Abmelden ignorieren ein vorhandenes Sitzungs-Cookie. So verhindert ein ungültig gewordenes Cookie nicht die erneute Anmeldung.

---

## 5. Gruppen und Mitglieder

### 5.1 Lebenszyklus einer Gruppe

```
Offen ──(≥3 Aktive, alle Wünsche erfüllt)──> Bereit ──Auslosung──> Ausgelost
  ^                                                                    │
  └───────────────────── Auslosung löschen ────────────────────────────┘
```

### 5.2 Was ist wann erlaubt

| Aktion | vor Auslosung | nach Auslosung |
|---|---|---|
| Titel, Beschreibung, Wichteldatum ändern | ✅ | ✅ |
| Jahr, Budget, Währung, `minWishAmount` ändern | ✅ | ❌ |
| Gruppe löschen | ✅ (Bestätigungs-Overlay) | ❌ |
| Einladen (Username / Share-Link) | ✅ | ❌ Share-Link liefert «Einladung abgelaufen» |
| Teilnehmer entfernen, inaktiv/aktiv setzen | ✅ | ❌ |
| Gruppe selbst verlassen (nicht Gruppenadmin) | ✅ | ❌ |
| Eigene Wünsche hinzufügen, bearbeiten | ✅ | ✅ |
| Eigene Wünsche löschen | ✅ | ✅ solange `minWishAmount` nicht unterschritten wird |
| Ausschlüsse ändern | ✅ | ❌ |
| Auslosung starten | ✅ wenn `drawable` | – |
| Auslosung löschen | – | ✅ (Bestätigungs-Overlay) |

Verstösse beantwortet die API mit `409 Conflict`. Die Regeln gelten auch für den Wichtler-Admin: Er muss eine Auslosung ebenfalls zuerst löschen, bevor er die Gruppe löscht. Das ist eine Schutzstufe, keine fehlende Berechtigung.

Beim Entfernen eines Teilnehmers werden dessen Wünsche und alle Ausschlüsse von und auf ihn gelöscht.

### 5.3 Einladung per Username

1. Gruppenadmin tippt den Username ein. Existiert er nicht: Fehlermeldung.
2. Es entsteht ein `GroupMember` mit Status `invited`.
3. Der Eingeladene sieht die Einladung auf seiner Gruppenübersicht und bestätigt oder lehnt im Detail-Overlay ab. Ablehnen löscht den Eintrag.

### 5.4 Einladung per Share-Link

Link: `https://<host>/einladung/<inviteToken>`. Der Gruppenadmin kann ihn kopieren und neu generieren (der alte wird ungültig).

| Szenario | Ablauf |
|---|---|
| **1. User existiert, nicht angemeldet** | Login-Seite mit Hinweis «Du wurdest eingeladen». Nach erfolgreichem Login erscheint das Einladungs-Overlay. |
| **2. User existiert, angemeldet** | Direkt das Einladungs-Overlay. |
| **3. Kein User** | Von der Login-Seite auf «Registrieren». Nach erfolgreicher Registrierung ist der User angemeldet und sieht das Einladungs-Overlay. |

Technisch: Die Einladungsseite liegt im angemeldeten Bereich. Gäste werden zum Login umgeleitet, das Ziel reist im Verlaufseintrag des Browsers mit (auch über den Wechsel zur Registrierung und ein Neuladen hinweg) und wird nach der Anmeldung wieder geöffnet. Die App kann Szenario 1 und 3 nicht unterscheiden, deshalb zeigt die Login-Seite beide Wege an.

Das Einladungs-Overlay zeigt Titel, Jahr, Wichteldatum, Budget, Gruppenadmin und Anzahl Teilnehmer, mit «Beitreten» und «Abbrechen». Die Vorschau ist nur angemeldet abrufbar, anonyme Besucher erfahren nichts über die Gruppe. Wer schon Mitglied ist, landet direkt in der Gruppe.

### 5.5 Sichtbarkeit

| Daten | Teilnehmer | Gruppenadmin | Wichtler-Admin |
|---|---|---|---|
| Teilnehmerliste (Name, Username, Status) | ✅ | ✅ | ✅ |
| Eigene Wünsche | ✅ | ✅ | ✅ |
| Wünsche anderer | nur die des eigenen Wichtels, nach Auslosung | wie Teilnehmer | ✅ |
| Wunsch-Fortschritt anderer (z. B. 2/3) | ❌ | ✅ | ✅ |
| Eigene Ausschlüsse | ✅ | ✅ | ✅ |
| Ausschlüsse anderer | ❌ | ❌ | ❌ |
| Eigene Zuteilung | ✅ | ✅ | ✅ |
| Zuteilungen anderer | ❌ | ❌ | ✅ |

Wünsche anderer bleiben verborgen, weil sonst das Blurren der Wichtel-Wünsche wirkungslos wäre.

---

## 6. Auslosung

### 6.1 Voraussetzungen (`drawable`)

`drawable = true`, wenn alle Punkte zutreffen:

1. `isDrawed = false`
2. mindestens 3 Teilnehmer mit Status `active`
3. jeder aktive Teilnehmer hat mindestens `minWishAmount` Wünsche
4. die Matching-Prüfung (6.3) findet eine gültige Zuteilung

Der Gruppenadmin sieht im Bereich «Auslosung» eine Checkliste mit den offenen Punkten und der Liste der Teilnehmer, denen noch Wünsche fehlen (`Anna Muster – 1 von 3`). Der Button «Auslosung starten» ist bis dahin deaktiviert.

### 6.2 Ausschlüsse

Jeder aktive Teilnehmer kann in der Gruppe Personen markieren, die er nicht ziehen möchte. Mit `n` = Anzahl aktiver Teilnehmer und `N = ⌊n/2⌋ − 1` gilt:

- **Regel A:** Jeder schliesst höchstens `N` Personen aus.
- **Regel B:** Jede Person wird von höchstens `N` anderen ausgeschlossen.

| n | 3 | 4 | 5 | 6 | 7 | 8 | 10 | 12 |
|---|---|---|---|---|---|---|---|---|
| N | 0 | 1 | 1 | 2 | 2 | 3 | 4 | 5 |

Ohne Regel B könnte die Auslosung schon bei `N = 1` scheitern, wenn alle dieselbe Person ausschliessen. Mit beiden Regeln darf jeder mindestens `n − 1 − N ≥ n/2` Personen ziehen und kann von mindestens `n/2` Personen gezogen werden. Nach dem Heiratssatz von Hall existiert dann immer eine gültige Zuteilung.

Verhalten:

- Die API lehnt einen Ausschluss mit `422` ab, wenn er Regel A oder B verletzt. Meldung bei Regel B bewusst neutral: «Diese Person kann nicht weiter ausgeschlossen werden.»
- Ausschlüsse sind nur für den Ersteller sichtbar.
- **Schrumpft die Gruppe** (Teilnehmer entfernt, inaktiv gesetzt, ausgetreten), sinkt `N`. Verletzen bestehende Ausschlüsse danach eine Regel, werden die jeweils neuesten automatisch gelöscht, bis beide Regeln wieder gelten. Betroffene sehen in der Gruppe einen Hinweis, bis sie ihn quittieren. Wird ein Teilnehmer inaktiv gesetzt, entfallen auch seine eigenen Ausschlüsse und die Ausschlüsse anderer auf ihn. So entsteht nie ein Zustand, den nur ein unbekannter Dritter auflösen könnte.

### 6.3 Algorithmus

Service `DrawService`, läuft in einer Datenbank-Transaktion mit Sperre auf der Gruppenzeile (verhindert doppelte Auslosung).

1. **Eingabe laden:** aktive Teilnehmer und ihre Ausschlüsse. Voraussetzungen aus 6.1 und Regeln A/B erneut prüfen.
2. **Existenz prüfen (Hopcroft-Karp):** bipartiter Graph Schenkende → Beschenkte mit einer Kante, wenn weder Selbstziehung noch Ausschluss vorliegt. Ist das maximale Matching kleiner als `n`, Abbruch mit Fehler. Durch A/B sollte das nie eintreten, die Prüfung ist das Sicherheitsnetz gegen Datenfehler.
3. **Gleichverteilt ziehen (Rejection Sampling):** zufällige Permutation per Fisher-Yates mit kryptografisch sicherem Zufall (`Random\Randomizer` mit `Secure`-Engine) erzeugen. Verwerfen, sobald jemand sich selbst oder eine ausgeschlossene Person zieht. Wiederholen bis zur ersten gültigen Permutation. Jede gültige Zuteilung ist damit gleich wahrscheinlich.
4. **Speichern:** `Assignment`-Zeilen schreiben, `isDrawed = true`, `drawnAt` setzen, offene Einladungen (`invited`) löschen, `inviteToken` entwerten.

**Kein Greedy-Verfahren**, auch nicht als Rückfall: Es bevorzugt bestimmte Zuteilungen, woraus sich Rückschlüsse ziehen lassen.

Zweierpaare (A zieht B, B zieht A) sind erlaubt. Ein Verbot würde die Zahl gültiger Zuteilungen weiter verkleinern.

**Grenze des Rejection Sampling:** Ohne Ausschlüsse ist etwa jeder dritte Versuch gültig (1/e). Bei voll ausgeschöpften Ausschlüssen sinkt die Trefferquote grob um den Faktor 2 pro Teilnehmer. Bis etwa 16 Teilnehmer ist das auch im ungünstigsten Fall unkritisch, darüber nur bei wenigen Ausschlüssen. Die Auslosung bricht nach 5 Sekunden mit einer klaren Meldung ab («Zu viele Ausschlüsse für diese Gruppengrösse»). Ein exakter Sampler für grosse Gruppen wäre eine spätere Erweiterung.

### 6.4 Hinweis auf Rückschlüsse

Bei kleinen Gruppen oder vielen Ausschlüssen bleiben wenige gültige Zuteilungen übrig. Teilnehmer können dann aus der eigenen Ziehung auf die anderen schliessen. Bei `n = 3` gibt es genau zwei Zuteilungen: Wer weiss, wen er zieht, kennt die ganze Auslosung.

Die App weist darauf hin:

- im Bestätigungs-Overlay der Auslosung, wenn `n ≤ 4` oder Ausschlüsse vorhanden sind
- beim Erfassen von Ausschlüssen als dauerhafter Infotext

### 6.5 Starten und Löschen

- **Starten:** Bestätigungs-Overlay mit Teilnehmerzahl, Hinweis auf die danach gesperrten Aktionen und allfälliger Warnung aus 6.4. Offene Einladungen werden als «verfallen bei Auslosung» aufgeführt.
- **Löschen:** Bestätigungs-Overlay. Alle `Assignment`-Zeilen werden gelöscht, `isDrawed = false`, `drawnAt = null`, neuer `inviteToken`. Die Gruppe ist wieder im Zustand «Offen/Bereit». Hinweis im Overlay: Wer seinen Wichtel schon aufgedeckt hat, kennt die alte Zuteilung.

### 6.6 Geheimhaltung und Blur

- **Gruppenübersicht:** zeigt nur den Status «Ausgelost», keine Namen. Der Listen-Endpoint enthält keine Zuteilungsdaten.
- **Gruppenseite:** Karte «Dein Wichtel» mit geblurrtem Platzhalter und Button «Wichtel aufdecken». Erst der Klick lädt die Zuteilung vom Server. Name und Wünsche des Wichtels werden gemeinsam aufgedeckt und mit «Verbergen» gemeinsam wieder geblurrt. Beim Verlassen der Seite wird der Zustand verworfen. Vor dem Klick steht der Name weder im DOM noch in einer Netzwerkantwort.
- **Gruppenadmin:** hat keinen Endpoint, der fremde Zuteilungen liefert. Er sieht wie jeder Teilnehmer nur die eigene. Die Prüfung erfolgt serverseitig in einem Symfony-Voter, nicht im Frontend.
- **Wichtler-Admin:** sieht in jeder Gruppe zusätzlich die Karte «Alle Zuteilungen». Jede Zeile ist einzeln geblurrt und wird einzeln aufgedeckt; auch hier lädt erst der Klick die jeweilige Zuteilung.
- Antworten mit einer Zuteilung tragen `Cache-Control: no-store`.
- Logs und Fehlermeldungen enthalten nie Zuteilungen.

Die Zuteilungen liegen im Klartext in der Datenbank, weil der Wichtler-Admin sie einsehen können muss. Wer Datenbankzugriff hat, kann sie lesen.

---

## 7. Wichtler-Admin im UI

Kein eigener Bereich, sondern Erweiterungen des normalen UI, die nur mit `ROLE_ADMIN` erscheinen:

- **Gruppenübersicht:** Umschalter «Meine Gruppen / Alle Gruppen». Fremde Gruppen tragen ein Admin-Badge.
- **Gruppenseite:** alle Aktionen des Gruppenadmins, zusätzlich die Karte «Alle Zuteilungen».
- **Navigation:** Eintrag «Benutzer» mit Suche. Pro User: Name und Username bearbeiten, temporäres Passwort setzen. Dazu ein Link «API-Dokumentation» auf `/api/doc`.

---

## 8. Screens

| Route | Inhalt |
|---|---|
| `/login`, `/registrieren` | Formulare, optional mit Einladungshinweis |
| `/` | Gruppenübersicht: Karten mit Titel, Jahr, Wichteldatum, Anzahl Teilnehmer, Status, Rolle. Offene Einladungen zuoberst. Button «Neue Gruppe» |
| `/gruppen/:id` | Kopf (Titel, Datum, Budget), «Dein Wichtel» (nach Auslosung), Teilnehmerliste, «Meine Wünsche», «Meine Ausschlüsse», für Admins «Verwaltung» und «Auslosung» |
| `/einladung/:token` | löst den Einladungsablauf aus 5.4 aus |
| `/profil` | Name, Username, Passwort ändern |
| `/benutzer` | nur Wichtler-Admin |

**Detail-Overlays** (modale Dialoge): Gruppe erstellen/bearbeiten, Gruppe löschen, Einladung bestätigen, Teilnehmer entfernen, Wunsch erfassen/bearbeiten, Auslosung starten, Auslosung löschen, Passwort zurücksetzen. Destruktive Overlays haben einen rot markierten Bestätigungsbutton, «Gruppe löschen» verlangt zusätzlich das Eintippen des Gruppentitels.

Layout mobile-first, da Share-Links meist auf dem Handy geöffnet werden.

---

## 9. API

Basis `/api`, JSON, Fehler als `application/problem+json`. Ablehnungen, die für den Benutzer formuliert sind (z. B. «Die Gruppe ist ausgelost»), stehen im Feld `detail` und werden auch im Produktivbetrieb ausgeliefert; alle anderen Fehlertexte bleiben dort verborgen. Auszug:

| Methode | Pfad | Zweck | Zugriff |
|---|---|---|---|
| POST | `/auth/register` · `/auth/login` · `/auth/refresh` · `/auth/logout` | Authentifizierung | Gast / User |
| GET, PATCH | `/me` | eigenes Profil | User |
| PUT | `/me/password` | Passwort ändern | User |
| GET | `/groups` (`?scope=all`) | eigene Gruppen (alle: Admin) | User |
| POST | `/groups` | Gruppe erstellen | User |
| GET | `/groups/{id}` | Detail inkl. `drawable`, `isDrawed` | Teilnehmer |
| PATCH, DELETE | `/groups/{id}` | bearbeiten, löschen | Gruppenadmin |
| GET | `/groups/{id}/members` | Teilnehmerliste | Teilnehmer |
| POST | `/groups/{id}/members` | per Username einladen | Gruppenadmin |
| PATCH, DELETE | `/groups/{id}/members/{memberId}` | inaktiv/aktiv, entfernen | Gruppenadmin |
| DELETE | `/groups/{id}/members/me` | Gruppe verlassen | Teilnehmer |
| POST | `/groups/{id}/invite-link` | Share-Link neu generieren | Gruppenadmin |
| GET | `/invitations` | eigene offene Einladungen | User |
| POST | `/invitations/{memberId}/accept` · `/decline` | Einladung beantworten | Eingeladener |
| GET | `/invite-links/{token}` | Vorschau für das Overlay | User |
| POST | `/invite-links/{token}/accept` | beitreten | User |
| GET, POST | `/groups/{id}/wishes` | eigene Wünsche | Teilnehmer |
| PATCH, DELETE | `/groups/{id}/wishes/{wishId}` | eigenen Wunsch ändern | Besitzer |
| GET | `/groups/{id}/exclusions` | eigene Ausschlüsse, `N`, Restkontingent | Teilnehmer |
| PUT, DELETE | `/groups/{id}/exclusions/{memberId}` | Ausschluss setzen/entfernen | Teilnehmer |
| DELETE | `/groups/{id}/exclusions/notice` | Hinweis «Ausschlüsse wurden entfernt» quittieren | Teilnehmer |
| GET | `/groups/{id}/draw/status` | Checkliste, fehlende Wünsche pro Teilnehmer, ob es Ausschlüsse gibt | Gruppenadmin |
| POST, DELETE | `/groups/{id}/draw` | auslosen, Auslosung löschen | Gruppenadmin |
| GET | `/groups/{id}/draw/my-assignment` | eigener Wichtel mit Wünschen | Teilnehmer |
| GET | `/groups/{id}/draw/assignments/{memberId}` | Zuteilung eines Teilnehmers, einzeln abgerufen | **nur** Wichtler-Admin |
| GET | `/admin/users` | Benutzerliste, Suche | Wichtler-Admin |
| PATCH | `/admin/users/{id}` | Name, Username ändern | Wichtler-Admin |
| POST | `/admin/users/{id}/reset-password` | temporäres Passwort erzeugen (wird einmal zurückgegeben) | Wichtler-Admin |

«Gruppenadmin» schliesst den Wichtler-Admin überall ein.

---

## 10. Technische Architektur

### 10.1 Stack

| Schicht | Wahl |
|---|---|
| Backend | PHP 8.5, Symfony 8, Doctrine ORM, vollständig headless. Twig ist nur als Abhängigkeit der Swagger UI installiert, die App rendert keine eigenen Templates |
| API-Beschreibung | NelmioApiDocBundle: OpenAPI 3.1 aus PHP-Attributen und DTOs. Swagger UI unter `/api/doc`, Spezifikation unter `/api/doc.json`. Lokal offen, produktiv nur angemeldet mit `ROLE_ADMIN` (Regel in `access_control`, sonst `403`) |
| Auth | LexikJWTAuthenticationBundle + Refresh-Token-Bundle, Cookies |
| Frontend | React 19, TypeScript, Vite, React Router, TanStack Query |
| API-Client | orval generiert Typen und TanStack-Query-Hooks aus der OpenAPI-Spezifikation |
| UI | Tailwind CSS, Radix-Dialog für die Detail-Overlays, react-hook-form + zod |
| Datenbank | MariaDB 11.8 (lokal und produktiv identisch) |
| Lokal | ddev |
| Betrieb | Docker Compose hinter Nginx Proxy Manager |

Controller mit `#[MapRequestPayload]`-DTOs statt API Platform: Die App besteht fast nur aus Sonderregeln (Sichtbarkeit, Zustandssperren, Auslosung), die sich als explizite Controller und Voter klarer abbilden lassen.

### 10.2 Repository

```
symfony-wichtler/
├── backend/            Symfony-App (src, config, migrations, tests)
├── frontend/           React-App
│   ├── openapi.json    exportierte Spezifikation (versioniert)
│   ├── orval.config.ts
│   └── src/api/        generiert, nicht von Hand editieren
├── docker/             entrypoint.sh, Apache-Konfiguration
├── e2e/                Browser-Abläufe gegen eine laufende Instanz
├── .ddev/
├── Dockerfile
├── docker-compose.yml
├── docker-compose.stack.yml
├── .env.docker.example
├── README.md           Entwicklung und Tests
├── DOCKER.md           Betrieb
└── docs/KONZEPT.md
```

### 10.3 OpenAPI-Ablauf

1. Endpoint in Symfony ändern.
2. `ddev api-generate` exportiert die Spezifikation nach `frontend/openapi.json` und lässt orval Typen und Hooks erzeugen.
3. Der Test `OpenApiSpecTest` stellt sicher, dass `openapi.json` zum Code passt.

Die Spezifikation liegt im Repository, damit der Docker-Build des Frontends kein laufendes Backend braucht.

### 10.4 Lokale Entwicklung (ddev)

- `type: symfony`, `php_version: "8.5"`, `docroot: backend/public`, `composer_root: backend`, MariaDB 11.8, `nodejs_version: "24"`
- Vite-Dev-Server als `web_extra_daemons`, über `web_extra_exposed_ports` erreichbar, leitet `/api` an Symfony weiter. Frontend und API teilen sich so auch lokal einen Origin, CORS entfällt.
- Kein Mailpit nötig, es werden keine Mails versendet.

### 10.5 Docker-Betrieb

Aufbau nach dem Muster des `symfony-dart-helper` (`docker-compose.yml` bindet `docker-compose.stack.yml` mit `.env.docker` ein, Pflichtvariablen mit `${VAR:?}`, Migrations-Job, Entrypoint erzeugt JWT-Schlüssel, `no-new-privileges`), aber ohne Traefik und Websocket: Es gibt keine Skalierung, und den Proxy stellt der Nginx Proxy Manager.

| Service | Image | Aufgabe |
|---|---|---|
| `wichtler_app` | eigenes Image, `php:8.5-apache` | liefert das gebaute React-Frontend aus und bedient `/api` über Symfony |
| `wichtler_migrations` | dasselbe Image | einmaliger Job: Migrationen mit Wiederholung, danach Admin-Seed (`app:admin:seed`, idempotent) |
| `wichtler_db` | `mariadb:11.8` | Datenbank mit Healthcheck und Volume |

**Dockerfile** (mehrstufig): `composer:2` installiert Vendor ohne Dev → `node:24-alpine` führt orval und `vite build` aus → `php:8.5-apache` mit `intl`, `pdo_mysql`, `opcache`. Apache: `/api` an `index.php`, vorhandene Dateien direkt, alles andere an `index.html` (SPA-Routing, nötig für `/einladung/<token>`).

**Netzwerke**

```yaml
networks:
    internal:                 # eigenes Netz für die Kommunikation der Container
        name: wichtler-internal
        internal: true        # kein Weg nach aussen
    wichtler_app:             # Anbindung an den Nginx Proxy Manager
        external: true
        name: wichtler-app
```

| Service | `internal` | `wichtler_app` |
|---|---|---|
| `wichtler_app` | ✅ | ✅ |
| `wichtler_migrations` | ✅ | – |
| `wichtler_db` | ✅ | – |

- `wichtler-app` entspricht dem externen Netz `dart-app` des `symfony-dart-helper`: einmalig mit `docker network create wichtler-app` anlegen und den Nginx Proxy Manager daran anschliessen. Proxy Host → `wichtler_app:80`. TLS endet am Proxy Manager.
- Die Datenbank hängt nur am internen Netz und veröffentlicht keinen Port.
- `wichtler_app` veröffentlicht standardmässig keinen Host-Port. Für Tests ohne Proxy optional `127.0.0.1:${DOCKER_HTTP_PORT}:80`.
- `SYMFONY_TRUSTED_PROXIES` und Trusted Headers (`x-forwarded-for`, `x-forwarded-proto`, `x-forwarded-host`) sind gesetzt, damit Share-Links, sichere Cookies und Rate-Limiting hinter dem Proxy stimmen.

**Bedienung**

```bash
cp .env.docker.example .env.docker   # Secrets setzen
docker compose up -d --build         # starten
docker compose down                  # stoppen
```

`.env.docker` enthält u. a. `DOCKER_APP_SECRET`, DB-Zugangsdaten, `DOCKER_JWT_PASSPHRASE`, `DOCKER_DEFAULT_URI` (öffentliche URL für Share-Links), `DOCKER_ADMIN_USERNAME` und `DOCKER_ADMIN_PASSWORD` (für den Seed von User 1).

### 10.6 Sicherheit

- Autorisierung ausschliesslich serverseitig über Voter (`GROUP_VIEW`, `GROUP_MANAGE`, `DRAW_VIEW_ALL`, …)
- Argon2id, Rate-Limiting, Cookies `HttpOnly`/`Secure`/`SameSite=Strict`
- Share-Token mit 256 Bit Zufall, neu generierbar, nach der Auslosung wertlos
- Security-Header in der Apache-Konfiguration: eine Content-Security-Policy ohne Inline-Skripte für die App, `default-src 'none'` für API-Antworten, dazu `X-Frame-Options`, `nosniff`, `Referrer-Policy`, `Permissions-Policy` und HSTS für Anfragen über HTTPS
- Swagger UI wird aus der App selbst ausgeliefert, nicht von einem CDN
- Anfragen sind auf 1 MB begrenzt
- Wunsch-URLs nur `http(s)`, Links mit `rel="noopener noreferrer"`

### 10.7 Tests

- **Unit:** `DrawService` (nie Selbstziehung, Ausschlüsse eingehalten, statistischer Test auf Gleichverteilung bei kleinen `n`), Hopcroft-Karp, Ausschluss-Validierung und Bereinigung
- **Funktional (API):** Berechtigungsmatrix aus 5.5, insbesondere «Gruppenadmin erhält nie fremde Zuteilungen», Zustandssperren aus 5.2, die drei Share-Link-Szenarien
- **Frontend (Vitest):** Blur/Reveal, die Weiterleitungen des Einladungsablaufs, Fehleranzeige, Formatierung
- **Browser-Abläufe (`e2e/`):** alle Hauptabläufe in einem echten Chrome gegen den Docker-Stack, inklusive Prüfung auf Verstösse gegen die Content-Security-Policy

---

## 11. Umsetzungsetappen

1. **Grundgerüst:** Repository, ddev, Symfony-Skelett, Vite-App, OpenAPI/orval-Kette, Docker-Stack
2. **Accounts:** Registrierung, Login, Profil, Admin-Seed
3. **Gruppen:** CRUD, Übersicht, Lösch-Overlay
4. **Mitglieder:** Einladung per Username und Share-Link, entfernen, inaktiv
5. **Wünsche:** CRUD, Fortschritt für den Gruppenadmin
6. **Auslosung:** Ausschlüsse, Algorithmus, Start/Löschen, Blur/Reveal
7. **Wichtler-Admin:** alle Gruppen, alle Zuteilungen, Benutzerverwaltung
8. **Härtung:** Rate-Limiting, Security-Header, Testabdeckung, Betriebsdoku

---

## 12. Entscheide

Am 12.11.2026 mit Marco geklärt:

| # | Thema | Entscheid |
|---|---|---|
| 1 | Sichtbarkeit der Wünsche | nur die eigenen und, nach der Auslosung, die des eigenen Wichtels |
| 2 | Ausschlüsse | jeder Teilnehmer erfasst sie selbst und geheim |
| 3 | Inaktive Teilnehmer | bleiben ausgegraut sichtbar, werden nicht ausgelost |
| 4 | Wichtler-Admin löscht ausgeloste Gruppe | erst Auslosung löschen, dann die Gruppe |
| 5 | Zweierpaare in der Auslosung | erlaubt |
| 6 | Währung | pro Gruppe wählbar (`currency`, Default CHF) |
| 7 | Selbst austreten | vor der Auslosung erlaubt, ausser für den Gruppenadmin |
| 8 | Swagger produktiv | nur für den Wichtler-Admin |
