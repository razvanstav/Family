import { useId } from "react";
import { X } from "lucide-react";

export function ExpirationField({
  value,
  onChange,
  disabled,
  autoFocus = false,
}: {
  value: string;
  onChange: (value: string) => void;
  disabled: boolean;
  autoFocus?: boolean;
}) {
  const id = useId();
  return (
    <div className="field">
      <label htmlFor={id}>Data expirării (opțional)</label>
      <input
        id={id}
        type="date"
        min="1900-01-01"
        max="2100-12-31"
        value={value}
        disabled={disabled}
        autoFocus={autoFocus}
        onChange={(event) => onChange(event.target.value)}
      />
      {value && (
        <button
          className="text-button document-clear-expiration"
          type="button"
          disabled={disabled}
          onClick={() => onChange("")}
        >
          <X size={16} aria-hidden="true" /> Șterge data expirării
        </button>
      )}
    </div>
  );
}
