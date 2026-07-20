# File Manager

A polished, self-hosted file manager for browsing and managing one server directory from a web browser.

The project uses PHP, vanilla JavaScript, and CSS. It has no database, framework, package installation, or frontend build step, making it suitable for straightforward deployment on conventional PHP hosting.

## Current feature set

### File management

- Browse folders with breadcrumbs, per-folder search, file sizes, and modification dates.
- Create, rename, move, download, and delete files or folders.
- Move items with the folder picker or by dragging rows onto a destination folder.
- Download a folder or any multi-selection as a ZIP archive.
- Extract ZIP archives into named folders.
- Copy configured public URLs for individual or selected items.
- Select multiple items for ZIP download, URL copying, moving, extraction, or deletion.
- Use compact three-dot row menus without crowding the file list.

### Uploads

- Upload individual files, multiple files, or complete folder trees.
- Drop files and folders anywhere on the page to upload them into the current directory.
- Run up to two uploads concurrently with aggregate byte and percentage progress.
- Cancel an active queue and retry files that failed or were not started.
- Resolve existing names with **Skip existing**, **Keep both**, or **Replace**.
- Report blocked files and upload failures instead of failing silently.

The configured per-file limit is 512 MiB by default. Your PHP and hosting limits, including `upload_max_filesize` and `post_max_size`, may impose a lower limit.

### Text editor

- Open supported text files in a focused modal editor.
- Protect unsaved work when closing the modal, pressing Escape, clicking the backdrop, refreshing, or leaving the page.
- Save directly to the server with atomic writes and edit-conflict detection.
- View synchronized line numbers and toggle line wrapping.
- Toggle a live rendered preview for Markdown, HTML, and SVG files while editing.
- Render previews inside a sandbox that blocks scripts, forms, and external resources.
- Download the edited buffer or copy the file's public URL.
- Auto-format JSON, HTML/XML, CSS, and JavaScript, with whitespace cleanup for configuration and plain-text formats.
- Trim trailing whitespace and convert tabs to spaces.

Editable file types and the default 2 MiB editor limit are controlled in `config.php`. Automatic backups are disabled by default.

### Media player

- Play supported audio and video in a modal without leaving the manager.
- Seek through authenticated media streams using HTTP byte-range requests.
- Start playback at 25% volume.
- Download the currently opened media file from the player.

Recognized formats are MP3, WAV, OGG/OGA, Opus, M4A, AAC, FLAC, MP4/M4V, WebM, OGV, and MOV. The player checks the browser's reported MIME support first, so unsupported formats download instead of opening with only a partially decoded track. Actual playback still depends on the codecs available in the browser.

### Interface

- Responsive desktop and mobile layouts with touch-friendly controls.
- Compact mobile rows and menus designed for narrow portrait screens.
- System, light, and dark themes through a persistent three-state toggle.
- URL-based folder navigation, including path preservation through login and logout.
- Accessible modal dialogs, focus restoration, keyboard closing, status messages, and upload feedback.
- Included `FM` favicon and a dependency-free interface.

## Requirements

- PHP 8.0 or newer
- A writable directory for managed content
- A modern web browser
- PHP `ZipArchive` for folder/batch ZIP downloads and ZIP extraction
- Apache 2.4 is recommended because the included hardening rules use `.htaccess`
- Apache `mod_headers` is recommended for all included response headers

Other web servers can run the application, but equivalent access-control, script-blocking, content-type, and directory-listing rules must be configured separately.

## Installation

1. Upload or clone the repository into a PHP-enabled web directory.
2. Preserve the included `.htaccess` files.
3. Make `files/` writable by the web-server user.
4. Configure a strong manager password.
5. Open `index.php` in a browser and sign in.

The default project layout is:

```text
file-manager/
├── .github/workflows/ci.yml
├── assets/
│   ├── app.css
│   ├── app.js
│   └── favicon.svg
├── files/
│   └── .htaccess
├── tests/
│   └── security_regression.php
├── .htaccess
├── api.php
├── bootstrap.php
├── config.php
├── index.php
├── LICENSE
└── README.md
```

`files/` is the managed storage root by default. The application files themselves are outside that root and are not exposed in the manager.

## Password configuration

The recommended production configuration is an environment variable:

```text
FILE_MANAGER_PASSWORD=use-a-long-unique-password
```

For a hashed password, set a value produced by PHP's `password_hash()` through:

```text
FILE_MANAGER_PASSWORD_HASH=your-password-hash
```

You can alternatively set `password` or `password_hash` in `config.php`. The application refuses login while the committed `change-this-password` placeholder is still active.

## Main configuration options

All application settings are documented in `config.php`. Important defaults include:

| Setting | Default | Purpose |
| --- | ---: | --- |
| `base_dir` | `files/` | Server directory managed by the application |
| `public_base_url` | `files/` | Base used when copying public URLs |
| `session_lifetime_seconds` | 12 hours | Maximum authenticated session age |
| `max_edit_size` | 2 MiB | Largest file accepted by the text editor |
| `max_upload_size` | 512 MiB | Per-file application upload limit |
| `default_conflict_policy` | `skip` | Safe default for an existing destination |
| `allow_recursive_delete` | `true` | Allows deletion of non-empty folders |
| `allow_zip_extract` | `true` | Enables ZIP extraction |
| `extract_zip_to_named_folder` | `true` | Extracts an archive into a same-named folder |

The configuration also contains editor extension allowlists, protected names and extensions, login rate limits, and ZIP safety limits.

## Security model

The manager interface and every management or authenticated download/stream API action require a valid login. The application also includes:

- CSRF validation for state-changing requests
- Session ID rotation after login, secure cookie settings, and session expiration
- Login attempt rate limiting
- Managed-root path validation and symbolic-link rejection
- Atomic saves with stale-edit detection
- Protected dotfiles, application files, and executable/server-side extensions
- ZIP path, symbolic-link, entry-count, expanded-size, and compression-ratio checks
- Security headers and script execution blocking through the included Apache rules
- `Options -Indexes` for the root and managed-content directories

### Important direct-link behavior

Password protection applies to the manager and its API; it does not automatically make `public_base_url` private. With the default configuration, someone who knows the exact URL of a file under `files/` may be able to read it directly.

The included `files/.htaccess` disables directory listing, blocks server-side scripts, and forces active HTML/SVG/XML content to download. Consequently, a direct folder URL normally does **not** produce a browsable file listing, while exact file URLs can remain shareable.

For private storage, deny direct web access to the managed directory and distribute files only through an authenticated endpoint. For the strongest separation when public links are desired, serve managed content from a separate cookie-free origin.

Always use HTTPS, keep backups of important files, and review the configuration before exposing the application publicly.

## Usage notes

- Click a folder name to open it.
- Click an editable text file to open the editor.
- Click recognized audio or video to open the media player.
- Other files download when their names are clicked.
- Use each row's three-dot menu for individual actions.
- Use the checkboxes for bulk actions.
- Use **New** to create a file or folder and **Upload** to choose files or a folder.
- Use **Refresh** when the managed directory may have changed outside the application.

This is a single-password, filesystem-backed manager. It does not provide separate user accounts, permissions, cloud synchronization, file version history, or automatic backups.

## Verification

Run the security regression suite with:

```bash
php tests/security_regression.php
```

Useful syntax checks are:

```bash
find . -name '*.php' -not -path './files/*' -print0 | xargs -0 -n1 php -l
node --check assets/app.js
```

GitHub Actions runs the PHP and JavaScript syntax checks plus the security regression suite on every push and pull request.

## Credits

Developed and refined with **OpenAI / ChatGPT**.

**LifeDreamer24** provided the original idea, product direction, testing, design decisions, and the many rounds of detail-focused polishing that shaped the project.

## License

Released into the public domain under the [Unlicense](LICENSE).
