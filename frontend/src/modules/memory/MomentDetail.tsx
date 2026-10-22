import { useEffect, useRef, useState } from "react";
import {
  BookHeart,
  LockKeyhole,
  Pencil,
  RefreshCw,
  StickyNote,
  Users,
} from "lucide-react";
import { api, ApiError, visibilityLabel } from "../../shared/api";
import { dateLabel } from "../../shared/calendarDates";
import { Modal } from "../../shared/Modal";
import { momentKinds, type Moment } from "./memoryTypes";

export function MomentDetail({
  base,
  moment,
  onClose,
  onEdit,
  onUnavailable,
}: {
  base: string;
  moment: Moment;
  onClose: () => void;
  onEdit: (moment: Moment) => void;
  onUnavailable: () => void;
}) {
  const [item, setItem] = useState(moment);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState("");
  const pending = useRef<AbortController | null>(null);

  async function refresh() {
    if (pending.current) return;
    const controller = new AbortController();
    pending.current = controller;
    setBusy(true);
    setError("");
    try {
      const current = await api<Moment>(`${base}/${moment.id}`, {
        signal: controller.signal,
      });
      if (!controller.signal.aborted) setItem(current);
    } catch (e) {
      if (controller.signal.aborted) return;
      if (e instanceof ApiError && [401, 403, 404, 419].includes(e.status))
        onUnavailable();
      else setError("Amintirea nu a putut fi actualizată. Reîncearcă.");
    } finally {
      if (!controller.signal.aborted) {
        pending.current = null;
        setBusy(false);
      }
    }
  }
  useEffect(() => {
    const timer = window.setInterval(() => {
      if (document.visibilityState === "visible") void refresh();
    }, 60000);
    return () => {
      window.clearInterval(timer);
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
            {item.visibility === "private" ? (
              <LockKeyhole size={16} />
            ) : (
              <Users size={16} />
            )}
            {visibilityLabel[item.visibility]}
          </span>
        </div>
        {item.body && <p className="moment-body">{item.body}</p>}
        {item.people.length > 0 && (
          <div className="moment-person-tags" aria-label="Persoane menționate">
            {item.people.map((person) => (
              <span key={person.id}>{person.name}</span>
            ))}
          </div>
        )}
        <p className="moment-author">Adăugat de {item.owner_name}</p>
        {error && (
          <p className="error" role="alert">
            {error}
          </p>
        )}
        <div className="moment-actions">
          <button
            className="button secondary"
            disabled={busy}
            onClick={() => void refresh()}
          >
            <RefreshCw size={17} />
            {busy ? "Se actualizează…" : "Actualizează"}
          </button>
          {item.can_edit && (
            <button
              className="button primary"
              disabled={busy}
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
