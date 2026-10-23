import { dateLabel } from "./calendarDates";
import "./datePicker.css";

const months = Array.from({ length: 12 }, (_, index) => {
  const value = String(index + 1).padStart(2, "0");
  const name = dateLabel(`2026-${value}-01`, { month: "long" });
  return { value, name: name[0].toLocaleUpperCase("ro") + name.slice(1) };
});
const years = Array.from({ length: 200 }, (_, index) => String(1900 + index));

export function MonthSelect({
  month,
  onChange,
}: {
  month: string;
  onChange: (month: string) => void;
}) {
  const [year, number] = month.split("-");
  return (
    <div className="month-selects">
      <select
        aria-label="Luna"
        value={number}
        onChange={(event) => onChange(`${year}-${event.target.value}`)}
      >
        {months.map((item) => (
          <option value={item.value} key={item.value}>
            {item.name}
          </option>
        ))}
      </select>
      <select
        aria-label="Anul"
        value={year}
        onChange={(event) => onChange(`${event.target.value}-${number}`)}
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
