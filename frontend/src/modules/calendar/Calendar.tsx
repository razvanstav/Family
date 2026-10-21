import { useEffect, useRef, useState, type FormEvent } from "react";
import {
  Archive,
  CalendarDays,
  ChevronRight,
  Clock3,
  LockKeyhole,
  Plus,
  RefreshCw,
  RotateCcw,
  Users,
} from "lucide-react";
import {
  api,
  ApiError,
  type Family,
  type CalendarRequest,
  visibilityLabel,
} from "../../shared/api";
import { Modal } from "../../shared/Modal";
import { bucharestToday, dateLabel } from "../../shared/calendarDates";
import type { CalendarItem } from "./calendarTypes";
import { CalendarMonth } from "../../shared/CalendarMonth";
import "./calendar.css";
import { ReminderFields } from "./ReminderFields";

export function CalendarPage({
  family,
  userId,
  requestedEvent,
  onEventOpened,
  onChanged,
  onExpired,
}: {
  family: Family;
  userId: number;
  requestedEvent: CalendarRequest | null;
  onEventOpened: () => void;
  onChanged: () => void;
  onExpired: () => void;
}) {
  const today = bucharestToday();
  const [month, setMonth] = useState(
    (requestedEvent?.date ?? today).slice(0, 7),
  );
  const [day, setDay] = useState<string | null>(
    requestedEvent?.monthOnly ? null : (requestedEvent?.date ?? null),
  );
  const [archived, setArchived] = useState(false);
  const [items, setItems] = useState<CalendarItem[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState("");
  const [notice, setNotice] = useState("");
  const [revision, setRevision] = useState(0);
  const [editor, setEditor] = useState<string | null>(
    requestedEvent?.id ?? null,
  );
  const sequence = useRef(0);
  const endpoint = `/families/${family.id}/calendar-items`;
  useEffect(() => {
    if (!requestedEvent) return;
    setMonth(requestedEvent.date.slice(0, 7));
    setDay(requestedEvent.monthOnly ? null : requestedEvent.date);
    setArchived(false);
    setEditor(requestedEvent.id ?? null);
    onEventOpened();
  }, [requestedEvent]);
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
    onChanged();
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
        <CalendarMonth
          month={month}
          day={day}
          today={today}
          markedDates={loading ? [] : items.map((item) => item.event_date)}
          onMonth={selectMonth}
          onDay={setDay}
          onToday={() => {
            setMonth(today.slice(0, 7));
            setDay(today);
          }}
          onAllMonth={() => setDay(null)}
        />
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
          familyId={family.id}
          userId={userId}
          key={editor}
          id={editor}
          endpoint={endpoint}
          initialItem={items.find((item) => item.id === editor) ?? null}
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
  familyId,
  userId,
  id,
  endpoint,
  initialItem,
  initialDate,
  onClose,
  onSaved,
  onUnavailable,
}: {
  familyId: string;
  userId: number;
  id: string;
  endpoint: string;
  initialItem: CalendarItem | null;
  initialDate: string;
  onClose: () => void;
  onSaved: (item: CalendarItem, action: string) => void;
  onUnavailable: () => void;
}) {
  const [item, setItem] = useState<CalendarItem | null>(initialItem);
  const [loading, setLoading] = useState(id !== "new" && !initialItem);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState("");
  const [stale, setStale] = useState(false);
  const [unavailable, setUnavailable] = useState(false);
  const [attempt, setAttempt] = useState(0);
  const [confirmArchive, setConfirmArchive] = useState(false);
  const [title, setTitle] = useState(initialItem?.title ?? "");
  const [date, setDate] = useState(initialItem?.event_date ?? initialDate);
  const [allDay, setAllDay] = useState(initialItem?.all_day ?? true);
  const [time, setTime] = useState(initialItem?.time ?? "10:00");
  const [notes, setNotes] = useState(initialItem?.notes ?? "");
  const [reminderOffset, setReminderOffset] = useState<number | null>(
    initialItem?.reminder?.offset_minutes ?? null,
  );
  const [recipients, setRecipients] = useState<string[]>(
    initialItem?.reminder?.recipients ?? [],
  );
  const [visibility, setVisibility] = useState<"private" | "family">(
    initialItem?.visibility ?? "private",
  );
  useEffect(() => {
    if (id === "new" || (initialItem && attempt === 0)) return;
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
        setReminderOffset(data.reminder?.offset_minutes ?? null);
        setRecipients(data.reminder?.recipients ?? []);
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
            reminder_offset_minutes: reminderOffset,
            reminder_recipients: reminderOffset === null ? [] : recipients,
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
                    onChange={(e) => {
                      setAllDay(e.target.checked);
                      if (
                        e.target.checked &&
                        reminderOffset !== null &&
                        ![0, 1440].includes(reminderOffset)
                      )
                        setReminderOffset(0);
                    }}
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
                    <small>Ora României</small>
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
                <ReminderFields
                  familyId={familyId}
                  userId={userId}
                  allDay={allDay}
                  visibility={visibility}
                  offset={reminderOffset}
                  recipients={recipients}
                  onOffset={setReminderOffset}
                  onRecipients={setRecipients}
                />
                {item?.reminder && (
                  <div className="calendar-reminder-state" role="status">
                    {item.reminder.deliveries
                      .map(
                        (delivery) =>
                          ({
                            pending: "Programat",
                            delivered: "Livrat",
                            cancelled: "Anulat",
                            expired: "Moment depășit",
                          })[delivery.state],
                      )
                      .filter(
                        (value, index, values) =>
                          values.indexOf(value) === index,
                      )
                      .join(" · ")}
                  </div>
                )}
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
                  disabled={
                    !title.trim() ||
                    (reminderOffset !== null && !recipients.length)
                  }
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
                  {item.all_day ? "Toată ziua" : item.time}
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
