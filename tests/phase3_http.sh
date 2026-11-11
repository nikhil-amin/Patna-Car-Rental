#!/usr/bin/env bash
# Phase 3 HTTP tests. Needs: migrated DB in .env, mysql client, curl. Starts its own server.
cd "$(dirname "$0")/.."
PORT=8099; B=http://127.0.0.1:$PORT; PASS=0; FAIL=0
php -S 127.0.0.1:$PORT -t public >/tmp/php_t.log 2>&1 & SRV=$!
trap 'kill $SRV 2>/dev/null; mysql carrental -e "DELETE FROM rentedcars WHERE customer_username=\"diwakar\" AND booking_status=\"confirmed\" AND fare=-1; DELETE FROM customers WHERE customer_username IN (\"newuser1\"); DELETE FROM feedback WHERE name=\"Test Sender\"" 2>/dev/null' EXIT
sleep 1
# Use an ephemeral credential for the seeded test accounts instead of a shared default.
TEST_PASSWORD="DriveEase_$(php -r 'echo bin2hex(random_bytes(8));')_Test"
USER_PASSWORD="$TEST_PASSWORD" php database/user.php set-password customer diwakar >/dev/null
USER_PASSWORD="$TEST_PASSWORD" php database/user.php set-password admin nikhil >/dev/null
ok(){ if [ "$2" = "1" ]; then PASS=$((PASS+1)); echo "  PASS $1"; else FAIL=$((FAIL+1)); echo "  FAIL $1  [$3]"; fi; }
has(){ echo "$1" | grep -qF -- "$2" && echo 1 || echo 0; }
cnt(){ echo "$1" | grep -o "$2" | wc -l; }
tok(){ echo "$1" | grep -o 'name="_csrf" value="[a-f0-9]*"' | head -1 | sed 's/.*value="//;s/"//'; }
D1=$(date -d "+10 days" +%F); D2=$(date -d "+12 days" +%F); D3=$(date -d "+13 days" +%F); D4=$(date -d "+14 days" +%F); D0=$(date -d "+9 days" +%F); YEST=$(date -d "-1 day" +%F)
mysql carrental -e "INSERT INTO rentedcars (customer_username,car_id,driver_id,booking_date,rent_start_date,rent_end_date,fare,charge_type,return_status,booking_status) VALUES ('diwakar',1,1,CURDATE(),'$D1','$D2',-1,'days','NR','confirmed')"

echo "Public pages"
H=$(curl -s $B/index.php)
ok "home 200 + brand"             $(has "$H" "DriveEase")
ok "uses Bootstrap 5 (local)"     $(has "$H" "vendor/bootstrap/bootstrap.min.css")
ok "responsive viewport meta"     $(has "$H" 'name="viewport"')
ok "no legacy libs / secrets"     $([ "$(cnt "$H" 'w3.css\|angular\|jquery\|AIzaSy\|googleapis')" = 0 ] && echo 1 || echo 0)
ok "category tiles rendered"      $(has "$H" "Hatchback")
ok "featured cars (6)"            $([ "$(cnt "$H" 'class="card car-card')" = 6 ] && echo 1 || echo 0) "$(cnt "$H" 'class="card car-card')"
HDR=$(curl -sI $B/index.php)
ok "security headers sent"        $(has "$HDR" "X-Frame-Options")
ok "FAQ 200"                      $([ "$(curl -s -o /dev/null -w '%{http_code}' $B/faq.php)" = 200 ] && echo 1 || echo 0)

echo "Search & filters"
A=$(curl -s "$B/cars.php?per_page=50")
ok "lists cars (page 1 = 9)"      $([ "$(cnt "$A" 'class="card car-card')" = 9 ] && echo 1 || echo 0) "$(cnt "$A" 'class="card car-card')"
P2=$(curl -s "$B/cars.php?page=2"); ok "pagination page 2 = 3 cars" $([ "$(cnt "$P2" 'class="card car-card')" = 3 ] && echo 1 || echo 0)
S=$(curl -s "$B/cars.php?category=SUV"); ok "category=SUV -> 4"   $([ "$(cnt "$S" 'class="card car-card')" = 4 ] && echo 1 || echo 0)
M=$(curl -s "$B/cars.php?max_price=2000"); ok "max_price<=2000 -> 3" $([ "$(cnt "$M" 'class="card car-card')" = 3 ] && echo 1 || echo 0)
Q=$(curl -s "$B/cars.php?q=Innova"); ok "keyword Innova -> 1"     $([ "$(cnt "$Q" 'class="card car-card')" = 1 ] && echo 1 || echo 0)
F=$(curl -s "$B/cars.php?sort=price_asc" | grep -o '<h3 class="h5 mb-1">[^<]*' | head -1); ok "sort low->high starts Alto 800" $(has "$F" "Alto 800")
X=$(curl -s -G --data-urlencode "q=<script>alert(1)</script>" $B/cars.php); ok "XSS in query escaped" $([ "$(has "$X" '<script>alert(1)')" = 0 ] && [ "$(has "$X" '&lt;script&gt;')" = 1 ] && echo 1 || echo 0)
I=$(curl -s -o /dev/null -w '%{http_code}' -G --data-urlencode "q=' OR 1=1-- " $B/cars.php); ok "SQLi in query harmless (200)" $([ "$I" = 200 ] && echo 1 || echo 0) $I
W=$(curl -s -G --data-urlencode "q=%" $B/cars.php); ok "LIKE wildcard % treated literally -> 0 cars" $([ "$(cnt "$W" 'class="card car-card')" = 0 ] && echo 1 || echo 0)

echo "Availability (booking exists $D1 .. $D2 for Wagon R)"
av(){ curl -s "$B/cars.php?start=$1&end=$2&q=Wagon" | grep -o 'badge-status [^>]*>[A-Za-z]*' | sed 's/.*>//'; }
ok "overlap inside range -> Booked"        $([ "$(av $D1 $D2)" = Booked ] && echo 1 || echo 0)
ok "overlap on last day (inclusive)"       $([ "$(av $D2 $D3)" = Booked ] && echo 1 || echo 0) "$(av $D2 $D3)"
ok "overlap on first day (inclusive)"      $([ "$(av $D0 $D1)" = Booked ] && echo 1 || echo 0)
ok "day before -> Available"               $([ "$(av $D0 $D0)" = Available ] && echo 1 || echo 0)
ok "day after -> Available"                $([ "$(av $D3 $D4)" = Available ] && echo 1 || echo 0)
ok "range engulfing booking -> Booked"     $([ "$(av $D0 $D4)" = Booked ] && echo 1 || echo 0)
ok "past date rejected"                    $(has "$(curl -s "$B/cars.php?start=$YEST&end=$D1")" "cannot be in the past")
ok "end before start rejected"             $(has "$(curl -s "$B/cars.php?start=$D2&end=$D1")" "on or after the pickup")
ok "stale 2018 booking doesn't block Amaze" $([ "$(curl -s "$B/cars.php?q=Amaze" | grep -o 'badge-status [^>]*>[A-Za-z]*' | sed 's/.*>//')" = Available ] && echo 1 || echo 0)

echo "Car details"
C=$(curl -s "$B/car.php?id=1&start=$D1&end=$D2"); ok "detail: not available message" $(has "$C" "Not available for those dates")
ok "detail: book button disabled when unavailable" $(has "$C" "Unavailable for selected dates")
C=$(curl -s "$B/car.php?id=1&start=$D3&end=$D4"); ok "detail: available + book CTA" $([ "$(has "$C" "Available from")" = 1 ] && [ "$(has "$C" "Book this car")" = 1 ] && echo 1 || echo 0)
ok "detail: upcoming bookings listed"  $(has "$(curl -s "$B/car.php?id=1")" "$D1")
ok "detail id=999 -> 404"              $([ "$(curl -s -o /dev/null -w '%{http_code}' $B/car.php?id=999)" = 404 ] && echo 1 || echo 0)
ok "detail id=abc -> 404"              $([ "$(curl -s -o /dev/null -w '%{http_code}' $B/car.php?id=abc)" = 404 ] && echo 1 || echo 0)

echo "Auth flow"
J=/tmp/cj_t.txt; rm -f $J
L=$(curl -s -c $J -b $J $B/login.php); T=$(tok "$L")
ok "login page has CSRF token"         $([ -n "$T" ] && echo 1 || echo 0)
ok "POST without CSRF -> 403"          $([ "$(curl -s -o /dev/null -w '%{http_code}' -c $J -b $J -d "username=diwakar&password=$TEST_PASSWORD" $B/login.php)" = 403 ] && echo 1 || echo 0)
R=$(curl -s -c $J -b $J -d "_csrf=$T&role=customer&username=diwakar&password=wrong" $B/login.php); ok "wrong password -> error shown" $(has "$R" "Invalid username or password")
R=$(curl -s -c $J -b $J -d "_csrf=$T&role=admin&username=diwakar&password=$TEST_PASSWORD" $B/login.php); ok "customer can't use admin login"   $(has "$R" "Invalid username or password")
CODE=$(curl -s -c $J -b $J -o /dev/null -w '%{http_code}' -d "_csrf=$T&role=customer&username=diwakar&password=$TEST_PASSWORD" $B/login.php); ok "valid login -> 302" $([ "$CODE" = 302 ] && echo 1 || echo 0) $CODE
N=$(curl -s -c $J -b $J $B/index.php); ok "navbar shows user + role"  $([ "$(has "$N" "Diwakar Vignesh")" = 1 ] && [ "$(has "$N" ">customer<")" = 1 ] && echo 1 || echo 0)
ok "customer nav links present"        $(has "$N" "Active rentals")
ok "admin links hidden from customer"  $([ "$(has "$N" "Fleet")" = 0 ] && echo 1 || echo 0)
ok "legacy page still works (mybookings)" $([ "$(curl -s -b $J -o /dev/null -w '%{http_code}' $B/mybookings.php)" = 200 ] && echo 1 || echo 0)
ok "legacy booking page works"         $([ "$(curl -s -b $J -o /dev/null -w '%{http_code}' "$B/booking.php?id=3")" = 200 ] && echo 1 || echo 0)
T2=$(tok "$N"); ok "logout w/o CSRF -> 403" $([ "$(curl -s -o /dev/null -w '%{http_code}' -c $J -b $J -X POST $B/logout.php)" = 403 ] && echo 1 || echo 0)
curl -s -c $J -b $J -o /dev/null -d "_csrf=$T2" $B/logout.php
ok "after logout: guest nav"           $(has "$(curl -s -c $J -b $J $B/index.php)" "Sign up")
JA=/tmp/cj_a.txt; rm -f $JA; TA=$(tok "$(curl -s -c $JA -b $JA "$B/login.php?role=admin")")
curl -s -c $JA -b $JA -o /dev/null -d "_csrf=$TA&role=admin&username=nikhil&password=$TEST_PASSWORD" $B/login.php
NA=$(curl -s -c $JA -b $JA $B/index.php); ok "admin login shows fleet nav" $([ "$(has "$NA" "Fleet")" = 1 ] && [ "$(has "$NA" "Active rentals")" = 0 ] && echo 1 || echo 0)
ok "admin sees 'can't book' notice"    $(has "$(curl -s -b $JA "$B/car.php?id=2")" "Fleet owners can't book")

echo "Registration"
JR=/tmp/cj_r.txt; rm -f $JR; TR=$(tok "$(curl -s -c $JR -b $JR $B/register.php)")
reg(){ curl -s -c $JR -b $JR -d "_csrf=$TR&role=customer&name=$1&username=$2&email=$3&phone=$4&address=Pune&password=$5&confirm=$6" $B/register.php; }
ok "weak password rejected"            $(has "$(reg New+User newuser1 n@x.com 9876543210 abc abc)" "Password needs 8+")
ok "mismatched confirm rejected"       $(has "$(reg New+User newuser1 n@x.com 9876543210 Passw0rd1 Passw0rd2)" "do not match")
ok "bad email/phone rejected"          $([ "$(has "$(reg New+User newuser1 bad 123 Passw0rd1 Passw0rd1)" "valid email")" = 1 ] && echo 1 || echo 0)
ok "duplicate username rejected"       $(has "$(reg New+User diwakar n@x.com 9876543210 Passw0rd1 Passw0rd1)" "already taken")
ok "XSS in name is escaped on redisplay" $([ "$(has "$(reg '<b>x</b>' bad+name n@x.com 9876543210 Passw0rd1 Passw0rd1)" '<b>x</b>')" = 0 ] && echo 1 || echo 0)
CODE=$(curl -s -c $JR -b $JR -o /dev/null -w '%{http_code}' -d "_csrf=$TR&role=customer&name=New+User&username=newuser1&email=n@x.com&phone=9876543210&address=Pune&password=Passw0rd1&confirm=Passw0rd1" $B/register.php); ok "valid registration -> 302" $([ "$CODE" = 302 ] && echo 1 || echo 0) $CODE
ok "password stored as bcrypt hash"    $([ "$(mysql -N carrental -e "SELECT LEFT(customer_password,4) FROM customers WHERE customer_username='newuser1'")" = '$2y$' ] && echo 1 || echo 0)
JN=/tmp/cj_n.txt; rm -f $JN; TN=$(tok "$(curl -s -c $JN -b $JN $B/login.php)")
ok "new user can log in"               $([ "$(curl -s -c $JN -b $JN -o /dev/null -w '%{http_code}' -d "_csrf=$TN&role=customer&username=newuser1&password=Passw0rd1" $B/login.php)" = 302 ] && echo 1 || echo 0)

echo "Contact form & legacy stubs"
JC=/tmp/cj_c.txt; rm -f $JC; TC=$(tok "$(curl -s -c $JC -b $JC $B/index.php)")
ok "contact w/o CSRF -> 403"           $([ "$(curl -s -o /dev/null -w '%{http_code}' -d 'name=x&email=a@b.co&message=hello there' $B/contact.php)" = 403 ] && echo 1 || echo 0)
curl -s -c $JC -b $JC -o /dev/null --data-urlencode "name=Test Sender" -d "_csrf=$TC&email=a@b.co" --data-urlencode "message=Hello, \"quoted\" <b>msg</b> longer than the old 150 char limit ................................................................................................................................" $B/contact.php
ok "feedback saved (long msg OK)"      $([ "$(mysql -N carrental -e "SELECT COUNT(*) FROM feedback WHERE name='Test Sender' AND LENGTH(message)>150")" = 1 ] && echo 1 || echo 0)
ok "legacy customerlogin.php -> 301"   $([ "$(curl -s -o /dev/null -w '%{http_code}' $B/customerlogin.php)" = 301 ] && echo 1 || echo 0)
ok ".env not served by app routes"     $([ "$(curl -s -o /dev/null -w '%{http_code}' $B/../.env)" != 200 ] && echo 1 || echo 0)

echo "Hosting hardening"
CK_PLAIN=$(curl -sI $B/index.php | grep -i set-cookie | grep -ci secure)
CK_PROXY=$(curl -sI -H 'X-Forwarded-Proto: https' $B/index.php | grep -i set-cookie | grep -ci secure)
ok "session cookie not Secure over plain http"        $([ "$CK_PLAIN" = 0 ] && echo 1 || echo 0)
ok "session cookie Secure behind HTTPS proxy header"  $([ "$CK_PROXY" = 1 ] && echo 1 || echo 0)
ok "cookie is HttpOnly + SameSite"                    $(has "$(curl -sI $B/index.php | grep -i set-cookie)" "HttpOnly")
ALLOW_ADMIN_SIGNUP=false php -S 127.0.0.1:8098 -t public >/dev/null 2>&1 & S2=$!; sleep 1
R2=$(curl -s "http://127.0.0.1:8098/register.php?role=admin")
ok "ALLOW_ADMIN_SIGNUP=false hides fleet-owner tab"   $([ "$(echo "$R2" | grep -c 'role=admin')" = 0 ] && echo 1 || echo 0)
ok "ALLOW_ADMIN_SIGNUP=false: login page explains"    $(has "$(curl -s "http://127.0.0.1:8098/login.php?role=admin")" "created by the site administrator")
JF=/tmp/cj_f2.txt; rm -f $JF; TF=$(tok "$(curl -s -c $JF -b $JF "http://127.0.0.1:8098/register.php")")
curl -s -c $JF -b $JF -o /dev/null -d "_csrf=$TF&role=admin&name=Evil+Owner&username=evilowner9&email=e9@x.com&phone=9876543210&address=Nowhere&password=Passw0rd1&confirm=Passw0rd1" http://127.0.0.1:8098/register.php
ok "forged role=admin POST cannot create an admin"    $([ "$(mysql -N carrental -e "SELECT COUNT(*) FROM clients WHERE client_username='evilowner9'")" = 0 ] && echo 1 || echo 0)
mysql carrental -e "DELETE FROM customers WHERE customer_username='evilowner9'"
kill $S2 2>/dev/null

echo; echo "$PASS passed, $FAIL failed"; [ $FAIL = 0 ]
