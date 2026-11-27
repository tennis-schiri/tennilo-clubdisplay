#!/usr/bin/env bash
# TENNILO ClubDisplay | Copyright © 2026 TENNILO | Alle Rechte vorbehalten.
set -euo pipefail
: "${FTP_HOST:?FTP host missing}" "${FTP_USER:?FTP user missing}" "${FTP_PASSWORD:?FTP password missing}" "${DOMAIN:?Domain missing}" "${RELEASE_ID:?Release ID missing}"
case "$DOMAIN" in clubdisplay.tennilo.de|clubdisplay.ntc93.de) ;; *) exit 1;; esac
[[ "$FTP_HOST" =~ ^[A-Za-z0-9.-]+$ ]] || { echo 'FTP host must be a hostname'; exit 1; }
node --check public/assets/app.js
node --check public/assets/auto-update.js
node tests/auto-update-test.cjs
node tests/info-countdown-test.cjs
node tests/night-mode-test.cjs
node tests/occupancy-test.cjs
node tests/match-greeting-test.cjs
node tests/demo-test.cjs
php -l public/nuliga.php
php tests/nuliga-test.php
php -l public/events.php
php -l public/admin-pin.php
php -l public/terminal.php
php -l public/ntc/ebusy-occupancy.php
php tests/ebusy-occupancy-test.php
php -l public/ntc/devices.php
php tests/test-devices.php
php tests/events-test.php
php -l public/news.php
php tests/news-test.php
php -l public/weather.php
php -l public/demo.php
php tests/weather-test.php
files=(assets/clubdisplay.png assets/favicon.png assets/ntc93-logo.png assets/tennilo-footer-logo.png assets/app.css assets/app.js assets/auto-update.js index.html)
if [[ "$DOMAIN" == "clubdisplay.ntc93.de" ]]; then files+=(assets/b1-logo.webp); fi
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
php -r '$v=json_decode(file_get_contents($argv[1]),true); if(!is_array($v)||!isset($v["status"],$v["events"])||!is_array($v["events"])) exit(1); echo "Calendar feed: ".$v["status"]." / ".count($v["events"])." events / ".($v["from"]??"?")." to ".($v["to"]??"?")." / ".($v["method"]??"?")."\n"; foreach($v["events"] as $e) echo "Calendar event: ".json_encode(array_intersect_key($e,array_flip(["title","start","end","allDay"])),JSON_UNESCAPED_UNICODE|JSON_INVALID_UTF8_SUBSTITUTE)."\n";' "$tmp/events"
if [[ "$DOMAIN" == "clubdisplay.tennilo.de" ]]; then
  upload public/demo.php demo.php
  curl --fail --silent --show-error --max-time 25 "https://${DOMAIN}/demo.php?board=1" -o "$tmp/demo-board"
  grep -Fq 'Nur Beispieldaten' "$tmp/demo-board"
  curl --fail --silent --show-error --max-time 25 "https://${DOMAIN}/demo.php?pdf=2026-11" -o "$tmp/demo-pdf"
  head -c 8 "$tmp/demo-pdf" | grep -Fq '%PDF-1.4'
fi
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

if [[ "$DOMAIN" == "clubdisplay.ntc93.de" ]]; then

  # NTC only: protect configuration before transferring any secret.
  printf 'Require all denied\n' > "$tmp/ebusy-deny"
  printf 'protected\n' > "$tmp/ebusy-probe"
  upload "$tmp/ebusy-deny" ebusy-private/.htaccess
  upload "$tmp/ebusy-probe" ebusy-private/probe.txt
  code="$(curl --silent --show-error --max-time 25 -o /dev/null -w '%{http_code}' "https://$DOMAIN/ebusy-private/probe.txt")"
  [[ "$code" == 403 ]] || { echo "::error::Private eBuSy directory is not protected (HTTP $code). No credentials uploaded."; exit 1; }
  php -r '$u=getenv("EBUSY_API_USER")?: ""; $p=getenv("EBUSY_API_PASSWORD")?: ""; $ip=getenv("EBUSY_DISPLAY_IP")?: ""; if($ip!==""&&!filter_var($ip,FILTER_VALIDATE_IP))exit(1); $check=bin2hex(random_bytes(32)); file_put_contents($argv[1],"<?php\nreturn ".var_export(["user"=>$u,"password"=>$p,"ip"=>$ip,"check"=>$check],true).";\n"); file_put_contents($argv[2],$check);' "$tmp/ebusy-config.php" "$tmp/ebusy-check"
  php -l "$tmp/ebusy-config.php"
  upload "$tmp/ebusy-config.php" ebusy-private/config.php
  php -l public/ntc/ebusy-occupancy.php
  upload public/ntc/devices.php devices.php
  upload public/ntc/ebusy-occupancy.php ebusy-occupancy.php
  # The diagnostic returns only status/count, not bookings. No secret in URL.
  printf 'X-ClubDisplay-Check: %s\n' "$(cat "$tmp/ebusy-check")" > "$tmp/ebusy-header"
  curl --silent --show-error --max-time 55 --header @"$tmp/ebusy-header" -o "$tmp/ebusy-status" "https://$DOMAIN/ebusy-occupancy.php"
  php -r '$v=json_decode(file_get_contents($argv[1]),true); if(!is_array($v)||!isset($v["status"]))exit(1); echo "eBuSy diagnostic: ".json_encode(array_intersect_key($v,array_flip(["status","reason","slotCount","onsiteConfigured","endpoints","labeledSlots","matchBookingCount"])))."\n";' "$tmp/ebusy-status"
  if ! php -r '$v=json_decode(file_get_contents($argv[1]),true); exit(($v["status"]??null)==="ok"?0:1);' "$tmp/ebusy-status"; then echo "::warning::eBuSy connection or mapping not confirmed. No live occupancy is shown."; fi


  # Local test-browser management: protect SQLite/config before upload.
  printf 'Require all denied\n' > "$tmp/devices-deny"
  printf 'protected\n' > "$tmp/devices-probe"
  upload "$tmp/devices-deny" devices-private/.htaccess
  upload "$tmp/devices-probe" devices-private/probe.txt
  code="$(curl --silent --show-error --max-time 25 -o /dev/null -w '%{http_code}' "https://$DOMAIN/devices-private/probe.txt")"
  [[ "$code" == 403 ]] || { echo "::error::Device storage is not protected (HTTP $code)"; exit 1; }
  php -r '$pin=getenv("ADMIN_PIN")?: ""; $hash=$pin!==""?password_hash($pin,PASSWORD_BCRYPT):""; file_put_contents($argv[1],"<?php\nreturn ".var_export(["hash"=>$hash],true).";\n");' "$tmp/devices-config.php"
  upload "$tmp/devices-config.php" devices-private/config.php
  upload public/ntc/devices.php devices.php
  php -l public/ntc/booking-widget.php
  upload public/ntc/booking-widget.php booking-widget.php
  curl --fail --silent --show-error --max-time 25 "https://$DOMAIN/booking-widget.php?release=$RELEASE_ID" -o "$tmp/booking-widget"
  grep -Fq 'ebusy.createBookingView' "$tmp/booking-widget"
  grep -Fq 'https://cd-ebusy.ntc93.de/embed/lite-module/1731' "$tmp/booking-widget"
  php -l public/ntc/feedback.php
  upload public/ntc/feedback.php feedback.php
  curl --fail --silent --show-error --max-time 25 "https://$DOMAIN/feedback.php?release=$RELEASE_ID" -o "$tmp/feedback-form"
  grep -Fq 'Feedback senden' "$tmp/feedback-form"
  curl --fail --silent --show-error --max-time 25 "https://$DOMAIN/feedback.php?manage=1&release=$RELEASE_ID" -o "$tmp/feedback-admin"
  grep -Fq 'Admin-Anmeldung' "$tmp/feedback-admin"
  code="$(curl --silent --show-error --max-time 25 -o /dev/null -w '%{http_code}' "https://$DOMAIN/devices-private/feedback.sqlite")"
  [[ "$code" == 403 ]] || { echo 'Feedback storage not protected'; exit 1; }
  curl --fail --silent --show-error --max-time 25 "https://$DOMAIN/devices.php?status=1" -o "$tmp/devices-status"
  php -r '$v=json_decode(file_get_contents($argv[1]),true); if(($v["registered"]??null)!==false)exit(1); echo "Unregistered browser has no device authorization.\n";' "$tmp/devices-status"
  curl --fail --silent --show-error --max-time 25 "https://$DOMAIN/devices.php?manage=1" -o "$tmp/devices-login"
  grep -Fq 'Admin-Anmeldung' "$tmp/devices-login"
  grep -Fq 'Admin-PIN' "$tmp/devices-login"
  # CI runner is outside the club network and has no registered device cookie.
  code="$(curl --silent --show-error --max-time 25 -o "$tmp/ebusy-anonymous" -w '%{http_code}' "https://$DOMAIN/ebusy-occupancy.php")"
  [[ "$code" == 403 ]] || { echo "::error::Anonymous occupancy request not denied (HTTP $code)"; exit 1; }
  echo "Anonymous live occupancy request denied."

  php -l public/ntc/ntc-gateway.php
  php -l public/ntc/admin/index.php
  php -l public/ntc/spielpartner/index.php
  php -l public/ntc/spielpartner/pin-test.php
  php tests/pin-test.php
  upload public/ntc/spielpartner/pin-test.php spielpartner/pin-test.php
  curl --fail --silent --show-error --max-time 25 "https://$DOMAIN/spielpartner/pin-test.php" -o "$tmp/pin-pilot"
  grep -Fq 'PIN-Abgleich testen' "$tmp/pin-pilot"
  grep -Fq 'Diagnoseversion 0.5.18' "$tmp/pin-pilot"
  grep -Fq 'Namensverzeichnis importieren' "$tmp/pin-pilot"
  grep -Fq 'API-Module anzeigen' "$tmp/pin-pilot"
  code="$(curl --silent --show-error --max-time 25 -o /dev/null -w '%{http_code}' --data 'action=test' "https://$DOMAIN/spielpartner/pin-test.php")"
  [[ "$code" == 403 ]] || { echo 'PIN pilot missing CSRF protection'; exit 1; }
  code="$(curl --silent --show-error --max-time 25 -o /dev/null -w '%{http_code}' "https://$DOMAIN/devices-private/pin-test.sqlite")"
  [[ "$code" == 403 ]] || { echo 'PIN pilot storage not protected'; exit 1; }
  upload public/ntc/ntc-gateway.php ntc-gateway.php
  for page in admin spielpartner; do
    upload public/admin/admin.css "$page/admin.css"
    verify public/admin/admin.css "$page/admin.css"
    upload "public/ntc/$page/index.php" "$page/index.php"
  done
  for asset in player.js player.css; do
    upload "public/admin/$asset" "admin/$asset"
    verify "public/admin/$asset" "admin/$asset"
  done
  # Do not publish the new QR until both public entrypoints are verified.
  curl --fail --silent --show-error --max-time 55 "https://$DOMAIN/admin/?release=$RELEASE_ID" -o "$tmp/ntc-admin"
  grep -Fq 'Vereinsadmin-Anmeldung' "$tmp/ntc-admin"
  ! grep -Fq 'TENNILO · Kundenverwaltung' "$tmp/ntc-admin"
  code="$(curl --silent --show-error --max-time 55 -w '%{http_code}' "https://$DOMAIN/spielpartner/?release=$RELEASE_ID" -o "$tmp/ntc-partner")"
  if [[ "$code" == 200 ]]; then
    grep -Fq 'Spielpartner gesucht' "$tmp/ntc-partner"
    # Verify the actual smartphone form through the NTC gateway.
    # Backend and public-display workflows run independently. Give the checked
    # central deployment time to finish; never silently accept an obsolete form.
    ready=0
    for attempt in {1..24}; do
      if curl --fail --silent --show-error --max-time 12 "https://$DOMAIN/spielpartner/?mode=post&release=$RELEASE_ID" -o "$tmp/ntc-partner-create"; then
        missing=()
        for marker in 'name="availability[]"' 'name="period[]"' 'name="age_primary"' 'name="age_wanted[]"' 'name="email"'; do
          grep -Fq "$marker" "$tmp/ntc-partner-create" || missing+=("$marker")
        done
        if [[ ${#missing[@]} == 0 ]]; then ready=1; break; fi
        echo "Waiting for updated central partner form ($attempt/24); missing fields: ${missing[*]}"
      else
        echo "Waiting for central partner form ($attempt/24); HTTP check failed."
      fi
      if [[ "$attempt" != 24 ]]; then sleep 10; fi
    done
    [[ "$ready" == 1 ]] || { echo "::error::Central admin backend has not published the required smartphone fields. Check deploy-admin.yml."; exit 1; }
    echo "NTC smartphone form fields verified."
    # Exercise POST routing with intentionally incomplete data: no real request or email.
    for origin in "https://$DOMAIN" null; do
      code="$(curl --silent --show-error --max-time 55 -w '%{http_code}' -H "Origin: $origin" -H 'Sec-Fetch-Site: same-origin' --data 'mode=post' "https://$DOMAIN/spielpartner/?partner=submit&club=ntc93&mode=post" -o "$tmp/ntc-partner-post")"
      [[ "$code" == 200 ]] || { echo "::error::NTC form POST rejected (HTTP $code, origin $origin)"; exit 1; }
      grep -Fq 'Bitte Altersgruppe, Mitgliedschaft und Einwilligung bestätigen.' "$tmp/ntc-partner-post" || { echo "::error::NTC form POST did not reach expected server validation"; exit 1; }
      echo "NTC form POST validation verified (origin $origin)."
    done
    code="$(curl --silent --show-error --max-time 55 -w '%{http_code}' -H 'Origin: null' -H 'Sec-Fetch-Site: cross-site' --data 'mode=post' "https://$DOMAIN/spielpartner/" -o "$tmp/ntc-partner-cross")"
    [[ "$code" == 403 ]] || { echo "::error::NTC cross-site POST did not return HTTP 403 (got $code)"; exit 1; }
  elif [[ "$code" == 404 ]]; then
    grep -Fq 'Spielpartnerbörse nicht freigeschaltet.' "$tmp/ntc-partner"
  else
    echo "::error::NTC partner gateway failed (HTTP $code)"; exit 1
  fi
fi
# Bake the checked Git commit into the HTML and cache-bust its script/style URLs.
build="$(git rev-parse HEAD)"
[[ "$build" =~ ^[0-9a-f]{40}$ ]] || exit 1
sed -E "s/__CLUBDISPLAY_BUILD__/$build/g; s/\?v=[0-9]+/?v=$build/g" public/index.html > "$tmp/index.html"
grep -Fq "name=\"clubdisplay-build\" content=\"$build\"" "$tmp/index.html"
for f in "${files[@]}"; do
  echo "Deploy: $f"
  src="public/$f"
  if [[ "$f" == "index.html" ]]; then src="$tmp/index.html"; fi
  upload "$src" "$f"
  verify "$src" "$f"
done
# Only advertise after all files have passed HTTPS verification.
printf '{"build":"%s"}\n' "$build" > "$tmp/release.json"
upload "$tmp/release.json" release.json
verify "$tmp/release.json" release.json
echo "Release verified: $DOMAIN"
