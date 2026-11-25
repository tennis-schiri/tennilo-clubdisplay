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
