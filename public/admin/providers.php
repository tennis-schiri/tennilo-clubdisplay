<?php
declare(strict_types=1);
// Public-source adapters. No credentials, cookies, redirects or private-network requests.
const CD_PROVIDER_OPTIONS=[
 'teams'=>['nuliga'=>'nuLiga · öffentlicher Spielplan'],
 'news'=>['wordpress'=>'WordPress · öffentliche REST-API'],
 'calendar'=>['wp-events'=>'WordPress · The Events Calendar','ical'=>'Öffentlicher iCalendar (ICS)'],
 'weather'=>['brightsky'=>'Bright Sky · DWD-Wetter'],
 'courts'=>['ebusy-link'=>'eBuSy · freigegebener externer Terminallink']
];
function cd_provider_url(string $url):array{
    $p=parse_url($url);
    if(!filter_var($url,FILTER_VALIDATE_URL)||!is_array($p)||strtolower($p['scheme']??'')!=='https'||isset($p['user'])||isset($p['pass'])||isset($p['port'])||isset($p['fragment']))throw new InvalidArgumentException('Nur öffentliche HTTPS-Adressen ohne Zugangsdaten oder Sonderport.');
    $host=strtolower($p['host']??'');
    if(strlen($host)>253||!preg_match('/^(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}$/D',$host)||filter_var($host,FILTER_VALIDATE_IP)||preg_match('/\.(?:local|localhost|internal|test|invalid|example|onion)$/D',$host))throw new InvalidArgumentException('Öffentlicher DNS-Hostname erforderlich.');
    return $p+['path'=>'/','host'=>$host];
}
function cd_provider_reference(string $provider,string $ref):string{
    if($provider==='brightsky'){
        if(!preg_match('/^(-?\d{1,2}(?:\.\d{1,6})?)\s*,\s*(-?\d{1,3}(?:\.\d{1,6})?)$/D',$ref,$m)||(float)$m[1]<-90||(float)$m[1]>90||(float)$m[2]<-180||(float)$m[2]>180)throw new InvalidArgumentException('Wetter: Breitengrad,Längengrad, z. B. 52.5333,13.6833.');
        return $m[1].','.$m[2];
    }
    if(!in_array($provider,['nuliga','wordpress','wp-events','ical','ebusy-link'],true))return $ref;
    $p=cd_provider_url($ref);$host=strtolower($p['host']);
    if($provider==='nuliga'){
        if(!str_ends_with($host,'.liga.nu')||!preg_match('~^/cgi-bin/WebObjects/nuLigaTENDE\.woa/wa/clubMeetings$~D',$p['path'])||!preg_match('/^club=\d{1,12}$/D',$p['query']??''))throw new InvalidArgumentException('nuLiga: öffentliche clubMeetings-Adresse mit club=Vereinsnummer erforderlich.');
    }else{
        if(isset($p['query']))throw new InvalidArgumentException('Keine URL-Parameter oder privaten Zugangsschlüssel eintragen.');
        if($provider==='wordpress'&&!str_ends_with(rtrim($p['path'],'/'),'/wp-json/wp/v2/posts'))throw new InvalidArgumentException('WordPress: URL des öffentlichen Posts-Endpunkts /wp-json/wp/v2/posts.');
        if($provider==='wp-events'&&!str_ends_with(rtrim($p['path'],'/'),'/wp-json/tribe/events/v1/events'))throw new InvalidArgumentException('Kalender: URL des öffentlichen The-Events-Calendar-Endpunkts.');
        if($provider==='ebusy-link'&&(!str_ends_with($host,'.ebusy.de')||!preg_match('~^/lite-module/\d+/?$~D',$p['path'])))throw new InvalidArgumentException('Nur eBuSy-Lite-Module ohne geheime Parameter.');
    }
    return $ref;
}
function cd_public_ip(string $ip):bool{
    if(defined('FILTER_FLAG_GLOBAL_RANGE'))return filter_var($ip,FILTER_VALIDATE_IP,FILTER_FLAG_IPV4|FILTER_FLAG_GLOBAL_RANGE)!==false;
    if(!filter_var($ip,FILTER_VALIDATE_IP,FILTER_FLAG_IPV4|FILTER_FLAG_NO_PRIV_RANGE|FILTER_FLAG_NO_RES_RANGE))return false;
    $n=ip2long($ip);foreach(['100.64.0.0/10','192.0.0.0/24','192.0.2.0/24','198.18.0.0/15','198.51.100.0/24','203.0.113.0/24','224.0.0.0/4','240.0.0.0/4'] as $range){[$net,$bits]=explode('/',$range);$mask=-1<<(32-(int)$bits);if(($n&$mask)===(ip2long($net)&$mask))return false;}return true;
}
function cd_public_http(string $url,int $max=1500000):string{
    $p=cd_provider_url($url);$host=strtolower($p['host']);$ips=@gethostbynamel($host);
    if(!$ips)throw new RuntimeException('DNS unavailable');
    foreach($ips as $ip)if(!cd_public_ip($ip))throw new RuntimeException('Non-public DNS rejected');
    $ch=curl_init($url);$body='';
    curl_setopt_array($ch,[CURLOPT_RESOLVE=>[$host.':443:'.$ips[0]],CURLOPT_IPRESOLVE=>CURL_IPRESOLVE_V4,CURLOPT_PROXY=>'',CURLOPT_CONNECTTIMEOUT=>3,CURLOPT_TIMEOUT=>5,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_PROTOCOLS=>CURLPROTO_HTTPS,CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2,CURLOPT_USERAGENT=>'TENNILO ClubDisplay/0.6 public club feed',CURLOPT_WRITEFUNCTION=>static function($ch,string $chunk)use(&$body,$max){if(strlen($body)+strlen($chunk)>$max)return 0;$body.=$chunk;return strlen($chunk);}]);
    $ok=curl_exec($ch);$code=curl_getinfo($ch,CURLINFO_RESPONSE_CODE);curl_close($ch);
    if(!$ok||$code!==200||$body==='')throw new RuntimeException('Public source unavailable');
    return $body;
}
function cd_plain(mixed $value,int $max=1000):string{
    if(!is_scalar($value))return '';
    $s=preg_replace('~<(script|style)\b[^>]*>.*?</\1>~is',' ',(string)$value)??'';
    $s=trim(preg_replace('/\s+/u',' ',html_entity_decode(strip_tags($s),ENT_QUOTES|ENT_HTML5,'UTF-8'))??'');
    return function_exists('mb_substr')?mb_substr($s,0,$max):substr($s,0,$max);
}
function cd_feed_json(string $provider,string $raw):array{
    $j=json_decode($raw,true,512,JSON_THROW_ON_ERROR);if(!is_array($j))throw new RuntimeException('Invalid feed.');
    $rows=[];
    if($provider==='wordpress'){
        if(!array_is_list($j))throw new RuntimeException('Invalid WordPress feed.');
        foreach(array_slice($j,0,12) as $r){$title=cd_plain($r['title']['rendered']??'',200);if($title!=='')$rows[]=['title'=>$title,'excerpt'=>cd_plain($r['excerpt']['rendered']??'',600),'date'=>cd_plain($r['date']??'',32)];}
    }elseif($provider==='wp-events'){
        if(!is_array($j['events']??null))throw new RuntimeException('Invalid calendar feed.');
        foreach(array_slice($j['events'],0,30) as $r){$title=cd_plain($r['title']??'',200);$date=cd_plain($r['start_date']??'',32);if($title!==''&&preg_match('/^\d{4}-\d{2}-\d{2}/D',$date))$rows[]=['title'=>$title,'start'=>$date,'venue'=>cd_plain($r['venue']['venue']??'',200)];}
    }elseif($provider==='brightsky'){
        if(!is_array($j['weather']??null))throw new RuntimeException('Invalid weather feed.');
        $nearest=null;$distance=PHP_INT_MAX;foreach($j['weather'] as $r){$t=strtotime((string)($r['timestamp']??''));if($t===false)continue;$d=abs(time()-$t);if($d<$distance){$nearest=$r;$distance=$d;}}
        if(!$nearest||$distance>10800)throw new RuntimeException('No recent weather.');
        $current=[];foreach(['temperature'=>'temperature','wind'=>'wind_speed','rain'=>'precipitation'] as $k=>$v)if(is_numeric($nearest[$v]??null)&&is_finite((float)$nearest[$v]))$current[$k]=(float)$nearest[$v];
        return ['status'=>'ok','fetchedAt'=>time(),'current'=>$current];
    }
    return ['status'=>'ok','fetchedAt'=>time(),'rows'=>$rows];
}
function cd_ical_parse(string $raw):array{
    if(!str_contains($raw,'BEGIN:VCALENDAR')||!str_contains($raw,'END:VCALENDAR'))throw new RuntimeException('Invalid iCalendar');
    $raw=preg_replace('/\r?\n[ \t]/','',$raw)??'';$rows=[];$event=null;$today=date('Y-m-d');
    foreach(preg_split('/\r\n|\n|\r/',$raw) as $line){
        if($line==='BEGIN:VEVENT'){$event=[];continue;}
        if($line==='END:VEVENT'&&is_array($event)){
            if(($event['STATUS'][1]??'')==='CANCELLED'||isset($event['RRULE'])||isset($event['RECURRENCE-ID'])){$event=null;continue;}
            $pair=$event['DTSTART']??[];$value=$pair[1]??'';$key=$pair[0]??'';$tz=new DateTimeZone('Europe/Berlin');$dt=null;
            try{
                if(preg_match('/(?:^|;)TZID=([^;:]+)/i',$key,$m))$tz=new DateTimeZone(trim($m[1],'"'));
                if(preg_match('/^\d{8}$/D',$value))$dt=DateTimeImmutable::createFromFormat('!Ymd',$value,$tz);
                elseif(preg_match('/^\d{8}T\d{6}Z$/D',$value))$dt=DateTimeImmutable::createFromFormat('!Ymd\THis\Z',$value,new DateTimeZone('UTC'));
                elseif(preg_match('/^\d{8}T\d{6}$/D',$value))$dt=DateTimeImmutable::createFromFormat('!Ymd\THis',$value,$tz);
            }catch(Throwable $e){}
            $title=cd_plain(str_replace(['\\n','\\N','\\,','\\;'],[' ',' ',',',';'],$event['SUMMARY'][1]??''),200);
            if($dt&&$title!==''&&$dt->format('Y-m-d')>=$today)$rows[]=['title'=>$title,'start'=>$dt->setTimezone(new DateTimeZone('Europe/Berlin'))->format(DATE_ATOM),'venue'=>cd_plain($event['LOCATION'][1]??'',200)];
            $event=null;continue;
        }
        if(is_array($event)&&str_contains($line,':')){[$key,$value]=explode(':',$line,2);$name=strtoupper(explode(';',$key,2)[0]);if(in_array($name,['DTSTART','SUMMARY','LOCATION','STATUS','RRULE','RECURRENCE-ID'],true))$event[$name]=[$key,$value];}
    }
    usort($rows,fn($a,$b)=>strcmp($a['start'],$b['start']));return array_slice($rows,0,30);
}
function cd_provider_load(string $provider,string $ref):array{
    $ref=cd_provider_reference($provider,$ref);
    if($provider==='nuliga')return ['status'=>'ok','fetchedAt'=>time(),'rows'=>array_slice(cd_nu_parse(cd_public_http($ref,4000000))['matches'],0,30)];
    if($provider==='ical')return ['status'=>'ok','fetchedAt'=>time(),'rows'=>cd_ical_parse(cd_public_http($ref))];
    if($provider==='wordpress')return cd_feed_json($provider,cd_public_http($ref.'?per_page=8&_fields=title,excerpt,date'));
    if($provider==='wp-events')return cd_feed_json($provider,cd_public_http($ref.'?'.http_build_query(['start_date'=>date('Y-m-d').' 00:00:00','per_page'=>30])));
    if($provider==='brightsky'){[$lat,$lon]=explode(',',$ref);return cd_feed_json($provider,cd_public_http('https://api.brightsky.dev/weather?'.http_build_query(['lat'=>$lat,'lon'=>$lon,'date'=>date('Y-m-d'),'tz'=>'Europe/Berlin'])));}
    throw new RuntimeException('No feed adapter.');
}
function cd_provider_cached(PDO $db,string $club,string $kind,string $provider,string $ref):array{
    $key=hash('sha256',$club."\0".$kind."\0".$provider."\0".$ref);$now=time();$old=null;
    $db->exec('BEGIN IMMEDIATE');
    try{
        $q=$db->prepare('SELECT * FROM source_cache WHERE kind=?');$q->execute([$key]);$old=$q->fetch();
        if($old&&$now-(int)$old['attempted']<300){$db->exec('COMMIT');return $now-(int)$old['fetched']<3600?(json_decode($old['payload'],true)?:['status'=>'unavailable']):['status'=>'unavailable'];}
        $q=$db->prepare('INSERT INTO source_cache VALUES (?,?,0,?) ON CONFLICT(kind) DO UPDATE SET attempted=excluded.attempted');$q->execute([$key,'{"status":"unavailable"}',$now]);$db->exec('COMMIT');
    }catch(Throwable $e){$db->exec('ROLLBACK');throw $e;}
    try{$data=cd_provider_load($provider,$ref);$fetched=$now;}catch(Throwable $e){
        $fetched=(int)($old['fetched']??0);$data=$old&&$now-$fetched<3600?(json_decode($old['payload'],true)?:[]):[];
        $data=$data?array_replace($data,['status'=>'stale']):['status'=>'unavailable'];
    }
    $q=$db->prepare('UPDATE source_cache SET payload=?,fetched=? WHERE kind=? AND attempted=?');$q->execute([json_encode($data,JSON_THROW_ON_ERROR|JSON_INVALID_UTF8_SUBSTITUTE),$fetched,$key,$now]);return $data;
}
function cd_nu_text(string $s): string {
    return trim(preg_replace('/\s+/u', ' ', str_replace("\u{00a0}", ' ', $s)) ?? '');
}
function cd_nu_parse(string $html): array {
    if (!class_exists('DOMDocument')) throw new RuntimeException('DOM extension unavailable');
    $dom = new DOMDocument();
    $old = libxml_use_internal_errors(true);
    try {
        if (!$dom->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING)) throw new RuntimeException('HTML parse failed');
    } finally { libxml_clear_errors(); libxml_use_internal_errors($old); }
    $xp = new DOMXPath($dom);
    $found = false; $matches = []; $range = null;
    $body = cd_nu_text($dom->textContent ?? '');
    if (preg_match('/Begegnungen im Zeitraum vom\s*[»„"]?(\d{2}\.\d{2}\.\d{4})[«“"]?\s*bis zum\s*[»„"]?(\d{2}\.\d{2}\.\d{4})/u', $body, $m)) {
        $range = ['from'=>$m[1], 'to'=>$m[2]];
    }
    foreach ($xp->query('//table') as $table) {
        $columns = null; $lastDate = null;
        $spans = [];
        foreach ($xp->query('./tr|./thead/tr|./tbody/tr|./tfoot/tr', $table) as $row) {
            $cells = []; $pos = 0;
            foreach ($spans as $i => $s) { $cells[$i] = $s['cell']; if (--$spans[$i]['left'] <= 0) unset($spans[$i]); }
            foreach ($xp->query('./th|./td', $row) as $cell) {
                while (isset($cells[$pos])) $pos++;
                $colspan = min(20, max(1, (int)$cell->getAttribute('colspan')));
                $rowspan = min(300, max(1, (int)$cell->getAttribute('rowspan')));
                for ($j = 0; $j < $colspan; $j++) {
                    $cells[$pos] = $cell;
                    if ($rowspan > 1) $spans[$pos] = ['cell'=>$cell,'left'=>$rowspan-1];
                    $pos++;
                }
            }
            ksort($cells);
            if (!$cells) continue;
            $texts = array_map(fn($c) => cd_nu_text($c->textContent), $cells);
            $homeCol = $awayCol = $leagueCol = $scoreCol = $venueCol = null;
            foreach ($texts as $i=>$s) {
                if ($s === 'Heimmannschaft') $homeCol = $i;
                if ($s === 'Gastmannschaft') $awayCol = $i;
                if ($s === 'Liga') $leagueCol = $i;
                if ($s === 'Matchpunkte') $scoreCol = $i;
                if (preg_match('/^(Spielort|Platzanlage|Spielstätte|Halle|Tennishalle|Austragungsort)$/iu', $s)) $venueCol = $i;
            }
            if ($homeCol !== null && $awayCol !== null && $leagueCol !== null) {
                $columns = [$leagueCol,$homeCol,$awayCol,$scoreCol,$venueCol]; $found = true; continue;
            }
            if ($columns === null) continue;
            [$li,$hi,$ai,$si,$vi] = $columns;
            // Blank date cells and rowspans repeat the last time.
            $dateCells = implode(' ', array_slice($texts,0,$li));
            if (preg_match('/(\d{2}\.\d{2}\.\d{4})\s+(\d{1,2}:\d{2})/u', $dateCells, $m)) {
                $dt = DateTimeImmutable::createFromFormat('!d.m.Y H:i', $m[1].' '.$m[2], new DateTimeZone('Europe/Berlin'));
                $lastDate = $dt ? $dt->format(DATE_ATOM) : null;
            } elseif (preg_match('/\d{2}\.\d{2}\.\d{4}/', $dateCells)) {
                $lastDate = null;
            }
            if ($lastDate === null || !isset($cells[$hi],$cells[$ai],$texts[$li])) continue;
            $name = static function($cell) use ($xp): string {
                foreach ($xp->query('.//a', $cell) as $a) {
                    $s = cd_nu_text($a->textContent);
                    if ($s !== '' && !preg_match('/Routenplan|Karte/i', $s)) return $s;
                }
                return cd_nu_text(str_replace('[Routenplan]', '', $cell->textContent));
            };
            $home = $name($cells[$hi]); $away = $name($cells[$ai]);
            $league = $texts[$li];
            if ($home === '' || $away === '' || $league === '') continue;
            $score = $si !== null ? ($texts[$si] ?? '') : '';
            $score = preg_match('/^\d+\s*:\s*\d+$/', $score) ? preg_replace('/\s+/', '', $score) : null;
            $venue = $vi !== null ? ($texts[$vi] ?? '') : '';
            // Some nuLiga layouts put a labeled venue inside the fixture cells.
            if ($venue === '') {
                foreach ($cells as $i=>$cell) {
                    if ($i > $ai) break;
                    $parts = preg_split('/\R/u', trim(preg_replace('/<br\s*\/?>/i', "\n", $dom->saveHTML($cell)) ?? '')) ?: [];
                    foreach ($parts as $part) {
                        $part = cd_nu_text(html_entity_decode(strip_tags($part), ENT_QUOTES|ENT_HTML5, 'UTF-8'));
                        if (preg_match('/^(?:Spielort|Platzanlage|Spielstätte|Halle|Tennishalle|Austragungsort)\s*:\s*(.+)$/iu', $part, $vm)) { $venue = $vm[1]; break 2; }
                    }
                    foreach ($xp->query('.//*[@title]', $cell) as $node) {
                        if (preg_match('/^(?:Spielort|Platzanlage|Spielstätte|Halle|Tennishalle|Austragungsort)\s*:\s*(.+)$/iu', cd_nu_text($node->getAttribute('title')), $vm)) { $venue = $vm[1]; break 2; }
                    }
                }
            }
            $venue = cd_nu_text(preg_replace('/\s*\[Routenplan(?:er)?\]\s*/iu', '', $venue) ?? '');
            if ($venue === '' || in_array($venue,['-','–','—'],true)) $venue = null;
            $key = $lastDate.'|'.$league.'|'.$home.'|'.$away;
            $matches[$key] = ['date'=>$lastDate,'league'=>$league,'home'=>$home,'away'=>$away,'score'=>$score,'venue'=>$venue];
        }
    }
    if (!$found) throw new RuntimeException('nuLiga match table not found');
    $matches = array_values($matches);
    usort($matches, fn($a,$b)=>strcmp($a['date'],$b['date']));
    return ['matches'=>$matches,'range'=>$range];
}
