<?php
declare(strict_types=1);
const CD_MODULES=['welcome','weather','calendar','teams','news','partner','restaurant','courts'];
function cd_db(string $path): PDO {
    $db=new PDO('sqlite:'.$path,null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
    $db->exec('PRAGMA busy_timeout=5000; PRAGMA foreign_keys=ON');
    $db->exec('CREATE TABLE IF NOT EXISTS clubs (slug TEXT PRIMARY KEY, name TEXT NOT NULL, plan TEXT NOT NULL, color TEXT NOT NULL, language TEXT NOT NULL, dwell INTEGER NOT NULL, modules TEXT NOT NULL, updated TEXT NOT NULL); CREATE TABLE IF NOT EXISTS login_attempts (ip TEXT PRIMARY KEY, count INTEGER NOT NULL, since INTEGER NOT NULL)');
    $q=$db->prepare('INSERT OR IGNORE INTO clubs VALUES (?,?,?,?,?,?,?,?)');
    foreach([['tennilo-demo','TENNILO Demo','INTERACTIVE','#21d5db','de',12, json_encode(['welcome','weather','calendar','teams','news','partner','courts']),''],['ntc93','NTC 93','INTERACTIVE','#f7dc14','de',12,json_encode(CD_MODULES),'']] as $row){
        // Seed only once; profiles are drafts, not connected to the live displays.
        $q->execute($row);
    }
    $db->exec('CREATE TABLE IF NOT EXISTS displays (club TEXT NOT NULL REFERENCES clubs(slug), slug TEXT NOT NULL, name TEXT NOT NULL, mode TEXT NOT NULL, language TEXT NOT NULL, dwell INTEGER NOT NULL, modules TEXT NOT NULL, enabled INTEGER NOT NULL, updated TEXT NOT NULL, PRIMARY KEY(club,slug)); CREATE TABLE IF NOT EXISTS club_accounts (club TEXT PRIMARY KEY REFERENCES clubs(slug), password_hash TEXT NOT NULL, enabled INTEGER NOT NULL DEFAULT 1)');
    $db->exec('CREATE TABLE IF NOT EXISTS club_brand (club TEXT PRIMARY KEY REFERENCES clubs(slug), logo BLOB, mime TEXT, welcome TEXT NOT NULL DEFAULT \'\'); CREATE TABLE IF NOT EXISTS club_content (id INTEGER PRIMARY KEY AUTOINCREMENT, club TEXT NOT NULL REFERENCES clubs(slug), kind TEXT NOT NULL, title TEXT NOT NULL, body TEXT NOT NULL, starts TEXT NOT NULL, ends TEXT NOT NULL, url TEXT NOT NULL, enabled INTEGER NOT NULL, updated TEXT NOT NULL)');
    return $db;
}
function cd_validate(array $in): array {
    $slug=trim((string)($in['slug']??'')); $name=trim((string)($in['name']??''));
    $plan=(string)($in['plan']??''); $color=(string)($in['color']??''); $language=(string)($in['language']??'');
    $dwell=filter_var($in['dwell']??null,FILTER_VALIDATE_INT);
    $modules=$in['modules']??[]; if(is_array($modules)&&array_filter($modules,fn($v)=>!is_string($v)))throw new InvalidArgumentException('Ungültige Module.');
    if(!preg_match('/^[a-z0-9][a-z0-9-]{1,48}[a-z0-9]$/D',$slug)||$name===''||strlen($name)>160||!preg_match('//u',$name)||preg_match('/[\x00-\x1f\x7f]/',$name)||!in_array($plan,['INFO','INTERACTIVE'],true)||!preg_match('/^#[0-9a-f]{6}$/iD',$color)||!in_array($language,['de','en'],true)||$dwell===false||$dwell<5||$dwell>120||!is_array($modules)||array_diff($modules,CD_MODULES)||!in_array('welcome',$modules,true)||($plan==='INFO'&&in_array('courts',$modules,true)))throw new InvalidArgumentException('Bitte alle Angaben prüfen. INFO erlaubt keine Buchung; Willkommen muss aktiv sein.');
    return [$slug,$name,$plan,$color,$language,$dwell,json_encode(array_values(array_intersect(CD_MODULES,$modules)),JSON_THROW_ON_ERROR),gmdate('c')];
}
function cd_save(PDO $db,array $in,bool $create): void {
    $v=cd_validate($in);
    if($create){$q=$db->prepare('INSERT INTO clubs VALUES (?,?,?,?,?,?,?,?)');$q->execute($v);}
    else{foreach(cd_displays($db,$v[0]) as $d){if(($v[2]==='INFO'&&$d['mode']==='INTERACTIVE')||array_diff(json_decode($d['modules'],true)?:[],json_decode($v[6],true)?:[]))throw new InvalidArgumentException('Zuerst betroffene Bildschirmprofile anpassen.');}$q=$db->prepare('UPDATE clubs SET name=?,plan=?,color=?,language=?,dwell=?,modules=?,updated=? WHERE slug=?');$slug=array_shift($v);$v[]=$slug;$q->execute($v);}
}
function cd_h(string $s):string{return htmlspecialchars($s,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');}

function cd_club(PDO $db,string $slug):array {
    $q=$db->prepare('SELECT * FROM clubs WHERE slug=?');$q->execute([$slug]);$c=$q->fetch();
    if(!$c)throw new InvalidArgumentException('Verein nicht gefunden.');
    return $c;
}
function cd_scope(?string $scope,string $club):void {
    if($scope!==null&&$scope!==$club)throw new InvalidArgumentException('Keine Berechtigung für diesen Verein.');
}
function cd_displays(PDO $db,string $club):array {
    $q=$db->prepare('SELECT * FROM displays WHERE club=? ORDER BY name');$q->execute([$club]);return $q->fetchAll();
}
function cd_save_display(PDO $db,array $in,bool $create,?string $scope):void {
    $club=(string)($in['club']??'');cd_scope($scope,$club);$c=cd_club($db,$club);
    $mode=(string)($in['mode']??'');
    if($c['plan']==='INFO'&&$mode!=='INFO')throw new InvalidArgumentException('INTERACTIVE ist für diesen Verein nicht freigeschaltet.');
    $v=cd_validate(array_replace($in,['plan'=>$mode,'color'=>$c['color']]));
    $allowed=json_decode($c['modules'],true)?:[];
    if(array_diff(json_decode($v[6],true),$allowed))throw new InvalidArgumentException('Modul für diesen Verein nicht freigeschaltet.');
    $row=[$club,$v[0],$v[1],$mode,$v[4],$v[5],$v[6],isset($in['enabled'])?1:0,$v[7]];
    if($create){$q=$db->prepare('INSERT INTO displays VALUES (?,?,?,?,?,?,?,?,?)');$q->execute($row);}
    else{$q=$db->prepare('UPDATE displays SET name=?,mode=?,language=?,dwell=?,modules=?,enabled=?,updated=? WHERE club=? AND slug=?');$q->execute(array_merge(array_slice($row,2),array_slice($row,0,2)));}
}
function cd_account(PDO $db,string $club,string $password,bool $enabled):void {
    cd_club($db,$club);
    if($password!==''){
        if(strlen($password)<16||strlen($password)>72||str_contains($password,"\0"))throw new InvalidArgumentException('Passwort: 16–72 Bytes.');
        $hash=password_hash($password,PASSWORD_BCRYPT);
        $q=$db->prepare('INSERT INTO club_accounts VALUES (?,?,?) ON CONFLICT(club) DO UPDATE SET password_hash=excluded.password_hash, enabled=excluded.enabled');$q->execute([$club,$hash,(int)$enabled]);
    }else{
        $q=$db->prepare('UPDATE club_accounts SET enabled=? WHERE club=?');$q->execute([(int)$enabled,$club]);
    }
}
function cd_account_row(PDO $db,string $club):?array{
    $q=$db->prepare('SELECT * FROM club_accounts WHERE club=?');$q->execute([$club]);return $q->fetch()?:null;
}
function cd_login_hash(PDO $db,string $club,string $adminHash):string{
    if($club==='')return $adminHash;
    $a=cd_account_row($db,$club);return $a&&$a['enabled']?$a['password_hash']:'';
}

const CD_CONTENT=['news'=>'Vereinsnachrichten','calendar'=>'Termine','partner'=>'Sponsoren','restaurant'=>'Gastronomie'];
function cd_text(mixed $v,int $max,bool $required=false):string{
    if(!is_string($v))throw new InvalidArgumentException('Ungültiger Text.');
    $v=trim($v);
    if(($required&&$v==='')||strlen($v)>$max||!preg_match('//u',$v)||preg_match('/[\x00-\x08\x0b\x0c\x0e-\x1f\x7f]/',$v))throw new InvalidArgumentException('Text fehlt oder ist zu lang.');
    return $v;
}
function cd_brand(PDO $db,string $club):array{
    $q=$db->prepare('SELECT welcome,mime,length(logo) AS logo_size FROM club_brand WHERE club=?');$q->execute([$club]);return $q->fetch()?:['welcome'=>'','mime'=>null,'logo_size'=>0];
}
function cd_save_brand(PDO $db,array $in,array $file,?string $scope):void{
    $club=cd_text($in['club']??'',50,true);cd_scope($scope,$club);cd_club($db,$club);
    $welcome=cd_text($in['welcome']??'',1000);
    $color=cd_text($in['color']??'',7,true);
    if(!preg_match('/^#[0-9a-f]{6}$/iD',$color))throw new InvalidArgumentException('Ungültige Akzentfarbe.');
    $logo=null;$mime=null;$replace=isset($in['remove_logo']);
    if(($file['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_NO_FILE){
        if(($file['error']??null)!==UPLOAD_ERR_OK||!is_uploaded_file($file['tmp_name'])||filesize($file['tmp_name'])>2000000)throw new InvalidArgumentException('Logo: maximal 2 MB.');
        $info=@getimagesize($file['tmp_name']);
        if(!$info||!in_array($info['mime'],['image/png','image/jpeg','image/webp'],true)||$info[0]>3000||$info[1]>3000)throw new InvalidArgumentException('Nur PNG, JPEG oder WebP bis 3000 × 3000 Pixel.');
        // Re-encode, so uploaded metadata and trailing bytes are never served.
        if(!function_exists('imagecreatefromstring'))throw new InvalidArgumentException('PHP GD fehlt beim Hosting.');
        $im=@imagecreatefromstring(file_get_contents($file['tmp_name']));
        if(!$im)throw new InvalidArgumentException('Ungültige Bilddatei.');
        imagesavealpha($im,true);ob_start();imagepng($im);$logo=ob_get_clean();imagedestroy($im);
        if(!is_string($logo)||strlen($logo)>6000000)throw new InvalidArgumentException('Logo nach Verarbeitung zu groß.');
        $mime='image/png';$replace=true;
    }
    $db->beginTransaction();
    try{
        $q=$db->prepare('INSERT INTO club_brand(club,welcome) VALUES (?,?) ON CONFLICT(club) DO UPDATE SET welcome=excluded.welcome');$q->execute([$club,$welcome]);
        if($replace){$q=$db->prepare('UPDATE club_brand SET logo=?,mime=? WHERE club=?');$q->bindValue(1,$logo,$logo===null?PDO::PARAM_NULL:PDO::PARAM_LOB);$q->bindValue(2,$mime);$q->bindValue(3,$club);$q->execute();}
        $q=$db->prepare('UPDATE clubs SET color=?,updated=? WHERE slug=?');$q->execute([$color,gmdate('c'),$club]);
        $db->commit();
    }catch(Throwable $e){$db->rollBack();throw $e;}
}
function cd_content_rows(PDO $db,string $club,string $kind):array{
    $q=$db->prepare('SELECT * FROM club_content WHERE club=? AND kind=? ORDER BY starts,title,id');$q->execute([$club,$kind]);return $q->fetchAll();
}
function cd_date(mixed $v):string{
    $v=cd_text($v,10);if($v==='')return '';
    $d=DateTimeImmutable::createFromFormat('!Y-m-d',$v);
    if(!$d||$d->format('Y-m-d')!==$v)throw new InvalidArgumentException('Ungültiges Datum.');
    return $v;
}
function cd_save_content(PDO $db,array $in,?string $scope):void{
    $club=cd_text($in['club']??'',50,true);cd_scope($scope,$club);$c=cd_club($db,$club);
    $kind=cd_text($in['kind']??'',20,true);
    if(!isset(CD_CONTENT[$kind])||!in_array($kind,json_decode($c['modules'],true)?:[],true))throw new InvalidArgumentException('Inhaltsmodul nicht freigeschaltet.');
    $id=filter_var($in['id']??0,FILTER_VALIDATE_INT);if($id===false||$id<0)throw new InvalidArgumentException('Ungültige Kennung.');
    if(($in['intent']??'')==='delete'){
        if(!$id)throw new InvalidArgumentException('Ungültige Kennung.');
        $q=$db->prepare('DELETE FROM club_content WHERE id=? AND club=? AND kind=?');$q->execute([$id,$club,$kind]);return;
    }
    $title=cd_text($in['title']??'',200,true);$body=cd_text($in['body']??'',4000);
    $starts=cd_date($in['starts']??'');$ends=cd_date($in['ends']??'');
    if($starts!==''&&$ends!==''&&$ends<$starts)throw new InvalidArgumentException('Enddatum liegt vor dem Startdatum.');
    $url=cd_text($in['url']??'',500);
    if($url!==''&&(!filter_var($url,FILTER_VALIDATE_URL)||strtolower((string)parse_url($url,PHP_URL_SCHEME))!=='https'||parse_url($url,PHP_URL_USER)!==null))throw new InvalidArgumentException('Nur gültige HTTPS-Links.');
    $v=[$title,$body,$starts,$ends,$url,isset($in['enabled'])?1:0,gmdate('c')];
    if($id){$q=$db->prepare('UPDATE club_content SET title=?,body=?,starts=?,ends=?,url=?,enabled=?,updated=? WHERE id=? AND club=? AND kind=?');$q->execute([...$v,$id,$club,$kind]);}
    else{$q=$db->prepare('INSERT INTO club_content(club,kind,title,body,starts,ends,url,enabled,updated) VALUES (?,?,?,?,?,?,?,?,?)');$q->execute([$club,$kind,...$v]);}
}
