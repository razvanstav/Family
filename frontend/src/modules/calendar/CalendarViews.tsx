import {
  CalendarDays,
  ChevronLeft,
  ChevronRight,
  Clock3,
  LockKeyhole,
  Users,
} from "lucide-react";
import { dateLabel } from "../../shared/calendarDates";
import { visibilityLabel } from "../../shared/api";
import type { CalendarItem } from "./calendarTypes";

export type CalendarView = "month" | "week" | "agenda";

export function moveDay(day: string, delta: number): string {
  const date = new Date(`${day}T12:00:00Z`);
  date.setUTCDate(date.getUTCDate() + delta);
  return date.toISOString().slice(0, 10);
}

export function weekDays(day: string): string[] {
  const weekday = new Date(`${day}T12:00:00Z`).getUTCDay();
  return Array.from({ length: 7 }, (_, index) =>
    moveDay(day, index - ((weekday + 6) % 7)),
  );
}

export function CalendarWeek({
  day,
  today,
  dates,
  markedDates,
  onDay,
}: {
  day: string;
  today: string;
  dates: string[];
  markedDates: Set<string>;
  onDay: (day: string) => void;
}) {
  function changeWeek(delta: number) {
    const next = moveDay(day, delta);
    onDay(
      next < "1900-01-01"
        ? "1900-01-01"
        : next > "2100-12-31"
          ? "2100-12-31"
          : next,
    );
  }
  return (
    <section className="calendar-week" aria-label="Săptămâna selectată">
      <div className="calendar-week-heading">
        <button
          className="icon-button"
          aria-label="Săptămâna precedentă"
          disabled={dates[0] <= "1900-01-01"}
          onClick={() => changeWeek(-7)}
        >
          <ChevronLeft size={19} />
        </button>
        <h2>
          {dateLabel(dates[0] < "1900-01-01" ? "1900-01-01" : dates[0], {
            day: "numeric",
            month: "short",
          })}{" "}
          –{" "}
          {dateLabel(dates[6] > "2100-12-31" ? "2100-12-31" : dates[6], {
            day: "numeric",
            month: "short",
            year: "numeric",
          })}
        </h2>
        <button
          className="icon-button"
          aria-label="Săptămâna următoare"
          disabled={dates[6] >= "2100-12-31"}
          onClick={() => changeWeek(7)}
        >
          <ChevronRight size={19} />
        </button>
      </div>
      <div className="calendar-week-strip">
        {dates.map((date, index) =>
          date < "1900-01-01" || date > "2100-12-31" ? (
            <span key={date} />
          ) : (
            <button
              key={date}
              aria-label={dateLabel(date)}
              aria-pressed={date === day}
              aria-current={date === today ? "date" : undefined}
              onClick={() => onDay(date)}
            >
              <span>{["Lu", "Ma", "Mi", "Jo", "Vi", "Sâ", "Du"][index]}</span>
              <strong>{Number(date.slice(-2))}</strong>
              <i
                className={markedDates.has(date) ? "has-events" : ""}
                aria-hidden="true"
              />
            </button>
          ),
        )}
      </div>
      <button className="text-button" onClick={() => onDay(today)}>
        Astăzi
      </button>
    </section>
  );
}

export function CalendarEvents({
  items,
  timeline,
  onOpen,
}: {
  items: CalendarItem[];
  timeline: boolean;
  onOpen: (id: string) => void;
}) {
  return (
    <div className={`calendar-events${timeline ? " calendar-timeline" : ""}`}>
      {items.map((item) => (
        <button
          key={item.id}
          className={`calendar-event calendar-event--${item.visibility}`}
          onClick={() => onOpen(item.id)}
          aria-label={`${item.title} · ${dateLabel(item.event_date)} · ${item.all_day ? "Toată ziua" : item.time} · ${visibilityLabel[item.visibility]}`}
        >
          {timeline ? (
            <span className="calendar-time">
              {item.all_day ? (
                <>
                  Toată
                  <br />
                  ziua
                </>
              ) : (
                item.time
              )}
            </span>
          ) : (
            <span className="calendar-date">
              <strong>{Number(item.event_date.slice(-2))}</strong>
              <span>{dateLabel(item.event_date, { weekday: "short" })}</span>
            </span>
          )}
          <span className="calendar-event-card">
            <span className="calendar-event-symbol" aria-hidden="true">
              {item.visibility === "family" ? (
                <Users size={21} />
              ) : (
                <CalendarDays size={21} />
              )}
            </span>
            <span className="calendar-event-info">
              <strong>{item.title}</strong>
              {!timeline && (
                <span>
                  <Clock3 size={13} aria-hidden="true" />
                  {item.all_day ? "Toată ziua" : item.time}
                </span>
              )}
              <span className="calendar-event-meta">
                <span className="calendar-event-owner">{item.owner_name}</span>
                <span className="calendar-event-access">
                  {item.visibility === "family" ? (
                    <Users size={12} aria-hidden="true" />
                  ) : (
                    <LockKeyhole size={12} aria-hidden="true" />
                  )}
                  {visibilityLabel[item.visibility]}
                </span>
              </span>
            </span>
            <ChevronRight
              size={16}
              className="calendar-event-chevron"
              aria-hidden="true"
            />
          </span>
        </button>
      ))}
    </div>
  );
}
