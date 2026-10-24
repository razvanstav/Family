import { useEffect, useRef, useState, type PointerEvent } from "react";
import { ChevronLeft, ChevronRight, Expand, Image, X } from "lucide-react";
import { PrivatePhoto } from "../../shared/PrivatePhoto";
import { galleryIndex, swipeStep } from "./galleryNavigation";
import type { Moment, MomentPhoto } from "./memoryTypes";

export function momentPhotoUrl(
  base: string,
  moment: Moment,
  photo: MomentPhoto,
) {
  return `/api/v1${base}/${moment.id}/photos/${photo.id}?v=${moment.version}`;
}

export function MomentGallery({
  base,
  moment,
}: {
  base: string;
  moment: Moment;
}) {
  const [selected, setSelected] = useState(moment.photos[0]?.id);
  const [expanded, setExpanded] = useState(false);
  const photo =
    moment.photos.find((item) => item.id === selected) ?? moment.photos[0];
  if (!photo) return null;
  const index = moment.photos.indexOf(photo);
  return (
    <div className="moment-gallery">
      <button
        type="button"
        className="moment-gallery-open"
        aria-label={`Deschide fotografia ${index + 1} din ${moment.photos.length} pe tot ecranul`}
        onClick={() => setExpanded(true)}
      >
        <PrivatePhoto
          src={momentPhotoUrl(base, moment, photo)}
          status={photo.status}
          className="moment-photo"
          placeholder={<Image size={28} aria-hidden="true" />}
          alt={`Fotografia ${index + 1}: ${moment.title}`}
        />
        <span className="moment-gallery-expand" aria-hidden="true">
          {index + 1} / {moment.photos.length}
          <Expand size={18} />
        </span>
      </button>
      {moment.photos.length > 1 && (
        <div className="moment-gallery-thumbnails" aria-label="Fotografii">
          {moment.photos.map((item, position) => (
            <button
              key={item.id}
              type="button"
              aria-label={`Fotografia ${position + 1}${item.status === "quarantine" ? ", în verificare" : item.status === "rejected" ? ", respinsă" : ""}`}
              aria-pressed={item.id === photo.id}
              onClick={() => setSelected(item.id)}
            >
              <PrivatePhoto
                src={momentPhotoUrl(base, moment, item)}
                status={item.status}
                className="moment-gallery-thumb"
                placeholder={<Image size={18} aria-hidden="true" />}
              />
            </button>
          ))}
        </div>
      )}
      {expanded && (
        <PhotoViewer
          base={base}
          moment={moment}
          index={index}
          onSelect={(next) => setSelected(moment.photos[next].id)}
          onClose={() => setExpanded(false)}
        />
      )}
    </div>
  );
}

function PhotoViewer({
  base,
  moment,
  index,
  onSelect,
  onClose,
}: {
  base: string;
  moment: Moment;
  index: number;
  onSelect: (index: number) => void;
  onClose: () => void;
}) {
  const dialogRef = useRef<HTMLDialogElement>(null);
  const start = useRef<{ x: number; y: number; id: number } | null>(null);
  const count = moment.photos.length;
  const photo = moment.photos[index];
  function move(step: number) {
    onSelect(galleryIndex(index, step, count));
  }
  useEffect(() => {
    const dialog = dialogRef.current!;
    const previous = document.activeElement as HTMLElement | null;
    dialog.showModal();
    return () => {
      dialog.close();
      previous?.focus();
    };
  }, []);
  function pointerDown(event: PointerEvent<HTMLDivElement>) {
    if (event.button !== 0) return;
    if (!event.isPrimary) {
      start.current = null;
      return;
    }
    start.current = { x: event.clientX, y: event.clientY, id: event.pointerId };
    if (event.pointerType === "mouse") event.preventDefault();
    event.currentTarget.setPointerCapture(event.pointerId);
  }
  function pointerUp(event: PointerEvent<HTMLDivElement>) {
    const origin = start.current;
    start.current = null;
    if (origin?.id !== event.pointerId) return;
    move(swipeStep(event.clientX - origin.x, event.clientY - origin.y));
  }
  return (
    <dialog
      ref={dialogRef}
      className="moment-viewer fullscreen-dialog"
      aria-label={`Fotografii: ${moment.title}`}
      onCancel={(event) => {
        event.preventDefault();
        event.stopPropagation();
        onClose();
      }}
      onKeyDown={(event) => {
        if (event.key === "Tab") {
          const controls = Array.from(
            event.currentTarget.querySelectorAll<HTMLButtonElement>(
              "button:not(:disabled)",
            ),
          );
          const first = controls[0];
          const last = controls[controls.length - 1];
          if (event.shiftKey && document.activeElement === first) {
            event.preventDefault();
            last?.focus();
          } else if (!event.shiftKey && document.activeElement === last) {
            event.preventDefault();
            first?.focus();
          }
        } else if (event.key === "ArrowLeft" || event.key === "ArrowRight") {
          event.preventDefault();
          move(event.key === "ArrowLeft" ? -1 : 1);
        } else if (event.key === "Home" || event.key === "End") {
          event.preventDefault();
          onSelect(event.key === "Home" ? 0 : count - 1);
        }
      }}
    >
      <div className="moment-viewer-heading">
        <span className="moment-viewer-title">{moment.title}</span>
        <span
          className="moment-viewer-count"
          role="status"
          aria-live="polite"
          aria-atomic="true"
        >
          {index + 1} / {count}
        </span>
        <button
          type="button"
          className="moment-viewer-control"
          aria-label="Închide fotografiile"
          onClick={onClose}
        >
          <X size={23} />
        </button>
      </div>
      <div
        className="moment-viewer-stage"
        onPointerDown={pointerDown}
        onPointerUp={pointerUp}
        onPointerCancel={() => {
          start.current = null;
        }}
      >
        <PrivatePhoto
          key={`${moment.version}:${photo.id}`}
          src={momentPhotoUrl(base, moment, photo)}
          status={photo.status}
          className="moment-viewer-photo"
          placeholder={<Image size={36} aria-hidden="true" />}
          alt={`Fotografia ${index + 1} din ${count}: ${moment.title}`}
        />
      </div>
      <div className="moment-viewer-navigation">
        <button
          type="button"
          className="moment-viewer-control"
          aria-label="Fotografia anterioară"
          disabled={index === 0}
          onClick={() => move(-1)}
        >
          <ChevronLeft size={25} />
        </button>
        <div className="moment-viewer-pages" aria-hidden="true">
          {moment.photos.map((item, position) => (
            <span
              key={item.id}
              className={position === index ? "active" : ""}
            />
          ))}
        </div>
        <button
          type="button"
          className="moment-viewer-control"
          aria-label="Fotografia următoare"
          disabled={index === count - 1}
          onClick={() => move(1)}
        >
          <ChevronRight size={25} />
        </button>
      </div>
    </dialog>
  );
}
