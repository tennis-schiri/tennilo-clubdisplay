<?php
declare(strict_types=1);
require __DIR__.'/../public/ntc/devices.php';
$db=cdtest_db(':memory:');$now=1000000;
$c=cdtest_issue($db,'Büro',$now);$t=cdtest_register($db,$c,$now);
if(!$t||cdtest_register($db,$c,$now)!==null||cdtest_lookup($db,$t,$now)['label']!=='Büro')throw new RuntimeException('registration');
if($db->query('SELECT digest FROM devices')->fetchColumn()===$t)throw new RuntimeException('raw token');
if(cdtest_lookup($db,$t,$now+31536001)!==null)throw new RuntimeException('expiry');
$db->exec('DELETE FROM devices');if(cdtest_lookup($db,$t,$now)!==null)throw new RuntimeException('revoke');
$c=cdtest_issue($db,'Laptop',$now);if(cdtest_register($db,$c,$now+901)!==null)throw new RuntimeException('code expiry');
for($i=0;$i<8;$i++)if(!cdtest_attempt($db,$now))throw new RuntimeException('attempt budget');
if(cdtest_attempt($db,$now))throw new RuntimeException('rate limit');
echo "NTC test device lifecycle OK\n";
