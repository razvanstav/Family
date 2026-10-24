import assert from "node:assert/strict";
import { readFile } from "node:fs/promises";
import test from "node:test";
import ts from "typescript";

const source = await readFile(
  new URL("../src/modules/pets/petFormData.ts", import.meta.url),
  "utf8",
);
const js = ts.transpileModule(source, {
  compilerOptions: {
    target: ts.ScriptTarget.ES2022,
    module: ts.ModuleKind.ES2022,
  },
}).outputText;
const { petEntryPayload } = await import(
  `data:text/javascript;base64,${Buffer.from(js).toString("base64")}`
);
const input = {
  kind: "weight",
  title: " Cântărire ",
  date: "2026-10-24",
  weight: " 12,345 ",
  cost: " 0,10 ",
  notes: "  ",
  nextDue: "2026-11-01",
  offset: 1440,
  recipients: ["membership"],
};

test("pet care preserves decimal precision and converts Romanian comma input", () => {
  const payload = petEntryPayload(input);
  assert.equal(payload.weight_kg, "12.345");
  assert.equal(payload.cost, "0.10");
  assert.equal(payload.title, "Cântărire");
  assert.equal(payload.notes, null);
});

test("removing a care deadline clears its stored reminder settings", () => {
  const payload = petEntryPayload({ ...input, nextDue: "" });
  assert.equal(payload.next_due_on, null);
  assert.equal(payload.reminder_offset_minutes, null);
  assert.deepEqual(payload.reminder_recipients, []);
});

test("care type changes discard stale weight while existing deadline preferences survive edits", () => {
  const payload = petEntryPayload({ ...input, kind: "visit", cost: "" });
  assert.equal(payload.weight_kg, null);
  assert.equal(payload.cost, null);
  assert.equal(payload.next_due_on, input.nextDue);
  assert.equal(payload.reminder_offset_minutes, input.offset);
  assert.deepEqual(payload.reminder_recipients, input.recipients);
  assert.deepEqual(
    petEntryPayload({ ...input, offset: null }).reminder_recipients,
    [],
  );
});
