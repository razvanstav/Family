export function observePageResume(
  document: Pick<
    Document,
    "visibilityState" | "addEventListener" | "removeEventListener"
  >,
  window: Pick<Window, "addEventListener" | "removeEventListener">,
  onResume: () => void,
) {
  let away = document.visibilityState !== "visible";
  const suspend = () => {
    away = true;
  };
  const resume = () => {
    if (!away || document.visibilityState !== "visible") return;
    away = false;
    onResume();
  };
  const visibilityChanged = () => {
    if (document.visibilityState === "visible") resume();
    else suspend();
  };
  const restored = (event: PageTransitionEvent) => {
    if (event.persisted) resume();
  };
  document.addEventListener("visibilitychange", visibilityChanged);
  window.addEventListener("blur", suspend);
  window.addEventListener("focus", resume);
  window.addEventListener("pageshow", restored);
  window.addEventListener("pagehide", suspend);
  return () => {
    document.removeEventListener("visibilitychange", visibilityChanged);
    window.removeEventListener("blur", suspend);
    window.removeEventListener("focus", resume);
    window.removeEventListener("pageshow", restored);
    window.removeEventListener("pagehide", suspend);
  };
}
