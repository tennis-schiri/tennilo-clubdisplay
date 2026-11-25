<?php
// TENNILO ClubDisplay | Copyright © 2026 TENNILO | Alle Rechte vorbehalten.
declare(strict_types=1);
header('Cache-Control: no-store, private');
header('Referrer-Policy: no-referrer');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
$client='__CLUBDISPLAY_EBUSY_CLIENT__';
$configured=preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/iD',$client)===1;
$ready=false;
if(($_SERVER['HTTP_HOST']??'')!=='clubdisplay.ntc93.de'||($_SERVER['REQUEST_METHOD']??'')!=='GET'){http_response_code(404);exit;}
$cookie=$_COOKIE['__Host-clubdisplay-terminal']??'';
if($configured&&is_string($cookie)&&preg_match('/^[0-9a-f]{64}$/D',$cookie)){
    $dir=sys_get_temp_dir().'/tennilo-admin-'.hash('sha256',__DIR__);
    $state=json_decode((string)@file_get_contents($dir.'/terminal-auth.json'),true);
    $ready=is_array($state)&&is_string($state['hash']??null)&&($state['expires']??0)>time()&&hash_equals($state['hash'],hash('sha256',$cookie));
}
if(isset($_GET['status'])){
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['configured'=>$configured,'ready'=>$ready]);exit;
}
if(!$ready){http_response_code(403);header('Content-Type: text/plain; charset=utf-8');echo 'Terminal nicht freigeschaltet.';exit;}
header('Location: https://ntc93v8.ebusy.de/court-module/3076?ebusy-client='.rawurlencode($client),true,302);
exit;
