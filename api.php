<?php
declare(strict_types=1);

session_start();

$config = require __DIR__ . '/config.php';

function json_response(array $data, int $status = 200): void {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

function is_logged_in(): bool {
    return !empty($_SESSION['fastdl_manager_logged_in']);
}

function require_login_json(): void {
    if (!is_logged_in()) {
        json_response(['ok' => false, 'error' => 'Not logged in.'], 401);
    }
}

function clean_rel_path(string $path): string {
    $path = str_replace('\\', '/', $path);
    $path = trim($path, '/');
    if ($path === '') return '';

    $parts = [];
    foreach (explode('/', $path) as $part) {
        if ($part === '' || $part === '.') continue;
        if ($part === '..') {
            json_response(['ok' => false, 'error' => 'Invalid path.'], 400);
        }
        $parts[] = $part;
    }
    return implode('/', $parts);
}

function clean_name(string $name): string {
    $name = str_replace('\\', '/', $name);
    $name = basename($name);
    $name = trim($name);

    if ($name === '' || $name === '.' || $name === '..') {
        json_response(['ok' => false, 'error' => 'Invalid name.'], 400);
    }

    if (preg_match('/[\/\\\\]/', $name)) {
        json_response(['ok' => false, 'error' => 'Name cannot contain slashes.'], 400);
    }

    return $name;
}

function ensure_base_dir(array $config): string {
    $base = $config['base_dir'];
    if (!is_dir($base)) {
        mkdir($base, 0755, true);
    }
    $real = realpath($base);
    if ($real === false) {
        json_response(['ok' => false, 'error' => 'Base directory does not exist.'], 500);
    }
    return rtrim($real, DIRECTORY_SEPARATOR);
}

function full_path(array $config, string $rel): string {
    $base = ensure_base_dir($config);
    $rel = clean_rel_path($rel);
    $full = $rel === '' ? $base : $base . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rel);

    $check = file_exists($full) ? realpath($full) : realpath(dirname($full));
    if ($check === false || strpos($check, $base) !== 0) {
        json_response(['ok' => false, 'error' => 'Path is outside the managed folder.'], 400);
    }

    return $full;
}


function download_content_disposition(string $filename): string {
    $filename = basename(str_replace('\\', '/', $filename));
    $filename = str_replace(["\r", "\n", "\0"], '_', $filename);
    if ($filename === '' || $filename === '.' || $filename === '..') {
        $filename = 'download';
    }

    $fallback = preg_replace('/[^\x20-\x7E]/', '_', $filename);
    if ($fallback === null || trim($fallback) === '') {
        $fallback = 'download';
    }
    $fallback = str_replace(['\\', '"'], '_', $fallback);

    return 'attachment; filename="' . $fallback . '"; filename*=UTF-8\'\'' . rawurlencode($filename);
}


function dos_datetime(int $timestamp): array {
    $year = (int)date('Y', $timestamp);
    if ($year < 1980) {
        $year = 1980;
        $month = 1;
        $day = 1;
        $hour = $minute = $second = 0;
    } else {
        $month = (int)date('n', $timestamp);
        $day = (int)date('j', $timestamp);
        $hour = (int)date('G', $timestamp);
        $minute = (int)date('i', $timestamp);
        $second = (int)date('s', $timestamp);
    }
    $dosTime = ($hour << 11) | ($minute << 5) | intdiv($second, 2);
    $dosDate = (($year - 1980) << 9) | ($month << 5) | $day;
    return [$dosTime, $dosDate];
}

function safe_zip_entry_name(string $filename): string {
    $filename = basename(str_replace('\\', '/', $filename));
    $filename = str_replace(["\r", "\n", "\0"], '_', $filename);
    if ($filename === '' || $filename === '.' || $filename === '..') {
        return 'shortcut.lnk';
    }
    return $filename;
}

function send_single_file_zip(string $file, string $entryName, string $zipFilename): void {
    $data = file_get_contents($file);
    if ($data === false) {
        http_response_code(500);
        echo 'Could not read file.';
        exit;
    }

    $entryName = safe_zip_entry_name($entryName);
    $zipFilename = safe_zip_entry_name($zipFilename);
    if (extension_of($zipFilename) !== 'zip') {
        $zipFilename .= '.zip';
    }

    [$dosTime, $dosDate] = dos_datetime(filemtime($file) ?: time());
    $crc = crc32($data);
    $size = strlen($data);
    $nameLen = strlen($entryName);
    $flags = 0x0800; // UTF-8 file names
    $method = 0; // store, no compression

    $local = "PK\x03\x04" . pack('vvvvvVVVvv', 20, $flags, $method, $dosTime, $dosDate, $crc, $size, $size, $nameLen, 0) . $entryName . $data;
    $central = "PK\x01\x02" . pack('vvvvvvVVVvvvvvVV', 0x031E, 20, $flags, $method, $dosTime, $dosDate, $crc, $size, $size, $nameLen, 0, 0, 0, 0, 0, 0) . $entryName;
    $end = "PK\x05\x06" . pack('vvvvVVv', 0, 0, 1, 1, strlen($central), strlen($local), 0);
    $zip = $local . $central . $end;

    header('Content-Type: application/zip');
    header('X-Content-Type-Options: nosniff');
    header('Content-Transfer-Encoding: binary');
    header('Content-Length: ' . strlen($zip));
    header('Content-Disposition: ' . download_content_disposition($zipFilename));
    echo $zip;
    exit;
}


function safe_zip_download_name(string $name, string $fallback = 'download.zip'): string {
    $name = basename(str_replace('\\', '/', $name));
    $name = str_replace(["\r", "\n", "\0"], '_', $name);
    $name = trim($name);
    if ($name === '' || $name === '.' || $name === '..') {
        $name = $fallback;
    }
    if (extension_of($name) !== 'zip') {
        $name .= '.zip';
    }
    return $name;
}

function zip_entry_headers(string $entryName, int $mtime, int $crc, int $size, int $offset, bool $isDir = false): array {
    $entryName = str_replace('\\', '/', $entryName);
    $entryName = ltrim($entryName, '/');
    if ($isDir && !str_ends_with($entryName, '/')) {
        $entryName .= '/';
    }

    [$dosTime, $dosDate] = dos_datetime($mtime);
    $nameLen = strlen($entryName);
    $flags = 0x0800; // UTF-8 file names
    $method = 0; // store, no compression
    $externalAttrs = $isDir ? (0x10 << 16) : 0;

    $local = "PK\x03\x04" . pack('vvvvvVVVvv', 20, $flags, $method, $dosTime, $dosDate, $crc, $size, $size, $nameLen, 0) . $entryName;
    $central = "PK\x01\x02" . pack('vvvvvvVVVvvvvvVV', 0x031E, 20, $flags, $method, $dosTime, $dosDate, $crc, $size, $size, $nameLen, 0, 0, 0, 0, $externalAttrs, $offset) . $entryName;

    return [$local, $central];
}

function send_folder_zip(array $config, string $folder, string $rel): void {
    $folderName = basename($folder);
    if ($folderName === '' || $folderName === DIRECTORY_SEPARATOR) {
        $folderName = 'files';
    }

    $zipFilename = safe_zip_download_name($folderName . '.zip', 'folder.zip');
    $rootEntry = safe_zip_entry($folderName) ?: 'folder';

    header('Content-Type: application/zip');
    header('X-Content-Type-Options: nosniff');
    header('Content-Transfer-Encoding: binary');
    header('Content-Disposition: ' . download_content_disposition($zipFilename));

    $central = [];
    $offset = 0;
    $entryCount = 0;

    $emitEntry = function (string $entryName, string $path, bool $isDir) use (&$central, &$offset, &$entryCount): void {
        $entryName = safe_zip_entry($entryName);
        if ($entryName === null) {
            return;
        }

        $size = 0;
        $crc = 0;
        if (!$isDir) {
            $size = filesize($path) ?: 0;
            $crcHex = hash_file('crc32b', $path);
            if ($crcHex === false) {
                return;
            }
            $crc = (int)sprintf('%u', hexdec($crcHex));
        }

        [$local, $centralHeader] = zip_entry_headers($entryName, filemtime($path) ?: time(), $crc, $size, $offset, $isDir);
        echo $local;
        $offset += strlen($local);

        if (!$isDir) {
            $in = fopen($path, 'rb');
            if ($in) {
                while (!feof($in)) {
                    $chunk = fread($in, 1024 * 1024);
                    if ($chunk === false) break;
                    echo $chunk;
                    $offset += strlen($chunk);
                    if (function_exists('flush')) flush();
                }
                fclose($in);
            }
        }

        $central[] = $centralHeader;
        $entryCount++;
    };

    $emitEntry($rootEntry . '/', $folder, true);

    $scan = function (string $dir, string $prefix) use (&$scan, $config, $emitEntry): void {
        $items = scandir($dir) ?: [];
        foreach ($items as $name) {
            if ($name === '.' || $name === '..' || is_hidden($config, $name)) continue;

            $full = $dir . DIRECTORY_SEPARATOR . $name;
            if (is_link($full)) continue;

            $entry = $prefix . '/' . $name;
            if (is_dir($full)) {
                $emitEntry($entry . '/', $full, true);
                $scan($full, $entry);
            } elseif (is_file($full)) {
                $emitEntry($entry, $full, false);
            }
        }
    };

    $scan($folder, $rootEntry);

    $centralStart = $offset;
    $centralBlob = implode('', $central);
    echo $centralBlob;
    $offset += strlen($centralBlob);

    echo "PK\x05\x06" . pack('vvvvVVv', 0, 0, $entryCount, $entryCount, strlen($centralBlob), $centralStart, 0);
    exit;
}


function send_paths_zip(array $config, array $paths): void {
    $cleaned = [];
    $seen = [];

    foreach ($paths as $path) {
        if (!is_string($path)) continue;
        $rel = clean_rel_path($path);
        if ($rel === '' || isset($seen[$rel])) continue;
        $seen[$rel] = true;
        $full = full_path($config, $rel);
        if (!file_exists($full)) {
            json_response(['ok' => false, 'error' => 'Selected path not found: ' . $rel], 404);
        }
        if (is_link($full)) continue;
        $cleaned[] = ['rel' => $rel, 'full' => $full];
    }

    if (!$cleaned) {
        json_response(['ok' => false, 'error' => 'No valid selected items to download.'], 400);
    }

    $firstName = basename($cleaned[0]['full']);
    $zipFilename = count($cleaned) === 1
        ? safe_zip_download_name($firstName . '.zip', 'selected-item.zip')
        : safe_zip_download_name('selected-items.zip', 'selected-items.zip');

    header('Content-Type: application/zip');
    header('X-Content-Type-Options: nosniff');
    header('Content-Transfer-Encoding: binary');
    header('Content-Disposition: ' . download_content_disposition($zipFilename));

    $central = [];
    $offset = 0;
    $entryCount = 0;

    $emitEntry = function (string $entryName, string $path, bool $isDir) use (&$central, &$offset, &$entryCount): void {
        $entryName = safe_zip_entry($entryName);
        if ($entryName === null) {
            return;
        }

        $size = 0;
        $crc = 0;
        if (!$isDir) {
            $size = filesize($path) ?: 0;
            $crcHex = hash_file('crc32b', $path);
            if ($crcHex === false) {
                return;
            }
            $crc = (int)sprintf('%u', hexdec($crcHex));
        }

        [$local, $centralHeader] = zip_entry_headers($entryName, filemtime($path) ?: time(), $crc, $size, $offset, $isDir);
        echo $local;
        $offset += strlen($local);

        if (!$isDir) {
            $in = fopen($path, 'rb');
            if ($in) {
                while (!feof($in)) {
                    $chunk = fread($in, 1024 * 1024);
                    if ($chunk === false) break;
                    echo $chunk;
                    $offset += strlen($chunk);
                    if (function_exists('flush')) flush();
                }
                fclose($in);
            }
        }

        $central[] = $centralHeader;
        $entryCount++;
    };

    $scan = function (string $dir, string $prefix) use (&$scan, $config, $emitEntry): void {
        $items = scandir($dir) ?: [];
        foreach ($items as $name) {
            if ($name === '.' || $name === '..' || is_hidden($config, $name)) continue;

            $full = $dir . DIRECTORY_SEPARATOR . $name;
            if (is_link($full)) continue;

            $entry = $prefix . '/' . $name;
            if (is_dir($full)) {
                $emitEntry($entry . '/', $full, true);
                $scan($full, $entry);
            } elseif (is_file($full)) {
                $emitEntry($entry, $full, false);
            }
        }
    };

    foreach ($cleaned as $item) {
        $full = $item['full'];
        $rootEntry = safe_zip_entry(basename($full));
        if ($rootEntry === null) continue;

        if (is_dir($full)) {
            $emitEntry($rootEntry . '/', $full, true);
            $scan($full, $rootEntry);
        } elseif (is_file($full)) {
            $emitEntry($rootEntry, $full, false);
        }
    }

    $centralStart = $offset;
    $centralBlob = implode('', $central);
    echo $centralBlob;
    $offset += strlen($centralBlob);

    echo "PK\x05\x06" . pack('vvvvVVv', 0, 0, $entryCount, $entryCount, strlen($centralBlob), $centralStart, 0);
    exit;
}

function extension_of(string $name): string {
    $ext = pathinfo($name, PATHINFO_EXTENSION);
    return strtolower($ext ?: '');
}

function is_hidden(array $config, string $name): bool {
    if (in_array($name, $config['hidden_names'], true)) return true;
    return str_starts_with($name, '.') && $name !== '.htaccess';
}

function upload_allowed(array $config, string $name): array {
    if (empty($config['block_system_uploads'])) {
        return [true, ''];
    }

    $lower = strtolower($name);
    $ext = extension_of($name);

    $blockedNames = array_map('strtolower', $config['blocked_upload_names'] ?? []);
    if (in_array($lower, $blockedNames, true)) {
        return [
            false,
            'This filename is protected because it can change server behavior or overwrite the file manager itself.'
        ];
    }

    if (!empty($config['block_dotfile_uploads']) && str_starts_with($name, '.')) {
        return [
            false,
            'Hidden/system files that start with a dot are blocked for safety.'
        ];
    }

    $blockedExts = array_map('strtolower', $config['blocked_upload_extensions'] ?? []);
    if ($ext !== '' && in_array($ext, $blockedExts, true)) {
        return [
            false,
            'Files ending in .' . $ext . ' are blocked because they may run as server-side scripts or programs.'
        ];
    }

    return [true, ''];
}

function clean_upload_rel_path(array $config, string $path): array {
    $path = str_replace('\\', '/', $path);
    $path = trim($path, '/');

    if ($path === '') {
        return [false, '', 'Invalid upload path.'];
    }

    $parts = [];
    $rawParts = explode('/', $path);

    foreach ($rawParts as $index => $part) {
        $part = trim($part);

        if ($part === '' || $part === '.' || $part === '..') {
            return [false, '', 'Invalid folder path.'];
        }

        if (preg_match('/[\\\\]/', $part)) {
            return [false, '', 'Invalid folder path.'];
        }

        if ($index < count($rawParts) - 1) {
            [$allowedFolder, $folderReason] = entry_name_allowed($config, $part);
            if (!$allowedFolder) {
                return [false, '', 'Folder "' . $part . '" is blocked. ' . $folderReason];
            }
        } else {
            [$allowedFile, $fileReason] = upload_allowed($config, $part);
            if (!$allowedFile) {
                return [false, '', $fileReason];
            }
        }

        $parts[] = $part;
    }

    return [true, implode('/', $parts), ''];
}

function encode_public_path(string $path): string {
    $parts = array_filter(explode('/', clean_rel_path($path)), fn($p) => $p !== '');
    return implode('/', array_map('rawurlencode', $parts));
}

function public_url(array $config, string $path): string {
    $base = (string)($config['public_base_url'] ?? '');
    if ($base === '') {
        return 'api.php?action=download&path=' . rawurlencode($path);
    }
    return rtrim($base, '/') . '/' . encode_public_path($path);
}

function is_editable(array $config, string $path, ?int $size = null): bool {
    $ext = extension_of($path);
    $allowed = $config['editable_extensions'] ?? [];
    if (!in_array($ext, $allowed, true)) return false;
    if ($size !== null && $size > (int)$config['max_edit_size']) return false;
    return true;
}

function format_bytes(int $bytes): string {
    if ($bytes <= 0) return '0 B';
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $i = min((int)floor(log($bytes, 1024)), count($units) - 1);
    return round($bytes / (1024 ** $i), $i === 0 ? 0 : 1) . ' ' . $units[$i];
}

function rrmdir(string $path): bool {
    if (!is_dir($path)) return false;
    foreach (scandir($path) ?: [] as $item) {
        if ($item === '.' || $item === '..') continue;
        $child = $path . DIRECTORY_SEPARATOR . $item;
        if (is_dir($child)) {
            if (!rrmdir($child)) return false;
        } else {
            if (!unlink($child)) return false;
        }
    }
    return rmdir($path);
}

function is_same_or_child_path(string $child, string $parent): bool {
    $child = trim(clean_rel_path($child), '/');
    $parent = trim(clean_rel_path($parent), '/');

    if ($parent === '') return true;
    return $child === $parent || str_starts_with($child, $parent . '/');
}

function entry_name_allowed(array $config, string $name): array {
    [$allowed, $reason] = upload_allowed($config, $name);
    if (!$allowed) return [$allowed, $reason];

    if (is_hidden($config, $name)) {
        return [
            false,
            'This name is reserved or hidden by the file manager.'
        ];
    }

    return [true, ''];
}

function safe_zip_entry(string $entry): ?string {
    $entry = str_replace('\\', '/', $entry);
    $entry = ltrim($entry, '/');

    if ($entry === '' || str_contains($entry, "\0")) return null;

    $parts = [];
    foreach (explode('/', $entry) as $part) {
        if ($part === '' || $part === '.') continue;
        if ($part === '..') return null;
        $parts[] = $part;
    }

    if (!$parts) return null;
    return implode('/', $parts);
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';

if ($action === 'download') {
    if (!is_logged_in()) {
        http_response_code(401);
        echo 'Not logged in.';
        exit;
    }

    $rel = clean_rel_path((string)($_GET['path'] ?? ''));
    $file = full_path($config, $rel);

    if (is_dir($file)) {
        send_folder_zip($config, $file, $rel);
    }

    if (!is_file($file)) {
        http_response_code(404);
        echo 'File not found.';
        exit;
    }

    $filename = basename($file);

    // Chromium-based browsers can rename direct .lnk downloads to .download for safety.
    // Wrap Windows shortcut files in a ZIP so the user gets a stable, usable download.
    if (extension_of($filename) === 'lnk') {
        $zipBase = preg_replace('/\.lnk$/i', '', $filename);
        if ($zipBase === null || $zipBase === '') {
            $zipBase = 'shortcut';
        }
        send_single_file_zip($file, $filename, $zipBase . '.zip');
    }

    header('Content-Type: application/octet-stream');
    header('X-Content-Type-Options: nosniff');
    header('Content-Transfer-Encoding: binary');
    header('Content-Length: ' . filesize($file));
    header('Content-Disposition: ' . download_content_disposition($filename));
    readfile($file);
    exit;
}


if ($action === 'download_batch') {
    if (!is_logged_in()) {
        http_response_code(401);
        echo 'Not logged in.';
        exit;
    }

    $input = json_decode(file_get_contents('php://input') ?: '{}', true);
    if (!is_array($input)) {
        json_response(['ok' => false, 'error' => 'Invalid JSON body.'], 400);
    }

    $paths = $input['paths'] ?? [];
    if (!is_array($paths)) {
        json_response(['ok' => false, 'error' => 'paths must be an array.'], 400);
    }

    send_paths_zip($config, $paths);
}

require_login_json();

if ($action === 'list') {
    $rel = clean_rel_path((string)($_GET['path'] ?? ''));
    $dir = full_path($config, $rel);

    if (!is_dir($dir)) {
        json_response(['ok' => false, 'error' => 'Folder not found.'], 404);
    }

    $entries = [];
    $totalSize = 0;
    $folderCount = 0;
    $fileCount = 0;

    foreach (scandir($dir) ?: [] as $name) {
        if (is_hidden($config, $name)) continue;

        $full = $dir . DIRECTORY_SEPARATOR . $name;
        $entryRel = trim(($rel ? $rel . '/' : '') . $name, '/');

        if (is_dir($full)) {
            $folderCount++;
            $entries[] = [
                'type' => 'dir',
                'name' => $name,
                'path' => $entryRel,
                'size' => 0,
                'modified' => filemtime($full) ?: 0,
                'editable' => false,
                'extractable' => false,
                'public_url' => null,
                'download_url' => 'api.php?action=download&path=' . rawurlencode($entryRel),
                'download_name' => $name . '.zip',
            ];
        } elseif (is_file($full)) {
            $size = filesize($full) ?: 0;
            $fileCount++;
            $totalSize += $size;
            $ext = extension_of($name);
            $entries[] = [
                'type' => 'file',
                'name' => $name,
                'path' => $entryRel,
                'size' => $size,
                'size_label' => format_bytes($size),
                'modified' => filemtime($full) ?: 0,
                'editable' => is_editable($config, $entryRel, $size),
                'extractable' => !empty($config['allow_zip_extract']) && $ext === 'zip',
                'public_url' => public_url($config, $entryRel),
                'download_url' => 'api.php?action=download&path=' . rawurlencode($entryRel),
                'download_name' => $ext === 'lnk' ? preg_replace('/\.lnk$/i', '', $name) . '.zip' : $name,
            ];
        }
    }

    usort($entries, function ($a, $b) {
        if ($a['type'] !== $b['type']) return $a['type'] === 'dir' ? -1 : 1;
        return strnatcasecmp($a['name'], $b['name']);
    });

    json_response([
        'ok' => true,
        'path' => $rel,
        'entries' => $entries,
        'stats' => [
            'folders' => $folderCount,
            'files' => $fileCount,
            'total_size' => $totalSize,
            'total_size_label' => format_bytes($totalSize),
        ],
    ]);
}

if ($action === 'read') {
    $rel = clean_rel_path((string)($_GET['path'] ?? ''));
    $file = full_path($config, $rel);

    if (!is_file($file)) {
        json_response(['ok' => false, 'error' => 'File not found.'], 404);
    }

    $size = filesize($file) ?: 0;
    if (!is_editable($config, $rel, $size)) {
        json_response(['ok' => false, 'error' => 'This file type or size is not editable.'], 400);
    }

    $content = file_get_contents($file);
    if ($content === false) {
        json_response(['ok' => false, 'error' => 'Could not read file.'], 500);
    }

    json_response([
        'ok' => true,
        'path' => $rel,
        'name' => basename($file),
        'content' => $content,
        'size' => $size,
        'modified' => filemtime($file) ?: 0,
        'public_url' => public_url($config, $rel),
    ]);
}

if ($action === 'save') {
    $input = json_decode(file_get_contents('php://input') ?: '{}', true);
    if (!is_array($input)) {
        json_response(['ok' => false, 'error' => 'Invalid JSON body.'], 400);
    }

    $rel = clean_rel_path((string)($input['path'] ?? ''));
    $content = (string)($input['content'] ?? '');
    $file = full_path($config, $rel);

    if (!is_file($file)) {
        json_response(['ok' => false, 'error' => 'File not found.'], 404);
    }

    $size = filesize($file) ?: 0;
    if (!is_editable($config, $rel, max($size, strlen($content)))) {
        json_response(['ok' => false, 'error' => 'This file type or size is not editable.'], 400);
    }

    if (!is_writable($file)) {
        json_response(['ok' => false, 'error' => 'File is not writable by the web server.'], 500);
    }

    if (!empty($config['create_backups'])) {
        $backupDir = (string)$config['backup_dir'];
        if (!is_dir($backupDir)) {
            mkdir($backupDir, 0755, true);
        }
        $safeName = str_replace(['/', '\\'], '__', $rel);
        copy($file, rtrim($backupDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . date('Ymd-His') . '__' . $safeName);
    }

    $written = file_put_contents($file, $content, LOCK_EX);
    if ($written === false) {
        json_response(['ok' => false, 'error' => 'Could not write file.'], 500);
    }

    clearstatcache(true, $file);

    json_response([
        'ok' => true,
        'message' => 'Saved.',
        'path' => $rel,
        'size' => filesize($file) ?: 0,
        'modified' => filemtime($file) ?: 0,
    ]);
}

if ($action === 'upload') {
    $rel = clean_rel_path((string)($_GET['path'] ?? $_POST['path'] ?? ''));
    $dir = full_path($config, $rel);

    if (!is_dir($dir)) {
        json_response(['ok' => false, 'error' => 'Upload target is not a folder.'], 400);
    }

    if (!is_writable($dir)) {
        json_response(['ok' => false, 'error' => 'Folder is not writable by the web server.'], 500);
    }

    if (empty($_FILES['files'])) {
        json_response(['ok' => false, 'error' => 'No files uploaded.'], 400);
    }

    $uploaded = [];
    $errors = [];
    $names = $_FILES['files']['name'];
    $tmpNames = $_FILES['files']['tmp_name'];
    $sizes = $_FILES['files']['size'];
    $errCodes = $_FILES['files']['error'];
    $relativePaths = $_POST['paths'] ?? [];

    if (!is_array($names)) {
        $names = [$names];
        $tmpNames = [$tmpNames];
        $sizes = [$sizes];
        $errCodes = [$errCodes];
    }

    if (!is_array($relativePaths)) {
        $relativePaths = [$relativePaths];
    }

    foreach ($names as $i => $rawName) {
        $displayName = clean_name((string)$rawName);
        $relativeRaw = (string)($relativePaths[$i] ?? $displayName);

        // For normal multi-file uploads, the relative path is just the filename.
        // For folder uploads, it can be something like "folder/subfolder/file.txt".
        [$pathOk, $uploadRelPath, $pathReason] = clean_upload_rel_path($config, $relativeRaw);
        if (!$pathOk) {
            $errors[] = $relativeRaw . ' was not uploaded. ' . $pathReason;
            continue;
        }

        $filename = basename(str_replace('\\', '/', $uploadRelPath));

        [$allowedUpload, $blockedReason] = upload_allowed($config, $filename);
        if (!$allowedUpload) {
            $errors[] = $uploadRelPath . ' was not uploaded. ' . $blockedReason;
            continue;
        }

        $err = (int)$errCodes[$i];

        if ($err !== UPLOAD_ERR_OK) {
            $errors[] = $uploadRelPath . ' upload failed. Error code ' . $err . '.';
            continue;
        }

        $size = (int)$sizes[$i];
        if ($size > (int)$config['max_upload_size']) {
            $errors[] = $uploadRelPath . ' was not uploaded. The file is too large.';
            continue;
        }

        $dest = $dir . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $uploadRelPath);
        $destParent = dirname($dest);

        $baseReal = realpath($dir);
        if ($baseReal === false) {
            $errors[] = $uploadRelPath . ' was not uploaded. Upload folder is invalid.';
            continue;
        }

        if (!is_dir($destParent)) {
            if (!mkdir($destParent, 0755, true)) {
                $errors[] = $uploadRelPath . ' was not uploaded. Could not create folder.';
                continue;
            }
        }

        $parentReal = realpath($destParent);
        if ($parentReal === false || strpos($parentReal, $baseReal) !== 0) {
            $errors[] = $uploadRelPath . ' was not uploaded. Invalid upload path.';
            continue;
        }

        if (!is_writable($destParent)) {
            $errors[] = $uploadRelPath . ' was not uploaded. Target folder is not writable.';
            continue;
        }

        if (!move_uploaded_file((string)$tmpNames[$i], $dest)) {
            $errors[] = $uploadRelPath . ' could not be moved.';
            continue;
        }

        $uploaded[] = $uploadRelPath;
    }

    json_response([
        'ok' => count($errors) === 0,
        'uploaded' => $uploaded,
        'errors' => $errors,
        'message' => count($uploaded) . ' file(s) uploaded.',
    ], count($uploaded) ? 200 : 400);
}

if ($action === 'delete') {
    $input = json_decode(file_get_contents('php://input') ?: '{}', true);
    if (!is_array($input)) {
        json_response(['ok' => false, 'error' => 'Invalid JSON body.'], 400);
    }

    $rel = clean_rel_path((string)($input['path'] ?? ''));
    if ($rel === '') {
        json_response(['ok' => false, 'error' => 'Cannot delete root folder.'], 400);
    }

    $path = full_path($config, $rel);

    if (!file_exists($path)) {
        json_response(['ok' => false, 'error' => 'Path not found.'], 404);
    }

    if (is_dir($path)) {
        if (empty($config['allow_recursive_delete'])) {
            $items = array_diff(scandir($path) ?: [], ['.', '..']);
            if ($items) {
                json_response(['ok' => false, 'error' => 'Folder is not empty.'], 400);
            }
            if (!rmdir($path)) {
                json_response(['ok' => false, 'error' => 'Could not delete folder.'], 500);
            }
        } else {
            if (!rrmdir($path)) {
                json_response(['ok' => false, 'error' => 'Could not delete folder.'], 500);
            }
        }
    } else {
        if (!unlink($path)) {
            json_response(['ok' => false, 'error' => 'Could not delete file.'], 500);
        }
    }

    json_response(['ok' => true, 'message' => 'Deleted.', 'path' => $rel]);
}

if ($action === 'rename') {
    $input = json_decode(file_get_contents('php://input') ?: '{}', true);
    if (!is_array($input)) {
        json_response(['ok' => false, 'error' => 'Invalid JSON body.'], 400);
    }

    $rel = clean_rel_path((string)($input['path'] ?? ''));
    $newName = clean_name((string)($input['new_name'] ?? ''));

    if ($rel === '') {
        json_response(['ok' => false, 'error' => 'Cannot rename root folder.'], 400);
    }

    $old = full_path($config, $rel);
    if (!file_exists($old)) {
        json_response(['ok' => false, 'error' => 'Path not found.'], 404);
    }

    $parentRel = dirname($rel);
    if ($parentRel === '.') $parentRel = '';
    $parent = full_path($config, $parentRel);
    $new = $parent . DIRECTORY_SEPARATOR . $newName;

    if (file_exists($new)) {
        json_response(['ok' => false, 'error' => 'A file or folder with that name already exists.'], 400);
    }

    if (!rename($old, $new)) {
        json_response(['ok' => false, 'error' => 'Could not rename. Check permissions.'], 500);
    }

    $newRel = trim(($parentRel ? $parentRel . '/' : '') . $newName, '/');
    json_response(['ok' => true, 'message' => 'Renamed.', 'old_path' => $rel, 'path' => $newRel]);
}


if ($action === 'create') {
    $input = json_decode(file_get_contents('php://input') ?: '{}', true);
    if (!is_array($input)) {
        json_response(['ok' => false, 'error' => 'Invalid JSON body.'], 400);
    }

    $parentRel = clean_rel_path((string)($input['path'] ?? ''));
    $type = (string)($input['type'] ?? '');
    $name = clean_name((string)($input['name'] ?? ''));

    if (!in_array($type, ['file', 'dir'], true)) {
        json_response(['ok' => false, 'error' => 'Type must be file or dir.'], 400);
    }

    [$allowedName, $nameReason] = entry_name_allowed($config, $name);
    if (!$allowedName) {
        json_response(['ok' => false, 'error' => $name . ' was not created. ' . $nameReason], 400);
    }

    $parent = full_path($config, $parentRel);
    if (!is_dir($parent)) {
        json_response(['ok' => false, 'error' => 'Target folder not found.'], 404);
    }

    if (!is_writable($parent)) {
        json_response(['ok' => false, 'error' => 'Folder is not writable by the web server.'], 500);
    }

    $newPath = $parent . DIRECTORY_SEPARATOR . $name;
    if (file_exists($newPath)) {
        json_response(['ok' => false, 'error' => 'A file or folder with that name already exists.'], 400);
    }

    if ($type === 'dir') {
        if (!mkdir($newPath, 0755, true)) {
            json_response(['ok' => false, 'error' => 'Could not create folder.'], 500);
        }
    } else {
        $content = (string)($input['content'] ?? '');
        if (file_put_contents($newPath, $content, LOCK_EX) === false) {
            json_response(['ok' => false, 'error' => 'Could not create file.'], 500);
        }
    }

    $rel = trim(($parentRel ? $parentRel . '/' : '') . $name, '/');
    json_response(['ok' => true, 'message' => ucfirst($type) . ' created.', 'path' => $rel, 'type' => $type]);
}

if ($action === 'move') {
    $input = json_decode(file_get_contents('php://input') ?: '{}', true);
    if (!is_array($input)) {
        json_response(['ok' => false, 'error' => 'Invalid JSON body.'], 400);
    }

    $rel = clean_rel_path((string)($input['path'] ?? ''));
    $targetRel = clean_rel_path((string)($input['target_dir'] ?? ''));

    if ($rel === '') {
        json_response(['ok' => false, 'error' => 'Cannot move the root folder.'], 400);
    }

    $source = full_path($config, $rel);
    $targetDir = full_path($config, $targetRel);

    if (!file_exists($source)) {
        json_response(['ok' => false, 'error' => 'Source file or folder not found.'], 404);
    }

    if (!file_exists($targetDir)) {
        json_response(['ok' => false, 'error' => 'Target folder does not exist.'], 404);
    }

    if (!is_dir($targetDir)) {
        json_response(['ok' => false, 'error' => 'Target is not a folder.'], 400);
    }

    if (is_dir($source) && is_same_or_child_path($targetRel, $rel)) {
        json_response(['ok' => false, 'error' => 'Cannot move a folder into itself or one of its own subfolders.'], 400);
    }

    $name = basename($source);
    $currentParentRel = dirname($rel);
    if ($currentParentRel === '.') $currentParentRel = '';

    if ($currentParentRel === $targetRel) {
        json_response(['ok' => true, 'message' => 'Already in that folder.', 'path' => $rel]);
    }

    if (!is_writable(dirname($source)) || !is_writable($targetDir)) {
        json_response(['ok' => false, 'error' => 'Move failed because the source or target folder is not writable.'], 500);
    }

    $dest = $targetDir . DIRECTORY_SEPARATOR . $name;
    if (file_exists($dest)) {
        json_response(['ok' => false, 'error' => 'A file or folder with that name already exists in the target folder.'], 400);
    }

    if (!rename($source, $dest)) {
        json_response(['ok' => false, 'error' => 'Could not move item. Check permissions.'], 500);
    }

    $newRel = trim(($targetRel ? $targetRel . '/' : '') . $name, '/');
    json_response(['ok' => true, 'message' => 'Moved.', 'old_path' => $rel, 'path' => $newRel]);
}

if ($action === 'extract') {
    if (empty($config['allow_zip_extract'])) {
        json_response(['ok' => false, 'error' => 'ZIP extraction is disabled.'], 403);
    }

    if (!class_exists('ZipArchive')) {
        json_response(['ok' => false, 'error' => 'PHP ZipArchive extension is not installed/enabled.'], 500);
    }

    $input = json_decode(file_get_contents('php://input') ?: '{}', true);
    if (!is_array($input)) {
        json_response(['ok' => false, 'error' => 'Invalid JSON body.'], 400);
    }

    $rel = clean_rel_path((string)($input['path'] ?? ''));
    $zipPath = full_path($config, $rel);

    if (!is_file($zipPath) || extension_of($zipPath) !== 'zip') {
        json_response(['ok' => false, 'error' => 'Only .zip files can be extracted.'], 400);
    }

    $parentRel = dirname($rel);
    if ($parentRel === '.') $parentRel = '';
    $parent = full_path($config, $parentRel);

    $target = $parent;
    $targetRel = $parentRel;

    if (!empty($config['extract_zip_to_named_folder'])) {
        $folderName = pathinfo(basename($zipPath), PATHINFO_FILENAME);
        $folderName = clean_name($folderName ?: 'extracted');
        $target = $parent . DIRECTORY_SEPARATOR . $folderName;
        $targetRel = trim(($parentRel ? $parentRel . '/' : '') . $folderName, '/');
        if (!is_dir($target)) {
            mkdir($target, 0755, true);
        }
    }

    if (!is_writable($target)) {
        json_response(['ok' => false, 'error' => 'Extract target is not writable.'], 500);
    }

    $zip = new ZipArchive();
    if ($zip->open($zipPath) !== true) {
        json_response(['ok' => false, 'error' => 'Could not open ZIP archive.'], 500);
    }

    $count = 0;
    $skipped = 0;

    for ($i = 0; $i < $zip->numFiles; $i++) {
        $name = $zip->getNameIndex($i);
        if ($name === false) {
            $skipped++;
            continue;
        }

        $safe = safe_zip_entry($name);
        if ($safe === null) {
            $skipped++;
            continue;
        }

        $dest = $target . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $safe);

        $destParent = dirname($dest);
        $baseReal = realpath($target);
        if ($baseReal === false) {
            $zip->close();
            json_response(['ok' => false, 'error' => 'Extract target is invalid.'], 500);
        }

        if (!is_dir($destParent)) {
            mkdir($destParent, 0755, true);
        }

        $parentReal = realpath($destParent);
        if ($parentReal === false || strpos($parentReal, $baseReal) !== 0) {
            $skipped++;
            continue;
        }

        if (str_ends_with($name, '/')) {
            if (!is_dir($dest)) {
                mkdir($dest, 0755, true);
            }
            continue;
        }

        $stream = $zip->getStream($name);
        if (!$stream) {
            $skipped++;
            continue;
        }

        $out = fopen($dest, 'wb');
        if (!$out) {
            fclose($stream);
            $skipped++;
            continue;
        }

        stream_copy_to_stream($stream, $out);
        fclose($stream);
        fclose($out);
        $count++;
    }

    $zip->close();

    json_response([
        'ok' => true,
        'message' => "Extracted {$count} file(s)." . ($skipped ? " Skipped {$skipped} unsafe/unreadable item(s)." : ''),
        'path' => $rel,
        'target_path' => $targetRel,
        'extracted' => $count,
        'skipped' => $skipped,
    ]);
}

json_response(['ok' => false, 'error' => 'Unknown action.'], 400);
