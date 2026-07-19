import assert from "node:assert/strict";
import { readFileSync } from "node:fs";

const index = readFileSync(new URL("../index.php", import.meta.url), "utf8");
const app = readFileSync(new URL("../assets/app.js", import.meta.url), "utf8");
const css = readFileSync(new URL("../assets/app.css", import.meta.url), "utf8");

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
  app.includes('uploadCancel.textContent = "Close"') &&
    app.includes('uploadCancel.addEventListener("click", handleUploadCancel)'),
  "the finished upload control closes the result panel",
);
assert.ok(
  app.includes("summarizeUploadErrors(data.errors)"),
  "blocked upload responses surface their server-provided reasons",
);
assert.ok(
  /\[hidden\]\s*\{\s*display:\s*none\s*!important;\s*\}/.test(css),
  "the hidden attribute wins over component display styles",
);

console.log("Frontend security regression checks passed.");
