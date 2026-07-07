# File Manager

A clean, responsive, server-hosted file manager built for browsing, editing, uploading, organizing, and sharing files directly from a web browser.

This project was built step by step with **OpenAI / ChatGPT** and customized into a polished private file management interface.

## Features

- Password-protected file manager interface
- Browse folders and files from the web
- Create new files and folders
- Upload files or folders
- Drag-and-drop uploads
- Rename files and folders
- Move files and folders with a folder picker
- Delete files and folders
- Download files
- Download folders or selected items as ZIP files
- Extract ZIP archives
- Copy public/direct URLs for files and folders
- Multi-select files and folders
- Bulk actions for selected items
- Built-in file editor
- Save edits directly to the server
- Download from the editor
- Copy the current file URL from the editor
- Line numbers in the editor
- Word wrap toggle
- Basic formatting tools
- Mobile-friendly responsive layout
- Compact mobile file rows
- Three-dots action menus for clean file lists
- Dark, modern green-accented theme

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
├── config.php
├── .htaccess
├── README.md
└── files/
```

The `files/` folder is the managed file storage area.

## Configuration

Open `config.php` and adjust the settings for your server.

At minimum, set a strong password for the file manager login.

Example:

```php
$PASSWORD = 'change-this-password';
```

Use a long unique password, especially if this is hosted publicly.

## Usage

Open the site in your browser, log in, then use the interface to manage files.

Common actions:

- Click a folder name to open it.
- Click an editable file name to open it in the editor.
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

- PHP-enabled web server
- ZIP support in PHP for ZIP download/extract features
- A modern web browser

## Notes

Some behavior depends on the browser and server. For example:

- Certain file downloads may be renamed by the browser.
- Folder upload support depends on the browser.
- Direct URL behavior depends on server rules and `.htaccess` configuration.
- ZIP extraction requires server-side ZIP support.

## Credits

Created and refined with help from **OpenAI / ChatGPT**.

Special thanks to **LifeDreamer24** for the idea, testing, design direction, and many rounds of polishing.

## License

Use and modify this project for your own server as needed.

Suggested footer:

```text
© 2026 LifeDreamer24. All rights reserved.
```
