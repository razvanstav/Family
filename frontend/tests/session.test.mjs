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
const { api, onSessionInvalidated } = await import(
  `data:text/javascript;base64,${Buffer.from(outputText).toString("base64")}`
);

for (const status of [401, 419]) {
  test(`A1: ${status} invalidates the session before the error body loads, without replaying the mutation`, async (t) => {
    let invalidated = 0;
    const unsubscribe = onSessionInvalidated(() => invalidated++);
    t.after(unsubscribe);
    const calls = [];
    t.mock.method(globalThis, "fetch", async (url, options) => {
      calls.push({ url, options });
      if (url.endsWith("/session"))
        return Response.json({ csrf_token: "fresh-token" });
      return {
        ok: false,
        status,
        json: async () => {
          assert.equal(invalidated, 1);
          return { message: "Expired" };
        },
      };
    });
    await assert.rejects(api("/logout", { method: "POST" }), { status });
    assert.equal(calls.length, 1);
    await api("/session");
    assert.equal(calls[1].options.headers.has("X-CSRF-TOKEN"), false);
    unsubscribe();
    t.mock.method(globalThis, "fetch", async (_url, options) => {
      assert.equal(options.headers.get("X-CSRF-TOKEN"), "fresh-token");
      return Response.json({});
    });
    await api("/login", { method: "POST" });
  });
}

test("A1: a session lookup failure does not recursively start another lookup", async (t) => {
  let invalidated = 0;
  const unsubscribe = onSessionInvalidated(() => invalidated++);
  t.after(unsubscribe);
  t.mock.method(globalThis, "fetch", async () =>
    Response.json({}, { status: 401 }),
  );
  await assert.rejects(api("/session"), { status: 401 });
  assert.equal(invalidated, 0);
});

test("A1: document authorization and validation errors do not invalidate a valid session", async (t) => {
  let invalidated = 0;
  const unsubscribe = onSessionInvalidated(() => invalidated++);
  t.after(unsubscribe);
  for (const status of [403, 404, 412, 422]) {
    t.mock.method(globalThis, "fetch", async () =>
      Response.json({}, { status }),
    );
    await assert.rejects(api("/families/example/documents/example"), {
      status,
    });
  }
  assert.equal(invalidated, 0);
});

test("A1: an unmounted session observer is not notified", async (t) => {
  let invalidated = 0;
  const unsubscribe = onSessionInvalidated(() => invalidated++);
  unsubscribe();
  t.mock.method(globalThis, "fetch", async () =>
    Response.json({}, { status: 419 }),
  );
  await assert.rejects(api("/logout", { method: "POST" }), { status: 419 });
  assert.equal(invalidated, 0);
});

const lifecycleSource = await readFile(
  new URL("../src/app/sessionLifecycle.ts", import.meta.url),
  "utf8",
);
const lifecycleJs = ts.transpileModule(lifecycleSource, {
  compilerOptions: {
    target: ts.ScriptTarget.ES2022,
    module: ts.ModuleKind.ES2022,
  },
}).outputText;
const { observeSessionLifecycle } = await import(
  `data:text/javascript;base64,${Buffer.from(lifecycleJs).toString("base64")}`
);

test("A1: hide removes the private view before a return asks to revalidate; cleanup stops observers", () => {
  const document = new EventTarget();
  document.visibilityState = "hidden";
  const window = new EventTarget();
  const actions = [];
  const stop = observeSessionLifecycle(document, window, {
    suspend: () => actions.push("clear"),
    resume: () => actions.push("verify"),
  });
  document.dispatchEvent(new Event("visibilitychange"));
  assert.deepEqual(actions, ["clear"]);
  document.visibilityState = "visible";
  document.dispatchEvent(new Event("visibilitychange"));
  assert.deepEqual(actions, ["clear", "verify"]);
  stop();
  document.dispatchEvent(new Event("visibilitychange"));
  assert.equal(actions.length, 2);
});

test("A1: restored browser history revalidates, ordinary pageshow does not duplicate startup", () => {
  const document = new EventTarget();
  const window = new EventTarget();
  let checks = 0;
  const stop = observeSessionLifecycle(document, window, {
    suspend: () => {},
    resume: () => checks++,
  });
  window.dispatchEvent(
    Object.assign(new Event("pageshow"), { persisted: false }),
  );
  assert.equal(checks, 0);
  window.dispatchEvent(
    Object.assign(new Event("pageshow"), { persisted: true }),
  );
  assert.equal(checks, 1);
  stop();
  window.dispatchEvent(
    Object.assign(new Event("pageshow"), { persisted: true }),
  );
  assert.equal(checks, 1);
});
