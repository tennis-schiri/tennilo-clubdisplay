<?php
declare(strict_types=1);
const CD_MODULES=['welcome','weather','calendar','teams','news','partner','restaurant','courts'];
function cd_db(string $path): PDO {
    $db=new PDO('sqlite:'.$path,null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
    $db->exec('PRAGMA busy_timeout=5000');
    $db->exec('CREATE TABLE IF NOT EXISTS clubs (slug TEXT PRIMARY KEY, name TEXT NOT NULL, plan TEXT NOT NULL, color TEXT NOT NULL, language TEXT NOT NULL, dwell INTEGER NOT NULL, modules TEXT NOT NULL, updated TEXT NOT NULL); CREATE TABLE IF NOT EXISTS login_attempts (ip TEXT PRIMARY KEY, count INTEGER NOT NULL, since INTEGER NOT NULL)');
    $q=$db->prepare('INSERT OR IGNORE INTO clubs VALUES (?,?,?,?,?,?,?,?)');
    foreach([['tennilo-demo','TENNILO Demo','INTERACTIVE','#21d5db','de',12, json_encode(['welcome','weather','calendar','teams','news','partner','courts']),''],['ntc93','NTC 93','INTERACTIVE','#f7dc14','de',12,json_encode(CD_MODULES),'']] as $row){
        // Seed only once; profiles are drafts, not connected to the live displays.
        $q->execute($row);
    }
    return $db;
}
function cd_validate(array $in): array {
    $slug=trim((string)($in['slug']??'')); $name=trim((string)($in['name']??''));
    $plan=(string)($in['plan']??''); $color=(string)($in['color']??''); $language=(string)($in['language']??'');
    $dwell=filter_var($in['dwell']??null,FILTER_VALIDATE_INT);
    $modules=$in['modules']??[];
    if(!preg_match('/^[a-z0-9][a-z0-9-]{1,48}[a-z0-9]$/D',$slug)||$name===''||strlen($name)>160||!preg_match('//u',$name)||preg_match('/[\x00-\x1f\x7f]/',$name)||!in_array($plan,['INFO','INTERACTIVE'],true)||!preg_match('/^#[0-9a-f]{6}$/iD',$color)||!in_array($language,['de','en'],true)||$dwell===false||$dwell<5||$dwell>120||!is_array($modules)||array_diff($modules,CD_MODULES)||!in_array('welcome',$modules,true)||($plan==='INFO'&&in_array('courts',$modules,true)))throw new InvalidArgumentException('Bitte alle Angaben prüfen. INFO erlaubt keine Buchung; Willkommen muss aktiv sein.');
    return [$slug,$name,$plan,$color,$language,$dwell,json_encode(array_values(array_intersect(CD_MODULES,$modules)),JSON_THROW_ON_ERROR),gmdate('c')];
}
function cd_save(PDO $db,array $in,bool $create): void {
    $v=cd_validate($in);
    if($create){$q=$db->prepare('INSERT INTO clubs VALUES (?,?,?,?,?,?,?,?)');$q->execute($v);}
    else{$q=$db->prepare('UPDATE clubs SET name=?,plan=?,color=?,language=?,dwell=?,modules=?,updated=? WHERE slug=?');$slug=array_shift($v);$v[]=$slug;$q->execute($v);}
}
function cd_h(string $s):string{return htmlspecialchars($s,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');}
