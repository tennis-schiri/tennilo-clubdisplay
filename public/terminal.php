<?php
// TENNILO ClubDisplay | Copyright © 2026 TENNILO | Alle Rechte vorbehalten.
declare(strict_types=1);
header('Cache-Control: no-store, private');
header('Referrer-Policy: no-referrer');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
$client='__CLUBDISPLAY_EBUSY_CLIENT__';
$configured=preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/iD',$client)===1;
if(($_SERVER['HTTP_HOST']??'')!=='clubdisplay.ntc93.de'||($_SERVER['REQUEST_METHOD']??'')!=='GET'){http_response_code(404);exit;}
if(isset($_GET['status'])){
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['configured'=>$configured,'ready'=>$configured]);exit;
}
if(!$configured){http_response_code(503);header('Content-Type: text/plain; charset=utf-8');echo 'eBuSy momentan nicht verfügbar.';exit;}
// The NTC booking view opens directly; authentication of members is handled by eBuSy.
header('Location: https://ntc93v8.ebusy.de/lite-module/1731?ebusy-client='.rawurlencode($client),true,302);
exit;
