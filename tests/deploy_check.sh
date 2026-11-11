#!/usr/bin/env bash
# DriveEase deployment check. Run it from any machine against your live/staging URL.
#   ./tests/deploy_check.sh http://localhost/driveease/
#   ./tests/deploy_check.sh https://rent.example.com/
# Optional login checks (use a real customer account):
#   DE_USER=diwakar DE_PASS=secret ./tests/deploy_check.sh https://rent.example.com/
# Needs: bash + curl (Git Bash on Windows is fine). Read-only apart from one login/logout.
URL="${1:-}"; [ -z "$URL" ] && { echo "usage: $0 <base-url>  (e.g. https://rent.example.com/)"; exit 2; }
URL="${URL%/}/"; ORIGIN=$(echo "$URL" | sed -E 's#^(https?://[^/]+).*#\1#'); APP_PATH="${URL#$ORIGIN}"
PASS=0; FAIL=0; WARN=0
ok(){   PASS=$((PASS+1)); echo "  [ OK ] $1"; }
bad(){  FAIL=$((FAIL+1)); echo "  [FAIL] $1${2:+  -> $2}"; }
warn(){ WARN=$((WARN+1)); echo "  [WARN] $1${2:+  -> $2}"; }
chk(){ if [ "$2" = 1 ]; then ok "$1"; else bad "$1" "$3"; fi; }
code(){ curl -s -o /dev/null -m 20 -w '%{http_code}' "$@"; }
tok(){ grep -o 'name="_csrf" value="[a-f0-9]*"' | head -1 | sed 's/.*value="//;s/"//'; }

echo "Checking $URL"
echo; echo "1. Application"
H=$(curl -s -m 20 -L "$URL")
[ -z "$H" ] && { bad "site did not respond" "is the URL right and the server running?"; exit 1; }
chk "home page renders"                     $(echo "$H" | grep -q "DriveEase\|Book the right car" && echo 1 || echo 0) "got something else - wrong folder/DocumentRoot?"
echo "$H" | grep -qiE "Fatal error|Warning:|Notice:|Stack trace|mysqli_sql_exception" && bad "PHP errors visible on the page" "set APP_ENV=production" || ok "no PHP errors shown to visitors"
BAD=0; N=0
for u in $(echo "$H" | grep -oE '(href|src)="[^"#]+"' | sed -E 's/^(href|src)="//;s/"$//' | grep -vE '^(https?:|mailto:|data:)' | sort -u); do
  case "$u" in /*) full="$ORIGIN$u";; *) full="$URL$u";; esac
  case "$u" in *.css|*.js|*.png|*.jpg|*.ico) N=$((N+1)); c=$(code "$full"); [ "$c" = 200 ] || { BAD=$((BAD+1)); echo "         asset $c: $u"; };; esac
done
chk "all $N CSS/JS/image files load"        $([ $BAD = 0 ] && echo 1 || echo 0) "$BAD missing - DocumentRoot is probably not 'public/'"
C=$(curl -s -m 20 "${URL}cars.php")
chk "database connected (cars listed)"      $(echo "$C" | grep -q 'car-card' && echo 1 || echo 0) "check DB_* in .env and that the data was imported/migrated"
chk "car page and 404 handling"             $([ "$(code "${URL}car.php?id=999999")" = 404 ] && echo 1 || echo 0) "expected 404 for a missing car"

echo; echo "2. Secrets and internals must NOT be readable"
LEAK=0
for s in .env config/config.php includes/db.php includes/security.php database/carrental.sql database/migrate.php database/user.php tests/foundation_test.php tests/deploy_check.sh .git/config .gitignore composer.json; do
  for pre in "" "public/"; do
    body=$(curl -s -m 20 "$URL$pre$s"); c=$(code "$URL$pre$s")
    if echo "$body" | grep -qE "DB_PASS|DB_USER|function db_|CREATE TABLE|password_hash\(|\[core\]|repositoryformatversion"; then
      bad "EXPOSED: $URL$pre$s (HTTP $c)" "move the web root to public/ or fix .htaccess immediately"; LEAK=1
    fi
  done
done
[ $LEAK = 0 ] && ok "no secret/source files are downloadable"
curl -s -m 20 "${URL}assets/img/" | grep -qi "Index of" && bad "directory listing is ON" "set 'Options -Indexes'" || ok "directory listing is off"

echo; echo "3. Headers and cookies"
HD=$(curl -sI -m 20 "$URL")
for h in X-Frame-Options X-Content-Type-Options Referrer-Policy; do echo "$HD" | grep -qi "^$h:" && ok "$h header set" || warn "$h header missing" "is includes/bootstrap.php loaded?"; done
echo "$HD" | grep -qi "^X-Powered-By:" && warn "X-Powered-By reveals PHP version" "set expose_php=Off" || ok "PHP version not advertised"
CK=$(echo "$HD" | grep -i "^set-cookie:" | head -1)
if [ -n "$CK" ]; then
  echo "$CK" | grep -qi "httponly" && ok "session cookie is HttpOnly" || bad "session cookie lacks HttpOnly"
  echo "$CK" | grep -qi "samesite" && ok "session cookie has SameSite" || warn "session cookie lacks SameSite"
fi
case "$URL" in
  https://*)
    echo "$CK" | grep -qi "secure" && ok "session cookie is Secure" || bad "session cookie is not Secure" "behind a proxy? it must send X-Forwarded-Proto: https"
    echo "$HD" | grep -qi "^Strict-Transport-Security:" && ok "HSTS enabled" || warn "HSTS not enabled" "recommended once HTTPS works everywhere"
    HP=$(echo "$URL" | sed 's#^https:#http:#'); L=$(curl -s -m 20 -o /dev/null -w '%{http_code} %{redirect_url}' "$HP")
    echo "$L" | grep -qE "^30[1278] https://" && ok "http:// redirects to https://" || warn "http:// does not redirect to https://" "$L";;
  *) warn "not using HTTPS" "fine for local testing; required for any real site";;
esac

echo; echo "4. Login flow (optional)"
if [ -n "${DE_USER:-}" ] && [ -n "${DE_PASS:-}" ]; then
  J=$(mktemp); T=$(curl -s -m 20 -c $J -b $J "${URL}login.php" | tok)
  chk "login form has a CSRF token"          $([ -n "$T" ] && echo 1 || echo 0)
  chk "POST without CSRF is rejected (403)"  $([ "$(code -c $J -b $J -d "username=$DE_USER&password=x" "${URL}login.php")" = 403 ] && echo 1 || echo 0)
  R=$(curl -s -m 20 -L -c $J -b $J --data-urlencode "username=$DE_USER" --data-urlencode "password=$DE_PASS" -d "_csrf=$T&role=customer" "${URL}login.php")
  chk "login succeeds and shows the user"    $(echo "$R" | grep -q ">customer<" && echo 1 || echo 0) "wrong credentials, or sessions are not saving (check session.save_path)"
  T2=$(echo "$R" | tok); L=$(curl -s -m 20 -L -c $J -b $J -d "_csrf=$T2" "${URL}logout.php")
  chk "logout returns to guest"              $(echo "$L" | grep -q "Sign up" && echo 1 || echo 0)
  rm -f $J
else
  echo "  (skipped - set DE_USER and DE_PASS to a customer account to test sign-in)"
fi

echo; echo "Result: $PASS ok, $WARN warning(s), $FAIL failed"
[ $FAIL = 0 ]
