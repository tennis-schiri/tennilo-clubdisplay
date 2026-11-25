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
