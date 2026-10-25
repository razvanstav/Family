import { useEffect, useRef, useState, type ReactNode } from "react";
import {
  Bell,
  CalendarDays,
  Clock3,
  Heart,
  LockKeyhole,
  Plus,
  RefreshCw,
  Users,
  BookHeart,
  UtensilsCrossed,
  ChevronRight,
  StickyNote,
  Sun,
} from "lucide-react";
import {
  api,
  ApiError,
  visibilityLabel,
  type CalendarRequest,
  type Family,
} from "../../shared/api";
import { CalendarMonth } from "../../shared/CalendarMonth";
import { bucharestToday, dateLabel } from "../../shared/calendarDates";
import { PrivatePhoto, type PhotoStatus } from "../../shared/PrivatePhoto";
import { PageBackdrop } from "../../shared/PageBackdrop";
import { observePageResume } from "../../shared/pageResume";
import { HomeActivityCard } from "./HomeActivityCard";
import type { ActivityTarget, HomeActivity } from "./homeActivity";
import "./home.css";

type EventSummary = {
  id: string;
  title: string;
  event_date: string;
  all_day: boolean;
  time: string | null;
  visibility: "private" | "family";
};
type ReminderSummary = {
  id: string;
  event_id: string;
  title: string;
  event_date: string;
  due_at: string;
};
type Overview<Task, Payment> = {
  today: string;
  month: string;
  days: string[];
  memories: MemorySummary[];
  events: EventSummary[];
  reminders: ReminderSummary[];
  tasks: Task[];
  upcoming_payments: Payment[];
  activity: HomeActivity[];
};

type MemorySummary = {
  id: string;
  title: string;
  kind: "moment" | "note";
  excerpt: string | null;
  occurred_on: string;
  visibility: keyof typeof visibilityLabel;
  photo_status: PhotoStatus;
};

export function Home<Task, Payment>({
  family,
  onExpired,
  onCalendar,
  onMemories,
  onKitchen,
  onActivity,
  renderTasks,
  renderUpcomingPayments,
}: {
  family: Family;
  onExpired: () => void;
  onCalendar: (target: CalendarRequest) => void;
  onMemories: (id?: string) => void;
  onKitchen: () => void;
  onActivity: (target: ActivityTarget) => void;
  renderTasks: (props: {
    tasks: Task[] | null;
    today: string;
    loading: boolean;
    onChanged: () => void;
  }) => ReactNode;
  renderUpcomingPayments: (props: {
    payments: Payment[];
    loading: boolean;
    onChanged: () => void;
  }) => ReactNode;
}) {
  const [month, setMonth] = useState(bucharestToday().slice(0, 7));
  const [data, setData] = useState<Overview<Task, Payment> | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState("");
  const [revision, setRevision] = useState(0);
  const sequence = useRef(0);
  const pending = useRef(false);
  const shortcuts = useRef<HTMLElement>(null);
  const paymentChanged = useRef(false);
  const endpoint = `/families/${family.id}/home?month=${month}`;
  useEffect(() => {
    const request = ++sequence.current;
    const controller = new AbortController();
    pending.current = true;
    setLoading(true);
    setError("");
    api<Overview<Task, Payment>>(endpoint, { signal: controller.signal })
      .then((result) => {
        if (request === sequence.current) setData(result);
      })
      .catch((e) => {
        if (controller.signal.aborted || request !== sequence.current) return;
        setData(null);
        if (e instanceof ApiError && [401, 403, 404, 419].includes(e.status)) {
          onExpired();
        }
        setError(
          e instanceof ApiError
            ? e.message
            : "Nu am putut încărca pagina Acasă. Verifică conexiunea și reîncearcă.",
        );
      })
      .finally(() => {
        if (request === sequence.current) {
          pending.current = false;
          setLoading(false);
        }
      });
    return () => {
      controller.abort();
      sequence.current++;
    };
  }, [endpoint, revision]);
  useEffect(() => {
    return observePageResume(document, window, () => {
      if (!pending.current) setRevision((value) => value + 1);
    });
  }, []);
  useEffect(() => {
    if (loading || !data || !paymentChanged.current) return;
    paymentChanged.current = false;
    if (data.upcoming_payments.length === 0) {
      shortcuts.current?.querySelector<HTMLButtonElement>("button")?.focus();
    }
  }, [data, loading]);
  const today = data?.today ?? bucharestToday();
  const featured =
    data?.memories.find((moment) => moment.photo_status === "ready") ??
    data?.memories[0];
  const dateOptions: Intl.DateTimeFormatOptions = {
    day: "numeric",
    month: "long",
  };
  return (
    <div className="home-page">
      <PageBackdrop image="home">
        <section className="page-heading">
          <div className="home-title">
            <h1>
              Acasă <Heart size={23} aria-hidden="true" />
            </h1>
            <p className="home-date">
              <Sun size={17} aria-hidden="true" />
              <time dateTime={today}>
                {dateLabel(today, {
                  weekday: "long",
                  day: "numeric",
                  month: "long",
                })}
              </time>
            </p>
          </div>
          <div className="page-heading-actions">
            <button
              className="icon-button"
              aria-label="Actualizează Acasă"
              disabled={loading}
              onClick={() => setRevision((value) => value + 1)}
            >
              <RefreshCw size={18} />
            </button>
            <button
              className="button primary"
              aria-label="Adaugă eveniment"
              disabled={family.role === "limited"}
              onClick={() => onCalendar({ id: "new", date: today })}
            >
              <Plus size={18} />
              <span>Adaugă eveniment</span>
            </button>
          </div>
        </section>
      </PageBackdrop>
      {error && (
        <div className="home-error">
          <p className="error" role="alert">
            {error}
          </p>
          <button
            className="button secondary"
            disabled={loading}
            onClick={() => setRevision((value) => value + 1)}
          >
            Reîncearcă
          </button>
        </div>
      )}
      {loading && !data && (
        <p className="home-loading" role="status">
          Se încarcă pagina Acasă…
        </p>
      )}
      <div className="home-layout">
        <div className="home-agenda">
          <section className="home-section" aria-labelledby="home-events">
            <div className="section-header">
              <h2 id="home-events">
                <span className="home-section-icon home-icon--calendar">
                  <CalendarDays size={20} aria-hidden="true" />
                </span>
                Evenimente apropiate
              </h2>
              <button
                className="text-button"
                aria-label="Deschide Calendar"
                onClick={() => onCalendar({ date: today, monthOnly: true })}
              >
                <ChevronRight size={18} aria-hidden="true" />
              </button>
            </div>
            {data &&
              (data.events.length ? (
                <ul className="home-rows">
                  {data.events.map((event) => (
                    <li key={event.id}>
                      <button
                        className="home-row"
                        onClick={() =>
                          onCalendar({ id: event.id, date: event.event_date })
                        }
                      >
                        <time
                          className="home-event-date"
                          dateTime={event.event_date}
                          aria-label={dateLabel(event.event_date, dateOptions)}
                        >
                          <strong>{Number(event.event_date.slice(-2))}</strong>
                          <span>
                            {dateLabel(event.event_date, {
                              month: "short",
                            }).replace(".", "")}
                          </span>
                        </time>
                        <span>
                          <strong>{event.title}</strong>
                          <small>
                            {event.all_day ? "Toată ziua" : event.time}
                          </small>
                        </span>
                        <span
                          className="home-access"
                          aria-label={visibilityLabel[event.visibility]}
                        >
                          {event.visibility === "private" ? (
                            <LockKeyhole size={15} />
                          ) : (
                            <Users size={15} />
                          )}
                        </span>
                      </button>
                    </li>
                  ))}
                </ul>
              ) : (
                <p className="home-empty">
                  Niciun eveniment în următoarele 30 de zile.
                </p>
              ))}
          </section>
          <section className="home-section" aria-labelledby="home-reminders">
            <div className="section-header">
              <h2 id="home-reminders">
                <span className="home-section-icon home-icon--reminder">
                  <Bell size={20} aria-hidden="true" />
                </span>
                Reminderele mele
              </h2>
            </div>
            {data &&
              (data.reminders.length ? (
                <ul className="home-rows">
                  {data.reminders.map((reminder) => (
                    <li key={reminder.id}>
                      <button
                        className="home-row"
                        onClick={() =>
                          onCalendar({
                            id: reminder.event_id,
                            date: reminder.event_date,
                          })
                        }
                      >
                        <span className="home-reminder-icon home-icon--reminder">
                          <Clock3 size={21} aria-hidden="true" />
                        </span>
                        <span>
                          <strong>{reminder.title}</strong>
                          <small>
                            {new Intl.DateTimeFormat("ro-RO", {
                              day: "numeric",
                              month: "long",
                              hour: "2-digit",
                              minute: "2-digit",
                              timeZone: "Europe/Bucharest",
                            }).format(new Date(reminder.due_at))}
                          </small>
                          <small>
                            {new Date(reminder.due_at).getTime() <= Date.now()
                              ? "În așteptare"
                              : "Programat"}
                          </small>
                        </span>
                      </button>
                    </li>
                  ))}
                </ul>
              ) : (
                <p className="home-empty">Niciun reminder programat.</p>
              ))}
          </section>
        </div>
        <section className="home-section home-tasks" aria-label="Sarcini">
          {renderTasks({
            tasks: data?.tasks ?? null,
            today,
            loading,
            onChanged: () => setRevision((value) => value + 1),
          })}
        </section>
        <HomeActivityCard items={data?.activity ?? null} onOpen={onActivity} />
        {!!data?.upcoming_payments.length && (
          <section
            className="home-section home-payments"
            aria-label="Plăți următoare"
          >
            {renderUpcomingPayments({
              payments: data.upcoming_payments,
              loading,
              onChanged: () => {
                paymentChanged.current = true;
                setRevision((value) => value + 1);
              },
            })}
          </section>
        )}
        <nav
          ref={shortcuts}
          className="home-shortcuts"
          aria-label="Acces rapid"
        >
          <button onClick={() => onCalendar({ date: today, monthOnly: true })}>
            <span className="home-shortcut-icon home-icon--calendar">
              <CalendarDays size={22} aria-hidden="true" />
            </span>
            <span>Calendar</span>
            <ChevronRight size={17} aria-hidden="true" />
          </button>
          <button onClick={onKitchen}>
            <span className="home-shortcut-icon home-icon--kitchen">
              <UtensilsCrossed size={22} aria-hidden="true" />
            </span>
            <span>Bucătărie</span>
            <ChevronRight size={17} aria-hidden="true" />
          </button>
          <button onClick={() => onMemories()}>
            <span className="home-shortcut-icon home-icon--memory">
              <BookHeart size={22} aria-hidden="true" />
            </span>
            <span>Amintiri</span>
            <ChevronRight size={17} aria-hidden="true" />
          </button>
        </nav>
        <section
          className="home-section home-memories"
          aria-labelledby="home-memories"
        >
          <div className="section-header">
            <h2 id="home-memories">
              <span className="home-section-icon home-icon--memory">
                <BookHeart size={20} aria-hidden="true" />
              </span>{" "}
              Amintiri
            </h2>
            <button
              className="text-button"
              onClick={() => onMemories()}
              aria-label="Vezi toate amintirile"
            >
              <span>Vezi toate</span>
              <ChevronRight size={17} />
            </button>
          </div>
          {featured ? (
            <>
              <button
                className="home-memory-feature"
                aria-label={`Deschide amintirea ${featured.title}`}
                onClick={() => onMemories(featured.id)}
              >
                {featured.photo_status !== null && (
                  <PrivatePhoto
                    src={`/api/v1/families/${family.id}/moments/${featured.id}/photo`}
                    status={featured.photo_status}
                    className="home-memory-photo"
                    placeholder={<BookHeart size={28} />}
                  />
                )}
                <div className="home-memory-copy">
                  <h3>{featured.title}</h3>
                  <div className="home-memory-meta">
                    <time dateTime={featured.occurred_on}>
                      {dateLabel(featured.occurred_on, {
                        day: "numeric",
                        month: "long",
                        year: "numeric",
                      })}
                    </time>
                    <span>{visibilityLabel[featured.visibility]}</span>
                  </div>
                  {featured.excerpt && <p>{featured.excerpt}</p>}
                </div>
              </button>
              <ul className="home-rows home-memory-notes">
                {data?.memories
                  .filter((moment) => moment.id !== featured.id)
                  .map((moment) => (
                    <li key={moment.id}>
                      <button
                        className="home-row"
                        onClick={() => onMemories(moment.id)}
                      >
                        {moment.kind === "note" ? (
                          <StickyNote size={21} aria-hidden="true" />
                        ) : (
                          <BookHeart size={21} aria-hidden="true" />
                        )}
                        <span>
                          <strong>{moment.title}</strong>
                          <small>
                            {dateLabel(moment.occurred_on, dateOptions)}
                          </small>
                        </span>
                        <ChevronRight size={17} aria-hidden="true" />
                      </button>
                    </li>
                  ))}
              </ul>
            </>
          ) : data ? (
            <div className="home-memory-empty">
              <p>Nicio amintire disponibilă.</p>
            </div>
          ) : (
            !error && (
              <div
                className="home-memory-skeleton skeleton"
                aria-label="Se încarcă amintirile"
              />
            )
          )}
        </section>
        <aside className="home-calendar" aria-label="Calendar">
          <CalendarMonth
            month={month}
            day={null}
            today={today}
            markedDates={data?.month === month ? data.days : []}
            onMonth={setMonth}
            onDay={(date) => onCalendar({ date })}
            onToday={() => onCalendar({ date: today })}
            onAllMonth={() =>
              onCalendar({ date: `${month}-01`, monthOnly: true })
            }
          />
        </aside>
      </div>
    </div>
  );
}
