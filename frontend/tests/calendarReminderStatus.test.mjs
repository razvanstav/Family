import assert from "node:assert/strict";
import { readFile } from "node:fs/promises";
import test from "node:test";
import ts from "typescript";

const source = await readFile(
  new URL("../src/modules/calendar/calendarReminderStatus.ts", import.meta.url),
  "utf8",
);
const js = ts.transpileModule(source, {
  compilerOptions: {
    target: ts.ScriptTarget.ES2022,
    module: ts.ModuleKind.ES2022,
  },
}).outputText;
const { reminderDeliveryStatus } = await import(
  `data:text/javascript;base64,${Buffer.from(js).toString("base64")}`
);

test("pending intents do not claim channels before delivery-time preferences are checked", () => {
  assert.deepEqual(
    reminderDeliveryStatus([
      {
        membership_id: "self",
        state: "pending",
        in_app_enabled: true,
        email_state: null,
      },
      {
        membership_id: "other",
        state: "pending",
        in_app_enabled: null,
        email_state: null,
      },
    ]),
    ["Reminder: programat"],
  );
});

test("processed email-only intent does not claim an app delivery or an email acceptance", () => {
  assert.deepEqual(
    reminderDeliveryStatus([
      {
        membership_id: "self",
        state: "delivered",
        in_app_enabled: false,
        email_state: "pending",
      },
    ]),
    ["În aplicație: oprit", "Email: programat"],
  );
});

test("SMTP acceptance and uncertain delivery remain distinct", () => {
  for (const [emailState, expected] of [
    ["sent", "Email: acceptat de server"],
    ["uncertain", "Email: trimitere neconfirmată"],
  ]) {
    assert.deepEqual(
      reminderDeliveryStatus([
        {
          membership_id: "self",
          state: "delivered",
          in_app_enabled: true,
          email_state: emailState,
        },
      ]),
      ["În aplicație: disponibil", expected],
    );
  }
});

test("other recipients only expose the generic intent state and repeated states are grouped", () => {
  const delivery = {
    membership_id: "other",
    state: "delivered",
    in_app_enabled: null,
    email_state: null,
  };
  assert.deepEqual(
    reminderDeliveryStatus([
      delivery,
      { ...delivery, membership_id: "another" },
    ]),
    ["Alți destinatari: procesat"],
  );
});
