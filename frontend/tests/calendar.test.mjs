import assert from "node:assert/strict";
import { readFile } from "node:fs/promises";
import test from "node:test";
import ts from "typescript";
const source = await readFile(
  new URL("../src/shared/calendarDates.ts", import.meta.url),
  "utf8",
);
const js = ts.transpileModule(source, {
  compilerOptions: {
    target: ts.ScriptTarget.ES2022,
    module: ts.ModuleKind.ES2022,
  },
}).outputText;
const { bucharestToday, monthDays, moveMonth, dateLabel, clampCalendarValue } =
  await import(
    `data:text/javascript;base64,${Buffer.from(js).toString("base64")}`
  );
const orderSource = await readFile(
  new URL("../src/modules/calendar/calendarTypes.ts", import.meta.url),
  "utf8",
);
const orderJs = ts.transpileModule(orderSource, {
  compilerOptions: {
    target: ts.ScriptTarget.ES2022,
    module: ts.ModuleKind.ES2022,
  },
}).outputText;
const { sortCalendarEvents } = await import(
  `data:text/javascript;base64,${Buffer.from(orderJs).toString("base64")}`
);

test("Calendar lists newest dates first across month and year boundaries without mutating input", () => {
  const events = Object.freeze([
    Object.freeze({
      id: "past",
      event_date: "2026-12-28",
      all_day: true,
      time: null,
    }),
    Object.freeze({
      id: "year",
      event_date: "2027-01-01",
      all_day: true,
      time: null,
    }),
    Object.freeze({
      id: "newest",
      event_date: "2027-01-03",
      all_day: true,
      time: null,
    }),
  ]);
  const ordered = sortCalendarEvents(events);
  assert.deepEqual(
    ordered.map((item) => item.id),
    ["newest", "year", "past"],
  );
  assert.deepEqual(
    events.map((item) => item.id),
    ["past", "year", "newest"],
  );
});

test("Calendar day lists keep all-day entries first then descending hours with stable ties", () => {
  const event = (id, time, source = null) => ({
    id,
    event_date: "2026-10-25",
    all_day: time === null,
    time,
    source,
  });
  assert.deepEqual(
    sortCalendarEvents([
      event("morning", "08:00"),
      event("equal-b", "18:30"),
      event("source", null, { type: "expense", virtual: true }),
      event("equal-a", "18:30"),
      event("noon", "12:00"),
    ]).map((item) => item.id),
    ["source", "equal-a", "equal-b", "noon", "morning"],
  );
});

test("Calendar date-only order stays civil through both DST changes and handles empty lists", () => {
  assert.deepEqual(sortCalendarEvents([]), []);
  const dates = ["2026-03-29", "2026-10-25", "2026-03-28", "2026-10-24"];
  assert.deepEqual(
    sortCalendarEvents(
      dates.map((event_date) => ({
        id: event_date,
        event_date,
        all_day: true,
        time: null,
      })),
    ).map((item) => item.event_date),
    ["2026-10-25", "2026-10-24", "2026-03-29", "2026-03-28"],
  );
});
test("C1: today is determined in Bucharest at UTC day boundaries", () => {
  assert.equal(bucharestToday(new Date("2026-10-20T22:30:00Z")), "2026-10-21");
  assert.equal(bucharestToday(new Date("2026-12-20T21:30:00Z")), "2026-12-20");
});
test("C1: Monday-first grid includes leap day and the first and last dates", () => {
  const days = monthDays("2028-02");
  assert.equal(days[0], null);
  assert.equal(days[1], "2028-02-01");
  assert.equal(days.at(-1), "2028-02-29");
  assert.equal(monthDays("2026-02").filter(Boolean).length, 28);
});
test("C1: month navigation crosses year boundaries without shifting a date-only value", () => {
  assert.equal(moveMonth("2026-12", 1), "2027-01");
  assert.equal(moveMonth("2026-01", -1), "2025-12");
  assert.match(dateLabel("2026-03-29"), /29/);
  assert.match(dateLabel("2026-10-25"), /25/);
});

test("DP1: selecting another year keeps a birth-date month inside its allowed range", () => {
  assert.equal(clampCalendarValue("2026-12", "1900-01", "2026-10"), "2026-10");
  assert.equal(clampCalendarValue("2025-12", "1900-01", "2026-10"), "2025-12");
  assert.equal(clampCalendarValue("1899-12", "1900-01", "2100-12"), "1900-01");
});

test("DP1: a newly opened calendar focuses a selectable date at both range boundaries", () => {
  assert.equal(
    clampCalendarValue("2026-10-24", "2027-01-01", "2100-12-31"),
    "2027-01-01",
  );
  assert.equal(
    clampCalendarValue("2026-10-24", "1900-01-01", "2025-12-31"),
    "2025-12-31",
  );
  assert.equal(monthDays("2100-02").filter(Boolean).length, 28);
  assert.equal(moveMonth("2099-12", 1), "2100-01");
});
