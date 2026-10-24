import { DatePicker } from "../../shared/DatePicker";

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
  return (
    <DatePicker
      label="Data expirării (opțional)"
      dialogLabel="Alege data expirării"
      emptyLabel="Fără dată de expirare"
      name="expires_on"
      optional
      min="1900-01-01"
      max="2100-12-31"
      value={value}
      disabled={disabled}
      autoFocus={autoFocus}
      onChange={onChange}
    />
  );
}
