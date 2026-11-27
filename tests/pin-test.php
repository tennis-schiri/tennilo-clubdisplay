<?php
declare(strict_types=1);
define('CD_PIN_UNIT_TEST',true);
require __DIR__.'/../public/ntc/spielpartner/pin-test.php';
function check(bool $ok):void{if(!$ok)throw new RuntimeException('PIN pilot test failed');}
$p=['error'=>null,'response'=>['archived'=>false,'code'=>'00123','birthday'=>'2000-01-01','bankAccount'=>['data'=>'never returned']]];
check(cd_pin_result($p,'00123')===['status'=>'matched','age'=>'adult']);
check(cd_pin_result($p,'123')===['status'=>'denied']);
check(cd_pin_result($p,'99999')===['status'=>'denied']);
$p['response']['archived']=true;check(cd_pin_result($p,'00123')===['status'=>'denied']);
$p['response']['archived']=false;$p['response']['birthday']='invalid';check(cd_pin_result($p,'00123')===['status'=>'matched','age'=>'unknown']);
$p['response']['birthday']=(new DateTimeImmutable('today',new DateTimeZone('Europe/Berlin')))->modify('-15 years')->format('Y-m-d');
check(cd_pin_result($p,'00123')===['status'=>'matched','age'=>'child']);
$p['response']['birthday']=(new DateTimeImmutable('today',new DateTimeZone('Europe/Berlin')))->modify('-17 years')->format('Y-m-d');
check(cd_pin_result($p,'00123')===['status'=>'matched','age'=>'teen']);
unset($p['response']['code']);check(cd_pin_result($p,'00123')===['status'=>'denied']);
$db=new PDO('sqlite::memory:');
for($i=0;$i<10;$i++)check(cd_pin_attempt($db,10000));
check(!cd_pin_attempt($db,10000));check(cd_pin_attempt($db,10900));
echo "PIN pilot: exact comparison, leading zeros, archived accounts, coarse age groups, rate limit OK\n";
