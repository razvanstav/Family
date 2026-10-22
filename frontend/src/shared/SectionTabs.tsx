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
  return (
    <nav className={`section-tabs ${className}`.trim()} aria-label={label}>
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
