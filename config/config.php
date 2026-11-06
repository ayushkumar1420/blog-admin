<?php
declare(strict_types=1);

session_start();

define('BASE_PATH', dirname(__DIR__));
define('DATA_PATH', BASE_PATH . '/data');
define('POSTS_FILE', DATA_PATH . '/posts.json');
define('USERS_FILE', DATA_PATH . '/users.json');
define('UPLOAD_PATH', BASE_PATH . '/images/blog');

define('BASE_URL', '/deepshitech-blog-admin');

date_default_timezone_set('Asia/Kolkata');
/**
 * Read JSON data from a file.
 */
function readJson(string $file, array $default = []): array
{
    if (!is_file($file)) {
        return $default;
    }

    $contents = file_get_contents($file);

    if ($contents === false || trim($contents) === '') {
        return $default;
    }

    $data = json_decode($contents, true);

    return is_array($data) ? $data : $default;
}

/**
 * Save JSON data using an exclusive lock.
 */
function writeJson(string $file, array $data): bool
{
    $json = json_encode(
        $data,
        JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
    );

    if ($json === false) {
        return false;
    }

    $handle = fopen($file, 'c');

    if ($handle === false) {
        return false;
    }

    try {
        if (!flock($handle, LOCK_EX)) {
            return false;
        }

        if (!ftruncate($handle, 0) || rewind($handle) === false) {
            return false;
        }

        $length = strlen($json);
        $written = 0;

        while ($written < $length) {
            $result = fwrite($handle, substr($json, $written));

            if ($result === false || $result === 0) {
                return false;
            }

            $written += $result;
        }

        return fflush($handle);
    } finally {
        flock($handle, LOCK_UN);
        fclose($handle);
    }
}

/**
 * Escape text before displaying it in HTML.
 */
function e(string $value): string
{
    return htmlspecialchars(
        $value,
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );
}

/**
 * Check whether the current user is logged in.
 */
function isLoggedIn(): bool
{
    return isset($_SESSION['admin_id'], $_SESSION['admin_role']);
}

/**
 * Require an authenticated user.
 */
function requireLogin(): void
{
    if (!isLoggedIn()) {
        header('Location: ' . BASE_URL . '/admin/login.php');
        exit;
    }
}

/**
 * Require the administrator role.
 */
function requireAdmin(): void
{
    requireLogin();

    if ($_SESSION['admin_role'] !== 'admin') {
        http_response_code(403);
        exit('Access denied.');
    }
}

/**
 * Generate a CSRF token for forms.
 */
function csrfToken(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

/**
 * Validate a submitted CSRF token.
 */
function verifyCsrfToken(): bool
{
    $submitted = $_POST['csrf_token'] ?? '';

    return is_string($submitted)
        && isset($_SESSION['csrf_token'])
        && hash_equals($_SESSION['csrf_token'], $submitted);
}
