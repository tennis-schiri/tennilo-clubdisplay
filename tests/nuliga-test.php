<?php
// TENNILO ClubDisplay | Copyright © 2026 TENNILO | Alle Rechte vorbehalten.
define('CLUBDISPLAY_NULIGA_TEST',true);
require __DIR__.'/../public/nuliga.php';
function check($condition,$message){if(!$condition)throw new RuntimeException($message);}
$html='<html><meta charset="utf-8"><body>Begegnungen im Zeitraum vom »01.11.2026« bis zum »30.04.2027«<table><tr><th colspan="3">Datum</th><th>Liga</th><th>Heimmannschaft</th><th>Gastmannschaft</th><th>Matchpunkte</th><th>Sätze</th><th>Spiele</th><th>Spielbericht</th></tr><tr><td>Sa.</td><td>19.12.2026 10:00</td><td></td><td>U12 VL</td><td><a>Neuenhagener Tennisclub 93 II</a></td><td><a>Testverein</a></td><td></td><td></td><td></td><td>offen</td></tr><tr><td></td><td></td><td></td><td>H30 VL</td><td><a>Testverein</a><a>[Routenplan]</a></td><td><a>Neuenhagener Tennisclub 93</a></td><td>3 : 3</td><td></td><td></td><td>anzeigen</td></tr><tr><td rowspan="2">So.</td><td rowspan="2">20.12.2026 14:00</td><td></td><td>D40 VL</td><td>Neuenhagener Tennisclub 93</td><td>Testverein</td><td></td></tr><tr><td></td><td>H40 VL</td><td>Testverein</td><td>Neuenhagener Tennisclub 93</td><td></td></tr></table></body></html>';
$r=parseMeetings($html);
check(count($r['matches'])===4,'Expected 4 rows');
check($r['matches'][0]['isHome']===true,'Home');
check($r['matches'][1]['isHome']===false,'Away');
check($r['matches'][1]['score']==='3:3','Score');
check($r['matches'][1]['date']===$r['matches'][0]['date'],'Inherited date');
check($r['matches'][3]['date']===$r['matches'][2]['date'],'Rowspan date');
check($r['range']['to']==='30.04.2027','Range');
try{parseMeetings('<html>unavailable</html>');throw new LogicException('Missing table accepted');}catch(RuntimeException $e){}
$winter='<table><tr><th colspan="3">Datum</th><th>Liga</th><th>Spielort</th><th>Heimmannschaft</th><th>Gastmannschaft</th><th>Matchpunkte</th></tr><tr><td>Sa</td><td>05.12.2026 10:00</td><td></td><td>H30 VL</td><td><a>Neuenhagen</a> <a>[Routenplan]</a></td><td>Neuenhagener Tennisclub 93 II</td><td>Testverein</td><td></td></tr><tr><td>So</td><td>06.12.2026 10:00</td><td></td><td>D40 VL</td><td>Beispielhalle</td><td>Testverein</td><td>Neuenhagener Tennisclub 93</td><td></td></tr></table>';
$w=parseMeetings($winter);
check(count($w['matches'])===2,'Winter rows');
check($w['matches'][0]['venue']==='Neuenhagen','Home venue');
check($w['matches'][1]['venue']==='Beispielhalle','Away venue');
check($r['matches'][0]['venue']===null,'Missing venue must remain null');
$labeled=str_replace('<th>Spielort</th>','',str_replace('<td><a>Neuenhagen</a> <a>[Routenplan]</a></td>','',str_replace('<td>Beispielhalle</td>','',$winter)));
$labeled=str_replace('<td>Neuenhagener Tennisclub 93 II</td>','<td><a>Neuenhagener Tennisclub 93 II</a><br>Spielort: Neuenhagen</td>',$labeled);
check(parseMeetings($labeled)['matches'][0]['venue']==='Neuenhagen','Inline venue');
echo "nuLiga parser tests OK\n";
