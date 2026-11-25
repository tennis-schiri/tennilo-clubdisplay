<?php
declare(strict_types=1);
if(!isset($db)){http_response_code(404);exit;}
?><!doctype html><html lang="de"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>ClubDisplay · Verwaltetes Display</title><link rel="stylesheet" href="player.css?v=063"><script defer src="player.js?v=063"></script></head><body data-preview="<?=isset($previewUrl)?cd_h($previewUrl):''?>">
<header><img id="club-logo" alt="" hidden><div><strong id="club">ClubDisplay</strong><small id="screen"></small></div><div class="clock"><strong id="clock">--:--</strong><small id="date"></small></div></header>
<main id="slide" aria-live="off"><p>Verbindung wird aufgebaut …</p></main>
<footer><span id="status" role="status">Verbindung wird aufgebaut</span><div class="controls"><button type="button" id="prev" aria-label="Zurück">‹</button><span id="position">–</span><button type="button" id="pause" aria-label="Pause">Ⅱ</button><button type="button" id="next" aria-label="Weiter">›</button><button type="button" id="full" aria-label="Vollbild">⛶</button></div><span>TENNILO ClubDisplay · 0.6.3</span></footer>
</body></html>
