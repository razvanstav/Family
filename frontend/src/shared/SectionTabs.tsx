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
  const ref = useRef<HTMLElement>(null);
  useEffect(() => {
    const rail = ref.current;
    if (!rail || !rail.classList.contains("section-tabs--scroll")) return;
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
  }, [value, className]);
  return (
    <nav
      ref={ref}
      className={`section-tabs ${className}`.trim()}
      aria-label={label}
    >
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
    </nav>
  );
}
import { useEffect, useRef } from "react";
