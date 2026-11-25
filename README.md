# TENNILO ClubDisplay

Copyright © 2026 TENNILO. Alle Rechte vorbehalten, soweit nicht anders angegeben.
Öffentlich einsehbarer Quellcode; keine Open-Source-Lizenz oder Nutzungsfreigabe.

## Version 0.1.0 – interaktiver Prototyp
- TENNILO: clubdisplay.tennilo.de (Entwicklung)
- NTC: clubdisplay.ntc93.de (Pilot)
- Browser im Vollbild, Touch-Navigation, automatisch wechselnder Informationsmodus.
- Beispielinhalte und simulierte Platzbuchung ohne Speicherung oder externe Anbindung.
- Keine echten Mitgliederdaten, Zugangsdaten oder Vereinsbuchungen im Repository.
- Admin-Backend, Schnittstellen und produktive Buchung folgen nach Abstimmung.

## Veröffentlichung
Automatischer Upload auf beide Umgebungen bei Änderungen an public/ oder dem Deployment-Skript auf main; Änderungen am jeweiligen Workflow lösen dessen Upload aus. Manueller Start über Actions weiterhin möglich (nur main).
FTP-Zugang muss auf die jeweilige Subdomain begrenzt sein. HTTPS-Prüfung des
Zielverzeichnisses über eine harmlose Prüfdatei vor Übertragung der Anwendung.
Die beiden Umgebungen werden unabhängig voneinander veröffentlicht.
Die Admin-Secrets werden erst mit einem serverseitigen Backend verwendet.

## 0.2.0 – nuLiga
Öffentliche NTC-Vereinsbegegnungen (Vereins-ID 36381) serverseitig, ohne Anmeldung. Nur Mannschaften, Liga, Spielzeit, Heim/Auswärts und Matchpunkte. 30 Minuten Cache, 5 Minuten Wiederholsperre nach Fehlern, veraltete Daten maximal sieben Tage und klar markiert. Keine Speicherung personenbezogener Kontaktdaten. Beide Hosts zeigen in dieser Pilotphase dieselbe Vereinsquelle. Keine Buchungen oder Schreibzugriffe bei nuLiga. Die öffentliche HTML-Quelle ist keine garantierte API; bei Strukturänderungen ist eine Anpassung nötig. PHP cURL und DOM erforderlich.

## 0.2.3 – NTC branding
Auf clubdisplay.ntc93.de: unverändertes rundes NTC-Vereinslogo aus dem Vereinsbestand (Drive: NTC93-Rundes-Logo.png), Vereinsfarben Petrol/Gelb/Weiß, eigenes Favicon und Titel. Die TENNILO-Entwicklungsdemo bleibt im bisherigen Design. Gemeinsamer Anwendungscode, hostabhängiges Theme.

## 0.3.0 – Vereinskalender
Öffentliche NTC-Veranstaltungen, REST-Abruf (180 Tage) mit iCalendar-Monatsexport als Fallback (3 Monate). Anzeige von Titel, Datum, Zeit und Veranstaltungsort; keine personenbezogenen Zusatzdaten. 30 Minuten Cache, 5 Minuten Wiederholsperre, Ausfallkennzeichnung. iCalendar-Serienregeln werden nicht expandiert. Die tatsächliche Verfügbarkeit der beiden Abrufwege muss serverseitig geprüft werden. eBuSy bleibt bis zur API-Freischaltung offen.


## Pflichtfunktion für NTC-Pilot: Außenplatzbuchung am Touchscreen
- Vor Ort echte Buchung im bestehenden eBuSy-Freiplatzmodul (1731), keine separate ClubDisplay-Anmeldung und kein Benutzername/Passwort. Persönliche eBuSy-PIN als einzige geheime Eingabe; Namens-/Mitspielerauswahl entsprechend eBuSy-Konfiguration.
- Platz und Zeit auswählen, ggf. Einzel/Doppel und Mitspieler, PIN im offiziellen Buchungsdialog eingeben, verbindliche Bestätigung von eBuSy abwarten. Danach Rückkehr zum Display; keine persönliche Sitzung am öffentlichen Gerät bestehen lassen.
- PINs nie über ClubDisplay sammeln, auslesen, speichern oder protokollieren. Keine Mitgliederliste oder personenbezogene Buchungsdaten auf die öffentliche Demo übertragen.
- eBuSy bleibt führendes Buchungssystem; bestehende Regeln, Sperren, Buchungsdauer und Spielberechtigung bleiben verbindlich. Keine lokalen Ersatzbuchungen und kein Umgehen von Terminalbeschränkungen.
- Bevorzugt offiziell unterstützter Terminal-/Embed-Modus; konkrete NTC-Terminal-URL, Einbettbarkeit, Einschränkungen und sichere Rückkehr/Inaktivitätsverhalten mit eBuSy verifizieren. Nur bei nachgewiesener Unterstützung für echte Buchungen aktivieren. Die bereitgestellte OpenAPI 1.1.2 dokumentiert für das Freiplatzmodul nur lesende Platz-/Reservierungsendpunkte, keine Erstellung einer Platzreservierung.
- Akzeptanztest vor Pilotstart: echte Buchung auf dem Vereinsgerät mit berechtigtem Testmitglied, Ablehnung ungültiger PIN und belegter Plätze, keine zurückbleibenden personenbezogenen Daten, Abbruch/Rückkehr ohne Buchung; Demo bleibt Simulation.

## 0.3.1 – Spielanzeige und eBuSy-Einbettung
Datum/Uhrzeit groß, Mannschaften horizontal in Heim-/Gast-Reihenfolge, Ergebnis mittig. Auf NTC-Host wird der eBuSy-Freiplatzplan versuchsweise über /embed/lite-module/1731 eingebettet, mit Link zur Originalseite. TENNILO bleibt Buchungssimulation. Keine PIN-Eingabe durch ClubDisplay. Während der Buchungsansicht ist die automatische Inaktivitätsrückkehr deaktiviert, da Interaktionen im fremden Frame nicht beobachtet werden können; explizite Rückkehr über Schaltfläche. Sprache während Buchung gesperrt, damit der Dialog nicht versehentlich neu lädt. NTC-Embed-Freigabe, PIN-Buchung, Cookies, Sitzungsende und Kiosk-Verhalten müssen real getestet werden, bevor öffentlicher Terminalbetrieb freigegeben wird.
