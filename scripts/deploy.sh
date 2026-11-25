#!/usr/bin/env bash
# TENNILO ClubDisplay | Copyright © 2026 TENNILO | Alle Rechte vorbehalten.
set -euo pipefail
: "${FTP_HOST:?FTP host missing}" "${FTP_USER:?FTP user missing}" "${FTP_PASSWORD:?FTP password missing}" "${DOMAIN:?Domain missing}" "${RELEASE_ID:?Release ID missing}"
case "$DOMAIN" in clubdisplay.tennilo.de|clubdisplay.ntc93.de) ;; *) exit 1;; esac
[[ "$FTP_HOST" =~ ^[A-Za-z0-9.-]+$ ]] || { echo 'FTP host must be a hostname'; exit 1; }
node --check public/assets/app.js
files=(assets/clubdisplay.png assets/favicon.png assets/app.css assets/app.js index.html)
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
for f in "${files[@]}"; do
  echo "Deploy: $f"
  upload "public/$f" "$f"
  verify "public/$f" "$f"
done
echo "Release verified: $DOMAIN"
