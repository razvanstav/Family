import { useEffect, useId, useRef, useState, type KeyboardEvent } from "react";
import { CalendarDays, ChevronLeft, ChevronRight } from "lucide-react";
import {
  bucharestToday,
  dateLabel,
  monthDays,
  moveMonth,
} from "./calendarDates";
import { MonthSelect } from "./MonthSelect";
import "./datePicker.css";

const minimum = "1900-01-01";
const maximum = "2099-12-31";
function shiftedDay(date: string, days: number) {
  const shifted = new Date(`${date}T12:00:00Z`);
  shifted.setUTCDate(shifted.getUTCDate() + days);
  return shifted.toISOString().slice(0, 10);
}

export function DatePicker({
  value,
  onChange,
  label = "Data",
  name = "date",
  dialogLabel = "Alege data",
  optional = false,
}: {
  value: string;
  label?: string;
  name?: string;
  dialogLabel?: string;
  optional?: boolean;
  onChange: (date: string) => void;
}) {
  const id = useId();
  const trigger = useRef<HTMLButtonElement>(null);
  const popup = useRef<HTMLDivElement>(null);
  const [open, setOpen] = useState(false);
  const [month, setMonth] = useState((value || bucharestToday()).slice(0, 7));
  const [focused, setFocused] = useState(value || bucharestToday());
  const [position, setPosition] = useState({ left: 0, top: 0, width: 0 });
  const today = bucharestToday();
  function close(restoreFocus = true) {
    setOpen(false);
    if (restoreFocus) trigger.current?.focus();
  }
  function choose(date: string) {
    onChange(date);
    close();
  }
  function changeMonth(next: string) {
    const days = monthDays(next).filter(
      (date): date is string => date !== null,
    );
    setMonth(next);
    setFocused(days[Math.min(Number(focused.slice(-2)), days.length) - 1]);
  }
  function placeCalendar() {
    const bounds = trigger.current!.getBoundingClientRect();
    const availableWidth = Math.min(
      document.documentElement.clientWidth,
      document.documentElement.getBoundingClientRect().width,
    );
    const width = Math.min(availableWidth, 380);
    setPosition({
      left: Math.max(0, Math.min(bounds.left, availableWidth - width)),
      top: Math.max(8, Math.min(bounds.bottom + 8, window.innerHeight - 440)),
      width,
    });
  }
  function toggle() {
    if (open) {
      close();
      return;
    }
    placeCalendar();
    setMonth((value || bucharestToday()).slice(0, 7));
    setFocused(value || bucharestToday());
    setOpen(true);
  }
  useEffect(() => {
    if (!open || !popup.current) return;
    const panel = popup.current;
    panel.showPopover();
    const outside = (event: PointerEvent) => {
      if (
        !panel.contains(event.target as Node) &&
        !trigger.current?.contains(event.target as Node)
      )
        close(false);
    };
    document.addEventListener("pointerdown", outside, true);
    window.addEventListener("resize", placeCalendar);
    return () => {
      document.removeEventListener("pointerdown", outside, true);
      window.removeEventListener("resize", placeCalendar);
      if (panel.matches(":popover-open")) panel.hidePopover();
    };
  }, [open]);
  useEffect(() => {
    if (open)
      popup.current
        ?.querySelector<HTMLButtonElement>(`button[data-date="${focused}"]`)
        ?.focus();
  }, [open, focused]);
  function onDayKey(event: KeyboardEvent<HTMLButtonElement>, date: string) {
    const delta = (
      { ArrowLeft: -1, ArrowRight: 1, ArrowUp: -7, ArrowDown: 7 } as Record<
        string,
        number
      >
    )[event.key];
    if (delta === undefined) return;
    event.preventDefault();
    const next = shiftedDay(date, delta);
    if (next < minimum || next > maximum) return;
    setMonth(next.slice(0, 7));
    setFocused(next);
  }
  return (
    <div className="field date-picker-field">
      <label id={`${id}-label`} htmlFor={`${id}-trigger`}>
        {label}
      </label>
      <button
        ref={trigger}
        id={`${id}-trigger`}
        type="button"
        className="date-picker-trigger"
        aria-labelledby={`${id}-label ${id}-value`}
        aria-haspopup="dialog"
        aria-expanded={open}
        aria-controls={open ? id : undefined}
        onClick={toggle}
      >
        <span id={`${id}-value`}>
          {value
            ? dateLabel(value, {
                day: "numeric",
                month: "short",
                year: "numeric",
              })
            : "Fără termen"}
        </span>
        <CalendarDays size={18} aria-hidden="true" />
      </button>
      <input type="hidden" name={name} value={value} />
      {open && (
        <div
          ref={popup}
          popover="manual"
          id={id}
          role="dialog"
          aria-label={dialogLabel}
          className="date-picker-calendar"
          style={position}
          onKeyDown={(event) => {
            if (event.key === "Escape") {
              event.preventDefault();
              event.stopPropagation();
              close();
            }
            if (event.key === "Tab") {
              const controls = Array.from(
                popup.current!.querySelectorAll<HTMLElement>(
                  'button:not([disabled]):not([tabindex="-1"]), select',
                ),
              );
              const first = controls[0];
              const last = controls.at(-1);
              if (event.shiftKey && document.activeElement === first) {
                event.preventDefault();
                last?.focus();
              } else if (!event.shiftKey && document.activeElement === last) {
                event.preventDefault();
                first?.focus();
              }
            }
          }}
        >
          <div className="date-picker-calendar-heading">
            <button
              className="icon-button"
              type="button"
              aria-label="Luna precedentă"
              disabled={month === "1900-01"}
              onClick={() => changeMonth(moveMonth(month, -1))}
            >
              <ChevronLeft size={19} />
            </button>
            <MonthSelect month={month} onChange={changeMonth} />
            <button
              className="icon-button"
              type="button"
              aria-label="Luna următoare"
              disabled={month === "2099-12"}
              onClick={() => changeMonth(moveMonth(month, 1))}
            >
              <ChevronRight size={19} />
            </button>
          </div>
          <div className="date-picker-weekdays" aria-hidden="true">
            {["Lu", "Ma", "Mi", "Jo", "Vi", "Sâ", "Du"].map((day) => (
              <span key={day}>{day}</span>
            ))}
          </div>
          <div className="date-picker-days">
            {monthDays(month).map((date, index) =>
              date ? (
                <button
                  key={date}
                  data-date={date}
                  type="button"
                  aria-label={dateLabel(date)}
                  aria-pressed={date === value}
                  aria-current={date === today ? "date" : undefined}
                  tabIndex={date === focused ? 0 : -1}
                  onFocus={() => setFocused(date)}
                  onKeyDown={(event) => onDayKey(event, date)}
                  onClick={() => choose(date)}
                >
                  {Number(date.slice(-2))}
                </button>
              ) : (
                <span key={index} />
              ),
            )}
          </div>
          <div className="date-picker-calendar-actions">
            {optional && value && (
              <button
                className="text-button"
                type="button"
                onClick={() => choose("")}
              >
                Fără termen
              </button>
            )}
            <button
              className="text-button"
              type="button"
              onClick={() => choose(today)}
            >
              Astăzi
            </button>
            <button
              className="text-button"
              type="button"
              onClick={() => close()}
            >
              Închide calendarul
            </button>
          </div>
        </div>
      )}
    </div>
  );
}
