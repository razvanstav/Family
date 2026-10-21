import assert from "node:assert/strict";
import { readFile } from "node:fs/promises";
import test from "node:test";
import ts from "typescript";
const source = await readFile(
  new URL("../src/modules/calendar/calendarDates.ts", import.meta.url),
  "utf8",
);
const js = ts.transpileModule(source, {
  compilerOptions: {
    target: ts.ScriptTarget.ES2022,
    module: ts.ModuleKind.ES2022,
  },
}).outputText;
const { bucharestToday, monthDays, moveMonth, dateLabel } = await import(
  `data:text/javascript;base64,${Buffer.from(js).toString("base64")}`
);
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
