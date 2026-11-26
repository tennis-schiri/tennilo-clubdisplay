<?php
declare(strict_types=1);
require __DIR__.'/../public/ntc/ebusy-occupancy.php';
$tz=new DateTimeZone('Europe/Berlin');
$start=eb_time('26.11.2026','14:00',$tz);
$rows=[['courtId'=>11,'date'=>'26.11.2026','fromTime'=>'14:15','toTime'=>'14:45','type'=>'BOOKING','person'=>['firstname'=>'Max','lastname'=>'Muster','email'=>'SECRET'],'additionalPlayerInfos'=>[['type'=>'REGULAR','player'=>['firstname'=>'Eva','lastname'=>'Beispiel']]],'bookingCode'=>'SECRET'],['courtId'=>11,'date'=>'26.11.2026','fromTime'=>'15:00','toTime'=>'16:00','type'=>'BLOCKING'],['courtId'=>11,'date'=>'26.11.2026','fromTime'=>'16:00','toTime'=>'17:00','type'=>'BOOKING','cancelled'=>true]];
$s=eb_slots([['id'=>11,'name'=>'Halle 1']],$rows,'indoor',$start,$tz);
if(array_column($s,'status')!==['partial','blocked','free']||($s[0]['bookings'][0]['displayLabel']??'')!=='Max Muster / Eva Beispiel'||str_contains(json_encode($s),'SECRET')||$s[0]['start']!=='2026-11-26T13:00:00.000Z')throw new RuntimeException('slot test failed');
try{eb_slots([['id'=>11,'name'=>'Unknown']],[],'indoor',$start,$tz);throw new LogicException('mapping accepted');}catch(RuntimeException $e){if($e->getMessage()!=='court_mapping')throw $e;}
$matches=[];$future=[['courtId'=>11,'date'=>'27.11.2026','fromTime'=>'10:00','toTime'=>'14:00','type'=>'BLOCKING','displayText'=>'Punktspiel Herren 30 II'],['courtId'=>11,'date'=>'27.11.2026','fromTime'=>'15:00','toTime'=>'16:00','type'=>'BOOKING','displayText'=>'Training Herren 30 II']];
eb_slots([['id'=>11,'name'=>'Halle 1']],$future,'indoor',$start,$tz,$matches);
if(count($matches)!==1||$matches[0]['team']!=='H30:2'||$matches[0]['court']!==1)throw new RuntimeException('fixture mapping');
$db=cdtest_db(':memory:');$now=time();
$code=cdtest_issue($db,'Büro',$now);$token=cdtest_register($db,$code,$now);
if(!$token||!eb_registered_device($db,$token,$now)||eb_registered_device($db,str_repeat('a',64),$now)||eb_registered_device($db,'',$now)||eb_registered_device(null,$token,$now)||eb_registered_device($db,$token,$now+31536001))throw new RuntimeException('live browser authorization');
$db->exec('DELETE FROM devices');
if(eb_registered_device($db,$token,$now))throw new RuntimeException('revoked browser still authorized');
echo "NTC eBuSy adapter and browser access tests OK\n";
