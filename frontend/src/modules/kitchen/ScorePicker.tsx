import { SelectField } from "../../shared/SelectField";
export function ScorePicker({
  label = "Notă (1–10)",
  value,
  onChange,
  disabled = false,
  required = false,
}: {
  label?: string;
  value: number | null;
  onChange: (value: number | null) => void;
  disabled?: boolean;
  required?: boolean;
}) {
  return (
    <label className="field">
      {label}
      <SelectField
        value={value ?? ""}
        onChange={(event) =>
          onChange(event.target.value ? Number(event.target.value) : null)
        }
        disabled={disabled}
        required={required}
      >
        <option value="">Fără notă</option>
        {Array.from({ length: 10 }, (_, index) => index + 1).map((score) => (
          <option key={score} value={score}>
            {score}
          </option>
        ))}
      </SelectField>
    </label>
  );
}
