<?php
// TENNILO ClubDisplay | Copyright © 2026 TENNILO | Alle Rechte vorbehalten.
declare(strict_types=1);
const EVENTS_SITE='https://ntc93.com';
const EVENTS_TTL=1800;
const EVENTS_CACHE_VERSION=2;
function eventText($s): string {
    return trim(preg_replace('/\s+/u',' ',html_entity_decode(strip_tags((string)$s),ENT_QUOTES|ENT_HTML5,'UTF-8')) ?? '');
}
function eventLink($s): ?string {
    $s=(string)$s; $p=parse_url($s);
    return is_array($p)&&($p['scheme']??'')==='https'&&in_array(strtolower($p['host']??''),['ntc93.com','www.ntc93.com'],true)&&!isset($p['user'],$p['pass'])?$s:null;
}
function eventDate(string $s): ?DateTimeImmutable {
    if(!preg_match('/^\d{4}-\d{2}-\d{2}(?:[ T]\d{2}:\d{2}(?::\d{2})?)?$/D',$s))return null;
    try{return new DateTimeImmutable($s,new DateTimeZone('Europe/Berlin'));}catch(Throwable){return null;}
}
function eventRow($id,$title,$start,$end,$allDay,$venue,$url): ?array {
    $title=eventText($title);$start=eventDate((string)$start);$end=eventDate((string)$end);
    if($title===''||!$start)return null;
    if(!$end||$end<$start)$end=$start;
    return ['id'=>(string)$id,'title'=>$title,'start'=>$start->format(DATE_ATOM),'end'=>$end->format(DATE_ATOM),'allDay'=>(bool)$allDay,'venue'=>eventText($venue),'url'=>eventLink($url)];
}
function eventRequest(string $url): string {
    if(!function_exists('curl_init'))throw new RuntimeException('cURL unavailable');
    $c=curl_init($url);$body='';
    curl_setopt_array($c,[CURLOPT_CONNECTTIMEOUT=>4,CURLOPT_TIMEOUT=>8,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2,CURLOPT_USERAGENT=>'TENNILO ClubDisplay/0.3 (public event calendar)',CURLOPT_WRITEFUNCTION=>static function($c,string $chunk)use(&$body):int{if(strlen($body)+strlen($chunk)>3000000)return 0;$body.=$chunk;return strlen($chunk);}]);
    $ok=curl_exec($c);$status=curl_getinfo($c,CURLINFO_RESPONSE_CODE);curl_close($c);
    if(!$ok||$status!==200||$body==='')throw new RuntimeException('Calendar HTTP '.$status);
    return $body;
}
function eventRest(string $from,string $to): array {
    $rows=[];
    for($p=1;$p<=5;$p++){
        $url=EVENTS_SITE.'/wp-json/tribe/events/v1/events?'.http_build_query(['start_date'=>$from.' 00:00:00','end_date'=>$to.' 23:59:59','per_page'=>50,'page'=>$p]);
        $d=json_decode(eventRequest($url),true,512,JSON_THROW_ON_ERROR);
        if(!isset($d['events'])||!is_array($d['events']))throw new RuntimeException('Unexpected event response');
        foreach($d['events'] as $e){
            $row=eventRow($e['id']??'', $e['title']??'', $e['start_date']??'', $e['end_date']??'', $e['all_day']??false, $e['venue']['venue']??'', $e['url']??'');
            if($row)$rows[]=$row;
        }
        if($p>=max(1,(int)($d['total_pages']??1)))return $rows;
    }
    throw new RuntimeException('Calendar page limit exceeded');
}
function icalText(string $v): string {
    return preg_replace_callback('/\\\\([nN,;\\\\])/',fn($m)=>strtolower($m[1])==='n'?' ':$m[1],$v)??'';
}
function icalDate(string $key,string $value): ?DateTimeImmutable {
    $value=trim($value);$zone=new DateTimeZone('Europe/Berlin');
    try{
        if(preg_match('/(?:^|;)TZID=([^;:]+)/i',$key,$m))$zone=new DateTimeZone(trim($m[1],'"'));
        if(preg_match('/^\d{8}$/D',$value))$d=DateTimeImmutable::createFromFormat('!Ymd',$value,$zone);
        elseif(preg_match('/^\d{8}T\d{6}Z$/D',$value))$d=DateTimeImmutable::createFromFormat('!Ymd\THis\Z',$value,new DateTimeZone('UTC'));
        elseif(preg_match('/^\d{8}T\d{6}$/D',$value))$d=DateTimeImmutable::createFromFormat('!Ymd\THis',$value,$zone);
        else return null;
        return $d?$d->setTimezone(new DateTimeZone('Europe/Berlin')):null;
    }catch(Throwable){return null;}
}
function parseCalendarIcs(string $raw): array {
    if(!str_contains($raw,'BEGIN:VCALENDAR')||!str_contains($raw,'END:VCALENDAR'))throw new RuntimeException('Invalid iCalendar');
    $raw=preg_replace('/\r?\n[ \t]/','',$raw)??'';
    $rows=[];$e=null;
    foreach(preg_split('/\r\n|\n|\r/',$raw) as $line){
        if($line==='BEGIN:VEVENT'){$e=[];continue;}
        if($line==='END:VEVENT'&&is_array($e)){
            if(($e['STATUS'][1]??'')==='CANCELLED'){$e=null;continue;}
            // Do not silently invent instances of recurring rules.
            if(isset($e['RRULE'])){$e=null;continue;}
            $s=isset($e['DTSTART'])?icalDate(...$e['DTSTART']):null;
            $end=isset($e['DTEND'])?icalDate(...$e['DTEND']):$s;
            if($s){
                $all=preg_match('/^\d{8}$/D',$e['DTSTART'][1])===1;
                if($all&&$end&&$end>$s)$end=$end->modify('-1 second');
                $row=eventRow($e['UID'][1]??'',icalText($e['SUMMARY'][1]??''),$s->format('Y-m-d H:i:s'),($end??$s)->format('Y-m-d H:i:s'),$all,icalText($e['LOCATION'][1]??''),$e['URL'][1]??'');
                if($row)$rows[]=$row;
            }
            $e=null;continue;
        }
        if($e===null||!str_contains($line,':'))continue;
        [$k,$v]=explode(':',$line,2);$name=strtoupper(explode(';',$k,2)[0]);
        if(in_array($name,['UID','SUMMARY','DTSTART','DTEND','LOCATION','URL','STATUS','RRULE'],true))$e[$name]=[$k,$v];
    }
    return $rows;
}
function eventRange(DateTimeImmutable $now): array {
    return [$now->format('Y-m-d'),$now->modify('+12 months')->format('Y-m-d')];
}
function eventCacheFresh(?array $cached,int $now): bool {
    return $cached!==null&&($cached['cacheVersion']??0)===EVENTS_CACHE_VERSION&&$now-($cached['fetchedAt']??0)<EVENTS_TTL;
}
function fetchEvents(): array {
    $now=new DateTimeImmutable('today',new DateTimeZone('Europe/Berlin'));
    [$from,$to]=eventRange($now);
    try{$rows=eventRest($from,$to);$method='rest';}
    catch(Throwable){
        $rows=[];$method='ical';$month=$now->modify('first day of this month');
        // The public calendar offers a monthly iCalendar export.
        // Request only three months in fallback mode.
        for($i=0;$i<3;$i++){
            $date=$month->modify("+$i months")->format('Y-m');
            $rows=array_merge($rows,parseCalendarIcs(eventRequest(EVENTS_SITE.'/events/monat/?'.http_build_query(['ical'=>1,'eventDate'=>$date]))));
        }
        $to=$month->modify('+2 months')->modify('last day of this month')->format('Y-m-d');
    }
    $out=[];foreach($rows as $e){
        if(substr($e['end'],0,10)<$from||substr($e['start'],0,10)>$to)continue;
        $key=$e['title'].'|'.$e['start'].'|'.$e['end'];$out[$key]=$e;
    }
    $out=array_values($out);usort($out,fn($a,$b)=>strcmp($a['start'],$b['start']));
    return ['events'=>$out,'method'=>$method,'from'=>$from,'to'=>$to,'fetchedAt'=>time(),'cacheVersion'=>EVENTS_CACHE_VERSION];
}
function getEvents(): array {
    $dir=sys_get_temp_dir().'/tennilo-events-'.hash('sha256',__DIR__);
    if(!is_dir($dir)&&!@mkdir($dir,0700)&&!is_dir($dir))throw new RuntimeException('Cache unavailable');
    $lock=fopen($dir.'/lock','c');if(!$lock)throw new RuntimeException('Cache unavailable');
    try{
        if(!flock($lock,LOCK_EX))throw new RuntimeException('Cache unavailable');
        $file=$dir.'/events.json';$retry=$dir.'/retry-v'.EVENTS_CACHE_VERSION;$now=time();
        $cached=is_file($file)?json_decode((string)file_get_contents($file),true):null;
        if(is_array($cached)&&eventCacheFresh($cached,$now))return $cached+['status'=>'ok'];
        if(!is_file($retry)||$now-(int)file_get_contents($retry)>=300){
            file_put_contents($retry,(string)$now,LOCK_EX);
            try{
                $data=fetchEvents();
                if(file_put_contents($file.'.tmp',json_encode($data,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE),LOCK_EX)===false||!rename($file.'.tmp',$file))throw new RuntimeException('Cache write failed');
                return $data+['status'=>'ok'];
            }catch(Throwable $e){error_log('ClubDisplay events: '.$e->getMessage());}
        }
        if(is_array($cached)&&$now-($cached['fetchedAt']??0)<=7*86400)return $cached+['status'=>'stale'];
        return ['status'=>'unavailable','events'=>[],'fetchedAt'=>null];
    }finally{fclose($lock);}
}
if(defined('CLUBDISPLAY_EVENTS_TEST'))return;
header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store');header('X-Content-Type-Options: nosniff');
try{$result=getEvents();}catch(Throwable $e){error_log('ClubDisplay events: '.$e->getMessage());$result=['status'=>'unavailable','events'=>[],'fetchedAt'=>null];}
echo json_encode($result+['source'=>EVENTS_SITE.'/events/'],JSON_UNESCAPED_UNICODE|JSON_INVALID_UTF8_SUBSTITUTE);
