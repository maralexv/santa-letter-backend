<?php
declare(strict_types=1);

/**
 * Shared setup for every page and API endpoint: config, database, session, helpers.
 * Written to run on PHP 7.4+.
 *
 * API endpoints define SANTA_API before requiring this file.
 */

ini_set('display_errors', '0');
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

set_exception_handler(function (Throwable $e): void {
    error_log('santa-letter: ' . get_class($e) . ': ' . $e->getMessage());
    if (!headers_sent()) {
        http_response_code(500);
    }
    echo defined('SANTA_API')
        ? json_encode(['error' => 'Server error. Please try again later.'])
        : 'Server error. Please try again later.';
});

header('X-Content-Type-Options: nosniff');
if (defined('SANTA_API')) {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
} else {
    header("Content-Security-Policy: default-src 'self'; img-src 'self' data:; "
        . "base-uri 'none'; form-action 'self'; frame-ancestors 'none'");
}

/* ---------- configuration ---------- */

/**
 * Loads config.php (or the file named by the SANTA_CONFIG environment variable).
 * Keeping the file outside the web root is the safest option.
 */
function config(): array
{
    static $config = null;
    if ($config === null) {
        $path = getenv('SANTA_CONFIG') ?: __DIR__ . '/../config.php';
        if (!is_file($path)) {
            throw new RuntimeException("Config file not found: $path (copy config.example.php to config.php)");
        }
        $loaded = require $path;
        $config = array_replace_recursive([
            'db' => ['host' => 'localhost', 'charset' => 'utf8mb4'],
            'limits' => ['name' => 50, 'note' => 1000],
        ], is_array($loaded) ? $loaded : []);
    }
    return $config;
}

function db(): mysqli
{
    static $conn = null;
    if ($conn === null) {
        $c = config()['db'];
        $conn = new mysqli($c['host'], $c['user'], $c['pass'], $c['name']);
        $conn->set_charset($c['charset']);
    }
    return $conn;
}

/* ---------- session and CSRF ---------- */

function start_session(): void
{
    if (session_status() !== PHP_SESSION_NONE) {
        return;
    }
    session_set_cookie_params([
        'lifetime' => 60 * 60 * 24 * 30,
        'path' => '/',
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function require_csrf(): void
{
    $sent = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    $known = $_SESSION['csrf'] ?? '';
    if ($known === '' || !is_string($sent) || !hash_equals($known, $sent)) {
        fail(403, 'Your session expired. Please reload the page.');
    }
}

/* ---------- JSON helpers ---------- */

function json_out(array $data, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function fail(int $status, string $message): void
{
    json_out(['error' => $message], $status);
}

function json_body(): array
{
    $raw = file_get_contents('php://input');
    $data = json_decode($raw === false ? '' : $raw, true);
    if (!is_array($data)) {
        fail(400, 'Invalid request.');
    }
    return $data;
}

/* ---------- input rules ---------- */

function text_length(string $s): int
{
    return function_exists('mb_strlen') ? mb_strlen($s, 'UTF-8') : strlen($s);
}

/** Trim and collapse whitespace; returns '' for invalid input. */
function clean_name($value): string
{
    if (!is_string($value)) {
        return '';
    }
    $name = preg_replace('/\s+/u', ' ', $value);
    return $name === null ? '' : trim($name);
}

function valid_name(string $name): bool
{
    return $name !== ''
        && text_length($name) <= config()['limits']['name']
        && !preg_match('/[\x00-\x1F\x7F]/', $name);
}

/** Trim a note; newlines and tabs are allowed, other control characters are not. */
function clean_note($value): string
{
    if (!is_string($value) || preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $value)) {
        return '';
    }
    return trim($value);
}

function valid_note(string $text): bool
{
    return $text !== '' && text_length($text) <= config()['limits']['note'];
}

/* ---------- dates ---------- */

/** [from, to) bounds of the current year, usable in an indexed range query. */
function year_range(): array
{
    $year = (int) date('Y');
    return [sprintf('%04d-01-01 00:00:00', $year), sprintf('%04d-01-01 00:00:00', $year + 1)];
}

/* ---------- output ---------- */

function e(string $s): string
{
    return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Hide everything from the first "@" on, so stored email addresses are never shown in full. */
function mask_name(string $name): string
{
    $at = strpos($name, '@');
    return $at === false ? $name : substr($name, 0, $at) . '***';
}

/** Asset URL with a cache-busting version taken from the file's modification time. */
function asset(string $file): string
{
    $mtime = @filemtime(__DIR__ . '/../assets/' . $file);
    return 'assets/' . $file . ($mtime ? '?v=' . $mtime : '');
}
