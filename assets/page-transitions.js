(() => {
  const reducedMotion = window.matchMedia("(prefers-reduced-motion: reduce)");
  const exitDuration = 140;
  let navigating = false;
  let restoreTimer = null;

  function enterPage() {
    clearTimeout(restoreTimer);
    navigating = false;
    document.body.classList.remove("page-leaving");
    if (reducedMotion.matches) {
      document.body.classList.remove("page-entering");
      return;
    }
    if (document.body.classList.contains("page-entering")) {
      window.setTimeout(
        () => document.body.classList.remove("page-entering"),
        190,
      );
      return;
    }
    document.body.classList.remove("page-entering");
    requestAnimationFrame(() => {
      document.body.classList.add("page-entering");
      window.setTimeout(
        () => document.body.classList.remove("page-entering"),
        190,
      );
    });
  }

  function leavePage(navigate) {
    if (navigating) return;
    if (reducedMotion.matches) {
      navigate();
      return;
    }
    navigating = true;
    document.body.classList.remove("page-entering");
    document.body.classList.add("page-leaving");
    window.setTimeout(navigate, exitDuration);

    // Restore the current page if a beforeunload prompt cancels navigation.
    restoreTimer = window.setTimeout(() => {
      if (document.visibilityState === "visible") enterPage();
    }, exitDuration + 800);
  }

  document.addEventListener("click", (event) => {
    const link = event.target.closest("a[data-page-transition]");
    if (
      !link ||
      event.defaultPrevented ||
      event.button !== 0 ||
      event.metaKey ||
      event.ctrlKey ||
      event.shiftKey ||
      event.altKey ||
      link.target === "_blank" ||
      link.hasAttribute("download")
    )
      return;

    const destination = new URL(link.href, location.href);
    if (destination.origin !== location.origin) return;
    if (
      destination.pathname === location.pathname &&
      destination.search === location.search &&
      destination.hash !== location.hash
    )
      return;

    event.preventDefault();
    leavePage(() => location.assign(destination.href));
  });

  document.addEventListener("submit", (event) => {
    const form = event.target.closest("form[data-page-transition]");
    if (!form || event.defaultPrevented || navigating) return;
    event.preventDefault();
    leavePage(() => HTMLFormElement.prototype.submit.call(form));
  });

  window.addEventListener("pageshow", (event) => {
    if (event.persisted) enterPage();
  });
  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", enterPage, { once: true });
  } else {
    enterPage();
  }
})();
