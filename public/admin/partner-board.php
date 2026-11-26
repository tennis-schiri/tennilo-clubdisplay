<?php
declare(strict_types=1);
// The public board contains only structured, approved data. Private contacts never enter a display feed.
function cd_partner_schema(PDO $db):void{
    $db->exec("CREATE TABLE IF NOT EXISTS partner_board (club TEXT PRIMARY KEY REFERENCES clubs(slug) ON DELETE CASCADE, enabled INTEGER NOT NULL DEFAULT 0);
    CREATE TABLE IF NOT EXISTS partner_invites (hash TEXT PRIMARY KEY, club TEXT NOT NULL REFERENCES clubs(slug) ON DELETE CASCADE, expires INTEGER NOT NULL);
    CREATE TABLE IF NOT EXISTS partner_posts (ref TEXT PRIMARY KEY, club TEXT NOT NULL REFERENCES clubs(slug) ON DELETE CASCADE, category TEXT NOT NULL, skill TEXT NOT NULL, availability TEXT NOT NULL, period TEXT NOT NULL, contact TEXT NOT NULL, state TEXT NOT NULL CHECK(state IN ('pending','published')), created INTEGER NOT NULL, expires INTEGER NOT NULL);
    CREATE TABLE IF NOT EXISTS partner_interest (id INTEGER PRIMARY KEY AUTOINCREMENT, ref TEXT NOT NULL REFERENCES partner_posts(ref) ON DELETE CASCADE, contact TEXT NOT NULL, created INTEGER NOT NULL, expires INTEGER NOT NULL);
    CREATE INDEX IF NOT EXISTS partner_posts_club ON partner_posts(club,state,expires);
    CREATE INDEX IF NOT EXISTS partner_interest_ref ON partner_interest(ref)");
}

/** Lazy additive migration: existing version-604 databases do not rerun the main schema. */
function cd_partner_mail_schema(PDO $db):void{
    $db->exec("CREATE TABLE IF NOT EXISTS partner_mail_settings (club TEXT PRIMARY KEY REFERENCES clubs(slug) ON DELETE CASCADE, email TEXT NOT NULL);
    CREATE TABLE IF NOT EXISTS partner_mail_tokens (ref TEXT PRIMARY KEY REFERENCES partner_posts(ref) ON DELETE CASCADE, hash TEXT UNIQUE NOT NULL, expires INTEGER NOT NULL, status TEXT NOT NULL)");
}
function cd_partner_mail_address(PDO $db,string $club):string{
    $q=$db->prepare('SELECT email FROM partner_mail_settings WHERE club=?');$q->execute([$club]);return (string)$q->fetchColumn();
}
function cd_partner_mail_issue(PDO $db,string $club,string $ref,int $now):?array{
    $email=cd_partner_mail_address($db,$club);if($email==='')return null;
    $q=$db->prepare("SELECT * FROM partner_posts WHERE club=? AND ref=? AND state='pending' AND expires>?");$q->execute([$club,$ref,$now]);$row=$q->fetch();
    if(!$row||!cd_partner_enabled($db,$club))return null;
    $token=bin2hex(random_bytes(32));
    $q=$db->prepare("INSERT INTO partner_mail_tokens(ref,hash,expires,status) VALUES (?,?,?,'prepared') ON CONFLICT(ref) DO UPDATE SET hash=excluded.hash,expires=excluded.expires,status=excluded.status");
    $q->execute([$ref,hash('sha256',$token),min((int)$row['expires'],$now+172800)]);
    return ['email'=>$email,'token'=>$token,'row'=>$row];
}
function cd_partner_mail_send(PDO $db,string $club,string $ref,int $now):void{
    try{
        $db->exec('BEGIN IMMEDIATE');
        try{$notice=cd_partner_mail_issue($db,$club,$ref,$now);$db->exec('COMMIT');}
        catch(Throwable $e){$db->exec('ROLLBACK');throw $e;}
        if(!$notice)return;
        $choices=cd_partner_choices();$row=$notice['row'];
        $base='https://clubdisplay.tennilo.de/admin/?partner=moderate&club='.rawurlencode($club).'&token='.$notice['token'];
        $body="Ein neues Spielpartnergesuch wartet auf Freigabe.\n\nVerein: ".cd_club($db,$club)['name']."\nReferenz: ".$ref."\n";
        foreach($choices as $key=>$options){$body.=$options[$row[$key]]."\n";}
        $body.="\nGesuch freigeben (mit Bestätigung):\n".$base."&decision=publish\n\nGesuch ablehnen (mit Bestätigung):\n".$base."&decision=delete\n\nDie Links sind einmalig verwendbar und höchstens 48 Stunden gültig. Private Kontaktdaten stehen nur auf der Prüfseite. Bitte diese Nachricht nicht weiterleiten.\n";
        $sent=false;
        try{$sent=@mail($notice['email'],'TENNILO ClubDisplay - Neues Spielpartnergesuch',$body,"MIME-Version: 1.0\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: 8bit");}catch(Throwable $e){}
        $q=$db->prepare('UPDATE partner_mail_tokens SET status=? WHERE ref=? AND hash=?');$q->execute([$sent?'sent':'failed',$ref,hash('sha256',$notice['token'])]);
    }catch(Throwable $e){error_log('ClubDisplay partner notification could not be prepared or sent.');}
}
function cd_partner_mail_lookup(PDO $db,string $club,string $token,int $now):?array{
    if(!preg_match('/^[a-f0-9]{64}$/D',$token)||!cd_partner_enabled($db,$club))return null;
    $q=$db->prepare("SELECT p.* FROM partner_mail_tokens t JOIN partner_posts p ON p.ref=t.ref WHERE t.hash=? AND p.club=? AND p.state='pending' AND t.expires>? AND p.expires>?");
    $q->execute([hash('sha256',$token),$club,$now,$now]);return $q->fetch()?:null;
}
function cd_partner_mail_decide(PDO $db,string $club,string $token,string $decision,int $now):bool{
    if(!in_array($decision,['publish','delete'],true))return false;
    $db->exec('BEGIN IMMEDIATE');
    try{
        $row=cd_partner_mail_lookup($db,$club,$token,$now);
        if(!$row){$db->exec('COMMIT');return false;}
        if($decision==='publish'){
            $q=$db->prepare("UPDATE partner_posts SET state='published',expires=? WHERE ref=? AND club=? AND state='pending'");$q->execute([$now+1209600,$row['ref'],$club]);
        }else{
            $q=$db->prepare("DELETE FROM partner_posts WHERE ref=? AND club=? AND state='pending'");$q->execute([$row['ref'],$club]);
        }
        $q=$db->prepare('DELETE FROM partner_mail_tokens WHERE ref=?');$q->execute([$row['ref']]);
        $db->exec('COMMIT');return true;
    }catch(Throwable $e){$db->exec('ROLLBACK');throw $e;}
}
function cd_partner_mail_page(PDO $db,string $club):never{
    cd_partner_mail_schema($db);
    $method=$_SERVER['REQUEST_METHOD']??'';
    if(!in_array($method,['GET','POST'],true)){http_response_code(405);exit;}
    $input=$method==='POST'?$_POST:$_GET;
    $token=is_string($input['token']??null)?$input['token']:'';
    $decision=is_string($input['decision']??null)?$input['decision']:'';
    $row=null;$message='';$now=time();
    if($method==='POST'){
        if(isset($_SERVER['HTTP_ORIGIN'])&&$_SERVER['HTTP_ORIGIN']!=='https://clubdisplay.tennilo.de'){http_response_code(403);exit;}
        if((int)($_SERVER['CONTENT_LENGTH']??0)>4096){http_response_code(413);exit;}
        if(($input['confirmed']??'')==='1'&&cd_partner_mail_decide($db,$club,$token,$decision,$now)){
            $message=$decision==='publish'?'Gesuch freigegeben und für 14 Tage veröffentlicht.':'Gesuch abgelehnt und gelöscht.';
        }
    }elseif(in_array($decision,['publish','delete'],true)){$row=cd_partner_mail_lookup($db,$club,$token,$now);}
    if(!$row&&$message===''){http_response_code(410);$message='Dieser Freigabelink ist ungültig, abgelaufen oder wurde bereits verwendet. Bitte im Vereinsbackend prüfen.';}
    ?><!doctype html><html lang="de"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>Spielpartnergesuch prüfen</title><link rel="stylesheet" href="admin.css?v=065"></head><body><main><section class="panel"><h1>Spielpartnergesuch prüfen</h1>
    <?php if($row):$choices=cd_partner_choices();?><p><?=cd_h(cd_club($db,$club)['name'].' · '.$row['ref'])?></p><p><?=cd_h(implode(' · ',array_map(fn($key)=>$choices[$key][$row[$key]],array_keys($choices))))?></p><p>Privater Kontakt: <?=cd_h($row['contact'])?></p>
    <form method="post" action="?partner=moderate&amp;club=<?=rawurlencode($club)?>" class="profile"><input type="hidden" name="token" value="<?=cd_h($token)?>"><input type="hidden" name="decision" value="<?=cd_h($decision)?>"><label class="check full"><input type="checkbox" name="confirmed" value="1" required> <?= $decision==='publish'?'Gesuch und Einwilligung geprüft':'Ich möchte dieses Gesuch ablehnen und löschen' ?></label><button class="<?=$decision==='delete'?'danger':''?>"><?=$decision==='publish'?'Jetzt freigeben':'Jetzt ablehnen'?></button></form>
    <?php else:?><p><?=cd_h($message)?></p><?php endif?></section></main></body></html><?php
    exit;
}

function cd_partner_choices():array{
    return ['category'=>['singles'=>'Einzel','doubles'=>'Doppel','mixed'=>'Mixed','training'=>'Training'],
        'skill'=>['any'=>'Alle Spielstärken','beginner'=>'Einsteiger','recreational'=>'Freizeitspieler','league'=>'Mannschaftsspieler','advanced'=>'Fortgeschritten'],
        'availability'=>['flexible'=>'Flexibel','weekday'=>'Werktags','weekend'=>'Am Wochenende','mon'=>'Montag','tue'=>'Dienstag','wed'=>'Mittwoch','thu'=>'Donnerstag','fri'=>'Freitag','sat'=>'Samstag','sun'=>'Sonntag'],
        'period'=>['any'=>'Zeitlich flexibel','morning'=>'Vormittags','afternoon'=>'Nachmittags','evening'=>'Abends']];
}
function cd_partner_enabled(PDO $db,string $club):bool{
    $q=$db->prepare('SELECT enabled FROM partner_board WHERE club=?');$q->execute([$club]);return (int)$q->fetchColumn()===1;
}
function cd_partner_cleanup(PDO $db,?int $now=null):void{
    $now??=time();
    foreach(['partner_invites','partner_interest','partner_posts'] as $table){$q=$db->prepare('DELETE FROM '.$table.' WHERE expires<=?');$q->execute([$now]);}
}
function cd_partner_rows(PDO $db,string $club,?int $now=null):array{
    $now??=time();if(!cd_partner_enabled($db,$club))return [];
    $q=$db->prepare("SELECT ref,category,skill,availability,period,expires FROM partner_posts WHERE club=? AND state='published' AND expires>? ORDER BY created DESC LIMIT 30");$q->execute([$club,$now]);return $q->fetchAll();
}
function cd_partner_admin_rows(PDO $db,string $club,?string $scope,?int $now=null):array{
    cd_scope($scope,$club);$now??=time();
    $q=$db->prepare('SELECT * FROM partner_posts WHERE club=? AND expires>? ORDER BY created DESC LIMIT 100');$q->execute([$club,$now]);$rows=$q->fetchAll();
    $q=$db->prepare('SELECT id,contact,created,expires FROM partner_interest WHERE ref=? AND expires>? ORDER BY created');
    foreach($rows as &$r){$q->execute([$r['ref'],$now]);$r['interest']=$q->fetchAll();}unset($r);return $rows;
}
function cd_partner_admin(PDO $db,array $in,?string $scope,?int $now=null):?string{
    $now??=time();$club=cd_text($in['club']??'',50,true);cd_scope($scope,$club);cd_club($db,$club);
    $intent=$in['intent']??'';cd_partner_mail_schema($db);$sendRef=null;
    $db->exec('BEGIN IMMEDIATE');
    try{
        cd_partner_cleanup($db,$now);$token=null;
        if($intent==='toggle'){
            $enabled=isset($in['enabled'])?1:0;
            $q=$db->prepare('INSERT INTO partner_board VALUES (?,?) ON CONFLICT(club) DO UPDATE SET enabled=excluded.enabled');$q->execute([$club,$enabled]);
            if(!$enabled){$q=$db->prepare('DELETE FROM partner_invites WHERE club=?');$q->execute([$club]);$q=$db->prepare('DELETE FROM partner_posts WHERE club=?');$q->execute([$club]);}
        }elseif($intent==='mail-settings'){
            $email=trim(is_string($in['moderator_email']??null)?$in['moderator_email']:'');
            if($email!==''&&(strlen($email)>254||!filter_var($email,FILTER_VALIDATE_EMAIL)||preg_match('/[\r\n]/',$email)))throw new InvalidArgumentException('Ungültige E-Mail-Adresse.');
            if($email!==cd_partner_mail_address($db,$club)){
                $q=$db->prepare('DELETE FROM partner_mail_tokens WHERE ref IN (SELECT ref FROM partner_posts WHERE club=?)');$q->execute([$club]);
                $q=$db->prepare('INSERT INTO partner_mail_settings(club,email) VALUES (?,?) ON CONFLICT(club) DO UPDATE SET email=excluded.email');$q->execute([$club,$email]);
            }
        }elseif($intent==='mail-resend'){
            $ref=cd_text($in['ref']??'',32,true);
            $q=$db->prepare("SELECT 1 FROM partner_posts WHERE ref=? AND club=? AND state='pending' AND expires>?");$q->execute([$ref,$club,$now]);
            if(!$q->fetchColumn()||!cd_partner_enabled($db,$club)||cd_partner_mail_address($db,$club)==='')throw new InvalidArgumentException('Offenes Gesuch und E-Mail-Adresse erforderlich.');
            $sendRef=$ref;
        }elseif($intent==='invite'){
            if(!cd_partner_enabled($db,$club)||($in['verified_member']??'')!=='1')throw new InvalidArgumentException('Nur für bestätigte volljährige Vereinsmitglieder.');
            $q=$db->prepare('SELECT COUNT(*) FROM partner_invites WHERE club=?');$q->execute([$club]);if((int)$q->fetchColumn()>=50)throw new InvalidArgumentException('Zu viele offene Einladungen.');
            $token=bin2hex(random_bytes(16));$q=$db->prepare('INSERT INTO partner_invites VALUES (?,?,?)');$q->execute([hash('sha256',$token),$club,$now+172800]);
        }elseif(in_array($intent,['publish','delete','interest-delete'],true)){
            $ref=cd_text($in['ref']??'',32,true);
            $q=$db->prepare('SELECT * FROM partner_posts WHERE ref=? AND club=? AND expires>?');$q->execute([$ref,$club,$now]);$row=$q->fetch();if(!$row)throw new InvalidArgumentException('Gesuch nicht gefunden.');
            if($intent==='publish'){
                if(!cd_partner_enabled($db,$club)||($in['reviewed']??'')!=='1'||$row['state']!=='pending')throw new InvalidArgumentException('Freigabe und Prüfung erforderlich.');
                $q=$db->prepare("UPDATE partner_posts SET state='published',expires=? WHERE ref=? AND club=? AND state='pending'");$q->execute([$now+1209600,$ref,$club]);
            }elseif($intent==='delete'){$q=$db->prepare('DELETE FROM partner_posts WHERE ref=? AND club=?');$q->execute([$ref,$club]);}
            else{$id=filter_var($in['interest_id']??null,FILTER_VALIDATE_INT);if(!$id)throw new InvalidArgumentException('Ungültige Anfrage.');$q=$db->prepare('DELETE FROM partner_interest WHERE id=? AND ref=?');$q->execute([$id,$ref]);}
        }else throw new InvalidArgumentException('Unbekannte Aktion.');
        $db->exec('COMMIT');
    }catch(Throwable $e){$db->exec('ROLLBACK');throw $e;}
    if($sendRef!==null)cd_partner_mail_send($db,$club,$sendRef,$now);return $token;
}
function cd_partner_submit(PDO $db,string $club,array $in,?int $now=null):void{
    $now??=time();
    $code=$in['code']??'';
    if(!is_string($code)||!preg_match('/^[a-f0-9]{32}$/D',$code)||($in['adult']??'')!=='1'||($in['consent']??'')!=='1')throw new InvalidArgumentException('Einladung und Einwilligung prüfen.');
    $contact=cd_text($in['contact']??'',200,true);
    $mode=$in['mode']??'';
    if(!in_array($mode,['post','interest'],true))throw new InvalidArgumentException('Aktion auswählen.');
    cd_partner_mail_schema($db);$fields=[];
    if($mode==='post'){foreach(cd_partner_choices() as $key=>$choices){$v=$in[$key]??'';if(!is_string($v)||!isset($choices[$v]))throw new InvalidArgumentException('Bitte Auswahl prüfen.');$fields[]=$v;}}
    $db->exec('BEGIN IMMEDIATE');
    try{
        cd_partner_cleanup($db,$now);
        if(!cd_partner_enabled($db,$club))throw new InvalidArgumentException('Spielpartnerbörse nicht freigeschaltet.');
        $q=$db->prepare('SELECT 1 FROM partner_invites WHERE hash=? AND club=? AND expires>?');$q->execute([hash('sha256',$code),$club,$now]);if(!$q->fetchColumn())throw new InvalidArgumentException('Einladung ungültig oder bereits verwendet.');
        if($mode==='post'){
            $q=$db->prepare('SELECT COUNT(*) FROM partner_posts WHERE club=?');$q->execute([$club]);if((int)$q->fetchColumn()>=100)throw new InvalidArgumentException('Börse derzeit ausgelastet.');
            $ref=strtoupper(bin2hex(random_bytes(5)));
            $q=$db->prepare("INSERT INTO partner_posts VALUES (?,?,?,?,?,?,?,'pending',?,?)");$q->execute([$ref,$club,...$fields,$contact,$now,$now+1209600]);
        }else{
            $ref=strtoupper(cd_text($in['ref']??'',10,true));
            $q=$db->prepare("SELECT expires FROM partner_posts WHERE ref=? AND club=? AND state='published' AND expires>?");$q->execute([$ref,$club,$now]);$expires=$q->fetchColumn();if(!$expires)throw new InvalidArgumentException('Gesuch nicht mehr verfügbar.');
            $q=$db->prepare('SELECT COUNT(*) FROM partner_interest WHERE ref=?');$q->execute([$ref]);if((int)$q->fetchColumn()>=30)throw new InvalidArgumentException('Zu viele offene Anfragen.');
            $q=$db->prepare('INSERT INTO partner_interest(ref,contact,created,expires) VALUES (?,?,?,?)');$q->execute([$ref,$contact,$now,min((int)$expires,$now+1209600)]);
        }
        $q=$db->prepare('DELETE FROM partner_invites WHERE hash=? AND club=?');$q->execute([hash('sha256',$code),$club]);$db->exec('COMMIT');
    }catch(Throwable $e){$db->exec('ROLLBACK');throw $e;}
    if($mode==='post')cd_partner_mail_send($db,$club,$ref,$now);
}
function cd_partner_public(PDO $db):never{
    $club=is_string($_GET['club']??null)?$_GET['club']:'';
    if(!preg_match('/^[a-z0-9][a-z0-9-]{1,48}[a-z0-9]$/D',$club)){http_response_code(404);exit;}
    $action=$_GET['partner']??'';
    if($action==='moderate')cd_partner_mail_page($db,$club);
    if($action==='feed'){
        if($_SERVER['REQUEST_METHOD']!=='GET'){http_response_code(405);exit;}
        if(($_SERVER['HTTP_ORIGIN']??'')==='https://clubdisplay.ntc93.de'&&$club==='ntc93')header('Access-Control-Allow-Origin: https://clubdisplay.ntc93.de');
        header('Vary: Origin');header('Content-Type: application/json; charset=utf-8');
        try{cd_partner_cleanup($db);echo json_encode(['enabled'=>cd_partner_enabled($db,$club),'rows'=>cd_partner_rows($db,$club)],JSON_THROW_ON_ERROR);}catch(Throwable $e){http_response_code(503);echo '{"enabled":false,"rows":[]}';}exit;
    }
    if($action!=='submit'||!cd_partner_enabled($db,$club)){http_response_code(404);exit('Spielpartnerbörse nicht freigeschaltet.');}
    if(!in_array($_SERVER['REQUEST_METHOD'],['GET','POST'],true)){http_response_code(405);exit;}
    $error='';$success=false;
    if($_SERVER['REQUEST_METHOD']==='POST'){
        if(isset($_SERVER['HTTP_ORIGIN'])&&$_SERVER['HTTP_ORIGIN']!=='https://clubdisplay.tennilo.de'){http_response_code(403);exit;}
        if((int)($_SERVER['CONTENT_LENGTH']??0)>4096){http_response_code(413);exit;}
        try{cd_partner_submit($db,$club,$_POST);$success=true;}catch(InvalidArgumentException $e){$error=$e->getMessage();}catch(Throwable $e){http_response_code(503);$error='Anfrage derzeit nicht möglich.';}
    }
    $c=cd_club($db,$club);
    ?><!doctype html><html lang="de"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>Spielpartnerbörse</title><link rel="stylesheet" href="admin.css?v=065"></head><body><main><section class="panel"><h1>Spielpartnerbörse · <?=cd_h($c['name'])?></h1>
    <?php if($success):?><p class="ok">Anfrage eingegangen. Dein Verein prüft sie und vermittelt den Kontakt. Es werden keine privaten Kontaktdaten veröffentlicht.</p>
    <?php else:?><?php if($error):?><p class="error"><?=cd_h($error)?></p><?php endif?>
    <p>Nur für bestätigte volljährige Vereinsmitglieder mit einmaligem Einladungscode vom Verein. Gesuche werden vor Veröffentlichung geprüft. Anfragen werden durch die Vereinsadministration vermittelt.</p>
    <form method="post" class="profile"><label class="full">Einladungscode<input name="code" required autocomplete="off" pattern="[a-f0-9]{32}" maxlength="32"></label>
    <label>Was möchtest du tun?<select name="mode"><option value="post">Gesuch erstellen</option><option value="interest">Auf Gesuch reagieren</option></select></label>
    <label>Referenz für eine Antwort (bei Gesuch leer lassen)<input name="ref" maxlength="10" pattern="[A-Fa-f0-9]{10}"></label>
    <?php foreach(cd_partner_choices() as $key=>$choices):?><label><?=cd_h(['category'=>'Spielart','skill'=>'Spielstärke','availability'=>'Wochentag','period'=>'Tageszeit'][$key])?><select name="<?=cd_h($key)?>"><?php foreach($choices as $v=>$label):?><option value="<?=cd_h($v)?>"><?=cd_h($label)?></option><?php endforeach?></select></label><?php endforeach?>
    <label class="full">Name und private Kontaktmöglichkeit für den Verein<textarea name="contact" required maxlength="200" rows="2" autocomplete="off"></textarea></label>
    <label class="check full"><input type="checkbox" name="adult" value="1" required> Ich bin mindestens 18 Jahre alt und Mitglied dieses Vereins.</label>
    <label class="check full"><input type="checkbox" name="consent" value="1" required> Ich willige in die Verarbeitung durch den Verein zur Spielpartnervermittlung ein. Nur die gewählten Kategorien und die anonyme Referenz werden nach Prüfung veröffentlicht. Gesuche laufen nach höchstens 14 Tagen ab; ich kann sie jederzeit über den Verein zurückziehen. Kontaktdaten werden nicht veröffentlicht.</label>
    <button>Absenden</button></form><?php endif?></section></main></body></html><?php
    exit;
}
function cd_partner_field(PDO $db,array $club,string $csrf,?string $scope):void{
    $slug=$club['slug'];cd_partner_mail_schema($db);$email=cd_partner_mail_address($db,$slug);$enabled=cd_partner_enabled($db,$slug);$choices=cd_partner_choices();$rows=cd_partner_admin_rows($db,$slug,$scope);
    ?><p>Freiwillige Spielpartnervermittlung für volljährige Vereinsmitglieder. Anonyme Gesuche erscheinen erst nach Prüfung. Private Angaben bleiben im geschützten Backend. Ausschalten löscht offene Gesuche und Einladungen.</p>
    <form method="post" class="profile"><input type="hidden" name="csrf" value="<?=$csrf?>"><input type="hidden" name="action" value="partner-board"><input type="hidden" name="club" value="<?=cd_h($slug)?>"><label class="check"><input type="checkbox" name="enabled" <?=$enabled?'checked':''?>> Spielpartnerbörse aktivieren</label><button name="intent" value="toggle">Speichern</button></form>
    <?php if($enabled):?><form method="post" class="profile"><input type="hidden" name="csrf" value="<?=$csrf?>"><input type="hidden" name="action" value="partner-board"><input type="hidden" name="club" value="<?=cd_h($slug)?>"><label class="full">E-Mail für Gesuchfreigaben<input type="email" name="moderator_email" maxlength="254" value="<?=cd_h($email)?>" placeholder="E-Mail-Adresse des Vereinsadministrators"></label><button name="intent" value="mail-settings">E-Mail speichern</button></form><p><?=$email===''?'Noch keine Freigabe-E-Mail hinterlegt. Gesuche müssen im Backend geprüft werden.':'Bei neuen Gesuchen wird ein Freigabelink verschickt. Der Link ist einmalig und maximal 48 Stunden gültig.'?></p><p>Mitgliederformular: <a href="?partner=submit&amp;club=<?=rawurlencode($slug)?>" target="_blank" rel="noopener">Öffnen</a>. Jede Aktion benötigt einen neuen, einmalig verwendbaren Einladungscode (48 Stunden).</p>
    <form method="post" class="profile"><input type="hidden" name="csrf" value="<?=$csrf?>"><input type="hidden" name="action" value="partner-board"><input type="hidden" name="club" value="<?=cd_h($slug)?>"><label class="check full"><input type="checkbox" name="verified_member" value="1" required> Volljährigkeit und Vereinsmitgliedschaft geprüft</label><button name="intent" value="invite">Einladungscode erzeugen</button></form>
    <?php foreach($rows as $r):?><details class="entry"><summary><?=cd_h($r['ref'].' · '.$choices['category'][$r['category']])?> <small><?=$r['state']==='pending'?'Prüfung ausstehend':'Veröffentlicht'?></small></summary><p><?=cd_h($choices['skill'][$r['skill']].' · '.$choices['availability'][$r['availability']].' · '.$choices['period'][$r['period']])?></p><p>Privater Kontakt: <?=cd_h($r['contact'])?></p><form method="post" class="profile"><input type="hidden" name="csrf" value="<?=$csrf?>"><input type="hidden" name="action" value="partner-board"><input type="hidden" name="club" value="<?=cd_h($slug)?>"><input type="hidden" name="ref" value="<?=cd_h($r['ref'])?>">
    <?php if($r['state']==='pending'):?><?php $q=$db->prepare('SELECT status,expires FROM partner_mail_tokens WHERE ref=?');$q->execute([$r['ref']]);$mail=$q->fetch();?><p>Freigabe-E-Mail: <?=!$mail?'Nicht versendet':($mail['status']==='sent'?((int)$mail['expires']>time()?'An Mailserver übergeben':'Link abgelaufen'):'Versand fehlgeschlagen / nicht bestätigt')?></p><?php if($email!==''):?><button name="intent" value="mail-resend" formnovalidate>Freigabe-E-Mail erneut senden</button><?php endif?><label class="check full"><input type="checkbox" name="reviewed" value="1" required> Gesuch und Einwilligung geprüft</label><button name="intent" value="publish">14 Tage veröffentlichen</button><?php endif?><button class="danger" name="intent" value="delete" formnovalidate>Gesuch schließen und löschen</button></form>
    <?php foreach($r['interest'] as $i):?><form method="post" class="profile"><input type="hidden" name="csrf" value="<?=$csrf?>"><input type="hidden" name="action" value="partner-board"><input type="hidden" name="club" value="<?=cd_h($slug)?>"><input type="hidden" name="ref" value="<?=cd_h($r['ref'])?>"><input type="hidden" name="interest_id" value="<?=(int)$i['id']?>"><p>Kontaktanfrage (privat): <?=cd_h($i['contact'])?></p><button name="intent" value="interest-delete">Anfrage erledigt / löschen</button></form><?php endforeach?></details><?php endforeach?>
    <?php if(!$rows):?><p>Noch keine Gesuche.</p><?php endif;endif;
}
