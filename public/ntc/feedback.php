<?php
declare(strict_types=1);
// NTC feedback, no contact details required. Private SQLite store protected by deploy script.
header('Content-Type: text/html; charset=UTF-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header("Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'; img-src 'self'; form-action 'self'; base-uri 'none'; frame-ancestors 'none'");
header('Referrer-Policy: no-referrer');
header('X-Robots-Tag: noindex, nofollow');
function fb_h(mixed $v):string{return htmlspecialchars((string)$v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');}
function fb_text(string $key):string{return is_string($_POST[$key]??null)?trim($_POST[$key]):'';}
$manage=isset($_GET['manage']);$msg='';$ok=false;$rows=[];$authenticated=false;$now=time();
$private=__DIR__.'/devices-private';
try{
 if(!is_file($private.'/.htaccess')||!is_file($private.'/config.php'))throw new RuntimeException('not configured');
 $config=require $private.'/config.php';
 $db=new PDO('sqlite:'.$private.'/feedback.sqlite',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
 $db->exec('PRAGMA busy_timeout=5000');
 $db->exec('CREATE TABLE IF NOT EXISTS feedback (id INTEGER PRIMARY KEY AUTOINCREMENT, created INTEGER NOT NULL, rating INTEGER NOT NULL, useful TEXT NOT NULL, missing TEXT NOT NULL, ideas TEXT NOT NULL)');
 $db->exec('CREATE TABLE IF NOT EXISTS login_attempts (at INTEGER NOT NULL)');
 $db->prepare('DELETE FROM feedback WHERE created<?')->execute([$now-90*86400]);
 $db->prepare('DELETE FROM login_attempts WHERE at<?')->execute([$now-900]);
 session_name('CD_NTC_FEEDBACK');session_set_cookie_params(['lifetime'=>0,'path'=>'/feedback.php','secure'=>true,'httponly'=>true,'samesite'=>'Strict']);
 ini_set('session.use_strict_mode','1');session_start();
 $_SESSION['csrf']??=bin2hex(random_bytes(32));
 $authenticated=($_SESSION['adminUntil']??0)>$now;
 if($_SERVER['REQUEST_METHOD']==='POST'){
  if(($_SERVER['HTTP_ORIGIN']??'')!=='https://clubdisplay.ntc93.de'||(int)($_SERVER['CONTENT_LENGTH']??0)>12000||!hash_equals($_SESSION['csrf'],fb_text('csrf'))){http_response_code(403);exit('Anfrage abgelehnt.');}
  $action=fb_text('action');
  if($manage&&$action==='login'){
   $db->exec('BEGIN IMMEDIATE');
   try{
    $attempts=(int)$db->query('SELECT COUNT(*) FROM login_attempts')->fetchColumn();
    $allowed=$attempts<12;
    if($allowed)$db->prepare('INSERT INTO login_attempts VALUES(?)')->execute([$now]);
    $db->exec('COMMIT');
   }catch(Throwable $e){$db->exec('ROLLBACK');throw $e;}
   if($allowed&&preg_match('/^\d{6,20}$/D',fb_text('pin'))&&is_string($config['hash']??null)&&password_verify(fb_text('pin'),$config['hash'])){
    session_regenerate_id(true);$_SESSION['csrf']=bin2hex(random_bytes(32));$_SESSION['adminUntil']=$now+1800;$authenticated=true;
   }else $msg='PIN nicht korrekt oder zu viele Versuche. Bitte später erneut versuchen.';
  }elseif($manage&&$authenticated&&$action==='logout'){unset($_SESSION['adminUntil']);session_regenerate_id(true);$authenticated=false;}
  elseif($manage&&$authenticated&&$action==='delete'){
   $id=filter_var(fb_text('id'),FILTER_VALIDATE_INT);if($id!==false)$db->prepare('DELETE FROM feedback WHERE id=?')->execute([$id]);$msg='Eintrag gelöscht.';
  }elseif(!$manage&&$action==='submit'){
   $rating=filter_var(fb_text('rating'),FILTER_VALIDATE_INT);
   $values=[fb_text('useful'),fb_text('missing'),fb_text('ideas')];
   if(fb_text('website')!==''||$now-($_SESSION['lastSubmit']??0)<60)$msg='Bitte warte einen Moment, bevor du erneut sendest.';
   elseif($rating===false||$rating<1||$rating>5||max(array_map('strlen',$values))>2400||!preg_match('//u',implode('',$values)))$msg='Bitte Bewertung von 1 bis 5 wählen und kurze Texte eingeben.';
   else{
    $db->exec('BEGIN IMMEDIATE');
    try{
     if((int)$db->query('SELECT COUNT(*) FROM feedback')->fetchColumn()>=2000){$msg='Das Feedbackformular ist derzeit voll.';}
     else{$db->prepare('INSERT INTO feedback(created,rating,useful,missing,ideas) VALUES(?,?,?,?,?)')->execute([$now,$rating,...$values]);$_SESSION['lastSubmit']=$now;$_SESSION['feedbackSuccess']=true;}
     $db->exec('COMMIT');
    }catch(Throwable $e){$db->exec('ROLLBACK');throw $e;}
    if(isset($_SESSION['feedbackSuccess'])){header('Location: /feedback.php',true,303);exit;}
   }
  }else{http_response_code(400);$msg='Ungültige Anfrage.';}
 }
 if(!$manage&&isset($_SESSION['feedbackSuccess'])){$ok=true;unset($_SESSION['feedbackSuccess']);}
 if($manage&&$authenticated)$rows=$db->query('SELECT * FROM feedback ORDER BY created DESC,id DESC LIMIT 2000')->fetchAll(PDO::FETCH_ASSOC);
 $csrf=fb_h($_SESSION['csrf']);
}catch(Throwable $e){http_response_code(503);echo '<!doctype html><meta charset="utf-8"><h1>Feedback derzeit nicht verfügbar</h1><p>Bitte später erneut versuchen.</p>';exit;}
?><!doctype html><html lang="de"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>NTC ClubDisplay · Feedback</title><style>
*{box-sizing:border-box}body{margin:0;background:#f3f6f5;color:#103639;font:16px/1.5 system-ui,sans-serif}main{max-width:680px;margin:0 auto;padding:24px}header{display:flex;gap:16px;align-items:center;margin-bottom:24px}header img{width:64px;height:64px;object-fit:contain}h1{font-size:clamp(24px,6vw,34px);line-height:1.1;margin:0}h2{font-size:20px}form,article,.card{background:white;border:1px solid #d4dfdc;border-radius:16px;padding:20px;margin:14px 0}label,legend{font-weight:700}textarea,input[type=password]{width:100%;border:1px solid #8da7a3;border-radius:8px;padding:10px;font:inherit;min-height:44px;margin:8px 0 16px}fieldset{border:0;padding:0;margin:0 0 20px}.rating{display:flex;gap:8px;flex-wrap:wrap;margin-top:8px}.rating label{display:flex;align-items:center;gap:5px;border:1px solid #a7bdb6;border-radius:8px;padding:10px 12px}button{background:#075357;color:white;border:0;border-radius:9px;padding:12px 18px;font:700 16px system-ui;cursor:pointer}a{color:#075357}.notice{background:#fff5cb;padding:12px;border-radius:8px}.honey{position:absolute;left:-10000px;top:auto;width:1px;height:1px;overflow:hidden}small{color:#4a6364}article p{white-space:pre-wrap;overflow-wrap:anywhere}article form{padding:0;border:0;margin:8px 0}
</style></head><body><main><header><img src="/assets/ntc93-logo.png" alt="NTC 93"><div><small>NTC 93 · CLUBDISPLAY</small><h1><?=$manage?'Feedback-Auswertung':'Eure Meinung zählt.'?></h1></div></header>
<?php if($msg!==''):?><p class="notice" role="status"><?=fb_h($msg)?></p><?php endif;?>
<?php if($manage):?>
 <?php if(!$authenticated):?><form method="post"><h2>Admin-Anmeldung</h2><input type="hidden" name="csrf" value="<?=$csrf?>"><input type="hidden" name="action" value="login"><label for="pin">Admin-PIN</label><input id="pin" type="password" name="pin" required inputmode="numeric" pattern="[0-9]{6,20}" autocomplete="off"><button>Anmelden</button></form>
 <?php else:?><div class="card"><strong><?=count($rows)?> Rückmeldungen</strong><?php if($rows):?> · Ø <?=number_format(array_sum(array_column($rows,'rating'))/count($rows),1,',','.')?> / 5<?php endif;?><form method="post"><input type="hidden" name="csrf" value="<?=$csrf?>"><input type="hidden" name="action" value="logout"><button>Abmelden</button></form></div>
 <?php foreach($rows as $row):?><article><strong><?=fb_h($row['rating'])?> / 5</strong> · <?=fb_h((new DateTimeImmutable('@'.$row['created']))->setTimezone(new DateTimeZone('Europe/Berlin'))->format('d.m.Y H:i'))?><h2>Besonders nützlich</h2><p><?=fb_h($row['useful'])?></p><h2>Was fehlt?</h2><p><?=fb_h($row['missing'])?></p><h2>Weitere Ideen</h2><p><?=fb_h($row['ideas'])?></p><form method="post"><input type="hidden" name="csrf" value="<?=$csrf?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?=fb_h($row['id'])?>"><button>Eintrag löschen</button></form></article><?php endforeach;endif;?>
<?php elseif($ok):?><div class="card"><h2>Vielen Dank!</h2><p>Deine Rückmeldung wurde gespeichert. Gemeinsam machen wir ClubDisplay noch besser.</p><a href="/feedback.php">Weitere Rückmeldung</a></div>
<?php else:?><p>Hilf uns, das digitale Clubhaus weiterzuentwickeln. Das dauert nur eine Minute.</p><form method="post"><input type="hidden" name="csrf" value="<?=$csrf?>"><input type="hidden" name="action" value="submit"><div class="honey" aria-hidden="true"><label>Website<input name="website" tabindex="-1" autocomplete="off"></label></div><fieldset><legend>Wie gefällt dir ClubDisplay?</legend><div class="rating"><?php for($i=1;$i<=5;$i++):?><label><input type="radio" name="rating" value="<?=$i?>" required><?=$i?></label><?php endfor;?></div><small>1 = gefällt mir nicht · 5 = gefällt mir sehr gut</small></fieldset><label for="useful">Was findest du besonders nützlich?</label><textarea id="useful" name="useful" rows="3" maxlength="600"></textarea><label for="missing">Was fehlt dir noch?</label><textarea id="missing" name="missing" rows="3" maxlength="600"></textarea><label for="ideas">Weitere Ideen oder Wünsche?</label><textarea id="ideas" name="ideas" rows="3" maxlength="600"></textarea><p><small>Freiwillig. Bitte keine Namen, Kontaktdaten oder Angaben über andere Personen eintragen. Rückmeldungen sind nur für die berechtigte Vereinsadministration sichtbar und werden bei Zugriff nach 90 Tagen bereinigt. Technische Serverprotokolle können unabhängig davon anfallen.</small></p><button>Feedback senden</button></form><?php endif;?><p><a href="/">Zum ClubDisplay</a></p></main></body></html>
