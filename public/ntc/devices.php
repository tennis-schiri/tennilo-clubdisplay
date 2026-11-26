<?php
// NTC-only browser registrations for authorized occupancy access.
declare(strict_types=1);
function cdtest_hash(string $token):string{return hash('sha256',$token);}
function cdtest_code():string{return strtoupper(bin2hex(random_bytes(10)));}
function cdtest_db(string $path):PDO{
    $db=new PDO('sqlite:'.$path,null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
    $db->exec('PRAGMA busy_timeout=5000');
    $db->exec('CREATE TABLE IF NOT EXISTS codes (id TEXT PRIMARY KEY, digest TEXT UNIQUE NOT NULL, label TEXT NOT NULL, expires INTEGER NOT NULL)');
    $db->exec('CREATE TABLE IF NOT EXISTS devices (id TEXT PRIMARY KEY, digest TEXT UNIQUE NOT NULL, label TEXT NOT NULL, created INTEGER NOT NULL, seen INTEGER NOT NULL, expires INTEGER NOT NULL)');
    $db->exec('CREATE TABLE IF NOT EXISTS attempts (at INTEGER NOT NULL)');
    return $db;
}
function cdtest_issue(PDO $db,string $label,int $now):string{
    $db->prepare('DELETE FROM codes WHERE expires<=?')->execute([$now]);
    if((int)$db->query('SELECT COUNT(*) FROM codes')->fetchColumn()>=20)throw new RuntimeException('Zu viele offene Codes.');
    $code=cdtest_code();
    $db->prepare('INSERT INTO codes VALUES(?,?,?,?)')->execute([bin2hex(random_bytes(8)),cdtest_hash($code),$label,$now+900]);
    return $code;
}
function cdtest_register(PDO $db,string $code,int $now):?string{
    $db->exec('BEGIN IMMEDIATE');
    try{
        $db->prepare('DELETE FROM codes WHERE expires<=?')->execute([$now]);
        $q=$db->prepare('SELECT * FROM codes WHERE digest=?');$q->execute([cdtest_hash($code)]);$row=$q->fetch(PDO::FETCH_ASSOC);
        if(!$row){$db->exec('COMMIT');return null;}
        if((int)$db->query('SELECT COUNT(*) FROM devices')->fetchColumn()>=100){$db->exec('COMMIT');return null;}
        $token=bin2hex(random_bytes(32));
        $db->prepare('INSERT INTO devices VALUES(?,?,?,?,?,?)')->execute([bin2hex(random_bytes(8)),cdtest_hash($token),$row['label'],$now,$now,$now+31536000]);
        $db->prepare('DELETE FROM codes WHERE id=?')->execute([$row['id']]);
        $db->exec('COMMIT');return $token;
    }catch(Throwable $e){$db->exec('ROLLBACK');throw $e;}
}
function cdtest_lookup(PDO $db,string $token,int $now):?array{
    if(!preg_match('/^[a-f0-9]{64}$/D',$token))return null;
    $q=$db->prepare('SELECT id,label,seen,expires FROM devices WHERE digest=? AND expires>?');$q->execute([cdtest_hash($token),$now]);
    return $q->fetch(PDO::FETCH_ASSOC)?:null;
}
function cdtest_attempt(PDO $db,int $now):bool{
    $db->exec('BEGIN IMMEDIATE');
    try{$db->prepare('DELETE FROM attempts WHERE at<=?')->execute([$now-300]);
        $ok=(int)$db->query('SELECT COUNT(*) FROM attempts')->fetchColumn()<8;
        if($ok)$db->prepare('INSERT INTO attempts VALUES(?)')->execute([$now]);
        $db->exec('COMMIT');return $ok;
    }catch(Throwable $e){$db->exec('ROLLBACK');throw $e;}
}
if(PHP_SAPI==='cli'||defined('CD_DEVICE_LIBRARY_ONLY'))return;
ini_set('display_errors','0');
header('Cache-Control: no-store, private');header('X-Robots-Tag: noindex, nofollow');header('X-Content-Type-Options: nosniff');header('Referrer-Policy: strict-origin');
header("Content-Security-Policy: default-src 'none'; style-src 'self'; form-action 'self'; frame-ancestors 'none'; base-uri 'none'");
if(($_SERVER['HTTP_HOST']??'')!=='clubdisplay.ntc93.de'||empty($_SERVER['HTTPS'])||$_SERVER['HTTPS']==='off'){http_response_code(404);exit;}
$method=$_SERVER['REQUEST_METHOD']??'';
if(!in_array($method,['GET','POST'],true)){http_response_code(405);exit;}
function cdtest_json(bool $registered):never{header('Content-Type: application/json; charset=utf-8');echo json_encode(['registered'=>$registered,'mode'=>$registered?'live':'none']);exit;}
function cdtest_h(mixed $v):string{return htmlspecialchars((string)$v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');}
function cdtest_cookie(string $value,int $expires):void{setcookie('__Host-CD_TEST',$value,['expires'=>$expires,'path'=>'/','secure'=>true,'httponly'=>true,'samesite'=>'Strict']);}
$mode=isset($_GET['manage'])?'manage':'register';
$private=__DIR__.'/devices-private';
try{
    if(!is_file($private.'/config.php')||!is_file($private.'/.htaccess'))throw new RuntimeException('unconfigured');
    $config=require $private.'/config.php';
    $db=cdtest_db($private.'/devices.sqlite');
    $now=time();$token=is_string($_COOKIE['__Host-CD_TEST']??null)?$_COOKIE['__Host-CD_TEST']:'';
    $device=cdtest_lookup($db,$token,$now);
    if(isset($_GET['status'])){
        if($method!=='GET'){http_response_code(405);cdtest_json(false);}
        if($device&&$device['seen']<$now-300){
            $db->prepare('UPDATE devices SET seen=?,expires=? WHERE id=?')->execute([$now,$now+31536000,$device['id']]);
            cdtest_cookie($token,$now+31536000);
        }
        cdtest_json($device!==null);
    }
    session_name('CD_TEST_ADMIN');session_set_cookie_params(['lifetime'=>0,'path'=>'/devices.php','secure'=>true,'httponly'=>true,'samesite'=>'Strict']);
    ini_set('session.use_strict_mode','1');session_start();
    $_SESSION['csrf']??=bin2hex(random_bytes(32));
    $auth=($_SESSION['adminUntil']??0)>$now;
    $message='';$issued='';
    if($method==='POST'){
        if(($_SERVER['HTTP_ORIGIN']??'')!=='https://clubdisplay.ntc93.de'||(int)($_SERVER['CONTENT_LENGTH']??0)>4096||!is_string($_POST['csrf']??null)||!hash_equals($_SESSION['csrf'],$_POST['csrf'])){http_response_code(403);exit('Anfrage abgelehnt.');}
        $action=$_POST['action']??'';
        if($action==='login'&&$mode==='manage'){
            $pin=$_POST['pin']??'';
            if(!cdtest_attempt($db,$now))$message='Zu viele Versuche. Bitte später erneut versuchen.';
            elseif(is_string($pin)&&preg_match('/^\d{6,20}$/D',$pin)&&is_string($config['hash']??null)&&password_verify($pin,$config['hash'])){
                session_regenerate_id(true);$_SESSION['csrf']=bin2hex(random_bytes(32));$_SESSION['adminUntil']=$now+1800;$auth=true;
            }else $message='Admin-PIN nicht korrekt oder nicht eingerichtet.';
        }elseif($action==='logout'&&$mode==='manage'){
            unset($_SESSION['adminUntil']);session_regenerate_id(true);$_SESSION['csrf']=bin2hex(random_bytes(32));$auth=false;
        }elseif($mode==='manage'&&$auth){
            if($action==='issue'){
                $label=trim(is_string($_POST['label']??null)?$_POST['label']:'');
                if($label===''||strlen($label)>100)$message='Bitte einen Gerätenamen mit maximal 100 Zeichen angeben.';
                else try{$issued=cdtest_issue($db,$label,$now);}catch(RuntimeException $e){$message='Keine weiteren Codes möglich.';}
            }elseif(in_array($action,['revoke','cancel'],true)){
                $id=$_POST['id']??'';if(is_string($id)&&preg_match('/^[a-f0-9]{16}$/D',$id)){
                    $db->prepare($action==='revoke'?'DELETE FROM devices WHERE id=?':'DELETE FROM codes WHERE id=?')->execute([$id]);$message='Freigabe bzw. Code widerrufen.';
                }
            }
        }elseif($mode==='register'&&$action==='register'){
            $code=strtoupper(preg_replace('/[\s-]+/','',is_string($_POST['code']??null)?$_POST['code']:''));
            if(!cdtest_attempt($db,$now))$message='Zu viele Versuche. Bitte später erneut versuchen.';
            elseif(preg_match('/^[A-F0-9]{20}$/D',$code)&&($new=cdtest_register($db,$code,$now))){
                if($device)$db->prepare('DELETE FROM devices WHERE id=?')->execute([$device['id']]);
                cdtest_cookie($new,$now+31536000);header('Location: /',true,303);exit;
            }else $message='Code ungültig, abgelaufen oder bereits verwendet.';
        }elseif($mode==='register'&&$action==='forget'){
            if($device)$db->prepare('DELETE FROM devices WHERE id=?')->execute([$device['id']]);
            cdtest_cookie('',1);header('Location: /devices.php',true,303);exit;
        }
    }
}catch(Throwable $e){
    http_response_code(503);if(isset($_GET['status']))cdtest_json(false);exit('Geräteverwaltung momentan nicht verfügbar.');
}
header('Content-Type: text/html; charset=utf-8');
$csrf=cdtest_h($_SESSION['csrf']);
function cdtest_field(string $csrf,string $action):string{return '<input type="hidden" name="csrf" value="'.$csrf.'"><input type="hidden" name="action" value="'.cdtest_h($action).'">';}
function cdtest_date(int $ts):string{return (new DateTimeImmutable('@'.$ts))->setTimezone(new DateTimeZone('Europe/Berlin'))->format('d.m.Y H:i');}
?><!doctype html><html lang="de"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>NTC · Gerätefreigaben</title><link rel="stylesheet" href="/admin/admin.css"></head><body class="ntc-portal"><main><section class="panel"><h1>NTC · Gerätefreigaben</h1><p>Registrierte Browser erhalten Zugriff auf die aktuelle NTC-Platzbelegung ohne Spielernamen. Nur berechtigte Vereins- und Wartungsgeräte registrieren; die eBuSy-Lizenzbedingungen sind zu beachten.</p><?php if($message!==''):?><p role="status"><?=cdtest_h($message)?></p><?php endif?>
<?php if($mode==='manage'&&!$auth):?><h2>Admin-Anmeldung</h2><form method="post" action="/devices.php?manage=1"><?=cdtest_field($csrf,'login')?><label>Bestehender ClubDisplay-Admin-PIN <input type="password" name="pin" inputmode="numeric" pattern="[0-9]{6,20}" required autocomplete="off"></label><button>Anmelden</button></form>
<?php elseif($mode==='manage'):?><h2>Neuen Browser freigeben</h2><form method="post" action="/devices.php?manage=1"><?=cdtest_field($csrf,'issue')?><label>Gerätename <input name="label" maxlength="100" placeholder="Büro-Rechner" required></label><button>Registrierungscode erzeugen</button></form>
<?php if($issued!==''):?><p><strong>Einmaliger Code (15 Minuten gültig):</strong></p><input readonly value="<?=cdtest_h($issued)?>" aria-label="Registrierungscode"><p>Auf dem freizugebenden Gerät <a href="/devices.php">clubdisplay.ntc93.de/devices.php</a> öffnen und den Code eingeben.</p><?php endif?>
<h2>Freigegebene Browser</h2><p>Die Freigabe läuft nach 365 Tagen ohne Nutzung ab. Jederzeit widerrufbar.</p>
<?php foreach($db->query('SELECT id,label,created,seen,expires FROM devices ORDER BY seen DESC')->fetchAll(PDO::FETCH_ASSOC) as $d):?><form method="post" action="/devices.php?manage=1"><?=cdtest_field($csrf,'revoke')?><input type="hidden" name="id" value="<?=cdtest_h($d['id'])?>"><p><strong><?=cdtest_h($d['label'])?></strong> · gekoppelt <?=cdtest_date((int)$d['created'])?> · zuletzt <?=cdtest_date((int)$d['seen'])?><?=((int)$d['expires']<=$now?' · abgelaufen':'')?></p><button class="danger">Widerrufen</button></form><?php endforeach?>
<h2>Offene Registrierungscodes</h2><?php $q=$db->prepare('SELECT id,label,expires FROM codes WHERE expires>? ORDER BY expires');$q->execute([$now]);foreach($q->fetchAll(PDO::FETCH_ASSOC) as $c):?><form method="post" action="/devices.php?manage=1"><?=cdtest_field($csrf,'cancel')?><input type="hidden" name="id" value="<?=cdtest_h($c['id'])?>"><p><?=cdtest_h($c['label'])?> · gültig bis <?=cdtest_date((int)$c['expires'])?></p><button>Code widerrufen</button></form><?php endforeach?><form method="post" action="/devices.php?manage=1"><?=cdtest_field($csrf,'logout')?><button>Abmelden</button></form>
<?php else:?><h2>Browser registrieren</h2><?php if($device):?><p>Dieser Browser ist als <?=cdtest_h($device['label'])?> freigegeben.</p><form method="post" action="/devices.php"><?=cdtest_field($csrf,'forget')?><button>Freigabe dieses Browsers entfernen</button></form><p><a href="/">ClubDisplay öffnen</a></p><?php else:?><form method="post" action="/devices.php"><?=cdtest_field($csrf,'register')?><label>Einmaliger Registrierungscode <input name="code" maxlength="32" autocomplete="off" required></label><button>Browser freigeben</button></form><?php endif?><?php endif?><p><a href="<?=$mode==='manage'?'/admin/':'/'?>">Zurück</a></p></section></main></body></html>
