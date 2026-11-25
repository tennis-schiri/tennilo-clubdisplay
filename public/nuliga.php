<?php
// TENNILO ClubDisplay | Copyright © 2026 TENNILO | Alle Rechte vorbehalten.
declare(strict_types=1);
const NULIGA_URL = 'https://tvbb.liga.nu/cgi-bin/WebObjects/nuLigaTENDE.woa/wa/clubMeetings?club=36381';
const NULIGA_TTL = 1800;
const NULIGA_RETRY = 300;
function cleanText(string $s): string {
    return trim(preg_replace('/\s+/u', ' ', str_replace("\u{00a0}", ' ', $s)) ?? '');
}
function parseMeetings(string $html): array {
    if (!class_exists('DOMDocument')) throw new RuntimeException('DOM extension unavailable');
    $dom = new DOMDocument();
    $old = libxml_use_internal_errors(true);
    try {
        if (!$dom->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING)) throw new RuntimeException('HTML parse failed');
    } finally { libxml_clear_errors(); libxml_use_internal_errors($old); }
    $xp = new DOMXPath($dom);
    $found = false; $matches = []; $range = null;
    $body = cleanText($dom->textContent ?? '');
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
            $texts = array_map(fn($c) => cleanText($c->textContent), $cells);
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
                    $s = cleanText($a->textContent);
                    if ($s !== '' && !preg_match('/Routenplan|Karte/i', $s)) return $s;
                }
                return cleanText(str_replace('[Routenplan]', '', $cell->textContent));
            };
            $home = $name($cells[$hi]); $away = $name($cells[$ai]);
            $ownHome = preg_match('/^Neuenhagener\s+(?:Tennisclub|TC)\s+93(?:\s|$)/iu', $home) === 1;
            $ownAway = preg_match('/^Neuenhagener\s+(?:Tennisclub|TC)\s+93(?:\s|$)/iu', $away) === 1;
            if (!$ownHome && !$ownAway) continue;
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
                        $part = cleanText(html_entity_decode(strip_tags($part), ENT_QUOTES|ENT_HTML5, 'UTF-8'));
                        if (preg_match('/^(?:Spielort|Platzanlage|Spielstätte|Halle|Tennishalle|Austragungsort)\s*:\s*(.+)$/iu', $part, $vm)) { $venue = $vm[1]; break 2; }
                    }
                    foreach ($xp->query('.//*[@title]', $cell) as $node) {
                        if (preg_match('/^(?:Spielort|Platzanlage|Spielstätte|Halle|Tennishalle|Austragungsort)\s*:\s*(.+)$/iu', cleanText($node->getAttribute('title')), $vm)) { $venue = $vm[1]; break 2; }
                    }
                }
            }
            $venue = cleanText(preg_replace('/\s*\[Routenplan(?:er)?\]\s*/iu', '', $venue) ?? '');
            if ($venue === '' || in_array($venue,['-','–','—'],true)) $venue = null;
            $key = $lastDate.'|'.$league.'|'.$home.'|'.$away;
            $matches[$key] = ['date'=>$lastDate,'league'=>$league,'home'=>$home,'away'=>$away,'isHome'=>$ownHome,'score'=>$score,'venue'=>$venue];
        }
    }
    if (!$found) throw new RuntimeException('nuLiga match table not found');
    $matches = array_values($matches);
    usort($matches, fn($a,$b)=>strcmp($a['date'],$b['date']));
    return ['matches'=>$matches,'range'=>$range];
}
function fetchSource(): string {
    if (!function_exists('curl_init')) throw new RuntimeException('cURL extension unavailable');
    $curl = curl_init(NULIGA_URL);
    $body = '';
    curl_setopt_array($curl, [
        CURLOPT_CONNECTTIMEOUT=>5,CURLOPT_TIMEOUT=>15,CURLOPT_FOLLOWLOCATION=>false,
        CURLOPT_USERAGENT=>'TENNILO ClubDisplay/0.2 (public club schedule)',
        CURLOPT_HTTPHEADER=>['Accept: text/html'],CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2,
        CURLOPT_WRITEFUNCTION=>static function($handle,string $chunk) use (&$body): int {
            if (strlen($body)+strlen($chunk)>4000000) return 0;
            $body.=$chunk; return strlen($chunk);
        }
    ]);
    $ok = curl_exec($curl); $status = curl_getinfo($curl,CURLINFO_RESPONSE_CODE);
    curl_close($curl);
    if (!$ok || $status !== 200 || $body === '') throw new RuntimeException('nuLiga request failed');
    return $body;
}
function getMeetings(): array {
    // Cache outside web root, separate per deployed domain/directory.
    $dir = sys_get_temp_dir().'/tennilo-cd-'.hash('sha256', __DIR__);
    if (!is_dir($dir) && !@mkdir($dir,0700) && !is_dir($dir)) throw new RuntimeException('Cache unavailable');
    $lock = @fopen($dir.'/lock','c');
    if (!$lock) throw new RuntimeException('Cache unavailable');
    try {
        if (!flock($lock,LOCK_EX)) throw new RuntimeException('Cache unavailable');
        $cache = is_file($dir.'/meetings-v2.json') ? json_decode((string)file_get_contents($dir.'/meetings-v2.json'),true) : null;
        $now = time();
        if (is_array($cache) && $now-($cache['fetchedAt']??0)<NULIGA_TTL) return $cache+['status'=>'ok'];
        if (!is_file($dir.'/retry-v2') || $now-(int)file_get_contents($dir.'/retry-v2')>=NULIGA_RETRY) {
            file_put_contents($dir.'/retry-v2',(string)$now,LOCK_EX);
            try {
                $data = parseMeetings(fetchSource());
                $data['fetchedAt'] = $now;
                if (file_put_contents($dir.'/meetings-v2.tmp',json_encode($data,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE),LOCK_EX) === false || !rename($dir.'/meetings-v2.tmp',$dir.'/meetings-v2.json')) throw new RuntimeException('Cache write failed');
                return $data+['status'=>'ok'];
            } catch (Throwable $e) { error_log('ClubDisplay nuLiga: '.$e->getMessage()); }
        }
        if (is_array($cache) && $now-($cache['fetchedAt']??0)<=7*86400) return $cache+['status'=>'stale'];
        return ['status'=>'unavailable','matches'=>[],'fetchedAt'=>null,'range'=>null];
    } finally { fclose($lock); }
}
if (defined('CLUBDISPLAY_NULIGA_TEST')) return;
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
try { $data = getMeetings(); }
catch (Throwable $e) { error_log('ClubDisplay nuLiga: '.$e->getMessage()); $data=['status'=>'unavailable','matches'=>[],'fetchedAt'=>null,'range'=>null]; }
echo json_encode($data+['source'=>NULIGA_URL],JSON_UNESCAPED_UNICODE|JSON_INVALID_UTF8_SUBSTITUTE);
