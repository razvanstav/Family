import { useEffect, useRef, useState } from "react";
import {
  ArrowRight,
  Bell,
  CalendarDays,
  Clock3,
  LockKeyhole,
  Plus,
  RefreshCw,
  Users,
  BookHeart,
  UtensilsCrossed,
  FolderHeart,
  ChevronRight,
  StickyNote,
} from "lucide-react";
import {
  api,
  ApiError,
  visibilityLabel,
  type CalendarRequest,
  type DocumentRequest,
  type DocumentSummary,
  personalDocumentCategories,
  type Family,
} from "../../shared/api";
import { DocumentCard, DocumentCategories } from "../../shared/DocumentCard";
import { CalendarMonth } from "../../shared/CalendarMonth";
import { bucharestToday, dateLabel } from "../../shared/calendarDates";
import { PrivatePhoto, type PhotoStatus } from "../../shared/PrivatePhoto";
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
type Overview = {
  today: string;
  month: string;
  days: string[];
  documents: DocumentSummary[];
  memories: MemorySummary[];
  events: EventSummary[];
  reminders: ReminderSummary[];
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

export function Home({
  family,
  onExpired,
  onDocuments,
  onCalendar,
  onMemories,
  onKitchen,
}: {
  family: Family;
  onExpired: () => void;
  onDocuments: (request: DocumentRequest) => void;
  onCalendar: (target: CalendarRequest) => void;
  onMemories: (id?: string) => void;
  onKitchen: () => void;
}) {
  const [month, setMonth] = useState(bucharestToday().slice(0, 7));
  const [data, setData] = useState<Overview | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState("");
  const [revision, setRevision] = useState(0);
  const sequence = useRef(0);
  const endpoint = `/families/${family.id}/home?month=${month}`;
  useEffect(() => {
    const request = ++sequence.current;
    setLoading(true);
    setError("");
    api<Overview>(endpoint)
      .then((result) => {
        if (request === sequence.current) setData(result);
      })
      .catch((e) => {
        if (request !== sequence.current) return;
        setData(null);
        if (e instanceof ApiError && [401, 403, 404, 419].includes(e.status)) {
          setData(null);
          onExpired();
        }
        setError(
          e instanceof ApiError
            ? e.message
            : "Nu am putut încărca pagina Acasă. Verifică conexiunea și reîncearcă.",
        );
      })
      .finally(() => {
        if (request === sequence.current) setLoading(false);
      });
    return () => {
      sequence.current++;
    };
  }, [endpoint, revision]);
  useEffect(() => {
    const timer = window.setInterval(() => {
      if (document.visibilityState === "visible")
        setRevision((value) => value + 1);
    }, 60000);
    return () => window.clearInterval(timer);
  }, []);
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
      <section className="page-heading">
        <h1>Acasă</h1>
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
            aria-label="Adaugă document"
            disabled={family.role === "limited"}
            onClick={() => onDocuments({ upload: true })}
          >
            <Plus size={18} />
            <span>Adaugă document</span>
          </button>
        </div>
      </section>
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
      <nav className="home-shortcuts" aria-label="Acces rapid">
        <button onClick={() => onMemories()}>
          <BookHeart size={24} aria-hidden="true" />
          <span>Amintiri</span>
        </button>
        <button onClick={onKitchen}>
          <UtensilsCrossed size={24} aria-hidden="true" />
          <span>Bucătărie</span>
        </button>
        <button onClick={() => onDocuments({})}>
          <FolderHeart size={24} aria-hidden="true" />
          <span>Documente</span>
        </button>
        <button onClick={() => onCalendar({ date: today, monthOnly: true })}>
          <CalendarDays size={24} aria-hidden="true" />
          <span>Calendar</span>
        </button>
      </nav>
      <div className="home-layout">
        <div className="home-main">
          <section
            className="home-section home-memories"
            aria-labelledby="home-memories"
          >
            <div className="section-header">
              <h2 id="home-memories">
                <BookHeart size={20} aria-hidden="true" /> Din amintirile
                noastre
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
                    <h3>{featured.title}</h3>
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
                <BookHeart size={28} aria-hidden="true" />
                <p>Nicio amintire disponibilă.</p>
                <button className="text-button" onClick={() => onMemories()}>
                  Deschide Amintiri <ArrowRight size={16} />
                </button>
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
          <section className="home-section" aria-labelledby="home-categories">
            <div className="section-header">
              <h2 id="home-categories">Categorii de documente</h2>
              <button
                className="text-button"
                aria-label="Deschide Documente"
                onClick={() => onDocuments({})}
              >
                <ArrowRight size={18} />
              </button>
            </div>
            <DocumentCategories
              onSelect={(category) => onDocuments({ category })}
            />
          </section>
          <section className="home-section" aria-labelledby="home-documents">
            <div className="section-header">
              <h2 id="home-documents">Documente recente</h2>
              <button
                className="text-button"
                aria-label="Vezi toate documentele"
                onClick={() => onDocuments({})}
              >
                <span>Vezi toate</span>
                <ChevronRight size={17} />
              </button>
            </div>
            {data ? (
              data.documents.length ? (
                <div className="document-grid home-document-grid">
                  {data.documents.map((document) => (
                    <DocumentCard
                      key={document.id}
                      document={document}
                      onOpen={(id) =>
                        onDocuments({
                          id,
                          ...(personalDocumentCategories.includes(
                            document.category,
                          )
                            ? {
                                memberId:
                                  document.subject_membership_id ?? undefined,
                                ownerId: document.owner_id,
                              }
                            : {}),
                        })
                      }
                    />
                  ))}
                </div>
              ) : (
                <p className="home-empty">Niciun document disponibil.</p>
              )
            ) : (
              !error && (
                <div
                  className="document-grid home-document-grid"
                  aria-hidden="true"
                >
                  {[0, 1, 2, 3].map((index) => (
                    <div className="skeleton" key={index} />
                  ))}
                </div>
              )
            )}
          </section>
        </div>
        <aside className="home-side" aria-label="Calendar și remindere">
          <section className="home-section" aria-labelledby="home-events">
            <div className="section-header">
              <h2 id="home-events">Evenimente apropiate</h2>
              <button
                className="icon-button"
                aria-label="Adaugă eveniment"
                disabled={family.role === "limited"}
                onClick={() => onCalendar({ id: "new", date: today })}
              >
                <Plus size={18} />
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
                        <span className="home-event-icon">
                          <CalendarDays size={20} aria-hidden="true" />
                        </span>
                        <span>
                          <strong>{event.title}</strong>
                          <small>
                            {dateLabel(event.event_date, dateOptions)} ·{" "}
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
              <h2 id="home-reminders">Reminderele mele</h2>
              <Bell size={18} aria-hidden="true" />
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
                        <Clock3 size={19} />
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
