import assert from "node:assert/strict";
import { readFile } from "node:fs/promises";
import test from "node:test";
import ts from "typescript";

const source = await readFile(
  new URL("../src/modules/memory/galleryNavigation.ts", import.meta.url),
  "utf8",
);
const js = ts.transpileModule(source, {
  compilerOptions: {
    target: ts.ScriptTarget.ES2022,
    module: ts.ModuleKind.ES2022,
  },
}).outputText;
const { swipeStep, galleryIndex } = await import(
  `data:text/javascript;base64,${Buffer.from(js).toString("base64")}`
);

test("gallery swipes distinguish horizontal browsing from taps and vertical scrolling", () => {
  assert.equal(swipeStep(-110, 12), 1);
  assert.equal(swipeStep(110, -12), -1);
  for (const [x, y] of [
    [0, 0],
    [30, 2],
    [-30, -2],
    [80, 100],
    [-80, 100],
    [60, 40],
  ])
    assert.equal(swipeStep(x, y), 0);
});

test("gallery navigation stops at each end including single-photo galleries", () => {
  assert.equal(galleryIndex(0, -1, 3), 0);
  assert.equal(galleryIndex(2, 1, 3), 2);
  assert.equal(galleryIndex(1, -1, 3), 0);
  assert.equal(galleryIndex(1, 1, 3), 2);
  assert.equal(galleryIndex(0, 1, 1), 0);
  assert.equal(galleryIndex(0, -1, 1), 0);
});
