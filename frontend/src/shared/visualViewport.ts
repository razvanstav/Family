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
  root: Pick<HTMLElement, "style" | "clientHeight">,
) {
  const viewport = window.visualViewport;
  if (!viewport) return () => {};
  let frame: number | undefined;
  const properties = ["--visual-viewport-height", "--visual-viewport-top"];
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
    // innerHeight can follow Safari's keyboard pan rather than the layout
    // viewport. Keep the fixed dialog's coordinate system tied to CSS layout.
    const layoutHeight = root.clientHeight || window.innerHeight;
    if (viewport.height <= 0 || layoutHeight <= 0) return;
    const height = Math.min(layoutHeight, viewport.height);
    // A few WebKit versions retain offsetTop after dismissing the keyboard.
    // Bound it to the missing height so a stale offset cannot leave a gap.
    const top = Math.min(
      Math.max(0, viewport.offsetTop),
      Math.max(0, layoutHeight - height),
    );
    [height, top].forEach((value, index) => {
      const next = `${Math.round(value)}px`;
      // Scroll can fire without a geometry change. Do not invalidate the
      // entire document's styles repeatedly while the keyboard is animating.
      if (root.style.getPropertyValue(properties[index]) !== next)
        root.style.setProperty(properties[index], next);
    });
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
