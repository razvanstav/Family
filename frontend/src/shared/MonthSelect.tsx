import { clampCalendarValue, dateLabel } from "./calendarDates";
import "./datePicker.css";

const months = Array.from({ length: 12 }, (_, index) => {
  const value = String(index + 1).padStart(2, "0");
  const name = dateLabel(`2026-${value}-01`, { month: "long" });
  return { value, name: name[0].toLocaleUpperCase("ro") + name.slice(1) };
});
export function MonthSelect({
  month,
  onChange,
  min = "1900-01",
  max = "2099-12",
}: {
  month: string;
  onChange: (month: string) => void;
  min?: string;
  max?: string;
}) {
  const [year, number] = month.split("-");
  const firstYear = Number(min.slice(0, 4));
  const years = Array.from(
    { length: Number(max.slice(0, 4)) - firstYear + 1 },
    (_, index) => String(firstYear + index),
  );
  return (
    <div className="month-selects">
      <select
        aria-label="Luna"
        value={number}
        onChange={(event) =>
          onChange(
            clampCalendarValue(`${year}-${event.target.value}`, min, max),
          )
        }
      >
        {months.map((item) => (
          <option
            value={item.value}
            key={item.value}
            disabled={
              `${year}-${item.value}` < min || `${year}-${item.value}` > max
            }
          >
            {item.name}
          </option>
        ))}
      </select>
      <select
        aria-label="Anul"
        value={year}
        onChange={(event) =>
          onChange(
            clampCalendarValue(`${event.target.value}-${number}`, min, max),
          )
        }
      >
        {years.map((item) => (
          <option value={item} key={item}>
            {item}
          </option>
        ))}
      </select>
    </div>
  );
}
