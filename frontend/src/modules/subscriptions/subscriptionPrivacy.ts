export function subscriptionSecretFields(
  action: "keep" | "replace" | "remove",
  fields: { login: string; password: string; pin: string },
) {
  return action === "replace"
    ? {
        secrets_action: action,
        login: fields.login.trim() || null,
        password: fields.password || null,
        pin: fields.pin || null,
      }
    : { secrets_action: action };
}

export function observeCredentialPrivacy(
  document: Pick<
    Document,
    "visibilityState" | "addEventListener" | "removeEventListener"
  >,
  window: Pick<Window, "addEventListener" | "removeEventListener">,
  onHide: () => void,
) {
  const visibility = () => {
    if (document.visibilityState !== "visible") onHide();
  };
  window.addEventListener("blur", onHide);
  window.addEventListener("pagehide", onHide);
  document.addEventListener("visibilitychange", visibility);
  return () => {
    window.removeEventListener("blur", onHide);
    window.removeEventListener("pagehide", onHide);
    document.removeEventListener("visibilitychange", visibility);
  };
}
