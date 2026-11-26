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
    CREATE TABLE IF NOT EXISTS partner_interest_age (id INTEGER PRIMARY KEY REFERENCES partner_interest(id) ON DELETE CASCADE, age_group TEXT NOT NULL CHECK(age_group IN ('adult','teen','child')))");
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
function cd_partner_mail_decide(PDO $db,string $club,string $token,string $decision,int $now,bool $youthReviewed=false):bool{
    if(!in_array($decision,['publish','delete'],true))return false;
    $db->exec('BEGIN IMMEDIATE');
    try{
        $row=cd_partner_mail_lookup($db,$club,$token,$now);
        if(!$row){$db->exec('COMMIT');return false;}
        if($decision==='publish'){
            if(cd_partner_post_age($db,$row['ref'])!=='adult'&&!$youthReviewed){$db->exec('COMMIT');return false;}
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
        if(($input['confirmed']??'')==='1'&&cd_partner_mail_decide($db,$club,$token,$decision,$now,($input['youth_reviewed']??'')==='1')){
            $message=$decision==='publish'?'Gesuch freigegeben und für 14 Tage veröffentlicht.':'Gesuch abgelehnt und gelöscht.';
        }
    }elseif(in_array($decision,['publish','delete'],true)){$row=cd_partner_mail_lookup($db,$club,$token,$now);}
    if(!$row&&$message===''){http_response_code(410);$message='Dieser Freigabelink ist ungültig, abgelaufen oder wurde bereits verwendet. Bitte im Vereinsbackend prüfen.';}
    ?><!doctype html><html lang="de"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>Spielpartnergesuch prüfen</title><link rel="stylesheet" href="admin.css?v=067"></head><body class="partner-page"><main><section class="panel"><span class="member-brand">TENNILO ClubDisplay</span><h1>Spielpartnergesuch prüfen</h1>
    <?php if($row):$choices=cd_partner_choices();?><p><?=cd_h(cd_club($db,$club)['name'].' · '.$row['ref'])?></p><p><?=cd_h(implode(' · ',array_map(fn($key)=>$choices[$key][$row[$key]],array_keys($choices))))?></p><p>Privater Kontakt: <?=cd_h($row['contact'])?></p><p>Altersgruppe (privat): <?=cd_h(cd_partner_age_label(cd_partner_post_age($db,$row['ref'])))?></p>
    <form method="post" action="?partner=moderate&amp;club=<?=rawurlencode($club)?>" class="profile"><input type="hidden" name="token" value="<?=cd_h($token)?>"><input type="hidden" name="decision" value="<?=cd_h($decision)?>"><label class="check full"><input type="checkbox" name="confirmed" value="1" required> <?= $decision==='publish'?'Vereinsmitgliedschaft, Gesuch und Einwilligung geprüft':'Ich möchte dieses Gesuch ablehnen und löschen' ?></label><?php if($decision==='publish'&&cd_partner_post_age($db,$row['ref'])!=='adult'):?><label class="check full"><input type="checkbox" name="youth_reviewed" value="1" required> Altersgruppe, Identität und Jugendschutz geprüft; bei unter 16 Jahren Erziehungsberechtigung und Zustimmung unabhängig bestätigt. Vermittlung ausschließlich über den Verein.</label><?php endif?><button class="<?=$decision==='delete'?'danger':''?>"><?=$decision==='publish'?'Jetzt freigeben':'Jetzt ablehnen'?></button></form>
    <?php else:?><p><?=cd_h($message)?></p><?php endif?></section></main></body></html><?php
    exit;
}


function cd_partner_interest_notify(PDO $db,string $club,string $ref):void{
    try{
        $email=cd_partner_mail_address($db,$club);if($email==='')return;
        $url='https://clubdisplay.tennilo.de/admin/?portal=club'.($club==='ntc93'?'&verein=ntc93':'').'&partner_review=1';
        $body="Neue Kontaktanfrage zu einem Spielpartnergesuch.\nVerein: ".cd_club($db,$club)['name']."\nReferenz: ".$ref."\n\nBitte im geschützten Vereinsbackend beide Mitgliedschaften und Kontaktangaben prüfen. Erst dann den Kontakt vermitteln.\n".$url."\n\nKeine privaten Kontaktdaten in dieser Nachricht.";
        if(!@mail($email,'TENNILO ClubDisplay - Neue Kontaktanfrage',$body,"MIME-Version: 1.0\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: 8bit"))error_log('ClubDisplay interest notification not accepted.');
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
    $body="Ein vom Verein geprüftes Mitglied interessiert sich für dein Spielpartnergesuch ".$ref.".\n\nKontakt des interessierten Mitglieds:\n".$contact."\n\nDu entscheidest selbst, ob du Kontakt aufnehmen möchtest. Bitte nicht weitergeben.";
    $send??=static fn($to,$subject,$body,$headers)=>@mail($to,$subject,$body,$headers);
    if(!$send($email,'TENNILO ClubDisplay - Kontakt zu deinem Spielpartnergesuch',$body,"MIME-Version: 1.0\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: 8bit"))throw new InvalidArgumentException('E-Mail-Versand fehlgeschlagen. Anfrage bleibt gespeichert.');
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
    $now??=time();
    foreach(['partner_invites','partner_interest','partner_posts'] as $table){$q=$db->prepare('DELETE FROM '.$table.' WHERE expires<=?');$q->execute([$now]);}
}
function cd_partner_rows(PDO $db,string $club,?int $now=null):array{
    $now??=time();if(!cd_partner_enabled($db,$club))return [];
    $q=$db->prepare("SELECT ref,category,skill,availability,period,expires FROM partner_posts WHERE club=? AND state='published' AND expires>? ORDER BY created DESC LIMIT 30");$q->execute([$club,$now]);return $q->fetchAll();
}
function cd_partner_admin_rows(PDO $db,string $club,?string $scope,?int $now=null):array{
    cd_scope($scope,$club);cd_partner_mail_schema($db);$now??=time();
    $q=$db->prepare('SELECT * FROM partner_posts WHERE club=? AND expires>? ORDER BY created DESC LIMIT 100');$q->execute([$club,$now]);$rows=$q->fetchAll();
    $q=$db->prepare('SELECT i.id,i.contact,i.created,i.expires,EXISTS(SELECT 1 FROM partner_interest_share s WHERE s.id=i.id) AS share FROM partner_interest i WHERE i.ref=? AND i.expires>? ORDER BY i.created');
    foreach($rows as &$r){$r['age_group']=cd_partner_post_age($db,$r['ref']);$q->execute([$r['ref'],$now]);$r['interest']=$q->fetchAll();foreach($r['interest'] as &$i){$i['age_group']=cd_partner_interest_age($db,(int)$i['id']);}unset($i);}unset($r);return $rows;
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
        }elseif(in_array($intent,['publish','delete','interest-delete','interest-forward','interest-youth-complete'],true)){
            $ref=cd_text($in['ref']??'',32,true);
            $q=$db->prepare('SELECT * FROM partner_posts WHERE ref=? AND club=? AND expires>?');$q->execute([$ref,$club,$now]);$row=$q->fetch();if(!$row)throw new InvalidArgumentException('Gesuch nicht gefunden.');
            if($intent==='publish'){
                if(!cd_partner_enabled($db,$club)||($in['reviewed']??'')!=='1'||$row['state']!=='pending')throw new InvalidArgumentException('Freigabe und Prüfung erforderlich.');
                if(cd_partner_post_age($db,$ref)!=='adult'&&($in['youth_reviewed']??'')!=='1')throw new InvalidArgumentException('Zusätzliche Jugendschutzprüfung erforderlich.');
                $q=$db->prepare("UPDATE partner_posts SET state='published',expires=? WHERE ref=? AND club=? AND state='pending'");$q->execute([$now+1209600,$ref,$club]);
            }elseif($intent==='delete'){$q=$db->prepare('DELETE FROM partner_posts WHERE ref=? AND club=?');$q->execute([$ref,$club]);}
            else{$id=filter_var($in['interest_id']??null,FILTER_VALIDATE_INT);if(!$id)throw new InvalidArgumentException('Ungültige Anfrage.');
                if($intent==='interest-forward')cd_partner_interest_forward($db,$club,$ref,$id,trim(is_string($in['verified_email']??null)?$in['verified_email']:''),($in['verified_both']??'')==='1',$now);
                else{
                    if($intent==='interest-youth-complete'){
                        $q=$db->prepare("SELECT 1 FROM partner_interest WHERE id=? AND ref=? AND expires>?");$q->execute([$id,$ref,$now]);
                        if(!$q->fetchColumn()||$row['state']!=='published'||(cd_partner_post_age($db,$ref)==='adult'&&cd_partner_interest_age($db,$id)==='adult')||($in['verified_youth']??'')!=='1')throw new InvalidArgumentException('Geschützte Vermittlung und Jugendschutzprüfung bestätigen.');
                    }
                    $q=$db->prepare('DELETE FROM partner_interest WHERE id=? AND ref=?');$q->execute([$id,$ref]);
                }
            }
        }else throw new InvalidArgumentException('Unbekannte Aktion.');
        $db->exec('COMMIT');
    }catch(Throwable $e){$db->exec('ROLLBACK');throw $e;}
    if($sendRef!==null)cd_partner_mail_send($db,$club,$sendRef,$now);return $token;
}
function cd_partner_submit(PDO $db,string $club,array $in,?int $now=null,?string $client=null):void{
    $now??=time();
    $age=$in['age_group']??'';
    if(!in_array($age,['adult','teen','child'],true)||($in['member']??'')!=='1'||($in['consent']??'')!=='1'||($in['website']??'')!=='')throw new InvalidArgumentException('Bitte Altersgruppe, Mitgliedschaft und Einwilligung bestätigen.');
    if($age==='child'&&($in['guardian']??'')!=='1')throw new InvalidArgumentException('Unter 16 Jahren darf nur eine erziehungsberechtigte Person die Anfrage stellen.');
    $contact=cd_text($in['contact']??'',200,true);
    $mode=$in['mode']??'';
    if(!in_array($mode,['post','interest'],true))throw new InvalidArgumentException('Aktion auswählen.');
    if($mode==='interest'&&($in['share']??'')!=='1')throw new InvalidArgumentException('Bitte der geschützten Kontaktvermittlung zustimmen.');
    cd_partner_mail_schema($db);$db->exec('CREATE TABLE IF NOT EXISTS partner_rate (club TEXT NOT NULL, client TEXT NOT NULL, created INTEGER NOT NULL); CREATE INDEX IF NOT EXISTS partner_rate_created ON partner_rate(created)');$fields=[];
    if($mode==='post'){foreach(cd_partner_choices() as $key=>$choices){$v=$in[$key]??'';if(!is_string($v)||!isset($choices[$v]))throw new InvalidArgumentException('Bitte Auswahl prüfen.');$fields[]=$v;}}
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
        }else{
            $ref=strtoupper(cd_text($in['ref']??'',10,true));
            $q=$db->prepare("SELECT expires FROM partner_posts WHERE ref=? AND club=? AND state='published' AND expires>?");$q->execute([$ref,$club,$now]);$expires=$q->fetchColumn();if(!$expires)throw new InvalidArgumentException('Gesuch nicht mehr verfügbar.');
            $q=$db->prepare('SELECT COUNT(*) FROM partner_interest WHERE ref=?');$q->execute([$ref]);if((int)$q->fetchColumn()>=30)throw new InvalidArgumentException('Zu viele offene Anfragen.');
            $q=$db->prepare('SELECT 1 FROM partner_interest WHERE ref=? AND contact=? AND expires>?');$q->execute([$ref,$contact,$now]);if($q->fetchColumn())throw new InvalidArgumentException('Diese Kontaktanfrage ist bereits vorhanden.');
            $q=$db->prepare('INSERT INTO partner_interest(ref,contact,created,expires) VALUES (?,?,?,?)');$q->execute([$ref,$contact,$now,min((int)$expires,$now+1209600)]);
            $id=(int)$db->lastInsertId();$q=$db->prepare('INSERT INTO partner_interest_share VALUES (?)');$q->execute([$id]);
            $q=$db->prepare('INSERT INTO partner_interest_age VALUES (?,?)');$q->execute([$id,$age]);
        }
        $db->exec('COMMIT');
    }catch(Throwable $e){$db->exec('ROLLBACK');throw $e;}
    if($mode==='post')cd_partner_mail_send($db,$club,$ref,$now);else cd_partner_interest_notify($db,$club,$ref);
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
        try{cd_partner_submit($db,$club,$_POST,null,$_SERVER['REMOTE_ADDR']??'unknown');$success=true;}catch(InvalidArgumentException $e){$error=$e->getMessage();}catch(Throwable $e){http_response_code(503);$error='Anfrage derzeit nicht möglich.';}
    }
    $c=cd_club($db,$club);$choices=cd_partner_choices();$publicRows=cd_partner_rows($db,$club);
    $mode=($_POST['mode']??$_GET['mode']??'')==='interest'?'interest':'post';
    $selected=is_string($_POST['ref']??$_GET['ref']??null)?strtoupper($_POST['ref']??$_GET['ref']):'';
    ?><!doctype html><html lang="de"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>Spielpartnerbörse</title><link rel="stylesheet" href="admin.css?v=067"></head><body class="partner-page"><main><section class="panel"><span class="member-brand">TENNILO ClubDisplay · <?=cd_h($c['name'])?></span><h1>Spielpartner gesucht</h1>
    <?php if($success):?><p class="ok">Anfrage eingegangen. Dein Verein prüft sie und vermittelt den Kontakt. Es werden keine privaten Kontaktdaten veröffentlicht.</p>
    <?php else:?><?php if($error):?><p class="error"><?=cd_h($error)?></p><?php endif?>
    <p>Für Vereinsmitglieder aller Altersgruppen. Die Teilnahme ist freiwillig und kostenlos.</p><section class="subpanel"><h2>Teilnahme nach Alter</h2><p><strong>Ab 18 Jahren:</strong> reguläre Teilnahme.</p><p><strong>16–17 Jahre:</strong> zusätzliche Prüfung und geschützte Vermittlung durch den Verein.</p><p><strong>Unter 16 Jahren:</strong> ausschließlich über die Erziehungsberechtigten. Nur deren Kontaktdaten angeben.</p></section>
    <section class="subpanel" aria-labelledby="partner-safety"><h2 id="partner-safety">So schützen wir deine Daten</h2><ol><li><strong>Geprüfte Gesuche:</strong> Der Verein prüft die Mitgliedschaft und das Gesuch vor der Veröffentlichung. Sichtbar sind nur Spielwünsche und eine anonyme Referenz.</li><li><strong>Geprüfte Kontaktvermittlung:</strong> Auch Kontaktanfragen werden durch den Verein geprüft. Nur wenn beide Beteiligten volljährig sind, können nach Prüfung und Einwilligung Kontaktdaten an die unabhängig bestätigte E-Mail-Adresse des Gesuchstellers übermittelt werden. Sobald Minderjährige beteiligt sind, vermittelt der Verein persönlich und geschützt; das System versendet keine privaten Kontaktdaten. Unter 16 Jahren erfolgt die Vermittlung ausschließlich über die Erziehungsberechtigten.</li></ol><p>Dein Name, deine Telefonnummer und deine E-Mail-Adresse sind weder auf dem ClubDisplay noch im öffentlichen Formular einsehbar. Bei Erwachsenen entscheidest du selbst, ob du anschließend Kontakt aufnimmst.</p><p>Gesuche sind höchstens 14 Tage nach Freigabe sichtbar. Du kannst dein Gesuch jederzeit über den Verein zurückziehen.</p></section>
    <div class="partner-mode"><a class="<?=$mode==='post'?'active':''?>" href="?partner=submit&amp;club=<?=rawurlencode($club)?>">Gesuch erstellen</a><a class="<?=$mode==='interest'?'active':''?>" href="?partner=submit&amp;club=<?=rawurlencode($club)?>&amp;mode=interest">Spielpartner finden</a></div>
    <form method="post" class="profile"><input type="hidden" name="mode" value="<?=cd_h($mode)?>"><div hidden><label>Website<input name="website" tabindex="-1" autocomplete="off"></label></div>
    <?php if($mode==='post'):?><h2 class="form-section">1. Spielwünsche</h2>
    <?php foreach($choices as $key=>$options):?><label><?=cd_h(['category'=>'Spielart','skill'=>'Spielstärke','availability'=>'Wochentag','period'=>'Tageszeit'][$key])?><select name="<?=cd_h($key)?>"><?php foreach($options as $v=>$label):?><option value="<?=cd_h($v)?>"><?=cd_h($label)?></option><?php endforeach?></select></label><?php endforeach?>
    <?php else:?><h2 class="form-section">1. Gesuch auswählen</h2><label class="full">Veröffentlichte Gesuche<select name="ref" required><option value="">Bitte auswählen</option><?php foreach($publicRows as $p):?><option value="<?=cd_h($p['ref'])?>" <?=$selected===$p['ref']?'selected':''?>><?=cd_h($p['ref'].' · '.$choices['category'][$p['category']].' · '.$choices['skill'][$p['skill']].' · '.$choices['availability'][$p['availability']].' · '.$choices['period'][$p['period']])?></option><?php endforeach?></select></label><?php if(!$publicRows):?><p class="form-hint full">Aktuell gibt es keine veröffentlichten Gesuche.</p><?php endif?><?php endif?>
    <h2 class="form-section">2. Kontakt &amp; Einwilligung</h2><label class="full">Altersgruppe der teilnehmenden Person<select name="age_group" required><option value="">Bitte auswählen</option><option value="adult">Ab 18 Jahren</option><option value="teen">16–17 Jahre</option><option value="child">Unter 16 Jahren – über Erziehungsberechtigte</option></select></label><p class="form-hint full">Unter 16 Jahren: Nur die erziehungsberechtigte Person füllt dieses Formular aus und gibt ihren eigenen Namen und ihre eigene Kontaktmöglichkeit an. Keine Telefonnummer oder E-Mail-Adresse des Kindes eintragen.</p><label class="full">Name und private Kontaktmöglichkeit für den Verein<textarea name="contact" required maxlength="200" rows="2" autocomplete="off"></textarea></label>
    <label class="check full"><input type="checkbox" name="member" value="1" required> Die teilnehmende Person ist Mitglied dieses Vereins; die Altersgruppe ist korrekt.</label><label class="check full"><input type="checkbox" name="guardian" value="1"> Nur bei unter 16 Jahren: Ich bin erziehungsberechtigt, stelle diese Anfrage für mein Kind und gebe ausschließlich meine eigenen Kontaktdaten an.</label>
    <label class="check full"><input type="checkbox" name="consent" value="1" required> Ich willige in die Verarbeitung durch den Verein zur Spielpartnervermittlung ein. Nur die gewählten Kategorien und die anonyme Referenz werden nach Prüfung veröffentlicht. Gesuche laufen nach höchstens 14 Tagen ab; ich kann sie jederzeit über den Verein zurückziehen. Kontaktdaten werden nicht veröffentlicht.</label>
    <?php if($mode==='interest'):?><label class="check full"><input type="checkbox" name="share" value="1" required> Ich stimme der geschützten Vermittlung durch den Verein zu. Nur wenn beide Beteiligten volljährig sind, darf der Verein meine angegebenen Kontaktdaten an den unabhängig geprüften Gesuchsteller weiterleiten. Andernfalls vermittelt ausschließlich der Verein persönlich.</label><?php endif?>
    <button class="partner-submit" <?=$mode==='interest'&&!$publicRows?'disabled':''?>>Anfrage absenden</button></form><?php endif?></section></main></body></html><?php
    exit;
}
function cd_partner_field(PDO $db,array $club,string $csrf,?string $scope):void{
    $slug=$club['slug'];cd_partner_mail_schema($db);$email=cd_partner_mail_address($db,$slug);$enabled=cd_partner_enabled($db,$slug);$choices=cd_partner_choices();$rows=cd_partner_admin_rows($db,$slug,$scope);
    ?><p>Freiwillige Spielpartnervermittlung für Vereinsmitglieder aller Altersgruppen. Bei 16–17 Jahren zusätzliche Jugendschutzprüfung, unter 16 ausschließlich über Erziehungsberechtigte. Anonyme Gesuche erscheinen erst nach Prüfung. Private Angaben bleiben im geschützten Backend. Ausschalten löscht offene Gesuche.</p>
    <form method="post" class="profile"><input type="hidden" name="csrf" value="<?=$csrf?>"><input type="hidden" name="action" value="partner-board"><input type="hidden" name="club" value="<?=cd_h($slug)?>"><label class="check"><input type="checkbox" name="enabled" <?=$enabled?'checked':''?>> Spielpartnerbörse aktivieren</label><button name="intent" value="toggle">Speichern</button></form>
    <?php if($enabled):?><form method="post" class="profile"><input type="hidden" name="csrf" value="<?=$csrf?>"><input type="hidden" name="action" value="partner-board"><input type="hidden" name="club" value="<?=cd_h($slug)?>"><label class="full">E-Mail für Gesuchfreigaben<input type="email" name="moderator_email" maxlength="254" value="<?=cd_h($email)?>" placeholder="E-Mail-Adresse des Vereinsadministrators"></label><button name="intent" value="mail-settings">E-Mail speichern</button></form><p><?=$email===''?'Noch keine Freigabe-E-Mail hinterlegt. Gesuche müssen im Backend geprüft werden.':'Bei neuen Gesuchen wird ein Freigabelink verschickt. Der Link ist einmalig und maximal 48 Stunden gültig.'?></p><p>Mitgliederformular: <a href="?partner=submit&amp;club=<?=rawurlencode($slug)?>" target="_blank" rel="noopener">Öffnen</a>. Gesuche können ohne Einladungscode eingereicht werden.</p>
    <?php foreach($rows as $r):?><details class="entry"><summary><?=cd_h($r['ref'].' · '.$choices['category'][$r['category']])?> <small><?=$r['state']==='pending'?'Prüfung ausstehend':'Veröffentlicht'?></small></summary><p><?=cd_h($choices['skill'][$r['skill']].' · '.$choices['availability'][$r['availability']].' · '.$choices['period'][$r['period']])?></p><p>Altersgruppe (privat): <?=cd_h(cd_partner_age_label($r['age_group']))?></p><p>Privater Kontakt: <?=cd_h($r['contact'])?></p><form method="post" class="profile"><input type="hidden" name="csrf" value="<?=$csrf?>"><input type="hidden" name="action" value="partner-board"><input type="hidden" name="club" value="<?=cd_h($slug)?>"><input type="hidden" name="ref" value="<?=cd_h($r['ref'])?>">
    <?php if($r['state']==='pending'):?><?php $q=$db->prepare('SELECT status,expires FROM partner_mail_tokens WHERE ref=?');$q->execute([$r['ref']]);$mail=$q->fetch();?><p>Freigabe-E-Mail: <?=!$mail?'Nicht versendet':($mail['status']==='sent'?((int)$mail['expires']>time()?'An Mailserver übergeben':'Link abgelaufen'):'Versand fehlgeschlagen / nicht bestätigt')?></p><?php if($email!==''):?><button name="intent" value="mail-resend" formnovalidate>Freigabe-E-Mail erneut senden</button><?php endif?><label class="check full"><input type="checkbox" name="reviewed" value="1" required> Vereinsmitgliedschaft, Gesuch und Einwilligung geprüft</label><?php if($r['age_group']!=='adult'):?><label class="check full"><input type="checkbox" name="youth_reviewed" value="1" required> Altersgruppe, Identität und Jugendschutz geprüft; bei unter 16 Jahren Erziehungsberechtigung und Zustimmung unabhängig bestätigt.</label><?php endif?><button name="intent" value="publish">14 Tage veröffentlichen</button><?php endif?><button class="danger" name="intent" value="delete" formnovalidate>Gesuch schließen und löschen</button></form>
    <?php foreach($r['interest'] as $i):?><form method="post" class="profile"><input type="hidden" name="csrf" value="<?=$csrf?>"><input type="hidden" name="action" value="partner-board"><input type="hidden" name="club" value="<?=cd_h($slug)?>"><input type="hidden" name="ref" value="<?=cd_h($r['ref'])?>"><input type="hidden" name="interest_id" value="<?=(int)$i['id']?>"><p class="full">Kontaktanfrage (privat): <?=cd_h($i['contact'])?> · <?=cd_h(cd_partner_age_label($i['age_group']))?></p><?php if($r['age_group']!=='adult'||$i['age_group']!=='adult'):?><p class="full"><strong>Jugendschutz:</strong> Keine direkte Weiterleitung von Kontaktdaten. Beide Identitäten, Altersgruppen und Mitgliedschaften prüfen; unter 16 Jahren Erziehungsberechtigung und Zustimmung unabhängig bestätigen. Verein organisiert eine altersgerechte, betreute Vermittlung; keine unbeaufsichtigten Erwachsenen-Minderjährigen-Treffen vermitteln.</p><label class="check full"><input type="checkbox" name="verified_youth" value="1" required> Zusätzliche Prüfung und geschützte Vermittlung durch den Verein erfolgt.</label><button name="intent" value="interest-youth-complete">Vermittlung als erledigt markieren</button><?php elseif($i['share']):?><p class="full">Mitgliedschaft und Identität beider Personen anhand der Vereinsunterlagen prüfen. Nur die dort unabhängig bestätigte E-Mail-Adresse des ursprünglichen Gesuchstellers verwenden. Niemals ungeprüfte Formularangaben als Empfänger übernehmen.</p><label class="full">Geprüfte E-Mail des Gesuchstellers<input type="email" name="verified_email" maxlength="254" required autocomplete="off"></label><label class="check full"><input type="checkbox" name="verified_both" value="1" required> Beide Mitgliedschaften und Identitäten, Kontaktdaten und Empfängeradresse geprüft.</label><button name="intent" value="interest-forward">Freigeben &amp; Kontakt weiterleiten</button><?php else:?><p class="full">Ältere Anfrage ohne ausdrückliche Weitergabeeinwilligung. Vor einer Vermittlung Einwilligung einholen.</p><?php endif?><button class="danger" name="intent" value="interest-delete" formnovalidate>Ablehnen / erledigt löschen</button></form><?php endforeach?></details><?php endforeach?>
    <?php if(!$rows):?><p>Noch keine Gesuche.</p><?php endif;endif;
}
