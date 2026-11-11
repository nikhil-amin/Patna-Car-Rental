<?php
declare(strict_types=1);

/** HTML-escape for output. Use on EVERY dynamic value. */
function e(mixed $v): string
{
    return htmlspecialchars((string) $v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function money(float|int|string|null $amount): string
{
    return '₹' . number_format((float) $amount, 2);
}

function redirect(string $path): never
{
    header('Location: ' . $path);
    exit;
}

function is_post(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
}

/** One-shot messages shown via SweetAlert2 in the footer partial. */
function flash(string $type, string $message): void
{
    $_SESSION['_flash'][] = ['type' => $type, 'message' => $message];
}

function flash_pull(): array
{
    $f = $_SESSION['_flash'] ?? [];
    unset($_SESSION['_flash']);
    return $f;
}

/** Invoice number derived from the booking id (no extra column needed). */
function invoice_no(int $bookingId): string
{
    return sprintf('DE-%06d', $bookingId % 1000000);
}

/** Inclusive-of-start whole-day count between two Y-m-d dates, minimum 1. */
function rental_days(string $start, string $end): int
{
    $d = (new DateTimeImmutable($start))->diff(new DateTimeImmutable($end))->days;
    return max(1, (int) $d);
}

/** Base URL path of /public, so links work at the domain root or in a subfolder. */
function base_path(): string
{
    static $base = null;
    if ($base === null) {
        $pub  = realpath(__DIR__ . '/../public');
        $root = realpath((string) ($_SERVER['DOCUMENT_ROOT'] ?? ''));
        $base = ($root && $pub && str_starts_with($pub, $root)) ? str_replace('\\', '/', substr($pub, strlen($root))) : '';
    }
    return $base;
}

function url(string $path = ''): string
{
    return base_path() . '/' . ltrim($path, '/');
}

function asset(string $path): string
{
    return url('assets/' . ltrim($path, '/'));
}

/**
 * Use lightweight, externally hosted Unsplash photos instead of committing
 * large vehicle images to this repository. Image URLs are public and require
 * no API key. Keep filenames as keys to support existing database records.
 */
function car_image(?string $storedPath): string
{
    $file = strtolower(basename(trim((string) $storedPath)));
    $photos = [
        'wagon-r.png' => 'https://images.unsplash.com/photo-1552519507-da3b142c6e3d?auto=format&fit=crop&w=1000&q=80',
        'alto-800.png' => 'https://images.unsplash.com/photo-1503376780353-7e6692767b70?auto=format&fit=crop&w=1000&q=80',
        'innova.png' => 'https://images.unsplash.com/photo-1519641471654-76ce0107ad1b?auto=format&fit=crop&w=1000&q=80',
        'figo.png' => 'https://images.unsplash.com/photo-1492144534655-ae79c964c9d7?auto=format&fit=crop&w=1000&q=80',
        'bolero.jpg' => 'https://images.unsplash.com/photo-1519641471654-76ce0107ad1b?auto=format&fit=crop&w=1000&q=80',
        'amaze.png' => 'https://images.unsplash.com/photo-1549317661-bd32c8ce0db2?auto=format&fit=crop&w=1000&q=80',
        'nexon.jpg' => 'https://images.unsplash.com/photo-1502872293635-2f6e1f2e8e7a?auto=format&fit=crop&w=1000&q=80',
        'dzire.png' => 'https://images.unsplash.com/photo-1549317661-bd32c8ce0db2?auto=format&fit=crop&w=1000&q=80',
        'suzuki_ciaz_2017.jpg' => 'https://images.unsplash.com/photo-1492144534655-ae79c964c9d7?auto=format&fit=crop&w=1000&q=80',
        'mahindra xuv.jpg' => 'https://images.unsplash.com/photo-1519641471654-76ce0107ad1b?auto=format&fit=crop&w=1000&q=80',
        'fortuner.png' => 'https://images.unsplash.com/photo-1519641471654-76ce0107ad1b?auto=format&fit=crop&w=1000&q=80',
        'maruti-suzuki-ertiga.jpg' => 'https://images.unsplash.com/photo-1519641471654-76ce0107ad1b?auto=format&fit=crop&w=1000&q=80',
    ];
    return $photos[$file] ?? 'https://images.unsplash.com/photo-1492144534655-ae79c964c9d7?auto=format&fit=crop&w=1000&q=80';
}

/** True on HTTPS, including behind a TLS-terminating proxy/CDN (Cloudflare, load balancer). */
function is_https(): bool
{
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || ($_SERVER['SERVER_PORT'] ?? '') === '443'
        || strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';
}

/** Public fleet-owner (admin) registration switch. Default on = original behaviour. */
function admin_signup_enabled(): bool
{
    return filter_var(config('ALLOW_ADMIN_SIGNUP', 'false'), FILTER_VALIDATE_BOOLEAN);
}
