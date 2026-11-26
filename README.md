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

## 0.4.9 – Terminkarten: Zeitraum und Ort
Zeitspanne bzw. Enddatum und Veranstaltungsort als eigene, leicht dunklere Felder am rechten Kartenrand. Titel bleibt links neben dem gelben Datumsblock; bei mehrtägigen Terminen bleibt der Datumsbereich zusätzlich unter dem Titel sichtbar. Mobile Ansicht stapelt die beiden Metafelder platzsparend. Keine Änderung am festen Seitenrahmen.

## 0.4.10 – Startmenü und Fokusrahmen
Fokus- und Hover-Umrandungen liegen innerhalb der Buttons, damit sie an keinem Rand des festen Inhaltsbereichs abgeschnitten werden. Aktive gelbe/cyanfarbene Buttons erhalten kontrastierende dunkle Fokusumrandungen. Startmenü mit fünf größeren Touch-Karten im 3+2-Raster, kurzen zweisprachigen Funktionsbeschreibungen und Pfeil; mobile Ansicht zweispaltig. Unveränderter fester Seitenrahmen, keine Änderung an Datenquellen oder Buchungen.

## 0.4.11 – NTC-Vereinsmeldungen
Auf dem NTC-Display werden bis zu drei neueste öffentliche WordPress-Beiträge von ntc93.com mit Datum, Titel und kurzem Originalauszug (max. 240 Zeichen) angezeigt; REST-API primär, RSS als Alternative. Servercache 15 Minuten, bei Ausfall bis 7 Tage alter Stand klar gekennzeichnet. Keine API-Schlüssel, keine externen Links im Kiosk, Originaltexte bleiben deutsch. TENNILO-Demo behält Beispielmeldungen. Parser-Tests und Live-Endpunktprüfung im Deployment; tatsächliche Erreichbarkeit der News-Feeds bis zur Ausführung nicht bestätigt.

## 0.4.12 – Interne News-Leseansicht
News-Karten mit größerem Veröffentlichungsdatum und nicht verlinktem Quellenhinweis ntc93.com. Erst beim Antippen wird der vollständige WordPress-Artikel in einer internen Ansicht geöffnet; die drei neuesten Beiträge werden im Servercache gemeinsam abgerufen. Sichere Neu-Erzeugung von Artikel-HTML anhand erlaubter Text-/Bild-Tags; Links werden zu normalem Text, aktive Inhalte und Fremd-Embeds entfernt. Bilder aus dem offiziellen NTC-Upload-Verzeichnis, inklusive Beitragsbild, sofern die API es liefert. RSS-Volltext als Rückfall, andernfalls Hinweis auf unvollständigen Artikel. Leseansicht mit Zurück-Taste und internem Scrollbereich samt Pfeiltasten, feste ClubDisplay-Navigation bleibt sichtbar. Keine externe Seitennavigation. Berechtigung zur Weiterverwendung von Beiträgen/Bildern ist durch den Vereinsbetreiber sicherzustellen. Live-Bildprüfung ausstehend.

## 0.4.13 – NTC-Sponsoren
Alle 15 Bilder aus dem eindeutig ermittelten Sponsorenbereich der NTC-Startseite (Prüflauf 45626245298 vom 25.11.2026) in Originalreihenfolge als fester 5×3-Logoraster im Infomodus, auf kleinen Displays 3×5. Bildquellen sind die offiziellen NTC-Upload-URLs, ohne klickbare Links; Finwire erhält wegen der hellen Logobestandteile einen dunklen Hintergrund. Sponsorenauswahl als feste Momentaufnahme im JavaScript gespeichert. Kein automatischer täglicher Abgleich; bei Bedarf Prüflauf check-sponsors.yml erneut manuell starten und Liste aktualisieren. Bilder direkt vom NTC-Webserver geladen, nicht lokal dupliziert. TENNILO-Demo unverändert. Optische Prüfung am Zielgerät ausstehend.

## 0.4.14 – Infomodus-Steuerung
Dezente Vor-/Zurück- und Pause/Fortsetzen-Tasten neben der Fortschrittsleiste. Die Pause hält den Fortschritt und die automatische Rotation an, manuelles Blättern bleibt möglich und startet die jeweilige Seite bei null; beim Fortsetzen läuft der verbleibende Zeitraum weiter. Seitenzahl, zweisprachige zugängliche Beschriftungen und zyklischer Wechsel. Nur im Infomodus sichtbare Buttons, in allen Ansichten gleich hohe Steuerzeile, damit Navigation und Inhaltsrahmen nicht springen. Beim erneuten Wechsel in den Infomodus beginnt die automatische Rotation wie zuvor auf der ersten Seite.

## 0.4.15 – Zeitbegrenzte Infomodus-Pause
25 Sekunden ohne Eingabe beenden die Pause automatisch; kleiner Sekunden-Countdown neben der Steuerung, nur während der Pause sichtbar. Tastendruck, Klick, Berührung und Scrollen setzen die Frist zurück; reine Mausbewegung nicht. Manuelles Fortsetzen jederzeit möglich. Nach Ablauf läuft die verbleibende Anzeigezeit der aktuellen Infoseite weiter. Fester Seitenrahmen unverändert.

## 0.4.16 – Einheitliche Sponsorenhintergründe
Alle Logokarten einschließlich Finwire auf reinweißem Hintergrund. Keine dunkle Sonderkarte mehr; bereits in den Originalbildern vorhandene Hintergrundfarben (z. B. Allianz) bleiben unverändert. Logos werden weder eingefärbt noch verzerrt. Raster und Reihenfolge bleiben erhalten. Bei transparenten weißen Logodetails kann eine geeignete offizielle Logoalternative nötig sein; Sichtprüfung ausstehend.

## 0.4.17 – Allianz-Logokarte
Nur Allianz erhält einen zur Originalgrafik passenden hellgrauen Hintergrund. Beim NTC-Deployment wird der dominante helle neutrale Randfarbton der offiziellen JPEG-Datei ausgelesen und als CSS-Farbwert gesetzt; bei Abruf-/Analysefehler greift ein hellgrauer Ersatzwert (#ededed). Andere Sponsorenfelder bleiben reinweiß. Layout und Bildproportionen unverändert.

## 0.4.18 – Sichtbarer Infomodus-Countdown
Numerische Anzeige durchgehend sichtbar, normal „Weiter in: 12 s“, pausiert gelb/cyan hervorgehoben „Pause: 25 s“. Bei Eingabe während der Pause erneute 25 Sekunden; automatische Rückkehr zur verbleibenden Seitenzeit. Automatischer Test der tatsächlichen JS-Funktionen mit kontrollierter Uhr (Sichtbarkeit, Countdown, Zurücksetzen, Ablauf, manueller Seitenwechsel) läuft vor jedem Deployment. Grund für die bisherige fehlende sichtbare Anzeige am Zielgerät nicht unabhängig reproduziert.

## 0.4.19 – Platzbelegung im Infomodus
NTC-Startfolie mit Außenplätzen 1–7 und Hallenplätzen 1–2, aktueller Berliner Kalenderstunde plus den zwei folgenden Stunden. Bei Stundenwechsel automatische Aktualisierung der Spalten. Belegung bis zur freigegebenen eBuSy-Anbindung ausschließlich unbekannt (27 Striche, ausdrücklicher Datenhinweis), keine simulierte Verfügbarkeit. Vorbereitetes UI-Format: {status:'ok',fetchedAt:Unixsekunden,slots:[{area:'outdoor'|'indoor',court:Nummer,start:ISO-UTC,end:ISO-UTC,status:'free'|'occupied'|'partial'|'blocked'}]}; fehlende oder ältere als drei Minuten Daten werden nicht als frei angezeigt. Echte Zuordnung von Platz-IDs und Reservierungen sowie Abruf/Cache erst nach API-Freigabe. Keine personenbezogenen Daten. Buchungsplan-Schaltfläche bleibt. Automatisierte Stunden-/Status-/DST-Tests im Deployment; visuelle Prüfung am Zielgerät ausstehend.

## 0.4.20 – Grafische Platzbelegung
Statt Tabellen neun gestaltete Court-Karten mit abstrahierten Tennisplatzlinien und zentralen Platznummern. Außenplätze sandfarben, Hallenplätze blau (rein illustrative Oberflächenfarben, keine Aussage über tatsächlichen Belag). Sieben Außenplätze im 4+3-Raster, zwei Hallenplätze rechts; pro Platz drei Zeitfelder mit Statusfarbe, Symbol und Text. Aktuelle Stunde gelb hervorgehoben. Ohne eBuSy-Daten alle Zeitfelder neutral und ausdrücklich unbekannt. Fester Seitenrahmen und übrige Funktionen unverändert. Mobile Ansicht kompakt umgebrochen. Visuelle Prüfung noch ausstehend.

## 0.4.21 – Wer spielt wo?
Namenorientierte Belegungsansicht: neun flache Court-Zeilen mit kleinen Platzgrafiken und drei breiten Buchungsfeldern, Außen/Halle getrennt; Belegungsname statt Statussymbol im Mittelpunkt. Die künftige normalisierte Slot-Struktur kann `bookings:[{displayLabel,start,end}]` mit freigegebenen Spielerpaaren, Mannschaften, Trainingsgruppen oder Sperrtexten liefern. Mehrere Teilbuchungen mit Zeitangaben darstellbar. Keine Namen aus fehlenden/veralteten Daten, keine erfundenen Angaben. Personenbezogene Namen erst nach freigegebener eBuSy-Anbindung und geeigneter Zugriffsbeschränkung des NTC-Displays; öffentlich erreichbare Demo enthält keine personenbezogenen Belegungsdaten. Visualisierung und echte Datenanbindung noch zu prüfen.

## 0.4.22 – Hallenplatzgrafiken
Hallenplätze ebenfalls sandfarben wie die Außenplätze; Gruppenüberschriften bleiben getrennt.

## 0.4.23 – Zeitgesteuerte Begrüßung der Heimmannschaften
Für jedes NTC-Heimspiel ohne Ergebnis wird eine eigene Begrüßungsfolie in die Infomodus-Rotation aufgenommen, exakt ab geplantem Beginn minus 30 Minuten bis (exklusiv) plus 30 Minuten. Parallelspiele erhalten eigene Folien; Auswärtsspiele, abgesagte (sofern im Feed markiert) und abgeschlossene Begegnungen werden ausgeschlossen. Bei Ablauf verschwindet die Folie auch während einer Pause. Begegnung, Spielklasse, Uhrzeit und bestätigte Platznummern werden groß dargestellt. Der bestehende nuLiga-Feed enthält derzeit keine Platzzuordnung und kein separates Absagefeld; bis zur eBuSy-Integration wird die fehlende Zuordnung ausdrücklich angezeigt. Die Rotationsreihenfolge wird dynamisch angepasst, ohne andere Folien unnötig neu zu starten. Automatische Tests für Zeitgrenzen, Mehrfachspiele, fehlende Plätze und Ablauf. Optische Kontrolle ausstehend.

## 0.4.24 – Funktionsgleichheit TENNILO-Demo
Dieselben vier Ansichten in NTC und TENNILO: 9 Plätze mit drei Stunden, zeitgesteuerte Mannschaftsbegrüßungen, vollständige anklickbare News mit Illustrationen und Sponsorenraster. TENNILO nutzt klar markierte fiktive Inhalte und eigene Markenfarben. Demo-Heimspiel beginnt auf der nächsten Viertelstunde nach Laden der Seite; nach ±30-Minuten-Fenster verschwindet Folie, Neuladen erzeugt neues Beispiel. Mannschaftsliste passend zum Demo-Spiel. Wetter und öffentlicher NTC-Veranstaltungskalender wie bisher. NTC-Datenquellen und eBuSy-Anbindung unverändert. Visuelle Kontrolle ausstehend.

## 0.4.25 – Due Fratelli im NTC-Infomodus
Eigene Restaurantfolie nach Vereinsnews und vor Sponsoren, ausschließlich auf NTC-Domain. Helles eigenständiges Gastronomiedesign mit Original-Logo, Gastraum-, Pizza- und Pastafotos von der offiziellen Restaurantwebsite (Assetpfade im Repository Due-Fratelli-Neuenhagen/website verifiziert). Reguläre Öffnungszeiten, Telefonnummer, Webadresse als Text ohne externe Navigation; zeitlich begrenzte Sonderhinweise gemäß Website am 25.11.2026. Keine automatische Aktualisierung der Gastronomiedaten; Anpassung bei Bedarf. Deutsch/Englisch; unveränderter Seitenrahmen. TENNILO erhält keine Due-Fratelli-Werbung. Bildabruf und optische Live-Prüfung noch ausstehend.

## 0.4.26 – Due Fratelli: reduzierte Infotafel
Keine großformatigen Fotos oder Werbeslogans. Original-Logo und Telefonnummer prominent im Kopf; drei klar getrennte Bereiche für Wochenaktionen (Dienstag Pizza 9,90 €, Mittwoch Pasta 9,90 €, Auswahl im Restaurant erfragen), reguläre Öffnungszeiten und Sonderöffnungszeiten einschließlich 06.12. privater Veranstaltung. Angaben anhand der offiziellen Website-Quelldateien business.ts, weeklyOffer.ts und specialHours.ts verifiziert. Feiertagsblock nach 01.01.2027 automatisch ausgeblendet, Hinweis zum 06.12. ab Folgetag ausgeblendet. Statische Wochenaktion und Öffnungszeiten müssen bei Änderungen im Restaurant abgeglichen werden. Visuelle Live-Kontrolle steht aus.

## 0.4.27 – Due Fratelli im originalen Homepage-Stil
Drei ruhige Informationsspalten beibehalten. Originale Dolce-Vita-Farben aus der Website: Weinrot/Gold für Wochenaktionen, Creme für reguläre Zeiten, Tannengrün/Gold für den Feiertagsbereich; Georgia-Überschriften, Arial-Informationstexte, weiche Rundungen. Italienische Wochentagslabels und automatische HEUTE-Markierung wie auf der Homepage. Original-Logo jetzt von der in astro.config.mjs ausgewiesenen offiziellen Subdomain neu.due-fratelli-neuenhagen.de. Keine zusätzlichen Fotos; keine Änderung der übrigen ClubDisplay-Ansichten. Live-Sichtprüfung ausstehend.

## 0.4.28 – Due Fratelli: Reservierung und Webadresse
Webadresse deutlich größer in der Fußzeile der Restaurantfolie. Reservierungshinweis vor Ort, telefonisch oder online im bestehenden Kontaktfeld, zweisprachig. Webadresse bleibt für den Infomodus reiner Anzeigetext, ohne externe Navigation. Sonst unverändertes Layout. Visuelle Live-Prüfung ausstehend.

## 0.4.29 – Originale Pizza- und Pastafotos
Die beiden Wochenaktionskarten erhalten die Originalfotos aus WeeklyOffer.astro der Due-Fratelli-Homepage (gericht-pizza.jpg, gericht-pasta.jpg). Kompakte Bildspalte neben Tag, Gericht und Preis; heutige Aktion weiterhin hervorgehoben. Keine weiteren großen Fotos, übriges Layout unverändert. Live-Bildabruf und optische Prüfung stehen aus.

## 0.4.30 – Mehr Raum für Sonderöffnungszeiten
Rechte Weihnachtsspalte leicht verbreitert. Datumsangaben und Zeiten nebeneinander statt untereinander; 06.12. als separate, goldgerahmte Hinweisfläche mit hervorgehobener Reservierungszeit. Mobile Ansicht behält kompakte Umbrüche. Originalfarben, Fotos, Aktionen und Reservierungsinformationen bleiben erhalten. Screenshot des Nutzers zeigte v0.4.28; Änderung basiert auf aktuellem v0.4.29-Code. Optische Prüfung des neuen Live-Standes ausstehend.

## 0.4.31 – Restaurantfolie: Webadresse im Kopf
Homepage-Adresse zwischen Logo und Reservierungsfeld; auf schmalen Bildschirmen direkt darunter. Fußzeile mit Trattoria-am-NTC-Hinweis vollständig entfernt, sodass die drei Informationsspalten mehr Höhe erhalten. Übriges Layout unverändert. Visuelle Live-Prüfung ausstehend.

## 0.4.32 – TENNILO-Entwicklerhinweis im Footer
Auf beiden Domains dauerhaft Design & Entwicklung, Original-TENNILO-Wortmarke (lokale Kopie aus tennilo-website/public/assets/tennilo-logo.png, einfarbig weiß per CSS für dunklen Footer) und tennilo.de als Text ohne externe Navigation. Deutsch/Englisch. Bestehender Footer bleibt 30 px bzw. 24 px hoch; Release-Nummer und Quellenhinweis bleiben erhalten. Logo wird beim FTP-Upload mit verifiziert. Visuelle Kontrolle ausstehend.

## 0.4.33 – Restaurantfolie: intrinsische Höhenanpassung
Die bisherige feste Boxhöhe verdeckte überlaufende Karten. Jetzt wird die natürliche Höhe vor dem Strecken gemessen. Kompakte/tight-Layouts bei knapper Fläche; proportionale Skalierung nur falls nötig. Keine ausgeblendeten Inhalte; Bildladevorgänge lösen erneute Messung aus. Fester Header/Footer unverändert. Firefox-Geometrieprüfung separat.

## 0.4.34 – Produktseite aus der TENNILO-Demo
Im Kopfbereich von clubdisplay.tennilo.de führt ein dauerhaft sichtbarer Link zur ClubDisplay-Produktseite (DE/EN passend zur Sprache, neuer Tab). Auf dem NTC-Terminal bleibt der Link verborgen; Buchung und Kiosk-Funktionen unverändert.

## 0.5.0 – Administrationsbackend (erste Ausbaustufe, noch nicht live bestätigt)
Separates Admin-Portal https://clubdisplay.tennilo.de/admin/ für den Betreiber. SQLite-Vereinsprofile, Lizenztyp, Module, Sprache, Akzentfarbe und Folienwechsel; NTC und Demo als Konfigurationsentwürfe. Keine Verbindung zu bestehenden Displays, keine Buchungen, kein Vereinslogin. Ausschließlich manuelles Admin-Deployment über https://github.com/tennis-schiri/tennilo-clubdisplay/actions/workflows/deploy-admin.yml. Secrets TENNILO_ADMIN_PASSWORD (neues starkes Passwort, 16–72 Bytes, nicht der Kiosk-PIN) und TENNILO_ADMIN_STORAGE_DIR (existierendes beschreibbares, nicht öffentlich erreichbares privates Verzeichnis außerhalb sämtlicher Webroots, PHP PDO SQLite erforderlich) zusätzlich zu vorhandenen TENNILO_FTP-Secrets setzen. Speicherpfad und Backup beim Hoster prüfen. Kein automatischer Upload der Admin-Dateien auf das NTC-Terminal. Browser-Login, Speichern, Logout, Sperrung und Dauerhaftigkeit nach Upload testen. Logo-Uploads, Medien, Datenquellen und Live-Konfigurationsanbindung folgen separat.

## 0.5.1 – TENNILO → Verein → Bildschirm
Zentrale Kundenverwaltung und separate, durch TENNILO freischaltbare Vereinszugänge. Ein Verein kann mehrere Bildschirmprofile mit Betriebsart, Sprache, Modulen, Wechselzeit und Aktivstatus verwalten. Vereinszugänge sehen ausschließlich den eigenen Verein und können weder Lizenzen noch Zugangsdaten anderer Vereine ändern. Profile sind weiterhin Entwürfe; Geräteanbindung und Vereinsinhalte folgen. Vorhandenes TENNILO_ADMIN_PASSWORD wird weiterverwendet; kein neues Passwort-Secret. Privater SQLite-Speicher und manuelle Abnahme erforderlich.

## 0.5.2 – Gemeinsame Vereinsinhalte
Vereinslogo (PNG/JPEG/WebP, max. 2 MB, serverseitig als PNG neu kodiert; PHP GD erforderlich), Akzentfarbe und Begrüßungstext sowie redaktionelle Einträge für Nachrichten, Termine, Sponsoren und Gastronomie. Einträge mit Titel, Text, optionalem Zeitraum und HTTPS-Link, aktiv/inaktiv, bearbeitbar und löschbar. Logos liegen privat in SQLite und werden nur nach Login und Vereinsrechteprüfung angezeigt. Vereinsinhalte sind noch nicht an produktive Displays angebunden. Sponsorenbilder, strukturierte Gastronomiezeiten und externe Datenquellen folgen.

## 0.5.3 – Speicherpfad für den bestehenden ALL-INKL-FTP-Zugang
Ersetzt die Speicherpfad-Anleitung aus 0.5.0: Das Admin-Backend ermittelt den Pfad relativ zur Installation (clubdisplay-admin/ neben admin/). TENNILO_ADMIN_STORAGE_DIR wird nicht mehr gelesen. TENNILO_ADMIN_PASSWORD bleibt unverändert. Der manuelle Upload setzt zuerst Apache Require all denied in clubdisplay-admin/.htaccess und prüft per HTTPS eine harmlose Testdatei, den Ordner sowie die vorgesehenen SQLite-Dateien und die Freigabedatei. Erst nach HTTP 403 für sämtliche Prüfpfade wird das Backend freigeschaltet. Die Anwendung prüft den erwarteten Pfad, die Schutzdatei und die zum Deployment gehörende Freigabe vor Datenbankzugriff. Die Datenbank wird bei späteren Uploads nicht überschrieben. Der HTTP-Test bestätigt den geprüften Host zum Uploadzeitpunkt; bei Hosting- oder Alias-Änderungen erneut prüfen. PHP PDO SQLite, Schreibrechte und PHP GD für Logos bleiben erforderlich. Keine Änderung an NTC oder öffentlicher Demo; Live-Abnahme steht aus.

## 0.5.4 – Redaktionelle Medien
Je Nachricht, Termin, Sponsor oder Gastronomieeintrag ein Bild hochladen, ersetzen, ansehen oder entfernen. PNG/JPEG/WebP bis 2 MB und 3000 × 3000 Pixel, serverseitig PNG-Neukodierung, 50 MB redaktionelle Medien pro Verein. Bilder als BLOB im geschützten SQLite-Speicher, ausschließlich authentifizierte Vorschau mit Vereins- und Modulprüfung. Beim Löschen eines Eintrags wird das Bild ebenfalls gelöscht. Keine öffentliche Medienauslieferung, Display-Anbindung folgt. PHP GD erforderlich; Live-Tests ausstehend.

## 0.4.35 – Admin-Link in der Entwicklungsdemo
Kleiner Admin-Link im Footer von clubdisplay.tennilo.de öffnet /admin/ in einem neuen Tab. Auf dem NTC-Host und im Vollbild ausgeblendet. Keine Änderung am Passwortschutz.

## 0.4.36 / Admin 0.5.5 – Getrennter Vereinslogin
Produktseite und beide Display-Hosts verlinken ausschließlich den Vereinslogin /admin/?portal=club; NTC-Display mit zusätzlicher fester Vereinskennung verein=ntc93. Link im Display-Footer nur außerhalb des Vollbilds. Betreiberlogin /admin/ bleibt separat, eigene Session-Cookies, Betreiberpasswort wird am Vereinsportal nicht als Betreiberzugang akzeptiert. Vereinsrechte weiterhin serverseitig. NTC-Zugang muss durch TENNILO freigeschaltet sein. Noch keine Live-Anbindung der Inhaltsprofile.

## Admin 0.5.6 – Strukturierte Gastronomie
Pro Verein mit Gastronomiemodul: Name, Telefon, Website, Online-Reservierungslink, Reservierung vor Ort/telefonisch, Hinweise, reguläre Wochenöffnungszeiten mit bis zu zwei Zeitfenstern je Tag (auch über Mitternacht), datumsbezogene Sonderöffnungszeiten und wiederkehrende Wochenangebote mit optionalem Gültigkeitszeitraum und Preis. Neue SQLite-Tabelle; bestehende redaktionelle Restaurant-Einträge und Bilder bleiben bestehen. Bis zu 40 Sondertermine und 40 Angebote; zusätzliche Eingabezeilen nach Speichern. Keine automatische Veröffentlichung auf Displays, keine echte Reservierung, keine vorbefüllten NTC-Daten. Live-Abnahme ausstehend.


## Admin 0.6.0–0.6.2 – Verwaltete Displays und Betriebsverwaltung

- Zentraler Betreiber- und Vereinslogin mit getrennten Sitzungen. Das Betreiberpasswort bleibt im vorhandenen GitHub-Secret; bestehende Betreiber-Sitzungen werden beim Deployment durch den neu generierten Hash ungültig.
- Pro Bildschirm: eigene Sprache, Module, Intervall, Modus und Vorschau. Geräteschlüssel werden zufällig erzeugt, nur als SHA-256-Hash dauerhaft gespeichert, sind einzeln widerrufbar und erscheinen nach Ausgabe genau einmal. Die Geräteadresse enthält den Schlüssel im URL-Fragment; nicht weitergeben. Schlüssel wird über HTTPS im JSON-POST übertragen, nicht als Query-Parameter.
- Verwalteter Player: neuer separater Einstieg, aktualisiert freigegebene Inhalte etwa einmal pro Minute, berücksichtigt aktive Anzeigezeiträume, kommende Kalendertermine, Sonderöffnungszeiten und Tagesangebote, rendert Bilddateien über geschützte Abrufe, paginiert längere Inhalte. Bei Verbindungsfehlern werden Daten nur kurz im RAM gehalten; nach etwa drei Minuten ohne erfolgreichen Abruf Offline-Hinweis, bei 403 sofort gesperrt. Für dauerhaften Betrieb Browser-/OS-Kioskmodus und Autostart konfigurieren; Browser-Vollbild allein ist kein Sicherheitsmechanismus.
- Datenquellen: öffentliche nuLiga-clubMeetings-URL, WordPress-Posts-REST, The Events Calendar REST, öffentliche nicht wiederkehrende ICS-Termine (keine RRULE-Expansion), Bright Sky / DWD-Wetter mit Koordinaten, vorhandene NTC-Feeds. Externe Quellen werden durch TENNILO freigegeben; Vereinsadmin kann nicht eigenmächtig einen freigegebenen Endpunkt ändern. HTTPS, öffentliche IPv4-DNS-Auflösung mit IP-Pinning, keine Redirects oder privaten Netze, begrenzte Antwortgröße/Timeout, Cache. Nur öffentliche Daten ohne Zugangsschlüssel; Anbieterfreigaben und konkrete Datenqualität je Kunde prüfen. Quellenprüfung im Admin.
- PLUS (interne Kennung INTERACTIVE): ausdrücklich freigegebener externer eBuSy-Lite-Terminallink; NTC optional vorhandener terminal.php-Einstieg. BASIC (interne Kennung INFO) und Vorschau haben keine Buchungsaktion. Keine allgemeine Live-Belegungs-API, keine Buchungsdaten im Admin, kein automatisierter Anbieter-Login; Freigabe und Eignung des externen Terminals müssen je Verein geprüft werden.
- Betrieb: Online-Status aus letztem Kontakt (180 Sekunden), Geräteschlüssel erneuern/widerrufen, Vertragsstatus und Bildschirmkontingent (Standard: 1 gekoppeltes Profil je Verein; weitere Profile bleiben Entwürfe), interne Abrechnung mit vereinbarten Beträgen, Netto-/Brutto-Basis, Intervall, Rechnungsreferenz und Zahlungsstatus; CSV-Export nur für TENNILO. Keine automatisch ausgestellten Rechnungen, kein Zahlungsdienst und keine automatische Mahnung. Änderungsprotokoll der letzten 100 sichtbaren Einträge, auf max. 5000 begrenzt. Kein Fernzugriff auf das Betriebssystem des Geräts.
- Backup: Betreiber kann konsistente SQLite-Sicherung herunterladen. Geschützte Datei clubdisplay-admin/backup-admin.php ist nur im CLI-Modus nutzbar. Für täglichen Host-Cron: PHP-CLI mit vollständigem Skriptpfad und einem bereits bestehenden privaten Ausgabeordner außerhalb sämtlicher Webroots aufrufen; 30 Tage lokale Rotation, Integritätsprüfung. Cron, externe verschlüsselte Sicherung und Restore-Prozess beim Hoster einrichten und prüfen. Keine automatischen Live-Wiederherstellungen.
- Migration: bestehende Datenbank bleibt erhalten; neue Tabellen über SQLite-Schemaversion 602. Bestehende NTC- und TENNILO-Displays werden nicht automatisch auf den neuen Player umgestellt. NTC-Datenquellen und Vertrags-/Geräteprofil zunächst im Admin freigeben; Gastro- und Medieninhalte prüfen/einpflegen; zuerst Vorschau, dann eigenes Testgerät, erst nach Abnahme Live-Wechsel.
- CI: .github/workflows/check-admin.yml prüft Alt- und Neufunktionen ohne Upload; isolierte echte HTTP-Anmeldungen, CSRF, Upload, Snapshot, Gerätewiderruf, Backup; separater Chromium-Test mit Desktop-/Mobil-Screenshots. Der manuelle Admin-Upload verlangt dieselben HTTP- und Browserprüfungen, Schutzordner-Proben, API-Sperrtests und Asset-Prüfung vor/nach Upload. Nur öffentliche Display-Dateien lösen weiterhin automatische Display-Uploads aus.
- Hosting: PHP 8.2+ (unterstützte Version), PDO SQLite, GD, DOM/XML, cURL, FTPS, HTTPS, Apache .htaccess-Schutz und ausreichend geschützter Speicher. Vor regulärem Verkauf: Testgerät 48–72 Stunden im Dauerbetrieb, Anbieter-/Datenschutzfreigaben, Backup-Wiederherstellung, Support-/Vertragsprozesse und Sicherheitsabnahme.

Links: Admin https://github.com/tennis-schiri/tennilo-clubdisplay/actions/workflows/deploy-admin.yml · Qualitätsprüfung https://github.com/tennis-schiri/tennilo-clubdisplay/actions/workflows/check-admin.yml.

## Display 0.4.37 – Vereinsadmin außerhalb des Kiosks
Der Vereinsadmin-Link wurde vollständig aus dem Footer beider öffentlichen Display-Versionen entfernt, auch außerhalb des Browser-Vollbilds. Der Vereinslogin auf der TENNILO-Produktseite und die direkten Admin-Adressen bleiben erhalten. Admin-PIN zur Vollbild-Freigabe unverändert.

## Admin 0.6.3 – Individuelle zusätzliche Bildschirme
Verwaltete Bildschirmprofile besitzen eine eigene Modulreihenfolge, eine optionale Begrüßung (leer = Vereinsbegrüßung) und optionale Anzeigezeit je Modul (leer = Profilstandard). Einzelne redaktionelle Inhalte einschließlich Bilder können für alle passenden Bildschirme oder eine explizite Auswahl innerhalb desselben Vereins freigegeben werden. Bestehende Einträge gelten weiterhin für alle passenden Bildschirme; neue Displays erhalten nur allgemeine Inhalte, nicht bereits gezielt zugewiesene. Externe automatische Feeds und strukturierte Gastronomiedaten werden über die Modulwahl gesteuert. Die Migration auf SQLite-Schema 603 ist additiv. Die klassischen NTC-/TENNILO-Displays bleiben unverändert; die Funktionen gelten für gekoppelte verwaltete Geräte und deren Vorschau.

## Produktnamen – BASIC und PLUS
Die öffentlichen Lizenznamen sind BASIC (19 €/Monat, 190 €/Jahr, Einrichtung 149 €) und PLUS (39 €/Monat, 390 €/Jahr, Einrichtung 299 €). Die Datenbank- und API-Kennungen INFO und INTERACTIVE bleiben absichtlich kompatibel; die Administration übersetzt diese in BASIC und PLUS. Zusätzliche Displays weiterhin 9 €/Monat bzw. 90 €/Jahr. Informationsmodus und interaktiver Modus sind weiterhin Funktionsbeschreibungen, keine Tarifnamen.

## Display 0.4.39 – Veranstaltungsbegrüßung
Für jeden Kalendereintrag mit gültigem Beginn und Ende erscheint eine eigene Begrüßungsfolie ab 30 Minuten vor Beginn bis zum angegebenen Ende. Mehrere gleichzeitig stattfindende Veranstaltungen und Heimspielbegrüßungen laufen gemeinsam in der Rotation. Die Folie enthält Titel, vollständiges Datum, Zeitraum und gegebenenfalls Veranstaltungsort; abgesagte oder zeitlich ungültige Einträge werden nicht begrüßt. Die Begrüßung endet automatisch, auch bei pausierter Folie. Kalenderdaten bleiben von der verfügbaren Datenquelle abhängig.

## Admin 0.6.4 – Veranstaltungsbegrüßung
Verwaltete Geräte zeigen die zusätzliche Begrüßungsfolie nur bei aktiviertem Kalendermodul und für freigegebene Kalenderfeeds mit gültigem Start- und Endzeitpunkt. WordPress, iCalendar und NTC-Feed liefern vorhandene Endzeiten mit. Redaktionelle Datumseinträge ohne Uhrzeit werden nicht als zeitgesteuerte Begrüßung interpretiert. Die lokale Zeitsteuerung endet unabhängig vom nächsten Serverabruf.

## SmartDisplay – public displays 0.4.51
The rotation automatically repeats today's home fixtures and currently scheduled events plus active welcome slides once per cycle; normal slides remain available. Season ordering: indoor first Oct 1–Apr 30, outdoor first May 1–Sep 30. Additional seasonal slides Dec 24–26, Dec 31 and Jan 1–6, local Europe/Berlin time. No private booking data, actual live-match status, automatic physical screen power control or weather alerts are inferred. Managed display settings are not changed by this release.

## Public display 0.4.53
Thursday–Sunday: weekend preview of Saturday/Sunday home fixtures and overlapping calendar events, five items per page. No preview when no entries are known. Individual Neuenhagen Open countdowns start 100 local calendar days before each event and expire when the welcome window starts 30 minutes before the scheduled beginning. No youth-training integration. Both slides use existing feeds and do not require manual event maintenance.

## Spielpartnerbörse (0.4.54 / 0.6.5)
The demo has fictional examples. The NTC board is initially disabled; activate it in the protected club admin. Invite codes are one-use, valid 48 hours and issued only after checking adult membership. Members submit structured requests or private interest via the smartphone form. The moderator must approve a listing; it expires after 14 days. Public feed and screens receive only a random reference and predefined categories, never private contact details. Contact mediation is performed by the club. Disable the board to delete open requests and invitations. NTC QR opens the invitation form; managed profiles require the partner_search module enabled. No direct messaging or automatic matching. Youth training is excluded.

## Spielpartnerbörse 0.4.58
QR-Code und Smartphone-Eingabe ohne Einladungscode. Nur anonyme Kategorien und Referenzen werden veröffentlicht. Auch bei leerer aktivierter Börse erscheint die QR-Folie. Kontaktanfragen werden separat an die Vereinsadministration gemeldet; Freigabe und Weiterleitung nur nach unabhängiger Prüfung beider Identitäten und der Empfängeradresse. eBuSy-Mitgliederabgleich ist perspektivisch vorgesehen, nicht aktiviert.

### Altersgruppen (0.4.60)
18+: reguläre manuelle Freigabe. 16–17: zusätzliche Jugendschutzprüfung. Unter 16: Einreichung ausschließlich durch Erziehungsberechtigte mit eigenen Kontaktdaten. Altersgruppen bleiben privat. Bei Beteiligung Minderjähriger ist die automatische Weiterleitung von Kontaktdaten serverseitig gesperrt; der Verein vermittelt geschützt und bestätigt den Abschluss. Bestehende Gesuche vor dieser Version wurden unter der bisherigen 18+-Bestätigung angenommen.

## NTC-Domain (0.4.61)
https://clubdisplay.ntc93.de/spielpartner/ und https://clubdisplay.ntc93.de/admin/ verwenden ein fest auf den NTC begrenztes HTTPS-Gateway zum zentralen Backend. Keine lokale Datenbank und kein Betreiberlogin auf der NTC-Domain. Vorhandene FTP-Zugänge genügen, PHP-cURL und ausgehendes HTTPS erforderlich. NTC-Portal in Vereinsfarben mit Vereinslogo; NTC-Absenderadresse gesucht@ntc93.de als From/Reply-To, tatsächliche Zustellung/DMARC-Ausrichtung und Hosting-Berechtigung separat prüfen. Kein SMTP-Passwort im Repository. Die zentrale Rate-Limitierung sieht bei Proxy-Anfragen die Gateway-IP; bei hoher gleichzeitiger Nutzung ist eine gemeinsame Begrenzung möglich. Bestehende Links auf der TENNILO-Domain bleiben funktionsfähig. NTC-Deploy prüft die Gateway-Routen vor Veröffentlichung des neuen QR-Codes.

## Spielpartner-Komplettprogramm 0.4.62
Neue Anfragen werden erst nach einer einmaligen 24h-E-Mail-Bestätigung dem Verein vorgelegt. Name und E-Mail Pflicht, Telefon freiwillig, LK-Bereich freiwillig (1–5, 6–10, 11–15, 16–20, 21–25). Nur strukturierte Spielwünsche und LK erscheinen öffentlich. Statusmails: Bestätigung, Eingang, Freigabe/Ablehnung, neue Kontaktanfrage, Eingang und Entscheidung der Kontaktanfrage, Vermittlung, Ablaufwarnung, Ablauf, Verlängerung und Rücknahme. Persönliche Links werden gehasht gespeichert und erfordern POST-Bestätigung. Verlängerung setzt Gesuch bis zur erneuten Freigabe auf pending. NTC-HTML-Design mit Textalternative. Wartungsworkflow stündlich (GitHub-Zeitplan kann verzögert werden); Mail-outbox mit Wiederholungen, maximal sieben Tage/acht Versuche, Versandstatus bedeutet Mailserverannahme, nicht Zustellung. Wartungsschlüssel aus bestehendem Admin-Secret zweckgebunden abgeleitet. Altdaten ohne verifizierte E-Mail erhalten keine neuen Mitglieder-Statusmails. Bestehende Jugend- und Identitätsprüfung bleibt obligatorisch.

## 0.4.63
Smartphone-Einstieg zeigt ausschließlich freigegebene anonyme Gesuche als Karten. Spielart/LK-Filter, eigener Gesuch-Button, Interesse pro Karte; getrennte Formulare. QR auf dem Kiosk ist reine scanbare Grafik ohne Link, Klick- oder Tastatur-Navigation. Freigabe, Datenschutz und E-Mail-Verifikation unverändert.

### NTC Formular-POST (26.11.2026)
Referrer-Policy strict-origin statt no-referrer, damit der Browser bei HTML-Formularen die eigene Origin beibehält, ohne persönliche URL-Token als Referrer zu übertragen. Alte offene Formulare mit Origin: null nur bei Sec-Fetch-Site: same-origin akzeptieren. Fremde Origins und Cross-Site-Fetch-Metadaten bleiben gesperrt. NTC-Deployment prüft echte POST-Routen mit absichtlich unvollständigen Formulardaten ohne E-Mail-Versand, inklusive negativem Cross-Site-Test. Browserregression prüft serverseitige Formularvalidierung.

## 0.4.66
NTC-ClubDisplay: jeder freigegebene Eintrag erhält einen lokal berechneten QR-Code (QR Model 2, Version 4-L) mit eigener öffentlicher Referenz und Direktlink zum passenden Interessenformular. Kein Klickziel, keine personenbezogenen Angaben im Code, keine Drittanbieter-Abfragen. Allgemeiner QR-Code zur anonymen Übersicht bleibt. Bei nicht mehr verfügbaren Referenzen erscheint die aktuelle Übersicht mit Hinweis. Fiktive Demo-Gesuche haben bewusst keine realen Kontaktlinks.

## 0.4.68
Mehrfachauswahl Wochentage und Tageszeiten als kanonische, kommaseparierte Auswahlschlüssel (bisherige Einzelauswahl bleibt kompatibel). Eigene Altersgruppe einmalig für Einzel/Training; weitere Altersgruppen für Doppel/Mixed. Gesuchte Altersgruppen mehrfach. Bei Beteiligung Minderjähriger greift die strengste Schutzstufe; konkrete Jugend-Altersangaben und Suchwünsche mit Jugendgruppen werden öffentlich durch 'Geschützte Vermittlung' ersetzt. Nur grobe Erwachsenen-Altersbereiche erscheinen nach Freigabe. Altgesuche ohne Alterswünsche bleiben funktionsfähig.

## 0.4.71
Geschützte Vereinsverwaltung in Übersicht, Einstellungen, Freigaben, aktive Gesuche, Kontaktvermittlung und E-Mail-Warteschlange gegliedert. Wiederholung ausstehender Statusmails auf Vereinskennung beschränkt. Verlängerung durch erneute Freigabe, keine Veröffentlichung ohne Prüfung. Öffentliche Smartphone-Ansicht: Altersgruppe bei eigenem Gesuch nur einmal erfassen, Altersprüfung daraus serverseitig ableiten; Tages-/Zeitfilter, Formularwerte bei Fehlern erhalten, sichtbare Versionsnummer. Upload prüft die tatsächlichen Eingabefelder auf TENNILO und NTC. Versandstatus bedeutet Annahme, keine Zustellbestätigung.

## 0.4.73 · MATCHBALL Newsletter
NTC-Neuigkeiten enthalten zusätzlich eine eigene Matchball-Kachel, aktuelle Ausgabe und Archiv (10/26, 09/26). Lesefassungen aus den vom Verein gelieferten PDFs, mit Quellseiten und eindeutigem Zusammenfassungshinweis. Keine öffentlichen Original-PDFs, Jugendfotos oder vereinsinternen Kontaktdaten; kein externer Browserwechsel. Die Kachel bleibt verfügbar, wenn der WordPress-Feed ausfällt. Neue Ausgaben werden zunächst redaktionell in matchballIssues (public/assets/app.js) ergänzt; ein automatischer PDF-Import ist noch nicht enthalten. In der TENNILO-Demo erscheinen keine echten NTC-Newsletter.
