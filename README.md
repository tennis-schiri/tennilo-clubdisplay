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
