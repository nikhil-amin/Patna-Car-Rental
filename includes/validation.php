<?php
declare(strict_types=1);

/**
 * validate($_POST, ['name' => 'required|min:2|max:50', 'email' => 'required|email', ...])
 * Returns [errors[], clean[]]. Strings are trimmed; unknown fields are dropped.
 * Rules: required, min:n, max:n, email, phone, username, int, numeric, positive,
 *        date, time, in:a,b,c, password
 */
function validate(array $input, array $rules): array
{
    $errors = [];
    $clean = [];
    foreach ($rules as $field => $ruleStr) {
        $raw = $input[$field] ?? '';
        $value = is_string($raw) ? trim($raw) : '';
        $label = ucfirst(str_replace('_', ' ', $field));
        $ruleList = explode('|', $ruleStr);
        $required = in_array('required', $ruleList, true);

        if ($value === '') {
            if ($required) {
                $errors[$field] = "$label is required.";
            }
            $clean[$field] = '';
            continue;
        }
        foreach ($ruleList as $rule) {
            [$name, $arg] = array_pad(explode(':', $rule, 2), 2, null);
            $bad = match ($name) {
                'min'      => mb_strlen($value) < (int) $arg ? "$label must be at least $arg characters." : null,
                'max'      => mb_strlen($value) > (int) $arg ? "$label must be at most $arg characters." : null,
                'email'    => filter_var($value, FILTER_VALIDATE_EMAIL) ? null : 'Enter a valid email address.',
                'phone'    => preg_match('/^[+]?[0-9]{10,15}$/', $value) ? null : 'Enter a valid phone number (10-15 digits).',
                'username' => preg_match('/^[A-Za-z0-9_.-]{3,30}$/', $value) ? null : 'Username must be 3-30 letters, numbers, . _ -',
                'int'      => preg_match('/^-?\d+$/', $value) ? null : "$label must be a whole number.",
                'numeric'  => is_numeric($value) ? null : "$label must be a number.",
                'positive' => (is_numeric($value) && (float) $value > 0) ? null : "$label must be greater than zero.",
                'date'     => _valid_date($value, 'Y-m-d') ? null : "$label must be a valid date.",
                'time'     => _valid_date($value, 'H:i') ? null : "$label must be a valid time.",
                'in'       => in_array($value, explode(',', (string) $arg), true) ? null : "$label has an invalid value.",
                'password' => (strlen($value) >= 8 && preg_match('/[A-Za-z]/', $value) && preg_match('/\d/', $value))
                                ? null : 'Password needs 8+ characters with a letter and a number.',
                default    => null,
            };
            if ($bad !== null) {
                $errors[$field] = $bad;
                break;
            }
        }
        $clean[$field] = $value;
    }
    return [$errors, $clean];
}

function _valid_date(string $v, string $format): bool
{
    $d = DateTimeImmutable::createFromFormat('!' . $format, $v);
    return $d !== false && $d->format($format) === $v;
}
