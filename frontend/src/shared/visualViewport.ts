type ViewportWindow = Pick<
  Window,
  | "visualViewport"
  | "innerHeight"
  | "requestAnimationFrame"
  | "cancelAnimationFrame"
  | "addEventListener"
  | "removeEventListener"
>;

// One observer for the application. The keyboard changes the visible viewport
// on iOS without resizing CSS viewport units or the layout viewport.
export function observeVisualViewport(
  window: ViewportWindow,
  root: Pick<HTMLElement, "style">,
) {
  const viewport = window.visualViewport;
  if (!viewport) return () => {};
  let frame: number | undefined;
  const properties = [
    "--visual-viewport-height",
    "--visual-viewport-top",
    "--visual-viewport-bottom",
  ];
  const previous = properties.map((property) => [
    root.style.getPropertyValue(property),
    root.style.getPropertyPriority(property),
  ]);
  const update = () => {
    frame = undefined;
    // Preserve normal pinch zoom; it is not keyboard space to compensate for.
    if (Math.abs(viewport.scale - 1) > 0.01) {
      properties.forEach((property) => root.style.removeProperty(property));
      return;
    }
    const height = Math.min(window.innerHeight, Math.max(0, viewport.height));
    // A few WebKit versions retain offsetTop after dismissing the keyboard.
    // Bound it to the missing height so a stale offset cannot leave a gap.
    const top = Math.min(
      Math.max(0, viewport.offsetTop),
      Math.max(0, window.innerHeight - height),
    );
    const bottom = Math.max(0, window.innerHeight - height - top);
    root.style.setProperty(properties[0], `${height}px`);
    root.style.setProperty(properties[1], `${top}px`);
    root.style.setProperty(properties[2], `${bottom}px`);
  };
  const schedule = () => {
    if (frame === undefined) frame = window.requestAnimationFrame(update);
  };
  update();
  viewport.addEventListener("resize", schedule);
  viewport.addEventListener("scroll", schedule);
  window.addEventListener("resize", schedule);
  window.addEventListener("pageshow", schedule);
  return () => {
    viewport.removeEventListener("resize", schedule);
    viewport.removeEventListener("scroll", schedule);
    window.removeEventListener("resize", schedule);
    window.removeEventListener("pageshow", schedule);
    if (frame !== undefined) window.cancelAnimationFrame(frame);
    properties.forEach((property, index) => {
      const [value, priority] = previous[index];
      if (value) root.style.setProperty(property, value, priority);
      else root.style.removeProperty(property);
    });
  };
}
