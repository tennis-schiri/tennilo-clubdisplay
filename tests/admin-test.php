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
