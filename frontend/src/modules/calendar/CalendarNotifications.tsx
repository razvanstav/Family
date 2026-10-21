import { useEffect, useRef, useState } from "react";
import { Bell, CalendarDays, Check, Circle, RefreshCw } from "lucide-react";
import { api, ApiError } from "../../shared/api";
import { Modal } from "../../shared/Modal";
import { dateLabel } from "../../shared/calendarDates";
import "./notifications.css";

type Notification = {
  id: string;
  event_id: string;
  title: string;
  event_date: string;
  all_day: boolean;
  time: string | null;
  delivered_at: string;
  read_at: string | null;
};
type Inbox = { items: Notification[]; unread_count: number; has_more: boolean };

export function CalendarNotifications({
  familyId,
  revision,
  onExpired,
  onOpenEvent,
}: {
  familyId: string;
  revision: number;
  onExpired: () => void;
  onOpenEvent: (event: { id: string; date: string }) => void;
}) {
  const [open, setOpen] = useState(false);
  const [data, setData] = useState<Inbox | null>(null);
  const [error, setError] = useState("");
  const [loading, setLoading] = useState(false);
  const [busy, setBusy] = useState<string | null>(null);
  const [page, setPage] = useState(1);
  const [attempt, setAttempt] = useState(0);
  const opened = useRef(false);
  const sequence = useRef(0);
  const mutating = useRef(false);
  const endpoint = `/families/${familyId}/calendar-notifications`;
  function failed(e: unknown) {
    setError(
      e instanceof ApiError
        ? e.message
        : "Nu am putut încărca notificările. Verifică conexiunea și reîncearcă.",
    );
    if (e instanceof ApiError && [401, 404, 419].includes(e.status))
      onExpired();
  }
  useEffect(() => {
    let active = true;
    async function load() {
      const request = ++sequence.current;
      setLoading(true);
      try {
        const result = await api<Inbox>(endpoint);
        if (active && request === sequence.current) {
          setData(result);
          setPage(1);
          setError("");
        }
      } catch (e) {
        if (active && request === sequence.current) failed(e);
      } finally {
        if (active && request === sequence.current) setLoading(false);
      }
    }
    void load();
    const timer = window.setInterval(() => {
      if (
        document.visibilityState === "visible" &&
        !opened.current &&
        !mutating.current
      )
        void load();
    }, 30000);
    return () => {
      active = false;
      sequence.current++;
      window.clearInterval(timer);
    };
  }, [endpoint, attempt, revision]);
  function close() {
    opened.current = false;
    setOpen(false);
  }
  async function more() {
    if (loading) return;
    const request = ++sequence.current;
    setLoading(true);
    setError("");
    try {
      const next = await api<Inbox>(`${endpoint}?page=${page + 1}`);
      if (request === sequence.current) {
        setData((current) => ({
          ...next,
          items: [
            ...(current?.items ?? []),
            ...next.items.filter(
              (item) => !current?.items.some((old) => old.id === item.id),
            ),
          ],
        }));
        setPage((value) => value + 1);
      }
    } catch (e) {
      if (request === sequence.current) failed(e);
    } finally {
      if (request === sequence.current) setLoading(false);
    }
  }
  async function read(item: Notification, value: boolean, navigate = false) {
    if (busy) return;
    const request = ++sequence.current;
    mutating.current = true;
    setBusy(item.id);
    setError("");
    try {
      if (navigate || value !== Boolean(item.read_at)) {
        await api(`${endpoint}/${item.id}`, {
          method: "PATCH",
          body: JSON.stringify({ read: value }),
        });
        if (request !== sequence.current) return;
        setData(
          (current) =>
            current && {
              ...current,
              unread_count: Math.max(
                0,
                current.unread_count +
                  (value === Boolean(item.read_at) ? 0 : value ? -1 : 1),
              ),
              items: current.items.map((row) =>
                row.id === item.id
                  ? { ...row, read_at: value ? new Date().toISOString() : null }
                  : row,
              ),
            },
        );
      }
      if (navigate && request === sequence.current) {
        close();
        onOpenEvent({ id: item.event_id, date: item.event_date });
      }
    } catch (e) {
      if (request === sequence.current) failed(e);
    } finally {
      mutating.current = false;
      setBusy(null);
      if (request === sequence.current) setLoading(false);
    }
  }
  return (
    <>
      <button
        className="icon-button notification-button"
        aria-label={
          data?.unread_count
            ? `Notificări, ${data.unread_count} ${data.unread_count === 1 ? "necitită" : "necitite"}`
            : "Notificări"
        }
        onClick={() => {
          opened.current = true;
          setOpen(true);
        }}
      >
        <Bell size={20} />
        {Boolean(data?.unread_count) && (
          <span className="notification-count" aria-hidden="true">
            {data!.unread_count > 99 ? "99+" : data!.unread_count}
          </span>
        )}
      </button>
      {open && (
        <Modal title="Notificări" onClose={close}>
          <div className="notification-tools">
            <button
              className="text-button"
              disabled={loading || Boolean(busy)}
              onClick={() => setAttempt((value) => value + 1)}
            >
              <RefreshCw size={16} />
              Reîncarcă
            </button>
          </div>
          {error && (
            <p className="error" role="alert">
              {error}
            </p>
          )}
          {loading && !data && <p role="status">Se încarcă notificările…</p>}
          {data?.items.length === 0 && (
            <p className="calendar-empty">Nu ai notificări.</p>
          )}
          <ul className="notification-list">
            {data?.items.map((item) => (
              <li key={item.id} className={item.read_at ? "" : "unread"}>
                <CalendarDays size={20} aria-hidden="true" />
                <button
                  className="notification-source"
                  disabled={Boolean(busy)}
                  onClick={() => void read(item, true, true)}
                >
                  <strong>{item.title}</strong>
                  <span>
                    {dateLabel(item.event_date, {
                      day: "numeric",
                      month: "long",
                    })}{" "}
                    · {item.all_day ? "Toată ziua" : item.time}
                  </span>
                  <small>
                    {item.read_at ? "Citit" : "Necitit"} ·{" "}
                    {new Intl.DateTimeFormat("ro-RO", {
                      day: "numeric",
                      month: "short",
                      hour: "2-digit",
                      minute: "2-digit",
                    }).format(new Date(item.delivered_at))}
                  </small>
                </button>
                <button
                  className="icon-button"
                  disabled={Boolean(busy)}
                  aria-label={`${item.read_at ? "Marchează necitit" : "Marchează citit"}: ${item.title}`}
                  onClick={() => void read(item, !item.read_at)}
                >
                  {item.read_at ? <Circle size={17} /> : <Check size={17} />}
                </button>
              </li>
            ))}
          </ul>
          {data?.has_more && (
            <button
              className="button secondary full"
              disabled={loading || Boolean(busy)}
              onClick={() => void more()}
            >
              {loading ? "Se încarcă…" : "Mai multe notificări"}
            </button>
          )}
        </Modal>
      )}
    </>
  );
}
