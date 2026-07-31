(() => {
  const storage = document.getElementById("storageUsage");
  const detail = document.getElementById("storageUsageDetail");
  const track = document.getElementById("storageUsageTrack");
  const bar = document.getElementById("storageUsageBar");
  const content = document.getElementById("content");
  const refreshButton = document.getElementById("refreshIndex");

  if (!storage || !detail || !track || !bar) return;

  let request = null;
  let refreshTimer = 0;

  async function refreshStorageUsage() {
    if (request) request.abort();
    const controller = new AbortController();
    request = controller;

    try {
      const response = await fetch("disk-usage.php", {
        credentials: "same-origin",
        cache: "no-store",
        signal: controller.signal,
      });
      const data = await response.json();

      if (!response.ok || !data.ok) {
        throw new Error(data.error || "Disk usage is unavailable.");
      }

      const percentage = Math.max(0, Math.min(100, Number(data.percentage) || 0));
      detail.textContent = `${data.uploaded_label} in uploaded files · ${percentage}% of ${data.total_label}`;
      bar.style.width = `${percentage}%`;
      track.setAttribute("aria-valuenow", String(percentage));
      track.setAttribute(
        "aria-label",
        `Uploaded files use ${data.uploaded_label}, ${percentage}% of ${data.total_label}`,
      );

      storage.classList.toggle("warning", percentage >= 85 && percentage < 95);
      storage.classList.toggle("critical", percentage >= 95);
    } catch (error) {
      if (error.name === "AbortError") return;
      detail.textContent = "Uploaded-file usage unavailable";
      bar.style.width = "0%";
      track.setAttribute("aria-valuenow", "0");
      storage.classList.remove("warning", "critical");
    } finally {
      if (request === controller) request = null;
    }
  }

  function scheduleRefresh(delay = 350) {
    window.clearTimeout(refreshTimer);
    refreshTimer = window.setTimeout(refreshStorageUsage, delay);
  }

  refreshButton?.addEventListener("click", () => scheduleRefresh(500));

  if (content) {
    new MutationObserver(() => scheduleRefresh()).observe(content, {
      childList: true,
      subtree: false,
    });
  }

  document.addEventListener("visibilitychange", () => {
    if (!document.hidden) refreshStorageUsage();
  });

  refreshStorageUsage();
  window.setInterval(() => {
    if (!document.hidden) refreshStorageUsage();
  }, 60000);
})();
