#!/usr/bin/env bash
# TENNILO ClubDisplay | Copyright © 2026 TENNILO | Alle Rechte vorbehalten.
set -euo pipefail
: "${FTP_HOST:?FTP host missing}" "${FTP_USER:?FTP user missing}" "${FTP_PASSWORD:?FTP password missing}" "${DOMAIN:?Domain missing}" "${RELEASE_ID:?Release ID missing}"
case "$DOMAIN" in clubdisplay.tennilo.de|clubdisplay.ntc93.de) ;; *) exit 1;; esac
[[ "$FTP_HOST" =~ ^[A-Za-z0-9.-]+$ ]] || { echo 'FTP host must be a hostname'; exit 1; }
node --check public/assets/app.js
node tests/info-countdown-test.cjs
node tests/occupancy-test.cjs
node tests/match-greeting-test.cjs
node tests/demo-test.cjs
php -l public/nuliga.php
php tests/nuliga-test.php
php -l public/events.php
php -l public/admin-pin.php
php -l public/terminal.php
php tests/events-test.php
php -l public/news.php
php tests/news-test.php
php -l public/weather.php
php tests/weather-test.php
files=(assets/clubdisplay.png assets/favicon.png assets/ntc93-logo.png assets/tennilo-footer-logo.png assets/app.css assets/app.js index.html)
test -s public/nuliga.php
test -s public/events.php
for f in "${files[@]}"; do test -s "public/$f"; done
tmp="$(mktemp -d)"
trap 'rm -rf "$tmp"' EXIT
upload() {
  curl --fail --silent --show-error --ssl-reqd --ftp-pasv --ftp-create-dirs --connect-timeout 15 --max-time 90 --user "${FTP_USER}:${FTP_PASSWORD}" --upload-file "$1" "ftp://${FTP_HOST}:21/$2"
}
verify() {
  curl --fail --silent --show-error --connect-timeout 15 --max-time 40 --header 'Cache-Control: no-cache' --output "$tmp/remote" "https://${DOMAIN}/$2?release=${RELEASE_ID}"
  cmp -s "$1" "$tmp/remote" || { echo "::error::HTTPS content mismatch: $2"; exit 1; }
}
printf '%s\n' "${DOMAIN}:${RELEASE_ID}" > "$tmp/check"
upload "$tmp/check" clubdisplay-deploy-check.txt
verify "$tmp/check" clubdisplay-deploy-check.txt
# Generate the deployment-specific PIN verifier without storing the PIN in Git.
php -r '$pin=getenv("ADMIN_PIN"); if($pin!==false && $pin!=="" && !preg_match("/^[0-9]{6,20}$/D",$pin)){fwrite(STDERR,"Admin PIN must contain 6 to 20 digits\n");exit(1);} $hash=($pin===false||$pin==="")?"":password_hash($pin,PASSWORD_BCRYPT); $src=file_get_contents("public/admin-pin.php"); file_put_contents($argv[1],str_replace("__CLUBDISPLAY_ADMIN_HASH__",$hash,$src));' "$tmp/admin-pin.php"
php -l "$tmp/admin-pin.php"
upload "$tmp/admin-pin.php" admin-pin.php
status="$(curl --silent --show-error --max-time 25 --output "$tmp/admin-response" --write-out '%{http_code}' "https://${DOMAIN}/admin-pin.php")"
[[ "$status" == 405 ]] || { echo "::error::Admin endpoint not executing correctly"; exit 1; }
php -r '$v=json_decode(file_get_contents($argv[1]),true); if(!is_array($v)||($v["ok"]??null)!==false)exit(1);' "$tmp/admin-response"
if [[ -z "${ADMIN_PIN:-}" ]]; then echo "::notice::Admin PIN not configured; app fullscreen exit remains locked."; fi
if [[ "$DOMAIN" == "clubdisplay.ntc93.de" ]]; then
  php -r '$client=getenv("EBUSY_CLIENT_ID")?: ""; if($client!==""&&!preg_match("/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/iD",$client)){fwrite(STDERR,"Invalid eBuSy client ID format\n");exit(1);} file_put_contents($argv[1],str_replace("__CLUBDISPLAY_EBUSY_CLIENT__",$client,file_get_contents("public/terminal.php")));' "$tmp/terminal.php"
  php -l "$tmp/terminal.php"
  upload "$tmp/terminal.php" terminal.php
  curl --fail --silent --show-error --max-time 25 --output "$tmp/terminal-status" "https://${DOMAIN}/terminal.php?status=1"
  php -r '$v=json_decode(file_get_contents($argv[1]),true); if(!is_array($v)||!is_bool($v["configured"]??null)||($v["ready"]??null)!==$v["configured"])exit(1); echo "Terminal endpoint configured: ".($v["configured"]?"yes":"no")."\n";' "$tmp/terminal-status"
fi
upload public/nuliga.php nuliga.php
# Dynamic PHP must be executed, never served as source.
curl --fail --silent --show-error --max-time 40 --output "$tmp/feed" "https://${DOMAIN}/nuliga.php?release=${RELEASE_ID}"
php -r '$v=json_decode(file_get_contents($argv[1]),true); if(!is_array($v)||!isset($v["status"],$v["matches"])||!is_array($v["matches"])) exit(1); echo "nuLiga feed: ".$v["status"]." / ".count($v["matches"])." matches\n";' "$tmp/feed"
upload public/events.php events.php
curl --fail --silent --show-error --max-time 50 --output "$tmp/events" "https://${DOMAIN}/events.php?release=${RELEASE_ID}"
php -r '$v=json_decode(file_get_contents($argv[1]),true); if(!is_array($v)||!isset($v["status"],$v["events"])||!is_array($v["events"])) exit(1); echo "Calendar feed: ".$v["status"]." / ".count($v["events"])." events\n";' "$tmp/events"
upload public/weather.php weather.php
curl --fail --silent --show-error --max-time 50 --output "$tmp/weather" "https://${DOMAIN}/weather.php"
php -r '$v=json_decode(file_get_contents($argv[1]),true); if(!is_array($v)||!in_array($v["status"]??"",["ok","stale","unavailable"],true))exit(1); echo "Weather feed: ".$v["status"]."\n";' "$tmp/weather"
if [[ "$DOMAIN" == "clubdisplay.ntc93.de" ]]; then
  upload public/news.php news.php
  curl --fail --silent --show-error --max-time 50 --output "$tmp/news" "https://${DOMAIN}/news.php?release=${RELEASE_ID}"
  php -r '$v=json_decode(file_get_contents($argv[1]),true); if(!is_array($v)||!in_array($v["status"]??"",["ok","stale","unavailable"],true)||!is_array($v["posts"]??null)||count($v["posts"])>3)exit(1); echo "News feed: ".$v["status"]." / ".count($v["posts"])." posts / ".($v["source"]??"none")."\n";' "$tmp/news"
fi
# Match the complete Allianz card to the original image's neutral grey border.
# Only the NTC upload needs the measured colour; a safe light-grey fallback remains in source CSS.
if [[ "$DOMAIN" == "clubdisplay.ntc93.de" ]]; then
  if curl --fail --silent --show-error --connect-timeout 5 --max-time 12 --max-filesize 2000000 'https://ntc93.com/wp-content/uploads/2023/07/Allianz-Maxelon-300x155.jpeg' -o "$tmp/allianz.jpeg"; then
    if ! python3 -c 'from PIL import Image' >/dev/null 2>&1; then
      python3 -m venv "$tmp/image-venv" && "$tmp/image-venv/bin/pip" install --quiet --disable-pip-version-check --timeout 8 --retries 1 Pillow || true
    fi
    image_python=python3
    if [[ -x "$tmp/image-venv/bin/python" ]]; then image_python="$tmp/image-venv/bin/python"; fi
    if colour="$("$image_python" scripts/allianz-background.py "$tmp/allianz.jpeg")" && [[ "$colour" =~ ^#[0-9a-f]{6}$ ]]; then
      printf '\n.sponsor-logo-allianz{--allianz-logo-background:%s}\n' "$colour" >> public/assets/app.css
      echo "Allianz logo background: $colour"
    else
      echo "::warning::Allianz background could not be sampled; using light-grey fallback."
    fi
  else
    echo "::warning::Allianz original image unavailable; using light-grey fallback."
  fi
fi
for f in "${files[@]}"; do
  echo "Deploy: $f"
  upload "public/$f" "$f"
  verify "public/$f" "$f"
done
echo "Release verified: $DOMAIN"
