#!/usr/bin/env bash
# Test the real HTTP handlers against an isolated disposable database, not production.
set -euo pipefail
tmp="$(mktemp -d)"
pid=""
cleanup(){ if [[ -n "$pid" ]]; then kill "$pid" 2>/dev/null || true; fi; rm -rf "$tmp"; }
trap cleanup EXIT
mkdir -p "$tmp/www/admin" "$tmp/www/clubdisplay-admin" "$tmp/sessions"
cp public/admin/* "$tmp/www/admin/"
cp deploy/admin-storage.htaccess "$tmp/www/clubdisplay-admin/.htaccess"
php -r '$p="test-operator-123456789";$g=bin2hex(random_bytes(32));$f=$argv[1]."/www/admin/index.php";file_put_contents($f,str_replace(["__CD_ADMIN_PASSWORD_HASH__","__CD_ADMIN_GUARD_TOKEN__"],[password_hash($p,PASSWORD_BCRYPT),$g],file_get_contents($f)));file_put_contents($argv[1]."/www/clubdisplay-admin/.deployment-verified-".hash("sha256",$g),$g);' "$tmp"
cat > "$tmp/router.php" <<'PHP'
<?php
// Disposable loopback test server simulates the production HTTPS virtual host.
if(($_SERVER['HTTP_ORIGIN']??'')==='http://localhost:18189'){
    $_SERVER['HTTP_ORIGIN']='https://clubdisplay.tennilo.de';
}
$_SERVER['HTTP_HOST']='clubdisplay.tennilo.de';
$_SERVER['HTTPS']='on';
$_SERVER['DOCUMENT_ROOT']=__DIR__.'/www';
if(parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH)==='/admin/'){require __DIR__.'/www/admin/index.php';return true;}
return false;
PHP
php -d session.save_path="$tmp/sessions" -S 127.0.0.1:18189 -t "$tmp/www" "$tmp/router.php" >"$tmp/server.log" 2>&1 &
pid=$!
base="http://localhost:18189/admin/"
for i in {1..30}; do curl -fsS "$base" -o /dev/null 2>/dev/null && break; sleep .2; done
# curl treats localhost as a secure context; the test uses production cookie flags.
op="$tmp/op.cookies"; club="$tmp/club.cookies"; other="$tmp/other.cookies"
touch "$op" "$club" "$other"
get(){ local jar="$1" url="$2" expected="$3"; local status; status="$(curl -sS -b "$jar" -c "$jar" -o "$tmp/body" -w '%{http_code}' "$url")"; [[ "$status" == "$expected" ]] || { echo "GET status $status expected $expected"; cat "$tmp/server.log"; exit 1; }; }
post(){ local jar="$1" url="$2" expected="$3"; shift 3; local status; status="$(curl -sS -b "$jar" -c "$jar" -o "$tmp/body" -w '%{http_code}' "$url" "$@")"; [[ "$status" == "$expected" ]] || { echo "POST status $status expected $expected"; cat "$tmp/server.log"; exit 1; }; }
csrf(){ grep -oE 'name="csrf" value="[a-f0-9]{64}"' "$tmp/body" | sed -n '1{s/.*value="//;s/"//;p;}'; }
login(){ local jar="$1" url="$2" pass="$3"; shift 3; get "$jar" "$url" 200; local t; t="$(csrf)"; post "$jar" "$url" 303 --data-urlencode "csrf=$t" --data-urlencode 'action=login' --data-urlencode "password=$pass" "$@"; get "$jar" "$url" 200; }
# Staging another release must not invalidate the currently deployed entrypoint.
marker="$(find "$tmp/www/clubdisplay-admin" -name '.deployment-verified-*' -print -quit)"
[[ -n "$marker" ]]
php -r '$g=bin2hex(random_bytes(32));file_put_contents($argv[1]."/.deployment-verified-".hash("sha256",$g),$g);' "$tmp/www/clubdisplay-admin"
get "$other" "$base" 200
# A missing or incorrect active marker must still block access, including the public QR route.
mv "$marker" "$marker.saved"
get "$other" "$base?partner=submit&club=ntc93" 503
grep -Fxq 'CD_ADMIN_STORAGE_GUARD' "$tmp/body"
printf '%064d' 0 > "$marker"
get "$other" "$base" 503
grep -Fxq 'CD_ADMIN_STORAGE_GUARD' "$tmp/body"
mv "$marker.saved" "$marker"
get "$other" "$base" 200
get "$other" "$base?preview-data=1&club=ntc93&screen=hall" 403
post "$other" "$base?device=1" 403 -H 'Content-Type: application/json' --data '{"action":"snapshot","token":"invalid"}'
login "$op" "$base" test-operator-123456789
grep -Fq 'TENNILO · Kundenverwaltung' "$tmp/body"
grep -Eq '<option value="INFO"[^>]*>BASIC</option>' "$tmp/body"
grep -Eq '<option value="INTERACTIVE"[^>]*>PLUS</option>' "$tmp/body"
t="$(csrf)"
post "$op" "$base" 303 --data-urlencode "csrf=$t" -d 'action=account&club=ntc93&new_password=test-club-password-123456&enabled=1'
get "$op" "$base" 200; t="$(csrf)"
post "$op" "$base" 303 --data-urlencode "csrf=$t" -d 'action=display-create&club=ntc93&slug=hall&name=Hall&mode=INFO&language=de&dwell=12&enabled=1&modules[]=welcome&modules[]=news'
get "$op" "$base" 200; t="$(csrf)"
post "$op" "$base" 303 --data-urlencode "csrf=$t" -d 'action=display-create&club=ntc93&slug=restaurant&name=Restaurant&mode=INFO&language=de&dwell=12&enabled=1&modules[]=welcome&modules[]=news'
# Operator cookie must not grant access to the club portal.
get "$op" "$base?portal=club" 200
grep -Fq 'Vereinsadmin-Anmeldung' "$tmp/body"
! grep -Fq 'TENNILO · Kundenverwaltung' "$tmp/body"
url="$base?portal=club&verein=ntc93"
login "$club" "$url" test-club-password-123456
grep -Fq 'Vereinsbackend' "$tmp/body"
! grep -Fq 'TENNILO · Kundenverwaltung' "$tmp/body"
! grep -Fq 'Vertrag & Bildschirmkontingent' "$tmp/body"
t="$(csrf)"
post "$club" "$url" 200 --data-urlencode "csrf=$t" -d 'action=contract&club=ntc93&screen_limit=99&state=active&cycle=yearly'
grep -Fq 'Nur TENNILO darf Vertragsdaten ändern.' "$tmp/body"
post "$club" "$url" 403 -d 'action=logout&csrf=wrong'
get "$club" "$url" 200; t="$(csrf)"
post "$club" "$url" 303 --data-urlencode "csrf=$t" -d 'action=content&club=ntc93&kind=news&id=0&title=HTTP-Test&body=%3Cscript%3Eexample%3C%2Fscript%3E&enabled=1&audience=selected&targets[]=hall'
get "$club" "$url" 200; t="$(csrf)"
id="$(php -r '$db=new PDO("sqlite:".$argv[1]);echo $db->query("SELECT id FROM club_content")->fetchColumn();' "$tmp/www/clubdisplay-admin/clubdisplay-admin.sqlite")"
php -r '$i=imagecreatetruecolor(10,10);imagepng($i,$argv[1]);' "$tmp/image.png"
post "$club" "$url" 303 -F "csrf=$t" -F action=media -F club=ntc93 -F "id=$id" -F "image=@$tmp/image.png;type=image/png"
get "$club" "$url&media=$id&club=ntc93" 200
cmp "$tmp/body" "$tmp/image.png" >/dev/null || php -r 'if(getimagesize($argv[1])["mime"]!=="image/png")exit(1);' "$tmp/body"
get "$club" "$url&preview-data=1&club=tennilo-demo&screen=hall" 404
get "$club" "$url&preview-data=1&club=ntc93&screen=restaurant" 200
php -r '$s=json_decode(file_get_contents($argv[1]),true,512,JSON_THROW_ON_ERROR);if($s["screen"]!=="Restaurant"||count($s["content"])!==0)exit(1);' "$tmp/body"
get "$club" "$url&preview-data=1&club=ntc93&screen=restaurant&image=$id" 404
get "$club" "$url&preview-data=1&club=ntc93&screen=hall" 200
php -r '$s=json_decode(file_get_contents($argv[1]),true,512,JSON_THROW_ON_ERROR);if($s["screen"]!=="Hall"||$s["content"][0]["title"]!=="HTTP-Test"||!$s["content"][0]["has_image"])exit(1);' "$tmp/body"
get "$club" "$url" 200; t="$(csrf)"
post "$club" "$url" 303 --data-urlencode "csrf=$t" -d 'action=device-issue&club=ntc93&slug=hall'
get "$club" "$url" 200
token="$(grep -oE 'player=1#[a-f0-9]{64}' "$tmp/body" | sed -n '1{s/.*#//;p;}')"
[[ "$token" =~ ^[a-f0-9]{64}$ ]]
post "$other" "$base?device=1" 200 -H 'Content-Type: application/json' --data "{\"action\":\"snapshot\",\"token\":\"$token\",\"version\":\"test\"}"
php -r '$s=json_decode(file_get_contents($argv[1]),true,512,JSON_THROW_ON_ERROR);if($s["club"]!=="NTC 93"||$s["content"][0]["title"]!=="HTTP-Test"||isset($s["token_hash"]))exit(1);' "$tmp/body"
post "$other" "$base?device=1" 200 -H 'Content-Type: application/json' --data "{\"action\":\"image\",\"token\":\"$token\",\"id\":$id}"
php -r 'if(getimagesize($argv[1])["mime"]!=="image/png")exit(1);' "$tmp/body"
if [[ "${CD_BROWSER_TEST:-0}" == 1 ]]; then
  mkdir -p artifacts
  CD_TEST_BASE="$base" CD_TEST_TOKEN="$token" node tests/admin-browser-test.cjs
fi
get "$club" "$url" 200; t="$(csrf)"
grep -Fq 'Letzter Kontakt' "$tmp/body"
! grep -Fq "$token" "$tmp/body"
post "$club" "$url" 303 --data-urlencode "csrf=$t" -d 'action=device-revoke&club=ntc93&slug=hall'
post "$other" "$base?device=1" 403 -H 'Content-Type: application/json' --data "{\"action\":\"snapshot\",\"token\":\"$token\"}"
get "$op" "$base" 200; t="$(csrf)"
post "$op" "$base" 200 --data-urlencode "csrf=$t" -d 'action=backup'
php -r '$d=new PDO("sqlite:".$argv[1]);if($d->query("PRAGMA integrity_check")->fetchColumn()!=="ok")exit(1);' "$tmp/body"
mkdir -p "$tmp/private-backups"
cp deploy/backup-admin.php "$tmp/www/clubdisplay-admin/backup-admin.php"
php "$tmp/www/clubdisplay-admin/backup-admin.php" "$tmp/private-backups"
backup="$(find "$tmp/private-backups" -name 'clubdisplay-*.sqlite' -print -quit)"
[[ -n "$backup" ]]
php -r '$d=new PDO("sqlite:".$argv[1]);if($d->query("PRAGMA integrity_check")->fetchColumn()!=="ok"||$d->query("SELECT COUNT(*) FROM club_content")->fetchColumn()!=1)exit(1);' "$backup"
if php "$tmp/www/clubdisplay-admin/backup-admin.php" "$tmp/www" 2>/dev/null; then echo 'Backup allowed in webroot'; exit 1; fi
# MATCHBALL: real multipart upload, authenticated original and public metadata only.
get "$club" "$url" 200; t="$(csrf)"
grep -Fq 'MATCHBALL – Newsletter' "$tmp/body"
# Minimal valid one-page PDF with offsets generated at runtime.
php -r '$pdf="%PDF-1.4\n";$objs=["<< /Type /Catalog /Pages 2 0 R >>","<< /Type /Pages /Kids [3 0 R] /Count 1 >>","<< /Type /Page /Parent 2 0 R /MediaBox [0 0 100 100] >>"];$offsets=[0];foreach($objs as $i=>$obj){$offsets[]=strlen($pdf);$pdf.=($i+1)." 0 obj\n".$obj."\nendobj\n";}$x=strlen($pdf);$pdf.="xref\n0 4\n0000000000 65535 f \n";foreach(array_slice($offsets,1) as $off)$pdf.=sprintf("%010d 00000 n \n",$off);$pdf.="trailer\n<< /Size 4 /Root 1 0 R >>\nstartxref\n".$x."\n%%EOF\n";file_put_contents($argv[1],$pdf);' "$tmp/newsletter.pdf"
post "$club" "$url" 303 -F "csrf=$t" -F action=matchball -F club=ntc93 -F intent=save -F month=2026-11 -F pages=1 -F headline=Testausgabe -F teaser=Kurztext -F 'story_title[]=Testthema' -F 'story_body[]=Testtext' -F 'story_ref[]=Seite 1' -F "newsletter_pdf=@$tmp/newsletter.pdf;type=application/pdf"
get "$club" "$url&matchball_pdf=2026-11&club=ntc93" 200
get "$other" "$base?matchball-display-pdf=1&club=ntc93&month=2026-11" 404
get "$club" "$url&matchball_pdf=2026-11&club=ntc93" 200
cmp "$tmp/body" "$tmp/newsletter.pdf"
get "$other" "$base?matchball_pdf=2026-11&club=ntc93" 404
get "$club" "$url&matchball_pdf=2026-11&club=tennilo-demo" 404
get "$other" "$base?matchball=feed&club=ntc93" 200
php -r '$v=json_decode(file_get_contents($argv[1]),true,512,JSON_THROW_ON_ERROR);if(count($v["issues"])!==2||isset($v["issues"][0]["pdf"]))exit(1);' "$tmp/body"
get "$club" "$url" 200; t="$(csrf)"
post "$club" "$url" 303 --data-urlencode "csrf=$t" -d 'action=matchball&club=ntc93&month=2026-11&intent=publish&public_reviewed=1'
get "$other" "$base?matchball-display-pdf=1&club=ntc93&month=2026-11" 404
get "$club" "$url" 200; t="$(csrf)"
post "$club" "$url" 303 --data-urlencode "csrf=$t" -d 'action=matchball&club=ntc93&month=2026-11&intent=publish&public_reviewed=1&pdf_reviewed=1'
get "$other" "$base?matchball-display-pdf=1&club=ntc93&month=2026-11" 200
cmp "$tmp/body" "$tmp/newsletter.pdf"
get "$other" "$base?matchball=feed&club=ntc93" 200
php -r '$v=json_decode(file_get_contents($argv[1]),true,512,JSON_THROW_ON_ERROR);if($v["issues"][0]["id"]!=="2026-11"||count($v["issues"])!==3||isset($v["issues"][0]["pdf"])||isset($v["issues"][0]["has_pdf"]))exit(1);' "$tmp/body"
get "$club" "$url" 200; t="$(csrf)"
post "$club" "$url" 303 --data-urlencode "csrf=$t" -d 'action=matchball&club=ntc93&month=2026-11&intent=delete&delete_confirm=1'
get "$other" "$base?matchball=feed&club=ntc93" 200
php -r '$v=json_decode(file_get_contents($argv[1]),true,512,JSON_THROW_ON_ERROR);if(count($v["issues"])!==2)exit(1);' "$tmp/body"
get "$other" "$base?matchball-display-pdf=1&club=ntc93&month=2026-11" 404
# A test PNG stands in for a private QR; never use a real invitation in CI.
php -r '$chunk=fn($t,$v)=>pack("N",strlen($v)).$t.$v.hash("crc32b",$t.$v,true);$p="\x89PNG\r\n\x1a\n".$chunk("IHDR",pack("NNCCCCC",574,574,1,0,0,0,0)).$chunk("IDAT",gzcompress(str_repeat("\0".str_repeat("\xff",72),574))).$chunk("IEND","");file_put_contents($argv[1],$p);' "$tmp/test-qr.png"
get "$club" "$url" 200; t="$(csrf)"
post "$club" "$url" 303 -F "csrf=$t" -F action=community -F club=ntc93 -F intent=save -F kind=youth -F "image=@$tmp/test-qr.png;type=image/png"
get "$other" "$base?matchball=feed&club=ntc93" 200
php -r '$v=json_decode(file_get_contents($argv[1]),true,512,JSON_THROW_ON_ERROR);if($v["communities"]!==[])exit(1);' "$tmp/body"
get "$club" "$url" 200; t="$(csrf)"
post "$club" "$url" 303 --data-urlencode "csrf=$t" -d 'action=community&club=ntc93&kind=youth&intent=publish&reviewed=1'
get "$other" "$base?matchball=feed&club=ntc93" 200
php -r '$v=json_decode(file_get_contents($argv[1]),true,512,JSON_THROW_ON_ERROR);if(count($v["communities"])!==1||$v["communities"][0]["kind"]!=="youth"||!str_starts_with(base64_decode($v["communities"][0]["png"]),"\x89PNG"))exit(1);' "$tmp/body"
get "$club" "$url" 200; t="$(csrf)"
post "$club" "$url" 303 --data-urlencode "csrf=$t" -d 'action=community&club=ntc93&kind=youth&intent=hide'
get "$other" "$base?matchball=feed&club=ntc93" 200
php -r '$v=json_decode(file_get_contents($argv[1]),true,512,JSON_THROW_ON_ERROR);if($v["communities"]!==[])exit(1);' "$tmp/body"
# Upload an original to a previously seeded month: preserve editorial text and require new review.
get "$club" "$url" 200; t="$(csrf)"
post "$club" "$url" 303 -F "csrf=$t" -F action=matchball -F club=ntc93 -F intent=upload-pdf -F month=2026-10 -F "newsletter_pdf=@$tmp/newsletter.pdf;type=application/pdf"
get "$club" "$url&matchball_pdf=2026-10&club=ntc93" 200
cmp "$tmp/body" "$tmp/newsletter.pdf"
get "$other" "$base?matchball-display-pdf=1&club=ntc93&month=2026-10" 404
get "$club" "$url" 200; t="$(csrf)"
grep -Fq 'Die Wintersaison hat begonnen' "$tmp/body"
post "$club" "$url" 200 -F "csrf=$t" -F action=matchball -F club=ntc93 -F intent=upload-pdf -F month=2026-10 -F "newsletter_pdf=@$tmp/newsletter.pdf;type=application/pdf"
grep -Fq 'Bitte Ersetzen ausdrücklich bestätigen' "$tmp/body"
get "$club" "$url" 200; t="$(csrf)"
post "$club" "$url" 303 --data-urlencode "csrf=$t" -d 'action=matchball&club=ntc93&month=2026-10&intent=publish&public_reviewed=1&pdf_reviewed=1'
get "$other" "$base?matchball-display-pdf=1&club=ntc93&month=2026-10" 200
cmp "$tmp/body" "$tmp/newsletter.pdf"
get "$club" "$url" 200; t="$(csrf)"
post "$club" "$url" 303 -F "csrf=$t" -F action=matchball -F club=ntc93 -F intent=upload-pdf -F month=2026-10 -F replace_pdf=1 -F "newsletter_pdf=@$tmp/newsletter.pdf;type=application/pdf"
get "$other" "$base?matchball-display-pdf=1&club=ntc93&month=2026-10" 404
echo "Existing-month PDF upload, protected replacement and renewed review OK"
echo "MATCHBALL upload, private original, public release and community QR draft/publish/hide HTTP tests passed."

# Neutral review link: fixture uses no actual email delivery.
review_token="$(php -r 'require "public/admin/lib.php";$db=cd_db($argv[1]);$now=time();cd_partner_mail_schema($db);$db->prepare("INSERT INTO partner_board VALUES (?,1) ON CONFLICT(club) DO UPDATE SET enabled=1")->execute(["ntc93"]);$db->prepare("INSERT INTO partner_posts VALUES (?,?,?,?,?,?,?, ?,?,?)")->execute(["ABCDEF1234","ntc93","singles","any","mon,fri","morning,evening","TEST-PRIVATE-CONTACT","pending",$now,$now+1209600]);$db->prepare("INSERT INTO partner_post_age VALUES (?,?)")->execute(["ABCDEF1234","adult"]);$db->prepare("INSERT INTO partner_post_preferences VALUES (?,?,?)")->execute(["ABCDEF1234","30-39","40-49,50-59"]);$db->prepare("INSERT INTO partner_post_meta(ref,email,lk) VALUES (?,?,?)")->execute(["ABCDEF1234","","11-15"]);$token=bin2hex(random_bytes(32));$db->prepare("INSERT INTO partner_mail_tokens VALUES (?,?,?,?)")->execute(["ABCDEF1234",hash("sha256",$token),$now+3600,"sent"]);echo $token;' "$tmp/www/clubdisplay-admin/clubdisplay-admin.sqlite")"
review_url="$base?partner=moderate&club=ntc93&token=$review_token"
get "$other" "$review_url" 200
for marker in 'Gesuch freigeben' 'Gesuch ablehnen' 'TEST-PRIVATE-CONTACT' 'Gesuchte Altersgruppen' 'LK-Angabe' 'Montag, Freitag'; do grep -Fq "$marker" "$tmp/body" || { echo "Missing review marker: $marker"; exit 1; }; done
get "$other" "$review_url&decision=delete" 200
grep -Fq 'Gesuch freigeben' "$tmp/body"
grep -Fq 'Gesuch ablehnen' "$tmp/body"
post "$other" "$base?partner=moderate&club=ntc93" 200 --data "token=$review_token&decision=publish"
grep -Fq 'Bitte die gewählte Entscheidung bestätigen.' "$tmp/body"
get "$other" "$review_url" 200
post "$other" "$base?partner=moderate&club=ntc93" 200 --data "token=$review_token&decision=publish&confirmed=1"
grep -Fq 'Gesuch freigegeben und für 14 Tage veröffentlicht.' "$tmp/body"
get "$other" "$review_url" 410
echo "Neutral review, legacy GET, confirmation and single-use token HTTP tests passed."

get "$other" "$base?player=1" 200
grep -Fq 'player.js' "$tmp/body"
echo "HTTP login, CSRF, tenant isolation, real image upload, preview, pairing, revocation, backup passed."
