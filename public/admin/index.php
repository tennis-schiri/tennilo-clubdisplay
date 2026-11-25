<?php
declare(strict_types=1);
header('Cache-Control: no-store, private');
header('X-Robots-Tag: noindex, nofollow');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');
header("Content-Security-Policy: default-src 'none'; style-src 'self'; img-src 'self'; form-action 'self'; frame-ancestors 'none'; base-uri 'none'");
if(($_SERVER['HTTP_HOST']??'')!=='clubdisplay.tennilo.de'||(empty($_SERVER['HTTPS'])||$_SERVER['HTTPS']==='off')){http_response_code(404);exit;}
require __DIR__.'/lib.php';
$hash='__CD_ADMIN_PASSWORD_HASH__';
$storage=base64_decode('__CD_ADMIN_STORAGE_B64__',true);
if(!str_starts_with($hash,'$2')||!is_string($storage)||!is_dir($storage)||!is_writable($storage)){http_response_code(503);exit('Administration noch nicht eingerichtet.');}
$dir=realpath($storage);$root=realpath($_SERVER['DOCUMENT_ROOT']??'');
if(!$dir||!$root||$dir===$root||str_starts_with($dir,$root.DIRECTORY_SEPARATOR)){http_response_code(503);exit('Privater Speicher fehlt.');}
umask(0077);
try{$db=cd_db($dir.'/clubdisplay-admin.sqlite');}catch(Throwable $e){http_response_code(503);exit('Privater Speicher nicht verfügbar.');}
ini_set('session.use_strict_mode','1');
session_name('CD_ADMIN');
session_set_cookie_params(['lifetime'=>0,'path'=>'/admin/','secure'=>true,'httponly'=>true,'samesite'=>'Strict']);
session_start();
if(isset($_SESSION['last'])&&time()-$_SESSION['last']>1800){$_SESSION=[];session_regenerate_id(true);}
if(!empty($_SESSION['auth'])&&isset($_SESSION['club'])){ $account=cd_account_row($db,(string)$_SESSION['club']); if(!$account||!$account['enabled']||!hash_equals((string)($_SESSION['account_version']??''),hash('sha256',$account['password_hash']))){$_SESSION=[];session_regenerate_id(true);} }
$_SESSION['csrf']??=bin2hex(random_bytes(32));
$scope=isset($_SESSION['club'])?(string)$_SESSION['club']:null;
$error='';$ok='';
if($_SERVER['REQUEST_METHOD']==='POST'){
    if(!hash_equals($_SESSION['csrf'],(string)($_POST['csrf']??''))){http_response_code(403);exit('Ungültige Sitzung.');}
    $action=$_POST['action']??'';
    if($action==='login'){
        $ip=hash('sha256',$_SERVER['REMOTE_ADDR']??'unknown');$now=time();
        $db->exec('BEGIN IMMEDIATE');
        try{
            $q=$db->prepare('DELETE FROM login_attempts WHERE since < ?');$q->execute([$now-900]);
            $q=$db->prepare('SELECT count FROM login_attempts WHERE ip=?');$q->execute([$ip]);$count=(int)$q->fetchColumn();
            $allowed=$count<5;
            $clubLogin=trim((string)($_POST['club_login']??''));
            $candidate=cd_login_hash($db,$clubLogin,$hash);
            $password=(string)($_POST['password']??'');
            $valid=$allowed&&password_verify($password,$candidate!==''?$candidate:$hash)&&$candidate!=='';
            
            if($valid){$q=$db->prepare('DELETE FROM login_attempts WHERE ip=?');$q->execute([$ip]);}
            elseif($allowed){$q=$db->prepare('INSERT INTO login_attempts VALUES (?,1,?) ON CONFLICT(ip) DO UPDATE SET count=count+1');$q->execute([$ip,$now]);}
            $db->exec('COMMIT');
        }catch(Throwable $e){$db->exec('ROLLBACK');http_response_code(503);exit('Login momentan nicht verfügbar.');}
        if($valid){session_regenerate_id(true);$_SESSION=['auth'=>true,'last'=>$now,'csrf'=>bin2hex(random_bytes(32))];if($clubLogin!==''){$_SESSION['club']=$clubLogin;$_SESSION['account_version']=hash('sha256',$candidate);}header('Location: ./',true,303);exit;}
        $error='Anmeldung fehlgeschlagen oder vorübergehend gesperrt.';
    }elseif(!empty($_SESSION['auth'])){
        if($action==='logout'){$_SESSION=[];session_destroy();header('Location: ./',true,303);exit;}
        if(in_array($action,['create','save'],true)){
            try{if($scope!==null)throw new InvalidArgumentException('Nur TENNILO darf Vereinsprofile und Lizenzen ändern.');cd_save($db,$_POST,$action==='create');$_SESSION['flash']='Vereinsprofil gespeichert.';header('Location: ./',true,303);exit;}
            catch(InvalidArgumentException $e){$error=$e->getMessage();}
            catch(Throwable $e){$error='Speichern fehlgeschlagen. Ist die Vereinskennung bereits vergeben?';}
        }
        if(in_array($action,['display-create','display-save','account'],true)){
            try{
                if($action==='account'){if($scope!==null)throw new InvalidArgumentException('Keine Berechtigung.');cd_account($db,(string)($_POST['club']??''),(string)($_POST['new_password']??''),isset($_POST['enabled']));}
                else cd_save_display($db,$_POST,$action==='display-create',$scope);
                $_SESSION['flash']='Änderungen gespeichert.';header('Location: ./',true,303);exit;
            }catch(InvalidArgumentException $e){$error=$e->getMessage();}
            catch(Throwable $e){$error='Speichern fehlgeschlagen. Kennung und Angaben prüfen.';}
        }
    }
}
$auth=!empty($_SESSION['auth']);if($auth)$_SESSION['last']=time();
$csrf=cd_h($_SESSION['csrf']);$ok=$_SESSION['flash']??'';unset($_SESSION['flash']);
function field(array $c,bool $new,string $csrf):void{
    $modules=json_decode($c['modules']??'["welcome"]',true)?:['welcome'];
    $labels=['welcome'=>'Willkommen','weather'=>'Wetter','calendar'=>'Termine','teams'=>'Mannschaften','news'=>'Vereinsinfos','partner'=>'Sponsoren','restaurant'=>'Gastronomie','courts'=>'Buchung / Plätze'];
    ?>
    <form method="post" class="profile">
      <input type="hidden" name="csrf" value="<?=$csrf?>"><input type="hidden" name="action" value="<?=$new?'create':'save'?>">
      <label>Vereinskennung <input name="slug" required pattern="[a-z0-9][a-z0-9\-]{1,48}[a-z0-9]" value="<?=cd_h($c['slug']??'')?>" <?=$new?'':'readonly'?>></label>
      <label>Vereinsname <input name="name" required maxlength="160" value="<?=cd_h($c['name']??'')?>"></label>
      <label>Lizenz <select name="plan"><?php foreach(['INFO','INTERACTIVE'] as $v):?><option <?=$v===($c['plan']??'INFO')?'selected':''?>><?=$v?></option><?php endforeach?></select></label>
      <label>Akzentfarbe <input type="color" name="color" value="<?=cd_h($c['color']??'#21d5db')?>"></label>
      <label>Sprache <select name="language"><option value="de" <?=($c['language']??'de')==='de'?'selected':''?>>Deutsch</option><option value="en" <?=($c['language']??'de')==='en'?'selected':''?>>English</option></select></label>
      <label>Folienwechsel (Sekunden) <input type="number" name="dwell" min="5" max="120" required value="<?=cd_h((string)($c['dwell']??12))?>"></label>
      <fieldset><legend>Module</legend><?php foreach($labels as $k=>$v):?><label class="check"><input type="checkbox" name="modules[]" value="<?=$k?>" <?=in_array($k,$modules,true)?'checked':''?>><?=cd_h($v)?></label><?php endforeach?></fieldset>
      <button><?=$new?'Verein anlegen':'Änderungen speichern'?></button>
    </form><?php
}
function displayField(array $c,array $d,bool $new,string $csrf):void{
    $allowed=json_decode($c['modules'],true)?:[];$active=json_decode($d['modules']??'["welcome"]',true)?:[];
    $labels=['welcome'=>'Willkommen','weather'=>'Wetter','calendar'=>'Termine','teams'=>'Mannschaften','news'=>'Vereinsinfos','partner'=>'Sponsoren','restaurant'=>'Gastronomie','courts'=>'Buchung / Plätze'];
    ?><form method="post" class="profile">
    <input type="hidden" name="csrf" value="<?=$csrf?>"><input type="hidden" name="action" value="<?=$new?'display-create':'display-save'?>"><input type="hidden" name="club" value="<?=cd_h($c['slug'])?>">
    <label>Bildschirmkennung <input name="slug" required pattern="[a-z0-9][a-z0-9\-]{1,48}[a-z0-9]" value="<?=cd_h($d['slug']??'')?>" <?=$new?'':'readonly'?>></label>
    <label>Bezeichnung / Standort <input name="name" required maxlength="160" value="<?=cd_h($d['name']??'')?>"></label>
    <label>Betriebsart <select name="mode"><?php foreach($c['plan']==='INFO'?['INFO']:['INFO','INTERACTIVE'] as $m):?><option <?=$m===($d['mode']??'INFO')?'selected':''?>><?=$m?></option><?php endforeach?></select></label>
    <label>Sprache <select name="language"><?php foreach(['de'=>'Deutsch','en'=>'English'] as $k=>$v):?><option value="<?=$k?>" <?=$k===($d['language']??$c['language'])?'selected':''?>><?=$v?></option><?php endforeach?></select></label>
    <label>Folienwechsel (Sekunden) <input type="number" name="dwell" min="5" max="120" required value="<?=cd_h((string)($d['dwell']??$c['dwell']))?>"></label>
    <label class="check"><input type="checkbox" name="enabled" <?=($d['enabled']??1)?'checked':''?>> Profil aktiviert</label>
    <fieldset><legend>Bildschirminhalte</legend><?php foreach($allowed as $k):?><label class="check"><input type="checkbox" name="modules[]" value="<?=cd_h($k)?>" <?=in_array($k,$active,true)?'checked':''?>><?=cd_h($labels[$k]??$k)?></label><?php endforeach?></fieldset>
    <button><?=$new?'Bildschirm anlegen':'Bildschirm speichern'?></button></form><?php
}
function accountField(array $c,?array $a,string $csrf):void{
    ?><form method="post" class="profile"><input type="hidden" name="csrf" value="<?=$csrf?>"><input type="hidden" name="action" value="account"><input type="hidden" name="club" value="<?=cd_h($c['slug'])?>">
    <p>Vereinskennung: <strong><?=cd_h($c['slug'])?></strong> · <?=!empty($a['enabled'])?'Zugang aktiv':'Zugang gesperrt / nicht eingerichtet'?></p>
    <label>Neues Vereinspasswort <input type="password" name="new_password" autocomplete="new-password" minlength="16" maxlength="72" <?=$a?'':'required'?> placeholder="<?=$a?'Leer lassen zum Beibehalten':'Mindestens 16 Zeichen'?>"></label>
    <label class="check"><input type="checkbox" name="enabled" <?=!empty($a['enabled'])?'checked':''?>> Vereinszugang freigeben</label><button>Zugang speichern</button></form><?php
}
?><!doctype html><html lang="de"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>ClubDisplay Administration</title><link rel="stylesheet" href="admin.css"></head><body><header><img src="../assets/clubdisplay.png" alt="TENNILO ClubDisplay"><strong>ADMINISTRATION</strong></header><main>
<?php if($error):?><p class="error" role="alert"><?=cd_h($error)?></p><?php endif?>
<?php if($ok):?><p class="ok" role="status"><?=cd_h($ok)?></p><?php endif?>
<?php if(!$auth):?><section class="login"><h1>Administrator-Anmeldung</h1><form method="post"><input type="hidden" name="csrf" value="<?=$csrf?>"><input type="hidden" name="action" value="login"><label>Vereinskennung (für TENNILO leer lassen) <input name="club_login" autocomplete="username"></label><label>Passwort <input type="password" name="password" autocomplete="current-password" required></label><button>Anmelden</button></form></section>
<?php else:$clubs=$scope===null?$db->query('SELECT * FROM clubs ORDER BY name')->fetchAll():[cd_club($db,$scope)];?>
<div class="head"><div><h1><?=$scope===null?'TENNILO · Kundenverwaltung':'Vereinsbackend'?></h1><p><?=count($clubs)?> Vereinsprofile · Konfigurationsentwürfe</p></div><form method="post"><input type="hidden" name="csrf" value="<?=$csrf?>"><input type="hidden" name="action" value="logout"><button>Abmelden</button></form></div>
<p class="note">Diese Profile sind noch nicht mit den laufenden Displays verbunden. Änderungen beeinflussen weder den NTC-Piloten noch die öffentliche Demo. Es werden keine realen Buchungsdaten gespeichert.</p>
<?php if($scope===null):?><details class="panel"><summary>+ Neuen Verein anlegen</summary><?php field([],true,$csrf)?></details><?php endif?>
<?php foreach($clubs as $club):$displays=cd_displays($db,$club['slug']);?><details class="panel" <?=$scope!==null?'open':''?>><summary><?=cd_h($club['name'])?> <small><?=cd_h($club['plan'])?> · <?=count($displays)?> Bildschirme</small></summary>
<?php if($scope===null):?><details class="subpanel"><summary>Vereinsprofil & Lizenz</summary><?php field($club,false,$csrf)?></details><details class="subpanel"><summary>Vereinszugang</summary><?php accountField($club,cd_account_row($db,$club['slug']),$csrf)?></details><?php endif?>
<h2>Bildschirme</h2><p>Jeder Bildschirm erhält eigene Module, Betriebsart, Sprache und Wechselzeit. Noch keine Verbindung zum Live-Gerät.</p>
<details class="subpanel"><summary>+ Bildschirm anlegen</summary><?php displayField($club,[],true,$csrf)?></details>
<?php foreach($displays as $d):?><details class="subpanel"><summary><?=cd_h($d['name'])?> <small><?=cd_h($d['mode'])?> · <?=$d['enabled']?'Aktiv':'Deaktiviert'?> · Entwurf</small></summary><?php displayField($club,$d,false,$csrf)?></details><?php endforeach?>
</details><?php endforeach?>
<?php endif?></main></body></html>
