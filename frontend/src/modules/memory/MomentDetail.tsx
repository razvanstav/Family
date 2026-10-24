import { useEffect, useRef, useState } from "react";
import {
  BookHeart,
  Archive,
  ArchiveRestore,
  LockKeyhole,
  Pencil,
  RefreshCw,
  StickyNote,
  Users,
} from "lucide-react";
import { api, ApiError, visibilityLabel } from "../../shared/api";
import { dateLabel } from "../../shared/calendarDates";
import { Modal } from "../../shared/Modal";
import { observePageResume } from "../../shared/pageResume";
import { MomentGallery } from "./MomentGallery";
import { momentKinds, type Moment } from "./memoryTypes";

export function MomentDetail({
  base,
  moment,
  onClose,
  onEdit,
  onUnavailable,
  onChanged,
}: {
  base: string;
  moment: Moment;
  onClose: () => void;
  onEdit: (moment: Moment) => void;
  onUnavailable: () => void;
  onChanged: (moment: Moment) => void;
}) {
  const [item, setItem] = useState(moment);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState("");
  const [confirmArchive, setConfirmArchive] = useState(false);
  const [conflict, setConflict] = useState(false);
  const pending = useRef<AbortController | null>(null);

  async function perform(
    action?: "archive" | "restore" | `photos/${string}/scan`,
  ) {
    if (pending.current) return;
    const controller = new AbortController();
    pending.current = controller;
    setBusy(true);
    setError("");
    try {
      const current = await api<Moment>(
        `${base}/${moment.id}${action ? `/${action}` : ""}`,
        {
          signal: controller.signal,
          ...(action
            ? { method: "POST", headers: { "If-Match": `"${item.version}"` } }
            : {}),
        },
      );
      if (!controller.signal.aborted) {
        setItem(current);
        setConflict(false);
        setConfirmArchive(false);
        onChanged(current);
      }
    } catch (e) {
      if (controller.signal.aborted) return;
      if (e instanceof ApiError && [401, 403, 404, 419].includes(e.status))
        onUnavailable();
      else if (e instanceof ApiError && [409, 412].includes(e.status)) {
        setConflict(true);
        setError(
          "Amintirea s-a schimbat. Actualizeaz-o înainte de a continua.",
        );
      } else
        setError(
          e instanceof ApiError
            ? e.message
            : "Amintirea nu a putut fi actualizată. Reîncearcă.",
        );
    } finally {
      if (!controller.signal.aborted) {
        pending.current = null;
        setBusy(false);
      }
    }
  }
  useEffect(() => {
    const unsubscribe = observePageResume(document, window, () => {
      void perform();
    });
    return () => {
      unsubscribe();
      pending.current?.abort();
    };
  }, [base, moment.id]);

  return (
    <Modal title={item.title} onClose={onClose} wide>
      <div className="moment-detail">
        <div className="moment-meta">
          <span>
            {item.kind === "moment" ? (
              <BookHeart size={17} />
            ) : (
              <StickyNote size={17} />
            )}
            {momentKinds[item.kind]}
          </span>
          <time dateTime={item.occurred_on}>{dateLabel(item.occurred_on)}</time>
          <span>
            {item.visibility !== "family" ? (
              <LockKeyhole size={16} />
            ) : (
              <Users size={16} />
            )}
            {visibilityLabel[item.visibility]}
          </span>
          {item.archived_at && (
            <span>
              <Archive size={16} />
              Arhivată
            </span>
          )}
        </div>
        <MomentGallery base={base} moment={item} />
        {item.body && <p className="moment-body">{item.body}</p>}
        {item.people.length > 0 && (
          <div className="moment-person-tags" aria-label="Persoane menționate">
            {item.people.map((person) => (
              <span key={person.id}>{person.name}</span>
            ))}
          </div>
        )}
        <p className="moment-author">Adăugat de {item.owner_name}</p>
        {confirmArchive && (
          <p className="notice" role="status">
            {item.archived_at
              ? "Amintirea va reveni în lista activă cu acces „Doar eu”."
              : "Amintirea și fotografiile vor fi vizibile doar pentru tine, în Arhivate. Partajarea se retrage; restaurarea folosește accesul „Doar eu”."}
          </p>
        )}
        {error && (
          <p className="error" role="alert">
            {error}
          </p>
        )}
        <div className="moment-actions">
          <button
            className="button secondary"
            disabled={busy}
            onClick={() => void perform()}
          >
            <RefreshCw size={17} />
            {busy ? "Se actualizează…" : "Actualizează"}
          </button>
          {item.can_manage && !confirmArchive && (
            <button
              className="button secondary"
              disabled={busy || conflict}
              onClick={() => setConfirmArchive(true)}
            >
              {item.archived_at ? (
                <ArchiveRestore size={17} />
              ) : (
                <Archive size={17} />
              )}
              {item.archived_at ? "Restaurează" : "Arhivează"}
            </button>
          )}
          {confirmArchive && (
            <>
              <button
                className="button secondary"
                disabled={busy}
                onClick={() => setConfirmArchive(false)}
              >
                Anulează
              </button>
              <button
                className="button primary"
                disabled={busy || conflict}
                onClick={() =>
                  void perform(item.archived_at ? "restore" : "archive")
                }
              >
                {item.archived_at ? "Restaurează" : "Arhivează"}
              </button>
            </>
          )}
          {item.can_edit &&
            !confirmArchive &&
            item.photos
              .filter((photo) => photo.status === "quarantine")
              .map((photo) => (
                <button
                  key={photo.id}
                  className="button secondary"
                  disabled={busy || conflict}
                  onClick={() => void perform(`photos/${photo.id}/scan`)}
                >
                  Verifică fotografia {item.photos.indexOf(photo) + 1}
                </button>
              ))}
          {item.can_edit && !confirmArchive && (
            <button
              className="button primary"
              disabled={busy || conflict}
              onClick={() => onEdit(item)}
            >
              <Pencil size={17} />
              Editează
            </button>
          )}
        </div>
      </div>
    </Modal>
  );
}
