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
    CREATE TABLE IF NOT EXISTS partner_mail_tokens (ref TEXT PRIMARY KEY REFERENCES partner_posts(ref) ON DELETE CASCADE, hash TEXT UNIQUE NOT NULL, expires INTEGER NOT NULL, status TEXT NOT NULL);
    CREATE TABLE IF NOT EXISTS partner_interest_share (id INTEGER PRIMARY KEY REFERENCES partner_interest(id) ON DELETE CASCADE);
    CREATE TABLE IF NOT EXISTS partner_post_age (ref TEXT PRIMARY KEY REFERENCES partner_posts(ref) ON DELETE CASCADE, age_group TEXT NOT NULL CHECK(age_group IN ('adult','teen','child')));
    CREATE TABLE IF NOT EXISTS partner_interest_age (id INTEGER PRIMARY KEY REFERENCES partner_interest(id) ON DELETE CASCADE, age_group TEXT NOT NULL CHECK(age_group IN ('adult','teen','child')));
    CREATE TABLE IF NOT EXISTS partner_post_meta (ref TEXT PRIMARY KEY REFERENCES partner_posts(ref) ON DELETE CASCADE, email TEXT NOT NULL, lk TEXT NOT NULL DEFAULT '', manage_hash TEXT NOT NULL DEFAULT '', reminded INTEGER NOT NULL DEFAULT 0);
    CREATE TABLE IF NOT EXISTS partner_interest_meta (id INTEGER PRIMARY KEY REFERENCES partner_interest(id) ON DELETE CASCADE, email TEXT NOT NULL, lk TEXT NOT NULL DEFAULT '');
    CREATE TABLE IF NOT EXISTS partner_verify (hash TEXT PRIMARY KEY, club TEXT NOT NULL REFERENCES clubs(slug) ON DELETE CASCADE, email TEXT NOT NULL, client TEXT NOT NULL, payload TEXT NOT NULL, created INTEGER NOT NULL, expires INTEGER NOT NULL);
    CREATE INDEX IF NOT EXISTS partner_verify_exp ON partner_verify(expires);
    CREATE TABLE IF NOT EXISTS partner_outbox (id INTEGER PRIMARY KEY AUTOINCREMENT, club TEXT NOT NULL, email TEXT NOT NULL, title TEXT NOT NULL, intro TEXT NOT NULL, details TEXT NOT NULL, actions TEXT NOT NULL, note TEXT NOT NULL, created INTEGER NOT NULL, attempts INTEGER NOT NULL DEFAULT 0, next_try INTEGER NOT NULL DEFAULT 0)");
}
function cd_partner_age_label(string $age):string{
    return ['adult'=>'Ab 18 Jahren','teen'=>'16–17 Jahre','child'=>'Unter 16 Jahren (Erziehungsberechtigte)'][$age]??'Nicht bekannt';
}
function cd_partner_post_age(PDO $db,string $ref):string{
    $q=$db->prepare('SELECT age_group FROM partner_post_age WHERE ref=?');$q->execute([$ref]);return (string)($q->fetchColumn()?:'adult');
}
function cd_partner_interest_age(PDO $db,int $id):string{
    $q=$db->prepare('SELECT age_group FROM partner_interest_age WHERE id=?');$q->execute([$id]);return (string)($q->fetchColumn()?:'adult');
}
function cd_partner_mail_headers(string $club):string{
    $headers="MIME-Version: 1.0\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: 8bit";
    if($club==='ntc93')$headers.="\r\nFrom: NTC 93 <gesucht@ntc93.de>\r\nReply-To: gesucht@ntc93.de";
    return $headers;
}

// Email-safe NTC layout: inline styles, table layout, no scripts or tracking.
// Public logo URL contains no member or moderation identifiers.
function cd_partner_mail_message(string $club,string $plain,string $title,string $intro,array $details,array $actions,string $note):array{
    if($club!=='ntc93')return ['body'=>$plain,'headers'=>cd_partner_mail_headers($club)];
    $e=static fn(string $value):string=>htmlspecialchars($value,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
    $rows='';
    foreach($details as $label=>$value){
        $rows.='<tr><td style="padding:10px 12px;border-bottom:1px solid #dce8e7;color:#426569;font-size:13px;vertical-align:top;width:35%">'.$e((string)$label).'</td><td style="padding:10px 12px;border-bottom:1px solid #dce8e7;font-size:15px;font-weight:600;color:#123b3d;overflow-wrap:anywhere;word-break:break-word">'.nl2br($e((string)$value),false).'</td></tr>';
    }
    $buttons='';
    foreach($actions as $action){
        $url=$action['url'];
        if(!str_starts_with($url,'https://clubdisplay.ntc93.de/'))throw new InvalidArgumentException('Ungültiger E-Mail-Link.');
        $secondary=($action['secondary']??false)===true;
        $style=$secondary?'background-color:#ffffff;color:#8b3434;border:1px solid #c78b8b;':'background-color:#f7dc14;color:#06383c;border:1px solid #f7dc14;';
        $buttons.='<tr><td style="padding:6px 0;"><table role="presentation" width="100%" cellspacing="0" cellpadding="0"><tr><td align="center" style="'.$style.'border-radius:8px;"><a href="'.$e($url).'" style="display:block;padding:15px 12px;color:'.($secondary?'#8b3434':'#06383c').';font-weight:bold;font-size:16px;text-decoration:none;">'.$e($action['label']).'</a></td></tr></table></td></tr>';
    }
    $html='<!doctype html><html lang="de"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>'.$e($title).'</title></head><body style="margin:0;padding:0;background-color:#edf3f2;font-family:Arial,Helvetica,sans-serif;color:#123b3d;"><table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background-color:#edf3f2;"><tr><td align="center" style="padding:20px 10px;"><table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:600px;background-color:#ffffff;border:1px solid #cbdcda;border-radius:14px;overflow:hidden;"><tr><td style="background-color:#06383c;padding:24px 20px;color:#ffffff;border-bottom:5px solid #f7dc14;"><table role="presentation" cellspacing="0" cellpadding="0"><tr><td style="padding-right:16px;vertical-align:middle;"><img src="https://clubdisplay.ntc93.de/assets/ntc93-logo.png" alt="NTC 93" width="64" height="64" style="display:block;width:64px;height:64px;object-fit:contain;"></td><td style="vertical-align:middle;"><div style="font-size:12px;font-weight:bold;letter-spacing:1.2px;color:#f7dc14;">NEUENHAGENER TENNISCLUB 93 E. V.</div><div style="font-size:23px;font-weight:bold;margin-top:6px;">Spielpartner gesucht</div></td></tr></table></td></tr><tr><td style="padding:26px 22px;"><h1 style="margin:0 0 12px;font-size:25px;line-height:1.2;color:#06383c;">'.$e($title).'</h1><p style="margin:0 0 20px;font-size:16px;line-height:1.55;">'.$e($intro).'</p><table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background-color:#f2f7f6;border:1px solid #dce8e7;border-radius:8px;">'.$rows.'</table>'.($buttons!==''?'<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="margin-top:18px;">'.$buttons.'</table>':'').'<p style="margin:20px 0 0;font-size:13px;line-height:1.55;color:#426569;">'.$e($note).'</p></td></tr><tr><td align="center" style="padding:17px 20px;background-color:#06383c;color:#d9e7e6;font-size:12px;line-height:1.6;">NTC 93 · Spielpartnerbörse<br>Powered by TENNILO ClubDisplay</td></tr></table></td></tr></table></body></html>';
    $boundary='cd-alt-'.bin2hex(random_bytes(18));
    $headers="MIME-Version: 1.0\r\nContent-Type: multipart/alternative; boundary=\"".$boundary."\"\r\nFrom: NTC 93 <gesucht@ntc93.de>\r\nReply-To: gesucht@ntc93.de";
    $encode=static fn(string $value):string=>quoted_printable_encode(preg_replace('/\r\n|\r|\n/',"\r\n",$value));
    $body='--'.$boundary."\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: quoted-printable\r\n\r\n".$encode($plain)."\r\n--".$boundary."\r\nContent-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: quoted-printable\r\n\r\n".$encode($html)."\r\n--".$boundary."--\r\n";
    return ['body'=>$body,'headers'=>$headers];
}
function cd_partner_mail_subject(string $club,string $subject):string{
    return ($club==='ntc93'?'NTC 93 - ':'TENNILO ClubDisplay - ').$subject;
}


function cd_partner_lk_choices():array{
    return [''=>'Keine Angabe','1-5'=>'LK 1–5','6-10'=>'LK 6–10','11-15'=>'LK 11–15','16-20'=>'LK 16–20','21-25'=>'LK 21–25'];
}
function cd_partner_email(mixed $value):string{
    $email=is_string($value)?trim($value):'';
    if(strlen($email)>254||!filter_var($email,FILTER_VALIDATE_EMAIL)||preg_match('/[\r\n]/',$email))throw new InvalidArgumentException('Bitte gültige E-Mail-Adresse eingeben.');
    return $email;
}
function cd_partner_url(string $club,string $action,string $token):string{
    return ($club==='ntc93'?'https://clubdisplay.ntc93.de/spielpartner/':'https://clubdisplay.tennilo.de/admin/').'?partner='.$action.'&club='.rawurlencode($club).'&token='.$token;
}
function cd_partner_notice(PDO $db,string $club,string $email,string $title,string $intro,string $ref,array $actions=[],string $note='Deine Kontaktdaten werden nicht öffentlich angezeigt.'):void{
    if($email==='')return;
    $q=$db->prepare('INSERT INTO partner_outbox(club,email,title,intro,details,actions,note,created) VALUES (?,?,?,?,?,?,?,?)');
    $q->execute([$club,$email,$title,$intro,json_encode($ref===''?[]:['Referenz'=>$ref],JSON_THROW_ON_ERROR),json_encode($actions,JSON_THROW_ON_ERROR),$note,time()]);
}
function cd_partner_outbox(PDO $db,?int $now=null,int $limit=30,?callable $send=null):int{
    $now??=time();cd_partner_mail_schema($db);
    $send??=static fn($to,$subject,$body,$headers)=>@mail($to,$subject,$body,$headers);
    $db->exec('BEGIN IMMEDIATE');
    try{
        // Keep the queue private and time limited; one worker at a time.
        $q=$db->prepare('DELETE FROM partner_outbox WHERE created<? OR attempts>=8');$q->execute([$now-604800]);
        $q=$db->prepare('SELECT * FROM partner_outbox WHERE next_try<=? ORDER BY id LIMIT ?');$q->bindValue(1,$now,PDO::PARAM_INT);$q->bindValue(2,$limit,PDO::PARAM_INT);$q->execute();$rows=$q->fetchAll();$count=0;
        foreach($rows as $r){
            $details=json_decode($r['details'],true,512,JSON_THROW_ON_ERROR);$actions=json_decode($r['actions'],true,512,JSON_THROW_ON_ERROR);
            $plain=$r['title']."\n\n".$r['intro']."\n";foreach($details as $k=>$v)$plain.=$k.': '.$v."\n";foreach($actions as $a)$plain.="\n".$a['label'].":\n".$a['url']."\n";$plain.="\n".$r['note'];
            try{$m=cd_partner_mail_message($r['club'],$plain,$r['title'],$r['intro'],$details,$actions,$r['note']);$ok=$send($r['email'],cd_partner_mail_subject($r['club'],$r['title']),$m['body'],$m['headers']);}catch(Throwable $e){$ok=false;}
            if($ok){$q=$db->prepare('DELETE FROM partner_outbox WHERE id=?');$q->execute([$r['id']]);$count++;}
            else{$q=$db->prepare('UPDATE partner_outbox SET attempts=attempts+1,next_try=? WHERE id=?');$q->execute([$now+min(21600,900*(2**(int)$r['attempts'])),$r['id']]);}
        }
        $db->exec('COMMIT');return $count;
    }catch(Throwable $e){$db->exec('ROLLBACK');throw $e;}
}
function cd_partner_flush(PDO $db):void{
    try{cd_partner_outbox($db,null,8);}catch(Throwable $e){error_log('ClubDisplay partner outbox pending.');}
}
function cd_partner_meta_email(PDO $db,string $ref,?int $id=null):string{
    $q=$db->prepare($id===null?'SELECT email FROM partner_post_meta WHERE ref=?':'SELECT email FROM partner_interest_meta WHERE id=?');$q->execute([$id??$ref]);return (string)($q->fetchColumn()?:'');
}
function cd_partner_post_decision_notice(PDO $db,string $club,string $ref,bool $publish):void{
    $email=cd_partner_meta_email($db,$ref);if($email==='')return;
    if(!$publish){cd_partner_notice($db,$club,$email,'Gesuch abgelehnt','Dein Spielpartnergesuch wurde nicht veröffentlicht. Bei Fragen wende dich bitte an deinen Verein.',$ref);return;}
    $token=bin2hex(random_bytes(32));$q=$db->prepare('UPDATE partner_post_meta SET manage_hash=?,reminded=0 WHERE ref=?');$q->execute([hash('sha256',$token),$ref]);
    $url=cd_partner_url($club,'manage',$token);
    cd_partner_notice($db,$club,$email,'Gesuch freigegeben','Dein Gesuch ist jetzt für 14 Tage anonym auf der Spielpartnerbörse sichtbar.',$ref,[['label'=>'Verlängerung beantragen','url'=>$url.'&decision=renew'],['label'=>'Gesuch zurückziehen','url'=>$url.'&decision=withdraw','secondary'=>true]],'Die Links sind persönlich. Eine Verlängerung wird erneut durch den Verein geprüft. Bitte nicht weiterleiten.');
}
function cd_partner_interest_decision_notice(PDO $db,string $club,string $ref,int $id,bool $approved):void{
    $email=cd_partner_meta_email($db,$ref,$id);
    cd_partner_notice($db,$club,$email,$approved?'Kontaktanfrage freigegeben':'Kontaktanfrage abgelehnt',$approved?'Deine Kontaktanfrage wurde durch den Verein geprüft und vermittelt. Bei Minderjährigen erfolgt die weitere Vermittlung ausschließlich persönlich über den Verein.':'Deine Kontaktanfrage wurde nicht freigegeben. Bei Fragen wende dich bitte an den Verein.',$ref);
}
function cd_partner_validate_submission(array $in,bool $structured):array{
    $age=$in['age_group']??'';
    if(!in_array($age,['adult','teen','child'],true)||($in['member']??'')!=='1'||($in['consent']??'')!=='1'||($in['website']??'')!=='')throw new InvalidArgumentException('Bitte Altersgruppe, Mitgliedschaft und Einwilligung bestätigen.');
    if($age==='child'&&($in['guardian']??'')!=='1')throw new InvalidArgumentException('Unter 16 Jahren darf nur eine erziehungsberechtigte Person die Anfrage stellen.');
    $mode=$in['mode']??'';
    if(!in_array($mode,['post','interest'],true))throw new InvalidArgumentException('Aktion auswählen.');
    if($mode==='interest'&&($in['share']??'')!=='1')throw new InvalidArgumentException('Bitte der geschützten Kontaktvermittlung zustimmen.');
    $fields=[];
    if($mode==='post'){foreach(cd_partner_choices() as $key=>$choices){$v=$in[$key]??'';if(!is_string($v)||!isset($choices[$v]))throw new InvalidArgumentException('Bitte Auswahl prüfen.');$fields[]=$v;}}
    $lk=$in['lk']??'';if(!is_string($lk)||!array_key_exists($lk,cd_partner_lk_choices()))throw new InvalidArgumentException('Ungültiger LK-Bereich.');
    $email='';$phone='';
    if($structured){
        $email=cd_partner_email($in['email']??null);
        $name=cd_text($in['name']??'',100,true);$phone=cd_text($in['phone']??'',50,false);
        $contact=$name."\n".$email.($phone!==''?"\n".$phone:'');
    }else{$contact=cd_text($in['contact']??'',200,true);}
    return [$age,$mode,$fields,$lk,$email,$contact];
}
function cd_partner_start_verification(PDO $db,string $club,array $in,string $client,?int $now=null,?callable $send=null):string{
    $now??=time();cd_partner_mail_schema($db);
    [$age,$mode,$fields,$lk,$email,$contact]=cd_partner_validate_submission($in,true);
    if(!cd_partner_enabled($db,$club))throw new InvalidArgumentException('Spielpartnerbörse nicht freigeschaltet.');
    if($mode==='interest'){
        $q=$db->prepare("SELECT 1 FROM partner_posts WHERE ref=? AND club=? AND state='published' AND expires>?");$q->execute([strtoupper(cd_text($in['ref']??'',10,true)),$club,$now]);if(!$q->fetchColumn())throw new InvalidArgumentException('Gesuch nicht mehr verfügbar.');
    }
    // Persist only known form fields; verification links must be confirmed by POST.
    $payload=array_intersect_key($in,array_flip(['age_group','member','consent','guardian','mode','share','category','skill','availability','period','lk','name','email','phone','ref']));
    $payload['email']=$email;$token=bin2hex(random_bytes(32));$hash=hash('sha256',$token);$client=hash('sha256',$club.'|'.$client);
    $db->exec('BEGIN IMMEDIATE');
    try{
        $q=$db->prepare('DELETE FROM partner_verify WHERE expires<=?');$q->execute([$now]);
        $q=$db->prepare('SELECT COUNT(*) FROM partner_verify WHERE club=? AND (email=? COLLATE NOCASE OR client=?) AND created>?');$q->execute([$club,$email,$client,$now-3600]);if((int)$q->fetchColumn()>=8)throw new InvalidArgumentException('Zu viele Bestätigungsanfragen. Bitte später erneut versuchen.');
        $q=$db->prepare('SELECT COUNT(*) FROM partner_verify WHERE club=?');$q->execute([$club]);if((int)$q->fetchColumn()>=200)throw new InvalidArgumentException('Bitte später erneut versuchen.');
        $q=$db->prepare('INSERT INTO partner_verify VALUES (?,?,?,?,?,?,?)');$q->execute([$hash,$club,$email,$client,json_encode($payload,JSON_THROW_ON_ERROR),$now,$now+86400]);
        $db->exec('COMMIT');
    }catch(Throwable $e){$db->exec('ROLLBACK');throw $e;}
    $url=cd_partner_url($club,'verify',$token);
    $plain="Bitte bestätige deine E-Mail-Adresse für die Spielpartnerbörse.\n".$url."\nDer Link ist 24 Stunden gültig. Falls du nichts beantragt hast, ignoriere diese E-Mail.";
    $m=cd_partner_mail_message($club,$plain,'E-Mail-Adresse bestätigen','Bitte bestätige deine E-Mail-Adresse. Erst danach wird deine Anfrage dem Verein zur Prüfung vorgelegt.',[],[['label'=>'E-Mail-Adresse bestätigen','url'=>$url]],'24 Stunden gültig. Wenn du keine Anfrage gestellt hast, ignoriere diese E-Mail.');
    $send??=static fn($to,$subject,$body,$headers)=>@mail($to,$subject,$body,$headers);
    if(!$send($email,cd_partner_mail_subject($club,'E-Mail-Adresse bestätigen'),$m['body'],$m['headers'])){$q=$db->prepare('DELETE FROM partner_verify WHERE hash=?');$q->execute([$hash]);throw new InvalidArgumentException('Bestätigungs-E-Mail konnte nicht versendet werden. Bitte später erneut versuchen.');}
    return $token;
}
function cd_partner_confirm(PDO $db,string $club,string $token,int $now):bool{
    if(!preg_match('/^[a-f0-9]{64}$/D',$token)||!cd_partner_enabled($db,$club))return false;
    $db->exec('BEGIN IMMEDIATE');
    try{
        $q=$db->prepare('SELECT payload,client FROM partner_verify WHERE hash=? AND club=? AND expires>?');$q->execute([hash('sha256',$token),$club,$now]);$r=$q->fetch();
        if(!$r){$db->exec('COMMIT');return false;}
        $q=$db->prepare('DELETE FROM partner_verify WHERE hash=?');$q->execute([hash('sha256',$token)]);$db->exec('COMMIT');
    }catch(Throwable $e){$db->exec('ROLLBACK');throw $e;}
    cd_partner_submit($db,$club,json_decode($r['payload'],true,512,JSON_THROW_ON_ERROR),$now,$r['client'],true);
    return true;
}
function cd_partner_manage(PDO $db,string $club,string $token,string $decision,int $now):bool{
    if(!preg_match('/^[a-f0-9]{64}$/D',$token)||!in_array($decision,['renew','withdraw'],true)||!cd_partner_enabled($db,$club))return false;
    $db->exec('BEGIN IMMEDIATE');
    try{
        $q=$db->prepare("SELECT p.ref,m.email FROM partner_posts p JOIN partner_post_meta m ON m.ref=p.ref WHERE p.club=? AND p.expires>? AND m.manage_hash=? AND (p.state='published' OR ?='withdraw')");$q->execute([$club,$now,hash('sha256',$token),$decision]);$r=$q->fetch();
        if(!$r){$db->exec('COMMIT');return false;}
        $ref=$r['ref'];
        if($decision==='withdraw'){
            cd_partner_notice($db,$club,$r['email'],'Gesuch zurückgezogen','Dein Gesuch wurde entfernt.',$ref);
            $q=$db->prepare('DELETE FROM partner_posts WHERE ref=?');$q->execute([$ref]);
        }else{
            $q=$db->prepare("UPDATE partner_posts SET state='pending',expires=? WHERE ref=?");$q->execute([$now+1209600,$ref]);
            $q=$db->prepare("UPDATE partner_post_meta SET reminded=0 WHERE ref=?");$q->execute([$ref]);
            cd_partner_notice($db,$club,$r['email'],'Verlängerung beantragt','Dein Gesuch wartet erneut auf Freigabe und ist bis dahin nicht sichtbar.',$ref);
        }
        $db->exec('COMMIT');
    }catch(Throwable $e){$db->exec('ROLLBACK');throw $e;}
    if($decision==='renew')cd_partner_mail_send($db,$club,$ref,$now);
    cd_partner_flush($db);return true;
}
function cd_partner_action_page(PDO $db,string $club,string $action):never{
    cd_partner_mail_schema($db);$method=$_SERVER['REQUEST_METHOD']??'';
    if(!in_array($method,['GET','POST'],true)){http_response_code(405);exit;}
    $in=$method==='POST'?$_POST:$_GET;$token=is_string($in['token']??null)?$in['token']:'';$decision=is_string($in['decision']??null)?$in['decision']:'';
    $title=$action==='verify'?'E-Mail bestätigen':($decision==='renew'?'Gesuch verlängern':'Gesuch zurückziehen');$message='';$valid=preg_match('/^[a-f0-9]{64}$/D',$token)&&($action==='verify'||in_array($decision,['renew','withdraw'],true));
    if($method==='POST'){
        if(isset($_SERVER['HTTP_ORIGIN'])&&$_SERVER['HTTP_ORIGIN']!=='https://clubdisplay.tennilo.de'){http_response_code(403);exit;}
        if((int)($_SERVER['CONTENT_LENGTH']??0)>4096){http_response_code(413);exit;}
        try{$ok=$valid&&($in['confirmed']??'')==='1'&&($action==='verify'?cd_partner_confirm($db,$club,$token,time()):cd_partner_manage($db,$club,$token,$decision,time()));$message=$ok?($action==='verify'?'E-Mail bestätigt. Deine Anfrage wird jetzt geprüft.':'Änderung gespeichert.'): 'Dieser Link ist ungültig, abgelaufen oder bereits verwendet.';if(!$ok)http_response_code(410);}
        catch(InvalidArgumentException $e){$message=$e->getMessage();http_response_code(400);}catch(Throwable $e){$message='Anfrage derzeit nicht möglich.';http_response_code(503);}
    }
    ?><!doctype html><html lang="de"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title><?=cd_h($title)?></title><link rel="stylesheet" href="admin.css?v=068"></head><body class="partner-page<?=$club==='ntc93'?' ntc-portal':''?>"><main><section class="panel"><?php if($club==='ntc93'):?><img class="portal-logo" src="../assets/ntc93-logo.png" alt="NTC 93"><?php endif?><h1><?=cd_h($title)?></h1><?php if($method==='POST'||!$valid):?><p><?=cd_h($message?:'Ungültiger Link.')?></p><?php else:?><form method="post" action="?partner=<?=cd_h($action)?>&amp;club=<?=rawurlencode($club)?>" class="profile"><input type="hidden" name="token" value="<?=cd_h($token)?>"><input type="hidden" name="decision" value="<?=cd_h($decision)?>"><label class="check full"><input type="checkbox" name="confirmed" value="1" required> Ich bestätige diese Aktion.</label><button><?=cd_h($title)?></button></form><?php endif?></section><footer class="portal-credit">Powered by TENNILO ClubDisplay</footer></main></body></html><?php
    exit;
}

function cd_partner_mail_address(PDO $db,string $club):string{
    $q=$db->prepare('SELECT email FROM partner_mail_settings WHERE club=?');$q->execute([$club]);$saved=$q->fetchColumn();
    // NTC's designated role mailbox; an explicitly saved address (including empty) overrides it.
    return $saved!==false?(string)$saved:($club==='ntc93'?'gesucht@ntc93.de':'');
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
        $base=($club==='ntc93'?'https://clubdisplay.ntc93.de/spielpartner/':'https://clubdisplay.tennilo.de/admin/').'?partner=moderate&club='.rawurlencode($club).'&token='.$notice['token'];
        $body="Ein neues Spielpartnergesuch wartet auf Freigabe.\n\nVerein: ".cd_club($db,$club)['name']."\nReferenz: ".$ref."\n";
        foreach($choices as $key=>$options){$body.=$options[$row[$key]]."\n";}
        $body.="\nGesuch freigeben (mit Bestätigung):\n".$base."&decision=publish\n\nGesuch ablehnen (mit Bestätigung):\n".$base."&decision=delete\n\nDie Links sind einmalig verwendbar und höchstens 48 Stunden gültig. Private Kontaktdaten stehen nur auf der Prüfseite. Bitte diese Nachricht nicht weiterleiten.\n";
        $details=['Referenz'=>$ref];
        foreach(['category'=>'Spielart','skill'=>'Spielstärke','availability'=>'Wochentag','period'=>'Tageszeit'] as $key=>$label)$details[$label]=$choices[$key][$row[$key]];
        $q=$db->prepare('SELECT lk FROM partner_post_meta WHERE ref=?');$q->execute([$ref]);$lk=(string)($q->fetchColumn()?:'');if($lk!==''){$details['LK-Bereich']=cd_partner_lk_choices()[$lk]??'';$body.="\nLK-Bereich: ".$details['LK-Bereich'];}
        $message=cd_partner_mail_message($club,$body,'Neues Spielpartnergesuch','Ein neues Gesuch wartet auf deine Prüfung. Bitte prüfe Mitgliedschaft, Identität und gegebenenfalls den Jugendschutz vor der Freigabe.',$details,[['label'=>'Gesuch prüfen und freigeben','url'=>$base.'&decision=publish'],['label'=>'Gesuch prüfen und ablehnen','url'=>$base.'&decision=delete','secondary'=>true]],'Die Links sind einmalig verwendbar und höchstens 48 Stunden gültig. Die Entscheidung erfolgt erst auf der Prüfseite. Keine privaten Kontaktdaten in dieser Nachricht. Bitte nicht weiterleiten.');
        $sent=false;
        try{$sent=@mail($notice['email'],cd_partner_mail_subject($club,'Neues Spielpartnergesuch'),$message['body'],$message['headers']);}catch(Throwable $e){}
        $q=$db->prepare('UPDATE partner_mail_tokens SET status=? WHERE ref=? AND hash=?');$q->execute([$sent?'sent':'failed',$ref,hash('sha256',$notice['token'])]);
    }catch(Throwable $e){error_log('ClubDisplay partner notification could not be prepared or sent.');}
}
function cd_partner_mail_lookup(PDO $db,string $club,string $token,int $now):?array{
    if(!preg_match('/^[a-f0-9]{64}$/D',$token)||!cd_partner_enabled($db,$club))return null;
    $q=$db->prepare("SELECT p.* FROM partner_mail_tokens t JOIN partner_posts p ON p.ref=t.ref WHERE t.hash=? AND p.club=? AND p.state='pending' AND t.expires>? AND p.expires>?");
    $q->execute([hash('sha256',$token),$club,$now,$now]);return $q->fetch()?:null;
}
function cd_partner_mail_decide(PDO $db,string $club,string $token,string $decision,int $now,bool $youthReviewed=false):bool{
    if(!in_array($decision,['publish','delete'],true))return false;
    $db->exec('BEGIN IMMEDIATE');
    try{
        $row=cd_partner_mail_lookup($db,$club,$token,$now);
        if(!$row){$db->exec('COMMIT');return false;}
        if($decision==='publish'){
            if(cd_partner_post_age($db,$row['ref'])!=='adult'&&!$youthReviewed){$db->exec('COMMIT');return false;}
            $q=$db->prepare("UPDATE partner_posts SET state='published',expires=? WHERE ref=? AND club=? AND state='pending'");$q->execute([$now+1209600,$row['ref'],$club]);cd_partner_post_decision_notice($db,$club,$row['ref'],true);
        }else{
            cd_partner_post_decision_notice($db,$club,$row['ref'],false);
            $q=$db->prepare("DELETE FROM partner_posts WHERE ref=? AND club=? AND state='pending'");$q->execute([$row['ref'],$club]);
        }
        $q=$db->prepare('DELETE FROM partner_mail_tokens WHERE ref=?');$q->execute([$row['ref']]);
        $db->exec('COMMIT');cd_partner_flush($db);return true;
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
        if(($input['confirmed']??'')==='1'&&cd_partner_mail_decide($db,$club,$token,$decision,$now,($input['youth_reviewed']??'')==='1')){
            $message=$decision==='publish'?'Gesuch freigegeben und für 14 Tage veröffentlicht.':'Gesuch abgelehnt und gelöscht.';
        }
    }elseif(in_array($decision,['publish','delete'],true)){$row=cd_partner_mail_lookup($db,$club,$token,$now);}
    if(!$row&&$message===''){http_response_code(410);$message='Dieser Freigabelink ist ungültig, abgelaufen oder wurde bereits verwendet. Bitte im Vereinsbackend prüfen.';}
    ?><!doctype html><html lang="de"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>Spielpartnergesuch prüfen</title><link rel="stylesheet" href="admin.css?v=068"></head><body class="partner-page<?=$club==='ntc93'?' ntc-portal':''?>"><main><section class="panel"><?php if($club==='ntc93'):?><img class="portal-logo" src="../assets/ntc93-logo.png" alt="NTC 93"><?php endif?><span class="member-brand"><?=$club==='ntc93'?'NTC 93':'TENNILO ClubDisplay'?></span><h1>Spielpartnergesuch prüfen</h1>
    <?php if($row):$choices=cd_partner_choices();?><p><?=cd_h(cd_club($db,$club)['name'].' · '.$row['ref'])?></p><p><?=cd_h(implode(' · ',array_map(fn($key)=>$choices[$key][$row[$key]],array_keys($choices))))?></p><p>Privater Kontakt: <?=cd_h($row['contact'])?></p><p>Altersgruppe (privat): <?=cd_h(cd_partner_age_label(cd_partner_post_age($db,$row['ref'])))?></p>
    <form method="post" action="?partner=moderate&amp;club=<?=rawurlencode($club)?>" class="profile"><input type="hidden" name="token" value="<?=cd_h($token)?>"><input type="hidden" name="decision" value="<?=cd_h($decision)?>"><label class="check full"><input type="checkbox" name="confirmed" value="1" required> <?= $decision==='publish'?'Vereinsmitgliedschaft, Gesuch und Einwilligung geprüft':'Ich möchte dieses Gesuch ablehnen und löschen' ?></label><?php if($decision==='publish'&&cd_partner_post_age($db,$row['ref'])!=='adult'):?><label class="check full"><input type="checkbox" name="youth_reviewed" value="1" required> Altersgruppe, Identität und Jugendschutz geprüft; bei unter 16 Jahren Erziehungsberechtigung und Zustimmung unabhängig bestätigt. Vermittlung ausschließlich über den Verein.</label><?php endif?><button class="<?=$decision==='delete'?'danger':''?>"><?=$decision==='publish'?'Jetzt freigeben':'Jetzt ablehnen'?></button></form>
    <?php else:?><p><?=cd_h($message)?></p><?php endif?></section><?php if($club==='ntc93'):?><footer class="portal-credit">Powered by TENNILO ClubDisplay</footer><?php endif?></main></body></html><?php
    exit;
}


function cd_partner_interest_notify(PDO $db,string $club,string $ref):void{
    try{
        $email=cd_partner_mail_address($db,$club);if($email==='')return;
        $url=$club==='ntc93'?'https://clubdisplay.ntc93.de/admin/?partner_review=1':'https://clubdisplay.tennilo.de/admin/?portal=club&partner_review=1';
        $body="Neue Kontaktanfrage zu einem Spielpartnergesuch.\nVerein: ".cd_club($db,$club)['name']."\nReferenz: ".$ref."\n\nBitte im geschützten Vereinsbackend beide Mitgliedschaften und Kontaktangaben prüfen. Erst dann den Kontakt vermitteln.\n".$url."\n\nKeine privaten Kontaktdaten in dieser Nachricht.";
        $message=cd_partner_mail_message($club,$body,'Neue Kontaktanfrage','Ein Mitglied hat Interesse an einem veröffentlichten Gesuch bekundet. Bitte prüfe beide Mitgliedschaften und Identitäten.',['Referenz'=>$ref],[['label'=>'Kontaktanfrage im Vereinsbackend prüfen','url'=>$url]],'Keine privaten Kontaktdaten in dieser Nachricht. Sobald Minderjährige beteiligt sind, vermittelt der Verein persönlich und geschützt.');
        if(!@mail($email,cd_partner_mail_subject($club,'Neue Kontaktanfrage'),$message['body'],$message['headers']))error_log('ClubDisplay interest notification not accepted.');
    }catch(Throwable $e){error_log('ClubDisplay interest notification failed.');}
}
// Recipient must be independently verified by the administrator, not taken from an unverified submission.
function cd_partner_interest_forward(PDO $db,string $club,string $ref,int $id,string $email,bool $reviewed,int $now,?callable $send=null):void{
    if(!$reviewed||strlen($email)>254||!filter_var($email,FILTER_VALIDATE_EMAIL)||preg_match('/[\r\n]/',$email))throw new InvalidArgumentException('Bitte Mitgliedschaften, Identität und Empfängeradresse prüfen.');
    if(!cd_partner_enabled($db,$club))throw new InvalidArgumentException('Spielpartnerbörse nicht freigeschaltet.');
    $q=$db->prepare("SELECT i.contact FROM partner_interest i JOIN partner_posts p ON p.ref=i.ref JOIN partner_interest_share s ON s.id=i.id WHERE i.id=? AND i.ref=? AND p.club=? AND p.state='published' AND i.expires>? AND p.expires>?");
    $q->execute([$id,$ref,$club,$now,$now]);$contact=$q->fetchColumn();
    if($contact===false)throw new InvalidArgumentException('Kontaktanfrage abgelaufen oder keine ausdrückliche Einwilligung zur Weitergabe vorhanden.');
    if(cd_partner_post_age($db,$ref)!=='adult'||cd_partner_interest_age($db,$id)!=='adult')throw new InvalidArgumentException('Bei Minderjährigen ist eine direkte Weiterleitung ausgeschlossen. Vermittlung nur durch den Verein.');
    $confirmed=cd_partner_meta_email($db,$ref);if($confirmed!==''&&strcasecmp($confirmed,$email)!==0)throw new InvalidArgumentException('Empfängeradresse stimmt nicht mit der bestätigten Adresse überein. Bitte durch den Verein klären.');
    $body="Ein vom Verein geprüftes Mitglied interessiert sich für dein Spielpartnergesuch ".$ref.".\n\nKontakt des interessierten Mitglieds:\n".$contact."\n\nDu entscheidest selbst, ob du Kontakt aufnehmen möchtest. Bitte nicht weitergeben.";
    $send??=static fn($to,$subject,$body,$headers)=>@mail($to,$subject,$body,$headers);
    $message=cd_partner_mail_message($club,$body,'Ein Spielpartner hat Interesse!','Ein vom Verein geprüftes Mitglied möchte mit dir Tennis spielen.',['Referenz'=>$ref,'Kontakt des interessierten Mitglieds'=>$contact],[],'Du entscheidest selbst, ob du Kontakt aufnimmst. Diese Kontaktdaten sind nur für dich bestimmt. Bitte nicht weitergeben.');
    if(!$send($email,cd_partner_mail_subject($club,'Kontakt zu deinem Spielpartnergesuch'),$message['body'],$message['headers']))throw new InvalidArgumentException('E-Mail-Versand fehlgeschlagen. Anfrage bleibt gespeichert.');
    cd_partner_interest_decision_notice($db,$club,$ref,$id,true);
    $q=$db->prepare('DELETE FROM partner_interest WHERE id=? AND ref=?');$q->execute([$id,$ref]);
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
    $now??=time();cd_partner_mail_schema($db);
    $q=$db->prepare('DELETE FROM partner_verify WHERE expires<=?');$q->execute([$now]);
    $q=$db->prepare("SELECT p.club,p.ref,p.state,p.expires,m.email,m.reminded FROM partner_posts p JOIN partner_post_meta m ON m.ref=p.ref WHERE p.expires<=?");$q->execute([$now+172800]);
    foreach($q->fetchAll() as $r){
        if((int)$r['expires']<=$now){$delete=$db->prepare('DELETE FROM partner_posts WHERE ref=? AND expires<=?');$delete->execute([$r['ref'],$now]);if($delete->rowCount())cd_partner_notice($db,$r['club'],$r['email'],$r['state']==='published'?'Gesuch abgelaufen':'Gesuch nicht veröffentlicht','Dein Gesuch ist abgelaufen und wurde entfernt. Du kannst jederzeit ein neues Gesuch erstellen.',$r['ref']);}
        elseif($r['state']==='published'&&!(int)$r['reminded']){
            $u=$db->prepare('UPDATE partner_post_meta SET reminded=1 WHERE ref=? AND reminded=0');$u->execute([$r['ref']]);
            if($u->rowCount())cd_partner_notice($db,$r['club'],$r['email'],'Gesuch läuft bald ab','Dein Gesuch endet innerhalb der nächsten zwei Tage. Verwende zur Verlängerung oder Rücknahme die persönlichen Links aus deiner Freigabe-E-Mail.',$r['ref']);
        }
    }
    foreach(['partner_invites','partner_interest','partner_posts'] as $table){$q=$db->prepare('DELETE FROM '.$table.' WHERE expires<=?');$q->execute([$now]);}
}
function cd_partner_rows(PDO $db,string $club,?int $now=null):array{
    $now??=time();if(!cd_partner_enabled($db,$club))return [];
    cd_partner_mail_schema($db);$q=$db->prepare("SELECT p.ref,p.category,p.skill,p.availability,p.period,p.expires,COALESCE(m.lk,'') AS lk FROM partner_posts p LEFT JOIN partner_post_meta m ON p.ref=m.ref WHERE p.club=? AND p.state='published' AND p.expires>? ORDER BY p.created DESC LIMIT 30");$q->execute([$club,$now]);return $q->fetchAll();
}
function cd_partner_admin_rows(PDO $db,string $club,?string $scope,?int $now=null):array{
    cd_scope($scope,$club);cd_partner_mail_schema($db);$now??=time();
    $q=$db->prepare('SELECT * FROM partner_posts WHERE club=? AND expires>? ORDER BY created DESC LIMIT 100');$q->execute([$club,$now]);$rows=$q->fetchAll();
    $q=$db->prepare('SELECT i.id,i.contact,i.created,i.expires,EXISTS(SELECT 1 FROM partner_interest_share s WHERE s.id=i.id) AS share FROM partner_interest i WHERE i.ref=? AND i.expires>? ORDER BY i.created');
    foreach($rows as &$r){$r['age_group']=cd_partner_post_age($db,$r['ref']);$meta=$db->prepare('SELECT lk FROM partner_post_meta WHERE ref=?');$meta->execute([$r['ref']]);$r['lk']=(string)($meta->fetchColumn()?:'');$q->execute([$r['ref'],$now]);$r['interest']=$q->fetchAll();foreach($r['interest'] as &$i){$i['age_group']=cd_partner_interest_age($db,(int)$i['id']);$meta=$db->prepare('SELECT lk FROM partner_interest_meta WHERE id=?');$meta->execute([$i['id']]);$i['lk']=(string)($meta->fetchColumn()?:'');}unset($i);}unset($r);return $rows;
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
            if(!$enabled){$q=$db->prepare('DELETE FROM partner_verify WHERE club=?');$q->execute([$club]);$q=$db->prepare('DELETE FROM partner_outbox WHERE club=?');$q->execute([$club]);$q=$db->prepare('DELETE FROM partner_invites WHERE club=?');$q->execute([$club]);$q=$db->prepare('DELETE FROM partner_posts WHERE club=?');$q->execute([$club]);}
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
        }elseif(in_array($intent,['publish','delete','interest-delete','interest-forward','interest-youth-complete'],true)){
            $ref=cd_text($in['ref']??'',32,true);
            $q=$db->prepare('SELECT * FROM partner_posts WHERE ref=? AND club=? AND expires>?');$q->execute([$ref,$club,$now]);$row=$q->fetch();if(!$row)throw new InvalidArgumentException('Gesuch nicht gefunden.');
            if($intent==='publish'){
                if(!cd_partner_enabled($db,$club)||($in['reviewed']??'')!=='1'||$row['state']!=='pending')throw new InvalidArgumentException('Freigabe und Prüfung erforderlich.');
                if(cd_partner_post_age($db,$ref)!=='adult'&&($in['youth_reviewed']??'')!=='1')throw new InvalidArgumentException('Zusätzliche Jugendschutzprüfung erforderlich.');
                $q=$db->prepare("UPDATE partner_posts SET state='published',expires=? WHERE ref=? AND club=? AND state='pending'");$q->execute([$now+1209600,$ref,$club]);cd_partner_post_decision_notice($db,$club,$ref,true);
            }elseif($intent==='delete'){cd_partner_post_decision_notice($db,$club,$ref,false);$q=$db->prepare('DELETE FROM partner_posts WHERE ref=? AND club=?');$q->execute([$ref,$club]);}
            else{$id=filter_var($in['interest_id']??null,FILTER_VALIDATE_INT);if(!$id)throw new InvalidArgumentException('Ungültige Anfrage.');
                if($intent==='interest-forward')cd_partner_interest_forward($db,$club,$ref,$id,trim(is_string($in['verified_email']??null)?$in['verified_email']:''),($in['verified_both']??'')==='1',$now);
                else{
                    if($intent==='interest-youth-complete'){
                        $q=$db->prepare("SELECT 1 FROM partner_interest WHERE id=? AND ref=? AND expires>?");$q->execute([$id,$ref,$now]);
                        if(!$q->fetchColumn()||$row['state']!=='published'||(cd_partner_post_age($db,$ref)==='adult'&&cd_partner_interest_age($db,$id)==='adult')||($in['verified_youth']??'')!=='1')throw new InvalidArgumentException('Geschützte Vermittlung und Jugendschutzprüfung bestätigen.');
                    }
                    cd_partner_interest_decision_notice($db,$club,$ref,$id,$intent==='interest-youth-complete');
                    $q=$db->prepare('DELETE FROM partner_interest WHERE id=? AND ref=?');$q->execute([$id,$ref]);
                }
            }
        }else throw new InvalidArgumentException('Unbekannte Aktion.');
        $db->exec('COMMIT');
    }catch(Throwable $e){$db->exec('ROLLBACK');throw $e;}
    if($sendRef!==null)cd_partner_mail_send($db,$club,$sendRef,$now);cd_partner_flush($db);return $token;
}
function cd_partner_submit(PDO $db,string $club,array $in,?int $now=null,?string $client=null,bool $verified=false):void{
    $now??=time();
    [$age,$mode,$fields,$lk,$email,$contact]=cd_partner_validate_submission($in,$verified);
    cd_partner_mail_schema($db);$db->exec('CREATE TABLE IF NOT EXISTS partner_rate (club TEXT NOT NULL, client TEXT NOT NULL, created INTEGER NOT NULL); CREATE INDEX IF NOT EXISTS partner_rate_created ON partner_rate(created)');
    $db->exec('BEGIN IMMEDIATE');
    try{
        cd_partner_cleanup($db,$now);
        if(!cd_partner_enabled($db,$club))throw new InvalidArgumentException('Spielpartnerbörse nicht freigeschaltet.');
        $q=$db->prepare('DELETE FROM partner_rate WHERE created<=?');$q->execute([$now-3600]);
        if($client!==null){
            $key=hash('sha256',$club.'|'.$client);
            $q=$db->prepare('SELECT COUNT(*) FROM partner_rate WHERE club=? AND client=? AND created>?');$q->execute([$club,$key,$now-900]);if((int)$q->fetchColumn()>=12)throw new InvalidArgumentException('Zu viele Anfragen. Bitte später erneut versuchen.');
            $q=$db->prepare('SELECT COUNT(*) FROM partner_rate WHERE club=?');$q->execute([$club]);if((int)$q->fetchColumn()>=40)throw new InvalidArgumentException('Zu viele Anfragen. Bitte später erneut versuchen.');
            $q=$db->prepare('INSERT INTO partner_rate VALUES (?,?,?)');$q->execute([$club,$key,$now]);
        }
        if($mode==='post'){
            $q=$db->prepare('SELECT COUNT(*) FROM partner_posts WHERE club=?');$q->execute([$club]);if((int)$q->fetchColumn()>=100)throw new InvalidArgumentException('Börse derzeit ausgelastet.');
            $q=$db->prepare("SELECT 1 FROM partner_posts WHERE club=? AND contact=? AND category=? AND skill=? AND availability=? AND period=? AND expires>?");$q->execute([$club,$contact,...$fields,$now]);if($q->fetchColumn())throw new InvalidArgumentException('Dieses Gesuch ist bereits vorhanden.');
            $ref=strtoupper(bin2hex(random_bytes(5)));
            $q=$db->prepare("INSERT INTO partner_posts VALUES (?,?,?,?,?,?,?,'pending',?,?)");$q->execute([$ref,$club,...$fields,$contact,$now,$now+1209600]);
            $q=$db->prepare('INSERT INTO partner_post_age VALUES (?,?)');$q->execute([$ref,$age]);
            if($verified){$manage=bin2hex(random_bytes(32));$q=$db->prepare('INSERT INTO partner_post_meta(ref,email,lk,manage_hash) VALUES (?,?,?,?)');$q->execute([$ref,$email,$lk,hash('sha256',$manage)]);cd_partner_notice($db,$club,$email,'Gesuch eingegangen','Deine E-Mail-Adresse wurde bestätigt. Dein Gesuch wartet auf die Freigabe durch den Verein.',$ref,[['label'=>'Gesuch zurückziehen','url'=>cd_partner_url($club,'manage',$manage).'&decision=withdraw','secondary'=>true]],'Der Link ist persönlich. Bitte nicht weiterleiten.');}
        }else{
            $ref=strtoupper(cd_text($in['ref']??'',10,true));
            $q=$db->prepare("SELECT expires FROM partner_posts WHERE ref=? AND club=? AND state='published' AND expires>?");$q->execute([$ref,$club,$now]);$expires=$q->fetchColumn();if(!$expires)throw new InvalidArgumentException('Gesuch nicht mehr verfügbar.');
            $q=$db->prepare('SELECT COUNT(*) FROM partner_interest WHERE ref=?');$q->execute([$ref]);if((int)$q->fetchColumn()>=30)throw new InvalidArgumentException('Zu viele offene Anfragen.');
            $q=$db->prepare('SELECT 1 FROM partner_interest WHERE ref=? AND contact=? AND expires>?');$q->execute([$ref,$contact,$now]);if($q->fetchColumn())throw new InvalidArgumentException('Diese Kontaktanfrage ist bereits vorhanden.');
            $q=$db->prepare('INSERT INTO partner_interest(ref,contact,created,expires) VALUES (?,?,?,?)');$q->execute([$ref,$contact,$now,min((int)$expires,$now+1209600)]);
            $id=(int)$db->lastInsertId();$q=$db->prepare('INSERT INTO partner_interest_share VALUES (?)');$q->execute([$id]);
            $q=$db->prepare('INSERT INTO partner_interest_age VALUES (?,?)');$q->execute([$id,$age]);
            if($verified){$q=$db->prepare('INSERT INTO partner_interest_meta VALUES (?,?,?)');$q->execute([$id,$email,$lk]);cd_partner_notice($db,$club,$email,'Kontaktanfrage eingegangen','Deine E-Mail-Adresse wurde bestätigt. Der Verein prüft deine Kontaktanfrage.',$ref);}
        }
        $db->exec('COMMIT');
    }catch(Throwable $e){$db->exec('ROLLBACK');throw $e;}
    if($mode==='post')cd_partner_mail_send($db,$club,$ref,$now);else cd_partner_interest_notify($db,$club,$ref);
    if($verified)cd_partner_flush($db);
}
function cd_partner_public(PDO $db):never{
    $club=is_string($_GET['club']??null)?$_GET['club']:'';
    if(!preg_match('/^[a-z0-9][a-z0-9-]{1,48}[a-z0-9]$/D',$club)){http_response_code(404);exit;}
    $action=$_GET['partner']??'';
    if($action==='moderate')cd_partner_mail_page($db,$club);
    if(in_array($action,['verify','manage'],true))cd_partner_action_page($db,$club,$action);
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
        try{cd_partner_start_verification($db,$club,$_POST,$_SERVER['REMOTE_ADDR']??'unknown');$success=true;}catch(InvalidArgumentException $e){$error=$e->getMessage();}catch(Throwable $e){http_response_code(503);$error='Anfrage derzeit nicht möglich.';}
    }
    $c=cd_club($db,$club);$choices=cd_partner_choices();$publicRows=cd_partner_rows($db,$club);
    $mode=($_POST['mode']??$_GET['mode']??'')==='interest'?'interest':'post';
    $selected=is_string($_POST['ref']??$_GET['ref']??null)?strtoupper($_POST['ref']??$_GET['ref']):'';
    ?><!doctype html><html lang="de"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>Spielpartnerbörse</title><link rel="stylesheet" href="admin.css?v=068"></head><body class="partner-page<?=$club==='ntc93'?' ntc-portal':''?>"><main><section class="panel"><?php if($club==='ntc93'):?><img class="portal-logo" src="../assets/ntc93-logo.png" alt="NTC 93"><?php endif?><span class="member-brand"><?=$club==='ntc93'?'NTC 93':'TENNILO ClubDisplay'?> · <?=cd_h($c['name'])?></span><h1>Spielpartner gesucht</h1>
    <?php if($success):?><p class="ok">Bitte bestätige deine E-Mail-Adresse über den Link in deinem Postfach. Erst danach wird deine Anfrage dem Verein zur Prüfung vorgelegt. Bitte auch den Spamordner prüfen.</p>
    <?php else:?><?php if($error):?><p class="error"><?=cd_h($error)?></p><?php endif?>
    <p>Für Vereinsmitglieder aller Altersgruppen. Die Teilnahme ist freiwillig und kostenlos.</p><section class="subpanel"><h2>Teilnahme nach Alter</h2><p><strong>Ab 18 Jahren:</strong> reguläre Teilnahme.</p><p><strong>16–17 Jahre:</strong> zusätzliche Prüfung und geschützte Vermittlung durch den Verein.</p><p><strong>Unter 16 Jahren:</strong> ausschließlich über die Erziehungsberechtigten. Nur deren Kontaktdaten angeben.</p></section>
    <section class="subpanel" aria-labelledby="partner-safety"><h2 id="partner-safety">So schützen wir deine Daten</h2><ol><li><strong>Geprüfte Gesuche:</strong> Der Verein prüft die Mitgliedschaft und das Gesuch vor der Veröffentlichung. Sichtbar sind nur Spielwünsche, der freiwillig angegebene LK-Bereich und eine anonyme Referenz.</li><li><strong>Geprüfte Kontaktvermittlung:</strong> Auch Kontaktanfragen werden durch den Verein geprüft. Nur wenn beide Beteiligten volljährig sind, können nach Prüfung und Einwilligung Kontaktdaten an die unabhängig bestätigte E-Mail-Adresse des Gesuchstellers übermittelt werden. Sobald Minderjährige beteiligt sind, vermittelt der Verein persönlich und geschützt; das System leitet keine privaten Kontaktdaten an andere Teilnehmer weiter. Unter 16 Jahren erfolgt die Vermittlung ausschließlich über die Erziehungsberechtigten.</li></ol><p>Dein Name, deine Telefonnummer und deine E-Mail-Adresse sind weder auf dem ClubDisplay noch im öffentlichen Formular einsehbar. Bei Erwachsenen entscheidest du selbst, ob du anschließend Kontakt aufnimmst.</p><p>Gesuche sind höchstens 14 Tage nach Freigabe sichtbar. Du kannst dein Gesuch über den persönlichen Link in der Freigabe-E-Mail zurückziehen oder eine Verlängerung beantragen.</p></section>
    <div class="partner-mode"><a class="<?=$mode==='post'?'active':''?>" href="?partner=submit&amp;club=<?=rawurlencode($club)?>">Gesuch erstellen</a><a class="<?=$mode==='interest'?'active':''?>" href="?partner=submit&amp;club=<?=rawurlencode($club)?>&amp;mode=interest">Spielpartner finden</a></div>
    <form method="post" class="profile"><input type="hidden" name="mode" value="<?=cd_h($mode)?>"><div hidden><label>Website<input name="website" tabindex="-1" autocomplete="off"></label></div>
    <?php if($mode==='post'):?><h2 class="form-section">1. Spielwünsche</h2>
    <?php foreach($choices as $key=>$options):?><label><?=cd_h(['category'=>'Spielart','skill'=>'Spielstärke','availability'=>'Wochentag','period'=>'Tageszeit'][$key])?><select name="<?=cd_h($key)?>"><?php foreach($options as $v=>$label):?><option value="<?=cd_h($v)?>"><?=cd_h($label)?></option><?php endforeach?></select></label><?php endforeach?>
    <?php else:?><h2 class="form-section">1. Gesuch auswählen</h2><label class="full">Veröffentlichte Gesuche<select name="ref" required><option value="">Bitte auswählen</option><?php foreach($publicRows as $p):?><option value="<?=cd_h($p['ref'])?>" <?=$selected===$p['ref']?'selected':''?>><?=cd_h($p['ref'].' · '.$choices['category'][$p['category']].' · '.$choices['skill'][$p['skill']].' · '.$choices['availability'][$p['availability']].' · '.$choices['period'][$p['period']].(!empty($p['lk'])?' · '.(cd_partner_lk_choices()[$p['lk']]??''):''))?></option><?php endforeach?></select></label><?php if(!$publicRows):?><p class="form-hint full">Aktuell gibt es keine veröffentlichten Gesuche.</p><?php endif?><?php endif?>
    <label class="full">LK-Bereich (optional)<select name="lk"><?php foreach(cd_partner_lk_choices() as $value=>$label):?><option value="<?=cd_h($value)?>"><?=cd_h($label)?></option><?php endforeach?></select></label>
    <h2 class="form-section">2. Kontakt &amp; Einwilligung</h2><label class="full">Altersgruppe der teilnehmenden Person<select name="age_group" required><option value="">Bitte auswählen</option><option value="adult">Ab 18 Jahren</option><option value="teen">16–17 Jahre</option><option value="child">Unter 16 Jahren – über Erziehungsberechtigte</option></select></label><p class="form-hint full">Unter 16 Jahren: Nur die erziehungsberechtigte Person füllt dieses Formular aus und gibt ihren eigenen Namen und ihre eigene Kontaktmöglichkeit an. Keine Telefonnummer oder E-Mail-Adresse des Kindes eintragen.</p><label class="full">Vor- und Nachname<input name="name" required maxlength="100" autocomplete="name"></label><label class="full">E-Mail-Adresse (Pflichtfeld)<input name="email" type="email" required maxlength="254" autocomplete="email"></label><label class="full">Handy / Telefon (optional)<input name="phone" type="tel" maxlength="50" autocomplete="tel"></label><p class="form-hint full">Die E-Mail-Adresse muss per Bestätigungslink bestätigt werden. Über diese Adresse erhältst du alle Statusmeldungen zu deiner Anfrage.</p>
    <label class="check full"><input type="checkbox" name="member" value="1" required> Die teilnehmende Person ist Mitglied dieses Vereins; die Altersgruppe ist korrekt.</label><label class="check full"><input type="checkbox" name="guardian" value="1"> Nur bei unter 16 Jahren: Ich bin erziehungsberechtigt, stelle diese Anfrage für mein Kind und gebe ausschließlich meine eigenen Kontaktdaten an.</label>
    <label class="check full"><input type="checkbox" name="consent" value="1" required> Ich willige in die Verarbeitung durch den Verein zur Spielpartnervermittlung ein. Nur die gewählten Kategorien, der freiwillige LK-Bereich und die anonyme Referenz werden nach Prüfung veröffentlicht. Gesuche laufen nach höchstens 14 Tagen ab; ich kann sie jederzeit über den Verein zurückziehen. Kontaktdaten werden nicht veröffentlicht.</label>
    <?php if($mode==='interest'):?><label class="check full"><input type="checkbox" name="share" value="1" required> Ich stimme der geschützten Vermittlung durch den Verein zu. Nur wenn beide Beteiligten volljährig sind, darf der Verein meine angegebenen Kontaktdaten an den unabhängig geprüften Gesuchsteller weiterleiten. Andernfalls vermittelt ausschließlich der Verein persönlich.</label><?php endif?>
    <button class="partner-submit" <?=$mode==='interest'&&!$publicRows?'disabled':''?>>Anfrage absenden</button></form><?php endif?></section><?php if($club==='ntc93'):?><footer class="portal-credit">Powered by TENNILO ClubDisplay</footer><?php endif?></main></body></html><?php
    exit;
}
function cd_partner_field(PDO $db,array $club,string $csrf,?string $scope):void{
    $slug=$club['slug'];cd_partner_mail_schema($db);$email=cd_partner_mail_address($db,$slug);$enabled=cd_partner_enabled($db,$slug);$choices=cd_partner_choices();$rows=cd_partner_admin_rows($db,$slug,$scope);
    ?><p>Freiwillige Spielpartnervermittlung für Vereinsmitglieder aller Altersgruppen. Bei 16–17 Jahren zusätzliche Jugendschutzprüfung, unter 16 ausschließlich über Erziehungsberechtigte. Anonyme Gesuche erscheinen erst nach Prüfung. Private Angaben bleiben im geschützten Backend. Ausschalten löscht offene Gesuche.</p>
    <form method="post" class="profile"><input type="hidden" name="csrf" value="<?=$csrf?>"><input type="hidden" name="action" value="partner-board"><input type="hidden" name="club" value="<?=cd_h($slug)?>"><label class="check"><input type="checkbox" name="enabled" <?=$enabled?'checked':''?>> Spielpartnerbörse aktivieren</label><button name="intent" value="toggle">Speichern</button></form>
    <?php if($enabled):?><form method="post" class="profile"><input type="hidden" name="csrf" value="<?=$csrf?>"><input type="hidden" name="action" value="partner-board"><input type="hidden" name="club" value="<?=cd_h($slug)?>"><label class="full">E-Mail für Gesuchfreigaben<input type="email" name="moderator_email" maxlength="254" value="<?=cd_h($email)?>" placeholder="E-Mail-Adresse des Vereinsadministrators"></label><button name="intent" value="mail-settings">E-Mail speichern</button></form><p><?=$email===''?'Noch keine Freigabe-E-Mail hinterlegt. Gesuche müssen im Backend geprüft werden.':'Bei neuen Gesuchen wird ein Freigabelink verschickt. Der Link ist einmalig und maximal 48 Stunden gültig.'?></p><p>Mitgliederformular: <a href="<?=$slug==='ntc93'?'https://clubdisplay.ntc93.de/spielpartner/':'?partner=submit&amp;club='.rawurlencode($slug)?>" target="_blank" rel="noopener">Öffnen</a>. Gesuche können ohne Einladungscode eingereicht werden.</p>
    <?php foreach($rows as $r):?><details class="entry"><summary><?=cd_h($r['ref'].' · '.$choices['category'][$r['category']])?> <small><?=$r['state']==='pending'?'Prüfung ausstehend':'Veröffentlicht'?></small></summary><p><?=cd_h($choices['skill'][$r['skill']].' · '.$choices['availability'][$r['availability']].' · '.$choices['period'][$r['period']])?></p><p>LK-Bereich: <?=cd_h(cd_partner_lk_choices()[$r['lk']]??'Keine Angabe')?></p><p>Altersgruppe (privat): <?=cd_h(cd_partner_age_label($r['age_group']))?></p><p>Privater Kontakt: <?=cd_h($r['contact'])?></p><form method="post" class="profile"><input type="hidden" name="csrf" value="<?=$csrf?>"><input type="hidden" name="action" value="partner-board"><input type="hidden" name="club" value="<?=cd_h($slug)?>"><input type="hidden" name="ref" value="<?=cd_h($r['ref'])?>">
    <?php if($r['state']==='pending'):?><?php $q=$db->prepare('SELECT status,expires FROM partner_mail_tokens WHERE ref=?');$q->execute([$r['ref']]);$mail=$q->fetch();?><p>Freigabe-E-Mail: <?=!$mail?'Nicht versendet':($mail['status']==='sent'?((int)$mail['expires']>time()?'An Mailserver übergeben':'Link abgelaufen'):'Versand fehlgeschlagen / nicht bestätigt')?></p><?php if($email!==''):?><button name="intent" value="mail-resend" formnovalidate>Freigabe-E-Mail erneut senden</button><?php endif?><label class="check full"><input type="checkbox" name="reviewed" value="1" required> Vereinsmitgliedschaft, Gesuch und Einwilligung geprüft</label><?php if($r['age_group']!=='adult'):?><label class="check full"><input type="checkbox" name="youth_reviewed" value="1" required> Altersgruppe, Identität und Jugendschutz geprüft; bei unter 16 Jahren Erziehungsberechtigung und Zustimmung unabhängig bestätigt.</label><?php endif?><button name="intent" value="publish">14 Tage veröffentlichen</button><?php endif?><button class="danger" name="intent" value="delete" formnovalidate>Gesuch schließen und löschen</button></form>
    <?php foreach($r['interest'] as $i):?><form method="post" class="profile"><input type="hidden" name="csrf" value="<?=$csrf?>"><input type="hidden" name="action" value="partner-board"><input type="hidden" name="club" value="<?=cd_h($slug)?>"><input type="hidden" name="ref" value="<?=cd_h($r['ref'])?>"><input type="hidden" name="interest_id" value="<?=(int)$i['id']?>"><p class="full">Kontaktanfrage (privat): <?=cd_h($i['contact'])?> · <?=cd_h(cd_partner_age_label($i['age_group']))?> · <?=cd_h(cd_partner_lk_choices()[$i['lk']]??'Keine Angabe')?></p><?php if($r['age_group']!=='adult'||$i['age_group']!=='adult'):?><p class="full"><strong>Jugendschutz:</strong> Keine direkte Weiterleitung von Kontaktdaten. Beide Identitäten, Altersgruppen und Mitgliedschaften prüfen; unter 16 Jahren Erziehungsberechtigung und Zustimmung unabhängig bestätigen. Verein organisiert eine altersgerechte, betreute Vermittlung; keine unbeaufsichtigten Erwachsenen-Minderjährigen-Treffen vermitteln.</p><label class="check full"><input type="checkbox" name="verified_youth" value="1" required> Zusätzliche Prüfung und geschützte Vermittlung durch den Verein erfolgt.</label><button name="intent" value="interest-youth-complete">Vermittlung als erledigt markieren</button><?php elseif($i['share']):?><p class="full">Mitgliedschaft und Identität beider Personen anhand der Vereinsunterlagen prüfen. Nur die dort unabhängig bestätigte E-Mail-Adresse des ursprünglichen Gesuchstellers verwenden. Niemals ungeprüfte Formularangaben als Empfänger übernehmen.</p><label class="full">Geprüfte E-Mail des Gesuchstellers<input type="email" name="verified_email" maxlength="254" required autocomplete="off"></label><label class="check full"><input type="checkbox" name="verified_both" value="1" required> Beide Mitgliedschaften und Identitäten, Kontaktdaten und Empfängeradresse geprüft.</label><button name="intent" value="interest-forward">Freigeben &amp; Kontakt weiterleiten</button><?php else:?><p class="full">Ältere Anfrage ohne ausdrückliche Weitergabeeinwilligung. Vor einer Vermittlung Einwilligung einholen.</p><?php endif?><button class="danger" name="intent" value="interest-delete" formnovalidate>Ablehnen / erledigt löschen</button></form><?php endforeach?></details><?php endforeach?>
    <?php if(!$rows):?><p>Noch keine Gesuche.</p><?php endif;endif;
}
