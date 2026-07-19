<?php
/*
  FastDL Web File Manager configuration

  IMPORTANT:
  1. Change the password below before uploading publicly.
  2. Put your FastDL files inside the "files" folder, or change base_dir/public_base_url.
  3. Make sure the web server user can write to files/folders you want to edit.
*/

return [
    // You can also set the FASTDL_MANAGER_PASSWORD environment variable instead.
    'password' => 'change-this-password',

    // Preferred alternative: a password_hash() value. You can also set
    // FASTDL_MANAGER_PASSWORD_HASH in the server environment.
    'password_hash' => '',

    // Authentication/session protection.
    'login_max_attempts' => 5,
    'login_window_seconds' => 15 * 60,
    'login_lockout_seconds' => 15 * 60,
    'session_lifetime_seconds' => 12 * 60 * 60,

    // Folder that will be managed/edited.
    'base_dir' => __DIR__ . '/files',

    // Public URL prefix used for Download / Copy URL.
    // Example: 'https://example.com/fastdl/'
    // Default works when your FastDL files are inside ./files/.
    'public_base_url' => 'files/',

    // Maximum text file size allowed in the editor.
    'max_edit_size' => 2 * 1024 * 1024,

    // Maximum upload size checked by the app.
    // Your PHP upload_max_filesize and post_max_size must also allow the file size.
    'max_upload_size' => 512 * 1024 * 1024,

    // Existing upload/extraction destination policy: skip, keep_both, or replace.
    // The interface sends the user's choice; this is the safe API fallback.
    'default_conflict_policy' => 'skip',

    // ZIP extraction resource limits.
    'max_zip_entries' => 5000,
    'max_zip_entry_size' => 512 * 1024 * 1024,
    'max_zip_uncompressed_size' => 2 * 1024 * 1024 * 1024,
    'max_zip_expansion_ratio' => 200,

    // Block dangerous/system files from being uploaded.
    // This prevents confusing hidden uploads and reduces server-side security risks.
    'block_system_uploads' => true,

    // Block names exactly matching these values.
    'blocked_upload_names' => [
        '.htaccess', '.htpasswd', '.user.ini', 'web.config',
        'php.ini', 'composer.json', 'composer.lock',
        'package.json', 'package-lock.json',
        'index.php', 'api.php', 'config.php'
    ],

    // Block any upload whose filename starts with a dot.
    // Examples: .htaccess, .env, .gitignore
    'block_dotfile_uploads' => true,

    // Block executable/server-side script extensions.
    // Add or remove extensions depending on your host.
    'blocked_upload_extensions' => [
        'php', 'php3', 'php4', 'php5', 'php7', 'php8',
        'phtml', 'phar', 'cgi', 'pl', 'py', 'rb',
        'asp', 'aspx', 'jsp', 'jspx', 'sh', 'bash',
        'exe', 'dll', 'so', 'bat', 'cmd', 'ps1'
    ],

    // File backups are disabled. Saved edits write directly to the file.
    'create_backups' => false,
    'backup_dir' => __DIR__ . '/.backups',

    // Allow deleting non-empty folders recursively.
    'allow_recursive_delete' => true,

    // Allow ZIP extraction. Requires PHP ZipArchive extension.
    'allow_zip_extract' => true,

    // Extract ZIPs into a folder with the same name as the archive.
    // Example: maps.zip -> maps/
    'extract_zip_to_named_folder' => true,

    // Hidden files/folders in the file browser.
    'hidden_names' => [
        '.', '..', '.git', '.github', '.htaccess',
        'index.php', 'api.php', 'config.php'
    ],

    // File types allowed in the text editor.
    'editable_extensions' => [
        'txt', 'cfg', 'ini', 'json', 'js', 'mjs', 'cjs', 'ts', 'tsx', 'jsx',
        'html', 'htm', 'xml', 'css', 'scss', 'md', 'yml', 'yaml', 'toml',
        'csv', 'log', 'res', 'vmt', 'qc', 'lua', 'java', 'cs',
        'cpp', 'c', 'h', 'hpp', 'go', 'rs', 'sql', 'svg'
    ],
];
