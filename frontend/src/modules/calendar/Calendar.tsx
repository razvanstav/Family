import { useEffect, useRef, useState, type FormEvent } from "react";
import {
  Archive,
  CalendarDays,
  ChevronLeft,
  ChevronRight,
  Clock3,
  LockKeyhole,
  Plus,
  RefreshCw,
  RotateCcw,
  Users,
} from "lucide-react";
import { api, ApiError, type Family, visibilityLabel } from "../../shared/api";
import { Modal } from "../../shared/Modal";
import {
  bucharestToday,
  dateLabel,
  monthDays,
  moveMonth,
  type CalendarItem,
} from "./calendarDates";
import "./calendar.css";

export function CalendarPage({
  family,
  onExpired,
}: {
  family: Family;
  onExpired: () => void;
}) {
  const today = bucharestToday();
  const [month, setMonth] = useState(today.slice(0, 7));
  const [day, setDay] = useState<string | null>(null);
  const [archived, setArchived] = useState(false);
  const [items, setItems] = useState<CalendarItem[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState("");
  const [notice, setNotice] = useState("");
  const [revision, setRevision] = useState(0);
  const [editor, setEditor] = useState<string | null>(null);
  const sequence = useRef(0);
  const endpoint = `/families/${family.id}/calendar-items`;
  useEffect(() => {
    const request = ++sequence.current;
    setLoading(true);
    setError("");
    setItems([]);
    api<CalendarItem[]>(
      `${endpoint}?month=${month}&archived=${archived ? 1 : 0}`,
    )
      .then((data) => {
        if (request === sequence.current) setItems(data);
      })
      .catch((e) => {
        if (request !== sequence.current) return;
        setError(
          e instanceof ApiError
            ? e.message
            : "Nu am putut încărca evenimentele. Verifică conexiunea și reîncearcă.",
        );
        if (e instanceof ApiError && [401, 404, 419].includes(e.status))
          onExpired();
      })
      .finally(() => {
        if (request === sequence.current) setLoading(false);
      });
    return () => {
      sequence.current++;
    };
  }, [endpoint, month, archived, revision]);
  function selectMonth(value: string) {
    setMonth(value);
    setDay(null);
    setNotice("");
  }
  const visible = day ? items.filter((item) => item.event_date === day) : items;
  function saved(item: CalendarItem, action: string) {
    setEditor(null);
    setMonth(item.event_date.slice(0, 7));
    setDay(item.event_date);
    if (action === "restore") setArchived(false);
    setNotice(
      action === "archive"
        ? "Eveniment arhivat. Îl poți restaura din Arhivate."
        : action === "restore"
          ? "Eveniment restaurat."
          : "Eveniment salvat.",
    );
    setRevision((value) => value + 1);
  }
  return (
    <div className="calendar-page">
      <section className="page-heading">
        <h1>Calendar</h1>
        <button
          className="button primary"
          aria-label="Adaugă eveniment"
          disabled={family.role === "limited"}
          onClick={() => setEditor("new")}
        >
          <Plus size={18} /> <span>Adaugă eveniment</span>
        </button>
      </section>
      {notice && (
        <p className="notice" role="status">
          {notice}
        </p>
      )}
      <div className="calendar-layout">
        <section className="calendar-month" aria-label="Calendar lunar">
          <div className="calendar-month-heading">
            <h2>
              {dateLabel(`${month}-01`, { month: "long", year: "numeric" })}
            </h2>
            <div>
              <button
                className="icon-button"
                aria-label="Luna precedentă"
                disabled={month === "1900-01"}
                onClick={() => selectMonth(moveMonth(month, -1))}
              >
                <ChevronLeft size={20} />
              </button>
              <button
                className="icon-button"
                aria-label="Luna următoare"
                disabled={month === "2100-12"}
                onClick={() => selectMonth(moveMonth(month, 1))}
              >
                <ChevronRight size={20} />
              </button>
            </div>
          </div>
          <div className="calendar-weekdays" aria-hidden="true">
            {["L", "M", "M", "J", "V", "S", "D"].map((label, i) => (
              <span key={i}>{label}</span>
            ))}
          </div>
          <div className="calendar-days">
            {monthDays(month).map((date, i) =>
              date ? (
                <button
                  key={date}
                  className={`${day === date ? "selected " : ""}${date === today ? "today" : ""}`}
                  aria-label={dateLabel(date)}
                  aria-pressed={day === date}
                  aria-current={date === today ? "date" : undefined}
                  onClick={() => setDay(date)}
                >
                  <span>{Number(date.slice(-2))}</span>
                  {!loading &&
                    items.some((item) => item.event_date === date) && (
                      <span
                        className="calendar-dot"
                        aria-label="Are evenimente"
                      />
                    )}
                </button>
              ) : (
                <span key={`blank-${i}`} />
              ),
            )}
          </div>
          <div className="calendar-month-footer">
            <button
              className="text-button"
              onClick={() => {
                setMonth(today.slice(0, 7));
                setDay(today);
              }}
            >
              Astăzi
            </button>
            <button
              className="text-button"
              aria-pressed={day === null}
              onClick={() => setDay(null)}
            >
              Toată luna
            </button>
          </div>
          <p className="calendar-timezone">Europe/Bucharest</p>
        </section>
        <section className="calendar-agenda" aria-labelledby="agenda-title">
          <div className="calendar-agenda-heading">
            <h2 id="agenda-title">
              {day
                ? dateLabel(day, { day: "numeric", month: "long" })
                : "Evenimentele lunii"}
            </h2>
            <button
              className="icon-button"
              aria-label="Actualizează evenimentele"
              onClick={() => setRevision((value) => value + 1)}
              disabled={loading}
            >
              <RefreshCw size={18} />
            </button>
          </div>
          <div className="calendar-filters">
            <button aria-pressed={!archived} onClick={() => setArchived(false)}>
              Active
            </button>
            <button aria-pressed={archived} onClick={() => setArchived(true)}>
              Arhivate
            </button>
          </div>
          {loading ? (
            <p className="calendar-empty" role="status">
              Se încarcă evenimentele…
            </p>
          ) : error ? (
            <div className="calendar-empty">
              <p role="alert" className="error">
                {error}
              </p>
              <button
                className="button secondary"
                onClick={() => setRevision((value) => value + 1)}
              >
                Reîncearcă
              </button>
            </div>
          ) : visible.length === 0 ? (
            <div className="calendar-empty">
              <CalendarDays size={30} />
              <p>
                {archived
                  ? "Niciun eveniment arhivat."
                  : day
                    ? "Niciun eveniment în această zi."
                    : "Niciun eveniment în această lună."}
              </p>
            </div>
          ) : (
            <div className="calendar-events">
              {visible.map((item) => (
                <button
                  key={item.id}
                  className="calendar-event"
                  onClick={() => setEditor(item.id)}
                  aria-label={`${item.title} · ${dateLabel(item.event_date)} · ${item.all_day ? "Toată ziua" : item.time} · ${visibilityLabel[item.visibility]}`}
                >
                  <span className="calendar-date">
                    <strong>{Number(item.event_date.slice(-2))}</strong>
                    <span>
                      {dateLabel(item.event_date, { month: "short" })}
                    </span>
                  </span>
                  <span className="calendar-event-info">
                    <strong>{item.title}</strong>
                    <span>
                      <Clock3 size={14} />
                      {item.all_day ? "Toată ziua" : item.time}
                    </span>
                  </span>
                  <span
                    className="calendar-access"
                    aria-label={visibilityLabel[item.visibility]}
                  >
                    {item.visibility === "private" ? (
                      <LockKeyhole size={17} />
                    ) : (
                      <Users size={17} />
                    )}
                  </span>
                  <ChevronRight size={16} />
                </button>
              ))}
            </div>
          )}
        </section>
      </div>
      {editor && (
        <EventEditor
          key={editor}
          id={editor}
          endpoint={endpoint}
          initialDate={
            day ?? (month === today.slice(0, 7) ? today : `${month}-01`)
          }
          onClose={() => setEditor(null)}
          onSaved={saved}
          onUnavailable={() => {
            setEditor(null);
            setRevision((value) => value + 1);
          }}
        />
      )}
    </div>
  );
}

function EventEditor({
  id,
  endpoint,
  initialDate,
  onClose,
  onSaved,
  onUnavailable,
}: {
  id: string;
  endpoint: string;
  initialDate: string;
  onClose: () => void;
  onSaved: (item: CalendarItem, action: string) => void;
  onUnavailable: () => void;
}) {
  const [item, setItem] = useState<CalendarItem | null>(null);
  const [loading, setLoading] = useState(id !== "new");
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState("");
  const [stale, setStale] = useState(false);
  const [unavailable, setUnavailable] = useState(false);
  const [attempt, setAttempt] = useState(0);
  const [confirmArchive, setConfirmArchive] = useState(false);
  const [title, setTitle] = useState("");
  const [date, setDate] = useState(initialDate);
  const [allDay, setAllDay] = useState(true);
  const [time, setTime] = useState("10:00");
  const [notes, setNotes] = useState("");
  const [visibility, setVisibility] = useState<"private" | "family">("private");
  useEffect(() => {
    if (id === "new") return;
    let active = true;
    setLoading(true);
    setError("");
    setStale(false);
    setConfirmArchive(false);
    api<CalendarItem>(`${endpoint}/${id}`)
      .then((data) => {
        if (!active) return;
        setItem(data);
        setTitle(data.title);
        setDate(data.event_date);
        setAllDay(data.all_day);
        setTime(data.time ?? "10:00");
        setNotes(data.notes ?? "");
        setVisibility(data.visibility);
      })
      .catch((e) => {
        if (active) handleError(e, true);
      })
      .finally(() => {
        if (active) setLoading(false);
      });
    return () => {
      active = false;
    };
  }, [id, endpoint, attempt]);
  function handleError(e: unknown, reading = false) {
    if (e instanceof ApiError && [403, 404].includes(e.status)) {
      setItem(null);
      setUnavailable(true);
      setError(
        "Evenimentul nu mai este disponibil sau nu mai ai drept de modificare.",
      );
      return;
    }
    if (e instanceof ApiError && [409, 412].includes(e.status)) setStale(true);
    setError(
      e instanceof ApiError
        ? e.message
        : reading
          ? "Nu am putut încărca evenimentul. Verifică conexiunea și reîncearcă."
          : "Nu am putut salva modificarea. Verifică conexiunea și reîncearcă.",
    );
  }
  async function save(event: FormEvent) {
    event.preventDefault();
    if (busy || stale || unavailable) return;
    setBusy(true);
    setError("");
    try {
      onSaved(
        await api<CalendarItem>(id === "new" ? endpoint : `${endpoint}/${id}`, {
          method: id === "new" ? "POST" : "PATCH",
          headers: item ? { "If-Match": `"${item.version}"` } : undefined,
          body: JSON.stringify({
            title,
            event_date: date,
            all_day: allDay,
            time: allDay ? null : time,
            notes,
            visibility,
          }),
        }),
        "save",
      );
    } catch (e) {
      handleError(e);
    } finally {
      setBusy(false);
    }
  }
  async function archive(action: "archive" | "restore") {
    if (!item || busy || stale || unavailable) return;
    setBusy(true);
    setError("");
    try {
      onSaved(
        await api<CalendarItem>(`${endpoint}/${id}/${action}`, {
          method: "POST",
          headers: { "If-Match": `"${item.version}"` },
        }),
        action,
      );
    } catch (e) {
      handleError(e);
    } finally {
      setBusy(false);
    }
  }
  const editable = id === "new" || (item?.can_edit && !item.archived_at);
  return (
    <Modal
      title={id === "new" ? "Adaugă eveniment" : (item?.title ?? "Eveniment")}
      onClose={() => {
        if (!busy) onClose();
      }}
    >
      {loading ? (
        <p role="status">Se încarcă…</p>
      ) : unavailable ? (
        <button className="button secondary full" onClick={onUnavailable}>
          Înapoi la calendar
        </button>
      ) : id !== "new" && !item ? (
        <button
          className="button secondary full"
          onClick={() => setAttempt((value) => value + 1)}
        >
          Reîncearcă
        </button>
      ) : (
        <>
          {editable ? (
            <form onSubmit={save}>
              <fieldset className="calendar-fields" disabled={busy || stale}>
                <label className="field">
                  Numele evenimentului
                  <input
                    value={title}
                    onChange={(e) => setTitle(e.target.value)}
                    required
                    maxLength={160}
                  />
                </label>
                <label className="field">
                  Data
                  <input
                    type="date"
                    value={date}
                    min="1900-01-01"
                    max="2100-12-31"
                    onChange={(e) => setDate(e.target.value)}
                    required
                  />
                </label>
                <label className="calendar-check">
                  <input
                    type="checkbox"
                    checked={allDay}
                    onChange={(e) => setAllDay(e.target.checked)}
                  />
                  Toată ziua
                </label>
                {!allDay && (
                  <label className="field">
                    Ora
                    <input
                      type="time"
                      value={time}
                      onChange={(e) => setTime(e.target.value)}
                      required
                    />
                    <small>Europe/Bucharest</small>
                  </label>
                )}
                <label className="field">
                  Acces
                  <select
                    value={visibility}
                    onChange={(e) =>
                      setVisibility(e.target.value as "private" | "family")
                    }
                  >
                    <option value="private">Doar eu</option>
                    <option value="family">Familia mea</option>
                  </select>
                  <small>
                    {visibility === "family"
                      ? "Toți membrii activi pot vedea evenimentul. Doar tu îl poți modifica."
                      : "Evenimentul este vizibil doar pentru tine."}
                  </small>
                </label>
                <label className="field">
                  Notițe (opțional)
                  <textarea
                    rows={3}
                    maxLength={2000}
                    value={notes}
                    onChange={(e) => setNotes(e.target.value)}
                  />
                </label>
                <button
                  className="button primary full"
                  disabled={!title.trim()}
                  type="submit"
                >
                  {busy ? "Se salvează…" : "Salvează"}
                </button>
              </fieldset>
            </form>
          ) : (
            item && (
              <div className="calendar-detail">
                <p>
                  <CalendarDays size={18} />
                  {dateLabel(item.event_date)}
                </p>
                <p>
                  <Clock3 size={18} />
                  {item.all_day
                    ? "Toată ziua"
                    : `${item.time} · Europe/Bucharest`}
                </p>
                <p>
                  {visibilityLabel[item.visibility]} · {item.owner_name}
                </p>
                {item.notes && <p className="calendar-notes">{item.notes}</p>}
                {item.archived_at && (
                  <p className="notice">Eveniment arhivat.</p>
                )}
              </div>
            )
          )}
          {item?.can_edit && !confirmArchive && (
            <button
              className="button secondary full calendar-archive"
              disabled={busy || stale}
              onClick={() =>
                item.archived_at
                  ? void archive("restore")
                  : setConfirmArchive(true)
              }
            >
              {item.archived_at ? (
                <>
                  <RotateCcw size={17} />
                  Restaurează
                </>
              ) : (
                <>
                  <Archive size={17} />
                  Arhivează
                </>
              )}
            </button>
          )}
          {confirmArchive && (
            <div className="calendar-confirm">
              <p>
                Evenimentul dispare din calendarul activ. Îl poți restaura din
                Arhivate.
              </p>
              <div>
                <button
                  className="button secondary"
                  disabled={busy}
                  onClick={() => setConfirmArchive(false)}
                >
                  Anulează
                </button>
                <button
                  className="button primary"
                  disabled={busy || stale}
                  onClick={() => void archive("archive")}
                >
                  Arhivează
                </button>
              </div>
            </div>
          )}
        </>
      )}
      {error && (
        <p className="error" role="alert">
          {error}
        </p>
      )}
      {stale && (
        <button
          className="button secondary full"
          disabled={busy}
          onClick={() => setAttempt((value) => value + 1)}
        >
          Reîncarcă evenimentul
        </button>
      )}
    </Modal>
  );
}
