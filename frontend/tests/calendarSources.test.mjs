import assert from "node:assert/strict";
import { readFile } from "node:fs/promises";
import test from "node:test";
import ts from "typescript";

const source = await readFile(
  new URL("../src/modules/calendar/calendarTypes.ts", import.meta.url),
  "utf8",
);
const js = ts.transpileModule(source, {
  compilerOptions: {
    target: ts.ScriptTarget.ES2022,
    module: ts.ModuleKind.ES2022,
  },
}).outputText;
const {
  calendarSourceDestination,
  calendarSourceLabel,
  calendarMatchesAccess,
} = await import(
  `data:text/javascript;base64,${Buffer.from(js).toString("base64")}`
);

test("a medical visit opens its own entry inside the subject profile", () => {
  const visit = {
    type: "profile_entry",
    id: "visit",
    parent_id: "subject",
    kind: "visit",
    virtual: true,
  };
  assert.deepEqual(calendarSourceDestination(visit), {
    type: "profile",
    memberId: "subject",
    entryId: "visit",
    kind: "visit",
  });
  assert.equal(calendarSourceLabel(visit), "Vizită medicală");
});

test("document and memory reminders use canonical records rather than projection IDs", () => {
  assert.deepEqual(
    calendarSourceDestination({
      type: "document_expiry",
      id: "projection",
      parent_id: "document",
    }),
    { type: "document", id: "document" },
  );
  assert.deepEqual(
    calendarSourceDestination({
      type: "memory_anniversary",
      id: "annual-projection",
      parent_id: "moment",
    }),
    { type: "memory", id: "moment" },
  );
  assert.deepEqual(
    calendarSourceDestination({
      type: "memory_date",
      id: "moment",
      parent_id: "moment",
      virtual: true,
    }),
    { type: "memory", id: "moment" },
  );
});

test("animal birth opens the profile while care and vehicle records retain their parent", () => {
  assert.deepEqual(
    calendarSourceDestination({
      type: "pet_birth",
      id: "pet",
      parent_id: "pet",
      virtual: true,
    }),
    { type: "petProfile", id: "pet" },
  );
  assert.deepEqual(
    calendarSourceDestination({
      type: "pet_due",
      id: "care",
      parent_id: "pet",
      virtual: true,
    }),
    { type: "pet", petId: "pet", entryId: "care" },
  );
  assert.deepEqual(
    calendarSourceDestination({
      type: "vehicle_date",
      id: "service",
      parent_id: "car",
      virtual: true,
    }),
    { type: "vehicle", vehicleId: "car", entryId: "service" },
  );
});

test("shared Calendar filtering includes selected and adult access without including private records", () => {
  const scopes = ["private", "family", "selected", "adults"];
  assert.deepEqual(
    scopes.filter((scope) => calendarMatchesAccess(scope, "family")),
    ["family", "selected", "adults"],
  );
  assert.deepEqual(
    scopes.filter((scope) => calendarMatchesAccess(scope, "private")),
    ["private"],
  );
  assert.deepEqual(
    scopes.filter((scope) => calendarMatchesAccess(scope, "")),
    scopes,
  );
});

test("a subscription date opens the canonical subscription with its direct label", () => {
  const source = {
    type: "subscription",
    id: "subscription",
    parent_id: "subscription",
    virtual: true,
  };
  assert.deepEqual(calendarSourceDestination(source), {
    type: "subscription",
    id: "subscription",
  });
  assert.equal(calendarSourceLabel(source), "Abonament");
});

test("profile record labels distinguish medical, reading and activity dates", () => {
  const entry = { type: "profile_entry", id: "entry", parent_id: "subject" };
  assert.equal(
    calendarSourceLabel({ ...entry, kind: "medication" }),
    "Medicament",
  );
  assert.equal(calendarSourceLabel({ ...entry, kind: "book" }), "Carte citită");
  assert.equal(
    calendarSourceLabel({ ...entry, kind: "activity" }),
    "Activitate",
  );
  assert.equal(
    calendarSourceLabel({ ...entry, kind: "future-kind" }),
    "Însemnare",
  );
});
