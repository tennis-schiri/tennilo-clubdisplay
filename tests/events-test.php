<?php
// TENNILO ClubDisplay | Copyright © 2026 TENNILO | Alle Rechte vorbehalten.
define('CLUBDISPLAY_EVENTS_TEST',true);require __DIR__.'/../public/events.php';
function check($ok,$msg){if(!$ok)throw new RuntimeException($msg);}
$ics="BEGIN:VCALENDAR\r\nBEGIN:VEVENT\r\nUID:1\r\nDTSTART;VALUE=DATE:20261205\r\nDTEND;VALUE=DATE:20261207\r\nSUMMARY:Clubturnier\\, Tag 1\r\nLOCATION:Vereinsanlage\r\nURL:https://ntc93.com/event/test/\r\nEND:VEVENT\r\nBEGIN:VEVENT\r\nUID:2\r\nDTSTART;TZID=Europe/Berlin:20261210T180000\r\nDTEND:20261210T200000\r\nSUMMARY:Testabend\r\nEND:VEVENT\r\nBEGIN:VEVENT\r\nUID:3\r\nSTATUS:CANCELLED\r\nDTSTART:20261211T180000\r\nSUMMARY:Abgesagt\r\nEND:VEVENT\r\nEND:VCALENDAR";
$v=parseCalendarIcs($ics);
check(count($v)===2,'Cancelled event excluded');
check($v[0]['allDay']&&substr($v[0]['end'],0,10)==='2026-12-06','Exclusive all-day end');
check($v[0]['title']==='Clubturnier, Tag 1','Escaped title');
check($v[1]['start']==='2026-12-10T18:00:00+01:00','Local time');
check(eventLink('javascript:alert(1)')===null,'Unsafe URL');
check(eventLink('https://external.example/')===null,'External URL');
echo "Calendar parser tests OK\n";

$zone=new DateTimeZone('Europe/Berlin');
[$from,$to]=eventRange(new DateTimeImmutable('2026-11-26',$zone));
check($from==='2026-11-26'&&$to==='2027-11-26','Twelve-month calendar horizon');
$rows=parseCalendarIcs("BEGIN:VCALENDAR\r\nBEGIN:VEVENT\r\nUID:june-test\r\nDTSTART;VALUE=DATE:20270611\r\nDTEND;VALUE=DATE:20270614\r\nSUMMARY:Mehrtaegiges Beispiel\r\nEND:VEVENT\r\nEND:VCALENDAR");
check(count($rows)===1&&$rows[0]['allDay']&&$rows[0]['start']==='2027-06-11T00:00:00+02:00'&&$rows[0]['end']==='2027-06-13T23:59:59+02:00','Multi-day all-day June event');
check(substr($rows[0]['start'],0,10)<=$to&&substr($rows[0]['end'],0,10)>=$from,'June event is in expanded range');
check(!eventCacheFresh(['fetchedAt'=>1000],1001),'Old six-month cache must be refreshed');
check(eventCacheFresh(['cacheVersion'=>EVENTS_CACHE_VERSION,'fetchedAt'=>1000],1001),'New cache retained within TTL');
check(!eventCacheFresh(['cacheVersion'=>EVENTS_CACHE_VERSION,'fetchedAt'=>1000],1000+EVENTS_TTL),'Cache expiry unchanged');
echo "Twelve-month horizon, multi-day June events and cache migration OK\n";
