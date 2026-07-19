<?php
declare(strict_types=1);

function app_is_https(): bool {
    if (!empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off') return true;
    return strtolower((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';
}

function app_base_url(): string {
    $dir = str_replace('\\', '/', dirname((string)($_SERVER['SCRIPT_NAME'] ?? '/')));
    $dir = rtrim($dir, '/');
    return ($dir === '' || $dir === '.') ? '/' : $dir . '/';
}

function start_secure_session(): void {
    if (session_status() === PHP_SESSION_ACTIVE) return;

    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => app_base_url(),
        'secure' => app_is_https(),
        'httponly' => true,
        'samesite' => 'Strict',
    ]);
    session_start();
}

function send_security_headers(bool $api = false): void {
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: no-referrer');
    header('X-Frame-Options: DENY');
    header("Permissions-Policy: camera=(), microphone=(), geolocation=(), payment=()");
    if (!$api) {
        header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self'; img-src 'self' data:; connect-src 'self'; object-src 'none'; base-uri 'self'; frame-ancestors 'none'; form-action 'self'");
    }
}

function is_logged_in(): bool {
    return !empty($_SESSION['fastdl_manager_logged_in']);
}

function csrf_token(): string {
    if (empty($_SESSION['fastdl_manager_csrf'])) {
        $_SESSION['fastdl_manager_csrf'] = bin2hex(random_bytes(32));
    }
    return (string)$_SESSION['fastdl_manager_csrf'];
}

function csrf_is_valid(?string $token): bool {
    return is_string($token) && $token !== '' && hash_equals(csrf_token(), $token);
}

function normalize_return_path(string $path): string {
    $path = str_replace('\\', '/', trim($path));
    $path = trim($path, '/');
    if ($path === '' || str_contains($path, "\0")) return '';

    $parts = [];
    foreach (explode('/', $path) as $part) {
        if ($part === '' || $part === '.') continue;
        if ($part === '..') return '';
        $parts[] = $part;
    }
    return implode('/', $parts);
}

function app_password_configured(array $config): bool {
    $env = getenv('FASTDL_MANAGER_PASSWORD');
    $hashEnv = getenv('FASTDL_MANAGER_PASSWORD_HASH');
    if ((is_string($env) && $env !== '') || (is_string($hashEnv) && $hashEnv !== '')) return true;
    $plain = (string)($config['password'] ?? '');
    $hash = (string)($config['password_hash'] ?? '');
    return $hash !== '' || ($plain !== '' && $plain !== 'change-this-password');
}

function verify_app_password(array $config, string $password): bool {
    $hash = getenv('FASTDL_MANAGER_PASSWORD_HASH');
    if (!is_string($hash) || $hash === '') $hash = (string)($config['password_hash'] ?? '');
    if ($hash !== '') return password_verify($password, $hash);

    $plain = getenv('FASTDL_MANAGER_PASSWORD');
    if (!is_string($plain) || $plain === '') $plain = (string)($config['password'] ?? '');
    return $plain !== '' && $plain !== 'change-this-password' && hash_equals($plain, $password);
}

function login_rate_key(): string {
    $ip = (string)($_SERVER['REMOTE_ADDR'] ?? 'unknown');
    return hash('sha256', __DIR__ . '|' . $ip);
}

function login_rate_file(): string {
    return rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'file-manager-login-' . login_rate_key() . '.json';
}

function locked_login_rate_state(array $config, ?callable $mutate = null): array {
    $handle = fopen(login_rate_file(), 'c+');
    $state = ['attempts' => [], 'locked_until' => 0];
    if ($handle === false) return $state;
    flock($handle, LOCK_EX);
    rewind($handle);
    $decoded = json_decode((string)stream_get_contents($handle), true);
    if (is_array($decoded)) $state = array_merge($state, $decoded);

    $window = max(60, (int)($config['login_window_seconds'] ?? 900));
    $cutoff = time() - $window;
    $state['attempts'] = array_values(array_filter(
        is_array($state['attempts']) ? $state['attempts'] : [],
        fn($timestamp) => is_int($timestamp) && $timestamp >= $cutoff
    ));
    if ($mutate !== null) $state = $mutate($state);
    ftruncate($handle, 0);
    rewind($handle);
    fwrite($handle, json_encode($state));
    fflush($handle);
    flock($handle, LOCK_UN);
    fclose($handle);
    return $state;
}

function login_retry_after(array $config): int {
    $state = locked_login_rate_state($config);
    return max(0, (int)$state['locked_until'] - time());
}

function record_failed_login(array $config): int {
    $maxAttempts = max(3, (int)($config['login_max_attempts'] ?? 5));
    $state = locked_login_rate_state($config, function (array $state) use ($config, $maxAttempts): array {
        $state['attempts'][] = time();
        if (count($state['attempts']) >= $maxAttempts) {
            $state['locked_until'] = time() + max(60, (int)($config['login_lockout_seconds'] ?? 900));
            $state['attempts'] = [];
        }
        return $state;
    });
    return max(0, (int)$state['locked_until'] - time());
}

function clear_login_rate_state(): void {
    $file = login_rate_file();
    if (is_file($file)) @unlink($file);
}
