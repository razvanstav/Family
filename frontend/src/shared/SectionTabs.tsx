import { useEffect, useId, useLayoutEffect, useRef, useState } from "react";
import { ChevronLeft, ChevronRight } from "lucide-react";

type SectionOption<Value extends string> = {
  value: Value;
  label: string;
};

export function SectionTabs<Value extends string>({
  label,
  value,
  options,
  onChange,
  className = "",
}: {
  label: string;
  value: Value;
  options: readonly SectionOption<Value>[];
  onChange: (value: Value) => void;
  className?: string;
}) {
  const containerRef = useRef<HTMLElement>(null);
  const railRef = useRef<HTMLDivElement>(null);
  const railId = useId();
  const scrollable = className.split(/\s+/).includes("section-tabs--scroll");
  const [scroll, setScroll] = useState({
    overflow: false,
    before: false,
    after: false,
  });
  const [selection, setSelection] = useState<{
    left: number;
    top: number;
    width: number;
    height: number;
  } | null>(null);
  useLayoutEffect(() => {
    const rail = railRef.current;
    const active = rail?.querySelector<HTMLButtonElement>(
      '[aria-pressed="true"]',
    );
    if (!rail || !active) {
      setSelection(null);
      return;
    }
    const update = () => {
      if (!active.offsetWidth) {
        setSelection(null);
        return;
      }
      const next = {
        left: active.offsetLeft,
        top: active.offsetTop,
        width: active.offsetWidth,
        height: active.offsetHeight,
      };
      setSelection((current) =>
        current &&
        current.left === next.left &&
        current.top === next.top &&
        current.width === next.width &&
        current.height === next.height
          ? current
          : next,
      );
    };
    update();
    const observer = new ResizeObserver(update);
    observer.observe(rail);
    observer.observe(active);
    return () => observer.disconnect();
  }, [value, options]);
  useLayoutEffect(() => {
    const container = containerRef.current;
    const rail = railRef.current;
    if (!scrollable || !container || !rail) return;
    const update = () => {
      const availableWidth = container.clientWidth - 8;
      const overflow = rail.scrollWidth > availableWidth + 1;
      const before = rail.scrollLeft > 1;
      const after = rail.scrollLeft + rail.clientWidth < rail.scrollWidth - 1;
      setScroll((current) =>
        current.overflow === overflow &&
        current.before === before &&
        current.after === after
          ? current
          : { overflow, before, after },
      );
    };
    update();
    const observer = new ResizeObserver(update);
    observer.observe(container);
    observer.observe(rail);
    rail.addEventListener("scroll", update, { passive: true });
    return () => {
      observer.disconnect();
      rail.removeEventListener("scroll", update);
    };
  }, [scrollable, options]);
  useEffect(() => {
    const rail = railRef.current;
    if (!rail || !scrollable) return;
    const active = rail.querySelector<HTMLButtonElement>(
      '[aria-pressed="true"]',
    );
    if (!active) return;
    const bounds = rail.getBoundingClientRect();
    const selected = active.getBoundingClientRect();
    if (selected.left < bounds.left + 4)
      rail.scrollLeft += selected.left - bounds.left - 4;
    else if (selected.right > bounds.right - 4)
      rail.scrollLeft += selected.right - bounds.right + 4;
  }, [value, scrollable, scroll.overflow]);
  function scrollSections(direction: "before" | "after") {
    const rail = railRef.current;
    if (!rail) return;
    const bounds = rail.getBoundingClientRect();
    const buttons = Array.from(rail.querySelectorAll("button"));
    const next =
      direction === "after"
        ? buttons.find(
            (button) => button.getBoundingClientRect().right > bounds.right - 3,
          )
        : buttons
            .reverse()
            .find(
              (button) => button.getBoundingClientRect().left < bounds.left + 3,
            );
    if (!next) return;
    const target = next.getBoundingClientRect();
    rail.scrollBy({
      left:
        direction === "after"
          ? target.right - bounds.right + 4
          : target.left - bounds.left - 4,
      behavior: window.matchMedia("(prefers-reduced-motion: reduce)").matches
        ? "instant"
        : "smooth",
    });
  }
  return (
    <nav
      ref={containerRef}
      className={`section-tabs ${className}`.trim()}
      aria-label={label}
    >
      {scrollable && scroll.overflow && (
        <button
          className="section-tabs-arrow"
          type="button"
          aria-label="Secțiunile anterioare"
          aria-controls={railId}
          disabled={!scroll.before}
          onClick={() => scrollSections("before")}
        >
          <ChevronLeft size={20} aria-hidden="true" />
        </button>
      )}
      <div
        className={`section-tabs-rail${selection ? " section-tabs-rail--selected" : ""}`}
        ref={railRef}
        id={railId}
      >
        {selection && (
          <span
            className="section-tabs-selection"
            aria-hidden="true"
            style={{
              width: selection.width,
              height: selection.height,
              transform: `translate(${selection.left}px, ${selection.top}px)`,
            }}
          />
        )}
        {options.map((option) => (
          <button
            key={option.value}
            type="button"
            aria-pressed={value === option.value}
            onClick={() => {
              if (value !== option.value) onChange(option.value);
            }}
          >
            {option.label}
          </button>
        ))}
      </div>
      {scrollable && scroll.overflow && (
        <button
          className="section-tabs-arrow"
          type="button"
          aria-label="Secțiunile următoare"
          aria-controls={railId}
          disabled={!scroll.after}
          onClick={() => scrollSections("after")}
        >
          <ChevronRight size={20} aria-hidden="true" />
        </button>
      )}
    </nav>
  );
}
