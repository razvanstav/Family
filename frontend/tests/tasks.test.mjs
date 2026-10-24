import assert from "node:assert/strict";
import { readFile } from "node:fs/promises";
import test from "node:test";
import ts from "typescript";

const compilerOptions = {
  target: ts.ScriptTarget.ES2022,
  module: ts.ModuleKind.ES2022,
};
const calendarDates = ts.transpileModule(
  await readFile(
    new URL("../src/shared/calendarDates.ts", import.meta.url),
    "utf8",
  ),
  { compilerOptions },
).outputText;
const calendarDatesUrl = `data:text/javascript;base64,${Buffer.from(calendarDates).toString("base64")}`;
const source = await readFile(
  new URL("../src/modules/tasks/taskData.ts", import.meta.url),
  "utf8",
);
const js = ts
  .transpileModule(source, { compilerOptions })
  .outputText.replace(
    '"../../shared/calendarDates"',
    JSON.stringify(calendarDatesUrl),
  );
const { taskCompletionMessage } = await import(
  `data:text/javascript;base64,${Buffer.from(js).toString("base64")}`
);

test("recurring completion retains next-deadline and skipped-repetition notices", () => {
  assert.equal(
    taskCompletionMessage({
      completed_at: "2027-02-28T10:00:00Z",
      recurrence_notice:
        "Sarcina a fost finalizată și următoarea sarcină a fost creată.",
      next_due_on: "2027-03-31",
    }),
    "Sarcina a fost finalizată și următoarea sarcină a fost creată. Următorul termen: 31 martie 2027.",
  );
  for (const notice of [
    "Sarcina a fost finalizată. Următorul termen: 31 martie 2027.",
    "Sarcina a fost finalizată. Următoarea sarcină nu a fost creată: responsabilul nu mai este activ.",
  ])
    assert.equal(
      taskCompletionMessage({
        completed_at: "2027-02-28T10:00:00Z",
        recurrence_notice: notice,
        next_due_on: null,
      }),
      notice,
    );
});

test("normal completion and reopening retain their action feedback", () => {
  assert.equal(
    taskCompletionMessage({ completed_at: "2027-02-28T10:00:00Z" }),
    "Sarcina a fost finalizată.",
  );
  assert.equal(
    taskCompletionMessage({
      completed_at: "2027-02-28T10:00:00Z",
      recurrence_notice: "  ",
    }),
    "Sarcina a fost finalizată.",
  );
  assert.equal(
    taskCompletionMessage({
      completed_at: null,
      recurrence_notice: "Următoarea sarcină există deja.",
      next_due_on: "2027-03-31",
    }),
    "Sarcina a fost redeschisă.",
  );
});
