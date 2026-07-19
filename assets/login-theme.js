(() => {
  "use strict";

  const storageKey = "file-manager-theme-preference";
  const themeMedia = window.matchMedia("(prefers-color-scheme: light)");
  const storedTheme = localStorage.getItem(storageKey);
  let themePreference = ["system", "light", "dark"].includes(storedTheme)
    ? storedTheme
    : "system";

  // Older releases used this key and could force dark mode unintentionally.
  localStorage.removeItem("file-manager-theme");

  const labels = {
    system: { icon: "◐", name: "System", next: "Light" },
    light: { icon: "☀", name: "Light", next: "Dark" },
    dark: { icon: "☾", name: "Dark", next: "System" },
  };

  function updateButton() {
    const button = document.getElementById("themeToggle");
    const icon = document.getElementById("themeToggleIcon");
    if (!button || !icon) return;

    const current = labels[themePreference];
    icon.textContent = current.icon;
    button.title = `Theme: ${current.name}. Click for ${current.next}.`;
    button.setAttribute(
      "aria-label",
      `Theme: ${current.name}. Switch to ${current.next}.`,
    );
  }

  function applyTheme() {
    const resolvedTheme =
      themePreference === "system"
        ? themeMedia.matches
          ? "light"
          : "dark"
        : themePreference;

    document.body.classList.toggle("light", resolvedTheme === "light");
    document.documentElement.style.colorScheme = resolvedTheme;
    localStorage.setItem(storageKey, themePreference);
    updateButton();
  }

  function toggleTheme() {
    themePreference =
      { system: "light", light: "dark", dark: "system" }[themePreference] ||
      "system";
    applyTheme();
  }

  function bindToggle() {
    const button = document.getElementById("themeToggle");
    if (!button) return;
    button.addEventListener("click", toggleTheme);
    updateButton();
  }

  const handleSystemThemeChange = () => {
    if (themePreference === "system") applyTheme();
  };

  if (typeof themeMedia.addEventListener === "function") {
    themeMedia.addEventListener("change", handleSystemThemeChange);
  } else {
    themeMedia.addListener(handleSystemThemeChange);
  }

  applyTheme();

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", bindToggle, { once: true });
  } else {
    bindToggle();
  }
})();
