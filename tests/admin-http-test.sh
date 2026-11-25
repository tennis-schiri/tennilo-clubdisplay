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
php -r '$p="test-operator-123456789";$g=bin2hex(random_bytes(32));$f=$argv[1]."/www/admin/index.php";file_put_contents($f,str_replace(["__CD_ADMIN_PASSWORD_HASH__","__CD_ADMIN_GUARD_TOKEN__"],[password_hash($p,PASSWORD_BCRYPT),$g],file_get_contents($f)));file_put_contents($argv[1]."/www/clubdisplay-admin/.deployment-verified",$g);' "$tmp"
cat > "$tmp/router.php" <<'PHP'
<?php
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
get "$other" "$base?preview-data=1&club=ntc93&screen=hall" 403
post "$other" "$base?device=1" 403 -H 'Content-Type: application/json' --data '{"action":"snapshot","token":"invalid"}'
login "$op" "$base" test-operator-123456789
grep -Fq 'TENNILO · Kundenverwaltung' "$tmp/body"
t="$(csrf)"
post "$op" "$base" 303 --data-urlencode "csrf=$t" -d 'action=account&club=ntc93&new_password=test-club-password-123456&enabled=1'
get "$op" "$base" 200; t="$(csrf)"
post "$op" "$base" 303 --data-urlencode "csrf=$t" -d 'action=display-create&club=ntc93&slug=hall&name=Hall&mode=INFO&language=de&dwell=12&enabled=1&modules[]=welcome&modules[]=news'
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
post "$club" "$url" 303 --data-urlencode "csrf=$t" -d 'action=content&club=ntc93&kind=news&id=0&title=HTTP-Test&body=%3Cscript%3Eexample%3C%2Fscript%3E&enabled=1'
get "$club" "$url" 200; t="$(csrf)"
id="$(php -r '$db=new PDO("sqlite:".$argv[1]);echo $db->query("SELECT id FROM club_content")->fetchColumn();' "$tmp/www/clubdisplay-admin/clubdisplay-admin.sqlite")"
php -r '$i=imagecreatetruecolor(10,10);imagepng($i,$argv[1]);' "$tmp/image.png"
post "$club" "$url" 303 -F "csrf=$t" -F action=media -F club=ntc93 -F "id=$id" -F "image=@$tmp/image.png;type=image/png"
get "$club" "$url&media=$id&club=ntc93" 200
cmp "$tmp/body" "$tmp/image.png" >/dev/null || php -r 'if(getimagesize($argv[1])["mime"]!=="image/png")exit(1);' "$tmp/body"
get "$club" "$url&preview-data=1&club=tennilo-demo&screen=hall" 404
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
get "$other" "$base?player=1" 200
grep -Fq 'player.js' "$tmp/body"
echo "HTTP login, CSRF, tenant isolation, real image upload, preview, pairing, revocation, backup passed."
