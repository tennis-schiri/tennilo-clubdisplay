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

## 0.3.2 – Kiosk / Admin-PIN
Externe Links aus der NTC-Buchungsansicht und den NTC-Spiel-/Terminlisten entfernt. eBuSy-Frame blockiert Popups und Navigation des übergeordneten Fensters per Sandbox. Interne Rückkehr bleibt möglich; kein automatischer Abbruch einer laufenden Buchung. Vollbild-Einstieg frei, Verlassen über die App ausschließlich mit serverseitiger PIN-Prüfung. Je Umgebung GitHub-Secret NTC_ADMIN_PIN bzw. TENNILO_ADMIN_PIN (6–20 Ziffern). Deploy erzeugt einen bcrypt-Hash; fehlendes Secret sperrt den App-Ausstieg. Fünf Fehlversuche pro 5 Minuten je Umgebung. Browser-/Windows-Tastenkürzel, Systemdialoge und erzwungenes Ende des Vollbilds sind nicht durch die Website kontrollierbar; für öffentlichen Betrieb Edge/Windows-Kioskmodus nötig. eBuSy-Sandbox-Kompatibilität und PIN-Buchung müssen am echten Gerät geprüft werden.

## 0.3.3 – NTC-Terminaladresse
Offizieller vom Betreiber angegebener Pfad /court-module/3076 mit ebusy-client-Parameter. Client-ID nur als NTC_EBUSY_CLIENT_ID Secret, serverseitig in terminal.php eingefügt; keine Ausgabe in Logs. Einmalige Admin-PIN-Freigabe am vorgesehenen NTC-Gerät setzt ein Secure/HttpOnly-Cookie (365 Tage, jeweils ein Terminal; erneute Freigabe ersetzt das vorige). Öffentlich zugängliche Displays ohne Freigabe erhalten keine Client-ID. Die ID bleibt für den autorisierten Browser technisch sichtbar. Einbettung des normalen Terminalpfads sowie PIN-Buchung und Restriktionen müssen vor Ort geprüft werden. Modul 3076 war bisher als Hallenmodul bekannt; Zuordnung zur gewünschten Freiplatzbuchung vom Betreiber prüfen. TENNILO-Demo unverändert simuliert.

## 0.3.4 – Freiplatzterminal
Korrigierter Betreiberpfad /lite-module/1731 mit ebusy-client-Parameter (Außenplätze). Bestehendes NTC_EBUSY_CLIENT_ID Secret und einmalige Admin-Freigabe unverändert; keine Weiterleitung aus dem eingebetteten Frame in einen neuen Tab. Funktion der Einbettung vor Ort weiterhin zu prüfen.

## 0.3.5 – Zwei Platzansichten
NTC: Außenplätze bleiben interaktives PIN-Buchungsterminal (1731). Halle (3076) nutzt versuchsweise den öffentlichen Embed-Pfad ohne Client-ID, getrennt von der geschützten Terminalfreigabe, mit deaktivierten Formularen, Popups, Top-Navigation und Touch-/Tastaturinteraktionen (inert, pointer-events, Overlay, credentialless sofern unterstützt). Nur Anzeige des eBuSy-Standardplans; die Ansichtsinteraktionen und Datumswahl sind bewusst gesperrt. Eigener Aktualisieren-Button, Hallenansicht kehrt nach Inaktivität zurück. Kein serverseitig bestätigter eBuSy-Read-only-Modus; browserseitige Anzeigesperre ist keine Zugriffskontrolle in eBuSy selbst. Echte Einbettung und Darstellung vor Ort prüfen. Nach API-Freischaltung bevorzugt native, datensparsame Hallenbelegung. TENNILO-Demo unverändert.

## 0.3.6 – Begegnungsansicht
Einheitliche, volle Breite nutzende Spielkarten: gut lesbares Datum und Uhrzeit oben, Liga und Heim/Auswärts rechts, Mannschaft und horizontale Begegnung darunter, Platzanlage und verfügbare Platznummern als gemeinsame Zusatzzeile. Nicht gelieferte Platznummern werden nicht als vermeintliche Zuordnung angezeigt. Seitengröße bei Desktop abhängig von Fensterhöhe (2/3/4), mobil 3. Kompakter Kopfbereich nur in der Spielansicht. NTC- und TENNILO-Farben bleiben erhalten.

## 0.3.7 – Kompakte Begegnungen
Datum/Uhrzeit, Mannschaft, Liga und Heim/Auswärts in einer gemeinsamen Kopfzeile; Paarung und vorhandene Platzinformationen direkt darunter. Kein künstlicher Abstand zwischen den Informationsgruppen. Reduzierte Kartenabstände, 3–6 Begegnungen je Seite abhängig von der Fensterhöhe (mobil 3). Namen bleiben nebeneinander, Umbruch innerhalb langer Namen möglich.

## 0.3.8 – Zweispaltige Begegnungsübersicht
Ab 1200 CSS-Pixeln zwei Spielkarten nebeneinander in chronologischer Reihenfolge, 4/6/8 Begegnungen pro Seite nach Fensterhöhe. Einzelne verbleibende Karte behält die halbe Breite. Datum/Mannschaft und Liga/Heimstatus bilden eine kompakte Kopfzeile, Begegnungen bleiben horizontal mit zentriertem Trenner/Ergebnis. Lange Mannschaftsnamen dürfen innerhalb ihrer Spalte umbrechen. Schmale Displays bleiben einspaltig. Keine Änderungen an Datenquellen oder Buchungen.

## 0.3.9 – CD-M04
Freigegebener Entwurf: jede Begegnung über die gesamte Breite, gelber Datumsblock beim NTC (TENNILO in Cyan), Mannschaft/Liga und horizontale Paarung mittig, Ergebnis in einer festen rechten Spalte. Platzanlage und Platznummern in der unteren Infozeile; bei Heimspielen ohne bestätigte Platznummern „noch offen“. Ergebnis ohne nuLiga-Matchpunkte als „– : –“. 3–5 Karten je Seite nach Fensterhöhe. Keine Änderung an der Datenquelle.

## 0.3.10 – CD-M04 Detailkorrektur
Beide Mannschaftsnamen direkt nebeneinander, linksbündig, ohne zentrierten Zwischenraum. Platznummern in einem kontrastreichen eigenen Feld rechts in der Infozeile; noch nicht zugeordnete Heimspielplätze als gelb/cyan umrandetes Feld. Datum, Ergebnisfeld und volle Kartenbreite bleiben unverändert.

## 0.3.11 – CD-M04c
Kompakte, vollbreite Spielkarte mit ausgeschriebenem Wochentag, gelbem Datumsblock und dunklerem gelben Platzstreifen (NTC; TENNILO cyan). Jede bestätigte Platznummer wird als eigenes Tennisplatzsymbol angezeigt. Numerische Bereiche werden einzeln aufgelöst; keine geratenen Platznummern. Ohne bestätigte Platzzuordnung steht bei Heimspielen ein Strich. Paarung bleibt linksbündig, Ergebnis rechts. Bis zu sechs Spiele pro Seite abhängig von der Fensterhöhe. Noch keine eBuSy-API-Zuordnung verfügbar.

## 0.4.0 – Wetter
Fest eingestellter Demo-/Pilotstandort Neuenhagen bei Berlin (52.5333, 13.6833; Ortsmitte, nicht exakte Anlage). DWD Open Data über die öffentliche Bright Sky API, kostenfrei ohne Schlüssel nach Anbieterbedingungen, Quellenangabe sichtbar. Servercache 10 min, bei Fehler maximal 3h alte Wetterdaten mit Hinweis; aktuelle Stationswerte oder klar gekennzeichneter Modellwert. Sonnenauf-/untergang astronomisch mit PHP. 12h Stunden- und 7-Tage-Prognose, Tageswerte und maximale stündliche Regenwahrscheinlichkeit. Regionaler DWD-WMS-Radarversuch mit 5-min-Cache, Zeitstempel, Ortsmarkierung und Fehlerzustand; derzeit statisches Bild ohne Grundkarte und Animation. API-/WMS-Live-Verfügbarkeit auf Hosting muss geprüft werden. Wetter im Kopf, Menü und Infomodus; keine Buchungsdaten berührt.

## 0.4.1 – Radar mit Grundkarte
Amtliche basemap.de Web Raster (BKG) als eigene WMS-Ebene, DWD RV-Radar darüber. Identische EPSG:4326-Box und PNG-Abmessungen, sichtbare Namensnennung und Standortmarkierung. Getrennte Servercaches (Karte 24h, Radar 5 min); expliziter Fehlerstatus statt grauer Fläche. Vollständig transparente Radarbilder werden als ohne sichtbare Echos gekennzeichnet, nicht als garantierte Regenfreiheit. Keine externen Navigationslinks im NTC-Kiosk; Quellen-URLs dort als Text. Live-Erreichbarkeit beider WMS-Dienste und Kartenbild noch zu prüfen.

## 0.4.2 – Ansichten ohne vertikales Scrollen
Mannschaften und Wetter belegen die verfügbare Viewporthöhe mit sichtbarer Kopfzeile und Navigation. Begegnungsseiten werden anhand der tatsächlich gemessenen Kartenhöhen gebildet, nicht anhand einer festen Anzahl; CD-M04c bleibt unverändert. Wetter mit kompakter Gesamtübersicht und bedarfsabhängigen Reitern für aktuell, Stunden, Tage und Radar. Radar bleibt quadratisch und passt in die Resthöhe. Sehr kleine Fenster oder außergewöhnlich lange Inhalte werden als letzte Rückfallebene proportional verkleinert. Neuberechnung bei Fenstergröße, Vollbild und Datenaktualisierung. Browserprüfung am tatsächlichen Touchscreen noch erforderlich.

## 0.4.3 – Kopfzeile
Erweiterter antippbarer Wetterblock mit Temperatur, Wetterzustand, Tagesminimum/-maximum, Wind, Niederschlagswahrscheinlichkeit der nächsten verfügbaren Stunde sowie eigenen SVG-Sonnenauf-/untergangssymbolen. Uhrzeit/Datum als eigener Block ganz rechts nach den Sprach-/Vollbild-Buttons. Auf schmalen Displays werden zunächst Zusatzwerte ausgeblendet; Sonnenzeiten bleiben sichtbar. Keine Änderung an Wetterquellen oder Spielkarten.

## 0.4.4 – Kopfzeilenanordnung
Logo/Vereinsname, umrandeter Datums-/Uhrzeitblock, Wetterblock, Sprach- und Vollbildsteuerung. Datum/Uhrzeit direkt links neben Wetter; beide Informationsblöcke gleich hoch. Wetterinhalte unverändert.

## 0.4.5 – Direkte Außenplatzbuchung
Zusätzliche ClubDisplay-Terminalfreigabe entfernt. Bei gesetztem NTC_EBUSY_CLIENT_ID wird das eBuSy-Freiplatzterminal 1731 direkt eingebettet, die Mitgliederanmeldung übernimmt eBuSy. Admin-PIN nur zum Verlassen des Vollbildmodus. Keine Client-ID im Git-Quellcode; bei öffentlich erreichbarer NTC-Seite ist die Terminaladresse jedoch für jeden Besucher technisch abrufbar. Vor regulärem Einsatz eBuSy-seitige Beschränkung der Terminal-ID auf den Vereinsstandort bzw. Schutz des NTC-Displays auf Netzwerkebene prüfen. Hallenansicht unverändert.

## 0.4.6 – Einheitlicher Kopf und Platzansicht
Flache Kopfzeile der Wetteransicht auf allen Reitern; Logo, Datum/Uhrzeit und Wetter unverändert angeordnet. Plätze belegen exakt den verbleibenden Flex-Bereich zwischen Kopf und Navigation, ohne bisherige feste Mindesthöhe des iframes. Kompakte Umschaltung und Hallen-Statuszeile. Fenstergrößenänderungen passen den iframe per CSS an, ohne laufende Buchungen neu zu laden. Kein vertikales Scrollen der ClubDisplay-Hülle; Scrollverhalten innerhalb der fremden eBuSy-Seite ist durch deren eigenes Layout bestimmt. Buchungsfunktion und Hallen-Anzeigesperre unverändert.

## 0.4.7 – Terminkarten
Termine im CD-M04c-Stil mit identischem Datumsblock (ausgeschriebener Wochentag, Datum, Beginn bzw. ganztägig), linksbündigem Veranstaltungstitel und vorhandener Zeitspanne/Ort; mehrtägige Termine mit zusätzlichem Datumsbereich. Keine leeren Platz-/Ergebnisspalten. Wie Mannschaftsspiele dynamisch nach gemessener Kartenhöhe paginiert, feste Kopfzeile und Navigation. Kalenderdaten und externe Linkregeln unverändert.

## 0.4.8 – Einheitlicher Seitenrahmen
Ein einziger viewportgebundener CSS-Grid-Rahmen für sämtliche Ansichten einschließlich Start, Vereinsinfos, Sponsoren und Infomodus. Feste Kopf-, Status-, Navigations- und Fußzeilenabmessungen pro Bildschirmgröße; ausschließlich der mittlere Bereich variiert inhaltlich. Gleich breite Navigationsbuttons mit konstanter Schriftstärke/Höhe verhindern Umbrüche beim Reiterwechsel. Alle nicht eingebetteten Inhalte nutzen vorhandene Höhenanpassung, Mannschaften und Termine weiterhin dynamische Seiteneinteilung. eBuSy-Frames behalten native Größe und werden bei Größenänderungen nicht neu geladen; internes eBuSy-Scrolling bleibt außerhalb der Kontrolle. Visuelle Kontrolle am Zielgerät ausstehend.
