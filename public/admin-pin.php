<?php
// TENNILO ClubDisplay | Copyright © 2026 TENNILO | Alle Rechte vorbehalten.
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
function reply(int $code, bool $ok=false): never {
    http_response_code($code); echo json_encode(['ok'=>$ok]); exit;
}
// The deployment injects only a salted password hash. No PIN is committed.
$hash='__CLUBDISPLAY_ADMIN_HASH__';
if(($_SERVER['REQUEST_METHOD']??'')!=='POST')reply(405);
$host=$_SERVER['HTTP_HOST']??'';
if(!in_array($host,['clubdisplay.ntc93.de','clubdisplay.tennilo.de'],true))reply(403);
if(($_SERVER['HTTP_ORIGIN']??'')!=='https://'.$host)reply(403);
if(!str_starts_with(strtolower($_SERVER['CONTENT_TYPE']??''),'application/json'))reply(415);
if(strlen($hash)<50||!str_starts_with($hash,'$2y$'))reply(503);
if((int)($_SERVER['CONTENT_LENGTH']??0)>256)reply(413);
$body=json_decode((string)file_get_contents('php://input',false,null,0,257),true);
$pin=$body['pin']??null;
if(!is_string($pin)||!preg_match('/^\d{6,20}$/D',$pin))reply(400);
// One global attempt budget per deployment, independent of client IP.
$dir=sys_get_temp_dir().'/tennilo-admin-'.hash('sha256',__DIR__);
if(!is_dir($dir)&&!@mkdir($dir,0700)&&!is_dir($dir))reply(503);
$file=@fopen($dir.'/attempts','c+');if(!$file)reply(503);
if(!flock($file,LOCK_EX)){fclose($file);reply(503);}
$now=time();$state=json_decode((string)stream_get_contents($file),true);
$attempts=is_array($state)?array_values(array_filter($state,fn($t)=>is_int($t)&&$t>$now-300)):[];
if(count($attempts)>=5){fclose($file);header('Retry-After: '.max(1,300-$now+min($attempts)));reply(429);}
$ok=password_verify($pin,$hash);
if(!$ok)$attempts[]=$now;
else $attempts=[];
rewind($file);
if(!ftruncate($file,0)||fwrite($file,json_encode($attempts))===false||!fflush($file)){fclose($file);reply(503);}
fclose($file);
if($ok&&($body['action']??'')==='terminal'){
    if($host!=='clubdisplay.ntc93.de')reply(403);
    $token=bin2hex(random_bytes(32));
    // Single authorized kiosk per deployment. Never persist the cookie itself.
    $state=json_encode(['hash'=>hash('sha256',$token),'expires'=>time()+365*86400],JSON_THROW_ON_ERROR);
    $temp=tempnam($dir,'terminal-');
    if($temp===false)reply(503);
    if(!chmod($temp,0600)||file_put_contents($temp,$state,LOCK_EX)===false||!rename($temp,$dir.'/terminal-auth.json')){@unlink($temp);reply(503);}
    setcookie('__Host-clubdisplay-terminal',$token,['expires'=>time()+365*86400,'path'=>'/','secure'=>true,'httponly'=>true,'samesite'=>'Strict']);
}
reply($ok?200:403,$ok);
