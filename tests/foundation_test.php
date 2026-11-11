<?php
declare(strict_types=1);
/** php tests/foundation_test.php  — needs a migrated DB configured in .env */
ob_start();
require __DIR__ . '/../includes/bootstrap.php';

$pass = 0; $fail = 0;
function t(string $name, bool $ok): void {
    global $pass, $fail; $ok ? $pass++ : $fail++;
    fwrite(STDERR, ($ok ? "  PASS " : "  FAIL ") . "$name\n");
}

db_exec("DELETE FROM login_attempts");

fwrite(STDERR, "Authentication\n");
$r = auth_attempt('customer', 'diwakar', 'root', '10.0.0.1');
t('legacy password still works after hashing',  is_array($r) && $r['role'] === 'customer');
t('session user populated',                     current_user()['id'] === 'diwakar');
$r = auth_attempt('admin', 'nikhil', 'root', '10.0.0.2');
t('admin (client) login works',                 is_array($r) && $r['role'] === 'admin');
t('role check: is_role(admin)',                 is_role('admin') && !is_role('customer'));
t('wrong password rejected',                    auth_attempt('customer', 'diwakar', 'nope', '10.0.0.3') === 'Invalid username or password.');
t('unknown user rejected (same message)',       auth_attempt('customer', 'ghost', 'root', '10.0.0.3') === 'Invalid username or password.');
t('customer cannot log in via admin table',     is_string(auth_attempt('admin', 'diwakar', 'root', '10.0.0.4')));
t('invalid role rejected',                      is_string(auth_attempt('superuser', 'x', 'y', '10.0.0.5')));

fwrite(STDERR, "SQL injection\n");
foreach (["' OR '1'='1", "admin'--", "x' UNION SELECT customer_password FROM customers-- ", "'; DROP TABLE customers;-- "] as $i => $payload) {
    $r = auth_attempt('customer', $payload, $payload, '10.1.0.' . $i);
    t("payload rejected: " . substr($payload, 0, 22), is_string($r));
}
t('customers table intact after attacks',       (int) db_value('SELECT COUNT(*) FROM customers') === 2);
t('"1 OR 1=1" is data, not SQL (1 row, not 12)', (int) db_value('SELECT COUNT(*) FROM cars WHERE car_id = ?', ['1 OR 1=1']) === 1);

fwrite(STDERR, "Brute-force throttle\n");
for ($i = 0; $i < LOGIN_MAX_ATTEMPTS; $i++) { auth_attempt('customer', 'rakshith', 'bad' . $i, '10.9.9.9'); }
$locked = auth_attempt('customer', 'rakshith', 'root', '10.9.9.9');
t('locked after 5 failures (even with correct pw)', is_string($locked) && str_contains($locked, 'Too many'));
t('other IP not locked out',                    is_array(auth_attempt('customer', 'rakshith', 'root', '10.8.8.8')));

fwrite(STDERR, "Hash upgrade / registration\n");
db_exec("UPDATE customers SET customer_password = ? WHERE customer_username = 'diwakar'", [password_hash('root', PASSWORD_BCRYPT, ['cost' => 4])]);
auth_attempt('customer', 'diwakar', 'root', '10.2.0.1');
$cost = password_get_info((string) db_value("SELECT customer_password FROM customers WHERE customer_username='diwakar'"))['options']['cost'];
t('weak-cost hash upgraded on login',           $cost >= 10);

fwrite(STDERR, "CSRF\n");
$tok = csrf_token();
t('valid token accepted',                       csrf_valid($tok));
t('missing token rejected',                     !csrf_valid(null));
t('wrong token rejected',                       !csrf_valid(str_repeat('a', 64)));
t('csrf_field escapes & embeds token',          str_contains(csrf_field(), $tok));

fwrite(STDERR, "Validation\n");
[$err, $c] = validate(['name' => ' Asha ', 'email' => 'bad', 'phone' => '98765', 'pw' => 'abc'],
    ['name' => 'required|min:2', 'email' => 'required|email', 'phone' => 'required|phone', 'pw' => 'required|password']);
t('trims input',                                $c['name'] === 'Asha');
t('flags bad email, phone, password',           isset($err['email'], $err['phone'], $err['pw']) && !isset($err['name']));
[$err] = validate(['d' => '2026-02-30', 't' => '25:00'], ['d' => 'required|date', 't' => 'required|time']);
t('rejects impossible date and time',           isset($err['d'], $err['t']));
[$err] = validate(['d' => '2026-10-06', 't' => '09:30', 'u' => 'asha_k', 'k' => 'ac'], ['d' => 'date', 't' => 'time', 'u' => 'username', 'k' => 'in:ac,non_ac']);
t('accepts valid values',                       $err === []);

fwrite(STDERR, "Helpers\n");
t('e() escapes XSS',                            e('<script>"x"</script>') === '&lt;script&gt;&quot;x&quot;&lt;/script&gt;');
t('rental_days min 1 (same-day)',               rental_days('2026-10-06', '2026-10-06') === 1);
t('rental_days multi-day',                      rental_days('2026-10-06', '2026-10-09') === 3);
t('money format',                               money(1234.5) === '₹1,234.50');

logout();
t('logout clears user',                         current_user() === null);
db_exec("DELETE FROM login_attempts");

fwrite(STDERR, "\n$pass passed, $fail failed\n");
exit($fail === 0 ? 0 : 1);
