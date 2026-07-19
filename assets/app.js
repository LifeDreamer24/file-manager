const themeMedia = window.matchMedia("(prefers-color-scheme: light)");
const storedTheme = localStorage.getItem("file-manager-theme-preference");
const initialTheme = ["system", "light", "dark"].includes(storedTheme)
  ? storedTheme
  : "system";
// Older releases wrote "dark" on every visit even without a user choice.
localStorage.removeItem("file-manager-theme");

const state = {
  path: new URL(location.href).searchParams.get("path") || "",
  entries: [],
  selected: new Set(),
  movePicker: null,
  editing: null,
  originalText: "",
  dirty: false,
  wrap: false,
  themePreference: initialTheme,
  failedUploads: [],
  uploadFailures: [],
  activeUploads: new Set(),
  uploadCancelled: false,
  conflictResolver: null,
  editorReturnFocus: null,
  media: null,
  mediaReturnFocus: null,
};
const csrfToken =
  document.querySelector('meta[name="csrf-token"]')?.content || "";
const $ = (id) => document.getElementById(id);
const content = $("content"),
  selectionBarHost = $("selectionBarHost"),
  breadcrumbs = $("breadcrumbs"),
  stats = $("stats"),
  search = $("search");
const editorModal = $("editorModal"),
  editorShell = $("editorShell"),
  editorName = $("editorName"),
  editorPath = $("editorPath"),
  editorStatus = $("editorStatus"),
  editorText = $("editorText"),
  lineNumbers = $("lineNumbers"),
  editorToolsMenuGroup = $("editorToolsMenuGroup"),
  editorToolsMenuBtn = $("editorToolsMenuBtn");
const uploadOverlay = $("uploadOverlay"),
  uploadInput = $("uploadInput"),
  uploadFolderInput = $("uploadFolderInput"),
  uploadProgress = $("uploadProgress");
const uploadProgressLabel = $("uploadProgressLabel"),
  uploadProgressPercent = $("uploadProgressPercent"),
  uploadProgressTrack = $("uploadProgressTrack"),
  uploadProgressBar = $("uploadProgressBar"),
  uploadProgressDetail = $("uploadProgressDetail");
const moveModal = $("moveModal"),
  moveFolderList = $("moveFolderList"),
  moveBreadcrumbs = $("moveBreadcrumbs"),
  moveCurrentPath = $("moveCurrentPath"),
  moveSelectedCount = $("moveSelectedCount"),
  moveHint = $("moveHint"),
  moveConfirm = $("moveConfirm");
const conflictModal = $("conflictModal"),
  conflictMessage = $("conflictMessage"),
  uploadCancel = $("uploadCancel"),
  uploadRetry = $("uploadRetry");
const mediaModal = $("mediaModal"),
  mediaStage = $("mediaStage"),
  mediaTitle = $("mediaTitle"),
  mediaPath = $("mediaPath"),
  mediaMessage = $("mediaMessage"),
  mediaFormat = $("mediaFormat"),
  mediaDownload = $("mediaDownload"),
  audioArtwork = $("audioArtwork"),
  audioPlayer = $("audioPlayer"),
  videoPlayer = $("videoPlayer");

function cleanPath(path) {
  return String(path || "")
    .replace(/^\/+|\/+$/g, "")
    .replace(/\/{2,}/g, "/");
}
function syncLogoutPath() {
  const input = $("logoutPath");
  if (input) input.value = state.path;
}
function apiUrl(action, params = {}) {
  const u = new URL("api.php", location.href);
  u.searchParams.set("action", action);
  for (const [k, v] of Object.entries(params)) u.searchParams.set(k, v);
  return u;
}
function absoluteUrl(url) {
  try {
    return new URL(String(url || ""), location.href).href;
  } catch {
    return String(url || "");
  }
}
function filePublicUrl(item) {
  return absoluteUrl(
    item.public_url ||
      item.download_url ||
      apiUrl("download", { path: item.path }).toString(),
  );
}
function fileDownloadUrl(item) {
  return absoluteUrl(
    item.download_url || apiUrl("download", { path: item.path }).toString(),
  );
}
function fileStreamUrl(item) {
  return absoluteUrl(
    item.stream_url || apiUrl("stream", { path: item.path }).toString(),
  );
}
async function apiGet(action, params = {}) {
  const r = await fetch(apiUrl(action, params), { credentials: "same-origin" });
  if (r.status === 401) {
    location.reload();
    return;
  }
  const j = await r.json();
  if (!j.ok) throw new Error(j.error || "Request failed");
  return j;
}
function responseError(data, status) {
  const error = new Error(data?.error || "Request failed");
  error.status = status;
  error.code = data?.code || "";
  error.data = data;
  return error;
}
async function apiPost(action, body = {}) {
  const r = await fetch(apiUrl(action), {
    method: "POST",
    credentials: "same-origin",
    headers: { "Content-Type": "application/json", "X-CSRF-Token": csrfToken },
    body: JSON.stringify(body),
  });
  if (r.status === 401) {
    location.reload();
    return;
  }
  let j = {};
  try {
    j = await r.json();
  } catch {
    throw new Error("The server returned an invalid response.");
  }
  if (!r.ok || !j.ok) throw responseError(j, r.status);
  return j;
}
async function loadFolder(showToast = false) {
  renderLoading();
  try {
    const data = await apiGet("list", { path: state.path });
    state.entries = data.entries || [];
    pruneSelection();
    render(data.stats);
    if (showToast) toast("Folder refreshed.");
  } catch (e) {
    showError(e.message || String(e));
  }
}
function currentEntries() {
  const q = search.value.trim().toLowerCase();
  if (!q) return state.entries;
  return state.entries.filter(
    (i) => i.name.toLowerCase().includes(q) || i.path.toLowerCase().includes(q),
  );
}
function selectedItems() {
  const byPath = new Map(state.entries.map((i) => [i.path, i]));
  return [...state.selected].map((path) => byPath.get(path)).filter(Boolean);
}
function selectedPaths() {
  return selectedItems().map((i) => i.path);
}
function pruneSelection() {
  const paths = new Set(state.entries.map((i) => i.path));
  for (const path of [...state.selected])
    if (!paths.has(path)) state.selected.delete(path);
}
function truncateName(name, max = 30) {
  name = String(name || "");
  if (name.length <= max) return name;
  const dot = name.lastIndexOf(".");
  const ext = dot > 0 ? name.slice(dot) : "";
  const marker = "(...)";
  if (ext && ext.length < 10) {
    const head = Math.max(3, max - marker.length - ext.length);
    return name.slice(0, head) + marker + ext;
  }
  const tail = Math.min(5, Math.max(2, max - 10));
  const head = Math.max(3, max - marker.length - tail);
  return name.slice(0, head) + marker + name.slice(-tail);
}
function truncateNameCompact(name, isDir = false) {
  name = String(name || "");
  const w = window.innerWidth || 420;
  const max =
    w >= 680 ? 34 : w >= 520 ? 30 : w >= 430 ? 28 : w >= 380 ? 25 : 22;
  return truncateName(name, isDir ? Math.max(18, max - 3) : max);
}
function truncatePath(path, max = 42) {
  path = String(path || "");
  if (path.length <= max) return path;
  const marker = "(...)";
  const tail = Math.min(14, Math.max(8, max - 12));
  const head = Math.max(4, max - marker.length - tail);
  return path.slice(0, head) + marker + path.slice(-tail);
}
function selectionBar() {
  const selected = selectedItems(),
    count = selected.length;
  if (!count) return "";
  const extractable = selected.some((i) => i.type === "file" && i.extractable);
  return `<div class="bulkbar show" id="bulkbar" aria-live="polite"><div class="bulk-summary"><strong>${count}</strong> selected</div><div class="bulk-actions"><button class="action" type="button" data-bulk-action="download">Download as ZIP</button><button class="action" type="button" data-bulk-action="copy">Copy URLs</button><button class="action" type="button" data-bulk-action="move">Move</button><button class="action" type="button" data-bulk-action="extract" ${extractable ? "" : "disabled"}>Extract</button><button class="action danger" type="button" data-bulk-action="delete">Delete</button><button class="action" type="button" data-bulk-action="clear">Deselect</button></div></div>`;
}
function render(s) {
  renderBreadcrumbs();
  selectionBarHost.innerHTML = selectionBar();
  const entries = currentEntries();
  if (!s) {
    const folders = entries.filter((i) => i.type === "dir").length,
      files = entries.filter((i) => i.type === "file").length,
      total = entries
        .filter((i) => i.type === "file")
        .reduce((a, i) => a + Number(i.size || 0), 0);
    s = { folders, files, total_size_label: formatBytes(total) };
  }
  const selectedCount = selectedItems().length;
  stats.textContent = `${s.folders} folder${s.folders === 1 ? "" : "s"} · ${s.files} file${s.files === 1 ? "" : "s"} · ${s.total_size_label}${selectedCount ? ` · ${selectedCount} selected` : ""}`;
  if (!entries.length) {
    content.innerHTML = `<div class="message">No files found in this folder.</div>`;
    setupSelectionControls();
    setupDragAndDrop();
    return;
  }
  content.innerHTML = `<table class="file-table manager-table"><thead><tr><th class="select-col"><input id="selectAll" type="checkbox" aria-label="Select all visible items"></th><th class="name-cell">Name</th><th class="hide-sm modified-cell">Modified</th><th class="right size-cell">Size</th><th class="right actions-cell"></th></tr></thead><tbody>${entries.map(renderRow).join("")}</tbody></table>`;
  setupSelectionControls();
  setupDragAndDrop();
}
function itemMenu(label, items) {
  return `<div class="item-menu" data-item-menu><button class="action item-menu-toggle" type="button" aria-label="More actions for ${escapeAttr(label)}" aria-haspopup="menu" aria-expanded="false">⋯</button><div class="item-menu-list" role="menu">${items}</div></div>`;
}
function renderRow(item) {
  const isDir = item.type === "dir",
    icon = isDir ? "📁" : fileIcon(item.name),
    isSelected = state.selected.has(item.path);
  let actions, click;
  if (isDir) {
    const downloadUrl = fileDownloadUrl(item);
    actions = itemMenu(
      item.name,
      `<a class="action" href="${escapeAttr(downloadUrl)}" download="${escapeAttr(item.download_name || item.name + ".zip")}">Download ZIP</a><button class="action" type="button" data-item-action="move">Move</button><button class="action" type="button" data-item-action="rename">Rename</button><button class="action" type="button" data-item-action="copy">Copy URL</button><button class="action danger" type="button" data-item-action="delete">Delete</button>`,
    );
    click = `href="?path=${encodeURIComponent(item.path)}" data-open-action="folder"`;
  } else {
    const downloadUrl = fileDownloadUrl(item);
    const playAction = item.media_type
      ? `<button class="action" type="button" data-item-action="play">Play</button>`
      : "";
    actions = itemMenu(
      item.name,
      `${playAction}<a class="action" href="${escapeAttr(downloadUrl)}" download="${escapeAttr(item.download_name || item.name)}">Download</a><button class="action" type="button" data-item-action="move">Move</button><button class="action" type="button" data-item-action="rename">Rename</button>${item.extractable ? `<button class="action" type="button" data-item-action="extract">Extract</button>` : ""}<button class="action" type="button" data-item-action="copy">Copy URL</button><button class="action danger" type="button" data-item-action="delete">Delete</button>`,
    );
    click = item.media_type
      ? `href="#" data-open-action="media" class="name file-name"`
      : item.editable
        ? `href="#" data-open-action="edit" class="name file-name"`
        : `href="${escapeAttr(downloadUrl)}" download="${escapeAttr(item.download_name || item.name)}" class="name file-name"`;
  }
  const nameMarkup = isDir ? `<a class="name" ${click}>` : `<a ${click}>`;
  const modified = formatDate(item.modified);
  const sizeLabel = isDir
    ? "—"
    : escapeHtml(item.size_label || formatBytes(item.size));
  return `<tr class="${isDir ? "folder" : "file"}${isSelected ? " selected" : ""}" draggable="true" data-path="${escapeAttr(item.path)}" data-name="${escapeAttr(item.name)}" data-public-url="${escapeAttr(filePublicUrl(item))}" data-type="${item.type}" ${isDir ? `data-drop-folder="${escapeAttr(item.path)}"` : ""}><td class="select-col"><input class="row-select" type="checkbox" aria-label="Select ${escapeAttr(item.name)}" ${isSelected ? "checked" : ""}></td><td class="name-cell">${nameMarkup}<span class="icon">${icon}</span><span class="truncate-text" title="${escapeAttr(item.path)}">${escapeHtml(item.name)}</span></a><div class="row-meta"><span>${escapeHtml(modified)}</span><span>${sizeLabel}</span></div></td><td class="muted hide-sm modified-cell">${escapeHtml(modified)}</td><td class="right muted size-cell"><span class="size-value">${sizeLabel}</span></td><td class="right actions-cell"><div class="actions">${actions}</div></td></tr>`;
}
function openFolder(path) {
  state.path = cleanPath(path);
  syncLogoutPath();
  state.selected.clear();
  search.value = "";
  const u = new URL(location.href);
  if (state.path) u.searchParams.set("path", state.path);
  else u.searchParams.delete("path");
  history.pushState(null, "", u);
  loadFolder();
}
function renderBreadcrumbs() {
  const parts = state.path ? state.path.split("/") : [];
  let html = `<button type="button" data-drop-folder="" data-nav-path="">root</button>`;
  parts.forEach((part, index) => {
    const path = parts.slice(0, index + 1).join("/");
    html += `<span class="sep">/</span><button type="button" data-drop-folder="${escapeAttr(path)}" data-nav-path="${escapeAttr(path)}">${escapeHtml(part)}</button>`;
  });
  breadcrumbs.innerHTML = html;
}
function renderLoading() {
  renderBreadcrumbs();
  selectionBarHost.innerHTML = "";
  stats.textContent = "Loading...";
  content.innerHTML = `<div class="message">Loading folder...</div>`;
}
function showError(message) {
  renderBreadcrumbs();
  selectionBarHost.innerHTML = "";
  stats.textContent = "Error";
  content.innerHTML = `<div class="message error"><strong>Could not load folder.</strong><br>${escapeHtml(message)}</div>`;
}

function setupSelectionControls() {
  const selectAll = $("selectAll");
  if (!selectAll) return;
  const entries = currentEntries();
  const selectedVisible = entries.filter((i) =>
    state.selected.has(i.path),
  ).length;
  selectAll.checked = entries.length > 0 && selectedVisible === entries.length;
  selectAll.indeterminate =
    selectedVisible > 0 && selectedVisible < entries.length;
}
function toggleSelected(path, checked) {
  path = cleanPath(path);
  if (!path) return;
  if (checked) state.selected.add(path);
  else state.selected.delete(path);
  render();
}
function toggleSelectAll(checked) {
  for (const item of currentEntries()) {
    if (checked) state.selected.add(item.path);
    else state.selected.delete(item.path);
  }
  render();
}
function clearSelection() {
  state.selected.clear();
  render();
}
function downloadSelected() {
  const paths = selectedPaths();
  if (!paths.length) {
    toast("No selected items.");
    return;
  }
  let frame = $("downloadFrame");
  if (!frame) {
    frame = document.createElement("iframe");
    frame.id = "downloadFrame";
    frame.name = "downloadFrame";
    frame.hidden = true;
    document.body.appendChild(frame);
  }
  const form = document.createElement("form");
  form.method = "post";
  form.action = apiUrl("download_batch");
  form.target = "downloadFrame";
  for (const [name, value] of Object.entries({
    paths: JSON.stringify(paths),
    csrf_token: csrfToken,
  })) {
    const input = document.createElement("input");
    input.type = "hidden";
    input.name = name;
    input.value = value;
    form.appendChild(input);
  }
  document.body.appendChild(form);
  form.submit();
  form.remove();
  toast(
    `Preparing ${paths.length} selected item${paths.length === 1 ? "" : "s"} as a ZIP...`,
  );
}
async function copySelectedUrls() {
  const items = selectedItems();
  if (!items.length) {
    toast("No selected items.");
    return;
  }
  const urls = items.map((item) => filePublicUrl(item));
  await copyText(
    urls.join("\n"),
    `Copied ${urls.length} URL${urls.length === 1 ? "" : "s"}`,
  );
}
async function deleteSelected() {
  const paths = selectedPaths();
  if (!paths.length) {
    toast("No selected items.");
    return;
  }
  const preview =
    paths.slice(0, 8).join("\n") + (paths.length > 8 ? "\n..." : "");
  if (
    !confirm(
      `Delete ${paths.length} selected item${paths.length === 1 ? "" : "s"}?\n\n${preview}`,
    )
  )
    return;
  await deleteItems(paths);
}
async function moveSelectedPrompt() {
  const paths = selectedPaths();
  if (!paths.length) {
    toast("No selected items.");
    return;
  }
  openMovePicker(paths);
}
function closeConflictModal(value = null) {
  conflictModal.classList.remove("show");
  conflictModal.setAttribute("aria-hidden", "true");
  const resolve = state.conflictResolver;
  state.conflictResolver = null;
  if (resolve) resolve(value);
}
function chooseConflictPolicy(message) {
  if (state.conflictResolver) return Promise.resolve(null);
  conflictMessage.textContent = message;
  conflictModal.classList.add("show");
  conflictModal.setAttribute("aria-hidden", "false");
  return new Promise((resolve) => {
    state.conflictResolver = resolve;
    setTimeout(
      () => conflictModal.querySelector('[data-conflict="skip"]')?.focus(),
      0,
    );
  });
}
async function extractSelected() {
  const items = selectedItems().filter(
    (i) => i.type === "file" && i.extractable,
  );
  if (!items.length) {
    toast("No selected ZIP archives.");
    return;
  }
  const policy = await chooseConflictPolicy(
    `Choose how existing items should be handled while extracting ${items.length} archive${items.length === 1 ? "" : "s"}.`,
  );
  if (!policy) return;
  let ok = 0,
    errors = [];
  for (const item of items) {
    try {
      await apiPost("extract", { path: item.path, conflict: policy });
      ok++;
    } catch (e) {
      errors.push(`${item.path}: ${e.message || String(e)}`);
    }
  }
  toast(
    errors.length
      ? `Extracted ${ok}; failed ${errors.length}: ${errors[0]}`
      : `Extracted ${ok} archive${ok === 1 ? "" : "s"}.`,
  );
  await loadFolder();
}

function simpleUploadReason(reason) {
  reason = String(reason || "").toLowerCase();
  if (
    reason.includes("protected") ||
    reason.includes("server behavior") ||
    reason.includes("file manager")
  )
    return "access protected/system file";
  if (reason.includes("dot") || reason.includes("hidden"))
    return "hidden/system file";
  const ext =
    reason.match(/ending in \.([a-z0-9]+)/i) ||
    reason.match(/extension \.([a-z0-9]+)/i);
  if (ext) return `blocked .${ext[1]} file`;
  if (reason.includes("too large")) return "file too large";
  return "blocked file";
}
function summarizeUploadErrors(errors) {
  const groups = new Map();
  for (const err of errors || []) {
    const text = String(err || "").trim();
    let name = "";
    let reason = text;
    const match = text.match(/^(.+?) was not uploaded\.\s*(.+)$/);
    if (match) {
      name = match[1];
      reason = match[2];
    }
    const label = simpleUploadReason(reason);
    if (!groups.has(label)) groups.set(label, { count: 0, names: [] });
    const group = groups.get(label);
    group.count++;
    if (name) group.names.push(name);
  }
  return [...groups.entries()]
    .map(([label, group]) => {
      const names =
        group.names.slice(0, 4).join(", ") +
        (group.names.length > 4 ? ", ..." : "");
      const filePart = names || "file";
      const countPart = group.count > 1 ? ` (x${group.count})` : "";
      return `Blocked ${filePart} upload: ${label}${countPart}`;
    })
    .join(" | ");
}
function fileRelativePath(file) {
  return file.relativePath || file.webkitRelativePath || file.name;
}
let uploadInProgress = false,
  uploadProgressTimer = 0;
function setUploadControlsDisabled(disabled) {
  document
    .querySelectorAll("#uploadMenuBtn,[data-upload]")
    .forEach((button) => (button.disabled = disabled));
  uploadInput.disabled = disabled;
  uploadFolderInput.disabled = disabled;
  $("browserPanel").setAttribute("aria-busy", disabled ? "true" : "false");
}
function showUploadProgress(percent, label, detail = "", status = "") {
  clearTimeout(uploadProgressTimer);
  uploadProgress.classList.remove("error", "partial");
  if (status) uploadProgress.classList.add(status);
  uploadProgress.classList.add("show");
  uploadProgressLabel.textContent = label;
  uploadProgressDetail.textContent = detail;
  const determinate = typeof percent === "number" && Number.isFinite(percent);
  if (determinate) {
    const value = Math.max(0, Math.min(100, Math.round(percent)));
    uploadProgressBar.classList.remove("indeterminate");
    uploadProgressBar.style.width = value + "%";
    uploadProgressPercent.textContent = value + "%";
    uploadProgressTrack.setAttribute("aria-valuenow", String(value));
  } else {
    uploadProgressBar.style.width = "";
    uploadProgressBar.classList.add("indeterminate");
    uploadProgressPercent.textContent = "Working...";
    uploadProgressTrack.removeAttribute("aria-valuenow");
  }
}
function hideUploadProgress(delay = 2500) {
  clearTimeout(uploadProgressTimer);
  uploadProgressTimer = setTimeout(() => {
    uploadProgress.classList.remove("show", "error", "partial");
  }, delay);
}
function cancelUploads() {
  state.uploadCancelled = true;
  for (const xhr of state.activeUploads) xhr.abort();
}
function handleUploadCancel() {
  if (uploadInProgress) {
    uploadCancel.disabled = true;
    uploadCancel.textContent = "Cancelling...";
    cancelUploads();
    return;
  }
  uploadCancel.hidden = true;
  uploadRetry.hidden = true;
  hideUploadProgress(0);
}
function uploadFailureDetail(uploaded, skipped) {
  const blocked = state.uploadFailures.filter(
    (failure) => !failure.retryable,
  ).length;
  const retryable = state.failedUploads.length;
  const counts = [`${uploaded} uploaded`, `${skipped} skipped`];
  if (blocked) counts.push(`${blocked} blocked`);
  if (retryable) counts.push(`${retryable} available to retry`);
  const reasons = [
    ...new Set(state.uploadFailures.map((failure) => failure.reason)),
  ];
  const visibleReasons = reasons.slice(0, 4);
  if (reasons.length > visibleReasons.length) {
    visibleReasons.push(
      `${reasons.length - visibleReasons.length} more upload error(s).`,
    );
  }
  return [counts.join(", ") + ".", ...visibleReasons].join("\n");
}
function uploadRequest(file, policy, onProgress) {
  return new Promise((resolve, reject) => {
    const form = new FormData();
    form.append("path", state.path);
    form.append("conflict", policy);
    form.append("files[]", file, file.name);
    form.append("paths[]", fileRelativePath(file));
    const xhr = new XMLHttpRequest();
    state.activeUploads.add(xhr);
    xhr.open("POST", apiUrl("upload", { path: state.path }), true);
    xhr.withCredentials = true;
    xhr.timeout = 30 * 60 * 1000;
    xhr.setRequestHeader("X-CSRF-Token", csrfToken);
    const finish = () => state.activeUploads.delete(xhr);
    xhr.upload.addEventListener("progress", (event) =>
      onProgress(
        event.loaded,
        event.lengthComputable ? event.total : Number(file.size || 0),
      ),
    );
    xhr.addEventListener("load", () => {
      finish();
      if (xhr.status === 401) {
        location.reload();
        reject(new Error("Your session expired. Please sign in again."));
        return;
      }
      let data;
      try {
        data = JSON.parse(xhr.responseText || "{}");
      } catch {
        reject(
          new Error(
            "The server returned an invalid response. Check the PHP request-size limits.",
          ),
        );
        return;
      }
      if (xhr.status < 200 || xhr.status >= 300 || !data.ok) {
        const error = responseError(data, xhr.status);
        if (Array.isArray(data.errors) && data.errors.length) {
          error.message = summarizeUploadErrors(data.errors);
          error.retryable = false;
        }
        reject(error);
        return;
      }
      resolve(data);
    });
    xhr.addEventListener("error", () => {
      finish();
      reject(
        new Error(
          "The upload connection failed. Check your connection and retry this file.",
        ),
      );
    });
    xhr.addEventListener("abort", () => {
      finish();
      reject(new Error("Upload cancelled."));
    });
    xhr.addEventListener("timeout", () => {
      finish();
      reject(new Error("The upload timed out. Retry this file."));
    });
    xhr.send(form);
  });
}
async function uploadFiles(files, presetPolicy = null) {
  files = [...files];
  if (!files.length) return;
  if (uploadInProgress) {
    toast("An upload is already in progress.");
    return;
  }
  // Start safely without interrupting every upload. The server reports actual
  // collisions while using "skip", and only then do we ask what to do.
  const policy = presetPolicy || "skip";
  uploadInProgress = true;
  state.uploadCancelled = false;
  state.failedUploads = [];
  state.uploadFailures = [];
  state.lastConflictPolicy = policy;
  setUploadControlsDisabled(true);
  uploadCancel.disabled = false;
  uploadCancel.textContent = "Cancel";
  uploadCancel.hidden = false;
  uploadRetry.hidden = true;
  const totalBytes = files.reduce(
    (sum, file) => sum + Number(file.size || 0),
    0,
  );
  const loaded = new Map(),
    settled = new Set();
  const conflictFiles = [];
  let conflictRetry = null;
  let cursor = 0,
    uploaded = 0,
    skipped = 0;
  const updateProgress = () => {
    const sent = [...loaded.values()].reduce((sum, value) => sum + value, 0);
    const percent =
      totalBytes > 0
        ? Math.min(99, Math.round((sent / totalBytes) * 100))
        : null;
    showUploadProgress(
      percent,
      `Uploading ${uploaded + skipped}/${files.length} completed`,
      `${formatBytes(sent)} of ${formatBytes(totalBytes)}`,
    );
  };
  showUploadProgress(
    0,
    `Preparing ${files.length} file${files.length === 1 ? "" : "s"}...`,
    `${formatBytes(totalBytes)} total`,
  );
  const worker = async () => {
    while (!state.uploadCancelled) {
      const index = cursor++;
      if (index >= files.length) return;
      const file = files[index];
      try {
        const result = await uploadRequest(file, policy, (value) => {
          loaded.set(index, Math.min(Number(file.size || value), value));
          updateProgress();
        });
        loaded.set(index, Number(file.size || 0));
        uploaded += (result.uploaded || []).length;
        const conflicts = result.conflicts || [];
        skipped += conflicts.length;
        if (conflicts.length) conflictFiles.push(file);
        settled.add(index);
      } catch (error) {
        if (!state.uploadCancelled) {
          const retryable = error.retryable !== false;
          const reason =
            error.message && error.message !== "Request failed"
              ? error.message
              : `${fileRelativePath(file)} could not be uploaded.`;
          state.uploadFailures.push({ file, reason, retryable });
          if (retryable) state.failedUploads.push(file);
          settled.add(index);
        }
      }
      updateProgress();
    }
  };
  try {
    await Promise.all(
      Array.from({ length: Math.min(2, files.length) }, worker),
    );
    if (state.uploadCancelled) {
      const retryFiles = new Set(state.failedUploads);
      files.forEach((file, index) => {
        if (!settled.has(index)) retryFiles.add(file);
      });
      state.failedUploads = [...retryFiles];
    }
    if (
      !state.uploadCancelled &&
      presetPolicy === null &&
      conflictFiles.length
    ) {
      const names = conflictFiles
        .slice(0, 5)
        .map(fileRelativePath)
        .join(", ");
      const more = conflictFiles.length > 5 ? ", ..." : "";
      const choice = await chooseConflictPolicy(
        `${conflictFiles.length} file${conflictFiles.length === 1 ? "" : "s"} already ${conflictFiles.length === 1 ? "exists" : "exist"}: ${names}${more}`,
      );
      if (choice && choice !== "skip") {
        conflictRetry = { files: conflictFiles, policy: choice };
      }
    }
    const failed = state.uploadFailures.length;
    if (conflictRetry) {
      uploadCancel.hidden = true;
      uploadRetry.hidden = true;
      showUploadProgress(
        null,
        "Resolving existing files...",
        `Continuing with ${conflictFiles.length} conflicting file${conflictFiles.length === 1 ? "" : "s"}.`,
        "partial",
      );
    } else if (state.uploadCancelled) {
      uploadCancel.disabled = false;
      uploadCancel.textContent = "Close";
      uploadCancel.hidden = false;
      uploadRetry.hidden = state.failedUploads.length === 0;
      showUploadProgress(
        null,
        "Upload cancelled",
        `${uploaded} uploaded, ${skipped} skipped, ${state.failedUploads.length} available to retry.`,
        "partial",
      );
    } else if (failed) {
      uploadCancel.disabled = false;
      uploadCancel.textContent = "Close";
      uploadCancel.hidden = false;
      uploadRetry.hidden = state.failedUploads.length === 0;
      showUploadProgress(
        100,
        state.failedUploads.length
          ? "Upload finished with errors"
          : "Upload blocked",
        uploadFailureDetail(uploaded, skipped),
        "error",
      );
      toast(state.uploadFailures[0].reason);
    } else {
      uploadCancel.hidden = true;
      showUploadProgress(
        100,
        "Upload complete",
        `${uploaded} uploaded${skipped ? `, ${skipped} existing skipped` : ""}.`,
        skipped ? "partial" : "",
      );
      hideUploadProgress(skipped ? 5000 : 2500);
    }
    if (!conflictRetry) await loadFolder();
  } finally {
    uploadInProgress = false;
    setUploadControlsDisabled(false);
    uploadInput.value = "";
    uploadFolderInput.value = "";
  }
  if (conflictRetry) {
    return uploadFiles(conflictRetry.files, conflictRetry.policy);
  }
}
function readEntryFile(entry, pathPrefix = "") {
  return new Promise((resolve) => {
    entry.file(
      (file) => {
        file.relativePath = pathPrefix + file.name;
        resolve([file]);
      },
      () => resolve([]),
    );
  });
}
function readDirectoryEntries(reader) {
  return new Promise((resolve) => {
    const entries = [];
    function readBatch() {
      reader.readEntries(
        (batch) => {
          if (!batch.length) {
            resolve(entries);
            return;
          }
          entries.push(...batch);
          readBatch();
        },
        () => resolve(entries),
      );
    }
    readBatch();
  });
}
async function readEntry(entry, pathPrefix = "") {
  if (entry.isFile) return readEntryFile(entry, pathPrefix);
  if (entry.isDirectory) {
    const dirPrefix = pathPrefix + entry.name + "/";
    const reader = entry.createReader();
    const entries = await readDirectoryEntries(reader);
    const files = [];
    for (const child of entries)
      files.push(...(await readEntry(child, dirPrefix)));
    return files;
  }
  return [];
}
async function filesFromDropEvent(e) {
  const transfer = e.dataTransfer;
  const fallbackFiles = [...(transfer.files || [])];
  const items = [...(transfer.items || [])];
  if (!items.length) return fallbackFiles;
  const dropped = items.map((item) => {
    const entry = item.webkitGetAsEntry ? item.webkitGetAsEntry() : null;
    const file = !entry && item.getAsFile ? item.getAsFile() : null;
    return { entry, file };
  });
  const hasDirectory = dropped.some(
    (item) => item.entry && item.entry.isDirectory,
  );
  if (!hasDirectory && fallbackFiles.length) return fallbackFiles;
  const batches = await Promise.all(
    dropped.map((item) =>
      item.entry ? readEntry(item.entry, "") : item.file ? [item.file] : [],
    ),
  );
  const files = batches.flat();
  return files.length ? files : fallbackFiles;
}
function editorInsideSelected(paths) {
  return (
    !!state.editing &&
    paths.some(
      (path) =>
        state.editing.path === path ||
        state.editing.path.startsWith(path + "/"),
    )
  );
}
async function deleteItems(paths) {
  paths = [...new Set(paths.map(cleanPath).filter(Boolean))];
  let ok = 0,
    errors = [];
  for (const path of paths) {
    try {
      await apiPost("delete", { path });
      ok++;
    } catch (e) {
      errors.push(`${path}: ${e.message || String(e)}`);
    }
  }
  if (editorInsideSelected(paths)) closeEditor(true);
  toast(
    errors.length
      ? `Deleted ${ok}; failed ${errors.length}: ${errors[0]}`
      : `Deleted ${ok} item${ok === 1 ? "" : "s"}.`,
  );
  state.selected.clear();
  await loadFolder();
}
async function deleteItem(path, type) {
  const label = type === "dir" ? "folder and everything inside it" : "file";
  if (!confirm(`Delete this ${label}?\n\n${path}`)) return;
  await deleteItems([path]);
}
async function renameItem(path, name) {
  const newName = prompt("New name:", name);
  if (!newName || newName === name) return;
  try {
    const data = await apiPost("rename", { path, new_name: newName });
    toast("Renamed.");
    state.selected.delete(path);
    if (state.editing && state.editing.path === path) {
      state.editing.path = data.path;
      state.editing.name = newName;
      editorName.textContent = newName;
      editorPath.textContent = data.path;
    }
    await loadFolder();
  } catch (e) {
    toast(e.message || String(e));
  }
}
async function extractArchive(path) {
  const policy = await chooseConflictPolicy(
    `Choose how existing items should be handled while extracting ${path}.`,
  );
  if (!policy) return;
  try {
    const data = await apiPost("extract", { path, conflict: policy });
    toast(data.message || "Extracted.");
    await loadFolder();
  } catch (e) {
    toast(e.message || String(e));
  }
}
async function createItem(type) {
  const label = type === "dir" ? "folder" : "file";
  const suggested = type === "dir" ? "new-folder" : "new-file.txt";
  const name = prompt(`New ${label} name:`, suggested);
  if (!name) return;
  try {
    const data = await apiPost("create", { type, path: state.path, name });
    toast(data.message || `${label} created.`);
    await loadFolder();
    if (type === "file") editFile(data.path);
  } catch (e) {
    toast(e.message || String(e));
  }
}
function sourceItemForPath(path) {
  return state.entries.find((i) => i.path === path) || null;
}
function moveWouldNest(path, targetDir) {
  const item = sourceItemForPath(path);
  return (
    item &&
    item.type === "dir" &&
    (path === targetDir || targetDir.startsWith(path + "/"))
  );
}
function moveTargetInvalid(targetDir) {
  targetDir = cleanPath(targetDir || "");
  return !!(
    state.movePicker &&
    state.movePicker.paths.some((path) => moveWouldNest(path, targetDir))
  );
}
function openMovePicker(paths) {
  paths = [...new Set(paths.map(cleanPath).filter(Boolean))];
  if (!paths.length) return;
  state.movePicker = { paths, browsePath: state.path };
  moveModal.classList.add("show");
  moveModal.setAttribute("aria-hidden", "false");
  renderMovePicker();
  setTimeout(() => moveConfirm.focus(), 0);
}
function closeMovePicker() {
  state.movePicker = null;
  moveModal.classList.remove("show");
  moveModal.setAttribute("aria-hidden", "true");
}
function movePathLabel(path) {
  path = cleanPath(path);
  return path || "root";
}
function renderMoveBreadcrumbs(path) {
  const parts = path ? path.split("/") : [];
  let html = `<button type="button" data-move-path="">root</button>`;
  parts.forEach((part, index) => {
    const target = parts.slice(0, index + 1).join("/");
    html += `<span class="sep">/</span><button type="button" data-move-path="${escapeAttr(target)}">${escapeHtml(part)}</button>`;
  });
  moveBreadcrumbs.innerHTML = html;
}
async function renderMovePicker() {
  if (!state.movePicker) return;
  const paths = state.movePicker.paths,
    browsePath = cleanPath(state.movePicker.browsePath);
  moveSelectedCount.textContent = `${paths.length} selected item${paths.length === 1 ? "" : "s"}`;
  moveCurrentPath.textContent = movePathLabel(browsePath);
  renderMoveBreadcrumbs(browsePath);
  const invalid = moveTargetInvalid(browsePath);
  moveConfirm.disabled = invalid;
  moveHint.textContent = invalid
    ? "You cannot move a folder into itself or one of its subfolders."
    : "Open a folder below, then click Move here.";
  moveFolderList.innerHTML = `<div class="move-empty">Loading folders...</div>`;
  try {
    const data = await apiGet("list", { path: browsePath });
    if (!state.movePicker || state.movePicker.browsePath !== browsePath) return;
    let dirs = (data.entries || []).filter((i) => i.type === "dir");
    if (browsePath) {
      const parent = browsePath.split("/").slice(0, -1).join("/");
      dirs = [{ type: "dir", name: "..", path: parent, parent: true }, ...dirs];
    }
    if (!dirs.length) {
      moveFolderList.innerHTML = `<div class="move-empty">No folders inside this location.</div>`;
      return;
    }
    moveFolderList.innerHTML = dirs
      .map((dir) => {
        const disabled =
          !dir.parent && paths.some((path) => moveWouldNest(path, dir.path));
        const label = dir.parent ? "Parent folder" : dir.name;
        return `<button class="move-folder" type="button" data-move-path="${escapeAttr(dir.path)}" ${disabled ? 'disabled title="Cannot move into this folder"' : ""}><span class="icon">${dir.parent ? "↩" : "📁"}</span><span>${escapeHtml(label)}</span><span class="muted">${escapeHtml(movePathLabel(dir.path))}</span></button>`;
      })
      .join("");
  } catch (e) {
    moveFolderList.innerHTML = `<div class="move-empty error">${escapeHtml(e.message || String(e))}</div>`;
  }
}
function browseMoveFolder(path) {
  if (!state.movePicker) return;
  path = cleanPath(path);
  if (moveTargetInvalid(path)) {
    toast("Cannot move a folder into itself or one of its subfolders.");
    return;
  }
  state.movePicker.browsePath = path;
  renderMovePicker();
}
async function confirmMovePicker() {
  if (!state.movePicker) return;
  const paths = [...state.movePicker.paths],
    target = cleanPath(state.movePicker.browsePath);
  if (moveTargetInvalid(target)) {
    toast("Cannot move a folder into itself or one of its subfolders.");
    return;
  }
  closeMovePicker();
  await moveItems(paths, target);
}
async function moveItems(paths, targetDir) {
  targetDir = cleanPath(targetDir || "");
  paths = [...new Set(paths.map(cleanPath).filter(Boolean))];
  if (!paths.length) return;
  if (paths.some((path) => moveWouldNest(path, targetDir))) {
    toast("Cannot move a folder into itself or one of its subfolders.");
    return;
  }
  let ok = 0,
    errors = [];
  for (const path of paths) {
    try {
      const data = await apiPost("move", { path, target_dir: targetDir });
      ok++;
      if (state.editing && state.editing.path === path) {
        state.editing.path = data.path;
        editorPath.textContent = data.path;
      }
    } catch (e) {
      errors.push(`${path}: ${e.message || String(e)}`);
    }
  }
  toast(
    errors.length
      ? `Moved ${ok}; failed ${errors.length}: ${errors[0]}`
      : `Moved ${ok} item${ok === 1 ? "" : "s"}.`,
  );
  state.selected.clear();
  await loadFolder();
}
async function moveItem(path, targetDir) {
  await moveItems([path], targetDir);
}
function dragPathsForRow(row) {
  const path = row.dataset.path;
  if (state.selected.has(path)) return selectedPaths();
  return [path];
}
function setupDragAndDrop() {
  document.querySelectorAll("tr[draggable='true']").forEach((row) => {
    row.addEventListener("dragstart", (e) => {
      const paths = dragPathsForRow(row);
      e.dataTransfer.setData("text/plain", paths[0] || row.dataset.path);
      e.dataTransfer.setData(
        "application/x-file-manager-paths",
        JSON.stringify(paths),
      );
      e.dataTransfer.setData("application/x-file-manager-move", "1");
      e.dataTransfer.effectAllowed = "move";
      document.querySelectorAll("tr[data-path]").forEach((r) => {
        if (paths.includes(r.dataset.path)) r.classList.add("dragging");
      });
    });
    row.addEventListener("dragend", () =>
      document
        .querySelectorAll("tr.dragging")
        .forEach((r) => r.classList.remove("dragging")),
    );
  });
  document.querySelectorAll("[data-drop-folder]").forEach((target) => {
    target.addEventListener("dragover", (e) => {
      const dragged = e.dataTransfer.types.includes(
        "application/x-file-manager-move",
      );
      if (!dragged) return;
      e.preventDefault();
      target.classList.add("drop-target");
      e.dataTransfer.dropEffect = "move";
    });
    target.addEventListener("dragleave", () =>
      target.classList.remove("drop-target"),
    );
    target.addEventListener("drop", (e) => {
      e.preventDefault();
      target.classList.remove("drop-target");
      let paths = [];
      try {
        paths = JSON.parse(
          e.dataTransfer.getData("application/x-file-manager-paths") || "[]",
        );
      } catch {
        paths = [];
      }
      if (!paths.length) {
        const path = e.dataTransfer.getData("text/plain");
        if (path) paths = [path];
      }
      const targetDir = target.dataset.dropFolder || "";
      if (paths.length) moveItems(paths, targetDir);
    });
  });
}

async function editFile(path) {
  if (state.editing && state.editing.path === path) {
    openEditorShell(false);
    toast("Already editing this file.");
    return;
  }
  if (
    state.dirty &&
    !confirm("You have unsaved editor changes. Open another file anyway?")
  )
    return;
  openEditorShell(true);
  editorName.textContent = path.split("/").pop();
  editorPath.textContent = path;
  editorText.disabled = true;
  editorText.value = "Loading...";
  setEditorStatus("Loading...", false);
  updateLines();
  try {
    const data = await apiGet("read", { path });
    state.editing = data;
    state.originalText = data.content || "";
    state.dirty = false;
    editorName.textContent = data.name;
    editorPath.textContent = data.path;
    editorText.value = data.content || "";
    editorText.disabled = false;
    editorText.classList.toggle("editor-word-wrap", state.wrap);
    $("syntaxMode").value = "auto";
    setEditorControls(true);
    setEditorStatus("Editing", false);
    updateLines();
    requestAnimationFrame(() => {
      editorText.scrollTop = 0;
      lineNumbers.scrollTop = 0;
      editorText.selectionStart = 0;
      editorText.selectionEnd = 0;
      editorText.focus();
    });
  } catch (e) {
    state.editing = null;
    editorText.value = "";
    editorText.disabled = true;
    setEditorControls(false);
    setEditorStatus(e.message || String(e), false);
  }
}

function mediaItem(path) {
  return state.entries.find((item) => item.path === path) || null;
}
function resetMediaElement(player) {
  player.pause();
  player.removeAttribute("src");
  player.load();
  player.hidden = true;
}
function openMedia(path, trigger = null) {
  const item = mediaItem(path);
  if (!item || !["audio", "video"].includes(item.media_type)) {
    toast("This file cannot be played in the browser.");
    return;
  }

  resetMediaElement(audioPlayer);
  resetMediaElement(videoPlayer);
  state.media = item;
  state.mediaReturnFocus = trigger || document.activeElement;

  mediaTitle.textContent = item.name;
  mediaPath.textContent = item.path;
  mediaFormat.textContent = `${extension(item.name).toUpperCase()} ${item.media_type}`;
  mediaDownload.href = fileDownloadUrl(item);
  mediaDownload.download = item.download_name || item.name;
  mediaMessage.textContent = "Loading media...";
  mediaMessage.classList.remove("error");
  mediaMessage.hidden = false;
  mediaStage.classList.toggle("audio", item.media_type === "audio");
  mediaStage.classList.toggle("video", item.media_type === "video");
  audioArtwork.hidden = item.media_type !== "audio";

  const player = item.media_type === "audio" ? audioPlayer : videoPlayer;
  player.hidden = false;
  player.volume = 0.25;
  player.src = fileStreamUrl(item);
  player.load();

  mediaModal.classList.add("show");
  mediaModal.setAttribute("aria-hidden", "false");
  document.body.classList.add("media-player-open");
  $("mediaClose").focus();

  const playback = player.play();
  if (playback && typeof playback.catch === "function") {
    playback.catch(() => {
      if (state.media === item && !player.error) {
        mediaMessage.textContent = "Ready — press play to start.";
        mediaMessage.hidden = false;
      }
    });
  }
}
function closeMedia() {
  if (!state.media && !mediaModal.classList.contains("show")) return;
  const returnFocus = state.mediaReturnFocus;
  resetMediaElement(audioPlayer);
  resetMediaElement(videoPlayer);
  state.media = null;
  state.mediaReturnFocus = null;
  mediaModal.classList.remove("show");
  mediaModal.setAttribute("aria-hidden", "true");
  document.body.classList.remove("media-player-open");
  if (returnFocus && document.contains(returnFocus)) returnFocus.focus();
}
function handleMediaReady(event) {
  const active = state.media?.media_type === "audio" ? audioPlayer : videoPlayer;
  if (event.currentTarget !== active) return;
  mediaMessage.hidden = true;
  mediaMessage.classList.remove("error");
}
function handleMediaError(event) {
  const active = state.media?.media_type === "audio" ? audioPlayer : videoPlayer;
  if (event.currentTarget !== active || !state.media) return;
  mediaMessage.textContent =
    "Playback failed. This browser may not support the file's codec.";
  mediaMessage.classList.add("error");
  mediaMessage.hidden = false;
}
async function saveFile() {
  if (!state.editing) return;
  try {
    setEditorStatus("Saving...", false);
    const result = await apiPost("save", {
      path: state.editing.path,
      content: editorText.value,
      expected_version: state.editing.version,
    });
    state.editing.version = result.version;
    state.originalText = editorText.value;
    state.dirty = false;
    setEditorStatus("Saved", false);
    setEditorControls(true);
    toast("Saved.");
    await loadFolder();
  } catch (e) {
    setEditorStatus(
      e.code === "edit_conflict" ? "Changed on server" : "Save failed",
      true,
    );
    toast(e.message || String(e));
  }
}
function openEditorShell() {
  if (!editorModal.classList.contains("show")) {
    state.editorReturnFocus = document.activeElement;
  }
  editorModal.classList.add("show");
  editorModal.setAttribute("aria-hidden", "false");
  editorShell.classList.add("open");
  document.body.classList.add("editor-open");
}
function closeEditorToolsMenu() {
  editorToolsMenuGroup.classList.remove("open");
  editorToolsMenuBtn.setAttribute("aria-expanded", "false");
}
function closeEditor(force = false) {
  if (
    !force &&
    state.dirty &&
    !confirm("Close editor and discard unsaved changes?")
  )
    return false;
  const returnFocus = state.editorReturnFocus;
  state.editing = null;
  state.originalText = "";
  state.dirty = false;
  state.editorReturnFocus = null;
  editorText.value = "";
  editorText.disabled = true;
  editorText.classList.remove("wrap");
  editorName.textContent = "No file selected";
  editorPath.textContent =
    "Click a text file name from the file browser to edit it.";
  setEditorStatus("Idle", false);
  setEditorControls(false);
  closeEditorToolsMenu();
  editorModal.classList.remove("show");
  editorModal.setAttribute("aria-hidden", "true");
  editorShell.classList.remove("open");
  document.body.classList.remove("editor-open");
  updateLines();
  if (returnFocus && document.contains(returnFocus)) returnFocus.focus();
  return true;
}
function setEditorControls(on) {
  $("saveFile").disabled = !on || !state.dirty;
  $("downloadEditor").disabled = !on;
  $("copyFileUrl").disabled = !state.editing;
  $("formatFile").disabled = !on;
  $("trimLines").disabled = !on;
  $("tabsToSpaces").disabled = !on;
  $("wrapToggle").disabled = !on;
}
function setEditorStatus(text, dirty) {
  editorStatus.textContent = text;
  editorStatus.classList.toggle("dirty", !!dirty);
}
function markDirty() {
  state.dirty = !!state.editing && editorText.value !== state.originalText;
  setEditorStatus(state.dirty ? "Unsaved edits" : "Editing", state.dirty);
  setEditorControls(!!state.editing);
}
function downloadEditor() {
  if (!state.editing) return;
  downloadBlob(
    editorText.value,
    state.editing.name,
    "text/plain;charset=utf-8",
  );
  setEditorStatus("File downloaded", state.dirty);
}
function downloadBlob(text, name, type) {
  const blob = new Blob([text], { type: type || "text/plain" }),
    url = URL.createObjectURL(blob),
    a = document.createElement("a");
  a.href = url;
  a.download = name || "edited-file.txt";
  document.body.appendChild(a);
  a.click();
  a.remove();
  URL.revokeObjectURL(url);
}
function formatEditor() {
  if (!state.editing) return;
  const lang = detectLang();
  try {
    let out = editorText.value;
    if (lang === "json") out = JSON.stringify(JSON.parse(out), null, 2) + "\n";
    else if (lang === "html") out = formatHtml(out);
    else if (lang === "css") out = formatCss(out);
    else if (lang === "js") out = formatJs(out);
    else out = trimTrailing(out);
    editorText.value = out;
    markDirty();
    updateLines();
    toast("Formatted.");
  } catch (e) {
    toast("Format failed: " + (e.message || String(e)));
  }
}
function detectLang() {
  const chosen = $("syntaxMode").value;
  if (chosen !== "auto") return chosen;
  if (!state.editing) return "plain";
  const ext = extension(state.editing.name);
  if (ext === "json") return "json";
  if (["html", "htm", "xml", "svg"].includes(ext)) return "html";
  if (["css", "scss"].includes(ext)) return "css";
  if (["js", "mjs", "cjs", "ts", "tsx", "jsx"].includes(ext)) return "js";
  if (["cfg", "ini", "res", "vmt"].includes(ext)) return "cfg";
  return "plain";
}
function trimTrailing(text) {
  return text
    .split(/\r?\n/)
    .map((l) => l.trimEnd())
    .join("\n");
}
function formatCss(text) {
  return (
    text
      .replace(/\s*{\s*/g, " {\n  ")
      .replace(/;\s*/g, ";\n  ")
      .replace(/\s*}\s*/g, "\n}\n\n")
      .replace(/\n\s*\n\s*\n/g, "\n\n")
      .replace(/[ \t]+\n/g, "\n")
      .trim() + "\n"
  );
}
function formatJs(text) {
  return (
    text
      .replace(/\s*{\s*/g, " {\n  ")
      .replace(/;\s*/g, ";\n")
      .replace(/\s*}\s*/g, "\n}\n")
      .replace(/\n\s*\n\s*\n/g, "\n\n")
      .replace(/[ \t]+\n/g, "\n")
      .trim() + "\n"
  );
}
function formatHtml(text) {
  const tokens = text
    .replace(/>\s*</g, "><")
    .split(/(?=<)|(?<=>)/g)
    .filter(Boolean);
  let indent = 0,
    lines = [];
  for (let token of tokens) {
    token = token.trim();
    if (!token) continue;
    if (/^<\//.test(token)) indent = Math.max(indent - 1, 0);
    lines.push("  ".repeat(indent) + token);
    if (
      /^<[^!?/][^>]*[^/]?>$/.test(token) &&
      !/^<(area|base|br|col|embed|hr|img|input|link|meta|param|source|track|wbr)\b/i.test(
        token,
      )
    )
      indent++;
  }
  return lines.join("\n").trim() + "\n";
}
function updateLines() {
  const count = Math.max(1, editorText.value.split("\n").length);
  let out = "";
  for (let i = 1; i <= count; i++) out += i + "\n";
  lineNumbers.textContent = out;
}
function applyTheme() {
  const resolvedTheme =
    state.themePreference === "system"
      ? themeMedia.matches
        ? "light"
        : "dark"
      : state.themePreference;
  document.body.classList.toggle("light", resolvedTheme === "light");
  document.documentElement.style.colorScheme = resolvedTheme;
  localStorage.setItem(
    "file-manager-theme-preference",
    state.themePreference,
  );
  const labels = {
    system: { icon: "◐", name: "System", next: "Light" },
    light: { icon: "☀", name: "Light", next: "Dark" },
    dark: { icon: "☾", name: "Dark", next: "System" },
  };
  const current = labels[state.themePreference];
  const button = $("themeToggle");
  if (button) {
    $("themeToggleIcon").textContent = current.icon;
    button.title = `Theme: ${current.name}. Click for ${current.next}.`;
    button.setAttribute(
      "aria-label",
      `Theme: ${current.name}. Switch to ${current.next}.`,
    );
  }
}
function toggleTheme() {
  state.themePreference =
    { system: "light", light: "dark", dark: "system" }[
      state.themePreference
    ] || "system";
  applyTheme();
}
function fileIcon(name) {
  const ext = extension(name);
  if (["bz2", "zip", "7z", "rar"].includes(ext)) return "🗜️";
  if (["bsp", "nav"].includes(ext)) return "🗺️";
  if (["vmt", "vtf", "png", "jpg", "jpeg", "webp", "gif"].includes(ext))
    return "🖼️";
  if (["wav", "mp3", "ogg", "oga", "opus", "m4a", "aac", "flac"].includes(ext)) return "🔊";
  if (["mp4", "m4v", "webm", "ogv", "mov"].includes(ext)) return "🎬";
  if (["mdl", "vvd", "phy", "vtx"].includes(ext)) return "🧩";
  if (["txt", "cfg", "res", "json", "html", "css", "js"].includes(ext))
    return "📄";
  return "📦";
}
function extension(name) {
  const i = String(name).lastIndexOf(".");
  return i >= 0
    ? String(name)
        .slice(i + 1)
        .toLowerCase()
    : "";
}
function formatBytes(bytes) {
  bytes = Number(bytes || 0);
  if (!bytes) return "0 B";
  const units = ["B", "KB", "MB", "GB", "TB"],
    i = Math.min(
      Math.floor(Math.log(bytes) / Math.log(1024)),
      units.length - 1,
    );
  return `${(bytes / Math.pow(1024, i)).toFixed(i === 0 ? 0 : 1)} ${units[i]}`;
}
function formatDate(ts) {
  ts = Number(ts || 0);
  if (!ts) return "—";
  const d = new Date(ts * 1000);
  if (Number.isNaN(d.getTime())) return "—";
  return d.toLocaleString([], {
    year: "numeric",
    month: "2-digit",
    day: "2-digit",
    hour: "2-digit",
    minute: "2-digit",
  });
}
async function copyText(text, message) {
  try {
    await navigator.clipboard.writeText(text);
    toast(message || "Copied");
  } catch {
    prompt("Copy this:", text);
  }
}
function toast(message) {
  const el = $("toast");
  el.textContent = message;
  el.classList.remove("hiding");
  el.classList.add("show");
  clearTimeout(toast.timer);
  clearTimeout(toast.hideTimer);
  toast.timer = setTimeout(() => {
    el.classList.remove("show");
    el.classList.add("hiding");
    toast.hideTimer = setTimeout(() => el.classList.remove("hiding"), 280);
  }, 6500);
}
function escapeHtml(str) {
  return String(str)
    .replaceAll("&", "&amp;")
    .replaceAll("<", "&lt;")
    .replaceAll(">", "&gt;")
    .replaceAll('"', "&quot;")
    .replaceAll("'", "&#039;");
}
function escapeAttr(str) {
  return escapeHtml(str);
}

search.addEventListener("input", () => render());
let lastRenderWidth = window.innerWidth;
window.addEventListener("resize", () => {
  clearTimeout(window.__resizeRenderTimer);
  window.__resizeRenderTimer = setTimeout(() => {
    lastRenderWidth = window.innerWidth;
    render();
    updateMobileMenuState();
  }, 120);
});
window.addEventListener("orientationchange", () =>
  setTimeout(() => {
    render();
    updateMobileMenuState();
  }, 220),
);
const newDropdown = $("newDropdown");
const uploadDropdown = $("uploadDropdown"),
  uploadMenuBtn = $("uploadMenuBtn");
function closeDropdowns(except = null) {
  if (except !== "new") newDropdown.classList.remove("open");
  if (except !== "upload") uploadDropdown.classList.remove("open");
}
function updateMobileMenuState() {
  const hasOpen =
    window.innerWidth <= 760 && !!document.querySelector(".item-menu.open");
  document.body.classList.toggle("mobile-menu-open", hasOpen);
}
function positionItemMenu(menu) {
  const list = menu?.querySelector(".item-menu-list");
  const toggle = menu?.querySelector(".item-menu-toggle");
  if (!list || !toggle) return;

  menu.classList.remove("open-up");
  list.style.removeProperty("--item-menu-max-height");
  if (window.innerWidth > 760) return;

  const rect = toggle.getBoundingClientRect();
  const viewportTop = window.visualViewport?.offsetTop || 0;
  const viewportHeight = window.visualViewport?.height || window.innerHeight;
  const viewportBottom = viewportTop + viewportHeight;
  const spaceAbove = Math.max(0, rect.top - viewportTop - 12);
  const spaceBelow = Math.max(0, viewportBottom - rect.bottom - 12);
  const wantedHeight = Math.min(
    list.scrollHeight,
    viewportHeight * 0.55,
    320,
  );
  const openUp = spaceBelow < wantedHeight && spaceAbove > spaceBelow;
  const available = openUp ? spaceAbove : spaceBelow;

  menu.classList.toggle("open-up", openUp);
  list.style.setProperty(
    "--item-menu-max-height",
    `${Math.max(80, Math.floor(available - 8))}px`,
  );
}
function closeItemMenus(except = null) {
  document.querySelectorAll(".item-menu.open").forEach((menu) => {
    if (except && menu === except) return;
    menu.classList.remove("open");
    menu.classList.remove("open-up");
    menu
      .querySelector(".item-menu-list")
      ?.style.removeProperty("--item-menu-max-height");
    const btn = menu.querySelector(".item-menu-toggle");
    if (btn) btn.setAttribute("aria-expanded", "false");
  });
  updateMobileMenuState();
}
$("newMenuBtn").addEventListener("click", (e) => {
  e.stopPropagation();
  const willOpen = !newDropdown.classList.contains("open");
  closeDropdowns();
  if (willOpen) newDropdown.classList.add("open");
});
uploadMenuBtn.addEventListener("click", (e) => {
  e.stopPropagation();
  const willOpen = !uploadDropdown.classList.contains("open");
  closeDropdowns();
  if (willOpen) uploadDropdown.classList.add("open");
});
document.addEventListener("click", () => {
  closeDropdowns();
  closeItemMenus();
});
content.addEventListener("click", (e) => {
  const toggle = e.target.closest(".item-menu-toggle");
  if (toggle) {
    e.preventDefault();
    e.stopPropagation();
    const menu = toggle.closest(".item-menu");
    const willOpen = !menu.classList.contains("open");
    closeItemMenus();
    closeDropdowns();
    if (willOpen) {
      menu.classList.add("open");
      toggle.setAttribute("aria-expanded", "true");
      positionItemMenu(menu);
    }
    updateMobileMenuState();
    return;
  }
  const open = e.target.closest("[data-open-action]");
  if (open) {
    e.preventDefault();
    const row = open.closest("tr[data-path]");
    if (!row) return;
    if (open.dataset.openAction === "folder") openFolder(row.dataset.path);
    else if (open.dataset.openAction === "media")
      openMedia(row.dataset.path, open);
    else editFile(row.dataset.path);
    return;
  }
  const action = e.target.closest("[data-item-action]");
  if (action) {
    e.preventDefault();
    e.stopPropagation();
    const row = action.closest("tr[data-path]");
    if (!row) return;
    const path = row.dataset.path,
      name = row.dataset.name,
      type = row.dataset.type;
    switch (action.dataset.itemAction) {
      case "play":
        openMedia(
          path,
          action.closest(".item-menu")?.querySelector(".item-menu-toggle") ||
            action,
        );
        break;
      case "move":
        openMovePicker([path]);
        break;
      case "rename":
        renameItem(path, name);
        break;
      case "copy":
        copyText(
          row.dataset.publicUrl,
          type === "dir" ? "Copied folder URL" : "Copied URL",
        );
        break;
      case "extract":
        extractArchive(path);
        break;
      case "delete":
        deleteItem(path, type);
        break;
    }
    setTimeout(() => closeItemMenus(), 0);
    return;
  }
  const menuAction = e.target.closest(
    ".item-menu-list .action, .item-menu-list button",
  );
  if (menuAction) setTimeout(() => closeItemMenus(), 0);
});
content.addEventListener("change", (e) => {
  if (e.target.id === "selectAll") toggleSelectAll(e.target.checked);
  if (e.target.classList.contains("row-select")) {
    const row = e.target.closest("tr[data-path]");
    if (row) toggleSelected(row.dataset.path, e.target.checked);
  }
});
content.addEventListener("click", (e) => {
  if (e.target.classList.contains("row-select")) e.stopPropagation();
});
breadcrumbs.addEventListener("click", (e) => {
  const button = e.target.closest("[data-nav-path]");
  if (button) openFolder(button.dataset.navPath || "");
});
selectionBarHost.addEventListener("click", (e) => {
  const button = e.target.closest("[data-bulk-action]");
  if (!button) return;
  ({
    download: downloadSelected,
    copy: copySelectedUrls,
    move: moveSelectedPrompt,
    extract: extractSelected,
    delete: deleteSelected,
    clear: clearSelection,
  })[button.dataset.bulkAction]?.();
});
$("newFileBtn").addEventListener("click", () => {
  closeDropdowns();
  createItem("file");
});
$("newFolderBtn").addEventListener("click", () => {
  closeDropdowns();
  createItem("dir");
});
uploadDropdown.addEventListener("click", (e) => {
  const btn = e.target.closest("[data-upload]");
  if (!btn) return;
  closeDropdowns();
  if (btn.dataset.upload === "folder") uploadFolderInput.click();
  else uploadInput.click();
});
uploadInput.addEventListener("change", () => uploadFiles(uploadInput.files));
uploadFolderInput.addEventListener("change", () =>
  uploadFiles(uploadFolderInput.files),
);
uploadCancel.addEventListener("click", handleUploadCancel);
uploadRetry.addEventListener("click", () => {
  const files = [...state.failedUploads];
  uploadRetry.hidden = true;
  uploadFiles(files, state.lastConflictPolicy);
});
let externalDragDepth = 0;
function isExternalFileDrag(e) {
  return (
    e.dataTransfer &&
    e.dataTransfer.types &&
    e.dataTransfer.types.includes("Files") &&
    !e.dataTransfer.types.includes("application/x-file-manager-move")
  );
}
function showUploadOverlay() {
  uploadOverlay.classList.remove("hiding");
  uploadOverlay.classList.add("show");
  uploadOverlay.setAttribute("aria-hidden", "false");
}
function hideUploadOverlay() {
  uploadOverlay.classList.remove("show");
  uploadOverlay.classList.add("hiding");
  uploadOverlay.setAttribute("aria-hidden", "true");
  setTimeout(() => uploadOverlay.classList.remove("hiding"), 190);
}
window.addEventListener("dragenter", (e) => {
  if (!isExternalFileDrag(e)) return;
  externalDragDepth++;
  showUploadOverlay();
});
window.addEventListener("dragover", (e) => {
  if (!isExternalFileDrag(e)) return;
  e.preventDefault();
  e.dataTransfer.dropEffect = "copy";
  showUploadOverlay();
});
window.addEventListener("dragleave", (e) => {
  if (!isExternalFileDrag(e)) return;
  externalDragDepth = Math.max(0, externalDragDepth - 1);
  if (externalDragDepth === 0) hideUploadOverlay();
});
window.addEventListener("drop", async (e) => {
  if (!isExternalFileDrag(e)) return;
  e.preventDefault();
  externalDragDepth = 0;
  hideUploadOverlay();
  const files = await filesFromDropEvent(e);
  uploadFiles(files);
});
$("refreshIndex").addEventListener("click", () => loadFolder(true));
$("saveFile").addEventListener("click", saveFile);
$("downloadEditor").addEventListener("click", downloadEditor);
$("copyFileUrl").addEventListener(
  "click",
  () => state.editing && copyText(filePublicUrl(state.editing), "Copied URL"),
);
$("closeEditor").addEventListener("click", () => closeEditor());
$("formatFile").addEventListener("click", formatEditor);
$("trimLines").addEventListener("click", () => {
  editorText.value = trimTrailing(editorText.value);
  markDirty();
  updateLines();
  toast("Trimmed lines.");
});
$("tabsToSpaces").addEventListener("click", () => {
  editorText.value = editorText.value.replace(/\t/g, "  ");
  markDirty();
  updateLines();
  toast("Converted tabs.");
});
$("wrapToggle").addEventListener("click", () => {
  state.wrap = !state.wrap;
  editorText.classList.toggle("editor-word-wrap", state.wrap);
  $("wrapToggle").textContent = state.wrap ? "Wrap On" : "Wrap Off";
  updateLines();
});
$("themeToggle").addEventListener("click", toggleTheme);
const handleSystemThemeChange = () => {
  if (state.themePreference === "system") applyTheme();
};
if (typeof themeMedia.addEventListener === "function") {
  themeMedia.addEventListener("change", handleSystemThemeChange);
} else {
  themeMedia.addListener(handleSystemThemeChange);
}
editorText.addEventListener("input", () => {
  markDirty();
  updateLines();
});
editorText.addEventListener("scroll", () => {
  lineNumbers.scrollTop = editorText.scrollTop;
});
editorText.addEventListener("keydown", (e) => {
  if (e.key === "Tab") {
    e.preventDefault();
    const s = editorText.selectionStart,
      t = editorText.selectionEnd;
    editorText.value =
      editorText.value.slice(0, s) + "  " + editorText.value.slice(t);
    editorText.selectionStart = editorText.selectionEnd = s + 2;
    markDirty();
    updateLines();
  }
  if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === "s") {
    e.preventDefault();
    saveFile();
  }
});
$("moveClose").addEventListener("click", closeMovePicker);
$("moveCancel").addEventListener("click", closeMovePicker);
moveConfirm.addEventListener("click", confirmMovePicker);
moveBreadcrumbs.addEventListener("click", (e) => {
  const button = e.target.closest("[data-move-path]");
  if (button) browseMoveFolder(button.dataset.movePath || "");
});
moveFolderList.addEventListener("click", (e) => {
  const button = e.target.closest("[data-move-path]");
  if (button) browseMoveFolder(button.dataset.movePath || "");
});
moveModal.addEventListener("click", (e) => {
  if (e.target === moveModal) closeMovePicker();
});
conflictModal.addEventListener("click", (e) => {
  const button = e.target.closest("[data-conflict]");
  if (button) closeConflictModal(button.dataset.conflict);
  else if (e.target === conflictModal) closeConflictModal(null);
});
$("conflictCancel").addEventListener("click", () => closeConflictModal(null));
editorToolsMenuBtn.addEventListener("click", (e) => {
  e.stopPropagation();
  const willOpen = !editorToolsMenuGroup.classList.contains("open");
  editorToolsMenuGroup.classList.toggle("open", willOpen);
  editorToolsMenuBtn.setAttribute("aria-expanded", String(willOpen));
});
editorToolsMenuGroup.addEventListener("click", (e) => {
  const action = e.target.closest("button");
  if (action && action !== editorToolsMenuBtn) closeEditorToolsMenu();
});
editorModal.addEventListener("click", (e) => {
  if (!e.target.closest("#editorToolsMenuGroup")) closeEditorToolsMenu();
  if (e.target === editorModal) closeEditor();
});
$("mediaClose").addEventListener("click", closeMedia);
$("mediaDone").addEventListener("click", closeMedia);
mediaModal.addEventListener("click", (e) => {
  if (e.target === mediaModal) closeMedia();
});
[audioPlayer, videoPlayer].forEach((player) => {
  player.addEventListener("loadedmetadata", handleMediaReady);
  player.addEventListener("canplay", handleMediaReady);
  player.addEventListener("error", handleMediaError);
});
window.addEventListener("keydown", (e) => {
  if (e.key !== "Escape") return;
  if (state.media) closeMedia();
  else if (state.conflictResolver) closeConflictModal(null);
  else if (state.movePicker) closeMovePicker();
  else if (editorToolsMenuGroup.classList.contains("open"))
    closeEditorToolsMenu();
  else if (editorModal.classList.contains("show")) closeEditor();
});
window.addEventListener("popstate", () => {
  state.path = cleanPath(new URL(location.href).searchParams.get("path") || "");
  syncLogoutPath();
  state.selected.clear();
  search.value = "";
  loadFolder();
});
window.addEventListener("beforeunload", (e) => {
  if (state.dirty) {
    e.preventDefault();
    e.returnValue = "";
  }
});
applyTheme();
syncLogoutPath();
document
  .querySelector(".logout-form")
  ?.addEventListener("submit", syncLogoutPath);
updateMobileMenuState();
loadFolder();
