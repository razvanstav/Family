import {
  useId,
  useLayoutEffect,
  useRef,
  useState,
  type SelectHTMLAttributes,
} from "react";
import { SelectMenu } from "./SelectMenu";
import type { SelectOption } from "./selectMenuOptions";

type SelectFieldProps = Omit<
  SelectHTMLAttributes<HTMLSelectElement>,
  "multiple" | "size"
>;

// Keep the native control as the source of form values, option selection and
// constraint validation. Only the visible interaction uses the shared menu.
export function SelectField({
  children,
  className,
  style,
  id,
  autoFocus,
  tabIndex,
  title,
  onChange,
  onInvalid,
  ...props
}: SelectFieldProps) {
  const generatedId = useId();
  const triggerId = id ?? generatedId;
  const container = useRef<HTMLSpanElement>(null);
  const native = useRef<HTMLSelectElement>(null);
  const [selection, setSelection] = useState({
    value: "",
    label: props["aria-label"],
    options: [] as SelectOption[],
  });
  const [invalid, setInvalid] = useState(false);

  function synchronize() {
    const control = native.current;
    if (!control) return;
    const options = Array.from(control.options, (option) => ({
      value: option.value,
      label: option.label,
      disabled:
        option.disabled ||
        (option.parentElement instanceof HTMLOptGroupElement &&
          option.parentElement.disabled),
    }));
    const trigger =
      container.current?.querySelector<HTMLButtonElement>("[role=combobox]");
    const label =
      props["aria-label"] ??
      Array.from(trigger?.labels ?? [], (element) =>
        Array.from(element.childNodes)
          .filter((node) => node !== container.current)
          .map((node) => node.textContent ?? "")
          .join(" ")
          .replace(/\s+/g, " ")
          .trim(),
      ).join(" ");
    setSelection((previous) =>
      previous.value === control.value &&
      previous.label === label &&
      previous.options.length === options.length &&
      previous.options.every(
        (option, index) =>
          option.value === options[index].value &&
          option.label === options[index].label &&
          option.disabled === options[index].disabled,
      )
        ? previous
        : { value: control.value, label, options },
    );
    if (control.validity.valid) setInvalid(false);
  }

  useLayoutEffect(synchronize, [children, props.value, props.defaultValue]);

  return (
    <span
      ref={container}
      className={`select-field${className ? ` ${className}` : ""}`}
      style={style}
    >
      <SelectMenu
        label={selection.label || undefined}
        value={selection.value}
        options={selection.options}
        disabled={props.disabled}
        triggerProps={{
          id: triggerId,
          title,
          autoFocus,
          tabIndex,
          "aria-labelledby": props["aria-labelledby"],
          "aria-describedby":
            [props["aria-describedby"], invalid && `${triggerId}-error`]
              .filter(Boolean)
              .join(" ") || undefined,
          "aria-required": props["aria-required"] ?? props.required,
          "aria-invalid": props["aria-invalid"] ?? (invalid || undefined),
        }}
        onChange={(value) => {
          const control = native.current;
          if (!control || control.matches(":disabled")) return;
          control.value = value;
          control.dispatchEvent(new Event("change", { bubbles: true }));
          synchronize();
        }}
      />
      <select
        {...props}
        ref={native}
        hidden
        aria-hidden="true"
        tabIndex={-1}
        onChange={(event) => {
          onChange?.(event);
          synchronize();
        }}
        onInvalid={(event) => {
          event.preventDefault();
          setInvalid(true);
          container.current
            ?.querySelector<HTMLButtonElement>("[role=combobox]")
            ?.focus();
          onInvalid?.(event);
        }}
      >
        {children}
      </select>
      {invalid && (
        <span
          id={`${triggerId}-error`}
          className="select-field-error"
          role="alert"
        >
          Alege o opțiune.
        </span>
      )}
    </span>
  );
}
