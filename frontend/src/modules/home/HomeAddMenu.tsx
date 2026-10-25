import {
  useEffect,
  useId,
  useLayoutEffect,
  useRef,
  useState,
  type KeyboardEvent,
} from "react";
import {
  Activity,
  BookHeart,
  Plus,
  Receipt,
  UtensilsCrossed,
} from "lucide-react";

export type HomeCreateAction = "activity" | "meal" | "expense" | "memory";

const actions = [
  { value: "activity", label: "Activitate", icon: Activity },
  { value: "meal", label: "Masă", icon: UtensilsCrossed },
  { value: "expense", label: "Cheltuială", icon: Receipt },
  { value: "memory", label: "Amintire", icon: BookHeart },
] as const;

export function HomeAddMenu({
  canAddActivity,
  canAddFamilyContent,
  onCreate,
}: {
  canAddActivity: boolean;
  canAddFamilyContent: boolean;
  onCreate: (action: HomeCreateAction) => void;
}) {
  const id = useId();
  const trigger = useRef<HTMLButtonElement>(null);
  const popup = useRef<HTMLDivElement>(null);
  const initialFocus = useRef(0);
  const [open, setOpen] = useState(false);
  const disabled = !canAddActivity && !canAddFamilyContent;
  const enabled = actions.map(({ value }) =>
    value === "activity" ? canAddActivity : canAddFamilyContent,
  );

  function close(restoreFocus = false) {
    setOpen(false);
    if (restoreFocus) trigger.current?.focus({ preventScroll: true });
  }
  function show(index = 0) {
    if (disabled) return;
    initialFocus.current = enabled[index] ? index : enabled.indexOf(true);
    setOpen(true);
  }
  function focusItem(index: number) {
    popup.current
      ?.querySelectorAll<HTMLButtonElement>("[role=menuitem]")
      [index]?.focus({ preventScroll: true });
  }
  useEffect(() => {
    if (disabled) close();
  }, [disabled]);
  useLayoutEffect(() => {
    if (!open || !popup.current || !trigger.current) return;
    const panel = popup.current;
    let frame: number | undefined;
    function place() {
      frame = undefined;
      const bounds = trigger.current!.getBoundingClientRect();
      const viewport = window.visualViewport;
      const width = Math.min(
        viewport?.width ?? window.innerWidth,
        document.documentElement.clientWidth,
      );
      const height = Math.min(
        window.innerHeight,
        viewport?.height ?? window.innerHeight,
      );
      const left = viewport?.offsetLeft ?? 0;
      const top = viewport?.offsetTop ?? 0;
      const panelWidth = Math.min(224, Math.max(0, width - 16));
      panel.style.width = `${panelWidth}px`;
      panel.style.maxHeight = `${Math.max(0, height - 16)}px`;
      panel.style.left = `${Math.max(left + 8, Math.min(bounds.right - panelWidth, left + width - panelWidth - 8))}px`;
      const panelHeight = panel.getBoundingClientRect().height;
      const below = bounds.bottom + 8;
      const targetTop =
        below + panelHeight <= top + height - 8
          ? below
          : bounds.top - panelHeight - 8;
      panel.style.top = `${Math.max(top + 8, Math.min(targetTop, top + height - panelHeight - 8))}px`;
    }
    function schedule() {
      if (frame === undefined) frame = window.requestAnimationFrame(place);
    }
    function outside(event: Event) {
      const target = event.target as Node;
      if (!panel.contains(target) && !trigger.current?.contains(target))
        close();
    }
    if (typeof panel.showPopover === "function") panel.showPopover();
    place();
    focusItem(initialFocus.current);
    document.addEventListener("pointerdown", outside, true);
    document.addEventListener("focusin", outside);
    window.addEventListener("resize", schedule);
    window.addEventListener("scroll", schedule, true);
    window.visualViewport?.addEventListener("resize", schedule);
    window.visualViewport?.addEventListener("scroll", schedule);
    return () => {
      document.removeEventListener("pointerdown", outside, true);
      document.removeEventListener("focusin", outside);
      window.removeEventListener("resize", schedule);
      window.removeEventListener("scroll", schedule, true);
      window.visualViewport?.removeEventListener("resize", schedule);
      window.visualViewport?.removeEventListener("scroll", schedule);
      if (frame !== undefined) window.cancelAnimationFrame(frame);
      if (
        typeof panel.hidePopover === "function" &&
        panel.matches(":popover-open")
      )
        panel.hidePopover();
    };
  }, [open]);

  function onMenuKeyDown(event: KeyboardEvent<HTMLDivElement>) {
    const items = Array.from(
      popup.current?.querySelectorAll<HTMLButtonElement>(
        "[role=menuitem]:not(:disabled)",
      ) ?? [],
    );
    const active = items.indexOf(document.activeElement as HTMLButtonElement);
    let next: number | undefined;
    if (event.key === "ArrowDown") next = (active + 1) % items.length;
    if (event.key === "ArrowUp")
      next = (active + items.length - 1) % items.length;
    if (event.key === "Home") next = 0;
    if (event.key === "End") next = items.length - 1;
    if (next !== undefined) {
      event.preventDefault();
      items[next]?.focus({ preventScroll: true });
    } else if (event.key === "Escape") {
      event.preventDefault();
      event.stopPropagation();
      close(true);
    } else if (event.key === "Tab") {
      close(true);
    }
  }

  return (
    <>
      <button
        ref={trigger}
        type="button"
        className="button primary"
        aria-label="Adaugă"
        aria-haspopup="menu"
        aria-expanded={open}
        aria-controls={open ? id : undefined}
        disabled={disabled}
        onClick={() => (open ? close(true) : show())}
        onKeyDown={(event) => {
          if (event.key === "ArrowDown" || event.key === "ArrowUp") {
            event.preventDefault();
            show(event.key === "ArrowUp" ? actions.length - 1 : 0);
          } else if (event.key === "Escape" && open) {
            event.preventDefault();
            close(true);
          }
        }}
      >
        <Plus size={18} aria-hidden="true" />
        <span>Adaugă</span>
      </button>
      {open && (
        <div
          ref={popup}
          id={id}
          className="home-add-menu"
          popover="manual"
          role="menu"
          aria-label="Adaugă"
          onKeyDown={onMenuKeyDown}
        >
          {actions.map(({ value, label, icon: Icon }, index) => (
            <button
              key={value}
              type="button"
              role="menuitem"
              tabIndex={-1}
              className="home-add-action"
              disabled={!enabled[index]}
              onClick={() => {
                close(true);
                onCreate(value);
              }}
            >
              <span className={`home-add-icon home-add-icon--${value}`}>
                <Icon size={20} aria-hidden="true" />
              </span>
              <span>{label}</span>
            </button>
          ))}
        </div>
      )}
    </>
  );
}
