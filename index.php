<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
start_secure_session();
send_security_headers();
$config = require __DIR__ . '/config.php';

$error = '';
$requestedPath = normalize_return_path((string)($_POST['path'] ?? $_GET['path'] ?? ''));
$passwordConfigured = app_password_configured($config);
if (!$passwordConfigured) {
    $error = 'Set FILE_MANAGER_PASSWORD, FILE_MANAGER_PASSWORD_HASH, or a secure value in config.php before signing in.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'logout') {
    if (!csrf_is_valid((string)($_POST['csrf_token'] ?? ''))) {
        http_response_code(403);
        $error = 'Invalid security token. Please refresh and try again.';
    } else {
        $returnPath = normalize_return_path((string)($_POST['path'] ?? ''));
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
        }
        session_destroy();
        $location = app_base_url();
        if ($returnPath !== '') $location .= '?path=' . rawurlencode($returnPath);
        header('Location: ' . $location);
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'login') {
    if (!csrf_is_valid((string)($_POST['csrf_token'] ?? ''))) {
        http_response_code(403);
        $error = 'Invalid security token. Please refresh and try again.';
    } elseif (!$passwordConfigured) {
        http_response_code(503);
        $error = 'Set a secure manager password before signing in.';
    } elseif (($retryAfter = login_retry_after($config)) > 0) {
        http_response_code(429);
        header('Retry-After: ' . $retryAfter);
        $error = 'Too many failed attempts. Try again in ' . (int)ceil($retryAfter / 60) . ' minute(s).';
    } else {
        $password = (string)($_POST['password'] ?? '');
        if (verify_app_password($config, $password)) {
            clear_login_rate_state();
            session_regenerate_id(true);
            $_SESSION['file_manager_logged_in'] = true;
            $_SESSION['file_manager_login_time'] = time();
            $location = app_base_url();
            if ($requestedPath !== '') $location .= '?path=' . rawurlencode($requestedPath);
            header('Location: ' . $location);
            exit;
        }
        $retryAfter = record_failed_login($config);
        if ($retryAfter > 0) {
            http_response_code(429);
            header('Retry-After: ' . $retryAfter);
            $error = 'Too many failed attempts. Try again later.';
        } else {
            $error = 'Wrong password.';
        }
    }
}

$sessionLifetime = max(300, (int)($config['session_lifetime_seconds'] ?? 43200));
if (is_logged_in() && time() - (int)($_SESSION['file_manager_login_time'] ?? 0) > $sessionLifetime) {
    $_SESSION = [];
    session_destroy();
    $location = app_base_url();
    if ($requestedPath !== '') $location .= '?path=' . rawurlencode($requestedPath);
    header('Location: ' . $location);
    exit;
}

$loggedIn = is_logged_in();
$appName = htmlspecialchars((string)($config['app_name'] ?? 'File Manager'), ENT_QUOTES, 'UTF-8');
$loginAction = app_base_url() . ($requestedPath !== '' ? '?path=' . rawurlencode($requestedPath) : '');
$faviconVersion = (string)(filemtime(__DIR__ . '/assets/favicon.svg') ?: 1);
$cssVersion = (string)(filemtime(__DIR__ . '/assets/app.css') ?: 1);
$loginThemeJsVersion = (string)(filemtime(__DIR__ . '/assets/login-theme.js') ?: 1);
$jsVersion = (string)(filemtime(__DIR__ . '/assets/app.js') ?: 1);
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title><?= $appName ?></title>
  <meta name="description" content="Server-hosted file browser and editor for your content." />
  <meta name="theme-color" content="#0f1115" media="(prefers-color-scheme: dark)" />
  <meta name="theme-color" content="#f4f6fa" media="(prefers-color-scheme: light)" />
  <meta name="csrf-token" content="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>" />
  <link rel="icon" type="image/svg+xml" href="assets/favicon.svg?v=<?= $faviconVersion ?>" />
  <link rel="stylesheet" href="assets/app.css?v=<?= $cssVersion ?>" />
</head>
<body>
<?php if (!$loggedIn): ?>
  <script src="assets/login-theme.js?v=<?= $loginThemeJsVersion ?>"></script>
  <main class="login-wrap">
    <section class="login-card">
      <div class="login-card-head">
        <div>
          <h1><?= $appName ?></h1>
          <p class="subtitle">Log in to manage and edit your hosted files.</p>
        </div>
        <button id="themeToggle" class="theme-toggle" type="button" title="Theme: System" aria-label="Theme: System">
          <span id="themeToggleIcon" aria-hidden="true">◐</span>
        </button>
      </div>
      <?php if ($error): ?><div class="error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
      <form method="post" action="<?= htmlspecialchars($loginAction, ENT_QUOTES, 'UTF-8') ?>">
        <input type="hidden" name="action" value="login" />
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>" />
        <input type="hidden" name="path" value="<?= htmlspecialchars($requestedPath, ENT_QUOTES, 'UTF-8') ?>" />
        <input type="password" name="password" placeholder="Password" autocomplete="current-password" autofocus required />
        <button type="submit" <?= $passwordConfigured ? '' : 'disabled' ?>>Log in</button>
      </form>
      <p class="footer">LifeDreamer24 · Released under the Unlicense</p>
    </section>
  </main>
<?php else: ?>
  <main class="wrap">
    <header>
      <div>
        <h1><?= $appName ?></h1>
        <p class="subtitle">Server-hosted file browser and editor for your content.</p>
      </div>
      <div class="header-actions">
        <button id="themeToggle" class="theme-toggle" type="button" title="Theme: System" aria-label="Theme: System">
          <span id="themeToggleIcon" aria-hidden="true">◐</span>
        </button>
        <div class="login-pill">
          <span class="badge-dot"></span>
          <span>Logged in</span>
          <form method="post" action="<?= htmlspecialchars(app_base_url(), ENT_QUOTES, 'UTF-8') ?>" class="logout-form">
            <input type="hidden" name="action" value="logout" />
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>" />
            <input id="logoutPath" type="hidden" name="path" value="<?= htmlspecialchars($requestedPath, ENT_QUOTES, 'UTF-8') ?>" />
          <button class="logout-icon" type="submit" title="Log out" aria-label="Log out">
            <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
              <path d="M12 3v9" stroke-width="2" stroke-linecap="round"/>
              <path d="M7.05 6.6a8 8 0 1 0 9.9 0" stroke-width="2" stroke-linecap="round"/>
            </svg>
          </button>
          </form>
        </div>
      </div>
    </header>

    <section class="toolbar" aria-label="File manager controls">
      <input id="search" type="search" placeholder="Search this folder..." autocomplete="off" />
      <div class="toolbar-actions">
        <div class="dropdown" id="newDropdown">
          <button id="newMenuBtn" type="button">New ▾</button>
          <div class="dropdown-menu" role="menu">
            <button id="newFileBtn" type="button">📄 File</button>
            <button id="newFolderBtn" type="button">📁 Folder</button>
          </div>
        </div>
        <div class="dropdown upload-dropdown" id="uploadDropdown">
          <button id="uploadMenuBtn" type="button">Upload ▾</button>
          <div class="dropdown-menu">
            <button type="button" data-upload="files">📄 Files</button>
            <button type="button" data-upload="folder">📁 Folder</button>
          </div>
        </div>
        <input id="uploadInput" type="file" multiple hidden />
        <input id="uploadFolderInput" type="file" webkitdirectory directory multiple hidden />
        <span class="toolbar-separator" aria-hidden="true"></span>
        <button id="refreshIndex" type="button">Refresh</button>
      </div>
    </section>

    <section class="panel" id="browserPanel">
      <div class="pathbar">
        <nav id="breadcrumbs" class="breadcrumbs" aria-label="Breadcrumb"></nav>
        <div id="stats" class="stats">Loading...</div>
      </div>
      <div id="uploadProgress" class="upload-progress" role="status" aria-live="polite" aria-atomic="true">
        <div class="upload-progress-head">
          <strong id="uploadProgressLabel" class="upload-progress-label">Preparing upload...</strong>
          <span id="uploadProgressPercent" class="upload-progress-percent">0%</span>
        </div>
        <div id="uploadProgressTrack" class="upload-progress-track" role="progressbar" aria-label="Upload progress" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0">
          <div id="uploadProgressBar" class="upload-progress-bar"></div>
        </div>
        <div id="uploadProgressDetail" class="upload-progress-detail"></div>
        <div class="upload-progress-actions">
          <button id="uploadCancel" type="button" class="action" hidden>Cancel</button>
          <button id="uploadRetry" type="button" class="action" hidden>Retry failed</button>
        </div>
      </div>
      <div id="content"><div class="message">Loading folder...</div></div>
    </section>
    <div id="selectionBarHost" class="selection-bar-host"></div>

    <p class="footer">LifeDreamer24 · Released under the Unlicense</p>
  </main>

  <div id="toast" class="toast"></div>
  <div id="uploadOverlay" class="upload-overlay" aria-hidden="true">
    <div class="upload-overlay-frame"></div>
    <div class="upload-overlay-card">
      <strong>Drop files or folders to upload</strong>
      <span>Files will be uploaded in the current directory.</span>
    </div>
  </div>

  <div id="editorModal" class="editor-modal" aria-hidden="true">
    <section id="editorShell" class="editor editor-shell" role="dialog" aria-modal="true" aria-labelledby="editorName" aria-describedby="editorPath">
      <div class="editor-head">
        <div class="editor-title">
          <strong id="editorName">No file selected</strong>
          <span id="editorPath">Click a text file name from the file browser to edit it.</span>
        </div>
        <div class="editor-head-actions">
          <div id="editorStatus" class="editor-status">Idle</div>
          <button id="closeEditor" class="move-close" type="button" aria-label="Close editor" title="Close editor"><span aria-hidden="true">×</span></button>
        </div>
      </div>

      <div class="editor-tools">
        <button id="saveFile" type="button" disabled>Save</button>
        <button id="downloadEditor" type="button" disabled>Download</button>
        <button id="copyFileUrl" type="button" disabled>Copy URL</button>
        <button id="previewFile" type="button" aria-pressed="false" disabled>Preview</button>

        <span class="spacer"></span>

        <div id="editorToolsMenuGroup" class="editor-tools-menu-group">
          <button id="editorToolsMenuBtn" class="editor-tools-menu-toggle" type="button" aria-haspopup="true" aria-expanded="false">Tools ▾</button>
          <div class="editor-tools-menu">
            <select id="syntaxMode" title="Formatter">
              <option value="auto">Auto format</option>
              <option value="plain">Plain text</option>
              <option value="json">JSON</option>
              <option value="html">HTML/XML</option>
              <option value="css">CSS</option>
              <option value="js">JavaScript</option>
              <option value="cfg">CFG/INI/RES/VMT</option>
            </select>
            <button id="formatFile" type="button" disabled>Format</button>
            <button id="trimLines" type="button" disabled>Trim lines</button>
            <button id="tabsToSpaces" type="button" disabled>Tabs → Spaces</button>
            <button id="wrapToggle" type="button" disabled>Wrap Off</button>
          </div>
        </div>
      </div>

      <div class="editor-workspace">
        <div id="editorBody" class="editor-body">
          <div id="lineNumbers" class="lines">1</div>
          <textarea id="editorText" spellcheck="false" disabled placeholder="File content will appear here..."></textarea>
        </div>
        <div id="editorPreview" class="editor-preview" hidden>
          <div class="editor-preview-head">
            <strong id="editorPreviewLabel">Rendered preview</strong>
            <span>Sandboxed preview</span>
          </div>
          <div id="editorPreviewContent" class="editor-preview-content" role="document" aria-label="Rendered file preview"></div>
        </div>
      </div>

      <div class="editor-note">
        <strong>Save</strong> writes directly to the server file.
      </div>
    </section>
  </div>

  <div id="moveModal" class="move-modal" aria-hidden="true">
    <section class="move-card" role="dialog" aria-modal="true" aria-labelledby="moveTitle">
      <div class="move-head">
        <div class="move-title">
          <strong id="moveTitle">Move selected items</strong>
          <span id="moveSelectedCount">Choose the destination folder.</span>
        </div>
        <button id="moveClose" class="move-close" type="button" aria-label="Close move window"><span aria-hidden="true">×</span></button>
      </div>
      <div class="move-browse">
        <div id="moveBreadcrumbs" class="move-path" aria-label="Move destination breadcrumb"></div>
        <div class="move-current">Destination: <code id="moveCurrentPath">root</code></div>
      </div>
      <div id="moveFolderList" class="move-list"></div>
      <div class="move-foot">
        <span id="moveHint" class="move-hint">Open a folder below, then click Move here.</span>
        <div class="bulk-actions">
          <button id="moveCancel" class="action" type="button">Cancel</button>
          <button id="moveConfirm" class="action move-primary" type="button">Move here</button>
        </div>
      </div>
    </section>
  </div>

  <div id="conflictModal" class="move-modal" aria-hidden="true">
    <section class="move-card conflict-card" role="dialog" aria-modal="true" aria-labelledby="conflictTitle">
      <div class="move-head">
        <div class="move-title">
          <strong id="conflictTitle">Existing items</strong>
          <span id="conflictMessage">Choose what should happen when an item already exists.</span>
        </div>
      </div>
      <div class="conflict-options">
        <button type="button" class="action" data-conflict="skip"><strong>Skip existing</strong><span>Keep the current files unchanged.</span></button>
        <button type="button" class="action" data-conflict="keep_both"><strong>Keep both</strong><span>Create a numbered copy.</span></button>
        <button type="button" class="action danger" data-conflict="replace"><strong>Replace</strong><span>Overwrite existing files.</span></button>
      </div>
      <div class="move-foot">
        <span class="move-hint">Skip existing is the safest choice.</span>
        <button id="conflictCancel" class="action" type="button">Cancel</button>
      </div>
    </section>
  </div>

  <div id="mediaModal" class="media-modal" aria-hidden="true">
    <section class="media-card" role="dialog" aria-modal="true" aria-labelledby="mediaTitle">
      <div class="media-head">
        <div class="media-title">
          <strong id="mediaTitle">Media player</strong>
          <span id="mediaPath"></span>
        </div>
        <button id="mediaClose" class="move-close" type="button" aria-label="Close media player"><span aria-hidden="true">×</span></button>
      </div>
      <div id="mediaStage" class="media-stage">
        <div id="audioArtwork" class="audio-artwork" hidden aria-hidden="true">
          <span>♫</span>
        </div>
        <video id="videoPlayer" controls preload="metadata" playsinline hidden></video>
        <audio id="audioPlayer" controls preload="metadata" hidden></audio>
        <div id="mediaMessage" class="media-message" role="status" aria-live="polite">Loading media...</div>
      </div>
      <div class="media-foot">
        <span id="mediaFormat" class="media-format"></span>
        <div class="media-actions">
          <a id="mediaDownload" class="action" href="#" download>Download</a>
          <button id="mediaDone" class="action" type="button">Close</button>
        </div>
      </div>
    </section>
  </div>

  <script src="assets/app.js?v=<?= $jsVersion ?>" defer></script>
<?php endif; ?>
</body>
</html>
