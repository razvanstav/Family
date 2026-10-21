export function observeSessionLifecycle(
  document: Pick<
    Document,
    "visibilityState" | "addEventListener" | "removeEventListener"
  >,
  window: Pick<Window, "addEventListener" | "removeEventListener">,
  actions: { suspend: () => void; resume: () => void },
) {
  const visibilityChanged = () => {
    if (document.visibilityState === "visible") actions.resume();
    else actions.suspend();
  };
  const pageRestored = (event: PageTransitionEvent) => {
    if (event.persisted) actions.resume();
  };
  document.addEventListener("visibilitychange", visibilityChanged);
  window.addEventListener("pageshow", pageRestored);
  return () => {
    document.removeEventListener("visibilitychange", visibilityChanged);
    window.removeEventListener("pageshow", pageRestored);
  };
}
