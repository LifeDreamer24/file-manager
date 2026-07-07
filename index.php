<?php
declare(strict_types=1);

session_start();
$config = require __DIR__ . '/config.php';

function app_password(array $config): string {
    $env = getenv('FASTDL_MANAGER_PASSWORD');
    if (is_string($env) && $env !== '') return $env;
    return (string)($config['password'] ?? '');
}

function clean_app_url(): string {
    $dir = str_replace('\\', '/', dirname((string)($_SERVER['SCRIPT_NAME'] ?? '/')));
    $dir = rtrim($dir, '/');
    return ($dir === '' || $dir === '.') ? '/' : $dir . '/';
}

$error = '';

if (isset($_GET['logout'])) {
    $_SESSION = [];
    session_destroy();
    header('Location: ' . clean_app_url());
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'login') {
    $password = (string)($_POST['password'] ?? '');
    if (hash_equals(app_password($config), $password)) {
        $_SESSION['fastdl_manager_logged_in'] = true;
        header('Location: ' . clean_app_url());
        exit;
    }
    $error = 'Wrong password.';
}

$loggedIn = !empty($_SESSION['fastdl_manager_logged_in']);
$appName = htmlspecialchars((string)($config['app_name'] ?? 'File Manager'), ENT_QUOTES, 'UTF-8');
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
  <style>
    :root{--bg:#0f1115;--panel:#171a21;--panel2:#202633;--panel3:#11161d;--text:#edf2f7;--muted:#9aa8ba;--line:#303847;--accent:#42d392;--accent2:#8ff0c1;--soft:rgba(66,211,146,.14);--good:#42d392;--goodsoft:rgba(66,211,146,.14);--bad:#ff5d5d;--warn:#ffd166;--shadow:0 18px 45px rgba(0,0,0,.26);font-family:ui-sans-serif,system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;--mono:ui-monospace,SFMono-Regular,Menlo,Monaco,Consolas,"Liberation Mono","Courier New",monospace}
    body.light{--bg:#f4f6fa;--panel:#ffffff;--panel2:#eef2f7;--panel3:#f8fafc;--text:#111827;--muted:#667085;--line:#d7dee8;--soft:rgba(66,211,146,.16);--shadow:0 18px 45px rgba(31,41,55,.12)}
    *{box-sizing:border-box}
    html{background:var(--bg)}
    body{position:relative;margin:0;min-height:100vh;color:var(--text);background:var(--bg);transition:color .18s ease}
    body::before{content:"";position:absolute;left:0;top:0;right:0;bottom:0;width:100%;height:auto;min-height:100vh;z-index:0;pointer-events:none;--wrap-width:min(1120px,calc(100vw - 32px));--wrap-left:calc((100vw - var(--wrap-width)) / 2);--glow-x:calc(var(--wrap-left) + clamp(118px,13.5vw,185px));--glow-y:clamp(92px,5.6vw,120px);--glow-size:clamp(34rem,38vw,44rem);background:radial-gradient(circle var(--glow-size) at var(--glow-x) var(--glow-y),rgba(66,211,146,.24) 0,rgba(66,211,146,.18) 18%,rgba(66,211,146,.11) 38%,rgba(66,211,146,.05) 58%,rgba(66,211,146,0) 78%),linear-gradient(180deg,#141821 0%,var(--bg) 54%,#090b0f 100%);transform:none}
    body.light::before{background:radial-gradient(circle var(--glow-size) at var(--glow-x) var(--glow-y),rgba(66,211,146,.23) 0,rgba(66,211,146,.17) 18%,rgba(66,211,146,.10) 38%,rgba(66,211,146,.045) 58%,rgba(66,211,146,0) 78%),linear-gradient(180deg,#fff8f1 0%,var(--bg) 56%,#e9edf5 100%)}
    body>main{position:relative;z-index:1}
    a{color:inherit;text-decoration:none}
    .wrap{width:min(1120px,calc(100% - 32px));margin:0 auto;padding:36px 0 48px}
    header{display:flex;justify-content:space-between;align-items:flex-start;gap:18px;margin-bottom:22px}
    h1{margin:0 0 8px;font-size:clamp(1.8rem,4vw,3.15rem);line-height:1;letter-spacing:-.045em}
    .subtitle{margin:0;max-width:760px;color:var(--muted);line-height:1.5}
    .badge{display:inline-flex;align-items:center;gap:8px;padding:10px 12px;border:1px solid var(--line);border-radius:999px;background:rgba(255,255,255,.045);color:var(--muted);white-space:nowrap;font-size:.92rem}
    body.light .badge{background:rgba(255,255,255,.7)}
    .badge-dot{width:8px;height:8px;border-radius:50%;background:var(--good);box-shadow:0 0 12px var(--good);flex:0 0 auto}
    .header-actions{display:flex;align-items:center;gap:10px;flex-wrap:nowrap;justify-content:flex-end}
    .login-pill{display:inline-flex;align-items:center;gap:10px;padding:7px 7px 7px 12px;border:1px solid var(--line);border-radius:999px;background:rgba(255,255,255,.045);color:var(--muted);white-space:nowrap;font-size:.92rem}
    body.light .login-pill{background:rgba(255,255,255,.7)}
    .logout-icon{width:32px;height:32px;display:inline-grid;place-items:center;border:1px solid rgba(148,163,184,.32);border-radius:999px;background:rgba(255,255,255,.06);color:var(--text);transition:transform .12s ease,border-color .12s ease,background .12s ease,box-shadow .12s ease}
    .logout-icon svg{width:16px;height:16px;stroke:currentColor}
    .logout-icon:hover,.logout-icon:focus-visible{transform:translateY(-1px);border-color:rgba(66,211,146,.75);background:var(--goodsoft);box-shadow:0 0 0 3px rgba(66,211,146,.16);outline:0}
    body.light .logout-icon{background:rgba(17,24,39,.045)}
    .icon-btn{width:42px;height:42px;display:inline-grid;place-items:center;border-radius:999px;padding:0;font-size:1.05rem;background:rgba(255,255,255,.06);flex:0 0 auto}
    .copy-base-btn{white-space:nowrap}
    .dropdown{position:relative;z-index:1}
    .dropdown.open{z-index:120}
    .dropdown>button{min-width:96px}
    .dropdown-menu{position:absolute;top:calc(100% + 8px);right:0;z-index:121;display:none;min-width:170px;padding:6px;border:1px solid var(--line);border-radius:12px;overflow:hidden;background:var(--panel);box-shadow:var(--shadow)}
    body.light .dropdown-menu{background:rgba(255,255,255,.96)}
    .dropdown.open .dropdown-menu{display:grid;gap:4px;animation:toastIn .14s ease both}
     .dropdown-menu button{width:100%;text-align:left;border-radius:9px;padding:9px 10px;background:transparent}
    .dropdown-menu{background:#121820;border-color:rgba(66,211,146,.45);box-shadow:0 18px 45px rgba(0,0,0,.38),0 0 0 1px rgba(255,255,255,.045)}
    .dropdown-menu button{color:var(--text);background:rgba(255,255,255,.035)}
    .dropdown-menu button:hover,.dropdown-menu button:focus{color:#ffffff;background:rgba(66,211,146,.20);border-color:rgba(66,211,146,.75);box-shadow:none}
    body.light .dropdown-menu{background:#ffffff;border-color:rgba(66,211,146,.55);box-shadow:0 18px 45px rgba(31,41,55,.16),0 0 0 1px rgba(17,24,39,.045)}
    body.light .dropdown-menu button{color:#111827;background:rgba(17,24,39,.035)}
    body.light .dropdown-menu button:hover,body.light .dropdown-menu button:focus{color:#111827;background:rgba(66,211,146,.18)}
    .toolbar{position:relative;z-index:40;display:flex;align-items:center;gap:10px;margin:20px 0;flex-wrap:wrap}
    .toolbar input[type="search"]{flex:1 1 360px;min-width:240px}
    .toolbar-actions{position:relative;z-index:41;display:flex;align-items:center;gap:10px;flex-wrap:wrap;margin-left:auto}
    .toolbar-actions>button,.toolbar-actions>.action,.toolbar-actions>.dropdown>button{width:100px;min-width:100px;height:48px;display:inline-flex;align-items:center;justify-content:center;padding:0 12px;box-sizing:border-box;text-align:center;white-space:nowrap}
    .toolbar-separator{width:1px;height:34px;margin:0 4px 0 6px;background:rgba(148,163,184,.28);border-radius:999px;flex:0 0 auto}
    .logout-btn{white-space:nowrap}
    .toolbar .action{min-height:44px;padding:12px 14px;border-radius:12px;align-self:stretch;display:inline-flex;align-items:center;justify-content:center}
    .toolbar-actions>.action{padding:0 12px}
    input,select,button,textarea{font:inherit}
    input,select,button{border:1px solid var(--line);border-radius:12px;background:rgba(255,255,255,.055);color:var(--text);padding:12px 14px;outline:none}
    body.light input,body.light select,body.light button{background:rgba(255,255,255,.74)}
    input:focus,select:focus,textarea:focus{border-color:var(--good);box-shadow:0 0 0 3px rgba(66,211,146,.16)}
    button:hover,button:focus-visible{border-color:var(--good);box-shadow:0 0 0 3px rgba(66,211,146,.16)}
    button:focus:not(:focus-visible):not(:hover){border-color:var(--line);box-shadow:none}
    input,select,textarea{caret-color:var(--good)}
    select{color:var(--text);background-color:#151a22;color-scheme:dark}
    select option{color:#edf2f7;background-color:#171a21}
    select option:checked{color:#ffffff;background-color:#307857}
    body.light select{color:#111827;background-color:#ffffff;color-scheme:light}
    body.light select option{color:#111827;background-color:#ffffff}
    body.light select option:checked{color:#111827;background-color:#baf3d6}
    input[type="password"]::-ms-reveal,
    input[type="password"]::-ms-clear{
      filter: invert(1) brightness(2.2);
      opacity: 1;
    }
    input[type="search"]::-webkit-search-cancel-button{
      -webkit-appearance:none;
      appearance:none;
      width:16px;
      height:16px;
      border-radius:999px;
      cursor:pointer;
      opacity:.92;
      background:
        linear-gradient(45deg, transparent 42%, #ffffff 42%, #ffffff 58%, transparent 58%),
        linear-gradient(-45deg, transparent 42%, #ffffff 42%, #ffffff 58%, transparent 58%);
    }
    input[type="search"]::-webkit-search-cancel-button:hover{
      opacity:1;
      filter:drop-shadow(0 0 6px rgba(66,211,146,.38));
    }
    body.light input[type="search"]::-webkit-search-cancel-button{
      background:
        linear-gradient(45deg, transparent 42%, #111827 42%, #111827 58%, transparent 58%),
        linear-gradient(-45deg, transparent 42%, #111827 42%, #111827 58%, transparent 58%);
    }
    button{cursor:pointer;transition:transform .12s ease,border-color .12s ease,background .12s ease,box-shadow .12s ease}
    button:hover{transform:translateY(-1px);border-color:rgba(66,211,146,.75);background:var(--goodsoft)}
    button:disabled{opacity:.48;cursor:not-allowed;transform:none}
    .panel,.editor,.login-card{border:1px solid var(--line);border-radius:14px;background:rgba(23,26,33,.88);box-shadow:var(--shadow);backdrop-filter:blur(12px)}
    .panel{overflow:visible}
    .editor,.login-card{overflow:hidden}
    body.light .panel,body.light .editor,body.light .login-card{background:rgba(255,255,255,.88)}
    #browserPanel,#content,.file-table,.file-table tbody,.file-table tr{overflow:visible}

    #browserPanel{position:relative;z-index:20}

    #browserPanel>.pathbar{
      border-top-left-radius:14px;
      border-top-right-radius:14px;
    }
    #browserPanel .bulkbar:first-child,
    #browserPanel #content>.message:first-child{
      border-bottom-left-radius:14px;
      border-bottom-right-radius:14px;
    }

    .editor-shell{position:relative;z-index:5}
    #browserPanel .item-menu.open{z-index:300}
    #browserPanel .item-menu-list{z-index:301}
    .file-table td,.file-table th{overflow:hidden}
    .file-table td.actions-cell,.file-table th.actions-cell{overflow:visible;white-space:nowrap;padding-right:18px;text-align:right}
    .file-table td.size-cell,.file-table th.size-cell{padding-right:18px;text-align:right;white-space:nowrap}
    .size-value{display:inline-block;min-width:3.25ch;text-align:right}
    .file-table td.path-cell,.file-table th.path-cell{padding-right:18px}
    .pathbar{display:flex;justify-content:space-between;align-items:center;gap:14px;padding:14px 16px;border-bottom:1px solid var(--line);background:rgba(255,255,255,.035)}
    body.light .pathbar,body.light .editor-head{background:rgba(255,255,255,.55)}
    .breadcrumbs{display:flex;align-items:center;flex-wrap:wrap;gap:8px;color:var(--muted);font-size:.95rem}
    .breadcrumbs button{padding:6px 9px;border-radius:9px;background:transparent}
    .sep{color:#5e6977}
    .stats{color:var(--muted);font-size:.92rem;white-space:nowrap}
    .bulkbar{display:none;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;padding:10px 12px;border-bottom:1px solid var(--line);background:rgba(66,211,146,.08)}
    .bulkbar.show{display:flex}
    .bulk-summary{display:flex;align-items:center;gap:10px;color:var(--muted);font-size:.92rem;line-height:1.35}
    .bulk-actions{display:flex;align-items:center;gap:8px;flex-wrap:wrap}
    .bulk-actions .action{min-height:32px}
    .select-col{width:44px;text-align:center}
    input[type="checkbox"]{-webkit-appearance:none;appearance:none;width:18px;height:18px;min-width:18px;min-height:18px;padding:0;margin:0;border:1px solid #46515f;border-radius:4px;background:#2a313b;cursor:pointer;vertical-align:middle;position:relative;flex:0 0 auto;transition:border-color .12s ease,background-color .12s ease,box-shadow .12s ease}
    input[type="checkbox"]:hover{border-color:#5a6778}
    input[type="checkbox"]:focus-visible{border-color:var(--good);box-shadow:0 0 0 3px rgba(66,211,146,.16)}
    input[type="checkbox"]:checked,
    input[type="checkbox"]:indeterminate{background-color:var(--good);border-color:var(--good)}
    input[type="checkbox"]:checked::after{content:"";position:absolute;left:5px;top:1px;width:4px;height:9px;border:solid #5c6775;border-width:0 2px 2px 0;transform:rotate(45deg)}
    input[type="checkbox"]:indeterminate::after{content:"";position:absolute;left:3px;top:7px;width:10px;height:2px;border-radius:999px;background:#5c6775}
    body.light input[type="checkbox"]{border-color:#c6d0dc;background:#dde3eb}
    body.light input[type="checkbox"]:hover{border-color:#a9b5c4}
    body.light input[type="checkbox"]:checked::after,
    body.light input[type="checkbox"]:indeterminate::after{border-color:#425163;background:#425163}
    tbody tr.selected{background:rgba(66,211,146,.115)}
    .upload-overlay{position:fixed;inset:0;z-index:80;display:none;padding:26px;background:rgba(10,12,16,.58);backdrop-filter:blur(7px);pointer-events:none}
    .upload-overlay.show{display:grid;place-items:center;animation:overlayIn .14s ease both}
    .upload-overlay.hiding{display:grid;place-items:center;animation:overlayOut .18s ease both}
    .upload-overlay-frame{position:absolute;inset:18px;border:2px dashed rgba(66,211,146,.78);border-radius:24px;box-shadow:inset 0 0 0 1px rgba(255,255,255,.08)}
    .upload-overlay-card{position:relative;z-index:1;max-width:min(560px,calc(100vw - 72px));padding:24px;border:1px solid var(--line);border-radius:18px;background:rgba(23,26,33,.86);box-shadow:var(--shadow);text-align:center}
    body.light .upload-overlay{background:rgba(244,246,250,.58)}
    body.light .upload-overlay-card{background:rgba(255,255,255,.9)}
    .upload-overlay-card strong{display:block;margin-bottom:6px;font-size:1.2rem}
    .upload-overlay-card span{color:var(--muted);line-height:1.45}
    @keyframes overlayIn{from{opacity:0}to{opacity:1}}
    @keyframes overlayOut{from{opacity:1}to{opacity:0}}
    tr.dragging{opacity:.45}
    tr.drop-target{outline:2px solid var(--accent);outline-offset:-3px;background:rgba(66,211,146,.14)}
    .breadcrumbs button.drop-target{border-color:var(--accent);background:rgba(66,211,146,.14);color:var(--text)}
    table{width:100%;border-collapse:collapse}
    .file-table{table-layout:fixed}
    .file-table th:nth-child(1),.file-table td:nth-child(1){width:44px}
    .file-table th:nth-child(2),.file-table td:nth-child(2){width:28%}
    .file-table th:nth-child(3),.file-table td:nth-child(3){width:40%}
    .file-table th:nth-child(4),.file-table td:nth-child(4){width:96px}
    .file-table th:nth-child(5),.file-table td:nth-child(5){width:104px}
    th,td{padding:13px 14px;border-bottom:1px solid rgba(48,56,71,.66);text-align:left;vertical-align:middle;min-width:0;overflow:hidden}
    body.light th,body.light td{border-bottom-color:rgba(215,222,232,.9)}
    th{color:var(--muted);font-size:.78rem;text-transform:uppercase;letter-spacing:.08em;background:rgba(255,255,255,.025);user-select:none}
    body.light th{background:rgba(17,24,39,.035)}
    tbody tr:last-child td{border-bottom:none}
    tbody tr:hover{background:rgba(66,211,146,.075)}
    .name{display:flex;align-items:center;gap:10px;min-width:0;max-width:100%}
    .name .truncate-text{flex:1 1 auto;min-width:0}
    .path-cell .truncate-text{display:block;max-width:100%;white-space:nowrap;overflow:hidden}
    .truncate-text{display:block;max-width:100%;white-space:nowrap;overflow:hidden}
    .size-cell{white-space:normal;line-height:1.2}
    .name.file-name{cursor:pointer}
    .name.file-name:hover span:last-child{color:var(--accent2)}
    .icon{display:inline-grid;place-items:center;width:30px;height:30px;flex:0 0 auto;border-radius:9px;background:var(--panel2)}
    .folder .icon{color:#8ff0c1}.file .icon{color:#4ea1ff}
    .muted{color:var(--muted)}.right{text-align:right}
    .actions{display:flex;justify-content:flex-end;gap:8px;flex-wrap:wrap;width:100%}
    .action{display:inline-flex;align-items:center;min-height:32px;padding:7px 9px;border:1px solid var(--line);border-radius:9px;background:rgba(255,255,255,.035);color:var(--muted);font-size:.88rem}
    body.light .action{background:rgba(255,255,255,.78)}
    .action:hover{border-color:var(--good);background:var(--goodsoft);color:var(--text)}
    .danger:hover{border-color:var(--good);background:var(--goodsoft);color:var(--text)}
    .item-menu{position:relative;display:flex;justify-content:flex-end;z-index:2;width:100%}
    .item-menu.open{z-index:40}
    .item-menu-toggle{width:38px;min-width:38px;height:38px;min-height:38px;padding:0;border-radius:11px;justify-content:center;font-size:1.35rem;line-height:1;color:var(--text)}
    .item-menu-toggle:hover{transform:none}
    .item-menu.open .item-menu-toggle{border-color:rgba(66,211,146,.78);background:rgba(66,211,146,.16);box-shadow:0 0 0 3px rgba(66,211,146,.16)}
    .item-menu-list{position:absolute;top:calc(100% + 8px);right:0;z-index:18;display:none;min-width:178px;padding:6px;border:1px solid rgba(66,211,146,.45);border-radius:12px;background:#121820;box-shadow:0 18px 45px rgba(0,0,0,.38),0 0 0 1px rgba(255,255,255,.045)}
    .item-menu.open .item-menu-list{display:grid;gap:4px;animation:toastIn .14s ease both}
    .item-menu-list .action,.item-menu-list button{width:100%;justify-content:flex-start;text-align:left;min-height:36px;padding:9px 10px;border-radius:9px;color:var(--text);background:rgba(255,255,255,.035)}
    .item-menu-list .danger{color:var(--text)}
    body.light .item-menu-list{background:#ffffff;border-color:rgba(66,211,146,.55);box-shadow:0 18px 45px rgba(31,41,55,.16),0 0 0 1px rgba(17,24,39,.045)}
    body.light .item-menu-list .action,body.light .item-menu-list button{color:#111827;background:rgba(17,24,39,.035)}
    .message{padding:34px 20px;text-align:center;color:var(--muted);line-height:1.55}
    .error{color:var(--bad)}
    .footer{margin-top:18px;color:var(--muted);font-size:.9rem;line-height:1.5}
    .footer code{padding:2px 6px;border:1px solid var(--line);border-radius:7px;background:rgba(255,255,255,.075);color:var(--text)}
    .login-wrap{width:min(460px,calc(100% - 32px));min-height:100vh;margin:0 auto;display:grid;place-items:center;padding:32px 0}
    .login-card{width:100%;padding:24px}
    .login-card h1{font-size:2rem}
    .login-card form{display:grid;gap:12px;margin-top:18px}
    .login-card .error{padding:10px 12px;border:1px solid rgba(255,93,93,.4);border-radius:10px;background:rgba(255,93,93,.12)}
    .login-card .subtitle + .error{margin-top:14px}
    .upload-progress{display:none;margin:0 10px 10px;color:var(--muted);font-size:.9rem}
    .upload-progress.show{display:block}

    .editor-shell{display:grid;grid-template-rows:0fr;opacity:0;transform:translateY(-8px) scale(.992);transition:grid-template-rows .2s ease,opacity .16s ease,transform .16s ease;margin-top:12px}
    .editor-shell.open{grid-template-rows:1fr;opacity:1;transform:translateY(0) scale(1)}
    .editor-shell>div{min-height:0;overflow:hidden}
    .editor-head{display:flex;justify-content:space-between;align-items:flex-start;gap:12px;padding:14px 16px;border-bottom:1px solid var(--line);background:rgba(255,255,255,.035)}
    .editor-title{min-width:0}
    .editor-title strong{display:block;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
    .editor-title span{display:block;margin-top:3px;color:var(--muted);font-size:.88rem;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
    .editor-status{color:var(--muted);font-size:.9rem;white-space:nowrap}
    .editor-status.dirty{color:var(--warn);font-weight:700}
    .editor-tools{display:flex;align-items:center;gap:8px;flex-wrap:wrap;padding:10px;border-bottom:1px solid var(--line);background:rgba(255,255,255,.022)}
    body.light .editor-tools{background:rgba(17,24,39,.025)}
    .editor-tools button,.editor-tools select{padding:7px 9px;border-radius:9px;font-size:.9rem}
    .editor-tools .spacer{flex:1}
    .editor-body{display:grid;grid-template-columns:58px 1fr;min-height:430px;background:var(--panel3)}
    .lines{padding:14px 8px;text-align:right;color:var(--muted);border-right:1px solid var(--line);font-family:var(--mono);font-size:14px;line-height:1.55;white-space:pre;user-select:none;overflow:hidden}
    textarea{display:block;width:100%;min-width:0;min-height:430px;border:0;resize:vertical;outline:none;color:var(--text);background:transparent;padding:14px;font-family:var(--mono);font-size:14px;line-height:1.55;tab-size:2;white-space:pre;overflow:auto}
    textarea:focus{border-color:transparent;box-shadow:none}
    textarea.editor-word-wrap{white-space:pre-wrap;overflow-wrap:anywhere;word-break:normal}
    .editor-body:focus-within{box-shadow:inset 0 0 0 1px var(--good),0 0 0 3px rgba(66,211,146,.14)}
    .editor-body:focus-within .lines{border-right-color:rgba(66,211,146,.5)}
    .editor-note{padding:10px 14px;border-top:1px solid var(--line);color:var(--muted);font-size:.88rem;line-height:1.45;background:rgba(255,255,255,.025)}
    body.light .editor-note{background:rgba(17,24,39,.025)}
    .toast{position:fixed;right:18px;bottom:18px;z-index:90;display:none;max-width:min(520px,calc(100vw - 36px));padding:12px 14px;border:1px solid var(--line);border-radius:12px;background:var(--panel);box-shadow:0 18px 45px rgba(0,0,0,.32);color:var(--text);line-height:1.45}
    .toast.show{display:block;animation:toastIn .18s ease both}
    .toast.hiding{display:block;animation:toastOut .26s ease both}
    @keyframes toastIn{from{opacity:0;transform:translateY(8px) scale(.985)}to{opacity:1;transform:translateY(0) scale(1)}}
    @keyframes toastOut{from{opacity:1;transform:translateY(0) scale(1)}to{opacity:0;transform:translateY(8px) scale(.985)}}

    .move-modal{position:fixed;inset:0;z-index:90;display:none;place-items:center;padding:22px;background:rgba(8,10,14,.64);backdrop-filter:blur(8px)}
    .move-modal.show{display:grid;animation:overlayIn .14s ease both}
    .move-card{width:min(620px,calc(100vw - 32px));max-height:min(760px,calc(100vh - 44px));display:grid;grid-template-rows:auto auto 1fr auto;border:1px solid rgba(66,211,146,.34);border-radius:18px;background:rgba(23,26,33,.96);box-shadow:var(--shadow),0 0 0 1px rgba(255,255,255,.04);overflow:hidden}
    body.light .move-card{background:rgba(255,255,255,.97);box-shadow:var(--shadow),0 0 0 1px rgba(17,24,39,.05)}
    .move-head,.move-foot{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:14px 16px;border-bottom:1px solid var(--line)}
    .move-foot{border-top:1px solid var(--line);border-bottom:0;flex-wrap:wrap}
    .move-title{display:grid;gap:3px}
    .move-title strong{font-size:1.05rem}
    .move-title span,.move-hint{color:var(--muted);font-size:.9rem;line-height:1.35}
    .move-close{width:36px;height:36px;padding:0;border-radius:999px;display:grid;place-items:center;background:rgba(255,255,255,.045);font-size:22px;line-height:1;font-weight:700}
    .move-close span{display:block;line-height:1;transform:translateY(-1px)}
    .move-browse{display:grid;gap:10px;padding:12px 16px;border-bottom:1px solid var(--line);background:rgba(255,255,255,.028)}
    body.light .move-browse{background:rgba(17,24,39,.025)}
    .move-path{display:flex;align-items:center;flex-wrap:wrap;gap:7px;color:var(--muted);font-size:.93rem}
    .move-path button{padding:6px 9px;border-radius:9px;background:rgba(255,255,255,.035)}
    .move-current{display:flex;align-items:center;gap:8px;color:var(--text);font-size:.92rem}
    .move-current code{padding:3px 7px;border:1px solid var(--line);border-radius:8px;background:rgba(255,255,255,.055);color:var(--accent2);font-family:var(--mono);font-size:.85rem}
    .move-list{min-height:160px;max-height:390px;overflow:auto;padding:10px;background:rgba(0,0,0,.08)}
    body.light .move-list{background:rgba(17,24,39,.025)}
    .move-folder{width:100%;display:flex;align-items:center;gap:10px;margin:0 0 7px;padding:10px 11px;border-radius:12px;background:rgba(255,255,255,.04);color:var(--text);text-align:left}
    .move-folder:hover,.move-folder:focus-visible{background:rgba(66,211,146,.14);border-color:rgba(66,211,146,.7);box-shadow:none;transform:none}
    .move-folder:disabled{opacity:.42;cursor:not-allowed;filter:saturate(.65)}
    .move-folder:disabled:hover{background:rgba(255,255,255,.04);border-color:var(--line)}
    .move-folder .icon{width:28px;height:28px}
    .move-empty{padding:28px 12px;text-align:center;color:var(--muted);line-height:1.45}
    .move-primary{background:var(--goodsoft);color:var(--text);border-color:rgba(66,211,146,.65)}
    .move-primary:disabled{opacity:.5}

    .upload-dropdown .dropdown-menu{min-width:150px}
    @media(min-width:1700px){body::before{--glow-x:calc(var(--wrap-left) + 190px);--glow-y:118px;--glow-size:46rem}}
    @media(max-width:760px){
      html{-webkit-text-size-adjust:100%;scroll-padding-top:92px;background:#0f1115}
      body{overflow-x:hidden}
      body::before{position:absolute;left:0;top:0;right:0;bottom:0;width:100%;height:auto;min-height:100dvh;z-index:0;pointer-events:none;transform:none;--wrap-width:calc(100vw - 20px);--wrap-left:calc((100vw - var(--wrap-width)) / 2);--glow-x:calc(var(--wrap-left) + clamp(112px,30vw,156px));--glow-y:132px;--glow-size:34rem}
      main{position:relative;z-index:1}
      .wrap{width:calc(100% - 16px);padding:72px 0 34px;scroll-margin-top:92px}
      @supports (padding-top: env(safe-area-inset-top)){.wrap{padding-top:calc(72px + env(safe-area-inset-top));padding-bottom:calc(34px + env(safe-area-inset-bottom))}}
      header{flex-direction:column;gap:14px;margin-bottom:16px;isolation:isolate}.header-copy{width:100%}
      h1{font-size:clamp(2rem,10vw,2.55rem);line-height:1.04;letter-spacing:-.04em;overflow-wrap:anywhere;padding-top:0}
      .subtitle{font-size:.95rem;overflow-wrap:anywhere}
      .header-actions{justify-content:flex-start;flex-wrap:wrap;width:100%}
      .badge{min-height:42px}
      input,select,button,.action{min-height:44px;font-size:16px}
      input,select,button{padding:11px 12px}
      .toolbar{align-items:stretch;gap:9px;margin:14px 0}
      .toolbar input[type="search"]{flex-basis:100%;min-width:0;width:100%;height:48px}
      .toolbar-actions{width:100%;margin-left:0;display:grid;grid-template-columns:1fr 1fr;gap:9px;align-items:start}
      .toolbar-actions>*{min-width:0;align-self:start}
      .toolbar-actions button,.toolbar-actions .action{width:100%;min-width:0;justify-content:center;text-align:center;min-height:48px}.toolbar-actions>.action,.toolbar-actions>button,.toolbar-actions>.dropdown>button{height:48px}.toolbar-separator{display:none}#refreshIndex{grid-column:2}.logout-btn{display:flex;align-items:center;justify-content:center}
      .dropdown{width:100%;min-width:0;align-self:start;z-index:90}
      .dropdown.open{z-index:140}
      .dropdown>button{width:100%;min-height:48px}
      .dropdown-menu{position:absolute;top:calc(100% + 7px);left:0;right:auto;margin-top:0;min-width:0;width:100%;padding:8px;max-height:min(60dvh,320px);overflow:auto}
      .dropdown-menu button{min-height:44px;text-align:center}
      .toolbar-actions .dropdown.open+*,.toolbar-actions .dropdown.open~*{align-self:start}
      .panel,.editor,.login-card{border-radius:16px}
      .pathbar,.editor-head{align-items:flex-start;flex-direction:column;padding:12px}
      .breadcrumbs,.move-path{width:100%;flex-wrap:nowrap;overflow-x:auto;overflow-y:visible;padding:4px 6px 6px;scrollbar-width:none;-webkit-overflow-scrolling:touch}
      .breadcrumbs::-webkit-scrollbar,.move-path::-webkit-scrollbar{display:none}
      .breadcrumbs button,.move-path button{white-space:nowrap;min-height:40px;position:relative}
      .stats{white-space:normal;font-size:.86rem;line-height:1.35}
      .name,.editor-head h2,.editor-path,.subtitle,button,.action{overflow-wrap:anywhere;word-break:break-word}
      .upload-progress{margin:0 12px 10px}
      .hide-sm{display:none!important}
      .bulkbar{position:sticky;top:8px;z-index:7;align-items:stretch;padding:12px;background:rgba(42,69,59,.96);backdrop-filter:blur(12px)}
      body.light .bulkbar{background:rgba(225,248,238,.96)}
      .bulk-summary{width:100%;justify-content:center;font-size:1rem}
      .bulk-actions{width:100%;display:grid;grid-template-columns:1fr 1fr;gap:8px}
      .bulk-actions button,.bulk-actions .action{width:100%;justify-content:center;text-align:center;min-height:44px}
      table,thead,tbody,tr,td{display:block}
      table{border-collapse:separate;border-spacing:0}
      thead{display:none}
      tbody{display:grid;gap:8px;padding:10px}
      tbody tr{display:grid;grid-template-columns:32px minmax(0,1fr) 42px;align-items:center;gap:0 8px;padding:10px;border:1px solid rgba(48,56,71,.78);border-radius:14px;background:rgba(255,255,255,.026);box-shadow:0 10px 24px rgba(0,0,0,.12);overflow:visible}
      body.light tbody tr{border-color:rgba(215,222,232,.95);background:rgba(255,255,255,.72)}
      tbody tr.selected{background:rgba(66,211,146,.14)}
      th,td{padding:0;border-bottom:0!important;text-align:left}
      .select-col{grid-column:1;grid-row:1;width:auto;text-align:center;display:flex;align-items:center;justify-content:center;padding-top:0}
      .select-col input{width:22px;height:22px;min-width:22px;min-height:22px;border-radius:6px}
      .select-col input:checked::after{left:7px;top:3px;width:5px;height:10px}
      .select-col input:indeterminate::after{left:5px;top:9px;width:12px}
      td:nth-child(2),td.name-cell{grid-column:2;grid-row:1;min-width:0;overflow:hidden!important}
      td:nth-child(5),td.actions-cell{grid-column:3;grid-row:1;margin-top:0;text-align:right;overflow:visible!important;display:flex;justify-content:flex-end}
      .name{min-width:0;align-items:center;gap:8px;padding:0;max-width:100%;overflow:hidden}
      .name span:last-child,.name .truncate-text{min-width:0;line-height:1.25;white-space:nowrap;overflow:hidden;text-overflow:clip;word-break:normal;overflow-wrap:normal;font-size:.95rem}
      .icon{width:30px;height:30px;border-radius:9px}
      .actions{display:flex;justify-content:flex-end;width:42px}
      .item-menu{width:42px;display:flex;justify-content:flex-end}
      .item-menu-toggle{width:38px;min-width:38px;height:38px;min-height:38px;border-radius:12px;font-size:1.35rem}
      .item-menu-list{width:min(220px,calc(100vw - 56px))}
      .item-menu-list .action,.item-menu-list button{min-height:44px;padding:10px 12px;border-radius:11px;font-size:.93rem;line-height:1.15}
      .action{min-height:38px;padding:8px;border-radius:11px;justify-content:center;text-align:center;font-size:.93rem;line-height:1.15}
      @media(max-width:430px){
        tbody tr{grid-template-columns:28px minmax(0,1fr) 38px;gap:0 7px;padding:8px 9px}
        td.select-col{width:28px;min-width:28px}
        td.actions-cell,.actions,.item-menu{width:38px;min-width:38px}
        .name{grid-template-columns:26px minmax(0,1fr);gap:7px}
        .icon,.name .icon{width:26px;height:26px;min-width:26px;flex-basis:26px}
        .item-menu-toggle{width:34px;min-width:34px;height:34px;min-height:34px}
        .name .truncate-text{font-size:.9rem}
      }
      .editor-shell{margin-top:14px}
      .editor-title,.editor-title strong,.editor-title span{width:100%}
      .editor-status{white-space:normal}
      .editor-tools{display:grid;grid-template-columns:1fr 1fr;gap:8px;padding:10px}
      .editor-tools .spacer{display:none}
      .editor-tools button,.editor-tools select{width:100%;min-height:44px;padding:10px 8px;border-radius:11px;font-size:.94rem}
      .editor-tools select{grid-column:1 / -1}
      .editor-body{grid-template-columns:42px 1fr;min-height:55dvh;max-height:70dvh;overflow:hidden}
      .lines,textarea{min-height:55dvh;font-size:13px;line-height:1.55}
      textarea{padding:12px 10px;-webkit-overflow-scrolling:touch;resize:vertical}
      .lines{padding:12px 6px}
      .editor-note{font-size:.84rem}
      .toast{left:10px;right:10px;bottom:calc(10px + env(safe-area-inset-bottom));max-width:none}
      .upload-overlay{padding:14px}
      .upload-overlay-frame{inset:10px;border-radius:18px}
      .upload-overlay-card{max-width:calc(100vw - 42px);padding:18px}
      .move-modal{padding:10px}
      .move-card{width:calc(100vw - 20px);max-height:calc(100dvh - 20px);border-radius:16px;grid-template-rows:auto auto minmax(160px,1fr) auto}
      .move-head,.move-foot{padding:12px}
      .move-close{width:44px;height:44px;font-size:26px}
      .move-browse{padding:10px 12px}
      .move-current{width:100%;align-items:flex-start;flex-direction:column}
      .move-current code{max-width:100%;overflow-wrap:anywhere}
      .move-list{max-height:none;min-height:190px;padding:10px}
      .move-folder{min-height:48px;padding:11px;align-items:flex-start}
      .move-folder .muted{margin-left:auto;max-width:48%;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;text-align:right}
      .move-foot{align-items:stretch;flex-direction:column}
      .move-foot .bulk-actions{grid-template-columns:1fr 1fr}
      .login-wrap{width:calc(100% - 20px);padding:22px 0}
      .login-card{padding:20px}
    }
    @media(max-width:420px){
      .toolbar-actions,.actions,.bulk-actions,.editor-tools,.move-foot .bulk-actions{grid-template-columns:1fr}
      .action{font-size:.95rem}
      tbody tr{grid-template-columns:40px minmax(0,1fr);padding:10px}
      .select-col input{width:21px;height:21px;min-width:21px;min-height:21px}
      .move-folder .muted{display:none}
    }
    @media(hover:none){
      button:hover,.action:hover{transform:none}
      tbody tr:hover{background:rgba(255,255,255,.026)}
      body.light tbody tr:hover{background:rgba(255,255,255,.72)}
      tbody tr.selected:hover{background:rgba(66,211,146,.14)}
    }
  
      /* Strong compact mobile file rows: checkbox | file name | actions */
      @media(max-width:760px){
        tbody tr{
          display:grid;
          grid-template-columns:30px minmax(0,1fr) 40px;
          align-items:center;
          gap:0 8px;
          min-height:58px;
          padding:9px 10px;
          overflow:visible!important;
        }
        td.select-col{
          grid-column:1;
          grid-row:1;
          width:30px;
          min-width:30px;
          display:flex;
          align-items:center;
          justify-content:center;
        }
        td.name-cell{
          grid-column:2;
          grid-row:1;
          min-width:0;
          overflow:hidden!important;
        }
        td.actions-cell{
          grid-column:3;
          grid-row:1;
          width:40px;
          min-width:40px;
          display:flex;
          align-items:center;
          justify-content:flex-end;
          overflow:visible!important;
          text-align:right;
        }
        .name{
          display:grid;
          grid-template-columns:28px minmax(0,1fr);
          align-items:center;
          gap:8px;
          width:100%;
          min-width:0;
          max-width:100%;
          overflow:hidden;
        }
        .name .icon{
          grid-column:1;
          width:28px;
          height:28px;
          min-width:28px;
          flex:0 0 28px;
        }
        .name .truncate-text{
          grid-column:2;
          width:100%;
          min-width:0;
          max-width:100%;
          white-space:nowrap;
          overflow:hidden;
          text-overflow:clip;
          word-break:normal;
          overflow-wrap:normal;
          font-size:.92rem;
          line-height:1.2;
        }
        .actions,.item-menu{
          width:40px;
          min-width:40px;
          display:flex;
          justify-content:flex-end;
          align-items:center;
        }
        .item-menu-toggle{
          width:38px;
          min-width:38px;
          height:38px;
          min-height:38px;
        }
        .item-menu-list{
          right:0;
          max-width:calc(100vw - 28px);
        }
      }

  
      /* Final mobile row override: compact, aligned, no hidden text behind menu */
      @media(max-width:760px){
        #content .file-table tbody{
          display:grid!important;
          gap:8px!important;
          padding:10px!important;
        }
        #content .file-table tbody tr{
          display:grid!important;
          grid-template-columns:28px minmax(0,1fr) 36px!important;
          align-items:center!important;
          column-gap:7px!important;
          min-height:54px!important;
          padding:8px 8px!important;
          overflow:visible!important;
        }
        #content .file-table tbody tr > td{
          min-width:0!important;
          margin:0!important;
          padding:0!important;
          border:0!important;
        }
        #content .file-table tbody tr > td.select-col{
          grid-column:1!important;
          grid-row:1!important;
          width:28px!important;
          min-width:28px!important;
          display:flex!important;
          align-items:center!important;
          justify-content:center!important;
          overflow:visible!important;
        }
        #content .file-table tbody tr > td.name-cell{
          grid-column:2!important;
          grid-row:1!important;
          width:100%!important;
          min-width:0!important;
          overflow:hidden!important;
        }
        #content .file-table tbody tr > td.actions-cell{
          grid-column:3!important;
          grid-row:1!important;
          width:36px!important;
          min-width:36px!important;
          height:36px!important;
          display:flex!important;
          align-items:center!important;
          justify-content:center!important;
          overflow:visible!important;
          transform:none!important;
          position:relative!important;
          right:auto!important;
          top:auto!important;
        }
        #content .file-table .name{
          display:grid!important;
          grid-template-columns:26px minmax(0,1fr)!important;
          align-items:center!important;
          gap:7px!important;
          width:100%!important;
          max-width:100%!important;
          min-width:0!important;
          overflow:hidden!important;
        }
        #content .file-table .name .icon{
          grid-column:1!important;
          width:26px!important;
          height:26px!important;
          min-width:26px!important;
          max-width:26px!important;
          flex:0 0 26px!important;
          border-radius:8px!important;
        }
        #content .file-table .name .truncate-text{
          grid-column:2!important;
          display:block!important;
          width:100%!important;
          max-width:100%!important;
          min-width:0!important;
          overflow:hidden!important;
          white-space:nowrap!important;
          text-overflow:clip!important;
          word-break:normal!important;
          overflow-wrap:normal!important;
          font-size:.9rem!important;
          line-height:1.15!important;
        }
        #content .file-table .actions,
        #content .file-table .item-menu{
          width:36px!important;
          min-width:36px!important;
          max-width:36px!important;
          display:flex!important;
          align-items:center!important;
          justify-content:center!important;
        }
        #content .file-table .item-menu-toggle{
          width:34px!important;
          min-width:34px!important;
          height:34px!important;
          min-height:34px!important;
          padding:0!important;
          border-radius:11px!important;
          font-size:1.25rem!important;
          line-height:1!important;
        }
      }

  
    .pathbar{overflow:visible}

  
    @media(hover:none){
      .breadcrumbs button:active,
      .move-path button:active{
        box-shadow:0 0 0 3px rgba(66,211,146,.16);
      }
    }


  /* Final mobile toolbar cleanup */
  @media(max-width:760px){
    .header-actions{justify-content:flex-start!important;width:100%!important}
    .login-pill{align-self:flex-start;max-width:100%}
    .toolbar{gap:10px!important}
    .toolbar-actions{
      width:100%!important;
      margin-left:0!important;
      display:grid!important;
      grid-template-columns:repeat(2,minmax(0,1fr))!important;
      gap:10px!important;
      align-items:stretch!important;
    }
    .toolbar-actions>*{min-width:0!important}
    .toolbar-actions>.dropdown,
    .toolbar-actions>button,
    .toolbar-actions>.action{width:100%!important}
    .toolbar-actions>.dropdown>button,
    #refreshIndex{
      width:100%!important;
      min-width:0!important;
      min-height:48px!important;
      height:48px!important;
    }
    #refreshIndex{grid-column:1 / -1!important}
  }

  @media(max-width:430px){
    .toolbar-actions{grid-template-columns:1fr!important}
    #refreshIndex{grid-column:auto!important}
  }

  </style>
</head>
<body>
<?php if (!$loggedIn): ?>
  <main class="login-wrap">
    <section class="login-card">
      <h1><?= $appName ?></h1>
      <p class="subtitle">Log in to manage and edit your hosted files.</p>
      <?php if ($error): ?><div class="error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
      <form method="post" action="./">
        <input type="hidden" name="action" value="login" />
        <input type="password" name="password" placeholder="Password" autocomplete="current-password" autofocus required />
        <button type="submit">Log in</button>
      </form>
      <p class="footer">© 2026 LifeDreamer24. All rights reserved.</p>
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
        <div class="login-pill">
          <span class="badge-dot"></span>
          <span>Logged in</span>
          <a class="logout-icon" href="?logout=1" title="Log out" aria-label="Log out">
            <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
              <path d="M12 3v9" stroke-width="2" stroke-linecap="round"/>
              <path d="M7.05 6.6a8 8 0 1 0 9.9 0" stroke-width="2" stroke-linecap="round"/>
            </svg>
          </a>
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
      <div id="uploadProgress" class="upload-progress"></div>
      <div id="content"><div class="message">Loading folder...</div></div>
    </section>

    <section id="editorShell" class="editor-shell" aria-label="File editor">
      <div>
        <section class="editor">
          <div class="editor-head">
            <div class="editor-title">
              <strong id="editorName">No file selected</strong>
              <span id="editorPath">Click a text file name from the file browser to edit it.</span>
            </div>
            <div id="editorStatus" class="editor-status">Idle</div>
          </div>

          <div class="editor-tools">
            <button id="saveFile" type="button" disabled>Save</button>
            <button id="downloadEditor" type="button" disabled>Download</button>
            <button id="copyFileUrl" type="button" disabled>Copy URL</button>
            <button id="closeEditor" type="button">Close</button>

            <span class="spacer"></span>

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

          <div class="editor-body">
            <div id="lineNumbers" class="lines">1</div>
            <textarea id="editorText" spellcheck="false" disabled placeholder="File content will appear here..."></textarea>
          </div>

          <div class="editor-note">
            <strong>Save</strong> writes directly to the server file.
          </div>
        </section>
      </div>
    </section>

    <p class="footer">© 2026 LifeDreamer24. All rights reserved.</p>
  </main>

  <div id="toast" class="toast"></div>
  <div id="uploadOverlay" class="upload-overlay" aria-hidden="true">
    <div class="upload-overlay-frame"></div>
    <div class="upload-overlay-card">
      <strong>Drop files or folders to upload</strong>
      <span>Files will be uploaded in the current directory.</span>
    </div>
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

  <script>
    const state={path:new URL(location.href).searchParams.get("path")||"",entries:[],selected:new Set(),movePicker:null,editing:null,originalText:"",dirty:false,wrap:false,theme:localStorage.getItem("fastdl-manager-theme")||"dark"};
    const $=id=>document.getElementById(id);
    const content=$("content"),breadcrumbs=$("breadcrumbs"),stats=$("stats"),search=$("search");
    const editorShell=$("editorShell"),editorName=$("editorName"),editorPath=$("editorPath"),editorStatus=$("editorStatus"),editorText=$("editorText"),lineNumbers=$("lineNumbers");
    const uploadOverlay=$("uploadOverlay"),uploadInput=$("uploadInput"),uploadFolderInput=$("uploadFolderInput"),uploadProgress=$("uploadProgress");
    const moveModal=$("moveModal"),moveFolderList=$("moveFolderList"),moveBreadcrumbs=$("moveBreadcrumbs"),moveCurrentPath=$("moveCurrentPath"),moveSelectedCount=$("moveSelectedCount"),moveHint=$("moveHint"),moveConfirm=$("moveConfirm");

    function cleanPath(path){return String(path||"").replace(/^\/+|\/+$/g,"").replace(/\/{2,}/g,"/")}
    function apiUrl(action,params={}){const u=new URL("api.php",location.href);u.searchParams.set("action",action);for(const [k,v] of Object.entries(params))u.searchParams.set(k,v);return u}
    function absoluteUrl(url){try{return new URL(String(url||""),location.href).href}catch{return String(url||"")}}
    function filePublicUrl(item){return absoluteUrl(item.public_url||item.download_url||apiUrl("download",{path:item.path}).toString())}
    function fileDownloadUrl(item){return absoluteUrl(item.download_url||apiUrl("download",{path:item.path}).toString())}
    async function apiGet(action,params={}){const r=await fetch(apiUrl(action,params),{credentials:"same-origin"});if(r.status===401){location.reload();return}const j=await r.json();if(!j.ok)throw new Error(j.error||"Request failed");return j}
    async function apiPost(action,body={}){const r=await fetch(apiUrl(action),{method:"POST",credentials:"same-origin",headers:{"Content-Type":"application/json"},body:JSON.stringify(body)});if(r.status===401){location.reload();return}const j=await r.json();if(!j.ok)throw new Error(j.error||"Request failed");return j}
    async function loadFolder(showToast=false){renderLoading();try{const data=await apiGet("list",{path:state.path});state.entries=data.entries||[];pruneSelection();render(data.stats);if(showToast)toast("Folder refreshed.")}catch(e){showError(e.message||String(e))}}
    function currentEntries(){const q=search.value.trim().toLowerCase();if(!q)return state.entries;return state.entries.filter(i=>i.name.toLowerCase().includes(q)||i.path.toLowerCase().includes(q))}
    function selectedItems(){const byPath=new Map(state.entries.map(i=>[i.path,i]));return [...state.selected].map(path=>byPath.get(path)).filter(Boolean)}
    function selectedPaths(){return selectedItems().map(i=>i.path)}
    function pruneSelection(){const paths=new Set(state.entries.map(i=>i.path));for(const path of [...state.selected])if(!paths.has(path))state.selected.delete(path)}
    function truncateName(name,max=30){name=String(name||"");if(name.length<=max)return name;const dot=name.lastIndexOf(".");const ext=dot>0?name.slice(dot):"";const marker="(...)";if(ext&&ext.length<10){const head=Math.max(3,max-marker.length-ext.length);return name.slice(0,head)+marker+ext}const tail=Math.min(5,Math.max(2,max-10));const head=Math.max(3,max-marker.length-tail);return name.slice(0,head)+marker+name.slice(-tail)}
    function truncateNameCompact(name,isDir=false){
      name=String(name||"");
      const w=window.innerWidth||420;
      const max=w>=680?34:w>=520?30:w>=430?28:w>=380?25:22;
      return truncateName(name,isDir?Math.max(18,max-3):max);
    }
    function truncatePath(path,max=42){path=String(path||"");if(path.length<=max)return path;const marker="(...)";const tail=Math.min(14,Math.max(8,max-12));const head=Math.max(4,max-marker.length-tail);return path.slice(0,head)+marker+path.slice(-tail)}
    function selectionBar(){const selected=selectedItems(),count=selected.length;if(!count)return`<div class="bulkbar" id="bulkbar" aria-live="polite"></div>`;const extractable=selected.some(i=>i.type==="file"&&i.extractable);return`<div class="bulkbar show" id="bulkbar" aria-live="polite"><div class="bulk-summary"><strong>${count}</strong> selected</div><div class="bulk-actions"><button class="action" type="button" onclick="downloadSelected()">Download as ZIP</button><button class="action" type="button" onclick="copySelectedUrls()">Copy URLs</button><button class="action" type="button" onclick="moveSelectedPrompt()">Move</button><button class="action" type="button" onclick="extractSelected()" ${extractable?"":"disabled"}>Extract</button><button class="action danger" type="button" onclick="deleteSelected()">Delete</button><button class="action" type="button" onclick="clearSelection()">Deselect</button></div></div>`}
    function render(s){renderBreadcrumbs();const entries=currentEntries();if(!s){const folders=entries.filter(i=>i.type==="dir").length,files=entries.filter(i=>i.type==="file").length,total=entries.filter(i=>i.type==="file").reduce((a,i)=>a+Number(i.size||0),0);s={folders,files,total_size_label:formatBytes(total)}}const selectedCount=selectedItems().length;stats.textContent=`${s.folders} folder${s.folders===1?"":"s"} · ${s.files} file${s.files===1?"":"s"} · ${s.total_size_label}${selectedCount?` · ${selectedCount} selected`:""}`;if(!entries.length){content.innerHTML=`${selectionBar()}<div class="message">No files found in this folder.</div>`;setupSelectionControls();setupDragAndDrop();return}content.innerHTML=`${selectionBar()}<table class="file-table"><thead><tr><th class="select-col"><input id="selectAll" type="checkbox" aria-label="Select all visible items" onchange="toggleSelectAll(this.checked)"></th><th class="name-cell">Name</th><th class="hide-sm path-cell">Path</th><th class="right hide-sm size-cell">Size</th><th class="right actions-cell">Actions</th></tr></thead><tbody>${entries.map(renderRow).join("")}</tbody></table>`;setupSelectionControls();setupDragAndDrop()}
    function itemMenu(label,items){return`<div class="item-menu" data-item-menu><button class="action item-menu-toggle" type="button" aria-label="More actions for ${escapeAttr(label)}" aria-haspopup="menu" aria-expanded="false">⋯</button><div class="item-menu-list" role="menu">${items}</div></div>`}
    function renderRow(item){
      const isDir=item.type==="dir",icon=isDir?"📁":fileIcon(item.name),isSelected=state.selected.has(item.path);
      let actions,click;
      if(isDir){
        const downloadUrl=fileDownloadUrl(item);
        actions=itemMenu(item.name,`<a class="action" href="${escapeAttr(downloadUrl)}" download="${escapeAttr(item.download_name||item.name+'.zip')}">Download ZIP</a><button class="action" type="button" onclick="openMovePicker(['${escapeJs(item.path)}'])">Move</button><button class="action" type="button" onclick="renameItem('${escapeJs(item.path)}','${escapeJs(item.name)}')">Rename</button><button class="action" type="button" onclick="copyText('${escapeJs(downloadUrl)}', 'Copied folder URL')">Copy URL</button><button class="action danger" type="button" onclick="deleteItem('${escapeJs(item.path)}','dir')">Delete</button>`);
        click=`href="?path=${encodeURIComponent(item.path)}" onclick="event.preventDefault(); openFolder('${escapeJs(item.path)}')"`;
      }else{
        const url=filePublicUrl(item);
        const downloadUrl=fileDownloadUrl(item);
        actions=itemMenu(item.name,`<a class="action" href="${escapeAttr(downloadUrl)}" download="${escapeAttr(item.download_name||item.name)}">Download</a><button class="action" type="button" onclick="openMovePicker(['${escapeJs(item.path)}'])">Move</button><button class="action" type="button" onclick="renameItem('${escapeJs(item.path)}','${escapeJs(item.name)}')">Rename</button>${item.extractable?`<button class="action" type="button" onclick="extractArchive('${escapeJs(item.path)}')">Extract</button>`:""}<button class="action" type="button" onclick="copyText('${escapeJs(url)}', 'Copied URL')">Copy URL</button><button class="action danger" type="button" onclick="deleteItem('${escapeJs(item.path)}','file')">Delete</button>`);
        click=item.editable?`href="#" onclick="event.preventDefault(); editFile('${escapeJs(item.path)}')" class="name file-name"`:`href="${escapeAttr(downloadUrl)}" download="${escapeAttr(item.download_name||item.name)}" class="name file-name"`;
      }
      const nameMarkup=isDir?`<a class="name" ${click}>`:`<a ${click}>`;
      const compactMobile=window.matchMedia("(max-width:760px)").matches;
      const mediumScreen=window.innerWidth<=1280;
      const displayName=compactMobile
        ? truncateNameCompact(item.name,isDir)
        : truncateName(item.name,mediumScreen?(isDir?18:20):(isDir?22:24));
      const displayPath=truncatePath(item.path,compactMobile?18:(mediumScreen?30:38));
      return`<tr class="${isDir?"folder":"file"}${isSelected?" selected":""}" draggable="true" data-path="${escapeAttr(item.path)}" data-type="${item.type}" ${isDir?`data-drop-folder="${escapeAttr(item.path)}"`:""}><td class="select-col"><input class="row-select" type="checkbox" aria-label="Select ${escapeAttr(item.name)}" ${isSelected?"checked":""} onclick="event.stopPropagation()" onchange="toggleSelected('${escapeJs(item.path)}',this.checked)"></td><td class="name-cell">${nameMarkup}<span class="icon">${icon}</span><span class="truncate-text" title="${escapeAttr(item.name)}">${escapeHtml(displayName)}</span></a></td><td class="muted hide-sm path-cell"><span class="truncate-text" title="${escapeAttr(item.path)}">${escapeHtml(displayPath)}</span></td><td class="right muted hide-sm size-cell"><span class="size-value">${isDir?"":escapeHtml(item.size_label||formatBytes(item.size))}</span></td><td class="right actions-cell"><div class="actions">${actions}</div></td></tr>`
    }
    function openFolder(path){state.path=cleanPath(path);state.selected.clear();search.value="";const u=new URL(location.href);if(state.path)u.searchParams.set("path",state.path);else u.searchParams.delete("path");history.pushState(null,"",u);loadFolder()}
    function renderBreadcrumbs(){const parts=state.path?state.path.split("/"):[];let html=`<button type="button" data-drop-folder="" onclick="openFolder('')">root</button>`;parts.forEach((part,index)=>{const path=parts.slice(0,index+1).join("/");html+=`<span class="sep">/</span><button type="button" data-drop-folder="${escapeAttr(path)}" onclick="openFolder('${escapeJs(path)}')">${escapeHtml(part)}</button>`});breadcrumbs.innerHTML=html}
    function renderLoading(){renderBreadcrumbs();stats.textContent="Loading...";content.innerHTML=`<div class="message">Loading folder...</div>`}
    function showError(message){renderBreadcrumbs();stats.textContent="Error";content.innerHTML=`<div class="message error"><strong>Could not load folder.</strong><br>${escapeHtml(message)}</div>`}


    function setupSelectionControls(){const selectAll=$("selectAll");if(!selectAll)return;const entries=currentEntries();const selectedVisible=entries.filter(i=>state.selected.has(i.path)).length;selectAll.checked=entries.length>0&&selectedVisible===entries.length;selectAll.indeterminate=selectedVisible>0&&selectedVisible<entries.length}
    function toggleSelected(path,checked){path=cleanPath(path);if(!path)return;if(checked)state.selected.add(path);else state.selected.delete(path);render()}
    function toggleSelectAll(checked){for(const item of currentEntries()){if(checked)state.selected.add(item.path);else state.selected.delete(item.path)}render()}
    function clearSelection(){state.selected.clear();render()}
    function filenameFromDisposition(disposition){const star=String(disposition||"").match(/filename\*=UTF-8''([^;]+)/i);if(star){try{return decodeURIComponent(star[1].trim().replace(/^"|"$/g,""))}catch{return star[1]}}const normal=String(disposition||"").match(/filename="?([^";]+)"?/i);return normal?normal[1]:""}
    async function downloadSelected(){const paths=selectedPaths();if(!paths.length){toast("No selected items.");return}try{toast("Preparing ZIP...");const r=await fetch(apiUrl("download_batch"),{method:"POST",credentials:"same-origin",headers:{"Content-Type":"application/json"},body:JSON.stringify({paths})});if(r.status===401){location.reload();return}if(!r.ok){let message="Download failed";try{const j=await r.json();message=j.error||message}catch{message=await r.text()||message}throw new Error(message)}const blob=await r.blob();const name=filenameFromDisposition(r.headers.get("Content-Disposition"))||"selected-items.zip";const url=URL.createObjectURL(blob),a=document.createElement("a");a.href=url;a.download=name;document.body.appendChild(a);a.click();a.remove();URL.revokeObjectURL(url);toast(`Downloaded ${paths.length} selected item${paths.length===1?"":"s"}.`)}catch(e){toast(e.message||String(e))}}
    async function copySelectedUrls(){const items=selectedItems();if(!items.length){toast("No selected items.");return}const urls=items.map(item=>item.type==="file"?filePublicUrl(item):fileDownloadUrl(item));await copyText(urls.join("\n"),`Copied ${urls.length} URL${urls.length===1?"":"s"}`)}
    async function deleteSelected(){const paths=selectedPaths();if(!paths.length){toast("No selected items.");return}const preview=paths.slice(0,8).join("\n")+(paths.length>8?"\n...":"");if(!confirm(`Delete ${paths.length} selected item${paths.length===1?"":"s"}?\n\n${preview}`))return;await deleteItems(paths)}
    async function moveSelectedPrompt(){const paths=selectedPaths();if(!paths.length){toast("No selected items.");return}openMovePicker(paths)}
    async function extractSelected(){const items=selectedItems().filter(i=>i.type==="file"&&i.extractable);if(!items.length){toast("No selected ZIP archives.");return}const preview=items.map(i=>i.path).slice(0,8).join("\n")+(items.length>8?"\n...":"");if(!confirm(`Extract ${items.length} selected ZIP archive${items.length===1?"":"s"}?\n\n${preview}`))return;let ok=0,errors=[];for(const item of items){try{await apiPost("extract",{path:item.path});ok++}catch(e){errors.push(`${item.path}: ${e.message||String(e)}`)}}toast(errors.length?`Extracted ${ok}; failed ${errors.length}: ${errors[0]}`:`Extracted ${ok} archive${ok===1?"":"s"}.`);await loadFolder()}

    function simpleUploadReason(reason){reason=String(reason||"").toLowerCase();if(reason.includes("protected")||reason.includes("server behavior")||reason.includes("file manager"))return"access protected/system file";if(reason.includes("dot")||reason.includes("hidden"))return"hidden/system file";const ext=reason.match(/ending in \.([a-z0-9]+)/i)||reason.match(/extension \.([a-z0-9]+)/i);if(ext)return`blocked .${ext[1]} file`;if(reason.includes("too large"))return"file too large";return"blocked file"}
    function summarizeUploadErrors(errors){const groups=new Map();for(const err of errors||[]){const text=String(err||"").trim();let name="";let reason=text;const match=text.match(/^(.+?) was not uploaded\.\s*(.+)$/);if(match){name=match[1];reason=match[2]}const label=simpleUploadReason(reason);if(!groups.has(label))groups.set(label,{count:0,names:[]});const group=groups.get(label);group.count++;if(name)group.names.push(name)}return [...groups.entries()].map(([label,group])=>{const names=group.names.slice(0,4).join(", ")+(group.names.length>4?", ...":"");const filePart=names||"file";const countPart=group.count>1?` (x${group.count})`:"";return `Blocked ${filePart} upload: ${label}${countPart}`}).join(" | ")}
    function fileRelativePath(file){return file.relativePath||file.webkitRelativePath||file.name}
    async function uploadFiles(files){files=[...files];if(!files.length)return;const form=new FormData();form.append("path",state.path);for(const file of files){form.append("files[]",file,file.name);form.append("paths[]",fileRelativePath(file))}toast(`Uploading ${files.length} file${files.length===1?"":"s"}...`);uploadProgress.classList.remove("show");uploadProgress.textContent="";try{const r=await fetch(apiUrl("upload",{path:state.path}),{method:"POST",credentials:"same-origin",body:form});const j=await r.json();const uploadedCount=(j.uploaded||[]).length;if(uploadedCount)toast(j.message||`${uploadedCount} file${uploadedCount===1?"":"s"} uploaded.`);if(j.errors&&j.errors.length)toast(summarizeUploadErrors(j.errors));if(!uploadedCount&&(!j.errors||!j.errors.length)&&!j.ok)throw new Error(j.error||"Upload failed");await loadFolder()}catch(e){toast(e.message||String(e))}finally{uploadProgress.classList.remove("show");uploadProgress.textContent="";uploadInput.value="";uploadFolderInput.value=""}}
    function readEntryFile(entry,pathPrefix=""){return new Promise(resolve=>{entry.file(file=>{file.relativePath=pathPrefix+file.name;resolve([file])},()=>resolve([]))})}
    function readDirectoryEntries(reader){return new Promise(resolve=>{const entries=[];function readBatch(){reader.readEntries(batch=>{if(!batch.length){resolve(entries);return}entries.push(...batch);readBatch()},()=>resolve(entries))}readBatch()})}
    async function readEntry(entry,pathPrefix=""){if(entry.isFile)return readEntryFile(entry,pathPrefix);if(entry.isDirectory){const dirPrefix=pathPrefix+entry.name+"/";const reader=entry.createReader();const entries=await readDirectoryEntries(reader);const files=[];for(const child of entries)files.push(...await readEntry(child,dirPrefix));return files}return[]}
    async function filesFromDropEvent(e){const items=[...(e.dataTransfer.items||[])];if(!items.length)return[...(e.dataTransfer.files||[])];const files=[];for(const item of items){const entry=item.webkitGetAsEntry?item.webkitGetAsEntry():null;if(entry)files.push(...await readEntry(entry,""));else{const file=item.getAsFile&&item.getAsFile();if(file)files.push(file)}}return files.length?files:[...(e.dataTransfer.files||[])]}
    function editorInsideSelected(paths){return !!state.editing&&paths.some(path=>state.editing.path===path||state.editing.path.startsWith(path+"/"))}
    async function deleteItems(paths){paths=[...new Set(paths.map(cleanPath).filter(Boolean))];let ok=0,errors=[];for(const path of paths){try{await apiPost("delete",{path});ok++}catch(e){errors.push(`${path}: ${e.message||String(e)}`)}}if(editorInsideSelected(paths))closeEditor(true);toast(errors.length?`Deleted ${ok}; failed ${errors.length}: ${errors[0]}`:`Deleted ${ok} item${ok===1?"":"s"}.`);state.selected.clear();await loadFolder()}
    async function deleteItem(path,type){const label=type==="dir"?"folder and everything inside it":"file";if(!confirm(`Delete this ${label}?\n\n${path}`))return;await deleteItems([path])}
    async function renameItem(path,name){const newName=prompt("New name:",name);if(!newName||newName===name)return;try{const data=await apiPost("rename",{path,new_name:newName});toast("Renamed.");state.selected.delete(path);if(state.editing&&state.editing.path===path){state.editing.path=data.path;state.editing.name=newName;editorName.textContent=newName;editorPath.textContent=data.path}await loadFolder()}catch(e){toast(e.message||String(e))}}
    async function extractArchive(path){if(!confirm(`Extract this ZIP archive?\n\n${path}`))return;try{const data=await apiPost("extract",{path});toast(data.message||"Extracted.");await loadFolder()}catch(e){toast(e.message||String(e))}}
    async function createItem(type){const label=type==="dir"?"folder":"file";const suggested=type==="dir"?"new-folder":"new-file.txt";const name=prompt(`New ${label} name:`,suggested);if(!name)return;try{const data=await apiPost("create",{type,path:state.path,name});toast(data.message||`${label} created.`);await loadFolder();if(type==="file")editFile(data.path)}catch(e){toast(e.message||String(e))}}
    function sourceItemForPath(path){return state.entries.find(i=>i.path===path)||null}
    function moveWouldNest(path,targetDir){const item=sourceItemForPath(path);return item&&item.type==="dir"&&(path===targetDir||targetDir.startsWith(path+"/"))}
    function moveTargetInvalid(targetDir){targetDir=cleanPath(targetDir||"");return !!(state.movePicker&&state.movePicker.paths.some(path=>moveWouldNest(path,targetDir)))}
    function openMovePicker(paths){paths=[...new Set(paths.map(cleanPath).filter(Boolean))];if(!paths.length)return;state.movePicker={paths,browsePath:state.path};moveModal.classList.add("show");moveModal.setAttribute("aria-hidden","false");renderMovePicker();setTimeout(()=>moveConfirm.focus(),0)}
    function closeMovePicker(){state.movePicker=null;moveModal.classList.remove("show");moveModal.setAttribute("aria-hidden","true")}
    function movePathLabel(path){path=cleanPath(path);return path||"root"}
    function renderMoveBreadcrumbs(path){const parts=path?path.split("/"):[];let html=`<button type="button" onclick="browseMoveFolder('')">root</button>`;parts.forEach((part,index)=>{const target=parts.slice(0,index+1).join("/");html+=`<span class="sep">/</span><button type="button" onclick="browseMoveFolder('${escapeJs(target)}')">${escapeHtml(part)}</button>`});moveBreadcrumbs.innerHTML=html}
    async function renderMovePicker(){if(!state.movePicker)return;const paths=state.movePicker.paths,browsePath=cleanPath(state.movePicker.browsePath);moveSelectedCount.textContent=`${paths.length} selected item${paths.length===1?"":"s"}`;moveCurrentPath.textContent=movePathLabel(browsePath);renderMoveBreadcrumbs(browsePath);const invalid=moveTargetInvalid(browsePath);moveConfirm.disabled=invalid;moveHint.textContent=invalid?"You cannot move a folder into itself or one of its subfolders.":"Open a folder below, then click Move here.";moveFolderList.innerHTML=`<div class="move-empty">Loading folders...</div>`;try{const data=await apiGet("list",{path:browsePath});if(!state.movePicker||state.movePicker.browsePath!==browsePath)return;let dirs=(data.entries||[]).filter(i=>i.type==="dir");if(browsePath){const parent=browsePath.split("/").slice(0,-1).join("/");dirs=[{type:"dir",name:"..",path:parent,parent:true},...dirs]}if(!dirs.length){moveFolderList.innerHTML=`<div class="move-empty">No folders inside this location.</div>`;return}moveFolderList.innerHTML=dirs.map(dir=>{const disabled=!dir.parent&&paths.some(path=>moveWouldNest(path,dir.path));const label=dir.parent?"Parent folder":dir.name;return`<button class="move-folder" type="button" ${disabled?'disabled title="Cannot move into this folder"':''} onclick="browseMoveFolder('${escapeJs(dir.path)}')"><span class="icon">${dir.parent?"↩":"📁"}</span><span>${escapeHtml(label)}</span><span class="muted">${escapeHtml(movePathLabel(dir.path))}</span></button>`}).join("")}catch(e){moveFolderList.innerHTML=`<div class="move-empty error">${escapeHtml(e.message||String(e))}</div>`}}
    function browseMoveFolder(path){if(!state.movePicker)return;path=cleanPath(path);if(moveTargetInvalid(path)){toast("Cannot move a folder into itself or one of its subfolders.");return}state.movePicker.browsePath=path;renderMovePicker()}
    async function confirmMovePicker(){if(!state.movePicker)return;const paths=[...state.movePicker.paths],target=cleanPath(state.movePicker.browsePath);if(moveTargetInvalid(target)){toast("Cannot move a folder into itself or one of its subfolders.");return}closeMovePicker();await moveItems(paths,target)}
    async function moveItems(paths,targetDir){targetDir=cleanPath(targetDir||"");paths=[...new Set(paths.map(cleanPath).filter(Boolean))];if(!paths.length)return;if(paths.some(path=>moveWouldNest(path,targetDir))){toast("Cannot move a folder into itself or one of its subfolders.");return}let ok=0,errors=[];for(const path of paths){try{const data=await apiPost("move",{path,target_dir:targetDir});ok++;if(state.editing&&state.editing.path===path){state.editing.path=data.path;editorPath.textContent=data.path}}catch(e){errors.push(`${path}: ${e.message||String(e)}`)}}toast(errors.length?`Moved ${ok}; failed ${errors.length}: ${errors[0]}`:`Moved ${ok} item${ok===1?"":"s"}.`);state.selected.clear();await loadFolder()}
    async function moveItem(path,targetDir){await moveItems([path],targetDir)}
    function dragPathsForRow(row){const path=row.dataset.path;if(state.selected.has(path))return selectedPaths();return[path]}
    function setupDragAndDrop(){document.querySelectorAll("tr[draggable='true']").forEach(row=>{row.addEventListener("dragstart",e=>{const paths=dragPathsForRow(row);e.dataTransfer.setData("text/plain",paths[0]||row.dataset.path);e.dataTransfer.setData("application/x-fastdl-paths",JSON.stringify(paths));e.dataTransfer.setData("application/x-fastdl-move","1");e.dataTransfer.effectAllowed="move";document.querySelectorAll("tr[data-path]").forEach(r=>{if(paths.includes(r.dataset.path))r.classList.add("dragging")})});row.addEventListener("dragend",()=>document.querySelectorAll("tr.dragging").forEach(r=>r.classList.remove("dragging")))});document.querySelectorAll("[data-drop-folder]").forEach(target=>{target.addEventListener("dragover",e=>{const dragged=e.dataTransfer.types.includes("application/x-fastdl-move");if(!dragged)return;e.preventDefault();target.classList.add("drop-target");e.dataTransfer.dropEffect="move"});target.addEventListener("dragleave",()=>target.classList.remove("drop-target"));target.addEventListener("drop",e=>{e.preventDefault();target.classList.remove("drop-target");let paths=[];try{paths=JSON.parse(e.dataTransfer.getData("application/x-fastdl-paths")||"[]")}catch{paths=[]}if(!paths.length){const path=e.dataTransfer.getData("text/plain");if(path)paths=[path]}const targetDir=target.dataset.dropFolder||"";if(paths.length)moveItems(paths,targetDir)})})}


    async function editFile(path){if(state.editing&&state.editing.path===path){openEditorShell(false);toast("Already editing this file.");return}if(state.dirty&&!confirm("You have unsaved editor changes. Open another file anyway?"))return;openEditorShell(true);editorName.textContent=path.split("/").pop();editorPath.textContent=path;editorText.disabled=true;editorText.value="Loading...";setEditorStatus("Loading...",false);updateLines();try{const data=await apiGet("read",{path});state.editing=data;state.originalText=data.content||"";state.dirty=false;editorName.textContent=data.name;editorPath.textContent=data.path;editorText.value=data.content||"";editorText.disabled=false;editorText.classList.toggle("editor-word-wrap",state.wrap);$("syntaxMode").value="auto";setEditorControls(true);setEditorStatus("Editing",false);updateLines();requestAnimationFrame(()=>{editorText.scrollTop=0;lineNumbers.scrollTop=0;editorText.selectionStart=0;editorText.selectionEnd=0})}catch(e){state.editing=null;editorText.value="";editorText.disabled=true;setEditorControls(false);setEditorStatus(e.message||String(e),false)}}
    async function saveFile(){if(!state.editing)return;try{setEditorStatus("Saving...",false);await apiPost("save",{path:state.editing.path,content:editorText.value});state.originalText=editorText.value;state.dirty=false;setEditorStatus("Saved",false);toast("Saved.");await loadFolder()}catch(e){setEditorStatus("Save failed",true);toast(e.message||String(e))}}
    function openEditorShell(scroll=true){editorShell.classList.add("open");if(scroll)setTimeout(()=>editorShell.scrollIntoView({behavior:"smooth",block:"nearest"}),70)}
    function closeEditor(force=false){if(!force&&state.dirty&&!confirm("Close editor and discard unsaved changes?"))return;state.editing=null;state.originalText="";state.dirty=false;editorText.value="";editorText.disabled=true;editorText.classList.remove("wrap");editorName.textContent="No file selected";editorPath.textContent="Click a text file name from the file browser to edit it.";setEditorStatus("Idle",false);setEditorControls(false);editorShell.classList.remove("open");updateLines()}
    function setEditorControls(on){$("saveFile").disabled=!on||!state.dirty;$("downloadEditor").disabled=!on;$("copyFileUrl").disabled=!state.editing;$("formatFile").disabled=!on;$("trimLines").disabled=!on;$("tabsToSpaces").disabled=!on;$("wrapToggle").disabled=!on}
    function setEditorStatus(text,dirty){editorStatus.textContent=text;editorStatus.classList.toggle("dirty",!!dirty)}
    function markDirty(){state.dirty=!!state.editing&&editorText.value!==state.originalText;setEditorStatus(state.dirty?"Unsaved edits":"Editing",state.dirty);setEditorControls(!!state.editing)}
    function downloadEditor(){if(!state.editing)return;downloadBlob(editorText.value,state.editing.name,"text/plain;charset=utf-8");setEditorStatus("File downloaded",state.dirty)}
    function downloadBlob(text,name,type){const blob=new Blob([text],{type:type||"text/plain"}),url=URL.createObjectURL(blob),a=document.createElement("a");a.href=url;a.download=name||"edited-file.txt";document.body.appendChild(a);a.click();a.remove();URL.revokeObjectURL(url)}
    function formatEditor(){if(!state.editing)return;const lang=detectLang();try{let out=editorText.value;if(lang==="json")out=JSON.stringify(JSON.parse(out),null,2)+"\n";else if(lang==="html")out=formatHtml(out);else if(lang==="css")out=formatCss(out);else if(lang==="js")out=formatJs(out);else out=trimTrailing(out);editorText.value=out;markDirty();updateLines();toast("Formatted.")}catch(e){toast("Format failed: "+(e.message||String(e)))}}
    function detectLang(){const chosen=$("syntaxMode").value;if(chosen!=="auto")return chosen;if(!state.editing)return"plain";const ext=extension(state.editing.name);if(ext==="json")return"json";if(["html","htm","xml","svg"].includes(ext))return"html";if(["css","scss"].includes(ext))return"css";if(["js","mjs","cjs","ts","tsx","jsx"].includes(ext))return"js";if(["cfg","ini","res","vmt"].includes(ext))return"cfg";return"plain"}
    function trimTrailing(text){return text.split(/\r?\n/).map(l=>l.trimEnd()).join("\n")}
    function formatCss(text){return text.replace(/\s*{\s*/g," {\n  ").replace(/;\s*/g,";\n  ").replace(/\s*}\s*/g,"\n}\n\n").replace(/\n\s*\n\s*\n/g,"\n\n").replace(/[ \t]+\n/g,"\n").trim()+"\n"}
    function formatJs(text){return text.replace(/\s*{\s*/g," {\n  ").replace(/;\s*/g,";\n").replace(/\s*}\s*/g,"\n}\n").replace(/\n\s*\n\s*\n/g,"\n\n").replace(/[ \t]+\n/g,"\n").trim()+"\n"}
    function formatHtml(text){const tokens=text.replace(/>\s*</g,"><").split(/(?=<)|(?<=>)/g).filter(Boolean);let indent=0,lines=[];for(let token of tokens){token=token.trim();if(!token)continue;if(/^<\//.test(token))indent=Math.max(indent-1,0);lines.push("  ".repeat(indent)+token);if(/^<[^!?/][^>]*[^/]?>$/.test(token)&&!/^<(area|base|br|col|embed|hr|img|input|link|meta|param|source|track|wbr)\b/i.test(token))indent++}return lines.join("\n").trim()+"\n"}
    function updateLines(){const count=Math.max(1,editorText.value.split("\n").length);let out="";for(let i=1;i<=count;i++)out+=i+"\n";lineNumbers.textContent=out}
    function applyTheme(){document.body.classList.toggle("light",state.theme==="light");localStorage.setItem("fastdl-manager-theme",state.theme)}
    function toggleTheme(){state.theme=state.theme==="light"?"dark":"light";applyTheme()}
    function fileIcon(name){const ext=extension(name);if(["bz2","zip","7z","rar"].includes(ext))return"🗜️";if(["bsp","nav"].includes(ext))return"🗺️";if(["vmt","vtf","png","jpg","jpeg","webp","gif"].includes(ext))return"🖼️";if(["wav","mp3","ogg"].includes(ext))return"🔊";if(["mdl","vvd","phy","vtx"].includes(ext))return"🧩";if(["txt","cfg","res","json","html","css","js"].includes(ext))return"📄";return"📦"}
    function extension(name){const i=String(name).lastIndexOf(".");return i>=0?String(name).slice(i+1).toLowerCase():""}
    function formatBytes(bytes){bytes=Number(bytes||0);if(!bytes)return"0 B";const units=["B","KB","MB","GB","TB"],i=Math.min(Math.floor(Math.log(bytes)/Math.log(1024)),units.length-1);return`${(bytes/Math.pow(1024,i)).toFixed(i===0?0:1)} ${units[i]}`}
    async function copyText(text,message){try{await navigator.clipboard.writeText(text);toast(message||"Copied")}catch{prompt("Copy this:",text)}}
    function toast(message){const el=$("toast");el.textContent=message;el.classList.remove("hiding");el.classList.add("show");clearTimeout(toast.timer);clearTimeout(toast.hideTimer);toast.timer=setTimeout(()=>{el.classList.remove("show");el.classList.add("hiding");toast.hideTimer=setTimeout(()=>el.classList.remove("hiding"),280)},6500)}
    function escapeHtml(str){return String(str).replaceAll("&","&amp;").replaceAll("<","&lt;").replaceAll(">","&gt;").replaceAll('"',"&quot;").replaceAll("'","&#039;")}
    function escapeAttr(str){return escapeHtml(str)}
    function escapeJs(str){return String(str).replaceAll("\\","\\\\").replaceAll("'","\\'").replaceAll("\n","\\n").replaceAll("\r","\\r")}

    search.addEventListener("input",()=>render());
    let lastRenderWidth=window.innerWidth;
    window.addEventListener("resize",()=>{clearTimeout(window.__resizeRenderTimer);window.__resizeRenderTimer=setTimeout(()=>{lastRenderWidth=window.innerWidth;render()},120)});
    window.addEventListener("orientationchange",()=>setTimeout(()=>render(),220));
    const newDropdown=$("newDropdown");
    const uploadDropdown=$("uploadDropdown"),uploadMenuBtn=$("uploadMenuBtn");
    function closeDropdowns(except=null){
      if(except!=="new")newDropdown.classList.remove("open");
      if(except!=="upload")uploadDropdown.classList.remove("open");
    }
    function closeItemMenus(except=null){
      document.querySelectorAll(".item-menu.open").forEach(menu=>{
        if(except&&menu===except)return;
        menu.classList.remove("open");
        const btn=menu.querySelector(".item-menu-toggle");
        if(btn)btn.setAttribute("aria-expanded","false");
      });
    }
    $("newMenuBtn").addEventListener("click",e=>{
      e.stopPropagation();
      const willOpen=!newDropdown.classList.contains("open");
      closeDropdowns();
      if(willOpen)newDropdown.classList.add("open");
    });
    uploadMenuBtn.addEventListener("click",e=>{
      e.stopPropagation();
      const willOpen=!uploadDropdown.classList.contains("open");
      closeDropdowns();
      if(willOpen)uploadDropdown.classList.add("open");
    });
    document.addEventListener("click",()=>{closeDropdowns();closeItemMenus()});
    content.addEventListener("click",e=>{
      const toggle=e.target.closest(".item-menu-toggle");
      if(toggle){
        e.preventDefault();
        e.stopPropagation();
        const menu=toggle.closest(".item-menu");
        const willOpen=!menu.classList.contains("open");
        closeItemMenus();
        closeDropdowns();
        if(willOpen){
          menu.classList.add("open");
          toggle.setAttribute("aria-expanded","true");
        }
        return;
      }
      const menuAction=e.target.closest(".item-menu-list .action, .item-menu-list button");
      if(menuAction)setTimeout(()=>closeItemMenus(),0);
    });
    $("newFileBtn").addEventListener("click",()=>{closeDropdowns();createItem("file")});
    $("newFolderBtn").addEventListener("click",()=>{closeDropdowns();createItem("dir")});
    uploadDropdown.addEventListener("click",e=>{
      const btn=e.target.closest("[data-upload]");
      if(!btn)return;
      closeDropdowns();
      if(btn.dataset.upload==="folder")uploadFolderInput.click();
      else uploadInput.click();
    });
    uploadInput.addEventListener("change",()=>uploadFiles(uploadInput.files));
    uploadFolderInput.addEventListener("change",()=>uploadFiles(uploadFolderInput.files));
    let externalDragDepth=0;
    function isExternalFileDrag(e){return e.dataTransfer&&e.dataTransfer.types&&e.dataTransfer.types.includes("Files")&&!e.dataTransfer.types.includes("application/x-fastdl-move")}
    function showUploadOverlay(){uploadOverlay.classList.remove("hiding");uploadOverlay.classList.add("show");uploadOverlay.setAttribute("aria-hidden","false")}
    function hideUploadOverlay(){uploadOverlay.classList.remove("show");uploadOverlay.classList.add("hiding");uploadOverlay.setAttribute("aria-hidden","true");setTimeout(()=>uploadOverlay.classList.remove("hiding"),190)}
    window.addEventListener("dragenter",e=>{if(!isExternalFileDrag(e))return;externalDragDepth++;showUploadOverlay()});
    window.addEventListener("dragover",e=>{if(!isExternalFileDrag(e))return;e.preventDefault();e.dataTransfer.dropEffect="copy";showUploadOverlay()});
    window.addEventListener("dragleave",e=>{if(!isExternalFileDrag(e))return;externalDragDepth=Math.max(0,externalDragDepth-1);if(externalDragDepth===0)hideUploadOverlay()});
    window.addEventListener("drop",async e=>{if(!isExternalFileDrag(e))return;e.preventDefault();externalDragDepth=0;hideUploadOverlay();const files=await filesFromDropEvent(e);uploadFiles(files)});
    $("refreshIndex").addEventListener("click",()=>loadFolder(true));
    $("saveFile").addEventListener("click",saveFile);
    $("downloadEditor").addEventListener("click",downloadEditor);
    $("copyFileUrl").addEventListener("click",()=>state.editing&&copyText(filePublicUrl(state.editing),"Copied URL"));
    $("closeEditor").addEventListener("click",()=>closeEditor());
    $("formatFile").addEventListener("click",formatEditor);
    $("trimLines").addEventListener("click",()=>{editorText.value=trimTrailing(editorText.value);markDirty();updateLines();toast("Trimmed lines.")});
    $("tabsToSpaces").addEventListener("click",()=>{editorText.value=editorText.value.replace(/\t/g,"  ");markDirty();updateLines();toast("Converted tabs.")});
    $("wrapToggle").addEventListener("click",()=>{state.wrap=!state.wrap;editorText.classList.toggle("editor-word-wrap",state.wrap);$("wrapToggle").textContent=state.wrap?"Wrap On":"Wrap Off";updateLines()});
    editorText.addEventListener("input",()=>{markDirty();updateLines()});
    editorText.addEventListener("scroll",()=>{lineNumbers.scrollTop=editorText.scrollTop});
    editorText.addEventListener("keydown",e=>{if(e.key==="Tab"){e.preventDefault();const s=editorText.selectionStart,t=editorText.selectionEnd;editorText.value=editorText.value.slice(0,s)+"  "+editorText.value.slice(t);editorText.selectionStart=editorText.selectionEnd=s+2;markDirty();updateLines()}if((e.ctrlKey||e.metaKey)&&e.key.toLowerCase()==="s"){e.preventDefault();saveFile()}});
    $("moveClose").addEventListener("click",closeMovePicker);
    $("moveCancel").addEventListener("click",closeMovePicker);
    moveConfirm.addEventListener("click",confirmMovePicker);
    moveModal.addEventListener("click",e=>{if(e.target===moveModal)closeMovePicker()});
    window.addEventListener("keydown",e=>{if(e.key==="Escape"&&state.movePicker)closeMovePicker()});
    window.addEventListener("popstate",()=>{state.path=cleanPath(new URL(location.href).searchParams.get("path")||"");state.selected.clear();search.value="";loadFolder()});
    window.addEventListener("beforeunload",e=>{if(state.dirty){e.preventDefault();e.returnValue=""}});
    applyTheme();
    loadFolder();
  </script>
<?php endif; ?>
</body>
</html>
