<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
start_secure_session();
send_security_headers(true);

$config = require __DIR__ . '/config.php';

$sessionLifetime = max(300, (int)($config['session_lifetime_seconds'] ?? 43200));
if (is_logged_in() && time() - (int)($_SESSION['file_manager_login_time'] ?? 0) > $sessionLifetime) {
    $_SESSION = [];
    session_destroy();
}

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: private, no-store');

if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Not logged in.']);
    exit;
}

function disk_usage_format_bytes(int $bytes): string {
    if ($bytes <= 0) return '0 B';

    $units = ['B', 'KB', 'MB', 'GB', 'TB', 'PB'];
    $index = min((int)floor(log($bytes, 1024)), count($units) - 1);
    $value = $bytes / (1024 ** $index);

    return round($value, $index === 0 ? 0 : 1) . ' ' . $units[$index];
}

$configuredPath = (string)($config['base_dir'] ?? __DIR__);
$storagePath = realpath($configuredPath);
if ($storagePath === false) {
    $storagePath = realpath(dirname($configuredPath));
}
if ($storagePath === false) {
    $storagePath = __DIR__;
}

clearstatcache(true, $storagePath);
$total = @disk_total_space($storagePath);
$free = @disk_free_space($storagePath);

if ($total === false || $free === false || $total <= 0) {
    http_response_code(503);
    echo json_encode(['ok' => false, 'error' => 'Disk usage is unavailable.']);
    exit;
}

$totalBytes = (int)$total;
$freeBytes = max(0, min($totalBytes, (int)$free));
$usedBytes = max(0, $totalBytes - $freeBytes);
$percentage = round(($usedBytes / $totalBytes) * 100, 1);

echo json_encode([
    'ok' => true,
    'used' => $usedBytes,
    'free' => $freeBytes,
    'total' => $totalBytes,
    'percentage' => $percentage,
    'used_label' => disk_usage_format_bytes($usedBytes),
    'free_label' => disk_usage_format_bytes($freeBytes),
    'total_label' => disk_usage_format_bytes($totalBytes),
], JSON_UNESCAPED_SLASHES);
