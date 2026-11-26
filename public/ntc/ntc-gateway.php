<?php
declare(strict_types=1);
// Fixed NTC-only HTTPS gateway. No database or operator access on this host.
header('Cache-Control: no-store, private');
header('X-Robots-Tag: noindex, nofollow');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');
header("Content-Security-Policy: default-src 'none'; style-src 'self'; img-src 'self'; form-action 'self'; frame-ancestors 'none'; base-uri 'none'");
function ntc_fail(int $status):never {
    http_response_code($status);header('Content-Type: text/plain; charset=utf-8');
    exit($status===503?'Der Vereinsservice ist momentan nicht erreichbar. Bitte später erneut versuchen.':'Anfrage nicht verfügbar.');
}
if(!defined('CD_NTC_ROUTE')||!in_array(CD_NTC_ROUTE,['admin','partner'],true)||($_SERVER['HTTP_HOST']??'')!=='clubdisplay.ntc93.de'||empty($_SERVER['HTTPS'])||$_SERVER['HTTPS']==='off')ntc_fail(404);
$method=$_SERVER['REQUEST_METHOD']??'';
if(!in_array($method,['GET','POST'],true))ntc_fail(405);
if($method==='POST'&&isset($_SERVER['HTTP_ORIGIN'])&&$_SERVER['HTTP_ORIGIN']!=='https://clubdisplay.ntc93.de')ntc_fail(403);
if(!function_exists('curl_init'))ntc_fail(503);
if((int)($_SERVER['CONTENT_LENGTH']??0)>3000000)ntc_fail(413);
$partner=CD_NTC_ROUTE==='partner';
if(!$partner&&($_GET['partner']??'')==='submit'&&$method==='GET'){header('Location: /spielpartner/',true,303);exit;}
$query=$partner?['partner'=>'submit','club'=>'ntc93']:['portal'=>'club','verein'=>'ntc93'];
if($partner){
    $action=$_GET['partner']??'submit';
    if(!in_array($action,['submit','moderate'],true))ntc_fail(404);
    $query['partner']=$action;
    foreach(['mode','ref','token','decision'] as $key)if(isset($_GET[$key])&&is_string($_GET[$key]))$query[$key]=$_GET[$key];
}else{
    foreach(['preview','preview-data','screen','media','image','partner_review'] as $key)if(isset($_GET[$key])&&is_string($_GET[$key]))$query[$key]=$_GET[$key];
    if(isset($_GET['preview'])||isset($_GET['preview-data'])||isset($_GET['media']))$query['club']='ntc93';
    if(isset($_GET['logo']))$query['logo']='ntc93';
}
$url='https://clubdisplay.tennilo.de/admin/?'.http_build_query($query,'','&',PHP_QUERY_RFC3986);
$headers=['Accept: */*','Expect:'];
if($method==='POST')$headers[]='Origin: https://clubdisplay.tennilo.de';
$ch=curl_init($url);$responseHeaders=[];$body='';
curl_setopt_array($ch,[
    CURLOPT_FOLLOWLOCATION=>false,CURLOPT_CONNECTTIMEOUT=>8,CURLOPT_TIMEOUT=>45,
    CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2,CURLOPT_PROTOCOLS=>CURLPROTO_HTTPS,
    CURLOPT_HTTPHEADER=>$headers,CURLOPT_ENCODING=>'',
    CURLOPT_HEADERFUNCTION=>static function($ch,string $line)use(&$responseHeaders):int{
        if(str_starts_with($line,'HTTP/'))$responseHeaders=[];
        elseif(str_contains($line,':')){[$k,$v]=explode(':',$line,2);$responseHeaders[strtolower(trim($k))][]=trim($v);}
        return strlen($line);
    },
    CURLOPT_WRITEFUNCTION=>static function($ch,string $chunk)use(&$body):int{
        if(strlen($body)+strlen($chunk)>12000000)return 0;
        $body.=$chunk;return strlen($chunk);
    }
]);
if(!$partner&&is_string($_COOKIE['CD_CLUB']??null)&&preg_match('/^[a-zA-Z0-9,-]{16,128}$/D',$_COOKIE['CD_CLUB']))curl_setopt($ch,CURLOPT_COOKIE,'CD_CLUB='.$_COOKIE['CD_CLUB']);
if($method==='POST'){
    $post=$_POST;
    if(!$partner&&isset($post['club']))$post['club']='ntc93';
    if(!$partner&&isset($post['action'])&&!in_array($post['action'],['login','logout','brand','content','media','dining','sources','source-test','partner-board','display-create','display-save','device-issue','device-revoke'],true))ntc_fail(403);
    if($partner&&$_FILES)ntc_fail(400);
    curl_setopt($ch,CURLOPT_POST,true);
    if($_FILES){
        $fields=[];
        $flatten=static function(array $items,string $prefix='')use(&$flatten,&$fields):void{
            foreach($items as $key=>$value){$name=$prefix===''?(string)$key:$prefix.'['.$key.']';if(is_array($value))$flatten($value,$name);elseif(is_scalar($value))$fields[$name]=(string)$value;}
        };
        $flatten($post);
        foreach($_FILES as $key=>$file){
            if(!in_array($key,['image','logo'],true)||!is_array($file)||is_array($file['error']??null))ntc_fail(400);
            if(($file['error']??null)===UPLOAD_ERR_NO_FILE)continue;
            if(($file['error']??null)!==UPLOAD_ERR_OK||!is_uploaded_file($file['tmp_name'])||filesize($file['tmp_name'])>2000000)ntc_fail(413);
            $fields[$key]=new CURLFile($file['tmp_name'],'application/octet-stream','upload');
        }
        curl_setopt($ch,CURLOPT_POSTFIELDS,$fields);
    }else curl_setopt($ch,CURLOPT_POSTFIELDS,http_build_query($post,'','&',PHP_QUERY_RFC3986));
}
$ok=curl_exec($ch);$status=(int)curl_getinfo($ch,CURLINFO_RESPONSE_CODE);curl_close($ch);
if($ok===false||$status<200||$status>=500)ntc_fail(503);
if($status>=300&&$status<400){
    if($partner||!in_array($status,[302,303],true))ntc_fail(503);
    $location=$responseHeaders['location'][0]??'';
    if(!preg_match('~^(?:\./|/admin/|https://clubdisplay\.tennilo\.de/admin/)\?portal=club&verein=ntc93(?:&partner_review=1)?$~D',$location))ntc_fail(503);
}
if(!$partner){
    foreach($responseHeaders['set-cookie']??[] as $cookie){
        if(preg_match('/^CD_CLUB=([a-zA-Z0-9,-]{16,128})(?:;|$)/D',$cookie,$m)){
            setcookie('CD_CLUB',$m[1],['path'=>'/admin/','secure'=>true,'httponly'=>true,'samesite'=>'Strict']);
        }
    }
}
if($status>=300&&$status<400){header('Location: /admin/'.(str_contains($location,'partner_review=1')?'?partner_review=1':''),true,303);exit;}
http_response_code($status);
foreach(['content-type','content-security-policy','content-disposition'] as $name){
    if(isset($responseHeaders[$name][0])&&!preg_match('/[\r\n]/',$responseHeaders[$name][0]))header($name.': '.$responseHeaders[$name][0],true);
}
echo $body;
