<?php
declare(strict_types=1);

define('FILE_MANAGER_TESTING', true);
require_once dirname(__DIR__) . '/api.php';

function check(bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
    fwrite(STDOUT, "PASS: {$message}\n");
}

function cleanup_test_tree(string $path): void {
    if (is_link($path) || is_file($path)) {
        @unlink($path);
        return;
    }
    if (!is_dir($path)) return;
    foreach (scandir($path) ?: [] as $name) {
        if ($name === '.' || $name === '..') continue;
        cleanup_test_tree($path . DIRECTORY_SEPARATOR . $name);
    }
    @rmdir($path);
}

$config = require dirname(__DIR__) . '/config.php';

check(normalize_return_path('stuff-and-more') === 'stuff-and-more', 'login deep-link folder is preserved');
check(normalize_return_path('/maps/archive/') === 'maps/archive', 'nested login path is normalized');
check(normalize_return_path('../config.php') === '', 'unsafe login redirect path is rejected');

$environmentPassword = 'test-password-' . bin2hex(random_bytes(6));
putenv('FILE_MANAGER_PASSWORD=' . $environmentPassword);
check(app_password_configured($config), 'FILE_MANAGER_PASSWORD configures authentication');
check(verify_app_password($config, $environmentPassword), 'FILE_MANAGER_PASSWORD is accepted at login');
putenv('FILE_MANAGER_PASSWORD');

check(path_is_within('/srv/files/maps', '/srv/files'), 'child path is accepted');
check(path_is_within('/srv/files', '/srv/files'), 'base path is accepted');
check(!path_is_within('/srv/files-private', '/srv/files'), 'similarly prefixed sibling is rejected');

[$allowedPhp] = entry_name_allowed($config, 'payload.php');
[$allowedDotfile] = entry_name_allowed($config, '.htaccess');
[$allowedText] = entry_name_allowed($config, 'server.cfg');
check(!$allowedPhp, 'server-side script extension is blocked');
check(!$allowedDotfile, 'server configuration dotfile is blocked');
check($allowedText, 'normal managed content remains allowed');

[$zipPathAllowed] = entry_path_allowed($config, 'maps/subfolder/test.cfg');
[$zipScriptAllowed] = entry_path_allowed($config, 'maps/payload.php');
check($zipPathAllowed, 'safe ZIP entry path is accepted');
check(!$zipScriptAllowed, 'blocked extension inside ZIP is rejected');
check(safe_zip_entry('../outside.txt') === null, 'ZIP traversal path is rejected');

$_SERVER['REMOTE_ADDR'] = 'test-' . bin2hex(random_bytes(6));
$rateConfig = array_merge($config, ['login_max_attempts' => 3, 'login_window_seconds' => 60, 'login_lockout_seconds' => 60]);
clear_login_rate_state();
check(login_retry_after($rateConfig) === 0, 'new login client is not rate limited');
record_failed_login($rateConfig);
record_failed_login($rateConfig);
check(record_failed_login($rateConfig) > 0, 'repeated login failures trigger a lockout');
clear_login_rate_state();

$root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'file-manager-test-' . bin2hex(random_bytes(6));
$outside = $root . '-outside';
mkdir($root, 0700, true);
mkdir($outside, 0700, true);

try {
    check(ensure_safe_directory_tree($root, 'one/two'), 'safe nested directory tree is created');
    if (function_exists('symlink') && @symlink($outside, $root . DIRECTORY_SEPARATOR . 'link')) {
        check(!ensure_safe_directory_tree($root, 'link/escaped'), 'symlinked directory tree is rejected');
    }

    $file = $root . DIRECTORY_SEPARATOR . 'example.txt';
    check(atomic_write($file, 'first'), 'new file is written atomically');
    $version = file_version($file);
    check($version !== '', 'file version token is generated');
    check(atomic_write($file, 'second'), 'existing file is replaced atomically');
    check(file_get_contents($file) === 'second', 'atomic replacement contains the complete new content');
    check(file_version($file) !== $version, 'file version changes after replacement');

    [$skipAction] = resolve_destination($file, 'skip');
    [$replaceAction, $replacePath] = resolve_destination($file, 'replace');
    [$keepAction, $keepPath] = resolve_destination($file, 'keep_both');
    check($skipAction === 'skip', 'skip conflict policy preserves existing file');
    check($replaceAction === 'write' && $replacePath === $file, 'replace policy targets the existing file');
    check($keepAction === 'write' && $keepPath !== $file, 'keep-both policy creates a distinct destination');
} finally {
    cleanup_test_tree($root);
    cleanup_test_tree($outside);
}

fwrite(STDOUT, "All security regression checks passed.\n");
