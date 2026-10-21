import assert from "node:assert/strict";
import { readFile } from "node:fs/promises";
import test from "node:test";
import ts from "typescript";

const source = await readFile(
  new URL("../src/shared/api.ts", import.meta.url),
  "utf8",
);
const { outputText } = ts.transpileModule(source, {
  compilerOptions: {
    target: ts.ScriptTarget.ES2022,
    module: ts.ModuleKind.ES2022,
  },
});
const { api, ApiError } = await import(
  `data:text/javascript;base64,${Buffer.from(outputText).toString("base64")}`
);

for (const message of ["", "   ", null, undefined]) {
  test(`a forbidden response with message ${JSON.stringify(message)} remains visible`, async (t) => {
    t.mock.method(globalThis, "fetch", async () =>
      Response.json({ message }, { status: 403 }),
    );
    await assert.rejects(api("/example"), (error) => {
      assert.ok(error instanceof ApiError);
      assert.equal(error.status, 403);
      assert.equal(error.message, "Acțiunea nu a putut fi finalizată.");
      return true;
    });
  });
}

test("a stale role response preserves its actionable explanation", async (t) => {
  const message = "Rolul s-a schimbat. Reîncarcă membrii înainte de a salva.";
  t.mock.method(globalThis, "fetch", async () =>
    Response.json({ message }, { status: 409 }),
  );
  await assert.rejects(api("/example"), { status: 409, message });
});

test("validation errors retain priority over a generic response message", async (t) => {
  t.mock.method(globalThis, "fetch", async () =>
    Response.json(
      { message: "Date invalide.", errors: { role: ["Alege un rol valid."] } },
      { status: 422 },
    ),
  );
  await assert.rejects(api("/example"), {
    status: 422,
    message: "Alege un rol valid.",
  });
});

test("an error without a JSON body still provides visible feedback", async (t) => {
  t.mock.method(
    globalThis,
    "fetch",
    async () => new Response("Service unavailable", { status: 503 }),
  );
  await assert.rejects(api("/example"), {
    status: 503,
    message: "Acțiunea nu a putut fi finalizată.",
  });
});
