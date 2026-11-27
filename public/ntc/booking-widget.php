<?php
declare(strict_types=1);
// Official eBuSy booking widget, isolated to the booking view on the NTC host.
if(($_SERVER['HTTP_HOST']??'')!=='clubdisplay.ntc93.de'||($_SERVER['REQUEST_METHOD']??'')!=='GET'){http_response_code(404);exit;}
$en=($_GET['lang']??'')==='en';
$nonce=base64_encode(random_bytes(18));
header('Content-Type: text/html; charset=UTF-8');
header('Cache-Control: no-store');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header("Content-Security-Policy: default-src 'self'; script-src 'nonce-".$nonce."' https://cd-ebusy.ntc93.de; style-src 'self' 'unsafe-inline'; frame-src https://cd-ebusy.ntc93.de; connect-src https://cd-ebusy.ntc93.de; img-src 'self' data: https://cd-ebusy.ntc93.de; form-action 'self' https://cd-ebusy.ntc93.de; base-uri 'none'; frame-ancestors 'self'");
?><!doctype html><html lang="<?=$en?'en':'de'?>"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>eBuSy · NTC 93</title><style>
*{box-sizing:border-box}html,body{margin:0;width:100%;height:100%;overflow:hidden;background:#fff;color:#12383b;font:15px/1.45 system-ui,sans-serif}#booking-container{width:100%!important;height:100vh!important;min-height:0!important;overflow:hidden}#booking-container iframe{display:block;width:100%!important;height:100vh!important;min-height:0!important;max-height:none!important;margin:0!important;border:0}#booking-status{margin:0;padding:16px;max-height:100vh;overflow:auto;background:#f2f7f6}#booking-status[hidden]{display:none}button{background:#075357;color:#fff;border:0;border-radius:6px;padding:.6rem 1rem;font:inherit;cursor:pointer;margin-top:.5rem}a{color:#075357}
</style><script src="https://cd-ebusy.ntc93.de/resources/js/ebusy.api.js"></script></head><body>
<div id="booking-status" role="status"><span id="booking-message"><?=$en?'Loading eBuSy booking widget …':'eBuSy-Buchungswidget wird geladen …'?></span><div id="booking-help" hidden><p><?=$en?'Use the PIN terminal in ClubDisplay if the online widget is unavailable.':'Falls das Online-Widget nicht erreichbar ist, nutze das PIN-Terminal in ClubDisplay.'?></p><button id="booking-retry" type="button"><?=$en?'Retry':'Erneut laden'?></button></div></div>
<div id="booking-container"></div>
<script nonce="<?=htmlspecialchars($nonce,ENT_QUOTES,'UTF-8')?>">
(function(){
 const status=document.getElementById('booking-status'),message=document.getElementById('booking-message'),help=document.getElementById('booking-help'),container=document.getElementById('booking-container');
 const fail=()=>{status.hidden=false;message.textContent=<?=$en?'"eBuSy widget could not be loaded."':'"eBuSy-Widget konnte nicht geladen werden."'?>;help.hidden=false;};
 document.getElementById('booking-retry').addEventListener('click',()=>location.reload());
 try{
  if(typeof ebusy==='undefined'||typeof ebusy.createBookingView!=='function')throw Error('widget unavailable');
  const observer=new MutationObserver(()=>{if(container.querySelector('iframe')){status.hidden=true;observer.disconnect();}});
  observer.observe(container,{childList:true,subtree:true});
  // Vendor-documented createBookingView, regular eBuSy login; no terminal client parameter.
  ebusy.createBookingView('booking-container','https://cd-ebusy.ntc93.de/embed/lite-module/1731',{offset:0});
  if(container.querySelector('iframe')){status.hidden=true;observer.disconnect();}
  setTimeout(()=>{if(!container.querySelector('iframe')){observer.disconnect();fail();}},12000);
 }catch(e){fail();}
})();
</script></body></html>
