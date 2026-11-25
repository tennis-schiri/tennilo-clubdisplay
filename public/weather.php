<?php
// TENNILO ClubDisplay | Copyright © 2026 TENNILO | Alle Rechte vorbehalten.
declare(strict_types=1);
date_default_timezone_set('Europe/Berlin');
const LAT=52.5333;
const LON=13.6833;
function weatherGet(string $url,int $max=3000000):?string{
    $c=curl_init($url);
    $body='';
    curl_setopt_array($c,[CURLOPT_CONNECTTIMEOUT=>5,CURLOPT_TIMEOUT=>12,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_USERAGENT=>'TENNILO-ClubDisplay/0.4',CURLOPT_WRITEFUNCTION=>static function($c,$chunk)use(&$body,$max){if(strlen($body)+strlen($chunk)>$max)return 0;$body.=$chunk;return strlen($chunk);}]);
    $ok=curl_exec($c);$code=curl_getinfo($c,CURLINFO_RESPONSE_CODE);curl_close($c);
    return $ok&&$code===200?$body:null;
}
function weatherJson(string $url):?array{
    $body=weatherGet($url);if($body===null)return null;
    $value=json_decode($body,true);return is_array($value)?$value:null;
}
function numericOrNull($v):?float{return is_numeric($v)&&is_finite((float)$v)?(float)$v:null;}
function weatherRecord(array $row):?array{
    $time=strtotime((string)($row['timestamp']??''));if($time===false)return null;
    return ['time'=>$time,'temperature'=>numericOrNull($row['temperature']??null),'wind'=>numericOrNull($row['wind_speed']??null),'gust'=>numericOrNull($row['wind_gust_speed']??null),'humidity'=>numericOrNull($row['relative_humidity']??null),'rain'=>numericOrNull($row['precipitation']??null),'chance'=>numericOrNull($row['precipitation_probability']??null),'icon'=>is_string($row['icon']??null)?$row['icon']:'unknown'];
}
function weatherSun(string $date):array{
    $sun=date_sun_info(strtotime($date.' 12:00:00'),LAT,LON);
    return ['sunrise'=>is_int($sun['sunrise'])?$sun['sunrise']:null,'sunset'=>is_int($sun['sunset'])?$sun['sunset']:null];
}
function loadWeather():?array{
    $now=time();$today=date('Y-m-d');$end=date('Y-m-d',strtotime('+7 days'));
    $query='lat='.LAT.'&lon='.LON;
    $forecast=weatherJson('https://api.brightsky.dev/weather?'.$query.'&date='.$today.'&last_date='.$end.'&tz=Europe%2FBerlin');
    if(!is_array($forecast['weather']??null))return null;
    $hourly=[];$days=[];$nearest=null;
    foreach($forecast['weather'] as $row){
        if(!is_array($row)||!($r=weatherRecord($row)))continue;
        $day=date('Y-m-d',$r['time']);if($day<$today||$day>=$end)continue;
        if($r['temperature']!==null&&($nearest===null||abs($r['time']-$now)<abs($nearest['time']-$now)))$nearest=$r;
        if($r['time']>=$now-1800)$hourly[]=$r;
        if(!isset($days[$day]))$days[$day]=['date'=>$day,'min'=>null,'max'=>null,'chance'=>null,'rain'=>null,'icon'=>'unknown','iconTime'=>PHP_INT_MAX]+weatherSun($day);
        $d=&$days[$day];
        if($r['temperature']!==null){$d['min']=$d['min']===null?$r['temperature']:min($d['min'],$r['temperature']);$d['max']=$d['max']===null?$r['temperature']:max($d['max'],$r['temperature']);}
        if($r['chance']!==null)$d['chance']=$d['chance']===null?$r['chance']:max($d['chance'],$r['chance']);
        if($r['rain']!==null)$d['rain']=($d['rain']??0)+$r['rain'];
        $diff=abs($r['time']-strtotime($day.' 12:00:00'));
        if($r['icon']!=='unknown'&&$diff<$d['iconTime']){$d['icon']=$r['icon'];$d['iconTime']=$diff;}
        unset($d);
    }
    usort($hourly,static fn($a,$b)=>$a['time']<=>$b['time']);
    ksort($days);foreach($days as &$d)unset($d['iconTime']);unset($d);
    if(!$hourly||!$days)return null;
    $observation=weatherJson('https://api.brightsky.dev/current_weather?'.$query.'&tz=Europe%2FBerlin');
    $current=is_array($observation['weather']??null)?weatherRecord($observation['weather']):null;
    $observed=$current&&$current['temperature']!==null&&abs($current['time']-$now)<=10800;
    if(!$observed)$current=$nearest&&abs($nearest['time']-$now)<=7200?$nearest:null;
    return ['status'=>'ok','fetchedAt'=>$now,'current'=>$current,'observed'=>(bool)$observed,'hourly'=>array_slice($hourly,0,24),'daily'=>array_values($days),'sun'=>weatherSun($today),'location'=>'Neuenhagen bei Berlin'];
}
// Same projection, bounding box and dimensions for both WMS layers.
function radarMapParams():array{
    return ['service'=>'WMS','version'=>'1.1.1','request'=>'GetMap','srs'=>'EPSG:4326','bbox'=>'12.4833,51.8033,14.8833,53.2633','width'=>600,'height'=>600,'format'=>'image/png'];
}
function validMapPng(?string $image):bool{
    if($image===null||!str_starts_with($image,"\x89PNG\r\n\x1a\n"))return false;
    $size=@getimagesizefromstring($image);
    return is_array($size)&&$size[0]===600&&$size[1]===600&&$size[2]===IMAGETYPE_PNG;
}
function loadBaseMap():?array{
    $params=radarMapParams()+['layers'=>'de_basemapde_web_raster_farbe','styles'=>'default','transparent'=>'false'];
    $image=weatherGet('https://sgx.geodatenzentrum.de/wms_basemapde?'.http_build_query($params),2000000);
    if(!validMapPng($image))return null;
    return ['status'=>'ok','fetchedAt'=>time(),'image'=>base64_encode($image),'sourceYear'=>(int)date('Y')];
}
function loadRadar():?array{
    // WMS 1.1.1 uses lon/lat order; bounding box centered on the selected locality.
    $stamp=(int)(floor((time()-600)/300)*300);
    for($i=0;$i<2;$i++){
        $t=$stamp-$i*600;
        $url='https://maps.dwd.de/geoserver/dwd/wms?'.http_build_query(['service'=>'WMS','version'=>'1.1.1','request'=>'GetMap','layers'=>'dwd:Radar_rv_product_1x1km_ger','styles'=>'','srs'=>'EPSG:4326','bbox'=>'12.4833,51.8033,14.8833,53.2633','width'=>600,'height'=>600,'format'=>'image/png','transparent'=>'true','time'=>gmdate('Y-m-d\TH:i:s\Z',$t)]);
        $image=weatherGet($url,1500000);
        if(validMapPng($image))return ['status'=>'ok','fetchedAt'=>time(),'time'=>$t,'image'=>base64_encode($image)];
    }
    return null;
}
function cachedWeather(string $kind,int $ttl,int $stale,callable $loader):array{
    $base=sys_get_temp_dir().'/clubdisplay-weather-'.hash('sha256',__DIR__.'-'.$kind);
    $read=static function()use($base){$v=json_decode((string)@file_get_contents($base.'.json'),true);return is_array($v)?$v:null;};
    $old=$read();$now=time();
    if($old&&$now-($old['fetchedAt']??0)<$ttl)return $old;
    $lock=@fopen($base.'.lock','c');
    if($lock&&flock($lock,LOCK_EX)){
        $old=$read();$retry=(int)@file_get_contents($base.'.retry');
        if($old&&$now-($old['fetchedAt']??0)<$ttl){$out=$old;}
        elseif($now-$retry<120){$out=null;}
        else{
            @file_put_contents($base.'.retry',(string)$now);
            try{$out=$loader();}catch(Throwable $e){$out=null;}
            if($out){$tmp=tempnam(sys_get_temp_dir(),'cd-weather-');if($tmp!==false){@chmod($tmp,0600);if(file_put_contents($tmp,json_encode($out))!==false)@rename($tmp,$base.'.json');else @unlink($tmp);}}
        }
        flock($lock,LOCK_UN);fclose($lock);
        if($out)return $out;
    }
    if($old&&$now-($old['fetchedAt']??0)<$stale){$old['status']='stale';return $old;}
    return ['status'=>'unavailable','fetchedAt'=>null];
}
if(defined('CLUBDISPLAY_WEATHER_TEST'))return;
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
if(($_SERVER['REQUEST_METHOD']??'')!=='GET'){http_response_code(405);exit;}
$radar=isset($_GET['radar']);
$base=isset($_GET['basemap']);
echo json_encode($base?cachedWeather('basemap-v1',86400,2592000,'loadBaseMap'):($radar?cachedWeather('radar-v2',300,1800,'loadRadar'):cachedWeather('weather',600,10800,'loadWeather')),JSON_INVALID_UTF8_SUBSTITUTE);
