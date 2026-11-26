<?php
declare(strict_types=1);
require __DIR__.'/../public/admin/lib.php';
function check(bool $ok):void{if(!$ok)throw new RuntimeException('Test failed');}
$db=cd_db(':memory:');
check((int)$db->query('SELECT COUNT(*) FROM clubs')->fetchColumn()===2);
$in=['slug'=>'test-club','name'=>'Testverein','plan'=>'INFO','color'=>'#21d5db','language'=>'de','dwell'=>'12','modules'=>['welcome','weather']];
cd_save($db,$in,true);
check((int)$db->query('SELECT COUNT(*) FROM clubs')->fetchColumn()===3);
$in['name']='Neu';cd_save($db,$in,false);
check($db->query("SELECT name FROM clubs WHERE slug='test-club'")->fetchColumn()==='Neu');
foreach([['slug'=>'../bad'],['modules'=>['welcome','courts']],['dwell'=>2],['color'=>'<script>']] as $bad){try{cd_validate(array_replace($in,$bad));throw new RuntimeException('Invalid accepted');}catch(InvalidArgumentException $e){}}
check(cd_h('<script>')==='&lt;script&gt;');
echo "Admin profile tests passed\n";

$display=['club'=>'test-club','slug'=>'clubhaus','name'=>'Clubhaus','mode'=>'INFO','language'=>'de','dwell'=>12,'modules'=>['welcome'],'enabled'=>1];
cd_save_display($db,$display,true,'test-club');
cd_save_display($db,array_replace($display,['slug'=>'terrasse','name'=>'Terrasse']),true,null);
check(count(cd_displays($db,'test-club'))===2);
check(count(cd_displays($db,'ntc93'))===0);
cd_save_display($db,array_replace($display,['name'=>'Eingang']),false,'test-club');
check(cd_displays($db,'test-club')[0]['name']==='Eingang');
foreach([['club'=>'ntc93'],['mode'=>'INTERACTIVE'],['modules'=>['welcome','news']]] as $bad){try{cd_save_display($db,array_replace($display,$bad),true,'test-club');throw new RuntimeException('Invalid display accepted');}catch(InvalidArgumentException $e){}}
cd_account($db,'test-club','test-only-password-12345',true);
check(password_verify('test-only-password-12345',cd_login_hash($db,'test-club','admin')));
check(cd_login_hash($db,'ntc93','admin')==='');
cd_account($db,'test-club','',false);
check(cd_login_hash($db,'test-club','admin')==='');
echo "Display isolation and account tests passed\n";

cd_save_brand($db,['club'=>'test-club','color'=>'#123456','welcome'=>'Willkommen'],[],'test-club');
check(cd_brand($db,'test-club')['welcome']==='Willkommen');
check(cd_club($db,'test-club')['color']==='#123456');
try{cd_save_brand($db,['club'=>'ntc93','color'=>'#123456'],[],'test-club');throw new RuntimeException('Cross-club brand accepted');}catch(InvalidArgumentException $e){}
$news=['club'=>'ntc93','kind'=>'news','title'=>'Testmeldung','body'=>'Test','starts'=>'2026-11-25','ends'=>'2026-12-01','url'=>'https://example.com/','enabled'=>1];
cd_save_content($db,$news,'ntc93');
$rows=cd_content_rows($db,'ntc93','news');check(count($rows)===1);
$news['id']=$rows[0]['id'];$news['title']='Geändert';cd_save_content($db,$news,'ntc93');check(cd_content_rows($db,'ntc93','news')[0]['title']==='Geändert');
foreach([['url'=>'javascript:alert(1)'],['starts'=>'2026-02-30'],['ends'=>'2026-01-01'],['club'=>'test-club']] as $bad){try{cd_save_content($db,array_replace($news,$bad),'ntc93');throw new RuntimeException('Invalid content accepted');}catch(InvalidArgumentException $e){}}
cd_save_content($db,array_replace($news,['intent'=>'delete']),'ntc93');check(count(cd_content_rows($db,'ntc93','news'))===0);
echo "Brand and editorial content tests passed\n";

cd_save_content($db,$news=array_replace($news,['id'=>0,'intent'=>'save']),'ntc93');
$id=(int)cd_content_rows($db,'ntc93','news')[0]['id'];
check(cd_media_get($db,'ntc93',$id,'ntc93')===null);
try{cd_media_get($db,'ntc93',$id,'test-club');throw new RuntimeException('Cross-club media accepted');}catch(InvalidArgumentException $e){}
try{cd_media_get($db,'test-club',$id,'test-club');throw new RuntimeException('Foreign media accepted');}catch(InvalidArgumentException $e){}
try{cd_media_png('<?php echo 1;');throw new RuntimeException('Non-image accepted');}catch(InvalidArgumentException $e){}
if(function_exists('imagecreatefromstring')){
    $im=imagecreatetruecolor(4,4);ob_start();imagepng($im);$raw=ob_get_clean();imagedestroy($im);
    $png=cd_media_png($raw);check(getimagesizefromstring($png)['mime']==='image/png');
    $q=$db->prepare('INSERT INTO content_media VALUES (?,?)');$q->bindValue(1,$id,PDO::PARAM_INT);$q->bindValue(2,$png,PDO::PARAM_LOB);$q->execute();
    check(cd_media_get($db,'ntc93',$id,'ntc93')===$png);
    check((int)cd_content_rows($db,'ntc93','news')[0]['has_image']===1);
    cd_media_save($db,['club'=>'ntc93','id'=>$id,'intent'=>'remove'],[],'ntc93');
    check(cd_media_get($db,'ntc93',$id,'ntc93')===null);
    $q->execute();
    cd_save_content($db,array_replace($news,['id'=>$id,'intent'=>'delete']),'ntc93');
    check((int)$db->query('SELECT COUNT(*) FROM content_media')->fetchColumn()===0);
}else{throw new RuntimeException('GD extension required for media tests');}
echo "Media validation, scope and cascade tests passed\n";

$dining=['club'=>'ntc93','name'=>'Testrestaurant','phone'=>'+49 123','website'=>'https://example.com/','reservation_phone'=>1,'hours'=>[0=>['state'=>'open','from1'=>'12:00','to1'=>'15:00','from2'=>'17:00','to2'=>'23:00'],1=>['state'=>'closed'],2=>['state'=>'open','from1'=>'17:00','to1'=>'01:00']],'special'=>[['date'=>'2026-12-24','state'=>'closed','note'=>'Feiertag']],'offers'=>[['title'=>'Pizza','day'=>0,'price'=>'9,90 €','starts'=>'2026-12-01','ends'=>'2026-12-31']]];
cd_dining_save($db,$dining,'ntc93');$d=cd_dining_get($db,'ntc93','ntc93');
check($d['hours'][0]['slots'][1]['from']==='17:00');check($d['hours'][1]['state']==='closed');check($d['hours'][2]['slots'][0]['to']==='01:00');check($d['hours'][3]['state']==='unknown');check($d['special'][0]['date']==='2026-12-24');check($d['offers'][0]['price']==='9,90 €');
foreach([['club'=>'test-club'],['website'=>'javascript:alert(1)'],['phone'=>''],['special'=>[['date'=>'2026-02-30']]],['special'=>[$dining['special'][0],$dining['special'][0]]],['hours'=>[['state'=>'open','from1'=>'12:00','to1'=>'18:00','from2'=>'17:00','to2'=>'20:00']]],['offers'=>[['title'=>'Pizza','day'=>8]]]] as $bad){try{cd_dining_save($db,array_replace($dining,$bad),'ntc93');throw new RuntimeException('Invalid dining data accepted');}catch(InvalidArgumentException $e){}}
try{cd_dining_get($db,'ntc93','test-club');throw new RuntimeException('Cross-club dining read accepted');}catch(InvalidArgumentException $e){}
check(cd_dining_get($db,'ntc93','ntc93')['name']==='Testrestaurant');
echo "Structured dining tests passed\n";

$screen=['club'=>'ntc93','slug'=>'managed-one','name'=>'Verwaltetes Gerät','mode'=>'INFO','language'=>'de','dwell'=>12,'modules'=>['welcome','calendar','news','restaurant'],'enabled'=>1];
cd_save_display($db,$screen,true,'ntc93');
cd_save_display($db,array_replace($screen,['slug'=>'managed-two']),true,'ntc93');
$first=cd_device_issue($db,'ntc93','managed-one','ntc93');check(strlen($first)===64);
check(cd_device_auth($db,$first)['display']==='managed-one');
check(cd_device_status($db,'ntc93','managed-one')['last_seen']===null);
cd_device_seen($db,cd_device_auth($db,$first),'0.6.0');check(cd_device_status($db,'ntc93','managed-one')['last_seen']>0);
try{cd_device_issue($db,'ntc93','managed-two','ntc93');throw new RuntimeException('Limit ignored');}catch(InvalidArgumentException $e){}
try{cd_device_issue($db,'ntc93','managed-one','test-club');throw new RuntimeException('Scope ignored');}catch(InvalidArgumentException $e){}
$contract=['club'=>'ntc93','screen_limit'=>2,'state'=>'active','cycle'=>'yearly','renewal'=>'2027-12-31'];
try{cd_save_contract($db,$contract,'ntc93');throw new RuntimeException('Contract role ignored');}catch(InvalidArgumentException $e){}
cd_save_contract($db,$contract,null);$second=cd_device_issue($db,'ntc93','managed-two',null);
$replacement=cd_device_issue($db,'ntc93','managed-one','ntc93');check(cd_device_auth($db,$first)===null);
check(cd_device_auth($db,$replacement)!==null);
cd_save_contract($db,array_replace($contract,['state'=>'paused']),null);check(cd_device_auth($db,$replacement)===null);
cd_save_contract($db,$contract,null);
cd_save_display($db,array_diff_key($screen,['enabled'=>true]),false,'ntc93');check(cd_device_auth($db,$replacement)===null);
cd_save_display($db,$screen,false,'ntc93');check(cd_device_auth($db,$replacement)!==null);
cd_device_revoke($db,'ntc93','managed-two','ntc93');check(cd_device_auth($db,$second)===null);
$source=['club'=>'ntc93','sources'=>['calendar'=>['provider'=>'ntc-feed']]];
try{cd_save_sources($db,$source,'ntc93');throw new RuntimeException('Unapproved feed accepted');}catch(InvalidArgumentException $e){}
cd_save_sources($db,$source,null);check(cd_sources($db,'ntc93','ntc93')['calendar']['provider']==='ntc-feed');
cd_save_sources($db,$source,'ntc93');
try{cd_save_sources($db,['club'=>'ntc93','sources'=>['courts'=>['provider'=>'ntc-feed']]],null);throw new RuntimeException('Booking data activated');}catch(InvalidArgumentException $e){}
cd_save_sources($db,['club'=>'ntc93'],null);
$clock=new DateTimeImmutable('2026-12-24 12:00:00',new DateTimeZone('Europe/Berlin'));
cd_save_content($db,['club'=>'ntc93','kind'=>'news','title'=>'Visible','enabled'=>1,'starts'=>'2026-12-01','ends'=>'2026-12-24'],'ntc93');
cd_save_content($db,['club'=>'ntc93','kind'=>'news','title'=>'Future','enabled'=>1,'starts'=>'2026-12-25'],'ntc93');
cd_save_content($db,['club'=>'ntc93','kind'=>'news','title'=>'Disabled'],'ntc93');
cd_save_content($db,['club'=>'ntc93','kind'=>'calendar','title'=>'Upcoming','enabled'=>1,'starts'=>'2026-12-25'],'ntc93');
cd_save_content($db,['club'=>'ntc93','kind'=>'calendar','title'=>'Past','enabled'=>1,'starts'=>'2026-12-23'],'ntc93');
cd_save_content($db,['club'=>'ntc93','kind'=>'partner','title'=>'Other module','enabled'=>1],'ntc93');
$snap=cd_snapshot($db,'ntc93','managed-one','ntc93',$clock);
check($snap['dining']['today']['state']==='closed');check($snap['dining']['today_note']==='Feiertag');
check(array_column($snap['content'],'title')===['Visible','Upcoming']);
foreach(['token_hash','password_hash','contract','reference'] as $secret)check(!array_key_exists($secret,$snap));
try{cd_snapshot($db,'ntc93','managed-one','test-club',$clock);throw new RuntimeException('Cross-club preview accepted');}catch(InvalidArgumentException $e){}
echo "Managed device, contract, source and dated snapshot tests passed\n";

foreach(['127.0.0.1','10.0.0.1','192.168.0.1','172.16.0.1','169.254.169.254','100.64.0.1','192.0.2.1','::1'] as $ip)check(!cd_public_ip($ip));
check(cd_public_ip('1.1.1.1'));
foreach([['wordpress','http://example.com/wp-json/wp/v2/posts'],['wordpress','https://127.0.0.1/wp-json/wp/v2/posts'],['wordpress','https://user@example.com/wp-json/wp/v2/posts'],['wordpress','https://example.com:8080/wp-json/wp/v2/posts'],['ical','https://example.com/secret?token=abc'],['nuliga','https://example.com/cgi-bin/WebObjects/nuLigaTENDE.woa/wa/clubMeetings?club=123'],['brightsky','91,180'],['ebusy-link','https://example.com/lite-module/1']] as [$provider,$ref]){
    try{cd_provider_reference($provider,$ref);throw new LogicException('Unsafe reference accepted');}catch(InvalidArgumentException $e){}
}
check(cd_provider_reference('brightsky','52.5333, 13.6833')==='52.5333,13.6833');
check(cd_provider_reference('nuliga','https://tvbb.liga.nu/cgi-bin/WebObjects/nuLigaTENDE.woa/wa/clubMeetings?club=123')==='https://tvbb.liga.nu/cgi-bin/WebObjects/nuLigaTENDE.woa/wa/clubMeetings?club=123');
$generic=['club'=>'ntc93','sources'=>['news'=>['provider'=>'wordpress','reference'=>'https://example.com/wp-json/wp/v2/posts']]];
cd_save_sources($db,$generic,null);
cd_save_sources($db,$generic,'ntc93');
try{cd_save_sources($db,array_replace($generic,['sources'=>['news'=>['provider'=>'wordpress','reference'=>'https://other.com/wp-json/wp/v2/posts']]]),'ntc93');throw new LogicException('Club changed approved source');}catch(InvalidArgumentException $e){}
$posts=cd_feed_json('wordpress','[{"title":{"rendered":"Club &amp; News"},"excerpt":{"rendered":"<script>bad()</script><b>Text</b>"},"date":"2026-12-01"}]');
check($posts['rows'][0]['title']==='Club & News'&&$posts['rows'][0]['excerpt']==='Text');
$fixture='<table><tr><th>Datum</th><th>Liga</th><th>Heimmannschaft</th><th>Gastmannschaft</th><th>Matchpunkte</th></tr><tr><td>01.12.2026 12:00</td><td>H30</td><td>Anderer TC</td><td>Gastverein</td><td>3:3</td></tr></table>';
check(cd_nu_parse($fixture)['matches'][0]['home']==='Anderer TC');
$ics="BEGIN:VCALENDAR\nBEGIN:VEVENT\nDTSTART:20991205T180000\nSUMMARY:Clubturnier\\, Tag 1\nEND:VEVENT\nBEGIN:VEVENT\nDTSTART:20991206T180000\nSUMMARY:Abgesagt\nSTATUS:CANCELLED\nEND:VEVENT\nEND:VCALENDAR";
check(count(cd_ical_parse($ics))===1&&cd_ical_parse($ics)[0]['title']==='Clubturnier, Tag 1');
cd_save_sources($db,['club'=>'ntc93','sources'=>['courts'=>['provider'=>'ntc-booking']]],null);
check(!isset(cd_snapshot_feeds($db,cd_snapshot($db,'ntc93','managed-one','ntc93'),'ntc93')['booking_url']));
cd_save_display($db,array_replace($screen,['mode'=>'INTERACTIVE','modules'=>['welcome','courts']]),false,'ntc93');
check(cd_snapshot_feeds($db,cd_snapshot($db,'ntc93','managed-one','ntc93'),'ntc93')['booking_url']==='https://clubdisplay.ntc93.de/terminal.php');
echo "Provider validation, public network restrictions, generic feed parsers and interactive booking scope passed\n";

check(cd_amount('19,90')===1990&&cd_amount('0')===0&&cd_amount('')===null&&cd_amount('12.5')===1250);
try{cd_amount('1.000,00');throw new LogicException('Invalid amount accepted');}catch(InvalidArgumentException $e){}
$bill=['club'=>'ntc93','recurring'=>'390,00','setup'=>'299','basis'=>'net','invoice_ref'=>'=FORMULA()','payment_state'=>'open'];
cd_save_billing($db,$bill,null);check((int)cd_billing($db,'ntc93')['recurring_cents']===39000);
try{cd_save_billing($db,$bill,'ntc93');throw new LogicException('Club changed billing');}catch(InvalidArgumentException $e){}
$out=fopen('php://temp','w+');cd_billing_csv($db,$out);rewind($out);$csv=stream_get_contents($out);fclose($out);check(str_contains($csv,"'=FORMULA()")&&str_contains($csv,'390,00'));
cd_audit($db,null,'contract','ntc93','managed-one');cd_audit($db,'test-club','content','ntc93',123);
check(count(cd_audit_rows($db,'ntc93'))===1&&count(cd_audit_rows($db,'test-club'))===1);
check(cd_audit_rows($db,'test-club')[0]['club']==='test-club');
check(count(cd_audit_rows($db,null))===2);
echo "Billing calculation, operator-only roles, CSV formula safety and audit isolation passed\n";

$custom=array_replace($screen,['screen_welcome'=>'Willkommen im Clubhaus','module_rank'=>['welcome'=>3,'calendar'=>1,'news'=>2,'restaurant'=>4],'module_dwell'=>['calendar'=>20,'news'=>35]]);
cd_save_display($db,$custom,false,'ntc93');
$other=array_replace($screen,['slug'=>'managed-two','name'=>'Gastronomie','screen_welcome'=>'Willkommen im Restaurant','module_rank'=>['restaurant'=>1,'welcome'=>2,'news'=>3,'calendar'=>4],'module_dwell'=>['restaurant'=>45]]);
cd_save_display($db,$other,false,'ntc93');
cd_save_content($db,['club'=>'ntc93','kind'=>'news','title'=>'Nur Clubhaus','enabled'=>1,'audience'=>'selected','targets'=>['managed-one']],'ntc93');
$private=array_values(array_filter(cd_content_rows($db,'ntc93','news'),fn($r)=>$r['title']==='Nur Clubhaus'))[0];$id=(int)$private['id'];
$q=$db->prepare('INSERT INTO content_media VALUES (?,?)');$q->bindValue(1,$id,PDO::PARAM_INT);$q->bindValue(2,$png,PDO::PARAM_LOB);$q->execute();
$a=cd_snapshot($db,'ntc93','managed-one','ntc93',$clock);$b=cd_snapshot($db,'ntc93','managed-two','ntc93',$clock);
check($a['modules']===['calendar','news','welcome','restaurant']);
check($b['modules']===['restaurant','welcome','news','calendar']);
check($a['welcome']==='Willkommen im Clubhaus'&&$b['welcome']==='Willkommen im Restaurant');
check($a['module_dwell']->news===35&&$b['module_dwell']->restaurant===45);
check(in_array('Nur Clubhaus',array_column($a['content'],'title'),true)&&!in_array('Nur Clubhaus',array_column($b['content'],'title'),true));
check(in_array('Visible',array_column($a['content'],'title'),true)&&in_array('Visible',array_column($b['content'],'title'),true));
check(cd_snapshot_image($db,'ntc93','managed-one','ntc93',$id)===$png);
check(cd_snapshot_image($db,'ntc93','managed-two','ntc93',$id)===null);
foreach([['targets'=>['terrasse']],['targets'=>[]],['targets'=>'managed-one'],['audience'=>'invalid']] as $bad){
    try{cd_save_content($db,array_replace(['club'=>'ntc93','kind'=>'news','id'=>$id,'title'=>'Changed','enabled'=>1,'audience'=>'selected','targets'=>['managed-one']],$bad),'ntc93');throw new LogicException('Invalid audience accepted');}catch(InvalidArgumentException $e){}
}
check(cd_content_audience($db,'ntc93',$id)===['mode'=>'selected','targets'=>['managed-one']]);
foreach([['module_rank'=>['welcome'=>0]],['module_dwell'=>['news'=>121]]] as $bad){try{cd_save_display($db,array_replace($custom,$bad),false,'ntc93');throw new LogicException('Invalid layout accepted');}catch(InvalidArgumentException $e){}}
cd_save_display($db,array_replace($screen,['slug'=>'managed-three']),true,'ntc93');
check(!in_array('Nur Clubhaus',array_column(cd_snapshot($db,'ntc93','managed-three','ntc93',$clock)['content'],'title'),true));
cd_save_content($db,['club'=>'ntc93','kind'=>'news','id'=>$id,'title'=>'Nur Clubhaus','enabled'=>1,'audience'=>'all'],'ntc93');
check(cd_snapshot_image($db,'ntc93','managed-two','ntc93',$id)===$png);
cd_save_content($db,['club'=>'ntc93','kind'=>'news','id'=>$id,'intent'=>'delete'],'ntc93');
check((int)$db->query("SELECT COUNT(*) FROM content_targets WHERE content_id=".$id)->fetchColumn()===0);
check((int)$db->query("SELECT COUNT(*) FROM content_audience WHERE content_id=".$id)->fetchColumn()===0);
echo "Per-screen layout, audience, module intersection, media isolation and cascade tests passed\n";

check(cd_plan_label('INFO')==='BASIC'&&cd_plan_label('INTERACTIVE')==='PLUS');
check(cd_club($db,'ntc93')['plan']==='INTERACTIVE');
check(cd_display($db,'ntc93','managed-one','ntc93')['mode']==='INFO');
$out=fopen('php://temp','w+');cd_billing_csv($db,$out);rewind($out);$csv=stream_get_contents($out);fclose($out);check(str_contains($csv,';PLUS;')&&str_contains($csv,';BASIC;'));
echo "BASIC and PLUS labels, compatible stored identifiers and CSV export passed\n";

$events=cd_feed_json('wp-events','{"events":[{"title":"Regel-Workshop","start_date":"2027-03-04 19:00:00","end_date":"2027-03-04 21:00:00","all_day":false},{"title":"Ohne Ende","start_date":"2027-03-04 19:00:00"},{"title":"Abgesagt","start_date":"2027-03-04 19:00:00","end_date":"2027-03-04 21:00:00","status":"cancelled"}]}');
check(count($events['rows'])===2);
check($events['rows'][0]['start']==='2027-03-04T19:00:00+01:00'&&$events['rows'][0]['end']==='2027-03-04T21:00:00+01:00');
check(!isset($events['rows'][1]['end']));
$timedIcs="BEGIN:VCALENDAR\nBEGIN:VEVENT\nDTSTART;TZID=Europe/Berlin:20991205T180000\nDTEND;TZID=Europe/Berlin:20991205T200000\nSUMMARY:Workshop\nEND:VEVENT\nEND:VCALENDAR";
check(cd_ical_parse($timedIcs)[0]['end']==='2099-12-05T20:00:00+01:00');
check(cd_event_timestamp('2027-02-30 12:00:00')===null);
echo "Managed event times and missing-end validation passed\n";

$partnerNow=1800000000;
cd_partner_mail_schema($db);
check(cd_partner_mail_address($db,'ntc93')==='gesucht@ntc93.de');
check(cd_partner_mail_address($db,'tennilo-demo')==='');
// Never send real notifications during automated tests.
cd_partner_admin($db,['club'=>'ntc93','intent'=>'mail-settings','moderator_email'=>''],'ntc93',$partnerNow);
check(cd_partner_mail_address($db,'ntc93')==='');
check(!cd_partner_enabled($db,'ntc93'));
cd_partner_admin($db,['club'=>'ntc93','intent'=>'toggle','enabled'=>1],'ntc93',$partnerNow);
$submission=['mode'=>'post','age_group'=>'adult','member'=>'1','consent'=>'1','contact'=>'PRIVATE-CONTACT@example.com','category'=>'doubles','skill'=>'recreational','availability'=>'weekend','period'=>'morning'];
cd_partner_submit($db,'ntc93',$submission,$partnerNow);
check(cd_partner_rows($db,'ntc93',$partnerNow)===[]);
$private=cd_partner_admin_rows($db,'ntc93','ntc93',$partnerNow);check(count($private)===1);$ref=$private[0]['ref'];
check($private[0]['contact']==='PRIVATE-CONTACT@example.com');
try{cd_partner_submit($db,'ntc93',$submission,$partnerNow);throw new RuntimeException('Duplicate request accepted');}catch(InvalidArgumentException $e){}
try{cd_partner_admin($db,['club'=>'ntc93','intent'=>'publish','ref'=>$ref,'reviewed'=>'1'],'test-club',$partnerNow);throw new RuntimeException('Cross-club approval accepted');}catch(InvalidArgumentException $e){}
cd_partner_admin($db,['club'=>'ntc93','intent'=>'publish','ref'=>$ref,'reviewed'=>'1'],'ntc93',$partnerNow);
$pub=cd_partner_rows($db,'ntc93',$partnerNow);check(count($pub)===1&&!isset($pub[0]['contact'])&&$pub[0]['category']==='doubles');
check(cd_partner_rows($db,'ntc93',$partnerNow+1209600)===[]);
cd_partner_submit($db,'ntc93',['mode'=>'interest','age_group'=>'adult','member'=>'1','consent'=>'1','share'=>'1','contact'=>'OTHER-PRIVATE','ref'=>$ref],$partnerNow);
$request=cd_partner_admin_rows($db,'ntc93','ntc93',$partnerNow)[0]['interest'][0];check((int)$request['share']===1);
$sent=[];$fakeSend=function($to,$subject,$body,$headers)use(&$sent){$sent=[$to,$body];return true;};
try{cd_partner_interest_forward($db,'ntc93',$ref,(int)$request['id'],'verified@example.invalid',false,$partnerNow,$fakeSend);throw new RuntimeException('Unverified forwarding accepted');}catch(InvalidArgumentException $e){}
check($sent===[]);
cd_partner_interest_forward($db,'ntc93',$ref,(int)$request['id'],'verified@example.invalid',true,$partnerNow,$fakeSend);
check($sent[0]==='verified@example.invalid'&&str_contains($sent[1],'OTHER-PRIVATE')&&!str_contains($sent[1],'PRIVATE-CONTACT'));
check(cd_partner_admin_rows($db,'ntc93','ntc93',$partnerNow)[0]['interest']===[]);
check(!str_contains(json_encode(cd_partner_rows($db,'ntc93',$partnerNow)),'PRIVATE'));
cd_partner_admin($db,['club'=>'ntc93','intent'=>'toggle'],'ntc93',$partnerNow);
check(cd_partner_rows($db,'ntc93',$partnerNow)===[]&&cd_partner_admin_rows($db,'ntc93','ntc93',$partnerNow)===[]);
echo "Partner board: direct submission, consent, moderation, privacy, isolation and expiry OK\n";

cd_partner_admin($db,['club'=>'ntc93','intent'=>'toggle','enabled'=>1],'ntc93',$partnerNow);
foreach(['MAIL-APPROVE','MAIL-REJECT'] as $contact){
    cd_partner_submit($db,'ntc93',array_replace($submission,['contact'=>$contact]),$partnerNow);
}
$refs=array_column(cd_partner_admin_rows($db,'ntc93','ntc93',$partnerNow),'ref','contact');
check(count($refs)===2&&cd_partner_rows($db,'ntc93',$partnerNow)===[]);
cd_partner_admin($db,['club'=>'ntc93','intent'=>'mail-settings','moderator_email'=>'moderator@example.invalid'],'ntc93',$partnerNow);
$notice=cd_partner_mail_issue($db,'ntc93',$refs['MAIL-APPROVE'],$partnerNow);
check($notice!==null&&$notice['email']==='moderator@example.invalid');
$token=$notice['token'];
$reviewMail=quoted_printable_decode(cd_partner_review_message($db,'ntc93',$refs['MAIL-APPROVE'],$notice)['body']);
check(str_contains($reviewMail,'Gesuch ansehen')&&!str_contains($reviewMail,'decision=publish')&&!str_contains($reviewMail,'decision=delete')&&!str_contains($reviewMail,'MAIL-APPROVE'));
check(cd_partner_mail_lookup($db,'ntc93',$token,$partnerNow)!==null);
check(cd_partner_mail_lookup($db,'ntc93',$token,$partnerNow+172800)===null);
check(!cd_partner_mail_decide($db,'tennilo-demo',$token,'publish',$partnerNow));
check(cd_partner_rows($db,'ntc93',$partnerNow)===[]);
$second=cd_partner_mail_issue($db,'ntc93',$refs['MAIL-APPROVE'],$partnerNow);
check(!cd_partner_mail_decide($db,'ntc93',$token,'publish',$partnerNow));
check(cd_partner_mail_decide($db,'ntc93',$second['token'],'publish',$partnerNow));
check(!cd_partner_mail_decide($db,'ntc93',$second['token'],'delete',$partnerNow));
check(count(cd_partner_rows($db,'ntc93',$partnerNow))===1);
$reject=cd_partner_mail_issue($db,'ntc93',$refs['MAIL-REJECT'],$partnerNow);
cd_partner_admin($db,['club'=>'ntc93','intent'=>'mail-settings','moderator_email'=>'other@example.invalid'],'ntc93',$partnerNow);
check(!cd_partner_mail_decide($db,'ntc93',$reject['token'],'delete',$partnerNow));
$reject=cd_partner_mail_issue($db,'ntc93',$refs['MAIL-REJECT'],$partnerNow);
check(cd_partner_mail_decide($db,'ntc93',$reject['token'],'delete',$partnerNow));
check(count(cd_partner_admin_rows($db,'ntc93','ntc93',$partnerNow))===1);
cd_partner_admin($db,['club'=>'ntc93','intent'=>'mail-settings','moderator_email'=>''],'ntc93',$partnerNow);
cd_partner_admin($db,['club'=>'ntc93','intent'=>'toggle'],'ntc93',$partnerNow);
echo "Partner mail: scoped, expiring, single-use links; resend and address change invalidate prior links; approve and reject OK\n";

cd_partner_admin($db,['club'=>'ntc93','intent'=>'toggle','enabled'=>1],'ntc93',$partnerNow);
for($i=0;$i<12;$i++)cd_partner_submit($db,'ntc93',array_replace($submission,['contact'=>'RATE-'.$i]),$partnerNow,'test-client');
try{cd_partner_submit($db,'ntc93',array_replace($submission,['contact'=>'RATE-OVER']),$partnerNow,'test-client');throw new RuntimeException('Rate limit bypass');}catch(InvalidArgumentException $e){}
check(count(cd_partner_admin_rows($db,'ntc93','ntc93',$partnerNow))===12);
check(cd_partner_rows($db,'ntc93',$partnerNow)===[]);
try{cd_partner_submit($db,'ntc93',array_replace($submission,['website'=>'spam']),$partnerNow);throw new RuntimeException('Honeypot bypass');}catch(InvalidArgumentException $e){}
cd_partner_admin($db,['club'=>'ntc93','intent'=>'toggle'],'ntc93',$partnerNow);
echo "Direct requests, duplicate prevention and rate limit OK\n";

cd_partner_admin($db,['club'=>'ntc93','intent'=>'toggle','enabled'=>1],'ntc93',$partnerNow);
try{cd_partner_submit($db,'ntc93',array_replace($submission,['age_group'=>'child','contact'=>'CHILD-GUARDIAN']),$partnerNow);throw new RuntimeException('Under 16 without guardian accepted');}catch(InvalidArgumentException $e){}
cd_partner_submit($db,'ntc93',array_replace($submission,['age_group'=>'child','guardian'=>'1','contact'=>'CHILD-GUARDIAN']),$partnerNow);
cd_partner_submit($db,'ntc93',array_replace($submission,['age_group'=>'teen','contact'=>'TEEN-PRIVATE']),$partnerNow);
$youthRefs=array_column(cd_partner_admin_rows($db,'ntc93','ntc93',$partnerNow),'ref','contact');
foreach($youthRefs as $youthRef){
  try{cd_partner_admin($db,['club'=>'ntc93','intent'=>'publish','ref'=>$youthRef,'reviewed'=>'1'],'ntc93',$partnerNow);throw new RuntimeException('Minor published without extra review');}catch(InvalidArgumentException $e){}
  cd_partner_admin($db,['club'=>'ntc93','intent'=>'publish','ref'=>$youthRef,'reviewed'=>'1','youth_reviewed'=>'1'],'ntc93',$partnerNow);
}
$youthPublic=cd_partner_rows($db,'ntc93',$partnerNow);
check(count($youthPublic)===2&&!str_contains(json_encode($youthPublic),'age_group')&&!str_contains(json_encode($youthPublic),'PRIVATE')&&!str_contains(json_encode($youthPublic),'GUARDIAN'));
$youthRef=$youthRefs['TEEN-PRIVATE'];
cd_partner_submit($db,'ntc93',['mode'=>'interest','age_group'=>'adult','member'=>'1','consent'=>'1','share'=>'1','contact'=>'ADULT-PRIVATE','ref'=>$youthRef],$partnerNow);
$youthRow=array_values(array_filter(cd_partner_admin_rows($db,'ntc93','ntc93',$partnerNow),fn($r)=>$r['ref']===$youthRef))[0];
$id=(int)$youthRow['interest'][0]['id'];
$sent=[];try{cd_partner_interest_forward($db,'ntc93',$youthRef,$id,'verified@example.invalid',true,$partnerNow,$fakeSend);throw new RuntimeException('Minor contact forwarded');}catch(InvalidArgumentException $e){}
check($sent===[]);
try{cd_partner_admin($db,['club'=>'ntc93','intent'=>'interest-youth-complete','ref'=>$youthRef,'interest_id'=>$id],'ntc93',$partnerNow);throw new RuntimeException('Youth mediation without review');}catch(InvalidArgumentException $e){}
cd_partner_admin($db,['club'=>'ntc93','intent'=>'interest-youth-complete','ref'=>$youthRef,'interest_id'=>$id,'verified_youth'=>'1'],'ntc93',$partnerNow);
cd_partner_admin($db,['club'=>'ntc93','intent'=>'toggle'],'ntc93',$partnerNow);
echo "Youth age bands, guardian requirement, private age metadata and no direct forwarding OK\n";

$mailPreview=cd_partner_mail_message('ntc93','Test & Text','Neues Gesuch','Vereinsprüfung',['Referenz'=>'ABCDEF1234','Test'=>'<script>alert(1)</script>'],[['label'=>'Prüfen','url'=>'https://clubdisplay.ntc93.de/spielpartner/?a=1&b=2']],'Nur für den Empfänger.');
$decoded=quoted_printable_decode($mailPreview['body']);
check(str_contains($mailPreview['headers'],'multipart/alternative')&&str_contains($mailPreview['headers'],'From: NTC 93 <gesucht@ntc93.de>'));
check(str_contains($decoded,'Content-Type: text/plain')&&str_contains($decoded,'Content-Type: text/html'));
check(str_contains($decoded,'https://clubdisplay.ntc93.de/assets/ntc93-logo.png')&&str_contains($decoded,'#f7dc14'));
check(str_contains($decoded,'&lt;script&gt;alert(1)&lt;/script&gt;')&&!str_contains($decoded,'<script>'));
check(str_contains($decoded,'?a=1&amp;b=2')&&str_contains($decoded,'Test & Text'));
check(cd_partner_mail_message('tennilo-demo','Plain','Title','Intro',[],[],'Note')['body']==='Plain');
echo "NTC email branding, MIME fallback and HTML escaping OK\n";

cd_partner_admin($db,['club'=>'ntc93','intent'=>'toggle','enabled'=>1],'ntc93',$partnerNow);
$new=array_replace($submission,['name'=>'Test Member','email'=>'member@example.invalid','phone'=>'','lk'=>'11-15']);
try{cd_partner_validate_submission(array_replace($new,['email'=>'']),true);throw new RuntimeException('Missing email accepted');}catch(InvalidArgumentException $e){}
try{cd_partner_validate_submission(array_replace($new,['lk'=>'<script>']),true);throw new RuntimeException('Invalid LK accepted');}catch(InvalidArgumentException $e){}
check(cd_partner_validate_submission($new,true)[3]==='11-15');
$verifyToken=cd_partner_start_verification($db,'ntc93',$new,'verification-test',$partnerNow,$fakeSend);
check(cd_partner_admin_rows($db,'ntc93','ntc93',$partnerNow)===[]);
check(!cd_partner_confirm($db,'tennilo-demo',$verifyToken,$partnerNow));
check(!cd_partner_confirm($db,'ntc93',$verifyToken,$partnerNow+86400));
check(cd_partner_confirm($db,'ntc93',$verifyToken,$partnerNow));
check(!cd_partner_confirm($db,'ntc93',$verifyToken,$partnerNow));
$verifiedRows=cd_partner_admin_rows($db,'ntc93','ntc93',$partnerNow);check(count($verifiedRows)===1&&$verifiedRows[0]['lk']==='11-15');
$verifiedRef=$verifiedRows[0]['ref'];
check(cd_partner_rows($db,'ntc93',$partnerNow)===[]);
cd_partner_admin($db,['club'=>'ntc93','intent'=>'publish','ref'=>$verifiedRef,'reviewed'=>'1'],'ntc93',$partnerNow);
$public=cd_partner_rows($db,'ntc93',$partnerNow);check(count($public)===1&&$public[0]['lk']==='11-15'&&!str_contains(json_encode($public),'member@example.invalid'));
cd_partner_cleanup($db,$partnerNow+1036801);
$reminded=$db->query('SELECT reminded FROM partner_post_meta')->fetchColumn();check((int)$reminded===1);
cd_partner_cleanup($db,$partnerNow+1209601);
check(cd_partner_rows($db,'ntc93',$partnerNow+1209601)===[]);
cd_partner_admin($db,['club'=>'ntc93','intent'=>'toggle'],'ntc93',$partnerNow);
echo "Verified email, optional phone, LK whitelist, no publication before approval and expiry OK\n";

$prefsInput=array_replace($submission,['preferences_version'=>'1','category'=>'doubles','availability'=>['mon','wed','fri'],'period'=>['morning','evening'],'age_primary'=>'30-39','age_additional'=>['40-49'],'age_wanted'=>['30-39','40-49']]);
$parsed=cd_partner_validate_submission($prefsInput,false);
check($parsed[2][2]==='mon,wed,fri'&&$parsed[2][3]==='morning,evening'&&$parsed[6]===['30-39','40-49']&&$parsed[7]===['30-39','40-49']);
$single=cd_partner_validate_submission(array_replace($prefsInput,['category'=>'singles']),false);check($single[6]===['30-39']);
try{cd_partner_validate_submission(array_replace($prefsInput,['age_additional'=>['u16']]),false);throw new RuntimeException('Unprotected minor group accepted');}catch(InvalidArgumentException $e){}
check(cd_partner_validate_submission(array_replace($prefsInput,['age_additional'=>['16-17']]),false)[0]==='teen');
check(cd_partner_validate_submission(array_replace($prefsInput,['age_additional'=>['u16'],'guardian'=>'1']),false)[0]==='child');
try{cd_partner_validate_submission(array_replace($prefsInput,['availability'=>[]]),false);throw new RuntimeException('Empty days accepted');}catch(InvalidArgumentException $e){}
cd_partner_admin($db,['club'=>'ntc93','intent'=>'toggle','enabled'=>1],'ntc93',$partnerNow);
cd_partner_submit($db,'ntc93',array_replace($prefsInput,['age_wanted'=>['16-17','30-39']]),$partnerNow);
$entry=cd_partner_admin_rows($db,'ntc93','ntc93',$partnerNow)[0];check($entry['wanted_age_label']==='16–17, 30–39');
cd_partner_admin($db,['club'=>'ntc93','intent'=>'publish','ref'=>$entry['ref'],'reviewed'=>'1'],'ntc93',$partnerNow);
$entryPublic=cd_partner_rows($db,'ntc93',$partnerNow)[0];check($entryPublic['availability']==='mon,wed,fri'&&$entryPublic['wanted_age_label']==='Geschützte Vermittlung'&&!str_contains(json_encode($entryPublic),'16-17'));
cd_partner_admin($db,['club'=>'ntc93','intent'=>'toggle'],'ntc93',$partnerNow);
echo "Multi-choice preferences, single/group ages and protected minor age display OK\n";

$oneAge=$prefsInput;unset($oneAge['age_group']);$oneAge['age_primary']='30-39';
check(cd_partner_validate_submission($oneAge,false)[0]==='adult');
try{cd_partner_validate_submission(array_replace($oneAge,['age_primary'=>'u16']),false);throw new RuntimeException('Missing guardian accepted');}catch(InvalidArgumentException $e){}
cd_partner_admin($db,['club'=>'ntc93','intent'=>'toggle','enabled'=>1],'ntc93',$partnerNow);
cd_partner_submit($db,'ntc93',$oneAge,$partnerNow);
$renewRef=cd_partner_admin_rows($db,'ntc93','ntc93',$partnerNow)[0]['ref'];
cd_partner_admin($db,['club'=>'ntc93','intent'=>'publish','ref'=>$renewRef,'reviewed'=>'1'],'ntc93',$partnerNow);
check(count(cd_partner_rows($db,'ntc93',$partnerNow))===1);
cd_partner_admin($db,['club'=>'ntc93','intent'=>'renew-admin','ref'=>$renewRef],'ntc93',$partnerNow);
check(cd_partner_rows($db,'ntc93',$partnerNow)===[]);
cd_partner_admin($db,['club'=>'ntc93','intent'=>'toggle'],'ntc93',$partnerNow);
echo "Single age input, administrative renewal requires fresh review OK\n";

check(count(cd_matchball_rows($db,'ntc93','ntc93',true))===2);
$mb=['club'=>'ntc93','month'=>'2026-11','intent'=>'save','headline'=>'Testausgabe','teaser'=>'Kurztext','pages'=>'2','story_title'=>['Thema'],'story_body'=>['Testtext'],'story_ref'=>['Seite 1']];
try{cd_matchball_save($db,$mb,[],'test-club');throw new RuntimeException('Cross-club newsletter save accepted');}catch(InvalidArgumentException $e){}
cd_matchball_save($db,$mb,[],'ntc93');check(count(cd_matchball_rows($db,'ntc93','ntc93',true))===2);
try{cd_matchball_save($db,array_replace($mb,['intent'=>'publish']),[],'ntc93');throw new RuntimeException('Unreviewed publication accepted');}catch(InvalidArgumentException $e){}
cd_matchball_save($db,array_replace($mb,['intent'=>'publish','public_reviewed'=>'1']),[],'ntc93');
check(cd_matchball_rows($db,'ntc93','ntc93',true)[0]['month']==='2026-11');
cd_matchball_save($db,array_replace($mb,['intent'=>'unpublish']),[],'ntc93');check(count(cd_matchball_rows($db,'ntc93','ntc93',true))===2);
cd_matchball_save($db,array_replace($mb,['intent'=>'delete','delete_confirm'=>'1']),[],'ntc93');
check(count(cd_matchball_rows($db,'ntc93','ntc93'))===2);
cd_matchball_save($db,['club'=>'ntc93','month'=>'2026-09','intent'=>'delete','delete_confirm'=>'1'],[],'ntc93');
check(count(cd_matchball_rows($db,'ntc93','ntc93'))===1);
echo "MATCHBALL seed, draft, review, tenant scope, withdrawal and non-reappearing deletion OK\n";

check(cd_community_public($db)===[]);
try{cd_community_save($db,['club'=>'ntc93','kind'=>'youth','intent'=>'publish','reviewed'=>'1'],[],'test-club');throw new RuntimeException('Cross-club community write accepted');}catch(InvalidArgumentException $e){}
try{cd_community_save($db,['club'=>'ntc93','kind'=>'youth','intent'=>'publish','reviewed'=>'1'],[],'ntc93');throw new RuntimeException('Missing QR published');}catch(InvalidArgumentException $e){}

foreach([['availability'=>['flexible','mon']],['period'=>['any','morning']]] as $conflict){
    try{cd_partner_validate_submission(array_replace($prefsInput,$conflict),false);throw new RuntimeException('Contradictory flexible selection accepted');}catch(InvalidArgumentException $e){}
}
$explicit=cd_partner_validate_submission(array_replace($prefsInput,['availability'=>['tue','sat'],'period'=>['afternoon','evening'],'lk'=>'11-15']),false);
check($explicit[2][2]==='tue,sat'&&$explicit[2][3]==='afternoon,evening'&&$explicit[3]==='11-15');
$flex=cd_partner_validate_submission(array_replace($prefsInput,['availability'=>['flexible'],'period'=>['any']]),false);
check($flex[2][2]==='flexible'&&$flex[2][3]==='any');
echo "Partner availability and time: explicit choices preserved, contradictory flexibility rejected\n";

$genderInput=array_replace($prefsInput,['gender_primary'=>'female','gender_additional'=>['male'],'gender_wanted'=>'any']);
$genderParsed=cd_partner_validate_submission($genderInput,false);
check($genderParsed[8]===['female','male']&&$genderParsed[9]==='any');
check(cd_partner_validate_submission(array_replace($genderInput,['category'=>'singles']),false)[8]===['female']);
try{cd_partner_validate_submission(array_replace($genderInput,['gender_wanted'=>'invalid']),false);throw new RuntimeException('Invalid gender accepted');}catch(InvalidArgumentException $e){}
cd_partner_admin($db,['club'=>'ntc93','intent'=>'toggle','enabled'=>1],'ntc93',$partnerNow);
cd_partner_submit($db,'ntc93',$genderInput,$partnerNow);
$genderEntry=cd_partner_admin_rows($db,'ntc93','ntc93',$partnerNow)[0];
check($genderEntry['own_gender_label']==='Weiblich, Männlich'&&$genderEntry['wanted_gender_label']==='Egal');
cd_partner_admin($db,['club'=>'ntc93','intent'=>'publish','ref'=>$genderEntry['ref'],'reviewed'=>'1'],'ntc93',$partnerNow);
check(cd_partner_rows($db,'ntc93',$partnerNow)[0]['own_gender_label']==='Weiblich, Männlich');
cd_partner_admin($db,['club'=>'ntc93','intent'=>'toggle'],'ntc93',$partnerNow);
echo "Partner gender: validated, persisted, public adult labels and legacy compatibility OK\n";

$chunk=static fn($t,$v)=>pack('N',strlen($v)).$t.$v.hash('crc32b',$t.$v,true);
$monoPng="\x89PNG\r\n\x1a\n".$chunk('IHDR',pack('NNCCCCC',574,574,1,0,0,0,0)).$chunk('IDAT',gzcompress(str_repeat("\0".str_repeat("\xff",72),574))).$chunk('IEND','');
check(cd_community_png($monoPng)===$monoPng);
try{cd_community_png(substr($monoPng,0,-4));throw new RuntimeException('Truncated PNG accepted');}catch(InvalidArgumentException $e){}
try{cd_community_png($monoPng.'trailing');throw new RuntimeException('PNG trailing payload accepted');}catch(InvalidArgumentException $e){}
echo "Community QR PNG: lossless 1-bit format and invalid structure checks OK\n";

$rgbPng="\x89PNG\r\n\x1a\n".$chunk('IHDR',pack('NNCCCCC',574,574,8,2,0,0,0)).$chunk('IDAT',gzcompress(str_repeat("\0".str_repeat("\xff",574*3),574))).$chunk('IEND','');
check(cd_community_png($rgbPng)===$rgbPng);
$rectPng="\x89PNG\r\n\x1a\n".$chunk('IHDR',pack('NNCCCCC',574,575,1,0,0,0,0)).$chunk('IDAT',gzcompress(str_repeat("\0".str_repeat("\xff",72),575))).$chunk('IEND','');
check(cd_community_png($rectPng)===$rectPng);
foreach([[510,510],[516,522]] as [$w,$h]){
    $row="\0".str_repeat("\xff",$w*3);
    $p="\x89PNG\r\n\x1a\n".$chunk('IHDR',pack('NNCCCCC',$w,$h,8,2,0,0,0)).$chunk('IDAT',gzcompress(str_repeat($row,$h))).$chunk('IEND','');
    check(cd_community_png($p)===$p);
}
$portrait="\x89PNG\r\n\x1a\n".$chunk('IHDR',pack('NNCCCCC',516,900,8,2,0,0,0)).$chunk('IDAT',gzcompress(str_repeat("\0".str_repeat("\xff",516*3),900))).$chunk('IEND','');
try{cd_community_png($portrait);throw new RuntimeException('Full portrait screenshot accepted');}catch(InvalidArgumentException $e){check(str_contains($e->getMessage(),'516 × 900'));}
if(function_exists('imagejpeg')){
    $im=imagecreatetruecolor(256,256);ob_start();imagejpeg($im);$jpg=ob_get_clean();imagedestroy($im);
    check(str_starts_with(cd_community_png($jpg),"\x89PNG\r\n\x1a\n"));
}
echo "Community QR: RGB and monochrome PNG, JPEG conversion and precise dimensions OK\n";

// BASIC license is passive, does not include partner search or booking, and snapshots enforce the plan.
check(cd_plan_modules('INFO')===CD_BASIC_MODULES);
check(!in_array('partner_search',cd_plan_modules('INFO'),true)&&in_array('partner_search',cd_plan_modules('INTERACTIVE'),true));
try{cd_save($db,array_replace($in,['slug'=>'basic-plan-test','modules'=>['welcome','partner_search']]),true);throw new RuntimeException('BASIC partner module accepted');}catch(InvalidArgumentException $e){}
check(cd_snapshot($db,'test-club','clubhaus','test-club')['plan']==='INFO');
check(cd_snapshot($db,'ntc93','managed-one','ntc93')['plan']==='INTERACTIVE');
echo "BASIC/PLUS entitlement tests passed\n";
