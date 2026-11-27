<?php
declare(strict_types=1);
// Admin-only pilot. Never return or persist a personal code or raw API response.
function cd_pin_result(array $payload,string $pin):array{
    if(($payload['error']??null)!==null||!is_array($payload['response']??null))return ['status'=>'denied'];
    $p=$payload['response'];
    if(($p['archived']??null)!==false||!is_string($p['code']??null)||$p['code']===''||!hash_equals($p['code'],$pin))return ['status'=>'denied'];
    $age='unknown';
    $birthday=$p['birthday']??null;
    if(is_string($birthday)&&preg_match('/^\d{4}-\d{2}-\d{2}$/D',$birthday)){
        $tz=new DateTimeZone('Europe/Berlin');
        $d=DateTimeImmutable::createFromFormat('!Y-m-d',$birthday,$tz);
        $today=new DateTimeImmutable('today',$tz);
        if($d&&$d->format('Y-m-d')===$birthday&&$d<=$today){
            $years=$d->diff($today)->y;
            if($years<=120)$age=$years<16?'child':($years<18?'teen':'adult');
        }
    }
    return ['status'=>'matched','age'=>$age];
}
function cd_pin_lookup(array $cfg,string $username,string $pin):array{
    if(!is_string($cfg['user']??null)||$cfg['user']===''||!is_string($cfg['password']??null)||$cfg['password']===''||!function_exists('curl_init'))return ['status'=>'unconfigured'];
    // Restrict path segment. No bulk enumeration or writes. Never put a PIN in a URL.
    if(!preg_match('/^[\p{L}\p{M}\p{N}_.@+ -]{1,100}$/uD',$username)||in_array($username,['.','..'],true)||!preg_match('/^[0-9]{1,20}$/D',$pin))return ['status'=>'denied'];
    $body='';$ch=curl_init('https://ntc93v8.ebusy.de/api/general/person/by-username/'.rawurlencode($username));
    if($ch===false)return ['status'=>'unavailable'];
    curl_setopt_array($ch,[CURLOPT_HTTPAUTH=>CURLAUTH_BASIC,CURLOPT_USERPWD=>$cfg['user'].':'.$cfg['password'],CURLOPT_HTTPHEADER=>['Accept: application/json'],CURLOPT_FOLLOWLOCATION=>false,CURLOPT_PROTOCOLS=>CURLPROTO_HTTPS,CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2,CURLOPT_CONNECTTIMEOUT=>5,CURLOPT_TIMEOUT=>12,CURLOPT_WRITEFUNCTION=>static function($c,string $s)use(&$body):int{if(strlen($body)+strlen($s)>1000000)return 0;$body.=$s;return strlen($s);}]);
    $ok=curl_exec($ch);$status=(int)curl_getinfo($ch,CURLINFO_RESPONSE_CODE);curl_close($ch);
    if(in_array($status,[401,403],true))return ['status'=>'permission'];
    if($status===429)return ['status'=>'limited'];
    if($status===404)return ['status'=>'denied'];
    if(!$ok||$status!==200)return ['status'=>'unavailable'];
    try{$payload=json_decode($body,true,64,JSON_THROW_ON_ERROR);return is_array($payload)?cd_pin_result($payload,$pin):['status'=>'unavailable'];}catch(Throwable){return ['status'=>'unavailable'];}
}
function cd_pin_attempt(PDO $db,int $now):bool{
    $db->exec('CREATE TABLE IF NOT EXISTS attempts (at INTEGER NOT NULL)');
    $db->exec('PRAGMA busy_timeout=5000');
    $db->exec('BEGIN IMMEDIATE');
    try{
        $db->prepare('DELETE FROM attempts WHERE at<=?')->execute([$now-900]);
        $allowed=(int)$db->query('SELECT COUNT(*) FROM attempts')->fetchColumn()<10;
        if($allowed)$db->prepare('INSERT INTO attempts VALUES(?)')->execute([$now]);
        $db->exec('COMMIT');return $allowed;
    }catch(Throwable $e){$db->exec('ROLLBACK');throw $e;}
}
if(PHP_SAPI==='cli'&&defined('CD_PIN_UNIT_TEST'))return;
ini_set('display_errors','0');
header('Content-Type: text/html; charset=UTF-8');
header('Cache-Control: no-store, private, max-age=0');
header('X-Content-Type-Options: nosniff');
header('X-Robots-Tag: noindex, nofollow');
header('X-Frame-Options: DENY');
header('Referrer-Policy: strict-origin');
header("Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'; img-src 'self'; form-action 'self'; base-uri 'none'; frame-ancestors 'none'");
if(($_SERVER['HTTP_HOST']??'')!=='clubdisplay.ntc93.de'||empty($_SERVER['HTTPS'])||$_SERVER['HTTPS']==='off'){http_response_code(404);exit('Nicht verfügbar.');}
$method=$_SERVER['REQUEST_METHOD']??'';
if(!in_array($method,['GET','POST'],true)){http_response_code(405);exit('Nicht verfügbar.');}
$msg='';$success=false;$age='';
function cd_pin_field(string $k):string{return is_string($_POST[$k]??null)?$_POST[$k]:'';}
function cd_pin_h(string $s):string{return htmlspecialchars($s,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');}
try{
    $root=dirname(__DIR__);
    $private=$root.'/devices-private';
    $eb=$root.'/ebusy-private';
    foreach([$private,$eb] as $dir)if(!is_file($dir.'/.htaccess')||!is_file($dir.'/config.php'))throw new RuntimeException('unconfigured');
    $admin=require $private.'/config.php';
    $cfg=require $eb.'/config.php';
    if(empty($admin['hash']))throw new RuntimeException('unconfigured');
    session_name('CD_NTC_PIN_TEST');
    session_set_cookie_params(['lifetime'=>0,'path'=>'/spielpartner/pin-test.php','secure'=>true,'httponly'=>true,'samesite'=>'Strict']);
    ini_set('session.use_strict_mode','1');ini_set('session.use_only_cookies','1');session_start();
    $_SESSION['csrf']??=bin2hex(random_bytes(32));
    if($method==='POST'){
        $origin=$_SERVER['HTTP_ORIGIN']??'';
        $site=$_SERVER['HTTP_SEC_FETCH_SITE']??'';
        if($origin!=='https://clubdisplay.ntc93.de'||($site!==''&&$site!=='same-origin')||(int)($_SERVER['CONTENT_LENGTH']??0)>4096||!hash_equals($_SESSION['csrf'],cd_pin_field('csrf'))){http_response_code(403);exit('Anfrage abgelehnt.');}
        $_SESSION['csrf']=bin2hex(random_bytes(32));
        $db=new PDO('sqlite:'.$private.'/pin-test.sqlite',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
        if(!cd_pin_attempt($db,time())){$msg='Zu viele Testversuche. Bitte später erneut versuchen.';http_response_code(429);}
        elseif(!preg_match('/^\d{6,20}$/D',cd_pin_field('admin_pin'))||!password_verify(cd_pin_field('admin_pin'),$admin['hash'])){$msg='Admin-Freigabe fehlgeschlagen.';http_response_code(403);}
        elseif(cd_pin_field('own_account')!=='yes'){$msg='Bitte bestätige, dass du ausschließlich dein eigenes Konto testest.';}
        else{
            $result=cd_pin_lookup($cfg,trim(cd_pin_field('username')),cd_pin_field('member_pin'));
            $success=$result['status']==='matched';
            $msg=match($result['status']){
                'matched'=>'PIN-Abgleich erfolgreich. Nicht archiviertes eBuSy-Konto bestätigt.',
                'permission'=>'Die API hat die Personenabfrage nicht erlaubt (HTTP 401/403).',
                'limited'=>'eBuSy meldet ein Anfragelimit. Bitte später erneut versuchen.',
                'unconfigured'=>'eBuSy-API nicht eingerichtet.',
                'unavailable'=>'Personenabfrage derzeit nicht verfügbar oder unerwartete API-Antwort.',
                default=>'Benutzername oder PIN nicht korrekt, Code nicht verfügbar oder Konto archiviert.'
            };
            $age=match($result['age']??'unknown'){'adult'=>'18 Jahre oder älter','teen'=>'16–17 Jahre','child'=>'Unter 16 Jahren',default=>'Nicht sicher bestimmbar'};
        }
        // No authenticated member session or credentials are kept.
        unset($_POST['admin_pin'],$_POST['member_pin'],$_POST['username']);
    }
    $csrf=cd_pin_h($_SESSION['csrf']);session_write_close();
}catch(Throwable){http_response_code(503);exit('PIN-Test derzeit nicht verfügbar.');}
?><!doctype html><html lang="de"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>NTC · eBuSy PIN-Test</title><style>
*{box-sizing:border-box}body{margin:0;background:#edf3f2;color:#123b3d;font:16px/1.5 system-ui,sans-serif}main{max-width:640px;margin:auto;padding:22px}header{display:flex;align-items:center;gap:14px;margin-bottom:20px}header img{width:64px;height:64px;object-fit:contain}h1{font-size:clamp(23px,5vw,32px);line-height:1.15;margin:0}form,section{background:white;border:1px solid #d4dfdc;border-radius:14px;padding:20px;margin:16px 0}label{font-weight:650;display:block}input:not([type=checkbox]){width:100%;margin:7px 0 15px;padding:12px;border:1px solid #9ab0ad;border-radius:8px;font:inherit;min-height:44px}button{width:100%;background:#075357;color:white;border:0;border-radius:8px;padding:13px;font:700 16px system-ui;margin-top:16px}a{color:#075357}small{color:#526969}.notice{border-left:5px solid #ad7a19}.success{border-left:5px solid #16805a}.check{display:flex;gap:8px;align-items:flex-start;font-weight:400}.check input{margin-top:5px}h2{font-size:20px;margin:0 0 12px}
</style></head><body><main><header><img src="/assets/ntc93-logo.png" alt="NTC 93"><div><small>CLUBDISPLAY · SPIELPARTNERBÖRSE</small><h1>eBuSy-PIN · Admin-Test</h1></div></header>
<p>Geschützter technischer Test. Bitte ausschließlich dein eigenes eBuSy-Konto verwenden. Die Spielpartnerbörse bleibt unverändert. PINs und Personenstammdaten werden von ClubDisplay nicht gespeichert.</p>
<?php if($msg!==''):?><section role="status" class="<?=$success?'success':'notice'?>"><h2><?=cd_pin_h($msg)?></h2><?php if($success):?><p>Altersgruppe: <?=cd_pin_h($age)?></p><p>Vereinsmitgliedschaft: noch nicht geprüft. Keine Freischaltung von Mitgliederfunktionen.</p><?php endif;?></section><?php endif;?>
<form method="post" action="/spielpartner/pin-test.php" autocomplete="off"><input type="hidden" name="csrf" value="<?=$csrf?>"><label for="admin_pin">ClubDisplay Admin-PIN</label><input id="admin_pin" name="admin_pin" type="password" inputmode="numeric" pattern="[0-9]{6,20}" maxlength="20" required autocomplete="off"><label for="username">eBuSy-Benutzername</label><input id="username" name="username" maxlength="100" required autocomplete="off" autocapitalize="none" spellcheck="false"><label for="member_pin">Persönlicher eBuSy-PIN</label><input id="member_pin" name="member_pin" type="password" inputmode="numeric" pattern="[0-9]{1,20}" maxlength="20" required autocomplete="off"><label class="check"><input name="own_account" type="checkbox" value="yes" required> Ich teste nur mein eigenes eBuSy-Konto.</label><button type="submit">PIN-Abgleich testen</button></form>
<p><a href="/spielpartner/">Zurück zur Spielpartnerbörse</a> · <a href="/admin/">Vereinsbackend</a></p><small>Nur lesende Einzelabfrage. Keine Buchung, keine Änderungen an eBuSy. Keine Weitergabe der PINs an das zentrale TENNILO-Backend.</small></main></body></html>
