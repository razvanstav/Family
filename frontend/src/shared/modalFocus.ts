// Keep keyboard navigation inside the form's scroll region. Native
// scrollIntoView may also pan the document behind a fixed dialog on iOS.
export function revealModalFocus(panel: HTMLElement, active: Element | null) {
  if (!active || !panel.contains(active)) return;
  if (active.closest("dialog") !== panel.closest("dialog")) return;
  // Date/select popovers position themselves in the top layer. Scrolling the
  // underlying form cannot reveal their controls and would only move its fields.
  if (active.closest("[popover]")) return;
  const bounds = panel.getBoundingClientRect();
  const target = active.getBoundingClientRect();
  const top = bounds.top + 16;
  const bottom = bounds.bottom - 16;
  let adjustment = 0;
  if (target.top < top || target.height > bottom - top)
    adjustment = target.top - top;
  else if (target.bottom > bottom) adjustment = target.bottom - bottom;
  if (adjustment) panel.scrollTop += adjustment;
}
