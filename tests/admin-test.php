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
