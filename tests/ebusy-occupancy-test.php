<?php
declare(strict_types=1);
require __DIR__.'/../public/ntc/ebusy-occupancy.php';
$tz=new DateTimeZone('Europe/Berlin');
$start=eb_time('26.11.2026','14:00',$tz);
$rows=[['courtId'=>11,'date'=>'26.11.2026','fromTime'=>'14:15','toTime'=>'14:45','type'=>'BOOKING','person'=>['firstname'=>'SECRET'],'bookingCode'=>'SECRET'],['courtId'=>11,'date'=>'26.11.2026','fromTime'=>'15:00','toTime'=>'16:00','type'=>'BLOCKING'],['courtId'=>11,'date'=>'26.11.2026','fromTime'=>'16:00','toTime'=>'17:00','type'=>'BOOKING','cancelled'=>true]];
$s=eb_slots([['id'=>11,'name'=>'Halle 1']],$rows,'indoor',$start,$tz);
if(array_column($s,'status')!==['partial','blocked','free']||str_contains(json_encode($s),'SECRET')||$s[0]['start']!=='2026-11-26T13:00:00.000Z')throw new RuntimeException('slot test failed');
try{eb_slots([['id'=>11,'name'=>'Unknown']],[],'indoor',$start,$tz);throw new LogicException('mapping accepted');}catch(RuntimeException $e){if($e->getMessage()!=='court_mapping')throw $e;}
echo "NTC eBuSy adapter tests OK\n";
