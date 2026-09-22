<?php
declare(strict_types=1);

/** HTML-escape output (defense against XSS). Use on every dynamic echo. */
function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Generate (or reuse) the CSRF token for this session. */
function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/** Output a hidden CSRF input field. */
function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

/** Verify a submitted CSRF token; terminates the request on mismatch. */
function csrf_verify(): void
{
    $submitted = $_POST['csrf_token'] ?? '';
    if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], (string)$submitted)) {
        http_response_code(403);
        die('رمز الأمان (CSRF) غير صالح أو منتهي الصلاحية. يرجى الرجوع والمحاولة مرة أخرى.');
    }
}

/** Redirect and stop execution. Always use relative app paths. */
function redirect(string $path): never
{
    header('Location: ' . $path);
    exit;
}

/** Queue a one-time flash message shown on the next page load. */
function flash(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

/** Pop and return all queued flash messages. */
function get_flashes(): array
{
    $flashes = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $flashes;
}

/** Format a decimal amount as money, e.g. 1234.5 -> "1,234.50" */
function format_money($amount, string $currency = ''): string
{
    $formatted = number_format((float)$amount, 2);
    return $currency !== '' ? "$formatted $currency" : $formatted;
}

/** Gregorian date, day/month/year — the conventional format for Arabic business users. */
function format_date(?string $date): string
{
    if (!$date) return '';
    $ts = strtotime($date);
    return $ts ? date('Y/m/d', $ts) : '';
}

function format_datetime(?string $date): string
{
    if (!$date) return '';
    $ts = strtotime($date);
    return $ts ? date('Y/m/d - H:i', $ts) : '';
}

/** Repopulate a form field with a previously submitted (old) value. */
function old(string $key, $default = '')
{
    return $_SESSION['old'][$key] ?? $default;
}

function keep_old(array $data): void
{
    $_SESSION['old'] = $data;
}

function clear_old(): void
{
    unset($_SESSION['old']);
}

/** Basic sanitisation for plain string inputs (trim; null bytes stripped). */
function clean_str(?string $value): string
{
    return trim(str_replace("\0", '', $value ?? ''));
}

/** Cast to float safely for financial inputs. */
function to_money(mixed $value): float
{
    if (is_string($value)) {
        $value = str_replace(',', '', $value);
    }
    return round((float)$value, 2);
}

/**
 * Map invoice status to a Bootstrap badge class + Arabic display label.
 * NOTE: the internal status VALUE stored in the database (draft, issued,
 * partially_paid, paid, overdue, cancelled) is never translated — only the
 * label shown to the user is Arabic. Business logic keys stay in English.
 */
function invoice_status_badge(string $status): array
{
    return match ($status) {
        'draft'           => ['secondary', 'مسودة'],
        'issued'          => ['primary', 'صادرة'],
        'partially_paid'  => ['warning', 'مدفوعة جزئيًا'],
        'paid'            => ['success', 'مدفوعة'],
        'overdue'         => ['danger', 'متأخرة'],
        'cancelled'       => ['dark', 'ملغاة'],
        default           => ['secondary', $status],
    };
}

function payment_method_label(string $method): string
{
    return match ($method) {
        'cash' => 'نقدًا',
        'bank_transfer' => 'تحويل بنكي',
        'cheque' => 'شيك',
        'card' => 'بطاقة',
        default => 'أخرى',
    };
}

/** Arabic label for a role name (internal role name stays in English in the DB). */
function role_label(string $role): string
{
    return match ($role) {
        'Admin' => 'مدير النظام',
        'Finance' => 'مالية',
        'Staff' => 'موظف',
        default => $role,
    };
}

/** Arabic label for account status. */
function account_status_label(string $status): string
{
    return $status === 'active' ? 'نشط' : 'غير نشط';
}

/** Get current authenticated user's IP (best-effort, for audit logs). */
function client_ip(): string
{
    return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
}

/** Build a query string preserving current GET params but overriding some. */
function build_query(array $overrides): string
{
    $params = array_merge($_GET, $overrides);
    foreach ($params as $k => $v) {
        if ($v === null || $v === '') unset($params[$k]);
    }
    return '?' . http_build_query($params);
}

function base_path(string $path = ''): string
{
    return rtrim(APP_URL, '/') . '/' . ltrim($path, '/');
}
