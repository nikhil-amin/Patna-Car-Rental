<?php
declare(strict_types=1);

const CAR_SORTS = [
    'price_asc'  => 'c.ac_price_per_day ASC',
    'price_desc' => 'c.ac_price_per_day DESC',
    'name'       => 'c.car_name ASC',
    'seats'      => 'c.seats DESC, c.car_name ASC',
];
const BLOCKING_STATUSES = "('confirmed','active')";

function car_categories(): array
{
    return db_all('SELECT category, COUNT(*) AS n FROM cars WHERE is_active = 1 AND category IS NOT NULL GROUP BY category ORDER BY category');
}

/**
 * Is the car free for [start, end] (Y-m-d, inclusive)? Based on real booking
 * date overlap, not the legacy yes/no flag, so future bookings work.
 */
function car_is_available(int $carId, string $start, string $end, ?int $ignoreBooking = null): bool
{
    $sql = 'SELECT COUNT(*) FROM rentedcars WHERE car_id = ? AND booking_status IN ' . BLOCKING_STATUSES
         . ' AND rent_start_date <= ? AND rent_end_date >= ?';
    $params = [$carId, $end, $start];
    if ($ignoreBooking !== null) {
        $sql .= ' AND id <> ?';
        $params[] = $ignoreBooking;
    }
    return (int) db_value($sql, $params) === 0;
}

/**
 * Search cars with filters. Returns ['rows' => [...], 'total' => n, 'pages' => n].
 * Filters: q, category, fuel, transmission, seats(min), max_price, sort, start, end, page, per_page
 */
function cars_search(array $f): array
{
    $where = ['c.is_active = 1'];
    $p = [];
    if (($f['q'] ?? '') !== '') {
        $where[] = '(c.car_name LIKE ? OR c.category LIKE ?)';
        $like = '%' . addcslashes($f['q'], '%_\\') . '%';
        array_push($p, $like, $like);
    }
    foreach (['category' => 'c.category', 'fuel' => 'c.fuel_type', 'transmission' => 'c.transmission'] as $k => $col) {
        if (($f[$k] ?? '') !== '') {
            $where[] = "$col = ?";
            $p[] = $f[$k];
        }
    }
    if ((int) ($f['seats'] ?? 0) > 0) {
        $where[] = 'c.seats >= ?';
        $p[] = (int) $f['seats'];
    }
    if ((float) ($f['max_price'] ?? 0) > 0) {
        $where[] = 'c.ac_price_per_day <= ?';
        $p[] = (float) $f['max_price'];
    }

    // Availability for the chosen dates (or today when none chosen).
    $start = $f['start'] ?? '';
    $end   = $f['end'] ?? '';
    $hasDates = $start !== '' && $end !== '';
    $a = $hasDates ? $start : date('Y-m-d');
    $b = $hasDates ? $end : date('Y-m-d');
    $busy = 'EXISTS (SELECT 1 FROM rentedcars r WHERE r.car_id = c.car_id AND r.booking_status IN ' . BLOCKING_STATUSES
          . ' AND r.rent_start_date <= ? AND r.rent_end_date >= ?)';

    $order = CAR_SORTS[$f['sort'] ?? ''] ?? CAR_SORTS['price_asc'];
    $per = max(1, min(24, (int) ($f['per_page'] ?? 9)));
    $page = max(1, (int) ($f['page'] ?? 1));

    $whereSql = implode(' AND ', $where);
    $total = (int) db_value("SELECT COUNT(*) FROM cars c WHERE $whereSql", $p);
    $pages = max(1, (int) ceil($total / $per));
    $page = min($page, $pages);

    $rows = db_all(
        "SELECT c.*, NOT $busy AS is_free FROM cars c WHERE $whereSql ORDER BY $order LIMIT $per OFFSET " . (($page - 1) * $per),
        array_merge([$b, $a], $p)
    );
    foreach ($rows as &$r) {
        $r['is_free'] = (bool) $r['is_free'];
    }
    return ['rows' => $rows, 'total' => $total, 'pages' => $pages, 'page' => $page, 'has_dates' => $hasDates];
}

function car_find(int $id): ?array
{
    return db_one('SELECT * FROM cars WHERE car_id = ? AND is_active = 1', [$id]);
}

/** Validate a start/end pair from user input. Returns [error|null, start, end]. */
function validate_date_range(string $start, string $end): array
{
    [$err, $c] = validate(['start' => $start, 'end' => $end], ['start' => 'required|date', 'end' => 'required|date']);
    if ($err) {
        return [implode(' ', $err), '', ''];
    }
    if ($c['start'] < date('Y-m-d')) {
        return ['Pickup date cannot be in the past.', '', ''];
    }
    if ($c['end'] < $c['start']) {
        return ['Return date must be on or after the pickup date.', '', ''];
    }
    return [null, $c['start'], $c['end']];
}
