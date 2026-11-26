<?php
declare(strict_types=1);
function cd_matchball_schema(PDO $db):void{
    $db->exec("CREATE TABLE IF NOT EXISTS matchball_issues (club TEXT NOT NULL REFERENCES clubs(slug) ON DELETE CASCADE, month TEXT NOT NULL, headline TEXT NOT NULL DEFAULT '', teaser TEXT NOT NULL DEFAULT '', pages INTEGER NOT NULL DEFAULT 0, articles TEXT NOT NULL DEFAULT '[]', published INTEGER NOT NULL DEFAULT 0, pdf BLOB, updated INTEGER NOT NULL, PRIMARY KEY(club,month)); CREATE TABLE IF NOT EXISTS matchball_seed (club TEXT PRIMARY KEY)");
}
function cd_matchball_seed(PDO $db,string $club):void{
    cd_matchball_schema($db);if($club!=='ntc93')return;
    $q=$db->prepare('SELECT 1 FROM matchball_seed WHERE club=?');$q->execute([$club]);if($q->fetchColumn())return;
    $db->exec('BEGIN IMMEDIATE');
    try{
        $q=$db->prepare('INSERT OR IGNORE INTO matchball_seed VALUES (?)');$q->execute([$club]);
        if($q->rowCount()){
            $seed=json_decode('[{"issue":"10/26","month":"2026-10","pages":10,"headline":"Die Wintersaison hat begonnen","teaser":"25 Teams in der Winterrunde · Jugendclubmeisterschaften · 33 Jahre NTC","articles":[["Winterrunde und Tennishalle","Die Hallensaison ist gestartet. Der NTC ist mit 25 Mannschaften in der Winterrunde 2026/27 vertreten. Die ersten Verbandsspiele sind absolviert. Der Verein bittet alle Spielerinnen und Spieler, die Plätze nach dem Spielen sorgfältig abzuziehen.","Seiten 1–2, 6"],["Vereinsleben","Der NTC präsentierte sich beim Oktoberfest und bei den Feierlichkeiten zu 100 Jahren Rathaus Neuenhagen. Ein Interview mit dem 1. Vorsitzenden blickt auf die ersten sechs Monate im Amt und die Entwicklung des Vereins zurück.","Seiten 1, 3"],["Jugendclubmeisterschaften","51 Kinder und Jugendliche nahmen an den Jugendclubmeisterschaften teil – ein neuer Teilnehmerrekord. Die Siegerehrung bildete den Abschluss des Turniers.","Seite 4"],["Eine Ära geht zu Ende","Nach 19 Jahren übergibt der Mannschaftsführer der 1. Herren seine Verantwortung. Dem Verein und dem Mannschaftstennis bleibt er erhalten.","Seite 7"],["33 Jahre NTC","Die Ausgabe kündigt den Geburtstagsbrunch am 6. Dezember 2026 von 11 bis 15 Uhr im Clubhaus an. Aktuelle Informationen stehen im Bereich Termine.","Seite 8"]]},{"issue":"09/26","month":"2026-09","pages":12,"headline":"Der Sommer neigt sich dem Ende","teaser":"Saisonrückblick · Sommercamps · Hallenvorbereitung","articles":[["Rückblick auf die Sommersaison","Die Herren 30, 50 und 60 feierten den Aufstieg. Die Damen 40 und Herren 75 vertraten den NTC in der Meisterklasse. Die Neuenhagen Open, Senior Open und Junior Open waren sportliche Höhepunkte.","Seiten 4–6"],["Unsere Halle","Bei einem gemeinsamen Arbeitseinsatz am 19. September wurde die Halle für die Wintersaison vorbereitet. Viele Helferinnen und Helfer reinigten und pflegten die Anlage.","Seite 2"],["Sommercamps","Die Jugendcamps auf der Anlage und in Usedom kombinierten Tennistraining, Matchpraxis und gemeinsame Freizeit. Auch das Erwachsenencamp mit wechselnden Trainern war ein Erfolg.","Seiten 7–9"],["Vereinsleben","Der NTC war beim Oktoberfest in Neuenhagen vertreten. Die September-Ausgabe blickte außerdem auf das Rathausjubiläum und den Übergang in die Wintersaison voraus.","Seiten 1, 3"]]}]',true,512,JSON_THROW_ON_ERROR);
            $q=$db->prepare('INSERT OR IGNORE INTO matchball_issues(club,month,headline,teaser,pages,articles,published,updated) VALUES (?,?,?,?,?,?,1,?)');
            foreach($seed as $r)$q->execute([$club,$r['month'],$r['headline'],$r['teaser'],$r['pages'],json_encode($r['articles'],JSON_THROW_ON_ERROR),time()]);
        }
        $db->exec('COMMIT');
    }catch(Throwable $e){$db->exec('ROLLBACK');throw $e;}
}
function cd_matchball_month(mixed $v):string{
    if(!is_string($v)||!preg_match('/^20[0-9]{2}-(0[1-9]|1[0-2])$/D',$v))throw new InvalidArgumentException('Bitte einen gültigen Ausgabemonat wählen.');
    return $v;
}
function cd_matchball_rows(PDO $db,string $club,?string $scope,bool $public=false):array{
    cd_scope($scope,$club);cd_club($db,$club);cd_matchball_seed($db,$club);
    $q=$db->prepare('SELECT month,headline,teaser,pages,articles,published,updated,pdf IS NOT NULL AS has_pdf FROM matchball_issues WHERE club=?'.($public?' AND published=1':'').' ORDER BY month DESC LIMIT 60');$q->execute([$club]);$rows=$q->fetchAll();
    foreach($rows as &$r)$r['articles']=json_decode($r['articles'],true,512,JSON_THROW_ON_ERROR);unset($r);return $rows;
}
function cd_matchball_save(PDO $db,array $in,array $file,?string $scope):void{
    $club=cd_text($in['club']??'',50,true);cd_scope($scope,$club);if($club!=='ntc93')throw new InvalidArgumentException('MATCHBALL ist nur für den NTC verfügbar.');cd_club($db,$club);cd_matchball_seed($db,$club);
    $month=cd_matchball_month($in['month']??null);$intent=$in['intent']??'';
    $q=$db->prepare('SELECT headline,teaser,articles,published FROM matchball_issues WHERE club=? AND month=?');$q->execute([$club,$month]);$old=$q->fetch();
    if($intent==='publish'){
        if(!$old||trim($old['headline'])===''||trim($old['teaser'])===''||!json_decode($old['articles'],true)||($in['public_reviewed']??'')!=='1')throw new InvalidArgumentException('Lesefassung vollständig ausfüllen und die öffentliche Freigabe bestätigen.');
        $q=$db->prepare('UPDATE matchball_issues SET published=1,updated=? WHERE club=? AND month=?');$q->execute([time(),$club,$month]);return;
    }
    if($intent==='unpublish'||$intent==='delete'){
        if(!$old)throw new InvalidArgumentException('Ausgabe nicht gefunden.');
        if($intent==='delete'){
            if(($in['delete_confirm']??'')!=='1')throw new InvalidArgumentException('Endgültiges Löschen bestätigen.');
            $q=$db->prepare('DELETE FROM matchball_issues WHERE club=? AND month=?');$q->execute([$club,$month]);
        }else{$q=$db->prepare('UPDATE matchball_issues SET published=0,updated=? WHERE club=? AND month=?');$q->execute([time(),$club,$month]);}return;
    }
    if($intent!=='save')throw new InvalidArgumentException('Unbekannte Newsletter-Aktion.');
    if(!$old&&($in['existing']??'')==='1')throw new InvalidArgumentException('Ausgabe nicht gefunden.');
    if($old&&($in['existing']??'')!=='1')throw new InvalidArgumentException('Dieser Ausgabemonat ist bereits vorhanden. Bitte die vorhandene Ausgabe bearbeiten.');
    $headline=cd_text($in['headline']??'',180,false);$teaser=cd_text($in['teaser']??'',320,false);
    $pages=filter_var($in['pages']??0,FILTER_VALIDATE_INT,['options'=>['min_range'=>0,'max_range'=>200]]);if($pages===false)throw new InvalidArgumentException('Seitenzahl 0 bis 200.');
    $articles=[];$titles=$in['story_title']??[];$bodies=$in['story_body']??[];$refs=$in['story_ref']??[];
    if(!is_array($titles)||!is_array($bodies)||!is_array($refs)||count($titles)>8||count($bodies)>8||count($refs)>8)throw new InvalidArgumentException('Maximal acht Themen.');
    for($i=0;$i<8;$i++){
        $title=cd_text($titles[$i]??'',180,false);$body=cd_text($bodies[$i]??'',1800,false);$ref=cd_text($refs[$i]??'',100,false);
        if($title===''&&$body===''&&$ref==='')continue;
        if($title===''||$body==='')throw new InvalidArgumentException('Jedes Thema braucht Überschrift und Text.');
        $articles[]=[$title,$body,$ref];
    }
    $pdf=null;$error=$file['error']??UPLOAD_ERR_NO_FILE;
    if($error!==UPLOAD_ERR_NO_FILE){
        if($error!==UPLOAD_ERR_OK||!is_string($file['tmp_name']??null)||!is_uploaded_file($file['tmp_name']))throw new InvalidArgumentException('PDF-Upload fehlgeschlagen. Hosting-Uploadlimit prüfen.');
        $size=filesize($file['tmp_name']);if(!$size||$size>10000000)throw new InvalidArgumentException('PDF darf maximal 10 MB groß sein.');
        $pdf=file_get_contents($file['tmp_name']);if(!is_string($pdf)||!str_starts_with($pdf,'%PDF-')||!str_contains(substr($pdf,-4096),'%%EOF'))throw new InvalidArgumentException('Keine gültige PDF-Datei.');
    }
    $db->exec('BEGIN IMMEDIATE');
    try{
        $q=$db->prepare('SELECT COUNT(*) FROM matchball_issues WHERE club=?');$q->execute([$club]);if(!$old&&(int)$q->fetchColumn()>=60)throw new InvalidArgumentException('Archivlimit von 60 Ausgaben erreicht.');
        $q=$db->prepare('SELECT COALESCE(SUM(length(pdf)),0) FROM matchball_issues WHERE club=? AND month<>?');$q->execute([$club,$month]);if($pdf!==null&&(int)$q->fetchColumn()+strlen($pdf)>150000000)throw new InvalidArgumentException('PDF-Archivlimit von 150 MB erreicht.');
        $q=$db->prepare("INSERT INTO matchball_issues(club,month,headline,teaser,pages,articles,published,pdf,updated) VALUES (?,?,?,?,?,?,0,?,?) ON CONFLICT(club,month) DO UPDATE SET headline=excluded.headline,teaser=excluded.teaser,pages=excluded.pages,articles=excluded.articles,published=0,pdf=COALESCE(excluded.pdf,matchball_issues.pdf),updated=excluded.updated");
        $q->bindValue(1,$club);$q->bindValue(2,$month);$q->bindValue(3,$headline);$q->bindValue(4,$teaser);$q->bindValue(5,$pages,PDO::PARAM_INT);$q->bindValue(6,json_encode($articles,JSON_THROW_ON_ERROR));$q->bindValue(7,$pdf,$pdf===null?PDO::PARAM_NULL:PDO::PARAM_LOB);$q->bindValue(8,time(),PDO::PARAM_INT);$q->execute();$db->exec('COMMIT');
    }catch(Throwable $e){$db->exec('ROLLBACK');throw $e;}
}
function cd_matchball_feed(PDO $db):never{
    header('Content-Type: application/json; charset=utf-8');header('Vary: Origin');
    if(($_SERVER['REQUEST_METHOD']??'')!=='GET'||($_GET['club']??'')!=='ntc93'){http_response_code(404);exit;}
    if(($_SERVER['HTTP_ORIGIN']??'')==='https://clubdisplay.ntc93.de')header('Access-Control-Allow-Origin: https://clubdisplay.ntc93.de');
    try{
        $rows=cd_matchball_rows($db,'ntc93','ntc93',true);$out=[];
        $months=['Januar','Februar','März','April','Mai','Juni','Juli','August','September','Oktober','November','Dezember'];
        foreach($rows as $r){$month=$r['month'];$out[]=['id'=>$month,'issue'=>substr($month,5,2).'/'.substr($month,2,2),'month'=>$months[(int)substr($month,5)-1].' '.substr($month,0,4),'pages'=>(int)$r['pages'],'headline'=>$r['headline'],'teaser'=>$r['teaser'],'articles'=>$r['articles']];}
        echo json_encode(['issues'=>$out],JSON_THROW_ON_ERROR|JSON_INVALID_UTF8_SUBSTITUTE);
    }catch(Throwable $e){http_response_code(503);echo '{"error":"unavailable"}';}exit;
}
function cd_matchball_download(PDO $db,string $club,string $month,?string $scope):never{
    cd_scope($scope,$club);cd_club($db,$club);cd_matchball_schema($db);$month=cd_matchball_month($month);
    $q=$db->prepare('SELECT pdf FROM matchball_issues WHERE club=? AND month=?');$q->execute([$club,$month]);$pdf=$q->fetchColumn();if(!is_string($pdf)||$pdf===''){http_response_code(404);exit;}
    header('Content-Type: application/octet-stream');header('Content-Disposition: attachment; filename="MATCHBALL-'.$month.'.pdf"');echo $pdf;exit;
}
function cd_matchball_editor(string $club,string $csrf,array $r=[]):void{
    $existing=isset($r['month']);$month=$r['month']??date('Y-m');$stories=$r['articles']??[];
    ?><form method="post" enctype="multipart/form-data" class="profile matchball-editor">
    <input type="hidden" name="csrf" value="<?=cd_h($csrf)?>"><input type="hidden" name="action" value="matchball"><input type="hidden" name="club" value="<?=cd_h($club)?>"><input type="hidden" name="existing" value="<?=$existing?'1':'0'?>">
    <label>Ausgabemonat<input type="month" name="month" required value="<?=cd_h($month)?>" <?=$existing?'readonly':''?>></label>
    <label>Seiten im Original (0 = unbekannt)<input type="number" name="pages" min="0" max="200" value="<?=(int)($r['pages']??0)?>"></label>
    <label class="full">Original-PDF (max. 10 MB, nur intern)<input type="file" name="newsletter_pdf" accept="application/pdf,.pdf"></label>
    <label class="full">Titel der Lesefassung<input name="headline" maxlength="180" value="<?=cd_h($r['headline']??'')?>"></label>
    <label class="full">Kurzbeschreibung für die Kachel<textarea name="teaser" maxlength="320" rows="2"><?=cd_h($r['teaser']??'')?></textarea></label>
    <div class="full"><h4>Lesefassung · bis zu acht Themen</h4><p>Texte manuell aus dem Newsletter übernehmen oder zusammenfassen. Keine nicht freigegebenen personenbezogenen oder internen Angaben eintragen.</p></div>
    <?php for($i=0;$i<8;$i++):$s=$stories[$i]??['','',''];?><details class="entry full" <?=($i===0||$i<count($stories))?'open':''?>><summary>Thema <?=$i+1?><?=isset($stories[$i])?' · '.cd_h($s[0]):''?></summary><label>Überschrift<input name="story_title[]" maxlength="180" value="<?=cd_h($s[0])?>"></label><label>Text<textarea name="story_body[]" rows="4" maxlength="1800"><?=cd_h($s[1])?></textarea></label><label>Quellseite (optional)<input name="story_ref[]" maxlength="100" value="<?=cd_h($s[2])?>"></label></details><?php endfor?>
    <p class="full">Speichern erstellt einen Entwurf bzw. nimmt eine bearbeitete Ausgabe bis zur erneuten Freigabe vom Display.</p><button name="intent" value="save">Entwurf speichern</button></form><?php
}
function cd_matchball_field(PDO $db,array $club,string $csrf,?string $scope):void{
    global $clubPortal,$fixedClub;
    $slug=$club['slug'];$rows=cd_matchball_rows($db,$slug,$scope);$published=array_values(array_filter($rows,static fn($r)=>(int)$r['published']===1));
    ?><div class="matchball-admin"><h2>MATCHBALL – Newsletter</h2><p>Originale intern archivieren, Lesefassung prüfen und separat freigeben. Die neueste freigegebene Ausgabe erscheint beim NTC unter Neuigkeiten; frühere freigegebene Ausgaben im Archiv. PDF-Inhalte werden noch nicht automatisch extrahiert.</p>
    <div class="partner-admin-metrics"><div><strong><?=count($rows)?></strong><small>Ausgaben</small></div><div><strong><?=count($published)?></strong><small>Freigegeben</small></div><div><strong><?=cd_h($published[0]['month']??'–')?></strong><small>Aktuelle Ausgabe</small></div></div>
    <details class="entry"><summary>+ Neue Ausgabe anlegen / PDF hochladen</summary><?php cd_matchball_editor($slug,$csrf)?></details>
    <h3>Ausgabenarchiv</h3>
    <?php foreach($rows as $r):?><details class="entry"><summary><?=cd_h($r['month'].' · '.$r['headline'])?> <small><?=$r['published']?'Freigegeben':'Entwurf'?> · <?=$r['has_pdf']?'PDF gespeichert':'Original fehlt'?></small></summary>
    <?php if($r['has_pdf']):?><p><a class="admin-action" href="?<?=$clubPortal?'portal=club&amp;'.($fixedClub?'verein=ntc93&amp;':''):''?>matchball_pdf=<?=rawurlencode($r['month'])?>&amp;club=<?=rawurlencode($slug)?>">Original-PDF herunterladen (geschützt)</a></p><?php endif?>
    <section class="matchball-admin-preview"><small>Vorschau der öffentlichen Lesefassung</small><h3><?=cd_h($r['headline'])?></h3><p><?=cd_h($r['teaser'])?></p><?php foreach($r['articles'] as $story):?><h4><?=cd_h($story[0])?></h4><p><?=nl2br(cd_h($story[1]))?></p><small><?=cd_h($story[2])?></small><?php endforeach?></section>
    <details class="entry"><summary>Ausgabe bearbeiten / Original hinterlegen</summary><?php cd_matchball_editor($slug,$csrf,$r)?></details>
    <form method="post" class="profile"><input type="hidden" name="csrf" value="<?=cd_h($csrf)?>"><input type="hidden" name="action" value="matchball"><input type="hidden" name="club" value="<?=cd_h($slug)?>"><input type="hidden" name="month" value="<?=cd_h($r['month'])?>">
    <?php if(!$r['published']):?><label class="check full"><input type="checkbox" name="public_reviewed" value="1" required> Lesefassung für öffentliche Anzeige geprüft und freigegeben. Keine vertraulichen Informationen.</label><button name="intent" value="publish">Veröffentlichen</button><?php else:?><button name="intent" value="unpublish">Vom Display nehmen</button><?php endif?></form>
    <form method="post" class="profile"><input type="hidden" name="csrf" value="<?=cd_h($csrf)?>"><input type="hidden" name="action" value="matchball"><input type="hidden" name="club" value="<?=cd_h($slug)?>"><input type="hidden" name="month" value="<?=cd_h($r['month'])?>"><label class="check full"><input type="checkbox" name="delete_confirm" value="1" required> Ausgabe samt Original endgültig löschen</label><button class="danger" name="intent" value="delete">Löschen</button></form></details><?php endforeach?>
    <?php if(!$rows):?><p>Noch keine Ausgaben.</p><?php endif?></div><?php
}
