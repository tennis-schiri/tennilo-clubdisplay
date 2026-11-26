<?php
declare(strict_types=1);
ini_set('display_errors','0');
header('Cache-Control: no-store, private');
header('X-Robots-Tag: noindex, nofollow');
header('X-Content-Type-Options: nosniff');
// Origin-only referrers keep token paths private and preserve same-origin form POSTs.
header('Referrer-Policy: strict-origin');
header("Content-Security-Policy: default-src 'none'; style-src 'self'; img-src 'self'; form-action 'self'; frame-ancestors 'none'; base-uri 'none'");
if(($_SERVER['HTTP_HOST']??'')!=='clubdisplay.tennilo.de'||(empty($_SERVER['HTTPS'])||$_SERVER['HTTPS']==='off')){http_response_code(404);exit;}
require __DIR__.'/lib.php';
$hash='__CD_ADMIN_PASSWORD_HASH__';
$storage=dirname(__DIR__).'/clubdisplay-admin';
$guardToken='__CD_ADMIN_GUARD_TOKEN__';
function cd_unavailable(string $code):never {http_response_code(503);header('Content-Type: text/plain; charset=utf-8');exit('CD_ADMIN_'.$code);}
if(!str_starts_with($hash,'$2'))cd_unavailable('HASH');
if(!is_dir($storage))cd_unavailable('STORAGE_DIRECTORY');
if(!is_writable($storage))cd_unavailable('STORAGE_PERMISSIONS');
$dir=realpath($storage);$root=realpath($_SERVER['DOCUMENT_ROOT']??'');
if(!$dir||!$root||$dir!==realpath(dirname(__DIR__)).DIRECTORY_SEPARATOR.'clubdisplay-admin'||$dir!==$root.DIRECTORY_SEPARATOR.'clubdisplay-admin')cd_unavailable('STORAGE_NOT_PRIVATE');
if(@file_get_contents($dir.'/.htaccess')!=="Require all denied\n")cd_unavailable('STORAGE_GUARD');
// Every deployment has its own verified marker. Never invalidate the active release
// by replacing its marker before the new index.php has reached the server.
if(!preg_match('/^[a-f0-9]{64}$/D',$guardToken))cd_unavailable('STORAGE_GUARD');
$guardPath=$dir.'/.deployment-verified-'.hash('sha256',$guardToken);
if(is_link($guardPath)||!hash_equals($guardToken,(string)@file_get_contents($guardPath)))cd_unavailable('STORAGE_GUARD');
if(is_link($dir.'/clubdisplay-admin.sqlite'))cd_unavailable('STORAGE_NOT_PRIVATE');
if(!class_exists('PDO')||!in_array('sqlite',PDO::getAvailableDrivers(),true))cd_unavailable('SQLITE_DRIVER');
umask(0077);
try{$db=cd_db($dir.'/clubdisplay-admin.sqlite');}catch(Throwable $e){cd_unavailable('DATABASE_INIT');}
if(isset($_GET['partner-maintenance'])){
    // HMAC-derived dedicated key, supplied only in a header by the scheduled workflow.
    $key='__CD_PARTNER_MAINTENANCE_KEY__';
    if(($_SERVER['REQUEST_METHOD']??'')!=='POST'||!preg_match('/^[a-f0-9]{64}$/D',$key)||!hash_equals($key,(string)($_SERVER['HTTP_X_CD_MAINTENANCE']??''))){http_response_code(403);exit;}
    try{cd_partner_cleanup($db);$count=cd_partner_outbox($db);header('Content-Type: application/json');echo json_encode(['ok'=>true,'sent'=>$count]);}catch(Throwable $e){http_response_code(503);echo '{"ok":false}';}exit;
}
if(isset($_GET['partner']))cd_partner_public($db);
if(isset($_GET['matchball-display-pdf']))cd_matchball_public_pdf($db);
if(isset($_GET['matchball']))cd_matchball_feed($db);
// Device traffic never uses an administrator session. The key travels in a POST body.
if(isset($_GET['device'])){
    header('Content-Type: application/json; charset=utf-8');
    if($_SERVER['REQUEST_METHOD']!=='POST'||!str_starts_with($_SERVER['CONTENT_TYPE']??'','application/json')){http_response_code(405);echo '{"error":"method"}';exit;}
    $raw=file_get_contents('php://input',false,null,0,4097);
    if(!is_string($raw)||strlen($raw)>4096){http_response_code(413);exit;}
    $input=json_decode($raw,true);$a=cd_device_auth($db,is_array($input)?($input['token']??null):null);
    if(!$a){http_response_code(403);echo '{"error":"access"}';exit;}
    try{
        if(($input['action']??'')==='image'){
            $image=cd_snapshot_image($db,$a['club'],$a['display'],$a['club'],$input['id']??null);
            if($image===null){http_response_code(404);exit;}
            header('Content-Type: image/png');echo $image;exit;
        }
        if(($input['action']??'')!=='snapshot'){http_response_code(400);exit;}
        $snapshot=cd_snapshot_feeds($db,cd_snapshot($db,$a['club'],$a['display'],$a['club']),$a['club']);
        if(!cd_device_auth($db,$input['token'])){http_response_code(403);exit;}
        cd_device_seen($db,$a,is_string($input['version']??null)?$input['version']:'unknown');
        echo json_encode($snapshot,JSON_THROW_ON_ERROR|JSON_INVALID_UTF8_SUBSTITUTE);
    }catch(Throwable $e){http_response_code(503);echo '{"error":"unavailable"}';}
    exit;
}
if(isset($_GET['player'])){
    if($_SERVER['REQUEST_METHOD']!=='GET'){http_response_code(405);exit;}
    header("Content-Security-Policy: default-src 'none'; script-src 'self'; style-src 'self'; img-src 'self' blob:; connect-src 'self'; frame-ancestors 'none'; base-uri 'none'; form-action 'none'");
    require __DIR__.'/player.php';exit;
}
ini_set('session.use_strict_mode','1');
$clubPortal=isset($_GET['portal'])&&$_GET['portal']==='club';
$fixedClub=$clubPortal&&($_GET['verein']??'')==='ntc93'?'ntc93':null;
$portalUrl=$clubPortal?'./?portal=club'.($fixedClub?'&verein=ntc93':'').(isset($_GET['partner_review'])?'&partner_review=1':''):'./';
session_name($clubPortal?'CD_CLUB':'CD_ADMIN');
session_set_cookie_params(['lifetime'=>0,'path'=>'/admin/','secure'=>true,'httponly'=>true,'samesite'=>'Strict']);
session_start();
if(isset($_SESSION['last'])&&time()-$_SESSION['last']>1800){$_SESSION=[];session_regenerate_id(true);}
if(!empty($_SESSION['auth'])&&isset($_SESSION['club'])){ $account=cd_account_row($db,(string)$_SESSION['club']); if(!$account||!$account['enabled']||!hash_equals((string)($_SESSION['account_version']??''),hash('sha256',$account['password_hash']))){$_SESSION=[];session_regenerate_id(true);} }
if(!empty($_SESSION['auth'])&&!isset($_SESSION['club'])&&!hash_equals(hash('sha256',$hash),(string)($_SESSION['operator_version']??''))){$_SESSION=[];session_regenerate_id(true);}
if(!empty($_SESSION['auth'])&&($clubPortal?(!isset($_SESSION['club'])||($fixedClub!==null&&$_SESSION['club']!==$fixedClub)):isset($_SESSION['club']))){$_SESSION=[];session_regenerate_id(true);}
$_SESSION['csrf']??=bin2hex(random_bytes(32));
$scope=isset($_SESSION['club'])?(string)$_SESSION['club']:null;
$error='';$ok='';$focusClub=cd_h(is_string($_SESSION['focus_club']??null)?$_SESSION['focus_club']:'');$focusAction=is_string($_SESSION['focus_action']??null)?$_SESSION['focus_action']:'';unset($_SESSION['focus_club'],$_SESSION['focus_action']);
if($clubPortal&&isset($_GET['partner_review'])&&$scope!==null){$focusClub=$scope;$focusAction='partner-board';}
if($_SERVER['REQUEST_METHOD']==='POST'){
    if(!hash_equals($_SESSION['csrf'],(string)($_POST['csrf']??''))){http_response_code(403);exit('Ungültige Sitzung.');}
    $action=is_string($_POST['action']??null)?$_POST['action']:'';
    if(!empty($_SESSION['auth'])){$focusClub=$scope??(is_string($_POST['club']??null)?$_POST['club']:(is_string($_POST['slug']??null)?$_POST['slug']:''));$focusAction=$action;$_SESSION['focus_club']=$focusClub;$_SESSION['focus_action']=$action;}
    if($action==='login'){
        $ip=hash('sha256',$_SERVER['REMOTE_ADDR']??'unknown');$now=time();
        $db->exec('BEGIN IMMEDIATE');
        try{
            $q=$db->prepare('DELETE FROM login_attempts WHERE since < ?');$q->execute([$now-900]);
            $q=$db->prepare('SELECT count FROM login_attempts WHERE ip=?');$q->execute([$ip]);$count=(int)$q->fetchColumn();
            $allowed=$count<5;
            $clubLogin=$clubPortal?($fixedClub??trim((string)($_POST['club_login']??''))):'';
            $candidate=cd_login_hash($db,$clubLogin,$hash);
            $password=(string)($_POST['password']??'');
            $valid=$allowed&&(!$clubPortal||$clubLogin!=='')&&password_verify($password,$candidate!==''?$candidate:$hash)&&$candidate!=='';
            
            if($valid){$q=$db->prepare('DELETE FROM login_attempts WHERE ip=?');$q->execute([$ip]);}
            elseif($allowed){$q=$db->prepare('INSERT INTO login_attempts VALUES (?,1,?) ON CONFLICT(ip) DO UPDATE SET count=count+1');$q->execute([$ip,$now]);}
            $db->exec('COMMIT');
        }catch(Throwable $e){$db->exec('ROLLBACK');http_response_code(503);exit('Login momentan nicht verfügbar.');}
        if($valid){session_regenerate_id(true);$_SESSION=['auth'=>true,'last'=>$now,'csrf'=>bin2hex(random_bytes(32))];if($clubLogin!==''){$_SESSION['club']=$clubLogin;$_SESSION['account_version']=hash('sha256',$candidate);}else{$_SESSION['operator_version']=hash('sha256',$hash);}header('Location: '.$portalUrl,true,303);exit;}
        $error='Anmeldung fehlgeschlagen oder vorübergehend gesperrt.';
    }elseif(!empty($_SESSION['auth'])){
        if($action==='logout'){$_SESSION=[];session_destroy();header('Location: '.$portalUrl,true,303);exit;}
        if(in_array($action,['create','save'],true)){
            try{if($scope!==null)throw new InvalidArgumentException('Nur TENNILO darf Vereinsprofile und Lizenzen ändern.');cd_save($db,$_POST,$action==='create');cd_audit($db,$scope,$action,$_POST['slug']??'');$_SESSION['flash']='Vereinsprofil gespeichert.';header('Location: '.$portalUrl,true,303);exit;}
            catch(InvalidArgumentException $e){$error=$e->getMessage();}
            catch(Throwable $e){$error='Speichern fehlgeschlagen. Ist die Vereinskennung bereits vergeben?';}
        }
        if(in_array($action,['display-create','display-save','account','brand','content','media','dining','sources','contract','device-issue','device-revoke','backup','billing','billing-export','source-test','partner-board','matchball','community'],true)){
            try{
                if($action==='account'){if($scope!==null)throw new InvalidArgumentException('Keine Berechtigung.');cd_account($db,(string)($_POST['club']??''),(string)($_POST['new_password']??''),isset($_POST['enabled']));}
                elseif($action==='brand')cd_save_brand($db,$_POST,$_FILES['logo']??[],$scope);
                elseif($action==='partner-board'){
                    cd_partner_admin($db,$_POST,$scope);
                }
                elseif($action==='matchball')cd_matchball_save($db,$_POST,$_FILES['newsletter_pdf']??[],$scope);
                elseif($action==='community')cd_community_save($db,$_POST,$_FILES['image']??[],$scope);
                elseif($action==='content')cd_save_content($db,$_POST,$scope);
                elseif($action==='media')cd_media_save($db,$_POST,$_FILES['image']??[],$scope);
                elseif($action==='dining')cd_dining_save($db,$_POST,$scope);
                elseif($action==='sources')cd_save_sources($db,$_POST,$scope);
                elseif($action==='contract')cd_save_contract($db,$_POST,$scope);
                elseif($action==='billing')cd_save_billing($db,$_POST,$scope);
                elseif($action==='source-test'){
                    $results=cd_source_check($db,cd_text($_POST['club']??'',50,true),$scope);
                    $_SESSION['flash']=$results?implode(' · ',$results):'Keine externen Datenfeeds freigeschaltet.';
                    header('Location: '.$portalUrl,true,303);exit;
                }
                elseif($action==='billing-export'){
                    if($scope!==null)throw new InvalidArgumentException('Nur TENNILO darf Abrechnungsdaten exportieren.');
                    cd_audit($db,$scope,'billing-export');
                    header('Content-Type: text/csv; charset=utf-8');header('Content-Disposition: attachment; filename="clubdisplay-abrechnung.csv"');
                    $out=fopen('php://output','wb');fwrite($out,"\xEF\xBB\xBF");cd_billing_csv($db,$out);fclose($out);exit;
                }
                elseif($action==='device-issue'||$action==='device-revoke'){
                    $club=cd_text($_POST['club']??'',50,true);$slug=cd_text($_POST['slug']??'',50,true);
                    if($action==='device-issue'){
                        $token=cd_device_issue($db,$club,$slug,$scope);
                        $_SESSION['device_link']='https://clubdisplay.tennilo.de/admin/?player=1#'.$token;
                    }else cd_device_revoke($db,$club,$slug,$scope);
                }
                elseif($action==='backup'){
                    if($scope!==null)throw new InvalidArgumentException('Nur TENNILO darf Sicherungen erstellen.');
                    cd_audit($db,$scope,'backup');
                    $file=$dir.'/backup-'.bin2hex(random_bytes(16)).'.sqlite';
                    try{
                        $db->exec('VACUUM INTO '.$db->quote($file));
                        header('Content-Type: application/octet-stream');header('Content-Disposition: attachment; filename="clubdisplay-backup-'.gmdate('Y-m-d-His').'.sqlite"');header('Content-Length: '.filesize($file));
                        readfile($file);
                    }finally{if(is_file($file))@unlink($file);}
                    exit;
                }
                else cd_save_display($db,$_POST,$action==='display-create',$scope);
                cd_audit($db,$scope,$action,$_POST['club']??'',$_POST['slug']??$_POST['id']??'');
                $_SESSION['flash']='Änderungen gespeichert.';header('Location: '.$portalUrl,true,303);exit;
            }catch(InvalidArgumentException $e){$error=$e->getMessage();}
            catch(Throwable $e){$error='Speichern fehlgeschlagen. Kennung und Angaben prüfen.';}
        }
    }
}
$auth=!empty($_SESSION['auth']);if($auth){$_SESSION['last']=time();cd_partner_cleanup($db);}
if(isset($_GET['preview'])||isset($_GET['preview-data'])){
    if(!$auth){http_response_code(403);exit;}
    try{
        $club=cd_text($_GET['club']??'',50,true);$slug=cd_text($_GET['screen']??'',50,true);cd_display($db,$club,$slug,$scope);
        if(isset($_GET['preview-data'])){
            if(isset($_GET['image'])){
                $image=cd_snapshot_image($db,$club,$slug,$scope,$_GET['image']);
                if($image===null){http_response_code(404);exit;}
                header('Content-Type: image/png');echo $image;exit;
            }
            header('Content-Type: application/json; charset=utf-8');
            session_write_close();echo json_encode(cd_snapshot_feeds($db,cd_snapshot($db,$club,$slug,$scope),$club),JSON_THROW_ON_ERROR|JSON_INVALID_UTF8_SUBSTITUTE);exit;
        }
        $previewUrl=$portalUrl.($clubPortal?'&':'?').'preview-data=1&club='.rawurlencode($club).'&screen='.rawurlencode($slug);
        header("Content-Security-Policy: default-src 'none'; script-src 'self'; style-src 'self'; img-src 'self' blob:; connect-src 'self'; frame-ancestors 'none'; base-uri 'none'; form-action 'none'");
        require __DIR__.'/player.php';exit;
    }catch(Throwable $e){http_response_code(404);exit;}
}
if(isset($_GET['matchball_pdf'])){
    if(!$auth){http_response_code(404);exit;}
    try{cd_matchball_download($db,cd_text($_GET['club']??'',50,true),cd_matchball_month($_GET['matchball_pdf']),$scope);}catch(Throwable $e){http_response_code(404);exit;}
}
if(isset($_GET['media'])){
    if(!$auth){http_response_code(404);exit;}
    try{$club=cd_text($_GET['club']??'',50,true);$id=filter_var($_GET['media'],FILTER_VALIDATE_INT);if(!$id||$id<1)throw new RuntimeException('Bild fehlt.');$png=cd_media_get($db,$club,$id,$scope);if($png===null)throw new RuntimeException('Bild fehlt.');header('Content-Type: image/png');echo $png;exit;}
    catch(Throwable $e){http_response_code(404);exit;}
}
if(isset($_GET['logo'])){
    if(!$auth){http_response_code(404);exit;}
    try{$club=cd_text($_GET['logo'],50,true);cd_scope($scope,$club);$q=$db->prepare('SELECT logo FROM club_brand WHERE club=?');$q->execute([$club]);$logo=$q->fetchColumn();if(!is_string($logo)||$logo==='')throw new RuntimeException('Logo fehlt.');header('Content-Type: image/png');echo $logo;exit;}
    catch(Throwable $e){http_response_code(404);exit;}
}
$csrf=cd_h($_SESSION['csrf']);$ok=$_SESSION['flash']??'';unset($_SESSION['flash']);
function field(array $c,bool $new,string $csrf):void{
    $modules=json_decode($c['modules']??'["welcome"]',true)?:['welcome'];
    $labels=['welcome'=>'Willkommen','weather'=>'Wetter','calendar'=>'Termine','teams'=>'Mannschaften','news'=>'Vereinsinfos','partner'=>'Sponsoren','restaurant'=>'Gastronomie','courts'=>'Buchung / Plätze','partner_search'=>'Spielpartnerbörse'];
    ?>
    <form method="post" class="profile">
      <input type="hidden" name="csrf" value="<?=$csrf?>"><input type="hidden" name="action" value="<?=$new?'create':'save'?>">
      <label>Vereinskennung <input name="slug" required pattern="[a-z0-9][a-z0-9\-]{1,48}[a-z0-9]" value="<?=cd_h($c['slug']??'')?>" <?=$new?'':'readonly'?>></label>
      <label>Vereinsname <input name="name" required maxlength="160" value="<?=cd_h($c['name']??'')?>"></label>
      <label>Lizenz <select name="plan"><?php foreach(['INFO','INTERACTIVE'] as $v):?><option value="<?=cd_h($v)?>" <?=$v===($c['plan']??'INFO')?'selected':''?>><?=cd_h(cd_plan_label($v))?></option><?php endforeach?></select></label>
      <label>Akzentfarbe <input type="color" name="color" value="<?=cd_h($c['color']??'#21d5db')?>"></label>
      <label>Sprache <select name="language"><option value="de" <?=($c['language']??'de')==='de'?'selected':''?>>Deutsch</option><option value="en" <?=($c['language']??'de')==='en'?'selected':''?>>English</option></select></label>
      <label>Folienwechsel (Sekunden) <input type="number" name="dwell" min="5" max="120" required value="<?=cd_h((string)($c['dwell']??12))?>"></label>
      <fieldset><legend>Module</legend><?php foreach($labels as $k=>$v):?><label class="check"><input type="checkbox" name="modules[]" value="<?=$k?>" <?=in_array($k,$modules,true)?'checked':''?>><?=cd_h($v)?></label><?php endforeach?></fieldset>
      <button><?=$new?'Verein anlegen':'Änderungen speichern'?></button>
    </form><?php
}
function displayField(PDO $db,array $c,array $d,bool $new,string $csrf):void{
    $allowed=json_decode($c['modules'],true)?:[];$active=json_decode($d['modules']??'["welcome"]',true)?:[];
    $layout=$new?['welcome'=>'','order'=>[],'dwell'=>[]]:cd_layout($db,$c['slug'],$d['slug']);
    $ordered=array_values(array_intersect($layout['order'],$allowed));foreach($allowed as $k)if(!in_array($k,$ordered,true))$ordered[]=$k;
    $labels=['welcome'=>'Willkommen','weather'=>'Wetter','calendar'=>'Termine','teams'=>'Mannschaften','news'=>'Vereinsinfos','partner'=>'Sponsoren','restaurant'=>'Gastronomie','courts'=>'Buchung / Plätze','partner_search'=>'Spielpartnerbörse'];
    ?><form method="post" class="profile">
    <input type="hidden" name="csrf" value="<?=$csrf?>"><input type="hidden" name="action" value="<?=$new?'display-create':'display-save'?>"><input type="hidden" name="club" value="<?=cd_h($c['slug'])?>">
    <label>Bildschirmkennung <input name="slug" required pattern="[a-z0-9][a-z0-9\-]{1,48}[a-z0-9]" value="<?=cd_h($d['slug']??'')?>" <?=$new?'':'readonly'?>></label>
    <label>Bezeichnung / Standort <input name="name" required maxlength="160" value="<?=cd_h($d['name']??'')?>"></label>
    <label>Betriebsart <select name="mode"><?php foreach($c['plan']==='INFO'?['INFO']:['INFO','INTERACTIVE'] as $m):?><option value="<?=cd_h($m)?>" <?=$m===($d['mode']??'INFO')?'selected':''?>><?=cd_h(cd_plan_label($m))?></option><?php endforeach?></select></label>
    <label>Sprache <select name="language"><?php foreach(['de'=>'Deutsch','en'=>'English'] as $k=>$v):?><option value="<?=$k?>" <?=$k===($d['language']??$c['language'])?'selected':''?>><?=$v?></option><?php endforeach?></select></label>
    <label>Folienwechsel (Sekunden) <input type="number" name="dwell" min="5" max="120" required value="<?=cd_h((string)($d['dwell']??$c['dwell']))?>"></label>
    <label class="check"><input type="checkbox" name="enabled" <?=($d['enabled']??1)?'checked':''?>> Profil aktiviert</label>
    <label class="full">Eigene Begrüßung für diesen Bildschirm <textarea name="screen_welcome" rows="3" maxlength="1000" placeholder="Leer = gemeinsame Vereinsbegrüßung"><?=cd_h($layout['welcome'])?></textarea></label>
    <fieldset class="screen-layout"><legend>Bildschirminhalte &amp; Folienablauf</legend>
    <p>Nur ausgewählte Module werden angezeigt. Kleinere Positionsnummern erscheinen zuerst. Eine leere Anzeigezeit übernimmt den Standardwert des Bildschirms. Willkommen ist verpflichtend; die Buchung ist nur mit PLUS verfügbar.</p>
    <div class="screen-layout-head"><span>Modul</span><span>Position</span><span>Sekunden / Folie</span></div>
    <?php foreach($ordered as $pos=>$k):?><div class="screen-layout-row"><label class="check"><input type="checkbox" name="modules[]" value="<?=cd_h($k)?>" <?=in_array($k,$active,true)?'checked':''?>><?=cd_h($labels[$k]??$k)?></label><label><span class="screen-label-mobile">Position</span><input type="number" name="module_rank[<?=cd_h($k)?>]" aria-label="<?=cd_h($labels[$k]??$k)?>: Position" min="1" max="100" value="<?=$pos+1?>" required></label><label><span class="screen-label-mobile">Sekunden / Folie</span><input type="number" name="module_dwell[<?=cd_h($k)?>]" aria-label="<?=cd_h($labels[$k]??$k)?>: Sekunden pro Folie" min="5" max="120" placeholder="Standard" value="<?=cd_h((string)($layout['dwell'][$k]??''))?>"></label></div><?php endforeach?></fieldset>
    <button><?=$new?'Bildschirm anlegen':'Bildschirm speichern'?></button></form><?php
}
function accountField(array $c,?array $a,string $csrf):void{
    ?><form method="post" class="profile"><input type="hidden" name="csrf" value="<?=$csrf?>"><input type="hidden" name="action" value="account"><input type="hidden" name="club" value="<?=cd_h($c['slug'])?>">
    <p>Vereinskennung: <strong><?=cd_h($c['slug'])?></strong> · <?=!empty($a['enabled'])?'Zugang aktiv':'Zugang gesperrt / nicht eingerichtet'?></p>
    <label>Neues Vereinspasswort <input type="password" name="new_password" autocomplete="new-password" minlength="16" maxlength="72" <?=$a?'':'required'?> placeholder="<?=$a?'Leer lassen zum Beibehalten':'Mindestens 16 Zeichen'?>"></label>
    <label class="check"><input type="checkbox" name="enabled" <?=!empty($a['enabled'])?'checked':''?>> Vereinszugang freigeben</label><button>Zugang speichern</button></form><?php
}
function brandField(array $c,array $b,string $csrf):void{ global $clubPortal,$fixedClub;
    ?><form method="post" enctype="multipart/form-data" class="profile">
    <input type="hidden" name="csrf" value="<?=$csrf?>"><input type="hidden" name="action" value="brand"><input type="hidden" name="club" value="<?=cd_h($c['slug'])?>">
    <?php if($b['logo_size']):?><img class="brand-preview" src="?<?=$clubPortal?'portal=club&amp;'.($fixedClub?'verein=ntc93&amp;':''):''?>logo=<?=rawurlencode($c['slug'])?>" alt="Aktuelles Vereinslogo"><?php endif?>
    <label>Vereinslogo (PNG, JPEG, WebP; max. 2 MB)<input type="file" name="logo" accept="image/png,image/jpeg,image/webp"></label>
    <label class="check"><input type="checkbox" name="remove_logo"> Logo entfernen</label>
    <label>Akzentfarbe<input type="color" name="color" value="<?=cd_h($c['color'])?>"></label>
    <label class="full">Begrüßungstext<textarea name="welcome" maxlength="1000" rows="3"><?=cd_h($b['welcome'])?></textarea></label>
    <button>Design speichern</button></form><?php
}
function mediaField(array $c,array $row,string $csrf):void{ global $clubPortal,$fixedClub;
    ?><form method="post" enctype="multipart/form-data" class="profile media-form">
    <input type="hidden" name="csrf" value="<?=$csrf?>"><input type="hidden" name="action" value="media"><input type="hidden" name="club" value="<?=cd_h($c['slug'])?>"><input type="hidden" name="id" value="<?=(int)$row['id']?>">
    <?php if($row['has_image']):?><img class="media-preview" src="?<?=$clubPortal?'portal=club&amp;'.($fixedClub?'verein=ntc93&amp;':''):''?>media=<?=(int)$row['id']?>&amp;club=<?=rawurlencode($c['slug'])?>" alt="Bild zu <?=cd_h($row['title'])?>" loading="lazy"><?php endif?>
    <label class="full">Bild oder Logo (PNG, JPEG, WebP; max. 2 MB)<input type="file" name="image" accept="image/png,image/jpeg,image/webp" required></label>
    <button name="intent" value="save"><?=$row['has_image']?'Bild ersetzen':'Bild hochladen'?></button>
    <?php if($row['has_image']):?><button class="danger" name="intent" value="remove" formnovalidate>Bild entfernen</button><?php endif?>
    </form><?php
}
function sourceField(array $c,array $rows,string $csrf,?string $scope):void{
    $modules=json_decode($c['modules'],true)?:[];
    ?><form method="post" class="profile"><input type="hidden" name="csrf" value="<?=$csrf?>"><input type="hidden" name="action" value="sources"><input type="hidden" name="club" value="<?=cd_h($c['slug'])?>">
    <p class="full">Externe Quellen erst nach Freigabe durch TENNILO. Keine Zugangsdaten oder privaten Kalenderlinks eintragen. Manuelle und zur Prüfung eingereichte Referenzen werden nicht automatisch abgerufen. Öffentliche Feeds und Terminallinks sind separat konfigurierbar.</p>
    <?php foreach(CD_SOURCE_MODULES as $kind=>$label):if(!in_array($kind,$modules,true))continue;$row=$rows[$kind]??[];$options=['manual'=>'Manuell / keine Schnittstelle','pending'=>'Prüfung erforderlich'];$external=CD_PROVIDER_OPTIONS[$kind]??[];if($c['slug']==='ntc93')$external[$kind==='courts'?'ntc-booking':'ntc-feed']=$kind==='courts'?'NTC · vorhandenes Terminal':'NTC · vorhandener Feed';foreach($external as $k=>$v)if($scope===null||($row['provider']??'')===$k)$options[$k]=$v;?>
    <label><?=cd_h($label)?><select name="sources[<?=$kind?>][provider]"><?php foreach($options as $k=>$v):?><option value="<?=$k?>" <?=($row['provider']??'manual')===$k?'selected':''?>><?=cd_h($v)?></option><?php endforeach?></select></label>
    <label>Referenz (URL oder Wetterkoordinaten)<input name="sources[<?=$kind?>][reference]" maxlength="500" value="<?=cd_h($row['reference']??'')?>"></label><?php endforeach?>
    <button>Quellen speichern</button></form><?php
}
function billingField(array $c,array $b,string $csrf):void{
    ?><form method="post" class="profile"><input type="hidden" name="csrf" value="<?=$csrf?>"><input type="hidden" name="action" value="billing"><input type="hidden" name="club" value="<?=cd_h($c['slug'])?>">
    <label>Vereinbarter Betrag je Abrechnungsintervall (EUR)<input name="recurring" inputmode="decimal" value="<?=cd_h(cd_amount_text($b['recurring_cents']===null?null:(int)$b['recurring_cents']))?>"></label>
    <label>Einrichtung einmalig (EUR)<input name="setup" inputmode="decimal" value="<?=cd_h(cd_amount_text($b['setup_cents']===null?null:(int)$b['setup_cents']))?>"></label>
    <label>Preisbasis<select name="basis"><?php foreach(['unspecified'=>'Nicht festgelegt','net'=>'Netto','gross'=>'Brutto'] as $k=>$v):?><option value="<?=$k?>" <?=$b['basis']===$k?'selected':''?>><?=$v?></option><?php endforeach?></select></label>
    <label>Rechnungsreferenz<input name="invoice_ref" maxlength="160" value="<?=cd_h($b['invoice_ref'])?>"></label>
    <label>Zahlungsstatus<select name="payment_state"><?php foreach(['none'=>'Keine Abrechnung','open'=>'Offen','paid'=>'Bezahlt','waived'=>'Erlassen'] as $k=>$v):?><option value="<?=$k?>" <?=$b['payment_state']===$k?'selected':''?>><?=$v?></option><?php endforeach?></select></label>
    <p class="full">Interne Abrechnungsübersicht. Rechnungen werden nicht automatisch erstellt oder verschickt; es erfolgt kein Zahlungsverkehr.</p><button>Abrechnung speichern</button></form><?php
}
function auditField(PDO $db,?string $scope):void{
    ?><div class="audit-list"><?php foreach(cd_audit_rows($db,$scope) as $a):?><div class="audit-row"><time><?=cd_h($a['ts'])?></time><span><?=cd_h($a['role'])?> · <?=cd_h($a['club'])?></span><strong><?=cd_h($a['action'])?> <?=cd_h($a['target'])?></strong></div><?php endforeach?></div><?php
}
function contractField(array $c,array $a,string $csrf):void{
    ?><form method="post" class="profile"><input type="hidden" name="csrf" value="<?=$csrf?>"><input type="hidden" name="action" value="contract"><input type="hidden" name="club" value="<?=cd_h($c['slug'])?>">
    <label>Freigeschaltete Geräte<input type="number" name="screen_limit" min="1" max="100" required value="<?=(int)$a['screen_limit']?>"></label>
    <label>Vertragsstatus<select name="state"><?php foreach(['pilot'=>'Pilot','active'=>'Aktiv','paused'=>'Gesperrt'] as $k=>$v):?><option value="<?=$k?>" <?=$a['state']===$k?'selected':''?>><?=$v?></option><?php endforeach?></select></label>
    <label>Abrechnungsintervall<select name="cycle"><?php foreach(['none'=>'Keine / Pilot','monthly'=>'Monatlich','yearly'=>'Jährlich'] as $k=>$v):?><option value="<?=$k?>" <?=$a['cycle']===$k?'selected':''?>><?=$v?></option><?php endforeach?></select></label>
    <label>Nächste Verlängerung<input type="date" name="renewal" value="<?=cd_h($a['renewal'])?>"></label>
    <label class="full">Interne Notiz<textarea name="note" maxlength="1000" rows="3"><?=cd_h($a['note'])?></textarea></label><p class="full">Reine Vertragsverwaltung; keine Rechnungsstellung, kein Zahlungsverkehr. Sperre stoppt neue Geräteabrufe.</p><button>Vertrag speichern</button></form><?php
}
function deviceField(PDO $db,array $c,array $d,string $csrf,string $portalUrl):void{
    $a=cd_device_status($db,$c['slug'],$d['slug']);$seen=$a['last_seen']??null;$blocked=!$d['enabled']||cd_contract($db,$c['slug'])['state']==='paused';$online=$seen&&time()-(int)$seen<=180;
    $url=$portalUrl.(str_contains($portalUrl,'?')?'&':'?').'preview=1&club='.rawurlencode($c['slug']).'&screen='.rawurlencode($d['slug']);
    ?><div class="device-panel"><p><strong><?=$blocked?'Gesperrt':($a?($online?'Online':'Offline / noch nicht verbunden'):'Nicht gekoppelt')?></strong><?php if($seen):?> · Letzter Kontakt <?=cd_h((new DateTimeImmutable('@'.$seen))->setTimezone(new DateTimeZone('Europe/Berlin'))->format('d.m.Y H:i:s'))?> · Version <?=cd_h($a['version'])?><?php endif?></p>
    <a class="admin-action" href="<?=cd_h($url)?>" target="_blank" rel="noopener noreferrer">Bildschirmvorschau öffnen</a>
    <form method="post" class="profile"><input type="hidden" name="csrf" value="<?=$csrf?>"><input type="hidden" name="club" value="<?=cd_h($c['slug'])?>"><input type="hidden" name="slug" value="<?=cd_h($d['slug'])?>">
    <button name="action" value="device-issue" <?=$blocked?'disabled':''?>><?=$a?'Neuen Geräteschlüssel erzeugen':'Gerät koppeln'?></button><?php if($a):?><button class="danger" name="action" value="device-revoke">Gerätezugang widerrufen</button><?php endif?></form>
    <p>Ein neuer Schlüssel ersetzt den alten. Die angezeigte Geräteadresse ist vertraulich und darf nur am vorgesehenen Gerät hinterlegt werden.</p></div><?php
}
function diningHours(string $prefix,array $row):void{
    $state=$row['state']??'unknown';$slots=$row['slots']??[];
    ?><label>Status<select name="<?=cd_h($prefix)?>[state]"><?php foreach(['unknown'=>'Keine Angabe','closed'=>'Geschlossen','open'=>'Geöffnet'] as $k=>$v):?><option value="<?=$k?>" <?=$state===$k?'selected':''?>><?=$v?></option><?php endforeach?></select></label>
    <?php foreach([1,2] as $i):?><label>Von <?=$i?><input type="time" name="<?=cd_h($prefix)?>[from<?=$i?>]" value="<?=cd_h($slots[$i-1]['from']??'')?>"></label><label>Bis <?=$i?><input type="time" name="<?=cd_h($prefix)?>[to<?=$i?>]" value="<?=cd_h($slots[$i-1]['to']??'')?>"></label><?php endforeach;
}
function diningField(array $club,array $d,string $csrf):void{
    ?><form method="post" class="profile dining-form"><input type="hidden" name="csrf" value="<?=$csrf?>"><input type="hidden" name="action" value="dining"><input type="hidden" name="club" value="<?=cd_h($club['slug'])?>">
    <h3 class="full">Kontaktdaten und Reservierung</h3>
    <?php foreach(['name'=>'Name der Gastronomie','phone'=>'Telefon','website'=>'Website (HTTPS)','reservation_url'=>'Online-Reservierung (HTTPS)'] as $k=>$label):?><label><?=cd_h($label)?><input name="<?=$k?>" type="<?=in_array($k,['website','reservation_url'])?'url':($k==='phone'?'tel':'text')?>" value="<?=cd_h($d[$k]??'')?>"></label><?php endforeach?>
    <label class="check"><input type="checkbox" name="reservation_onsite" <?=!empty($d['reservation_onsite'])?'checked':''?>> Reservierung vor Ort</label><label class="check"><input type="checkbox" name="reservation_phone" <?=!empty($d['reservation_phone'])?'checked':''?>> Telefonische Reservierung</label>
    <label class="full">Allgemeine Hinweise<textarea name="note" maxlength="1500" rows="3"><?=cd_h($d['note']??'')?></textarea></label>
    <h3 class="full">Reguläre Öffnungszeiten</h3><p class="full">Zwei Zeitfenster pro Tag möglich. Schließzeit nach Mitternacht durch eine frühere Uhrzeit angeben.</p>
    <?php foreach(CD_DAYS as $i=>$day):?><div class="full dining-row"><strong><?=cd_h($day)?></strong><?php diningHours("hours[$i]",$d['hours'][$i]??[])?></div><?php endforeach?>
    <h3 class="full">Sonderöffnungszeiten</h3><p class="full">Leere Datumsfelder werden nicht gespeichert. Ein Datum überschreibt später die regulären Zeiten.</p>
    <?php $special=$d['special']??[];$special=array_pad($special,min(40,count($special)+3),[]);foreach($special as $i=>$row):?><div class="full dining-row"><label>Datum<input type="date" name="special[<?=$i?>][date]" value="<?=cd_h($row['date']??'')?>"></label><?php diningHours("special[$i]",$row)?><label class="full">Hinweis<input name="special[<?=$i?>][note]" maxlength="300" value="<?=cd_h($row['note']??'')?>"></label></div><?php endforeach?>
    <h3 class="full">Wochenangebote</h3><p class="full">Zum Löschen Titel leeren. Weitere Felder erscheinen nach dem Speichern. Bilder können separat in den redaktionellen Gastronomieeinträgen hinterlegt werden.</p>
    <?php $offers=$d['offers']??[];$offers=array_pad($offers,min(40,count($offers)+3),[]);foreach($offers as $i=>$row):?><div class="full dining-offer"><label>Wochentag<select name="offers[<?=$i?>][day]"><?php foreach(CD_DAYS as $k=>$day):?><option value="<?=$k?>" <?=$k===($row['day']??0)?'selected':''?>><?=cd_h($day)?></option><?php endforeach?></select></label><label>Gericht / Aktion<input name="offers[<?=$i?>][title]" maxlength="160" value="<?=cd_h($row['title']??'')?>"></label><label>Preis<input name="offers[<?=$i?>][price]" maxlength="80" value="<?=cd_h($row['price']??'')?>"></label><label>Gültig ab<input type="date" name="offers[<?=$i?>][starts]" value="<?=cd_h($row['starts']??'')?>"></label><label>Gültig bis<input type="date" name="offers[<?=$i?>][ends]" value="<?=cd_h($row['ends']??'')?>"></label><label>Hinweis<input name="offers[<?=$i?>][note]" maxlength="300" value="<?=cd_h($row['note']??'')?>"></label></div><?php endforeach?>
    <button class="full">Gastronomiedaten speichern</button></form><?php
}
function contentField(PDO $db,array $c,string $kind,array $row,string $csrf,array $displays):void{
    $existing=isset($row['id']);$audience=$existing?cd_content_audience($db,$c['slug'],(int)$row['id']):['mode'=>'all','targets'=>[]];
    ?><form method="post" class="profile">
    <input type="hidden" name="csrf" value="<?=$csrf?>"><input type="hidden" name="action" value="content"><input type="hidden" name="club" value="<?=cd_h($c['slug'])?>"><input type="hidden" name="kind" value="<?=cd_h($kind)?>"><input type="hidden" name="id" value="<?=(int)($row['id']??0)?>">
    <label class="full">Titel<input name="title" required maxlength="200" value="<?=cd_h($row['title']??'')?>"></label>
    <label class="full"><?=$kind==='restaurant'?'Öffnungszeiten, Angebote und Sonderöffnungszeiten':($kind==='calendar'?'Uhrzeit, Ort und Beschreibung':'Beschreibung')?> <textarea name="body" rows="4" maxlength="4000"><?=cd_h($row['body']??'')?></textarea></label>
    <label><?=$kind==='calendar'?'Termin / Beginn':'Anzeigen ab'?><input type="date" name="starts" value="<?=cd_h($row['starts']??'')?>"></label>
    <label><?=$kind==='calendar'?'Enddatum':'Anzeigen bis'?><input type="date" name="ends" value="<?=cd_h($row['ends']??'')?>"></label>
    <label class="full">Website / weiterer Link (HTTPS)<input type="url" name="url" maxlength="500" value="<?=cd_h($row['url']??'')?>"></label>
    <fieldset class="screen-audience"><legend>Anzeigen auf</legend>
    <label class="check"><input type="radio" name="audience" value="all" <?=$audience['mode']==='all'?'checked':''?>> Allen Displays mit diesem Modul (auch zukünftigen)</label>
    <label class="check"><input type="radio" name="audience" value="selected" <?=$audience['mode']==='selected'?'checked':''?> <?=(!$displays&&$audience['mode']!=='selected')?'disabled':''?>> Nur ausgewählten Displays</label>
    <?php foreach($displays as $display):$hasModule=in_array($kind,json_decode($display['modules'],true)?:[],true);?><label class="check audience-target"><input type="checkbox" name="targets[]" value="<?=cd_h($display['slug'])?>" <?=in_array($display['slug'],$audience['targets'],true)?'checked':''?>> <span><?=cd_h($display['name'])?> <small><?=!$hasModule?'Modul nicht aktiviert':(!$display['enabled']?'Profil deaktiviert':'')?></small></span></label><?php endforeach?>
    <?php if(!$displays):?><p>Lege zuerst ein Bildschirmprofil an, um einzelne Displays auszuwählen.</p><?php endif?>
    <p>Die Auswahl gilt nur bei „Nur ausgewählten Displays“. Zusätzlich müssen das Inhaltsmodul, das Profil und der Eintrag freigegeben sein.</p></fieldset>
    <label class="check"><input type="checkbox" name="enabled" <?=($row['enabled']??1)?'checked':''?>> Für Anzeige freigegeben</label>
    <button name="intent" value="save"><?=$existing?'Eintrag speichern':'Eintrag anlegen'?></button>
    <?php if($existing):?><button class="danger" name="intent" value="delete" formnovalidate>Eintrag löschen</button><?php endif?>
    </form><?php
}
?><!doctype html><html lang="de"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>ClubDisplay Administration</title><link rel="stylesheet" href="admin.css?v=075"></head><body class="admin-page<?=$fixedClub==='ntc93'?' ntc-portal':''?>"><header><img src="<?=$fixedClub==='ntc93'?'../assets/ntc93-logo.png':'../assets/clubdisplay.png'?>" alt="<?=$fixedClub==='ntc93'?'NTC 93':'TENNILO ClubDisplay'?>"><strong><?=$fixedClub==='ntc93'?'NTC 93 · VEREINSADMINISTRATION':($clubPortal?'VEREINSADMINISTRATION':'TENNILO ADMINISTRATION')?></strong></header><main>
<?php if($error):?><p class="error" role="alert"><?=cd_h($error)?></p><?php endif?>
<?php if($ok):?><p class="ok" role="status"><?=cd_h($ok)?></p><?php endif?>
<?php if($auth&&!empty($_SESSION['device_link'])):$deviceLink=$_SESSION['device_link'];unset($_SESSION['device_link']);?><section class="panel"><h2>Geräteadresse · nur einmal sichtbar</h2><p>Vertraulich behandeln und am Bildschirm öffnen. Bei Verlust einen neuen Schlüssel erzeugen.</p><input readonly value="<?=cd_h($deviceLink)?>" aria-label="Geräteadresse"><a class="admin-action" href="<?=cd_h($deviceLink)?>" target="_blank" rel="noopener noreferrer">Gerät öffnen</a></section><?php endif?>
<?php if(!$auth):?><section class="login"><h1><?=$clubPortal?'Vereinsadmin-Anmeldung':'Administrator-Anmeldung'?></h1><form method="post"><input type="hidden" name="csrf" value="<?=$csrf?>"><input type="hidden" name="action" value="login"><?php if($clubPortal):?><?php if($fixedClub):?><p>NTC 93</p><?php else:?><label>Vereinskennung <input name="club_login" autocomplete="username" required></label><?php endif?><?php endif?><label>Passwort <input type="password" name="password" autocomplete="current-password" required></label><button>Anmelden</button></form></section>
<?php else:$clubs=$scope===null?$db->query('SELECT * FROM clubs ORDER BY name')->fetchAll():[cd_club($db,$scope)];?>
<div class="head"><div><h1><?=$scope===null?'TENNILO · Kundenverwaltung':'Vereinsbackend'?></h1><p><?=count($clubs)?> Vereinsprofile · Konfigurationsentwürfe</p></div><form method="post"><input type="hidden" name="csrf" value="<?=$csrf?>"><input type="hidden" name="action" value="logout"><button>Abmelden</button></form></div>
<p class="note">Neue gekoppelte Geräte verwenden diese Konfiguration. Bestehende NTC- und Demo-Displays bleiben unverändert. Das verwaltete Display ist zunächst eine reine Anzeige ohne Buchungsfunktion.</p>
<?php if($scope===null):?><details class="panel"><summary>Datensicherung</summary><form method="post" class="profile"><input type="hidden" name="csrf" value="<?=$csrf?>"><input type="hidden" name="action" value="backup"><p>Vollständige SQLite-Sicherung mit vertraulichen Daten. Verschlüsselt und zugriffsgeschützt aufbewahren.</p><button>Sicherung herunterladen</button></form></details><?php endif?>
<?php if($scope===null):?><details class="panel"><summary>Abrechnungsübersicht exportieren</summary><form method="post" class="profile"><input type="hidden" name="csrf" value="<?=$csrf?>"><input type="hidden" name="action" value="billing-export"><button>CSV herunterladen</button></form></details><?php endif?><details class="panel"><summary>Änderungsprotokoll · letzte 100 Einträge</summary><?php auditField($db,$scope)?></details>
<?php if($scope===null):?><details class="panel"><summary>+ Neuen Verein anlegen</summary><?php field([],true,$csrf)?></details><?php endif?>
<?php foreach($clubs as $club):$displays=cd_displays($db,$club['slug']);?><details class="panel" <?=$scope!==null||$focusClub===$club['slug']?'open':''?>><summary><?=cd_h($club['name'])?> <small><?=cd_h(cd_plan_label($club['plan']))?> · <?=count($displays)?> Bildschirme</small></summary>
<?php if($scope===null):?><details class="subpanel"><summary>Vereinsprofil & Lizenz</summary><?php field($club,false,$csrf)?></details><details class="subpanel"><summary>Vereinszugang</summary><?php accountField($club,cd_account_row($db,$club['slug']),$csrf)?></details><details class="subpanel"><summary>Vertrag & Bildschirmkontingent</summary><?php contractField($club,cd_contract($db,$club['slug']),$csrf)?></details><details class="subpanel" <?=$focusAction==='billing'&&$focusClub===$club['slug']?'open':''?>><summary>Abrechnungsdaten</summary><?php billingField($club,cd_billing($db,$club['slug']),$csrf)?></details><?php endif?>
<details class="subpanel" <?=in_array($focusAction,['sources','source-test'],true)&&$focusClub===$club['slug']?'open':''?>><summary>Datenquellen & Schnittstellen</summary><?php sourceField($club,cd_sources($db,$club['slug'],$scope),$csrf,$scope)?><form method="post" class="profile"><input type="hidden" name="csrf" value="<?=$csrf?>"><input type="hidden" name="club" value="<?=cd_h($club['slug'])?>"><button name="action" value="source-test">Freigegebene Datenfeeds prüfen</button></form></details><details class="subpanel" <?=$focusAction==='partner-board'&&$focusClub===$club['slug']?'open':''?>><summary>Spielpartnerbörse</summary><?php cd_partner_field($db,$club,$csrf,$scope)?></details><?php if($club['slug']==='ntc93'):?><details class="subpanel" <?=$focusAction==='matchball'&&$focusClub===$club['slug']?'open':''?>><summary>MATCHBALL – Newsletter</summary><?php cd_matchball_field($db,$club,$csrf,$scope)?></details><details class="subpanel" <?=$focusAction==='community'&&$focusClub===$club['slug']?'open':''?>><summary>WhatsApp-Communitys</summary><?php cd_community_field($db,$club,$csrf,$scope)?></details><?php endif?><h2>Gemeinsame Vereinsinhalte</h2><p>Einmal pflegen und gezielt auf allen oder ausgewählten Bildschirmen veröffentlichen. Für gekoppelte Geräte wirksam.</p>
<details class="subpanel" <?=$focusAction==='brand'&&$focusClub===$club['slug']?'open':''?>><summary>Logo, Farben & Begrüßung</summary><?php brandField($club,cd_brand($db,$club['slug']),$csrf)?></details>
<?php foreach(CD_CONTENT as $kind=>$label):if(!in_array($kind,json_decode($club['modules'],true)?:[],true))continue;$rows=cd_content_rows($db,$club['slug'],$kind);?><details class="subpanel"><summary><?=cd_h($label)?> <small><?=count($rows)?> Einträge</small></summary>
<details class="entry"><summary>+ Eintrag anlegen</summary><?php contentField($db,$club,$kind,[],$csrf,$displays)?></details>
<?php foreach($rows as $row):?><details class="entry"><summary><?=cd_h($row['title'])?> <small><?=$row['enabled']?'Freigegeben':'Entwurf'?></small></summary><?php contentField($db,$club,$kind,$row,$csrf,$displays);mediaField($club,$row,$csrf)?></details><?php endforeach?></details><?php endforeach?>
<?php if(in_array('restaurant',json_decode($club['modules'],true)?:[],true)):?><details class="subpanel" <?=$focusAction==='dining'&&$focusClub===$club['slug']?'open':''?>><summary>Gastronomie · Öffnungszeiten & Wochenangebote</summary><?php diningField($club,cd_dining_get($db,$club['slug'],$scope),$csrf)?></details><?php endif?>
<h2>Bildschirme</h2><p>Jeder Bildschirm erhält eigene Module, Folienreihenfolge, Begrüßung, Anzeigezeiten, Betriebsart und Sprache. Automatische externe Feeds werden über die Modulauswahl gesteuert.</p>
<details class="subpanel"><summary>+ Bildschirm anlegen</summary><?php displayField($db,$club,[],true,$csrf)?></details>
<?php foreach($displays as $d):?><details class="subpanel"><summary><?=cd_h($d['name'])?> <small><?=cd_h(cd_plan_label($d['mode']))?> · <?=$d['enabled']?'Aktiv':'Deaktiviert'?></small></summary><?php displayField($db,$club,$d,false,$csrf);deviceField($db,$club,$d,$csrf,$portalUrl)?></details><?php endforeach?>
</details><?php endforeach?>
<?php endif?><?php if($fixedClub==='ntc93'):?><footer class="portal-credit">Powered by TENNILO ClubDisplay</footer><?php endif?></main></body></html>
