import { useState } from "react";
import { api, ApiError, errorMessage } from "./api";
import { Modal } from "./Modal";
import type { PhotoStatus } from "./PrivatePhoto";
import "./photoEditor.css";

export function ProfilePhotoEditor<
  T extends { version: number; photo_status: PhotoStatus },
>({
  profile,
  base,
  onClose,
  onSaved,
  onReload,
}: {
  profile: T;
  base: string;
  onClose: () => void;
  onSaved: (profile: T) => void;
  onReload: () => void;
}) {
  const [file, setFile] = useState<File | null>(null);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState("");
  const [stale, setStale] = useState(false);
  async function save(action: "replace" | "remove") {
    setBusy(true);
    setError("");
    const data = new FormData();
    data.set("action", action);
    if (file && action === "replace") data.set("photo", file);
    try {
      onSaved(
        await api<T>(`${base}/photo`, {
          method: "POST",
          headers: { "If-Match": `"${profile.version}"` },
          body: data,
        }),
      );
    } catch (e) {
      setError(errorMessage(e));
      if (e instanceof ApiError && [403, 404, 412].includes(e.status))
        setStale(true);
    } finally {
      setBusy(false);
    }
  }
  return (
    <Modal
      title="Fotografie de profil"
      onClose={() => {
        if (!busy) onClose();
      }}
    >
      <fieldset className="photo-editor-fields" disabled={busy || stale}>
        <label className="field">
          Fotografie
          <input
            type="file"
            accept="image/jpeg,image/png,image/webp"
            onChange={(e) => setFile(e.target.files?.[0] ?? null)}
          />
          <small>
            JPG, PNG sau WebP, maximum 10 MB și 16 megapixeli. Fotografia este
            vizibilă membrilor familiei.
          </small>
        </label>
        <button
          className="button primary full"
          disabled={!file}
          onClick={() => void save("replace")}
        >
          Salvează fotografia
        </button>
        {profile.photo_status && (
          <button
            className="button secondary full photo-editor-remove"
            onClick={() => void save("remove")}
          >
            Elimină fotografia
          </button>
        )}
      </fieldset>
      {error && (
        <p role="alert" className="error">
          {error}
        </p>
      )}
      {stale && (
        <button className="button secondary full" onClick={onReload}>
          Renunță la modificări și reîncarcă
        </button>
      )}
    </Modal>
  );
}
