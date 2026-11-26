<?php
declare(strict_types=1);
// The public board contains only structured, approved data. Private contacts never enter a display feed.
function cd_partner_post_origin_allowed(array $server):bool{
    $origin=$server['HTTP_ORIGIN']??null;$site=$server['HTTP_SEC_FETCH_SITE']??'';
    return ($site===''||$site==='same-origin')&&($origin===null||$origin==='https://clubdisplay.tennilo.de'||($origin==='null'&&$site==='same-origin'));
}
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
    CREATE TABLE IF NOT EXISTS partner_post_gender (ref TEXT PRIMARY KEY REFERENCES partner_posts(ref) ON DELETE CASCADE, own_gender TEXT NOT NULL, wanted_gender TEXT NOT NULL);
    CREATE TABLE IF NOT EXISTS partner_post_preferences (ref TEXT PRIMARY KEY REFERENCES partner_posts(ref) ON DELETE CASCADE, own_ages TEXT NOT NULL, wanted_ages TEXT NOT NULL);
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
    $q=$db->prepare($id===null?'SELECT email FROM partner_post_meta WHERE ref=?':'SELECT m.email FROM partner_interest_meta m JOIN partner_interest i ON i.id=m.id WHERE i.id=? AND i.ref=?');$q->execute($id===null?[$ref]:[$id,$ref]);return (string)($q->fetchColumn()?:'');
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

function cd_partner_age_choices():array{
    return ['u16'=>'Unter 16','16-17'=>'16–17','18-29'=>'18–29','30-39'=>'30–39','40-49'=>'40–49','50-59'=>'50–59','60-69'=>'60–69','70plus'=>'70+'];
}
function cd_partner_multi(mixed $value,array $allowed,string $error,bool $required=true):array{
    $values=is_string($value)?[$value]:$value;
    if(!is_array($values)||count($values)>count($allowed))throw new InvalidArgumentException($error);
    foreach($values as $v)if(!is_string($v)||!array_key_exists($v,$allowed))throw new InvalidArgumentException($error);
    $values=array_values(array_intersect(array_keys($allowed),$values));
    if($required&&!$values)throw new InvalidArgumentException($error);
    return $values;
}
function cd_partner_choice_label(array $options,string $value):string{
    return implode(', ',array_values(array_filter(array_map(static fn($v)=>$options[$v]??'',explode(',',$value)),static fn($v)=>$v!=='')));
}
function cd_partner_age_level(string $band):string{
    return $band==='u16'?'child':($band==='16-17'?'teen':'adult');
}
function cd_partner_gender_choices():array{return ['male'=>'Männlich','female'=>'Weiblich','diverse'=>'Divers'];}
function cd_partner_gender_preferences(PDO $db,string $ref,bool $public=false):array{
    $q=$db->prepare('SELECT own_gender,wanted_gender FROM partner_post_gender WHERE ref=?');$q->execute([$ref]);$g=$q->fetch();
    if(!$g)return ['own_gender_label'=>'','wanted_gender_label'=>''];
    $labels=cd_partner_gender_choices()+['any'=>'Egal'];
    $out=['own_gender_label'=>cd_partner_choice_label($labels,$g['own_gender']),'wanted_gender_label'=>cd_partner_choice_label($labels,$g['wanted_gender'])];
    if($public){
        $q=$db->prepare('SELECT own_ages,wanted_ages FROM partner_post_preferences WHERE ref=?');$q->execute([$ref]);$ages=$q->fetch();
        if(!$ages||cd_partner_post_age($db,$ref)!=='adult')$out['own_gender_label']='';
        if(!$ages||array_intersect(explode(',',$ages['wanted_ages']),['u16','16-17']))$out['wanted_gender_label']='';
    }
    return $out;
}
function cd_partner_preferences(PDO $db,string $ref,bool $public=false):array{
    $q=$db->prepare('SELECT own_ages,wanted_ages FROM partner_post_preferences WHERE ref=?');$q->execute([$ref]);$r=$q->fetch();
    if(!$r)return ['own_age_label'=>'','wanted_age_label'=>'']+cd_partner_gender_preferences($db,$ref,$public);
    $format=static function(string $value)use($public):string{
        $bands=explode(',',$value);$labels=cd_partner_age_choices();
        if($public&&array_intersect($bands,['u16','16-17']))return 'Geschützte Vermittlung';
        return cd_partner_choice_label($labels,$value);
    };
    return ['own_age_label'=>$format($r['own_ages']),'wanted_age_label'=>$format($r['wanted_ages'])]+cd_partner_gender_preferences($db,$ref,$public);
}

function cd_partner_validate_submission(array $in,bool $structured):array{
    $age=$in['age_group']??'';
    if(($in['mode']??'')==='post'&&($in['preferences_version']??'')==='1'&&is_string($in['age_primary']??null)&&isset(cd_partner_age_choices()[$in['age_primary']])){
        $age=cd_partner_age_level($in['age_primary']);
    }
    if(!in_array($age,['adult','teen','child'],true)||($in['member']??'')!=='1'||($in['consent']??'')!=='1'||($in['website']??'')!=='')throw new InvalidArgumentException('Bitte Altersgruppe, Mitgliedschaft und Einwilligung bestätigen.');
    if($age==='child'&&($in['guardian']??'')!=='1')throw new InvalidArgumentException('Unter 16 Jahren darf nur eine erziehungsberechtigte Person die Anfrage stellen.');
    $mode=$in['mode']??'';
    if(!in_array($mode,['post','interest'],true))throw new InvalidArgumentException('Aktion auswählen.');
    if($mode==='interest'&&($in['share']??'')!=='1')throw new InvalidArgumentException('Bitte der geschützten Kontaktvermittlung zustimmen.');
    $fields=[];$own=[];$wanted=[];$ownGender=[];$wantedGender='';
    if($mode==='post'){
        foreach(cd_partner_choices() as $key=>$choices){
            $v=$in[$key]??'';
            if(in_array($key,['availability','period'],true)){
                $values=cd_partner_multi($v,$choices,$key==='availability'?'Bitte mindestens einen Wochentag auswählen.':'Bitte mindestens eine Tageszeit auswählen.');
                // Do not silently replace concrete user choices with a broad default.
                $flex=$key==='availability'?'flexible':'any';
                if(count($values)>1&&in_array($flex,$values,true))throw new InvalidArgumentException($key==='availability'?'Bitte entweder flexible Wochentage oder konkrete Wochentage auswählen, nicht beides.':'Bitte entweder zeitlich flexibel oder konkrete Tageszeiten auswählen, nicht beides.');
                $fields[]=implode(',',$values);
            }else{if(!is_string($v)||!isset($choices[$v]))throw new InvalidArgumentException('Bitte Auswahl prüfen.');$fields[]=$v;}
        }
        if(($in['preferences_version']??'')==='1'||isset($in['age_primary'])||isset($in['age_wanted'])){
            $bands=cd_partner_age_choices();$primary=$in['age_primary']??'';
            if(!is_string($primary)||!isset($bands[$primary]))throw new InvalidArgumentException('Bitte eigene Altersgruppe auswählen.');
            if(cd_partner_age_level($primary)!==$age)throw new InvalidArgumentException('Bitte eigene Altersgruppe prüfen.');
            $own=[$primary];
            if(in_array($fields[0],['doubles','mixed'],true))$own=array_values(array_unique(array_merge($own,cd_partner_multi($in['age_additional']??[],$bands,'Bitte Altersgruppen prüfen.',false))));
            $wanted=cd_partner_multi($in['age_wanted']??[],$bands,'Bitte mindestens eine gesuchte Altersgruppe auswählen.');
            // Any participating minor requires the existing protected route, even in a mixed-age team.
            if(in_array('u16',$own,true))$age='child';elseif(in_array('16-17',$own,true))$age='teen';
            if($age==='child'&&($in['guardian']??'')!=='1')throw new InvalidArgumentException('Bei Beteiligung unter 16 Jahren muss eine erziehungsberechtigte Person das Gesuch einreichen.');
        }
    }
    if($mode==='post'){
        $genders=cd_partner_gender_choices();$primary=$in['gender_primary']??'';$wantedGender=$in['gender_wanted']??'';
        if(!is_string($primary)||($primary!==''&&!isset($genders[$primary]))||!is_string($wantedGender)||($wantedGender!==''&&$wantedGender!=='any'&&!isset($genders[$wantedGender])))throw new InvalidArgumentException('Bitte Geschlechtsangaben prüfen.');
        if($primary!=='')$ownGender=[$primary];
        if(in_array($fields[0],['doubles','mixed'],true))$ownGender=array_values(array_unique(array_merge($ownGender,cd_partner_multi($in['gender_additional']??[],$genders,'Bitte Geschlechtsangaben prüfen.',false))));
    }
    $lk=$in['lk']??'';if(!is_string($lk)||!array_key_exists($lk,cd_partner_lk_choices()))throw new InvalidArgumentException('Ungültiger LK-Bereich.');
    $email='';$phone='';
    if($structured){
        $email=cd_partner_email($in['email']??null);
        $name=cd_text($in['name']??'',100,true);$phone=cd_text($in['phone']??'',50,false);
        $contact=$name."\n".$email.($phone!==''?"\n".$phone:'');
    }else{$contact=cd_text($in['contact']??'',200,true);}
    return [$age,$mode,$fields,$lk,$email,$contact,$own,$wanted,$ownGender,$wantedGender];
}
function cd_partner_start_verification(PDO $db,string $club,array $in,string $client,?int $now=null,?callable $send=null):string{
    $now??=time();cd_partner_mail_schema($db);
    [$age,$mode,$fields,$lk,$email,$contact,$own,$wanted,$ownGender,$wantedGender]=cd_partner_validate_submission($in,true);
    if(!cd_partner_enabled($db,$club))throw new InvalidArgumentException('Spielpartnerbörse nicht freigeschaltet.');
    if($mode==='interest'){
        $q=$db->prepare("SELECT 1 FROM partner_posts WHERE ref=? AND club=? AND state='published' AND expires>?");$q->execute([strtoupper(cd_text($in['ref']??'',10,true)),$club,$now]);if(!$q->fetchColumn())throw new InvalidArgumentException('Gesuch nicht mehr verfügbar.');
    }
    // Persist only known form fields; verification links must be confirmed by POST.
    $payload=array_intersect_key($in,array_flip(['age_group','member','consent','guardian','mode','share','category','skill','availability','period','lk','name','email','phone','ref','preferences_version','age_primary','age_additional','age_wanted','gender_primary','gender_additional','gender_wanted']));
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
    if(!$send($email,cd_partner_mail_subject($club,'E-Mail-Adresse bestätigen'),$m['body'],$m['headers'])){throw new InvalidArgumentException('Bestätigungs-E-Mail konnte nicht versendet werden. Bitte später erneut versuchen.');}
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
    $title=$action==='verify'?'E-Mail bestätigen':($decision==='renew'?'Gesuch verlängern':'Gesuch zurückziehen');$message='';$valid=preg_match('/^[a-f0-9]{64}$/D',$token)&&($action==='verify'||in_array($decision,['renew','withdraw'],true))&&cd_partner_enabled($db,$club);
    if($method==='GET'&&$valid){
        if($action==='verify'){$q=$db->prepare('SELECT 1 FROM partner_verify WHERE hash=? AND club=? AND expires>?');$q->execute([hash('sha256',$token),$club,time()]);}
        else{$q=$db->prepare("SELECT 1 FROM partner_posts p JOIN partner_post_meta m ON m.ref=p.ref WHERE p.club=? AND p.expires>? AND m.manage_hash=? AND (p.state='published' OR ?='withdraw')");$q->execute([$club,time(),hash('sha256',$token),$decision]);}
        $valid=(bool)$q->fetchColumn();if(!$valid)http_response_code(410);
    }
    if($method==='POST'){
        if(!cd_partner_post_origin_allowed($_SERVER)){http_response_code(403);exit;}
        if((int)($_SERVER['CONTENT_LENGTH']??0)>4096){http_response_code(413);exit;}
        try{$ok=$valid&&($in['confirmed']??'')==='1'&&($action==='verify'?cd_partner_confirm($db,$club,$token,time()):cd_partner_manage($db,$club,$token,$decision,time()));$message=$ok?($action==='verify'?'E-Mail bestätigt. Deine Anfrage wird jetzt geprüft.':'Änderung gespeichert.'): 'Dieser Link ist ungültig, abgelaufen oder bereits verwendet.';if(!$ok)http_response_code(410);}
        catch(InvalidArgumentException $e){$message=$e->getMessage();http_response_code(400);}catch(Throwable $e){$message='Anfrage derzeit nicht möglich.';http_response_code(503);}
    }
    ?><!doctype html><html lang="de"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title><?=cd_h($title)?></title><link rel="stylesheet" href="admin.css?v=076"></head><body class="partner-page<?=$club==='ntc93'?' ntc-portal':''?>"><main><section class="panel"><?php if($club==='ntc93'):?><img class="portal-logo" src="../assets/ntc93-logo.png" alt="NTC 93"><?php endif?><h1><?=cd_h($title)?></h1><?php if($method==='POST'||!$valid):?><p><?=cd_h($message?:'Ungültiger Link.')?></p><?php else:?><form method="post" action="?partner=<?=cd_h($action)?>&amp;club=<?=rawurlencode($club)?>" class="profile"><input type="hidden" name="token" value="<?=cd_h($token)?>"><input type="hidden" name="decision" value="<?=cd_h($decision)?>"><label class="check full"><input type="checkbox" name="confirmed" value="1" required> Ich bestätige diese Aktion.</label><button><?=cd_h($title)?></button></form><?php endif?></section><footer class="portal-credit">Powered by TENNILO ClubDisplay</footer></main></body></html><?php
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
function cd_partner_review_message(PDO $db,string $club,string $ref,array $notice):array{
    $choices=cd_partner_choices();$row=$notice['row'];$base=cd_partner_url($club,'moderate',$notice['token']);
    $details=['Referenz'=>$ref];
    foreach(['category'=>'Spielart','skill'=>'Spielstärke','availability'=>'Wochentage','period'=>'Tageszeiten'] as $key=>$label)$details[$label]=cd_partner_choice_label($choices[$key],$row[$key]);
    $intro='Ein neues Gesuch wartet auf deine Prüfung. Öffne die Detailansicht, um alle Angaben einzusehen und anschließend über die Freigabe zu entscheiden.';
    $note='Der persönliche Link ist höchstens 48 Stunden gültig und wird erst nach deiner Entscheidung ungültig. Keine privaten Kontaktdaten in dieser Nachricht. Bitte nicht weiterleiten.';
    $body=$intro."\n\nVerein: ".cd_club($db,$club)['name']."\n";
    foreach($details as $label=>$value)$body.=$label.': '.$value."\n";
    $body.="\nGesuch ansehen:\n".$base."\n\n".$note."\n";
    return cd_partner_mail_message($club,$body,'Neues Spielpartnergesuch',$intro,$details,[['label'=>'Gesuch ansehen','url'=>$base]],$note);
}
function cd_partner_mail_send(PDO $db,string $club,string $ref,int $now):void{
    try{
        $db->exec('BEGIN IMMEDIATE');
        try{$notice=cd_partner_mail_issue($db,$club,$ref,$now);$db->exec('COMMIT');}
        catch(Throwable $e){$db->exec('ROLLBACK');throw $e;}
        if(!$notice)return;
        $message=cd_partner_review_message($db,$club,$ref,$notice);
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
    $row=null;$message='';$error='';$now=time();
    // Every GET is a neutral detail view, including previously sent decision links.
    // Only a confirmed POST can consume the token or change the request.
    if($method==='POST'){
        if(!cd_partner_post_origin_allowed($_SERVER)){http_response_code(403);exit;}
        if((int)($_SERVER['CONTENT_LENGTH']??0)>4096){http_response_code(413);exit;}
        $row=cd_partner_mail_lookup($db,$club,$token,$now);
        if($row){
            if(!in_array($decision,['publish','delete'],true)||($input['confirmed']??'')!=='1')$error='Bitte die gewählte Entscheidung bestätigen.';
            elseif($decision==='publish'&&cd_partner_post_age($db,$row['ref'])!=='adult'&&($input['youth_reviewed']??'')!=='1')$error='Bitte die zusätzliche Jugendschutzprüfung bestätigen.';
            elseif(cd_partner_mail_decide($db,$club,$token,$decision,$now,($input['youth_reviewed']??'')==='1')){
                $message=$decision==='publish'?'Gesuch freigegeben und für 14 Tage veröffentlicht.':'Gesuch abgelehnt und gelöscht.';$row=null;
            }else{$row=null;}
        }
    }else{$row=cd_partner_mail_lookup($db,$club,$token,$now);}
    if(!$row&&$message===''){http_response_code(410);$message='Dieser Freigabelink ist ungültig, abgelaufen oder wurde bereits verwendet. Bitte im Vereinsbackend prüfen.';}
    ?><!doctype html><html lang="de"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>Spielpartnergesuch prüfen</title><link rel="stylesheet" href="admin.css?v=076"></head><body class="partner-page<?=$club==='ntc93'?' ntc-portal':''?>"><main><section class="panel"><?php if($club==='ntc93'):?><img class="portal-logo" src="../assets/ntc93-logo.png" alt="NTC 93"><?php endif?><span class="member-brand"><?=$club==='ntc93'?'NTC 93':'TENNILO ClubDisplay'?></span><h1>Spielpartnergesuch prüfen</h1>
    <?php if($row):
        $choices=cd_partner_choices();$age=cd_partner_post_age($db,$row['ref']);$prefs=cd_partner_preferences($db,$row['ref']);
        $q=$db->prepare('SELECT email,lk FROM partner_post_meta WHERE ref=?');$q->execute([$row['ref']]);$meta=$q->fetch()?:[];
        $details=['Referenz'=>$row['ref'],'Spielart'=>cd_partner_choice_label($choices['category'],$row['category']),'Spielstärke (Gesuch)'=>cd_partner_choice_label($choices['skill'],$row['skill']),'Wochentage'=>cd_partner_choice_label($choices['availability'],$row['availability']),'Tageszeiten'=>cd_partner_choice_label($choices['period'],$row['period']),'LK-Angabe'=>cd_partner_lk_choices()[$meta['lk']??'']??'Keine Angabe','Alter der Suchenden'=>$prefs['own_age_label']?:'Keine Angabe','Gesuchte Altersgruppen'=>$prefs['wanted_age_label']?:'Keine Angabe','Geschlecht Suchende'=>$prefs['own_gender_label']?:'Keine Angabe','Geschlecht gesucht'=>$prefs['wanted_gender_label']?:'Keine Angabe','Jugendschutz'=>cd_partner_age_label($age)];
    ?><p>Prüfung ausstehend · <?=cd_h(cd_club($db,$club)['name'])?></p>
    <?php if($error!==''):?><p role="alert"><?=cd_h($error)?></p><?php endif?>
    <h2>Spielwünsche</h2><dl class="partner-review-details"><?php foreach($details as $label=>$value):?><div><dt><?=cd_h($label)?></dt><dd><?=cd_h($value)?></dd></div><?php endforeach?></dl>
    <h2>Kontaktdaten (privat)</h2><div class="partner-review-contact"><p><?=nl2br(cd_h($row['contact']),false)?></p><p>E-Mail-Bestätigung: <?=!empty($meta['email'])?'Bestätigt':'Nicht nachgewiesen (Altgesuch)'?></p></div>
    <p>Prüfe die Vereinsmitgliedschaft und Identität anhand der Vereinsunterlagen. Die Kontaktdaten werden nicht auf dem ClubDisplay veröffentlicht.</p>
    <div class="partner-review-actions"><h2>Entscheidung</h2>
    <form method="post" action="?partner=moderate&amp;club=<?=rawurlencode($club)?>" class="profile partner-review-form"><input type="hidden" name="token" value="<?=cd_h($token)?>"><input type="hidden" name="decision" value="publish"><label class="check full"><input type="checkbox" name="confirmed" value="1" required> Vereinsmitgliedschaft, Identität, Gesuch und Einwilligung geprüft.</label><?php if($age!=='adult'):?><label class="check full"><input type="checkbox" name="youth_reviewed" value="1" required> Altersgruppe, Identität und Jugendschutz geprüft; bei unter 16 Jahren Erziehungsberechtigung und Zustimmung unabhängig bestätigt. Vermittlung ausschließlich über den Verein.</label><?php endif?><button class="full">Gesuch freigeben · 14 Tage</button></form>
    <form method="post" action="?partner=moderate&amp;club=<?=rawurlencode($club)?>" class="profile partner-review-form"><input type="hidden" name="token" value="<?=cd_h($token)?>"><input type="hidden" name="decision" value="delete"><label class="check full"><input type="checkbox" name="confirmed" value="1" required> Ich möchte dieses Gesuch ablehnen und endgültig löschen.</label><button class="danger full">Gesuch ablehnen</button></form></div>
    <?php else:?><p><?=cd_h($message)?></p><?php endif?><p><a class="partner-back" href="<?=$club==='ntc93'?'https://clubdisplay.ntc93.de/admin/?partner_review=1':'https://clubdisplay.tennilo.de/admin/?portal=club&amp;partner_review=1'?>">Zum geschützten Vereinsbackend</a></p></section><?php if($club==='ntc93'):?><footer class="portal-credit">Powered by TENNILO ClubDisplay</footer><?php endif?></main></body></html><?php
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
    cd_partner_mail_schema($db);$q=$db->prepare("SELECT p.ref,p.category,p.skill,p.availability,p.period,p.expires,COALESCE(m.lk,'') AS lk FROM partner_posts p LEFT JOIN partner_post_meta m ON p.ref=m.ref WHERE p.club=? AND p.state='published' AND p.expires>? ORDER BY p.created DESC LIMIT 30");$q->execute([$club,$now]);$rows=$q->fetchAll();foreach($rows as &$r)$r+=cd_partner_preferences($db,$r['ref'],true);unset($r);return $rows;
}
function cd_partner_admin_rows(PDO $db,string $club,?string $scope,?int $now=null):array{
    cd_scope($scope,$club);cd_partner_mail_schema($db);$now??=time();
    $q=$db->prepare('SELECT * FROM partner_posts WHERE club=? AND expires>? ORDER BY created DESC LIMIT 100');$q->execute([$club,$now]);$rows=$q->fetchAll();
    $q=$db->prepare('SELECT i.id,i.contact,i.created,i.expires,EXISTS(SELECT 1 FROM partner_interest_share s WHERE s.id=i.id) AS share FROM partner_interest i WHERE i.ref=? AND i.expires>? ORDER BY i.created');
    foreach($rows as &$r){$r['age_group']=cd_partner_post_age($db,$r['ref']);$r+=cd_partner_preferences($db,$r['ref']);$meta=$db->prepare('SELECT lk FROM partner_post_meta WHERE ref=?');$meta->execute([$r['ref']]);$r['lk']=(string)($meta->fetchColumn()?:'');$q->execute([$r['ref'],$now]);$r['interest']=$q->fetchAll();foreach($r['interest'] as &$i){$i['age_group']=cd_partner_interest_age($db,(int)$i['id']);$meta=$db->prepare('SELECT lk FROM partner_interest_meta WHERE id=?');$meta->execute([$i['id']]);$i['lk']=(string)($meta->fetchColumn()?:'');}unset($i);}unset($r);return $rows;
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
        }elseif($intent==='outbox-retry'){
            $id=filter_var($in['mail_id']??null,FILTER_VALIDATE_INT);
            if(!$id)throw new InvalidArgumentException('Ungültige Nachricht.');
            $q=$db->prepare('UPDATE partner_outbox SET next_try=0,attempts=0 WHERE id=? AND club=? AND created>? AND attempts<8');$q->execute([$id,$club,$now-604800]);
            if(!$q->rowCount())throw new InvalidArgumentException('Nachricht nicht mehr verfügbar.');
        }elseif($intent==='mail-resend'){
            $ref=cd_text($in['ref']??'',32,true);
            $q=$db->prepare("SELECT 1 FROM partner_posts WHERE ref=? AND club=? AND state='pending' AND expires>?");$q->execute([$ref,$club,$now]);
            if(!$q->fetchColumn()||!cd_partner_enabled($db,$club)||cd_partner_mail_address($db,$club)==='')throw new InvalidArgumentException('Offenes Gesuch und E-Mail-Adresse erforderlich.');
            $sendRef=$ref;
        }elseif(in_array($intent,['publish','delete','renew-admin','interest-delete','interest-forward','interest-youth-complete'],true)){
            $ref=cd_text($in['ref']??'',32,true);
            $q=$db->prepare('SELECT * FROM partner_posts WHERE ref=? AND club=? AND expires>?');$q->execute([$ref,$club,$now]);$row=$q->fetch();if(!$row)throw new InvalidArgumentException('Gesuch nicht gefunden.');
            if($intent==='publish'){
                if(!cd_partner_enabled($db,$club)||($in['reviewed']??'')!=='1'||$row['state']!=='pending')throw new InvalidArgumentException('Freigabe und Prüfung erforderlich.');
                if(cd_partner_post_age($db,$ref)!=='adult'&&($in['youth_reviewed']??'')!=='1')throw new InvalidArgumentException('Zusätzliche Jugendschutzprüfung erforderlich.');
                $q=$db->prepare("UPDATE partner_posts SET state='published',expires=? WHERE ref=? AND club=? AND state='pending'");$q->execute([$now+1209600,$ref,$club]);cd_partner_post_decision_notice($db,$club,$ref,true);
            }elseif($intent==='renew-admin'){
                if($row['state']!=='published'||!cd_partner_enabled($db,$club))throw new InvalidArgumentException('Nur aktive Gesuche können verlängert werden.');
                $q=$db->prepare("UPDATE partner_posts SET state='pending',expires=? WHERE ref=? AND club=?");$q->execute([$now+1209600,$ref,$club]);
                $q=$db->prepare('UPDATE partner_post_meta SET reminded=0 WHERE ref=?');$q->execute([$ref]);
                cd_partner_notice($db,$club,cd_partner_meta_email($db,$ref),'Verlängerung beantragt','Dein Verein hat die erneute Prüfung des Gesuchs eingeleitet. Bis zur Freigabe ist es nicht sichtbar.',$ref);$sendRef=$ref;
            }elseif($intent==='delete'){
                if($row['state']==='published')cd_partner_notice($db,$club,cd_partner_meta_email($db,$ref),'Gesuch geschlossen','Dein veröffentlichtes Gesuch wurde durch den Verein geschlossen.',$ref);else cd_partner_post_decision_notice($db,$club,$ref,false);$q=$db->prepare('DELETE FROM partner_posts WHERE ref=? AND club=?');$q->execute([$ref,$club]);}
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
    [$age,$mode,$fields,$lk,$email,$contact,$own,$wanted,$ownGender,$wantedGender]=cd_partner_validate_submission($in,$verified);
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
            if($own&&$wanted){$q=$db->prepare('INSERT INTO partner_post_preferences VALUES (?,?,?)');$q->execute([$ref,implode(',',$own),implode(',',$wanted)]);}
            if($ownGender||$wantedGender!==''){$q=$db->prepare('INSERT INTO partner_post_gender VALUES (?,?,?)');$q->execute([$ref,implode(',',$ownGender),$wantedGender]);}
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
// Neutral labels for legacy skill/LK selections; never infer whose playing level was intended.
function cd_partner_public_details(array $p,array $choices):void{
    $details=['Alter der suchenden Person(en)'=>$p['own_age_label']?:'Nicht angegeben','Gewünschtes Alter der Spielpartner'=>$p['wanted_age_label']?:'Nicht angegeben','Geschlecht Suchende'=>$p['own_gender_label']?:'Nicht angegeben','Geschlecht gesucht'=>$p['wanted_gender_label']?:'Nicht angegeben','Spielstärke (Gesuch)'=>cd_partner_choice_label($choices['skill'],$p['skill']),'LK-Angabe'=>cd_partner_lk_choices()[$p['lk']??'']??'Keine Angabe','Mögliche Wochentage'=>cd_partner_choice_label($choices['availability'],$p['availability']),'Mögliche Tageszeiten'=>cd_partner_choice_label($choices['period'],$p['period'])];
    ?><dl class="partner-public-details"><?php foreach($details as $label=>$value):?><div><dt><?=cd_h($label)?></dt><dd><?=cd_h($value)?></dd></div><?php endforeach?></dl><?php
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
        if(!cd_partner_post_origin_allowed($_SERVER)){http_response_code(403);exit;}
        if((int)($_SERVER['CONTENT_LENGTH']??0)>4096){http_response_code(413);exit;}
        try{$submission=$_POST;if(($submission['mode']??'')==='post')$submission['preferences_version']='1';cd_partner_start_verification($db,$club,$submission,$_SERVER['REMOTE_ADDR']??'unknown');$success=true;}catch(InvalidArgumentException $e){$error=$e->getMessage();}catch(Throwable $e){http_response_code(503);$error='Anfrage derzeit nicht möglich.';}
    }
    $c=cd_club($db,$club);$choices=cd_partner_choices();$publicRows=cd_partner_rows($db,$club);
    $requestedMode=$_POST['mode']??$_GET['mode']??'';
    $mode=in_array($requestedMode,['post','interest'],true)?$requestedMode:'browse';
    $boardUrl='?partner=submit&club='.rawurlencode($club);
    $filterCategory=is_string($_GET['category']??null)&&isset($choices['category'][$_GET['category']])?$_GET['category']:'';
    $filterLk=is_string($_GET['lk_filter']??null)&&isset(cd_partner_lk_choices()[$_GET['lk_filter']])?$_GET['lk_filter']:'';
    $filterDay=is_string($_GET['day_filter']??null)&&isset($choices['availability'][$_GET['day_filter']])?$_GET['day_filter']:'';
    $filterTime=is_string($_GET['time_filter']??null)&&isset($choices['period'][$_GET['time_filter']])?$_GET['time_filter']:'';
    $visibleRows=array_values(array_filter($publicRows,static function($p)use($filterCategory,$filterLk,$filterDay,$filterTime){
        $days=explode(',',$p['availability']);$times=explode(',',$p['period']);
        $dayMatch=$filterDay===''||in_array('flexible',$days,true)||in_array($filterDay,$days,true)|| (in_array('weekday',$days,true)&&in_array($filterDay,['mon','tue','wed','thu','fri'],true))||(in_array('weekend',$days,true)&&in_array($filterDay,['sat','sun'],true));
        return ($filterCategory===''||$p['category']===$filterCategory)&&($filterLk===''||($p['lk']??'')===$filterLk)&&$dayMatch&&($filterTime===''||in_array('any',$times,true)||in_array($filterTime,$times,true));
    }));
    $selected=is_string($_POST['ref']??$_GET['ref']??null)?strtoupper($_POST['ref']??$_GET['ref']):'';
    $selectedPost=null;foreach($publicRows as $p){if($p['ref']===$selected){$selectedPost=$p;break;}}
    if(!$success&&$mode==='interest'&&!$selectedPost){$mode='browse';$error=$error?:($selected!==''?'Dieses Gesuch ist nicht mehr verfügbar. Hier findest du die aktuell freigegebenen Gesuche.':'Bitte wähle ein aktuelles Gesuch aus.');}
    ?><!doctype html><html lang="de"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>Spielpartnerbörse</title><link rel="stylesheet" href="admin.css?v=076"></head><body class="partner-page<?=$club==='ntc93'?' ntc-portal':''?>"><main><section class="panel"><?php if($club==='ntc93'):?><img class="portal-logo" src="../assets/ntc93-logo.png" alt="NTC 93"><?php endif?><span class="member-brand"><?=cd_h($club==='ntc93'?$c['name']:'TENNILO ClubDisplay · '.$c['name'])?></span><h1>Spielpartner gesucht</h1>
    <?php if($success):?><p class="ok">Bitte bestätige deine E-Mail-Adresse über den Link in deinem Postfach. Erst danach wird deine Anfrage dem Verein zur Prüfung vorgelegt. Bitte auch den Spamordner prüfen.</p><a class="partner-back" href="<?=cd_h($boardUrl)?>">Zurück zu den aktuellen Gesuchen</a>
    <?php else:?><?php if($error):?><p class="error"><?=cd_h($error)?></p><?php endif?>
    <p class="form-hint">Für Vereinsmitglieder aller Altersgruppen. Gesuche und Kontaktanfragen werden geprüft. Kontaktdaten bleiben privat.</p>
    <details class="subpanel partner-safety"><summary>Teilnahmebedingungen &amp; Datenschutz</summary>
    <p>Für Vereinsmitglieder aller Altersgruppen. Die Teilnahme ist freiwillig und kostenlos.</p><section class="subpanel"><h2>Teilnahme nach Alter</h2><p><strong>Ab 18 Jahren:</strong> reguläre Teilnahme.</p><p><strong>16–17 Jahre:</strong> zusätzliche Prüfung und geschützte Vermittlung durch den Verein.</p><p><strong>Unter 16 Jahren:</strong> ausschließlich über die Erziehungsberechtigten. Nur deren Kontaktdaten angeben.</p></section>
    <section class="subpanel" aria-labelledby="partner-safety"><h2 id="partner-safety">So schützen wir deine Daten</h2><ol><li><strong>Geprüfte Gesuche:</strong> Der Verein prüft die Mitgliedschaft und das Gesuch vor der Veröffentlichung. Sichtbar sind nur Spielwünsche, die Altersbereiche und freiwilligen Geschlechtsangaben Erwachsener, der freiwillig angegebene LK-Bereich und eine anonyme Referenz.</li><li><strong>Geprüfte Kontaktvermittlung:</strong> Auch Kontaktanfragen werden durch den Verein geprüft. Nur wenn beide Beteiligten volljährig sind, können nach Prüfung und Einwilligung Kontaktdaten an die unabhängig bestätigte E-Mail-Adresse des Gesuchstellers übermittelt werden. Sobald Minderjährige beteiligt sind, vermittelt der Verein persönlich und geschützt; das System leitet keine privaten Kontaktdaten an andere Teilnehmer weiter. Unter 16 Jahren erfolgt die Vermittlung ausschließlich über die Erziehungsberechtigten.</li></ol><p>Dein Name, deine Telefonnummer und deine E-Mail-Adresse sind weder auf dem ClubDisplay noch im öffentlichen Formular einsehbar. Bei Erwachsenen entscheidest du selbst, ob du anschließend Kontakt aufnimmst.</p><p>Gesuche sind höchstens 14 Tage nach Freigabe sichtbar. Du kannst dein Gesuch über den persönlichen Link in der Freigabe-E-Mail zurückziehen oder eine Verlängerung beantragen.</p></section>
    </details>
    <?php if($mode==='browse'):?>
    <div class="partner-overview-intro"><h2>Aktuelle Gesuche</h2><p>Hier findest du die freigegebenen, anonymen Gesuche anderer Vereinsmitglieder. Wähle ein passendes Gesuch aus und melde dein Interesse an.</p><a class="partner-cta" href="<?=cd_h($boardUrl.'&mode=post')?>">+ Eigenes Gesuch erstellen</a><p class="form-hint">Noch nichts Passendes gefunden? Gib selbst ein Gesuch auf.</p></div>
    <?php if($publicRows):?><form method="get" class="partner-filters"><input type="hidden" name="partner" value="submit"><input type="hidden" name="club" value="<?=cd_h($club)?>"><label>Spielart<select name="category"><option value="">Alle Spielarten</option><?php foreach($choices['category'] as $v=>$label):?><option value="<?=cd_h($v)?>" <?=$filterCategory===$v?'selected':''?>><?=cd_h($label)?></option><?php endforeach?></select></label><label>LK-Bereich<select name="lk_filter"><option value="">Alle LK-Bereiche</option><?php foreach(cd_partner_lk_choices() as $v=>$label):if($v==='')continue;?><option value="<?=cd_h($v)?>" <?=$filterLk===$v?'selected':''?>><?=cd_h($label)?></option><?php endforeach?></select></label><label>Wochentag<select name="day_filter"><option value="">Alle Tage</option><?php foreach(['mon','tue','wed','thu','fri','sat','sun'] as $v):?><option value="<?=cd_h($v)?>" <?=$filterDay===$v?'selected':''?>><?=cd_h($choices['availability'][$v])?></option><?php endforeach?></select></label><label>Tageszeit<select name="time_filter"><option value="">Alle Zeiten</option><?php foreach(['morning','afternoon','evening'] as $v):?><option value="<?=cd_h($v)?>" <?=$filterTime===$v?'selected':''?>><?=cd_h($choices['period'][$v])?></option><?php endforeach?></select></label><button>Filtern</button><a class="partner-back" href="<?=cd_h($boardUrl)?>">Zurücksetzen</a></form><?php endif?>
    <p class="partner-count"><?=count($visibleRows)?> <?=count($visibleRows)===1?'Gesuch':'Gesuche'?></p>
    <div class="partner-list"><?php foreach($visibleRows as $p):?><article class="partner-list-card"><h3><?=cd_h($choices['category'][$p['category']])?></h3><?php cd_partner_public_details($p,$choices)?><small>Referenz: <?=cd_h($p['ref'])?></small><a class="partner-cta" href="<?=cd_h($boardUrl.'&mode=interest&ref='.$p['ref'])?>">Interesse anmelden</a></article><?php endforeach?></div>
    <?php if(!$visibleRows):?><p class="partner-empty"><?=$publicRows?'Keine Gesuche entsprechen deinen Filtern.':'Aktuell sind keine Gesuche veröffentlicht. Du kannst jederzeit ein eigenes Gesuch erstellen.'?></p><?php endif?>
    <?php else:?>
    <a class="partner-back" href="<?=cd_h($boardUrl)?>">‹ Zurück zu den aktuellen Gesuchen</a>
    <h2><?=$mode==='post'?'Eigenes Gesuch erstellen':'Interesse an einem Gesuch anmelden'?></h2>
    <p><?=$mode==='post'?'Beschreibe, wen du zum Tennisspielen suchst. Dein Gesuch erscheint nach Prüfung in der anonymen Liste.':'Du hast ein passendes Gesuch gefunden. Melde hier dein Interesse an. Der Verein prüft und vermittelt den Kontakt.'?></p>
    <form method="post" class="profile"><input type="hidden" name="mode" value="<?=cd_h($mode)?>"><div hidden><label>Website<input name="website" tabindex="-1" autocomplete="off"></label></div>
    <?php if($mode==='post'):?><h2 class="form-section">1. Spielwünsche</h2>
    <input type="hidden" name="preferences_version" value="1">
    <?php foreach(['category'=>'Spielart','skill'=>'Spielstärke für das gemeinsame Spiel'] as $key=>$label):?><label><?=cd_h($label)?><select name="<?=cd_h($key)?>" required><option value="" <?=empty($_POST[$key])?'selected':''?>>Bitte auswählen</option><?php foreach($choices[$key] as $v=>$name):?><option value="<?=cd_h($v)?>" <?=($_POST[$key]??'')===$v?'selected':''?>><?=cd_h($name)?></option><?php endforeach?></select></label><?php endforeach?>
    <?php foreach(['availability'=>'Wochentage','period'=>'Tageszeiten'] as $key=>$label):?><fieldset class="partner-choice-set full"><legend><?=cd_h($label)?> (Mehrfachauswahl)</legend><p class="form-hint">Entweder konkrete Angaben auswählen oder flexibel. Nicht beides.</p><div class="partner-choice-grid"><?php foreach($choices[$key] as $v=>$name):?><label class="check"><input type="checkbox" name="<?=cd_h($key)?>[]" value="<?=cd_h($v)?>" <?=in_array($v,(array)($_POST[$key]??[]),true)?'checked':''?>> <?=cd_h($name)?></label><?php endforeach?></div></fieldset><?php endforeach?>
    <label class="full">Eigene Altersgruppe<select name="age_primary" required><option value="">Bitte auswählen</option><?php foreach(cd_partner_age_choices() as $v=>$label):?><option value="<?=cd_h($v)?>" <?=($_POST['age_primary']??'')===$v?'selected':''?>><?=cd_h($label)?></option><?php endforeach?></select></label>
    <label class="full">Eigenes Geschlecht (optional)<select name="gender_primary"><option value="">Keine Angabe</option><?php foreach(cd_partner_gender_choices() as $v=>$label):?><option value="<?=cd_h($v)?>" <?=($_POST['gender_primary']??'')===$v?'selected':''?>><?=cd_h($label)?></option><?php endforeach?></select></label>
    <fieldset class="partner-choice-set full partner-extra-genders"><legend>Weitere Geschlechter der Suchenden (Doppel / Mixed, optional)</legend><div class="partner-choice-grid"><?php foreach(cd_partner_gender_choices() as $v=>$label):?><label class="check"><input type="checkbox" name="gender_additional[]" value="<?=cd_h($v)?>" <?=in_array($v,(array)($_POST['gender_additional']??[]),true)?'checked':''?>> <?=cd_h($label)?></label><?php endforeach?></div></fieldset>
    <fieldset class="partner-choice-set full partner-extra-ages"><legend>Weitere Altersgruppen der Suchenden (Doppel / Mixed)</legend><div class="partner-choice-grid"><?php foreach(cd_partner_age_choices() as $v=>$label):?><label class="check"><input type="checkbox" name="age_additional[]" value="<?=cd_h($v)?>" <?=in_array($v,(array)($_POST['age_additional']??[]),true)?'checked':''?>> <?=cd_h($label)?></label><?php endforeach?></div></fieldset>
    <fieldset class="partner-choice-set full"><legend>Gesuchte Altersgruppen (Mehrfachauswahl)</legend><div class="partner-choice-grid"><?php foreach(cd_partner_age_choices() as $v=>$label):?><label class="check"><input type="checkbox" name="age_wanted[]" value="<?=cd_h($v)?>" <?=in_array($v,(array)($_POST['age_wanted']??[]),true)?'checked':''?>> <?=cd_h($label)?></label><?php endforeach?></div><p class="form-hint">Bei Minderjährigen werden die Altersangaben nur im geschützten Vereinsbereich angezeigt.</p></fieldset>
    <label class="full">Geschlecht der gesuchten Spielpartner (optional)<select name="gender_wanted"><option value="">Keine Angabe</option><?php foreach(cd_partner_gender_choices()+['any'=>'Egal'] as $v=>$label):?><option value="<?=cd_h($v)?>" <?=($_POST['gender_wanted']??'')===$v?'selected':''?>><?=cd_h($label)?></option><?php endforeach?></select></label>
    <?php else:?><h2 class="form-section">1. Ausgewähltes Gesuch</h2><input type="hidden" name="ref" value="<?=cd_h($selectedPost['ref'])?>"><article class="partner-list-card full"><h3><?=cd_h($choices['category'][$selectedPost['category']])?></h3><?php cd_partner_public_details($selectedPost,$choices)?><small>Referenz: <?=cd_h($selectedPost['ref'])?></small></article><?php endif?>
    <label class="full">LK-Angabe (optional)<select name="lk"><?php foreach(cd_partner_lk_choices() as $value=>$label):?><option value="<?=cd_h($value)?>" <?=($_POST['lk']??'')===$value?'selected':''?>><?=cd_h($label)?></option><?php endforeach?></select></label>
    <h2 class="form-section">2. Kontakt &amp; Einwilligung</h2><?php if($mode==='interest'):?><label class="full">Deine Altersgruppe (Jugendschutz)<select name="age_group" required><option value="">Bitte auswählen</option><option value="adult" <?=($_POST['age_group']??'')==='adult'?'selected':''?>>Ab 18 Jahren</option><option value="teen" <?=($_POST['age_group']??'')==='teen'?'selected':''?>>16–17 Jahre</option><option value="child" <?=($_POST['age_group']??'')==='child'?'selected':''?>>Unter 16 Jahren – über Erziehungsberechtigte</option></select></label><?php endif?><p class="form-hint full">Unter 16 Jahren: Nur die erziehungsberechtigte Person füllt dieses Formular aus und gibt ihren eigenen Namen und ihre eigene Kontaktmöglichkeit an. Keine Telefonnummer oder E-Mail-Adresse des Kindes eintragen.</p><label class="full">Vor- und Nachname<input name="name" required maxlength="100" autocomplete="name" value="<?=cd_h(is_string($_POST['name']??null)?$_POST['name']:'')?>"></label><label class="full">E-Mail-Adresse (Pflichtfeld)<input name="email" type="email" required maxlength="254" autocomplete="email" value="<?=cd_h(is_string($_POST['email']??null)?$_POST['email']:'')?>"></label><label class="full">Handy / Telefon (optional)<input name="phone" type="tel" maxlength="50" autocomplete="tel" value="<?=cd_h(is_string($_POST['phone']??null)?$_POST['phone']:'')?>"></label><p class="form-hint full">Die E-Mail-Adresse muss per Bestätigungslink bestätigt werden. Über diese Adresse erhältst du alle Statusmeldungen zu deiner Anfrage.</p>
    <label class="check full"><input type="checkbox" name="member" value="1" <?=($_POST['member']??'')==='1'?'checked':''?> required> Die teilnehmende Person ist Mitglied dieses Vereins; die Altersgruppe ist korrekt.</label><label class="check full"><input type="checkbox" name="guardian" value="1" <?=($_POST['guardian']??'')==='1'?'checked':''?>> Bei Beteiligung unter 16 Jahren: Ich bin erziehungsberechtigt, stelle die Anfrage für mein Kind und gebe ausschließlich meine eigenen Kontaktdaten an. Der Verein prüft die Erziehungsberechtigung für alle beteiligten Kinder.</label>
    <label class="check full"><input type="checkbox" name="consent" value="1" <?=($_POST['consent']??'')==='1'?'checked':''?> required> Ich willige in die Verarbeitung durch den Verein zur Spielpartnervermittlung ein. Nur die gewählten Spielwünsche, Altersbereiche und freiwillige Geschlechtsangaben Erwachsener, der freiwillige LK-Bereich und die anonyme Referenz werden nach Prüfung veröffentlicht. Gesuche laufen nach höchstens 14 Tagen ab; ich kann sie jederzeit über den Verein zurückziehen. Kontaktdaten werden nicht veröffentlicht.</label>
    <?php if($mode==='interest'):?><label class="check full"><input type="checkbox" name="share" value="1" <?=($_POST['share']??'')==='1'?'checked':''?> required> Ich stimme der geschützten Vermittlung durch den Verein zu. Nur wenn beide Beteiligten volljährig sind, darf der Verein meine angegebenen Kontaktdaten an den unabhängig geprüften Gesuchsteller weiterleiten. Andernfalls vermittelt ausschließlich der Verein persönlich.</label><?php endif?>
    <button class="partner-submit"><?=$mode==='post'?'Gesuch zur Prüfung absenden':'Interesse zur Prüfung absenden'?></button></form><?php endif?><?php endif?></section><?php if($club==='ntc93'):?><footer class="portal-credit">Powered by TENNILO ClubDisplay · Spielpartnerbörse 0.4.71</footer><?php endif?></main></body></html><?php
    exit;
}
function cd_partner_field(PDO $db,array $club,string $csrf,?string $scope):void{
    $slug=$club['slug'];cd_partner_mail_schema($db);$email=cd_partner_mail_address($db,$slug);$enabled=cd_partner_enabled($db,$slug);$choices=cd_partner_choices();$rows=cd_partner_admin_rows($db,$slug,$scope);
    $pending=array_values(array_filter($rows,static fn($r)=>$r['state']==='pending'));
    $active=array_values(array_filter($rows,static fn($r)=>$r['state']==='published'));
    $interestCount=array_sum(array_map(static fn($r)=>count($r['interest']),$rows));
    $waitingInterest=array_sum(array_map(static fn($r)=>count($r['interest']),$pending));
    $q=$db->prepare('SELECT id,email,title,attempts,next_try,created FROM partner_outbox WHERE club=? ORDER BY created DESC LIMIT 100');$q->execute([$slug]);$outbox=$q->fetchAll();
    $q=$db->prepare('SELECT COUNT(*) FROM partner_verify WHERE club=? AND expires>?');$q->execute([$slug,time()]);$unverified=(int)$q->fetchColumn();
    ?><div class="partner-admin-dashboard">
    <div class="partner-admin-top"><h2>Übersicht · Spielpartnerbörse</h2><strong><?=$enabled?'Aktiv':'Deaktiviert'?></strong></div>
    <div class="partner-admin-metrics"><?php foreach(['Offene Freigaben'=>count($pending),'Aktive Gesuche'=>count($active),'Kontaktanfragen'=>$interestCount,'E-Mail-Bestätigung offen'=>$unverified,'Versandwarteschlange'=>count($outbox)] as $label=>$value):?><div><strong><?=$value?></strong><small><?=cd_h($label)?></small></div><?php endforeach?></div>
    <details class="entry partner-admin-section" open><summary>Einstellungen &amp; Teilnahmebedingungen</summary>
    <p>Freiwillige Spielpartnervermittlung für Vereinsmitglieder aller Altersgruppen. Bei 16–17 Jahren zusätzliche Jugendschutzprüfung, unter 16 ausschließlich über Erziehungsberechtigte. Anonyme Gesuche erscheinen erst nach Prüfung. Private Angaben bleiben im geschützten Backend. Ausschalten löscht offene Gesuche.</p>
    <form method="post" class="profile"><input type="hidden" name="csrf" value="<?=$csrf?>"><input type="hidden" name="action" value="partner-board"><input type="hidden" name="club" value="<?=cd_h($slug)?>"><label class="check"><input type="checkbox" name="enabled" <?=$enabled?'checked':''?>> Spielpartnerbörse aktivieren</label><button name="intent" value="toggle">Speichern</button></form>
    <?php if($enabled):?><form method="post" class="profile"><input type="hidden" name="csrf" value="<?=$csrf?>"><input type="hidden" name="action" value="partner-board"><input type="hidden" name="club" value="<?=cd_h($slug)?>"><label class="full">E-Mail für Gesuchfreigaben<input type="email" name="moderator_email" maxlength="254" value="<?=cd_h($email)?>" placeholder="E-Mail-Adresse des Vereinsadministrators"></label><button name="intent" value="mail-settings">E-Mail speichern</button></form><p><?=$email===''?'Noch keine Freigabe-E-Mail hinterlegt. Gesuche müssen im Backend geprüft werden.':'Bei neuen Gesuchen wird ein Freigabelink verschickt. Der Link ist einmalig und maximal 48 Stunden gültig.'?></p><p>Mitgliederformular: <a href="<?=$slug==='ntc93'?'https://clubdisplay.ntc93.de/spielpartner/':'?partner=submit&amp;club='.rawurlencode($slug)?>" target="_blank" rel="noopener">Öffnen</a>. Gesuche können ohne Einladungscode eingereicht werden.</p>
    <?php endif?></details>
    <?php if($enabled):?>
    <?php foreach(['Freigaben'=>$pending,'Aktive Gesuche'=>$active] as $sectionLabel=>$groupRows):?><details class="entry partner-admin-section" open><summary><?=cd_h($sectionLabel)?> · <?=count($groupRows)?></summary>
    <?php if(!$groupRows):?><p>Keine Einträge.</p><?php endif?>
    <?php foreach($groupRows as $r):?><details class="entry"><summary><?=cd_h($r['ref'].' · '.$choices['category'][$r['category']])?> <small><?=$r['state']==='pending'?'Prüfung ausstehend':'Veröffentlicht'?></small></summary><p><?=cd_h($choices['skill'][$r['skill']].' · '.cd_partner_choice_label($choices['availability'],$r['availability']).' · '.cd_partner_choice_label($choices['period'],$r['period']))?></p><?php if($r['own_age_label']!==''):?><p>Alter der suchenden Person(en): <?=cd_h($r['own_age_label'])?> · Gewünschtes Alter der Spielpartner: <?=cd_h($r['wanted_age_label'])?></p><?php endif?><p>Geschlecht Suchende: <?=cd_h($r['own_gender_label']?:'Keine Angabe')?> · Geschlecht gesucht: <?=cd_h($r['wanted_gender_label']?:'Keine Angabe')?></p><p>LK-Bereich: <?=cd_h(cd_partner_lk_choices()[$r['lk']]??'Keine Angabe')?></p><p>Altersgruppe (privat): <?=cd_h(cd_partner_age_label($r['age_group']))?></p><p>Privater Kontakt: <?=nl2br(cd_h($r['contact']))?></p><p>E-Mail-Status: <?=cd_partner_meta_email($db,$r['ref'])!==''?'Adresse bestätigt':'Altgesuch ohne Adressbestätigung'?></p><p>Gültig bis: <?=cd_h((new DateTimeImmutable('@'.(int)$r['expires']))->setTimezone(new DateTimeZone('Europe/Berlin'))->format('d.m.Y, H:i'))?> Uhr<?= $r['state']==='published'?' · '.max(0,(int)ceil(($r['expires']-time())/86400)).' Tage verbleibend':''?></p><form method="post" class="profile"><input type="hidden" name="csrf" value="<?=$csrf?>"><input type="hidden" name="action" value="partner-board"><input type="hidden" name="club" value="<?=cd_h($slug)?>"><input type="hidden" name="ref" value="<?=cd_h($r['ref'])?>">
    <?php if($r['state']==='pending'):?><?php $q=$db->prepare('SELECT status,expires FROM partner_mail_tokens WHERE ref=?');$q->execute([$r['ref']]);$mail=$q->fetch();?><p>Freigabe-E-Mail: <?=!$mail?'Nicht versendet':($mail['status']==='sent'?((int)$mail['expires']>time()?'An Mailserver übergeben':'Link abgelaufen'):'Versand fehlgeschlagen / nicht bestätigt')?></p><?php if($email!==''):?><button name="intent" value="mail-resend" formnovalidate>Freigabe-E-Mail erneut senden</button><?php endif?><label class="check full"><input type="checkbox" name="reviewed" value="1" required> Vereinsmitgliedschaft, Gesuch und Einwilligung geprüft</label><?php if($r['age_group']!=='adult'):?><label class="check full"><input type="checkbox" name="youth_reviewed" value="1" required> Altersgruppe, Identität und Jugendschutz geprüft; bei unter 16 Jahren Erziehungsberechtigung und Zustimmung unabhängig bestätigt.</label><?php endif?><button name="intent" value="publish">14 Tage veröffentlichen</button><?php else:?><button name="intent" value="renew-admin">Erneute Freigabe für Verlängerung anstoßen</button><?php endif?><button class="danger" name="intent" value="delete" formnovalidate><?=$r['state']==='pending'?'Gesuch ablehnen':'Gesuch schließen und löschen'?></button></form>
    
    </details><?php endforeach?></details><?php endforeach?>
    <details class="entry partner-admin-section" open><summary>Kontaktvermittlung · <?=$interestCount?></summary>
    <?php if(!$interestCount):?><p>Keine offenen Kontaktanfragen.</p><?php endif?>
    <?php if($waitingInterest):?><p><?=$waitingInterest?> Kontaktanfrage(n) warten auf die erneute Freigabe des zugehörigen Gesuchs. Erst danach ist eine Vermittlung möglich.</p><?php endif?>
    <?php foreach($active as $r):?><?php foreach($r['interest'] as $i):?><article class="partner-admin-item"><h4><?=cd_h($r['ref'].' · '.$choices['category'][$r['category']])?></h4><p>Gesuchsteller (privat): <?=nl2br(cd_h($r['contact']))?></p><form method="post" class="profile"><input type="hidden" name="csrf" value="<?=$csrf?>"><input type="hidden" name="action" value="partner-board"><input type="hidden" name="club" value="<?=cd_h($slug)?>"><input type="hidden" name="ref" value="<?=cd_h($r['ref'])?>"><input type="hidden" name="interest_id" value="<?=(int)$i['id']?>"><p class="full">Kontaktanfrage (privat): <?=nl2br(cd_h($i['contact']))?> · <?=cd_h(cd_partner_age_label($i['age_group']))?> · <?=cd_h(cd_partner_lk_choices()[$i['lk']]??'Keine Angabe')?></p><?php if($r['age_group']!=='adult'||$i['age_group']!=='adult'):?><p class="full"><strong>Jugendschutz:</strong> Keine direkte Weiterleitung von Kontaktdaten. Beide Identitäten, Altersgruppen und Mitgliedschaften prüfen; unter 16 Jahren Erziehungsberechtigung und Zustimmung unabhängig bestätigen. Verein organisiert eine altersgerechte, betreute Vermittlung; keine unbeaufsichtigten Erwachsenen-Minderjährigen-Treffen vermitteln.</p><label class="check full"><input type="checkbox" name="verified_youth" value="1" required> Zusätzliche Prüfung und geschützte Vermittlung durch den Verein erfolgt.</label><button name="intent" value="interest-youth-complete">Vermittlung als erledigt markieren</button><?php elseif($i['share']):?><p class="full">Mitgliedschaft und Identität beider Personen anhand der Vereinsunterlagen prüfen. Nur die dort unabhängig bestätigte E-Mail-Adresse des ursprünglichen Gesuchstellers verwenden. Niemals ungeprüfte Formularangaben als Empfänger übernehmen.</p><label class="full">Geprüfte E-Mail des Gesuchstellers<input type="email" name="verified_email" maxlength="254" required autocomplete="off"></label><label class="check full"><input type="checkbox" name="verified_both" value="1" required> Beide Mitgliedschaften und Identitäten, Kontaktdaten und Empfängeradresse geprüft.</label><button name="intent" value="interest-forward">Freigeben &amp; Kontakt weiterleiten</button><?php else:?><p class="full">Ältere Anfrage ohne ausdrückliche Weitergabeeinwilligung. Vor einer Vermittlung Einwilligung einholen.</p><?php endif?><button class="danger" name="intent" value="interest-delete" formnovalidate>Ablehnen / erledigt löschen</button></form></article><?php endforeach?><?php endforeach?></details>
    <details class="entry partner-admin-section"><summary>E-Mail-Verwaltung · <?=count($outbox)?></summary><p>Ausstehende Nachrichten und automatische Wiederholungen. Die Annahme durch den Mailserver ist keine Zustellbestätigung. Freigabe-E-Mails können im Bereich Freigaben erneut versendet werden.</p>
    <?php if(!$outbox):?><p>Keine ausstehenden Status-E-Mails.</p><?php endif?>
    <?php foreach($outbox as $m):?><article class="partner-admin-item"><strong><?=cd_h($m['title'])?></strong><p><?=cd_h($m['email'])?> · <?=$m['attempts']?'Versand fehlgeschlagen, Versuche: '.(int)$m['attempts']:'Wartet auf Versand'?></p><form method="post" class="profile"><input type="hidden" name="csrf" value="<?=$csrf?>"><input type="hidden" name="action" value="partner-board"><input type="hidden" name="club" value="<?=cd_h($slug)?>"><input type="hidden" name="mail_id" value="<?=(int)$m['id']?>"><button name="intent" value="outbox-retry">Erneut versenden</button></form></article><?php endforeach?></details>
    <?php endif?></div><?php
}
