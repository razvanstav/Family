import { clampCalendarValue, dateLabel } from "./calendarDates";
import { SelectMenu } from "./SelectMenu";
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
      <SelectMenu
        label="Luna"
        value={number}
        onChange={(value) =>
          onChange(clampCalendarValue(`${year}-${value}`, min, max))
        }
        options={months.map((item) => ({
          value: item.value,
          label: item.name,
          disabled:
            `${year}-${item.value}` < min || `${year}-${item.value}` > max,
        }))}
      />
      <SelectMenu
        label="Anul"
        value={year}
        onChange={(value) =>
          onChange(clampCalendarValue(`${value}-${number}`, min, max))
        }
        options={years.map((item) => ({ value: item, label: item }))}
      />
    </div>
  );
}
