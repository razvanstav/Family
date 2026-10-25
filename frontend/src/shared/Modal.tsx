import { useId, useLayoutEffect, useRef, type ReactNode } from "react";
import { X } from "lucide-react";
import { lockPageScroll } from "./pageScroll";
import { revealModalFocus } from "./modalFocus";

export function Modal({
  title,
  titleIcon,
  titleMeta,
  children,
  onClose,
  wide = false,
  returnFocus,
}: {
  title: string;
  titleIcon?: ReactNode;
  titleMeta?: ReactNode;
  children: ReactNode;
  onClose: () => void;
  wide?: boolean;
  returnFocus?: HTMLElement | null;
}) {
  const ref = useRef<HTMLDialogElement>(null);
  const panelRef = useRef<HTMLDivElement>(null);
  const titleId = useId();
  useLayoutEffect(() => {
    const dialog = ref.current!;
    const previous =
      returnFocus === undefined
        ? (document.activeElement as HTMLElement | null)
        : returnFocus;
    const releaseScroll = lockPageScroll(document, window);
    dialog.showModal();
    const panel = panelRef.current!;
    let frame: number | undefined;
    const reveal = () => {
      if (frame !== undefined) return;
      frame = window.requestAnimationFrame(() => {
        frame = undefined;
        if (Math.abs((window.visualViewport?.scale ?? 1) - 1) < 0.01)
          revealModalFocus(panel, document.activeElement);
      });
    };
    const observer = new ResizeObserver(reveal);
    observer.observe(panel);
    panel.addEventListener("focusin", reveal);
    return () => {
      observer.disconnect();
      panel.removeEventListener("focusin", reveal);
      if (frame !== undefined) window.cancelAnimationFrame(frame);
      // Dismiss the keyboard before unlocking the page. Otherwise WebKit can
      // finish panning the document after its original scroll was restored.
      if (dialog.contains(document.activeElement))
        (document.activeElement as HTMLElement | null)?.blur();
      dialog.close();
      releaseScroll();
      previous?.focus({ preventScroll: true });
    };
  }, []);
  return (
    <dialog
      ref={ref}
      className={`modal ${wide ? "wide" : ""}`}
      onCancel={(event) => {
        event.preventDefault();
        onClose();
      }}
      aria-labelledby={titleId}
    >
      <div className="modal-panel" ref={panelRef}>
        <div className="modal-heading">
          {titleIcon || titleMeta ? (
            <div className="modal-title-with-icon">
              {titleIcon}
              <div className="modal-title-copy">
                <h2 id={titleId}>{title}</h2>
                {titleMeta && (
                  <div className="modal-title-meta">{titleMeta}</div>
                )}
              </div>
            </div>
          ) : (
            <h2 id={titleId}>{title}</h2>
          )}
          <button
            className="icon-button"
            aria-label="Închide"
            onClick={onClose}
          >
            <X size={20} />
          </button>
        </div>
        {children}
      </div>
    </dialog>
  );
}
