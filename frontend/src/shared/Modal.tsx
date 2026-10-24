import { useEffect, useRef, type ReactNode } from "react";
import { X } from "lucide-react";
import { lockPageScroll } from "./pageScroll";

export function Modal({
  title,
  children,
  onClose,
  wide = false,
  returnFocus,
}: {
  title: string;
  children: ReactNode;
  onClose: () => void;
  wide?: boolean;
  returnFocus?: HTMLElement | null;
}) {
  const ref = useRef<HTMLDialogElement>(null);
  useEffect(() => {
    const dialog = ref.current!;
    const previous =
      returnFocus === undefined
        ? (document.activeElement as HTMLElement | null)
        : returnFocus;
    const releaseScroll = lockPageScroll(document, window);
    dialog.showModal();
    return () => {
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
      aria-labelledby="modal-title"
    >
      <div className="modal-heading">
        <h2 id="modal-title">{title}</h2>
        <button className="icon-button" aria-label="Închide" onClick={onClose}>
          <X size={20} />
        </button>
      </div>
      {children}
    </dialog>
  );
}
