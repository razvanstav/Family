import assert from "node:assert/strict";
import { readFile } from "node:fs/promises";
import test from "node:test";
import ts from "typescript";

const source = await readFile(
  new URL("../src/app/pageNavigation.ts", import.meta.url),
  "utf8",
);
const js = ts.transpileModule(source, {
  compilerOptions: {
    target: ts.ScriptTarget.ES2022,
    module: ts.ModuleKind.ES2022,
  },
}).outputText;
const { createPageNavigation } = await import(
  `data:text/javascript;base64,${Buffer.from(js).toString("base64")}`
);

function deferred() {
  let resolve;
  let reject;
  const promise = new Promise((yes, no) => {
    resolve = yes;
    reject = no;
  });
  return { promise, resolve, reject };
}

function fixture() {
  const attributes = new Map();
  const transitions = [];
  const commits = [];
  const modes = [];
  let current = "home";
  let reduced = false;
  const document = {
    visibilityState: "visible",
    documentElement: {
      setAttribute: (name, value) => attributes.set(name, value),
      removeAttribute: (name) => attributes.delete(name),
    },
    startViewTransition(update) {
      const ready = deferred();
      const finished = deferred();
      const updateDone = deferred();
      const transition = {
        ready: ready.promise,
        finished: finished.promise,
        updateCallbackDone: updateDone.promise,
        skipped: false,
        skipTransition() {
          this.skipped = true;
          ready.reject(new Error("Animation skipped"));
        },
        update() {
          update();
          updateDone.resolve();
        },
        finish() {
          ready.resolve();
          finished.resolve();
        },
      };
      transitions.push(transition);
      return transition;
    },
  };
  const navigation = createPageNavigation(
    document,
    () => reduced,
    (page, native) => {
      current = page;
      commits.push(page);
      modes.push(native);
    },
    () => current,
  );
  return {
    document,
    attributes,
    transitions,
    commits,
    modes,
    navigation,
    reduce: () => (reduced = true),
  };
}

test("page navigation commits synchronously within the native update", async () => {
  const f = fixture();
  f.navigation.navigate("expenses");
  assert.deepEqual(f.commits, []);
  assert.equal(f.attributes.has("data-page-navigation"), true);
  f.transitions[0].update();
  assert.deepEqual(f.commits, ["expenses"]);
  assert.deepEqual(f.modes, [true]);
  f.transitions[0].finish();
  await new Promise(setImmediate);
  assert.equal(f.attributes.has("data-page-navigation"), false);
});

test("rapid navigation skips the previous animation and ignores its late update", async () => {
  const f = fixture();
  f.navigation.navigate("expenses");
  f.navigation.navigate("subscriptions");
  assert.equal(f.transitions[0].skipped, true);
  f.transitions[0].update();
  f.transitions[1].update();
  assert.deepEqual(f.commits, ["subscriptions"]);
  f.transitions[0].finish();
  await new Promise(setImmediate);
  assert.equal(f.attributes.has("data-page-navigation"), true);
  f.transitions[1].finish();
  await new Promise(setImmediate);
  assert.equal(f.attributes.has("data-page-navigation"), false);
});

test("returning to the current page cancels an uncommitted destination", () => {
  const f = fixture();
  f.navigation.navigate("expenses");
  f.navigation.navigate("home");
  f.transitions[0].update();
  assert.equal(f.transitions.length, 1);
  assert.equal(f.transitions[0].skipped, true);
  assert.deepEqual(f.commits, []);
  assert.equal(f.attributes.has("data-page-navigation"), false);
});

test("stopping for session suspension prevents a late private-page update", () => {
  const f = fixture();
  f.navigation.navigate("subscriptions");
  f.navigation.stop();
  f.transitions[0].update();
  assert.deepEqual(f.commits, []);
  assert.equal(f.transitions[0].skipped, true);
  assert.equal(f.attributes.has("data-page-navigation"), false);
});

for (const fallback of ["unsupported", "reduced", "hidden"]) {
  test(`page navigation immediately commits with ${fallback} fallback`, () => {
    const f = fixture();
    if (fallback === "unsupported") delete f.document.startViewTransition;
    if (fallback === "reduced") f.reduce();
    if (fallback === "hidden") f.document.visibilityState = "hidden";
    f.navigation.navigate("expenses");
    assert.deepEqual(f.commits, ["expenses"]);
    assert.deepEqual(f.modes, [false]);
    assert.equal(f.transitions.length, 0);
    assert.equal(f.attributes.has("data-page-navigation"), false);
  });
}

test("failure to start a native transition falls back to one immediate update", () => {
  const f = fixture();
  f.document.startViewTransition = () => {
    throw new Error("Native animation unavailable");
  };
  f.navigation.navigate("expenses");
  assert.deepEqual(f.commits, ["expenses"]);
  assert.deepEqual(f.modes, [false]);
  assert.equal(f.attributes.has("data-page-navigation"), false);
});
