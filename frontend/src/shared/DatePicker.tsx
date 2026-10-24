import { useEffect, useId, useRef, useState, type KeyboardEvent } from "react";
import { CalendarDays, ChevronLeft, ChevronRight } from "lucide-react";
import {
  bucharestToday,
  clampCalendarValue,
  dateLabel,
  monthDays,
  moveMonth,
} from "./calendarDates";
import { MonthSelect } from "./MonthSelect";
import "./datePicker.css";

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
  emptyLabel = "Fără termen",
  min = "1900-01-01",
  max = "2099-12-31",
  disabled = false,
  autoFocus = false,
  required = !optional,
  hideLabel = false,
}: {
  value: string;
  label?: string;
  name?: string;
  dialogLabel?: string;
  optional?: boolean;
  emptyLabel?: string;
  min?: string;
  max?: string;
  disabled?: boolean;
  autoFocus?: boolean;
  required?: boolean;
  hideLabel?: boolean;
  onChange: (date: string) => void;
}) {
  const id = useId();
  const trigger = useRef<HTMLButtonElement>(null);
  const popup = useRef<HTMLDivElement>(null);
  const validationInput = useRef<HTMLInputElement>(null);
  const [invalid, setInvalid] = useState(false);
  const [open, setOpen] = useState(false);
  const [month, setMonth] = useState((value || bucharestToday()).slice(0, 7));
  const [focused, setFocused] = useState(value || bucharestToday());
  const [position, setPosition] = useState({ left: 0, top: 0, width: 0 });
  const today = bucharestToday();
  const boundsError =
    value && (value < min || value > max)
      ? `Alege o dată între ${dateLabel(min)} și ${dateLabel(max)}.`
      : "";
  const validationMessage =
    boundsError || (required && !value ? "Alege data." : "");
  useEffect(() => {
    validationInput.current?.setCustomValidity(boundsError);
    if (!validationMessage) setInvalid(false);
  }, [boundsError, validationMessage]);
  useEffect(() => {
    if (disabled) setOpen(false);
  }, [disabled]);
  function close(restoreFocus = true) {
    setOpen(false);
    if (restoreFocus) trigger.current?.focus();
  }
  function choose(date: string) {
    if (disabled || (date ? date < min || date > max : !optional)) return;
    onChange(date);
    setInvalid(false);
    close();
  }
  function changeMonth(next: string) {
    next = clampCalendarValue(next, min.slice(0, 7), max.slice(0, 7));
    const days = monthDays(next).filter(
      (date): date is string => date !== null,
    );
    setMonth(next);
    setFocused(
      clampCalendarValue(
        days[Math.min(Number(focused.slice(-2)), days.length) - 1],
        min,
        max,
      ),
    );
  }
  function placeCalendar() {
    const bounds = trigger.current!.getBoundingClientRect();
    const viewport = window.visualViewport;
    const offsetLeft = viewport?.offsetLeft ?? 0;
    const height = viewport
      ? Math.min(window.innerHeight, Math.max(0, viewport.height))
      : window.innerHeight;
    const offsetTop = viewport
      ? Math.abs(viewport.scale - 1) > 0.01
        ? viewport.offsetTop
        : Math.min(
            Math.max(0, viewport.offsetTop),
            Math.max(0, window.innerHeight - height),
          )
      : 0;
    const availableWidth = Math.min(
      viewport?.width ?? window.innerWidth,
      document.documentElement.clientWidth,
      document.documentElement.getBoundingClientRect().width,
    );
    const width = Math.min(availableWidth, 380);
    setPosition({
      left:
        offsetLeft +
        Math.max(0, Math.min(bounds.left - offsetLeft, availableWidth - width)),
      top:
        offsetTop +
        Math.max(8, Math.min(bounds.bottom - offsetTop + 8, height - 440)),
      width,
    });
  }
  function toggle() {
    if (disabled) return;
    if (open) {
      close();
      return;
    }
    placeCalendar();
    const initialDate = clampCalendarValue(value || bucharestToday(), min, max);
    setMonth(initialDate.slice(0, 7));
    setFocused(initialDate);
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
    window.visualViewport?.addEventListener("resize", placeCalendar);
    window.visualViewport?.addEventListener("scroll", placeCalendar);
    return () => {
      document.removeEventListener("pointerdown", outside, true);
      window.removeEventListener("resize", placeCalendar);
      window.visualViewport?.removeEventListener("resize", placeCalendar);
      window.visualViewport?.removeEventListener("scroll", placeCalendar);
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
    if (next < min || next > max) return;
    setMonth(next.slice(0, 7));
    setFocused(next);
  }
  return (
    <div className="field date-picker-field">
      <label id={`${id}-label`} htmlFor={`${id}-trigger`} hidden={hideLabel}>
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
        aria-describedby={invalid ? `${id}-error` : undefined}
        aria-invalid={invalid || undefined}
        disabled={disabled}
        autoFocus={autoFocus}
        onClick={toggle}
      >
        <span id={`${id}-value`}>
          {value
            ? dateLabel(value, {
                day: "numeric",
                month: "short",
                year: "numeric",
              })
            : emptyLabel}
        </span>
        <CalendarDays size={18} aria-hidden="true" />
      </button>
      <input
        ref={validationInput}
        type="text"
        className="date-picker-validation"
        name={name}
        value={value}
        onChange={() => {}}
        required={required}
        disabled={disabled}
        tabIndex={-1}
        aria-hidden="true"
        onInvalid={(event) => {
          event.preventDefault();
          setInvalid(true);
          trigger.current?.focus();
        }}
      />
      {invalid && (
        <small id={`${id}-error`} className="error" role="alert">
          {validationMessage}
        </small>
      )}
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
              disabled={month <= min.slice(0, 7)}
              onClick={() => changeMonth(moveMonth(month, -1))}
            >
              <ChevronLeft size={19} />
            </button>
            <MonthSelect
              month={month}
              onChange={changeMonth}
              min={min.slice(0, 7)}
              max={max.slice(0, 7)}
            />
            <button
              className="icon-button"
              type="button"
              aria-label="Luna următoare"
              disabled={month >= max.slice(0, 7)}
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
                  disabled={date < min || date > max}
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
                {emptyLabel}
              </button>
            )}
            <button
              className="text-button"
              type="button"
              disabled={today < min || today > max}
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
