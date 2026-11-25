<?php
// TENNILO ClubDisplay | Copyright © 2026 TENNILO | Alle Rechte vorbehalten.
declare(strict_types=1);
const NEWS_SITE='https://ntc93.com';
function newsText($value,int $limit):string{
    $s=preg_replace('~<(script|style)\b[^>]*>.*?</\1>~is',' ',(string)$value)??'';
    $s=trim(preg_replace('/\s+/u',' ',html_entity_decode(strip_tags($s),ENT_QUOTES|ENT_HTML5,'UTF-8'))??'');
    if(preg_match('/^(.{'.$limit.'})./us',$s,$m)){
        $s=preg_replace('/\s+\S*$/u','',$m[1])?:$m[1];$s=rtrim($s,' .').'…';
    }
    return $s;
}
function newsRow($title,$excerpt,$date,$link):?array{
    $title=newsText($title,180);$excerpt=newsText($excerpt,240);$p=parse_url((string)$link);
    if($title===''||!is_array($p)||($p['scheme']??'')!=='https'||!in_array(strtolower($p['host']??''),['ntc93.com','www.ntc93.com'],true)||isset($p['user'])||isset($p['pass']))return null;
    if(trim((string)$date)==='')return null;
    try{$d=new DateTimeImmutable((string)$date,new DateTimeZone('Europe/Berlin'));}catch(Throwable){return null;}
    if($d->getTimestamp()>time()+300)return null;
    return ['title'=>$title,'excerpt'=>$excerpt,'date'=>$d->format(DATE_ATOM),'url'=>(string)$link];
}
function newsLatest(array $rows):array{
    usort($rows,static fn($a,$b)=>strtotime($b['date'])<=>strtotime($a['date']));
    $unique=[];foreach($rows as $r){if(!isset($unique[$r['url']]))$unique[$r['url']]=$r;}
    return array_slice(array_values($unique),0,3);
}
function newsRest(string $raw):array{
    $data=json_decode($raw,true,512,JSON_THROW_ON_ERROR);
    if(!is_array($data)||!array_is_list($data))throw new RuntimeException('Invalid posts');
    $rows=[];foreach($data as $p){
        if(!is_array($p)||!empty($p['content']['protected'])||!empty($p['excerpt']['protected']))continue;
        $r=newsRow($p['title']['rendered']??'',$p['excerpt']['rendered']??'',$p['date']??'',$p['link']??'');
        if($r)$rows[]=$r;
    }
    if(!$rows&&$data)throw new RuntimeException('No valid posts');
    return newsLatest($rows);
}
function newsRss(string $raw):array{
    if(!function_exists('simplexml_load_string'))throw new RuntimeException('RSS parser unavailable');
    if(preg_match('/<!DOCTYPE|<!ENTITY/i',$raw))throw new RuntimeException('Unsafe XML');
    $xml=@simplexml_load_string($raw,'SimpleXMLElement',LIBXML_NONET|LIBXML_NOCDATA);
    if(!$xml||!isset($xml->channel))throw new RuntimeException('Invalid RSS');
    $rows=[];foreach($xml->channel->item as $p){
        $r=newsRow((string)$p->title,(string)$p->description,(string)$p->pubDate,(string)$p->link);
        if($r)$rows[]=$r;
    }
    if(!$rows&&count($xml->channel->item)>0)throw new RuntimeException('No valid RSS items');
    return newsLatest($rows);
}
function newsRequest(string $url):string{
    $c=curl_init($url);$body='';
    curl_setopt_array($c,[CURLOPT_CONNECTTIMEOUT=>4,CURLOPT_TIMEOUT=>9,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2,CURLOPT_USERAGENT=>'NTC ClubDisplay/0.4 (public club news)',CURLOPT_WRITEFUNCTION=>static function($c,string $chunk)use(&$body):int{if(strlen($body)+strlen($chunk)>2000000)return 0;$body.=$chunk;return strlen($chunk);}]);
    $ok=curl_exec($c);$status=curl_getinfo($c,CURLINFO_RESPONSE_CODE);curl_close($c);
    if(!$ok||$status!==200||$body==='')throw new RuntimeException('News HTTP '.$status);
    return $body;
}
function newsLoad():array{
    try{
        $url=NEWS_SITE.'/wp-json/wp/v2/posts?'.http_build_query(['per_page'=>10,'orderby'=>'date','order'=>'desc','_fields'=>'date,title,excerpt,link,content.protected']);
        $rows=newsRest(newsRequest($url));$source='wordpress';
    }catch(Throwable){
        $rows=newsRss(newsRequest(NEWS_SITE.'/feed/'));$source='rss';
    }
    return ['status'=>'ok','fetchedAt'=>time(),'source'=>$source,'posts'=>$rows];
}
function newsCache():array{
    $base=sys_get_temp_dir().'/clubdisplay-news-'.hash('sha256',__DIR__);
    $read=static function()use($base){$d=json_decode((string)@file_get_contents($base.'.json'),true);return is_array($d)&&isset($d['posts'],$d['fetchedAt'])?$d:null;};
    $old=$read();$now=time();
    if($old&&$now-$old['fetchedAt']<900)return $old;
    $lock=@fopen($base.'.lock','c');
    if($lock&&flock($lock,LOCK_EX)){
        $old=$read();
        try{
            if($old&&$now-$old['fetchedAt']<900)return $old;
            if($now-(int)@file_get_contents($base.'.retry')>=120){
                @file_put_contents($base.'.retry',(string)$now);
                $result=newsLoad();
                $tmp=tempnam(sys_get_temp_dir(),'cd-news-');
                if($tmp!==false){@chmod($tmp,0600);if(file_put_contents($tmp,json_encode($result,JSON_INVALID_UTF8_SUBSTITUTE))!==false)@rename($tmp,$base.'.json');else @unlink($tmp);}
                return $result;
            }
        }catch(Throwable){}finally{flock($lock,LOCK_UN);fclose($lock);}
    }
    if($old&&$now-$old['fetchedAt']<604800){$old['status']='stale';return $old;}
    return ['status'=>'unavailable','fetchedAt'=>null,'posts'=>[]];
}
if(defined('CLUBDISPLAY_NEWS_TEST'))return;
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
if(($_SERVER['REQUEST_METHOD']??'')!=='GET'){http_response_code(405);exit;}
if(($_SERVER['HTTP_HOST']??'')!=='clubdisplay.ntc93.de'){http_response_code(404);echo '{"status":"unavailable","posts":[]}';exit;}
echo json_encode(newsCache(),JSON_INVALID_UTF8_SUBSTITUTE);
