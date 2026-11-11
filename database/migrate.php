<?php
declare(strict_types=1);
/**
 * DriveEase migration: upgrades the ORIGINAL Patna-Car-Rental schema in place.
 *   php database/migrate.php
 * Additive and idempotent: safe to run repeatedly. Never drops tables or data.
 * Back up first:  mysqldump carrental > backup.sql
 */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

require __DIR__ . '/../config/config.php';
require __DIR__ . '/../includes/db.php';

$log = fn(string $m) => print("  $m\n");

function col_exists(string $t, string $c): bool {
    return (int) db_value('SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?', [$t, $c]) > 0;
}
function add_col(string $t, string $c, string $def, callable $log): void {
    if (!col_exists($t, $c)) { db()->query("ALTER TABLE `$t` ADD COLUMN `$c` $def"); $log("+ $t.$c"); }
}

echo "DriveEase migration\n";

// ---- 1. Credentials: widen columns, then hash legacy plaintext ----------
echo "[1] Accounts\n";
db()->query("ALTER TABLE customers MODIFY customer_password VARCHAR(255) NOT NULL, MODIFY customer_email VARCHAR(100) NOT NULL, MODIFY customer_name VARCHAR(100) NOT NULL, MODIFY customer_address VARCHAR(255) NOT NULL");
db()->query("ALTER TABLE clients   MODIFY client_password   VARCHAR(255) NOT NULL, MODIFY client_email   VARCHAR(100) NOT NULL, MODIFY client_name   VARCHAR(100) NOT NULL, MODIFY client_address   VARCHAR(255) NOT NULL");
add_col('customers', 'created_at', 'DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP', $log);
add_col('clients',   'created_at', 'DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP', $log);

$hashed = 0;
foreach ([['customers', 'customer_username', 'customer_password'], ['clients', 'client_username', 'client_password']] as [$t, $id, $pw]) {
    foreach (db_all("SELECT $id AS id, $pw AS pw FROM $t") as $r) {
        if (!preg_match('/^\$(2y|argon2id?)\$/', $r['pw'])) {
            db_exec("UPDATE $t SET $pw = ? WHERE $id = ?", [password_hash($r['pw'], PASSWORD_DEFAULT), $r['id']]);
            $hashed++;
        }
    }
}
$log("password hashes upgraded: $hashed");

// ---- 2. Vehicles ---------------------------------------------------------
echo "[2] Vehicles\n";
add_col('cars', 'category',     'VARCHAR(30) NULL', $log);
add_col('cars', 'seats',        'TINYINT NOT NULL DEFAULT 5', $log);
add_col('cars', 'transmission', "VARCHAR(15) NOT NULL DEFAULT 'Manual'", $log);
add_col('cars', 'fuel_type',    "VARCHAR(15) NOT NULL DEFAULT 'Petrol'", $log);
add_col('cars', 'description',  'TEXT NULL', $log);
add_col('cars', 'is_active',    'TINYINT(1) NOT NULL DEFAULT 1', $log);
db()->query("UPDATE cars SET category = CASE
    WHEN car_name IN ('Wagon R','Alto 800','Ford Figo') THEN 'Hatchback'
    WHEN car_name IN ('Honda Amaze','Swift Dzire','Suzuki Ciaz') THEN 'Sedan'
    WHEN car_name IN ('Innova','Suzuki Ertiga') THEN 'MPV'
    ELSE 'SUV' END WHERE category IS NULL");
db()->query("UPDATE cars SET seats = 7 WHERE car_name IN ('Innova','Suzuki Ertiga','Mahindra Bolero','Mahindra XUV 500','Toyota Fortuner') AND seats = 5");
db()->query("UPDATE cars SET fuel_type = 'Diesel' WHERE car_name IN ('Innova','Mahindra Bolero','Mahindra XUV 500','Toyota Fortuner') AND fuel_type = 'Petrol'");

// ---- 3. Drivers / feedback ----------------------------------------------
echo "[3] Drivers & feedback\n";
db()->query("ALTER TABLE cars MODIFY car_img VARCHAR(255) NULL DEFAULT NULL");
db()->query("ALTER TABLE feedback MODIFY name VARCHAR(100) NOT NULL, MODIFY e_mail VARCHAR(100) NOT NULL, MODIFY message TEXT NOT NULL");
db()->query("UPDATE cars SET car_img = NULL WHERE car_img = 'NA'");
add_col('driver', 'is_active', 'TINYINT(1) NOT NULL DEFAULT 1', $log);
if (!col_exists('feedback', 'id')) {
    db()->query('ALTER TABLE feedback ADD COLUMN id INT NOT NULL AUTO_INCREMENT PRIMARY KEY FIRST'); $log('+ feedback.id');
}
add_col('feedback', 'created_at', 'DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP', $log);

// ---- 4. Bookings ---------------------------------------------------------
echo "[4] Bookings\n";
add_col('rentedcars', 'pickup_time',    "TIME NOT NULL DEFAULT '09:00:00'", $log);
add_col('rentedcars', 'return_time',    "TIME NOT NULL DEFAULT '09:00:00'", $log);
add_col('rentedcars', 'ac_type',        "VARCHAR(10) NOT NULL DEFAULT 'ac'", $log);
add_col('rentedcars', 'booking_status', "VARCHAR(15) NOT NULL DEFAULT 'confirmed'", $log);
add_col('rentedcars', 'payment_status', "VARCHAR(15) NOT NULL DEFAULT 'unpaid'", $log);
add_col('rentedcars', 'amount_paid',    'DECIMAL(12,2) NOT NULL DEFAULT 0', $log);
add_col('rentedcars', 'estimated_total','DECIMAL(12,2) NULL', $log);
add_col('rentedcars', 'late_fee',       'DECIMAL(12,2) NOT NULL DEFAULT 0', $log);
add_col('rentedcars', 'cancelled_at',   'DATETIME NULL', $log);
add_col('rentedcars', 'cancel_reason',  'VARCHAR(255) NULL', $log);

// Backfill once: only rows still at defaults AND never touched by new code.
if (!col_exists('rentedcars', '_migrated')) {
    db()->query("UPDATE rentedcars rc JOIN cars c ON c.car_id = rc.car_id
                 SET rc.ac_type = IF(rc.fare IN (c.non_ac_price, c.non_ac_price_per_day), 'non_ac', 'ac')");
    db()->query("UPDATE rentedcars SET booking_status = 'completed', payment_status = 'paid', amount_paid = COALESCE(total_amount,0) WHERE return_status = 'R'");
    db()->query("UPDATE rentedcars SET booking_status = 'confirmed' WHERE return_status = 'NR'");
    db()->query('ALTER TABLE rentedcars ADD COLUMN `_migrated` TINYINT(1) NOT NULL DEFAULT 1');
    $log('legacy bookings backfilled (returned => completed + paid)');
}
// Check each index independently so a partially applied migration can recover.
$carDatesIndex = (int) db_value("SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME='rentedcars' AND INDEX_NAME='idx_car_dates'");
if (!$carDatesIndex) {
    db()->query('ALTER TABLE rentedcars ADD INDEX idx_car_dates (car_id, rent_start_date, rent_end_date)');
    $log('+ index idx_car_dates');
}
$statusIndex = (int) db_value("SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME='rentedcars' AND INDEX_NAME='idx_status'");
if (!$statusIndex) {
    db()->query('ALTER TABLE rentedcars ADD INDEX idx_status (booking_status)');
    $log('+ index idx_status');
}

// ---- 5. New tables -------------------------------------------------------
echo "[5] New tables\n";
db()->query("CREATE TABLE IF NOT EXISTS payments (
    id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    booking_id INT NOT NULL,
    amount DECIMAL(12,2) NOT NULL,
    method VARCHAR(20) NOT NULL DEFAULT 'cash',
    reference VARCHAR(80) NULL,
    status VARCHAR(15) NOT NULL DEFAULT 'success',
    recorded_by VARCHAR(50) NULL,
    paid_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_booking (booking_id),
    CONSTRAINT fk_pay_booking FOREIGN KEY (booking_id) REFERENCES rentedcars(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
db()->query("CREATE TABLE IF NOT EXISTS login_attempts (
    id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    ident VARCHAR(120) NOT NULL,
    ip VARCHAR(45) NOT NULL,
    attempted_at DATETIME NOT NULL,
    INDEX idx_ident (ident, ip, attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// Ledger rows for settled legacy bookings (once per booking).
$n = db_exec("INSERT INTO payments (booking_id, amount, method, reference, paid_at)
    SELECT rc.id, rc.total_amount, 'legacy', 'Imported from original system', COALESCE(rc.car_return_date, rc.booking_date)
    FROM rentedcars rc WHERE rc.payment_status = 'paid' AND rc.total_amount > 0
      AND NOT EXISTS (SELECT 1 FROM payments p WHERE p.booking_id = rc.id)");
$log("ledger rows created: $n");

echo "Done.\n";
