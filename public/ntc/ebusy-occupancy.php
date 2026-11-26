<?php
// NTC-only, read-only eBuSy adapter. Never returns raw booking or person data.
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
function eb_slots(array $courts,array $rows,string $area,int $start,DateTimeZone $tz): array {
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
        $intervals[$map[$b['courtId']]][]=[$a,$z,$b['type']==='BLOCKING'];
    }
    $slots=[];
    foreach($map as $court)for($i=0;$i<3;$i++){
        $a=$start+$i*3600;$z=$a+3600;$pieces=[];$booked=false;
        foreach($intervals[$court]??[] as [$x,$y,$blocked])if($x<$z&&$y>$a){$pieces[]=[max($a,$x),min($z,$y)];if(!$blocked)$booked=true;}
        sort($pieces);$covered=0;$end=$a;
        foreach($pieces as [$x,$y]){if($y>$end){$covered+=$y-max($x,$end);$end=$y;}}
        $status=$covered===0?'free':($covered<3600?'partial':($booked?'occupied':'blocked'));
        $slots[]=['area'=>$area,'court'=>$court,'start'=>gmdate('Y-m-d\TH:i:s.000\Z',$a),'end'=>gmdate('Y-m-d\TH:i:s.000\Z',$z),'status'=>$status];
    }
    return $slots;
}
function eb_snapshot(array $cfg,int $now): array {
    $tz=new DateTimeZone('Europe/Berlin');$local=(new DateTimeImmutable('@'.$now))->setTimezone($tz);
    $start=$now-(int)$local->format('i')*60-(int)$local->format('s');
    $from=$local->format('Y-m-d');$to=(new DateTimeImmutable('@'.($start+10799)))->setTimezone($tz)->format('Y-m-d');
    // Local booking times are ambiguous on DST transition days. Fail closed.
    if(count($tz->getTransitions($start-86400,$start+86400))>1)throw new RuntimeException('dst_transition');
    $slots=[];
    foreach(['indoor'=>'court/modules/3076','outdoor'=>'lite/modules/1731'] as $area=>$path){
        $courts=eb_get($cfg,$path.'/courts');
        $rows=eb_get($cfg,$path.'/reservations',['fromDate'=>$from,'toDate'=>$to,'includeCancelled'=>'false']);
        $slots=array_merge($slots,eb_slots($courts,$rows,$area,$start,$tz));
    }
    return ['status'=>'ok','fetchedAt'=>$now,'slots'=>$slots];
}
if(PHP_SAPI==='cli')return;
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, private');header('X-Content-Type-Options: nosniff');header('Referrer-Policy: no-referrer');
ini_set('display_errors','0');
function eb_reply(int $status,array $body): never {http_response_code($status);echo json_encode($body);exit;}
if(($_SERVER['HTTP_HOST']??'')!=='clubdisplay.ntc93.de'||($_SERVER['REQUEST_METHOD']??'')!=='GET')eb_reply(404,['status'=>'unavailable']);
$file=__DIR__.'/ebusy-private/config.php';
if(!is_file($file))eb_reply(403,['status'=>'restricted']);
$cfg=require $file;
$check=isset($_SERVER['HTTP_X_CLUBDISPLAY_CHECK'])&&is_string($cfg['check']??null)&&strlen($cfg['check'])===64&&hash_equals($cfg['check'],$_SERVER['HTTP_X_CLUBDISPLAY_CHECK']);
$ip=$cfg['ip']??'';
$local=filter_var($ip,FILTER_VALIDATE_IP)!==false&&inet_pton($ip)===inet_pton($_SERVER['REMOTE_ADDR']??'');
if(!$check&&!$local)eb_reply(403,['status'=>'restricted']);
if(empty($cfg['user'])||empty($cfg['password'])||!function_exists('curl_init'))eb_reply(503,['status'=>'unconfigured']);
$lock=@fopen(__DIR__.'/ebusy-private/cache.lock','c+');
if(!$lock||!flock($lock,LOCK_EX)){eb_reply(503,['status'=>'unavailable']);}
$now=time();$cache=json_decode((string)stream_get_contents($lock),true);
if(!is_array($cache)||($cache['retryAt']??0)<=$now){
    try{$cache=['retryAt'=>$now+60,'data'=>eb_snapshot($cfg,$now)];}
    catch(Throwable $e){$code=$e instanceof RuntimeException?$e->getMessage():'unexpected_response';if(!preg_match('/^(http_\d{3}|api_error|unexpected_response|court_id|court_mapping|no_courts|invalid_booking|booking_mapping|invalid_time|booking_range|dst_transition)$/D',$code))$code='unexpected_response';$cache=['retryAt'=>$now+max(60,(int)$e->getCode()),'data'=>['status'=>'unavailable','reason'=>$code]];}
    rewind($lock);if(!ftruncate($lock,0)||fwrite($lock,json_encode($cache))===false||!fflush($lock)){fclose($lock);eb_reply(503,['status'=>'unavailable']);}
}
fclose($lock);$data=$cache['data']??['status'=>'unavailable'];
if($check)eb_reply(200,['status'=>$data['status'],'reason'=>$data['reason']??null,'slotCount'=>count($data['slots']??[]),'onsiteConfigured'=>filter_var($ip,FILTER_VALIDATE_IP)!==false]);
eb_reply(200,$data['status']==='ok'?$data:['status'=>'unavailable']);
