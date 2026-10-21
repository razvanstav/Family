import { ChevronLeft, ChevronRight } from "lucide-react";
import { dateLabel, monthDays, moveMonth } from "./calendarDates";
import "./calendarMonth.css";

export function CalendarMonth({
  month,
  day,
  today,
  markedDates,
  onMonth,
  onDay,
  onToday,
  onAllMonth,
}: {
  month: string;
  day: string | null;
  today: string;
  markedDates: string[];
  onMonth: (month: string) => void;
  onDay: (day: string) => void;
  onToday: () => void;
  onAllMonth: () => void;
}) {
  return (
    <section className="calendar-month" aria-label="Calendar lunar">
      <div className="calendar-month-heading">
        <h2>{dateLabel(`${month}-01`, { month: "long", year: "numeric" })}</h2>
        <div>
          <button
            className="icon-button"
            aria-label="Luna precedentă"
            disabled={month === "1900-01"}
            onClick={() => onMonth(moveMonth(month, -1))}
          >
            <ChevronLeft size={20} />
          </button>
          <button
            className="icon-button"
            aria-label="Luna următoare"
            disabled={month === "2100-12"}
            onClick={() => onMonth(moveMonth(month, 1))}
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
              onClick={() => onDay(date)}
            >
              <span>{Number(date.slice(-2))}</span>
              {markedDates.includes(date) && (
                <span className="calendar-dot" aria-label="Are evenimente" />
              )}
            </button>
          ) : (
            <span key={`blank-${i}`} />
          ),
        )}
      </div>
      <div className="calendar-month-footer">
        <button className="text-button" onClick={onToday}>
          Astăzi
        </button>
        <button
          className="text-button"
          aria-pressed={day === null}
          onClick={onAllMonth}
        >
          Toată luna
        </button>
      </div>
    </section>
  );
}
