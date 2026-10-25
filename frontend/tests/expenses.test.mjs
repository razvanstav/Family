import assert from "node:assert/strict";
import { readFile } from "node:fs/promises";
import test from "node:test";
import ts from "typescript";

const compilerOptions = {
  target: ts.ScriptTarget.ES2022,
  module: ts.ModuleKind.ES2022,
};
async function moduleUrl(path, imports = {}) {
  const source = await readFile(new URL(path, import.meta.url), "utf8");
  let { outputText } = ts.transpileModule(source, { compilerOptions });
  for (const [specifier, url] of Object.entries(imports))
    outputText = outputText.replace(
      JSON.stringify(specifier),
      JSON.stringify(url),
    );
  return `data:text/javascript;base64,${Buffer.from(outputText).toString("base64")}`;
}
const apiUrl = await moduleUrl("../src/shared/api.ts");
const datesUrl = await moduleUrl("../src/shared/calendarDates.ts");
const { markExpensePaid, expensePaymentMessage, expensePayerOptions } =
  await import(
    await moduleUrl("../src/modules/expenses/expenseData.ts", {
      "../../shared/api": apiUrl,
      "../../shared/calendarDates": datesUrl,
    })
  );

test("payer filters include historical payers once and retain a selected member in an empty month", () => {
  const totals = [
    {
      payer_membership_id: "ana",
      payer_name: "Ana",
      total_bani: 1800,
      count: 2,
    },
    {
      payer_membership_id: "former",
      payer_name: "Bogdan",
      total_bani: 500,
      count: 1,
    },
  ];
  assert.deepEqual(
    expensePayerOptions(
      [
        { id: "ana", name: "Ana" },
        { id: "mihai", name: "Mihai" },
      ],
      totals,
      { id: "former", name: "Bogdan" },
    ),
    [
      { value: "", label: "Toți membrii" },
      { value: "ana", label: "Ana" },
      { value: "former", label: "Bogdan" },
      { value: "mihai", label: "Mihai" },
    ],
  );
  assert.deepEqual(
    expensePayerOptions([], [], { id: "former", name: "Bogdan" }),
    [
      { value: "", label: "Toți membrii" },
      { value: "former", label: "Bogdan" },
    ],
  );
});

test("payment action preserves family, version, and original due date", async (t) => {
  const controller = new AbortController();
  const paid = {
    id: "expense-one",
    version: 4,
    spent_on: "2027-01-31",
    payment_status: "paid",
    next_due_on: "2027-02-28",
  };
  t.mock.method(globalThis, "fetch", async (url, options) => {
    assert.equal(
      url,
      "/api/v1/families/family-one/expenses/expense-one/payment",
    );
    assert.equal(options.method, "PATCH");
    assert.equal(options.headers.get("If-Match"), '"3"');
    assert.equal(options.signal, controller.signal);
    assert.deepEqual(JSON.parse(options.body), { payment_status: "paid" });
    return Response.json(paid);
  });
  assert.deepEqual(
    await markExpensePaid(
      "family-one",
      { id: "expense-one", version: 3 },
      controller.signal,
    ),
    paid,
  );
});

test("a changed payment is surfaced for explicit reload instead of retried", async (t) => {
  let requests = 0;
  t.mock.method(globalThis, "fetch", async () => {
    requests++;
    return Response.json({ message: "Plata s-a schimbat." }, { status: 412 });
  });
  await assert.rejects(
    markExpensePaid("family-one", { id: "expense-one", version: 3 }),
    { status: 412, message: "Plata s-a schimbat." },
  );
  assert.equal(requests, 1);
});

test("payment feedback shows next due date and retains skipped recurrence reasons", () => {
  assert.equal(
    expensePaymentMessage({
      recurrence_notice:
        "Plata a fost salvată și următoarea plată a fost creată.",
      next_due_on: "2027-02-28",
    }),
    "Plata a fost salvată și următoarea plată a fost creată. Următoarea plată: 28 februarie 2027.",
  );
  const reason =
    "Următoarea plată nu a fost creată: plătitorul nu mai este activ.";
  assert.equal(
    expensePaymentMessage({ recurrence_notice: reason, next_due_on: null }),
    reason,
  );
  assert.equal(
    expensePaymentMessage({ recurrence_notice: "  " }),
    "Plata a fost marcată plătită.",
  );
});
