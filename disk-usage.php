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

function storage_usage_format_bytes(int $bytes): string {
    if ($bytes <= 0) return '0 B';

    $units = ['B', 'KB', 'MB', 'GB', 'TB', 'PB'];
    $index = min((int)floor(log($bytes, 1024)), count($units) - 1);
    $value = $bytes / (1024 ** $index);

    return round($value, $index === 0 ? 0 : 1) . ' ' . $units[$index];
}

function managed_files_size(string $directory): int {
    if (!is_dir($directory) || is_link($directory)) return 0;

    $bytes = 0;

    try {
        $visibleEntries = new RecursiveCallbackFilterIterator(
            new RecursiveDirectoryIterator(
                $directory,
                FilesystemIterator::SKIP_DOTS
            ),
            static function (SplFileInfo $item): bool {
                return !$item->isLink()
                    && !str_starts_with($item->getFilename(), '.');
            }
        );
        $iterator = new RecursiveIteratorIterator(
            $visibleEntries,
            RecursiveIteratorIterator::LEAVES_ONLY,
            RecursiveIteratorIterator::CATCH_GET_CHILD
        );

        foreach ($iterator as $item) {
            if (!$item->isFile()) continue;
            $size = $item->getSize();
            if ($size > 0) $bytes += $size;
        }
    } catch (RuntimeException $error) {
        // Unreadable entries are excluded rather than exposing server details.
    }

    return $bytes;
}

$configuredPath = (string)($config['base_dir'] ?? __DIR__);
$storagePath = realpath($configuredPath);
if ($storagePath === false || !is_dir($storagePath)) {
    http_response_code(503);
    echo json_encode(['ok' => false, 'error' => 'Uploaded-file usage is unavailable.']);
    exit;
}

clearstatcache(true, $storagePath);
$total = @disk_total_space($storagePath);

if ($total === false || $total <= 0) {
    http_response_code(503);
    echo json_encode(['ok' => false, 'error' => 'Storage capacity is unavailable.']);
    exit;
}

$totalBytes = (int)$total;
$uploadedBytes = managed_files_size($storagePath);
$percentage = round((min($uploadedBytes, $totalBytes) / $totalBytes) * 100, 1);

echo json_encode([
    'ok' => true,
    'uploaded' => $uploadedBytes,
    'total' => $totalBytes,
    'percentage' => $percentage,
    'uploaded_label' => storage_usage_format_bytes($uploadedBytes),
    'total_label' => storage_usage_format_bytes($totalBytes),
], JSON_UNESCAPED_SLASHES);
