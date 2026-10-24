import assert from "node:assert/strict";
import { readFile } from "node:fs/promises";
import test from "node:test";
import ts from "typescript";

const source = await readFile(
  new URL("../src/shared/pageResume.ts", import.meta.url),
  "utf8",
);
const js = ts.transpileModule(source, {
  compilerOptions: {
    target: ts.ScriptTarget.ES2022,
    module: ts.ModuleKind.ES2022,
  },
}).outputText;
const { observePageResume } = await import(
  `data:text/javascript;base64,${Buffer.from(js).toString("base64")}`
);

function fixture() {
  const document = Object.assign(new EventTarget(), {
    visibilityState: "visible",
  });
  const window = new EventTarget();
  let resumes = 0;
  const stop = observePageResume(document, window, () => resumes++);
  return { document, window, stop, count: () => resumes };
}

test("returning to a tab refreshes once when visibility and focus both fire", () => {
  const f = fixture();
  f.window.dispatchEvent(new Event("blur"));
  f.document.visibilityState = "hidden";
  f.document.dispatchEvent(new Event("visibilitychange"));
  f.document.visibilityState = "visible";
  f.document.dispatchEvent(new Event("visibilitychange"));
  f.window.dispatchEvent(new Event("focus"));
  assert.equal(f.count(), 1);
  f.stop();
});

test("focus refreshes after leaving the window, and hidden pages do not refresh", () => {
  const f = fixture();
  f.window.dispatchEvent(new Event("focus"));
  assert.equal(f.count(), 0);
  f.window.dispatchEvent(new Event("blur"));
  f.window.dispatchEvent(new Event("focus"));
  assert.equal(f.count(), 1);
  f.document.visibilityState = "hidden";
  f.document.dispatchEvent(new Event("visibilitychange"));
  f.window.dispatchEvent(new Event("focus"));
  assert.equal(f.count(), 1);
  f.document.visibilityState = "visible";
  f.document.dispatchEvent(new Event("visibilitychange"));
  assert.equal(f.count(), 2);
  f.stop();
});

test("bfcache restoration refreshes and cleanup removes listeners", () => {
  const f = fixture();
  const restored = Object.assign(new Event("pageshow"), { persisted: true });
  f.window.dispatchEvent(new Event("pagehide"));
  f.window.dispatchEvent(restored);
  assert.equal(f.count(), 1);
  f.stop();
  f.window.dispatchEvent(new Event("blur"));
  f.window.dispatchEvent(new Event("focus"));
  f.window.dispatchEvent(new Event("pagehide"));
  f.window.dispatchEvent(restored);
  assert.equal(f.count(), 1);
});
