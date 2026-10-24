import { useEffect, useState } from "react";
import { Image, X } from "lucide-react";
import { PrivatePhoto } from "../../shared/PrivatePhoto";
import { momentPhotoUrl } from "./MomentGallery";
import type { Moment } from "./memoryTypes";

function DraftPhoto({ file }: { file: File }) {
  const [url, setUrl] = useState("");
  useEffect(() => {
    const reader = new FileReader();
    reader.onload = () => setUrl(String(reader.result));
    reader.readAsDataURL(file);
    return () => reader.abort();
  }, [file]);
  return <img src={url || undefined} alt={file.name} />;
}

export function MomentPhotoEditor({
  base,
  moment,
  photoIds,
  uploads,
  onRemove,
  onRemoveUpload,
  onAdd,
}: {
  base: string;
  moment?: Moment;
  photoIds: string[];
  uploads: File[];
  onRemove: (id: string) => void;
  onRemoveUpload: (index: number) => void;
  onAdd: (files: File[]) => void;
}) {
  const photos =
    moment?.photos.filter((photo) => photoIds.includes(photo.id)) ?? [];
  return (
    <div className="field">
      <label htmlFor="moment-photos">Fotografii</label>
      {(photos.length > 0 || uploads.length > 0) && (
        <div className="moment-photo-editor">
          {photos.map((photo, index) => (
            <div className="moment-photo-draft" key={photo.id}>
              <PrivatePhoto
                src={momentPhotoUrl(base, moment!, photo)}
                status={photo.status}
                className="moment-draft-image"
                placeholder={<Image size={23} aria-hidden="true" />}
                alt={`Fotografia ${index + 1}`}
              />
              {index === 0 && (
                <span className="moment-cover-label">Copertă</span>
              )}
              <button
                type="button"
                className="moment-photo-remove"
                aria-label={`Elimină fotografia ${index + 1}`}
                onClick={() => onRemove(photo.id)}
              >
                <X size={18} />
              </button>
            </div>
          ))}
          {uploads.map((file, index) => (
            <div
              className="moment-photo-draft"
              key={`${index}:${file.name}:${file.lastModified}`}
            >
              <div className="moment-draft-image">
                <DraftPhoto file={file} />
              </div>
              {photos.length === 0 && index === 0 && (
                <span className="moment-cover-label">Copertă</span>
              )}
              <button
                type="button"
                className="moment-photo-remove"
                aria-label={`Elimină fotografia nouă ${index + 1}`}
                onClick={() => onRemoveUpload(index)}
              >
                <X size={18} />
              </button>
            </div>
          ))}
        </div>
      )}
      <input
        id="moment-photos"
        type="file"
        accept="image/jpeg,image/png,image/webp"
        multiple
        disabled={photos.length + uploads.length >= 20}
        aria-describedby="moment-photos-hint"
        onChange={(event) => {
          onAdd(Array.from(event.currentTarget.files ?? []));
          event.currentTarget.value = "";
        }}
      />
      <small id="moment-photos-hint">
        Până la 20 de fotografii. JPG, PNG sau WebP, maximum 10 MB de fotografii
        noi la o salvare și 16 megapixeli/fotografie. Apar după verificare.
      </small>
    </div>
  );
}
