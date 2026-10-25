import {
  useEffect,
  useId,
  useLayoutEffect,
  useRef,
  useState,
  type ButtonHTMLAttributes,
  type KeyboardEvent,
} from "react";
import { Check, ChevronDown } from "lucide-react";
import {
  findSelectOption,
  getEnabledOptionIndex,
  type SelectOption,
} from "./selectMenuOptions";
import "./selectMenu.css";

function scrollActiveOption(panel: HTMLElement) {
  const option = panel.querySelector<HTMLElement>("[data-active]");
  if (!option) return;
  const top = option.offsetTop;
  const bottom = top + option.offsetHeight;
  if (top < panel.scrollTop + 6) panel.scrollTop = top - 6;
  else if (bottom > panel.scrollTop + panel.clientHeight - 6)
    panel.scrollTop = bottom - panel.clientHeight + 6;
}

export function SelectMenu({
  label,
  value,
  options,
  onChange,
  disabled = false,
  triggerProps,
}: {
  label?: string;
  value: string;
  options: readonly SelectOption[];
  onChange: (value: string) => void;
  disabled?: boolean;
  triggerProps?: Pick<
    ButtonHTMLAttributes<HTMLButtonElement>,
    | "id"
    | "title"
    | "autoFocus"
    | "tabIndex"
    | "aria-labelledby"
    | "aria-describedby"
    | "aria-required"
    | "aria-invalid"
  >;
}) {
  const id = useId();
  const container = useRef<HTMLDivElement>(null);
  const trigger = useRef<HTMLButtonElement>(null);
  const popup = useRef<HTMLDivElement>(null);
  const [open, setOpen] = useState(false);
  const [active, setActive] = useState(-1);
  const search = useRef({ query: "", time: 0 });
  const selected = options.findIndex((option) => option.value === value);
  const initial =
    selected >= 0 && !options[selected].disabled
      ? selected
      : getEnabledOptionIndex(options, -1, "first");
  const unavailable = disabled || initial < 0;

  function close(restoreFocus = false) {
    setOpen(false);
    search.current = { query: "", time: 0 };
    if (restoreFocus) trigger.current?.focus({ preventScroll: true });
  }
  function show(index = initial) {
    if (unavailable) return;
    setActive(index);
    setOpen(true);
  }
  function choose(index: number) {
    const option = options[index];
    if (unavailable || !option || option.disabled) return;
    close(true);
    if (option.value !== value) onChange(option.value);
  }
  useEffect(() => {
    if (unavailable) close();
  }, [unavailable]);
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
      const top = viewport
        ? Math.abs(viewport.scale - 1) > 0.01
          ? viewport.offsetTop
          : Math.min(
              Math.max(0, viewport.offsetTop),
              Math.max(0, window.innerHeight - height),
            )
        : 0;
      const menuWidth = Math.max(
        0,
        Math.min(Math.max(bounds.width, 144), width - 16),
      );
      panel.style.width = `${menuWidth}px`;
      const desiredHeight = Math.min(
        panel.scrollHeight + 2,
        308,
        Math.max(0, height - 16),
      );
      const below = Math.max(0, top + height - 8 - bounds.bottom - 6);
      const above = Math.max(0, bounds.top - 6 - top - 8);
      const after = below >= Math.min(desiredHeight, 176) || below >= above;
      const menuHeight = Math.min(desiredHeight, after ? below : above);
      panel.style.maxHeight = `${menuHeight}px`;
      panel.style.left = `${Math.max(left + 8, Math.min(bounds.left, left + width - menuWidth - 8))}px`;
      panel.style.top = `${Math.max(top + 8, Math.min(after ? bounds.bottom + 6 : bounds.top - menuHeight - 6, top + height - menuHeight - 8))}px`;
      scrollActiveOption(panel);
    }
    const schedule = (event: Event) => {
      if (event.target === panel || frame !== undefined) return;
      frame = window.requestAnimationFrame(place);
    };
    const outside = (event: PointerEvent) => {
      if (!container.current?.contains(event.target as Node)) close();
    };
    if (typeof panel.showPopover === "function") panel.showPopover();
    place();
    document.addEventListener("pointerdown", outside, true);
    window.addEventListener("resize", schedule);
    window.addEventListener("scroll", schedule, true);
    window.visualViewport?.addEventListener("resize", schedule);
    window.visualViewport?.addEventListener("scroll", schedule);
    return () => {
      document.removeEventListener("pointerdown", outside, true);
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
  useLayoutEffect(() => {
    if (!open || !popup.current) return;
    scrollActiveOption(popup.current);
  }, [open, active]);

  function typeahead(character: string) {
    const now = performance.now();
    const previous =
      now - search.current.time < 700 ? search.current.query : "";
    const query = previous + character;
    const repeated = Array.from(query.toLocaleLowerCase("ro")).every(
      (letter) => letter === query[0].toLocaleLowerCase("ro"),
    );
    const from = open ? active : initial;
    const found = findSelectOption(
      options,
      repeated ? character : query,
      repeated || !previous ? from + 1 : from,
    );
    search.current = { query, time: now };
    if (found >= 0) show(found);
    else if (!open) show();
  }
  function onKeyDown(event: KeyboardEvent<HTMLButtonElement>) {
    if (unavailable) return;
    if (event.key === "Tab") {
      if (open) close();
      return;
    }
    if (event.key === "Escape") {
      if (open) {
        event.preventDefault();
        event.stopPropagation();
        close(true);
      }
      return;
    }
    const direction = (
      {
        ArrowDown: "next",
        ArrowUp: "previous",
        Home: "first",
        End: "last",
      } as const
    )[event.key as "ArrowDown" | "ArrowUp" | "Home" | "End"];
    if (direction) {
      event.preventDefault();
      event.stopPropagation();
      search.current = { query: "", time: 0 };
      show(
        !open && (direction === "next" || direction === "previous")
          ? initial
          : getEnabledOptionIndex(options, active, direction),
      );
    } else if (event.key === "Enter" || event.key === " ") {
      event.preventDefault();
      event.stopPropagation();
      if (open) choose(active);
      else show();
    } else if (
      event.key.length === 1 &&
      !event.altKey &&
      !event.ctrlKey &&
      !event.metaKey
    ) {
      event.preventDefault();
      event.stopPropagation();
      typeahead(event.key);
    }
  }
  return (
    <div className="select-menu" ref={container}>
      <button
        {...triggerProps}
        ref={trigger}
        type="button"
        className="select-menu-trigger"
        role="combobox"
        aria-label={label}
        aria-expanded={open}
        aria-haspopup="listbox"
        aria-controls={open ? id : undefined}
        aria-activedescendant={
          open && active >= 0 ? `${id}-option-${active}` : undefined
        }
        disabled={unavailable}
        onClick={() => (open ? close() : show())}
        onKeyDown={onKeyDown}
      >
        <span>{options[selected]?.label ?? value}</span>
        <ChevronDown size={16} aria-hidden="true" />
      </button>
      {open && (
        <div
          ref={popup}
          id={id}
          popover="manual"
          role="listbox"
          aria-label={label}
          aria-labelledby={label ? undefined : triggerProps?.id}
          className="select-menu-options"
        >
          {options.map((option, index) => (
            <div
              key={option.value}
              id={`${id}-option-${index}`}
              role="option"
              className="select-menu-option"
              aria-selected={option.value === value}
              aria-disabled={option.disabled || undefined}
              data-active={index === active || undefined}
              onPointerDown={(event) => {
                if (event.pointerType !== "touch") event.preventDefault();
              }}
              onPointerMove={(event) => {
                if (event.pointerType === "mouse" && !option.disabled)
                  setActive(index);
              }}
              onClick={(event) => {
                event.preventDefault();
                choose(index);
              }}
            >
              <span>{option.label}</span>
              {option.value === value && <Check size={17} aria-hidden="true" />}
            </div>
          ))}
        </div>
      )}
    </div>
  );
}
