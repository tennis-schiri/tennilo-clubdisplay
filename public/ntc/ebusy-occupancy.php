<?php
// NTC-only read-only adapter; only display names, court and time, never raw booking records.
declare(strict_types=1);
function eb_list(mixed $v): array {
    if(is_array($v)&&!array_is_list($v)){
        if(!empty($v['error']))throw new RuntimeException('api_error');
        $v=$v['response']??null;
    }
    if(!is_array($v)||!array_is_list($v))throw new RuntimeException('unexpected_response');
    return $v;
}
function eb_get(array $cfg,string $path,array $query=[]): array {
    if(!preg_match('~^(court/modules/3076|lite/modules/1731)/(courts|reservations)$~D',$path))throw new RuntimeException('invalid_path');
    $url='https://ntc93v8.ebusy.de/api/'.$path.($query?'?'.http_build_query($query):'');
    $body='';$retry=60;$ch=curl_init($url);
    curl_setopt_array($ch,[CURLOPT_HTTPAUTH=>CURLAUTH_BASIC,CURLOPT_USERPWD=>$cfg['user'].':'.$cfg['password'],CURLOPT_HTTPHEADER=>['Accept: application/json'],CURLOPT_FOLLOWLOCATION=>false,CURLOPT_PROTOCOLS=>CURLPROTO_HTTPS,CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2,CURLOPT_CONNECTTIMEOUT=>5,CURLOPT_TIMEOUT=>10,CURLOPT_WRITEFUNCTION=>function($c,$s)use(&$body){if(strlen($body)+strlen($s)>4000000)return 0;$body.=$s;return strlen($s);},CURLOPT_HEADERFUNCTION=>function($c,$s)use(&$retry){if(preg_match('/^Retry-After:\s*(\d+)/i',$s,$m))$retry=max(60,(int)$m[1]);return strlen($s);}]);
    $ok=curl_exec($ch);$status=(int)curl_getinfo($ch,CURLINFO_RESPONSE_CODE);curl_close($ch);
    if($status===429||$status===503)throw new RuntimeException('http_'.$status,$retry);
    if(!$ok||$status!==200)throw new RuntimeException('http_'.$status);
    return eb_list(json_decode($body,true,512,JSON_THROW_ON_ERROR));
}
function eb_time(string $date,string $time,DateTimeZone $tz): int {
    if(!preg_match('/^\d{2}\.\d{2}\.\d{4}$/D',$date)||!preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/D',$time)&&$time!=='24:00')throw new RuntimeException('invalid_time');
    $d=DateTimeImmutable::createFromFormat('!d.m.Y H:i',$date.' '.($time==='24:00'?'00:00':$time),$tz);
    if(!$d||$d->format('d.m.Y H:i')!==$date.' '.($time==='24:00'?'00:00':$time))throw new RuntimeException('invalid_time');
    return ($time==='24:00'?$d->modify('+1 day'):$d)->getTimestamp();
}
function eb_name(mixed $v):string {
    if(!is_array($v))return '';
    $parts=[];
    foreach(['firstname','lastname'] as $k){
        $p=$v[$k]??null;
        if(is_string($p)&&preg_match("/^[\p{L}\p{M} .'\x{2019}-]{1,80}$/u",trim($p)))$parts[]=trim($p);
    }
    return implode(' ',$parts);
}
function eb_display(mixed $v):string{
    if(!is_string($v))return '';
    $v=trim(preg_replace('/\s+/u',' ',$v)??'');
    return preg_match("~^[\p{L}\p{M}\d .,'’&()/+\-–]{1,140}$~u",$v)&&!preg_match('/\d{5,}/',$v)?$v:'';
}
function eb_team(mixed $value):string{
    if(!is_string($value))return '';
    $v=trim(preg_replace('/\s+/u',' ',$value)??'');
    $v=preg_replace('/^(?:Punktspiel|Heimspiel|Verbandsspiel|Mannschaftsspiel)\s*[:–-]?\s*/iu','',$v);
    if(!preg_match('/^(Herren|Damen|H|D|U\d{1,2}\s*[mw]?|Mixed)\s*(\d{2})?\s*(IV|III|II|I|V|[1-5])?$/iu',$v,$m))return '';
    $kind=strtoupper(preg_replace('/\s+/u','',$m[1]));
    $kind=match($kind){'HERREN'=>'H','DAMEN'=>'D',default=>$kind};
    $age=$m[2]??'';if($age==='00')$age='';
    $suffix=strtoupper($m[3]??'');
    $suffix=match($suffix){'I','1',''=>'1','II','2'=>'2','III','3'=>'3','IV','4'=>'4','V','5'=>'5',default=>''};
    return $kind.$age.':'.$suffix;
}
function eb_match_team(array $b):string{
    $label=$b['displayText']??'';$type=$b['bookingTypeName']??'';
    if(!is_string($label)||!is_string($type))return '';
    if(!preg_match('/\b(?:Punktspiel|Heimspiel|Verbandsspiel|Mannschaftsspiel)\b/iu',$label.' '.$type))return '';
    return eb_team($label);
}
// eBuSy exposes bookingTypeName, but no color field. Use only a fixed category palette.
function eb_category(array $b):string{
    $type=is_string($b['bookingTypeName']??null)?$b['bookingTypeName']:'';
    $label=is_string($b['displayText']??null)?$b['displayText']:'';
    $text=$type.' '.$label;
    if(preg_match('/\b(?:Punktspiel|Heimspiel|Verbandsspiel|Mannschaftsspiel)(?:e|en)?\b/iu',$text))return 'match';
    if(preg_match('/\b(?:Turnier|Clubmeisterschaft|Vereinsmeisterschaft)(?:e|en)?\b/iu',$text))return 'tournament';
    if(preg_match('/Jugendtraining|Kindertraining|Jugendkurs|Kinderkurs/iu',$text))return 'junior';
    if(preg_match('/Training|Trainerstunde|Tenniskurs/iu',$text))return 'training';
    if(($b['type']??'')==='BLOCKING'||preg_match('/Platzpflege|Sperrung|Wartung/iu',$type))return 'blocked';
    if(($b['guestBooking']??false)===true||preg_match('/Gastbuchung|Gastspieler/iu',$type))return 'guest';
    if(preg_match('/Doppel/iu',$type))return 'doubles';
    return 'regular';
}
function eb_label(array $b):string{
    $display=eb_display($b['displayText']??null);
    if(in_array(eb_category($b),['match','training','junior','tournament','blocked'],true))return $display!==''?$display:eb_display($b['bookingTypeName']??null);
    $names=[];$main=eb_name($b['person']??null);if($main!=='')$names[]=$main;
    foreach(is_array($b['additionalPlayerInfos']??null)?$b['additionalPlayerInfos']:[] as $p){
        if(!is_array($p))continue;
        $name=eb_name($p['player']??null);
        if($name===''&&($p['type']??'')!=='PARTNER_WANTED')$name=eb_display($p['displayName']??null);
        if($name!=='')$names[]=$name;
    }
    return $names?implode(' / ',array_slice(array_unique($names),0,4)):$display;
}
function eb_slots(array $courts,array $rows,string $area,int $start,DateTimeZone $tz,array &$matchBookings=[]): array {
    $expected=$area==='indoor'?2:7;$map=[];$numbers=[];
    foreach($courts as $c){
        if(!is_array($c)||!is_int($c['id']??null)||$c['id']<1)throw new RuntimeException('court_id');
        $n=null;
        foreach(['name','shortName'] as $field)if(is_string($c[$field]??null)&&preg_match('/(?:^|[^\d])([1-9]\d*)\s*$/u',$c[$field],$m)){$n=(int)$m[1];break;}
        if($n===null||$n>$expected||isset($numbers[$n])||isset($map[$c['id']]))throw new RuntimeException('court_mapping');
        $map[$c['id']]=$n;$numbers[$n]=true;
    }
    if(!$map)throw new RuntimeException('no_courts');
    $intervals=[];
    foreach($rows as $b){
        if(!is_array($b))throw new RuntimeException('invalid_booking');
        if(($b['cancelled']??false)===true)continue;
        if(!isset($map[$b['courtId']??0])||!in_array($b['type']??null,['BOOKING','BLOCKING'],true))throw new RuntimeException('booking_mapping');
        $a=eb_time($b['date']??'',$b['fromTime']??'',$tz);$z=eb_time($b['date']??'',$b['toTime']??'',$tz);
        if($z<=$a)throw new RuntimeException('booking_range');
        $court=$map[$b['courtId']];
        $team=eb_match_team($b);
        if($team!==''&&$z>$start)$matchBookings[]=['team'=>$team,'area'=>$area,'court'=>$court,'start'=>gmdate('Y-m-d\TH:i:s.000\Z',$a),'end'=>gmdate('Y-m-d\TH:i:s.000\Z',$z)];
        if($a<$start+10800&&$z>$start)$intervals[$court][]=[$a,$z,$b['type']==='BLOCKING',eb_label($b),eb_category($b)];
    }
    $slots=[];
    foreach($map as $court)for($i=0;$i<3;$i++){
        $a=$start+$i*3600;$z=$a+3600;$pieces=[];$booked=false;$bookings=[];
        foreach($intervals[$court]??[] as [$x,$y,$blocked,$label,$category])if($x<$z&&$y>$a){$pieces[]=[max($a,$x),min($z,$y)];if(!$blocked)$booked=true;$bookings[]=['displayLabel'=>$label,'category'=>$category,'start'=>gmdate('Y-m-d\TH:i:s.000\Z',$x),'end'=>gmdate('Y-m-d\TH:i:s.000\Z',$y)];}
        sort($pieces);$covered=0;$end=$a;
        foreach($pieces as [$x,$y]){if($y>$end){$covered+=$y-max($x,$end);$end=$y;}}
        $status=$covered===0?'free':($covered<3600?'partial':($booked?'occupied':'blocked'));
        $slots[]=['area'=>$area,'court'=>$court,'start'=>gmdate('Y-m-d\TH:i:s.000\Z',$a),'end'=>gmdate('Y-m-d\TH:i:s.000\Z',$z),'status'=>$status,'bookings'=>$bookings];
    }
    return $slots;
}
function eb_snapshot(array $cfg,int $now,array &$diagnostics=[]): array {
    $tz=new DateTimeZone('Europe/Berlin');$local=(new DateTimeImmutable('@'.$now))->setTimezone($tz);
    $start=$now-(int)$local->format('i')*60-(int)$local->format('s');
    $from=$local->format('Y-m-d');$to=$local->modify('+7 days')->format('Y-m-d');
    // Local booking times are ambiguous on DST transition days. Fail closed.
    if(count($tz->getTransitions($start-86400,$start+86400))>1)throw new RuntimeException('dst_transition');
    $slots=[];$failure=null;$results=[];$matchBookings=[];
    foreach(['indoor'=>'court/modules/3076','outdoor'=>'lite/modules/1731'] as $area=>$path){
        foreach(['courts','reservations'] as $kind){
            $endpoint=$path.'/'.$kind;
            try{
                $results[$area][$kind]=eb_get($cfg,$endpoint,$kind==='reservations'?['fromDate'=>$from,'toDate'=>$to,'includeCancelled'=>'false']:[]);
                $diagnostics[$endpoint]='ok';
            }catch(Throwable $e){
                $reason=$e instanceof RuntimeException?$e->getMessage():'unexpected_response';
                $diagnostics[$endpoint]=preg_match('/^(http_\d{3}|api_error|unexpected_response)$/D',$reason)?$reason:'unexpected_response';
                $failure??=$e;
                // Do not continue after rate limits, maintenance or invalid authentication.
                if(in_array($reason,['http_429','http_503','http_401'],true))throw $e;
            }
        }
    }
    if($failure)throw $failure;
    foreach($results as $area=>$data){
        try{$slots=array_merge($slots,eb_slots($data['courts'],$data['reservations'],$area,$start,$tz,$matchBookings));$diagnostics[$area.'_mapping']='ok';}
        catch(Throwable $e){$diagnostics[$area.'_mapping']='failed';throw $e;}
    }
    return ['status'=>'ok','fetchedAt'=>$now,'slots'=>$slots,'matchBookings'=>$matchBookings];
}
// Authorization is checked on every request before reading the occupancy cache.
define('CD_DEVICE_LIBRARY_ONLY',true);
require_once __DIR__.'/devices.php';
function eb_registered_device(?PDO $db,string $token,int $now):bool{
    return $db!==null&&cdtest_lookup($db,$token,$now)!==null;
}
if(PHP_SAPI==='cli')return;
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, private');header('X-Content-Type-Options: nosniff');header('Referrer-Policy: no-referrer');
ini_set('display_errors','0');
function eb_reply(int $status,array $body): never {http_response_code($status);echo json_encode($body);exit;}
if(($_SERVER['HTTP_HOST']??'')!=='clubdisplay.ntc93.de'||($_SERVER['REQUEST_METHOD']??'')!=='GET'||empty($_SERVER['HTTPS'])||$_SERVER['HTTPS']==='off')eb_reply(404,['status'=>'unavailable']);
$file=__DIR__.'/ebusy-private/config.php';
if(!is_file($file))eb_reply(403,['status'=>'restricted']);
$cfg=require $file;
$check=isset($_SERVER['HTTP_X_CLUBDISPLAY_CHECK'])&&is_string($cfg['check']??null)&&strlen($cfg['check'])===64&&hash_equals($cfg['check'],$_SERVER['HTTP_X_CLUBDISPLAY_CHECK']);
$ip=$cfg['ip']??'';
$local=filter_var($ip,FILTER_VALIDATE_IP)!==false&&inet_pton($ip)===inet_pton($_SERVER['REMOTE_ADDR']??'');
if(!$check&&!$local){
    $registered=false;$token=$_COOKIE['__Host-CD_TEST']??'';
    $private=__DIR__.'/devices-private';
    // Fail closed on missing/corrupt registration storage. Never trust a client-side flag.
    if(is_string($token)&&preg_match('/^[a-f0-9]{64}$/D',$token)&&is_file($private.'/devices.sqlite')&&is_file($private.'/.htaccess')&&is_file($private.'/config.php')){
        try{$db=cdtest_db($private.'/devices.sqlite');$registered=eb_registered_device($db,$token,time());}catch(Throwable $e){$registered=false;}
    }
    if(!$registered)eb_reply(403,['status'=>'restricted']);
}
if(empty($cfg['user'])||empty($cfg['password'])||!function_exists('curl_init'))eb_reply(503,['status'=>'unconfigured']);
$lock=@fopen(__DIR__.'/ebusy-private/cache-v3.lock','c+');
if(!$lock||!flock($lock,LOCK_EX)){eb_reply(503,['status'=>'unavailable']);}
$now=time();$cache=json_decode((string)stream_get_contents($lock),true);
if(!is_array($cache)||($cache['retryAt']??0)<=$now){
    $diagnostics=[];
    try{$cache=['retryAt'=>$now+60,'data'=>eb_snapshot($cfg,$now,$diagnostics)];}
    catch(Throwable $e){$code=$e instanceof RuntimeException?$e->getMessage():'unexpected_response';if(!preg_match('/^(http_\d{3}|api_error|unexpected_response|court_id|court_mapping|no_courts|invalid_booking|booking_mapping|invalid_time|booking_range|dst_transition)$/D',$code))$code='unexpected_response';$cache=['retryAt'=>$now+max(60,(int)$e->getCode()),'data'=>['status'=>'unavailable','reason'=>$code]];}
    $cache['diagnostics']=$diagnostics;
    rewind($lock);if(!ftruncate($lock,0)||fwrite($lock,json_encode($cache))===false||!fflush($lock)){fclose($lock);eb_reply(503,['status'=>'unavailable']);}
}
fclose($lock);$data=$cache['data']??['status'=>'unavailable'];
if($check)eb_reply(200,['status'=>$data['status'],'reason'=>$data['reason']??null,'slotCount'=>count($data['slots']??[]),'endpoints'=>$cache['diagnostics']??[],'onsiteConfigured'=>filter_var($ip,FILTER_VALIDATE_IP)!==false]);
eb_reply(200,$data['status']==='ok'?$data:['status'=>'unavailable']);
