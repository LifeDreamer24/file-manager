# File Manager

A clean, responsive, server-hosted file manager built for browsing, editing, uploading, organizing, and sharing files directly from a web browser.

This project was built step by step with **OpenAI / ChatGPT** and customized into a polished private file management interface.

## Features

- Password-protected file manager interface
- Browse folders and files from the web
- Create new files and folders
- Upload files or folders
- Drag-and-drop uploads
- Per-file upload queue with aggregate progress, cancellation, and retry
- Safe conflict choices: skip, keep both, or replace
- Rename files and folders
- Move files and folders with a folder picker
- Delete files and folders
- Download files
- Download folders or selected items as ZIP files
- Extract ZIP archives
- Copy public/direct URLs for files and folders
- Play supported audio and video files without leaving the manager
- Seek through media with authenticated byte-range streaming
- Multi-select files and folders
- Bulk actions for selected items
- Built-in file editor
- Save edits directly to the server
- Download from the editor
- Copy the current file URL from the editor
- Line numbers in the editor
- Word wrap toggle
- Edit-conflict detection and atomic saves
- Basic formatting tools
- Mobile-friendly responsive layout
- Compact mobile file rows
- Three-dots action menus for clean file lists
- System-aware light and dark themes with a visible three-state toggle

## Mobile Support

The interface has been tuned for mobile devices with:

- Large touch-friendly buttons
- Responsive toolbar layout
- Compact file cards
- Shortened long file names using `(...)`
- Action menus instead of crowded row buttons
- Mobile-safe scrolling behavior
- Improved iPhone/Safari layout handling

## Installation

Upload the project files to a PHP-enabled web server.

A typical structure looks like this:

```text
public_html/
├── index.php
├── api.php
├── bootstrap.php
├── config.php
├── assets/
│   ├── app.css
│   └── app.js
├── .htaccess
├── README.md
└── files/
```

The `files/` folder is the managed file storage area.

## Configuration

Open `config.php` and adjust the settings for your server.

At minimum, set a strong password for the file manager login.

The recommended production setup is an environment variable:

```text
FILE_MANAGER_PASSWORD=use-a-long-unique-password
```

You can alternatively generate a `password_hash()` value and set
`FILE_MANAGER_PASSWORD_HASH`, or configure the returned array directly:

```php
return [
    'password' => 'use-a-long-unique-password',
    // ...the remaining settings
];
```

The application refuses to log in while the default `change-this-password`
placeholder is still active.

## Usage

Open the site in your browser, log in, then use the interface to manage files.

Common actions:

- Click a folder name to open it.
- Click an editable file name to open it in the editor.
- Click a supported audio or video file to open the built-in media player.
- Use the three-dots menu on each row for file or folder actions.
- Use checkboxes to select multiple items and run bulk actions.
- Use **New** to create files or folders.
- Use **Upload** to upload files or folders.

## Security Notes

The file manager interface is password-protected, but files may still be accessible through their direct public URLs depending on your server configuration.

This is useful when you want shareable direct links, but keep these notes in mind:

- Password protection controls access to the manager interface.
- Direct file URLs may still be readable if someone has the exact link.
- Editing, moving, deleting, uploading, extracting, and other management actions require login.
- Do not store private or sensitive files in a publicly accessible folder unless your server is configured to block direct access.
- Keep backups of important files.
- Use HTTPS.
- Use a strong password.
- Do not upload this tool to an untrusted or shared environment without reviewing the configuration.
- Uploading, extracting, renaming, and creating files use the same protected-name policy.
- Symbolic links are rejected and paths are constrained to the managed directory.
- ZIP archives are checked for unsafe paths, scripts, symlinks, excessive size,
  excessive entry counts, and suspicious compression ratios before extraction.
- Existing files are skipped unless you explicitly choose another conflict policy.
- Login attempts are rate limited, sessions rotate after login, and
  state-changing API requests require a CSRF token.
- `files/.htaccess` disables script execution and forces active HTML/SVG/XML
  content to download instead of running with the manager's authenticated origin.

For strongest isolation, serve `public_base_url` from a separate, cookie-free
subdomain in addition to the included server rules.

## Protected Files

The manager blocks or avoids handling certain protected/system files to reduce the chance of accidentally breaking the site or exposing sensitive server behavior.

Examples may include files such as:

```text
.htaccess
.php
configuration/system files
hidden server files
```

This helps prevent accidental uploads or edits that could affect the file manager itself or the server.

## Requirements

- PHP 8.0 or newer
- ZIP support in PHP for ZIP download/extract features
- Apache 2.4 with `mod_headers` is recommended for all included hardening rules
- A modern web browser

## Verification

Run the regression checks with:

```bash
php tests/security_regression.php
```

GitHub Actions also performs PHP and JavaScript syntax checks and runs the
security regression suite on every push and pull request.

## Notes

Some behavior depends on the browser and server. For example:

- Certain file downloads may be renamed by the browser.
- Folder upload support depends on the browser.
- Direct URL behavior depends on server rules and `.htaccess` configuration.
- ZIP extraction requires server-side ZIP support.
- Playback support depends on the browser and the codecs used inside each file.

The player recognizes MP3, WAV, OGG/OGA, Opus, M4A, AAC, FLAC, MP4/M4V,
WebM, OGV, and MOV files. The authenticated stream endpoint supports byte-range
requests so compatible files can seek without downloading the whole file first.

## Credits

Created and refined with help from **OpenAI / ChatGPT**.

Special thanks to **LifeDreamer24** for the idea, testing, design direction, and many rounds of polishing.

## License

This project is released into the public domain under the [Unlicense](LICENSE).
