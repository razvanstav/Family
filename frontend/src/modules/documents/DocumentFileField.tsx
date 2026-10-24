import { useRef } from "react";
import { FileText, UploadCloud } from "lucide-react";

export function DocumentFileField({
  file,
  onChange,
  onError,
  disabled,
  optional = false,
}: {
  file: File | null;
  onChange: (file: File | null) => void;
  onError: (message: string) => void;
  disabled: boolean;
  optional?: boolean;
}) {
  const input = useRef<HTMLInputElement>(null);
  function choose(chosen?: File) {
    if (!chosen) return;
    if (
      chosen.size > 10 * 1024 * 1024 ||
      chosen.size === 0 ||
      !["application/pdf", "image/jpeg", "image/png", "image/webp"].includes(
        chosen.type,
      )
    ) {
      onChange(null);
      onError("Alege un PDF, JPG, PNG sau WebP de cel mult 10 MB.");
      return;
    }
    onChange(chosen);
    onError("");
  }
  return (
    <>
      <button
        type="button"
        disabled={disabled}
        autoFocus={!optional}
        className={
          file || !optional
            ? `dropzone ${file ? "has-file" : ""}`
            : "button secondary full"
        }
        onClick={() => input.current?.click()}
        onDragOver={(event) => event.preventDefault()}
        onDrop={(event) => {
          event.preventDefault();
          if (!disabled) choose(event.dataTransfer.files[0]);
        }}
      >
        {file ? (
          <FileText size={32} aria-hidden="true" />
        ) : (
          <UploadCloud
            size={optional ? 18 : 34}
            strokeWidth={1.4}
            aria-hidden="true"
          />
        )}
        {file ? (
          <strong>{file.name}</strong>
        ) : optional ? (
          "Adaugă fișier (opțional)"
        ) : (
          <strong>Alege un fișier sau trage-l aici</strong>
        )}
        {file && <span>Apasă pentru a înlocui</span>}
      </button>
      {file && (
        <button
          type="button"
          className="text-button"
          disabled={disabled}
          onClick={() => {
            onChange(null);
            onError("");
          }}
        >
          Elimină fișierul selectat
        </button>
      )}
      {(file || !optional) && (
        <p className="upload-limits">
          PDF, JPG, PNG, WebP · maximum 10 MB/fișier și 100 MB/familie. Imagini
          de maximum 16 megapixeli.
        </p>
      )}
      <input
        ref={input}
        hidden
        type="file"
        accept=".pdf,.jpg,.jpeg,.png,.webp"
        onChange={(event) => {
          choose(event.target.files?.[0]);
          event.target.value = "";
        }}
      />
    </>
  );
}
