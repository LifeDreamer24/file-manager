(() => {
  "use strict";

  const finePointer = window.matchMedia("(hover: hover) and (pointer: fine)");
  const reducedMotion = window.matchMedia("(prefers-reduced-motion: reduce)");

  if (!finePointer.matches || reducedMotion.matches) return;

  const glow = document.createElement("div");
  glow.className = "ambient-cursor-glow";
  glow.setAttribute("aria-hidden", "true");
  document.body.append(glow);

  let targetX = window.innerWidth / 2;
  let targetY = window.innerHeight / 3;
  let currentX = targetX;
  let currentY = targetY;
  let frame = 0;
  let visible = false;

  const render = () => {
    currentX += (targetX - currentX) * 0.16;
    currentY += (targetY - currentY) * 0.16;
    glow.style.transform = `translate3d(${currentX}px, ${currentY}px, 0) translate(-50%, -50%)`;

    if (
      Math.abs(targetX - currentX) > 0.1 ||
      Math.abs(targetY - currentY) > 0.1
    ) {
      frame = requestAnimationFrame(render);
    } else {
      frame = 0;
    }
  };

  const queueRender = () => {
    if (!frame) frame = requestAnimationFrame(render);
  };

  window.addEventListener(
    "pointermove",
    (event) => {
      if (event.pointerType && event.pointerType !== "mouse") return;
      targetX = event.clientX;
      targetY = event.clientY;
      if (!visible) {
        currentX = targetX;
        currentY = targetY;
        visible = true;
        glow.classList.add("is-visible");
      }
      queueRender();
    },
    { passive: true },
  );

  document.addEventListener(
    "pointerover",
    (event) => {
      glow.classList.toggle(
        "is-interactive",
        Boolean(event.target.closest("button, a, input, select, textarea, .name")),
      );
    },
    { passive: true },
  );

  document.documentElement.addEventListener("mouseleave", () => {
    visible = false;
    glow.classList.remove("is-visible", "is-interactive");
  });

  document.addEventListener("pointerdown", (event) => {
    if (
      event.button !== 0 ||
      (event.pointerType && event.pointerType !== "mouse")
    ) {
      return;
    }

    const ripple = document.createElement("span");
    ripple.className = "ambient-click-ripple";
    ripple.setAttribute("aria-hidden", "true");
    ripple.style.left = `${event.clientX}px`;
    ripple.style.top = `${event.clientY}px`;
    document.body.append(ripple);
    ripple.addEventListener("animationend", () => ripple.remove(), {
      once: true,
    });
  });
})();
