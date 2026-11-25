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
