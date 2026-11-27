<?php
declare(strict_types=1);
define('CD_PIN_UNIT_TEST',true);
require __DIR__.'/../public/ntc/spielpartner/pin-test.php';
function check(bool $ok):void{if(!$ok)throw new RuntimeException('PIN pilot test failed');}
$p=['error'=>null,'response'=>['archived'=>false,'code'=>'00123','birthday'=>'2000-01-01','bankAccount'=>['data'=>'never returned']]];
check(cd_pin_result($p,'00123')===['status'=>'matched','age'=>'adult']);
check(cd_pin_result($p,'123')===['status'=>'pin_mismatch']);
check(cd_pin_result($p,'99999')===['status'=>'pin_mismatch']);
$p['response']['archived']=true;check(cd_pin_result($p,'00123')===['status'=>'archived']);
check(cd_pin_result(['error'=>'fictional error','response'=>null],'00123')===['status'=>'api_error']);
check(cd_pin_result(['response'=>null],'00123')===['status'=>'not_found']);
check(cd_pin_result([],'00123')===['status'=>'unexpected_envelope']);
check(cd_pin_result(['response'=>[['id'=>1]]],'00123')===['status'=>'unexpected_person']);
check(cd_pin_result(['response'=>['id'=>1]],'00123')===['status'=>'archive_unknown']);
check(cd_pin_result(['response'=>['archived'=>false,'code'=>'']],'00123')===['status'=>'code_empty']);
check(cd_pin_result(['response'=>['archived'=>false,'code'=>123]],'00123')===['status'=>'code_type']);
$p['response']['archived']=false;$p['response']['birthday']='invalid';check(cd_pin_result($p,'00123')===['status'=>'matched','age'=>'unknown']);
$p['response']['birthday']=(new DateTimeImmutable('today',new DateTimeZone('Europe/Berlin')))->modify('-15 years')->format('Y-m-d');
check(cd_pin_result($p,'00123')===['status'=>'matched','age'=>'child']);
$p['response']['birthday']=(new DateTimeImmutable('today',new DateTimeZone('Europe/Berlin')))->modify('-17 years')->format('Y-m-d');
check(cd_pin_result($p,'00123')===['status'=>'matched','age'=>'teen']);
unset($p['response']['code']);check(cd_pin_result($p,'00123')===['status'=>'code_missing']);
$db=new PDO('sqlite::memory:');
for($i=0;$i<10;$i++)check(cd_pin_attempt($db,10000));
check(!cd_pin_attempt($db,10000));check(cd_pin_attempt($db,10900));
echo "PIN pilot: exact comparison, leading zeros, archived accounts, coarse age groups, rate limit OK\n";
