(() => {
  const reducedMotion = window.matchMedia("(prefers-reduced-motion: reduce)");
  const exitDuration = 140;
  const appEntranceDuration = 440;
  let navigating = false;
  let restoreTimer = null;
  let entranceTimer = null;

  function entranceCleanupDelay() {
    return document.querySelector("main.login-wrap, main.wrap")
      ? appEntranceDuration + 40
      : 190;
  }

  function isAppPage() {
    return Boolean(document.querySelector("main.login-wrap, main.wrap"));
  }

  function restoreHomepageAnchor() {
    if (!document.querySelector("main.home-shell") || !location.hash) return;

    let targetId = location.hash.slice(1);
    try {
      targetId = decodeURIComponent(targetId);
    } catch {
      // Keep the literal hash when it is not valid encoded text.
    }

    const target = document.getElementById(targetId);
    if (!target) return;

    target.scrollIntoView({
      behavior: reducedMotion.matches ? "auto" : "smooth",
      block: "start",
    });
  }

  function scheduleHomepageAnchorRestore() {
    requestAnimationFrame(() => {
      requestAnimationFrame(restoreHomepageAnchor);
    });
  }

  function finishEntrance() {
    document.body.classList.remove("page-entering", "page-enter-active");
  }

  function enterPage() {
    clearTimeout(restoreTimer);
    clearTimeout(entranceTimer);
    navigating = false;
    document.body.classList.remove("page-leaving");
    if (reducedMotion.matches) {
      finishEntrance();
      return;
    }

    if (isAppPage()) {
      document.body.classList.remove("page-enter-active");
      document.body.classList.add("page-entering");
      requestAnimationFrame(() => {
        requestAnimationFrame(() => {
          document.body.classList.add("page-enter-active");
          entranceTimer = window.setTimeout(
            finishEntrance,
            entranceCleanupDelay(),
          );
        });
      });
      return;
    }

    if (document.body.classList.contains("page-entering")) {
      entranceTimer = window.setTimeout(finishEntrance, entranceCleanupDelay());
      return;
    }
    document.body.classList.remove("page-entering");
    requestAnimationFrame(() => {
      document.body.classList.add("page-entering");
      entranceTimer = window.setTimeout(finishEntrance, entranceCleanupDelay());
    });
  }

  function leavePage(navigate) {
    if (navigating) return;
    if (reducedMotion.matches) {
      navigate();
      return;
    }
    navigating = true;
    clearTimeout(entranceTimer);
    document.body.classList.remove("page-entering", "page-enter-active");
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
    if (event.persisted) {
      enterPage();
      scheduleHomepageAnchorRestore();
    }
  });
  window.addEventListener("load", scheduleHomepageAnchorRestore, { once: true });
  if (document.readyState === "loading") {
    document.addEventListener(
      "DOMContentLoaded",
      () => {
        enterPage();
        scheduleHomepageAnchorRestore();
      },
      { once: true },
    );
  } else {
    enterPage();
    scheduleHomepageAnchorRestore();
  }
})();
