import assert from "node:assert/strict";
import { readFileSync } from "node:fs";

const index = readFileSync(new URL("../index.php", import.meta.url), "utf8");
const app = readFileSync(new URL("../assets/app.js", import.meta.url), "utf8");
const css = readFileSync(new URL("../assets/app.css", import.meta.url), "utf8");
const favicon = readFileSync(
  new URL("../assets/favicon.svg", import.meta.url),
  "utf8",
);

assert.ok(
  !/on(?:click|change|input|submit|load|error)\s*=/.test(index + app),
  "inline event handlers must not return",
);
assert.ok(
  /"X-CSRF-Token"\s*:\s*csrfToken/.test(app),
  "JSON API requests include the CSRF header",
);
assert.ok(
  /setRequestHeader\("X-CSRF-Token",\s*csrfToken\)/.test(app),
  "upload requests include the CSRF header",
);
assert.ok(
  app.includes("data-item-action"),
  "row actions use delegated data attributes",
);
assert.ok(
  index.includes('name="path" value="<?= htmlspecialchars($requestedPath'),
  "login form preserves a normalized path",
);
assert.ok(
  index.includes('id="logoutPath"') &&
    app.includes("input.value = state.path") &&
    app.includes('addEventListener("submit", syncLogoutPath)'),
  "logout submits the folder currently open in the client-side manager",
);
assert.ok(
  index.includes('assets/app.css?v=<?= $cssVersion ?>') &&
    index.includes('assets/app.js?v=<?= $jsVersion ?>'),
  "frontend asset URLs change after deployment so stale upload code is not reused",
);
assert.ok(
  index.includes('rel="icon" type="image/svg+xml"') &&
    index.includes('assets/favicon.svg?v=<?= $faviconVersion ?>'),
  "the SVG favicon is linked with cache busting",
);
assert.ok(
  /<rect[^>]*rx="15"/.test(favicon) &&
    /font-family="ui-sans-serif, system-ui/.test(favicon) &&
    />FM<\/text>/.test(favicon.replace(/\s+/g, "")),
  "the favicon is a rounded FM mark using the manager's font stack",
);
assert.ok(
  index.includes('id="themeToggle"') && index.includes('id="themeToggleIcon"'),
  "the theme control is visible and accessible in the manager header",
);
assert.ok(
  app.includes('matchMedia("(prefers-color-scheme: light)")') &&
    app.includes('themeMedia.addEventListener("change"') &&
    app.includes('state.themePreference === "system"'),
  "system theme changes are observed while the System preference is active",
);
assert.ok(
  app.includes('file-manager-theme-preference') &&
    app.includes('localStorage.removeItem("file-manager-theme")'),
  "explicit theme choices use a new key and the forced-dark legacy value is migrated",
);
assert.ok(
  app.includes('uploadCancel.textContent = "Close"') &&
    app.includes('uploadCancel.addEventListener("click", handleUploadCancel)'),
  "the finished upload control closes the result panel",
);
assert.ok(
  app.includes("summarizeUploadErrors(data.errors)"),
  "blocked upload responses surface their server-provided reasons",
);
assert.ok(
  app.includes('const policy = presetPolicy || "skip"') &&
    app.includes("presetPolicy === null") &&
    app.includes("conflictFiles.length"),
  "uploads ask for a conflict policy only after the server reports a collision",
);
assert.ok(
  !app.includes("Choose how existing items should be handled while uploading"),
  "new uploads are not preemptively described as existing files",
);
assert.ok(
  /\[hidden\]\s*\{\s*display:\s*none\s*!important;\s*\}/.test(css),
  "the hidden attribute wins over component display styles",
);
assert.ok(
  /body\.light input\[type="checkbox"\]:checked\s*::after\s*\{[^}]*background:\s*transparent/s.test(
    css,
  ) &&
    /body\.light input\[type="checkbox"\]:checked,[\s\S]*?background-color:\s*var\(--good\)/.test(
      css,
    ),
  "light-mode checkboxes retain a clear check shape on the selected background",
);
assert.ok(
  /body\.light\s*\{[^}]*--name-hover:\s*#087a55/s.test(css) &&
    /\.name\.file-name:hover span:last-child\s*\{[^}]*color:\s*var\(--name-hover\)/s.test(
      css,
    ),
  "light-mode filename hover text uses a darker accessible green",
);

console.log("Frontend security regression checks passed.");
