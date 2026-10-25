import assert from "node:assert/strict";
import { readdir, readFile } from "node:fs/promises";
import path from "node:path";
import { fileURLToPath } from "node:url";
import test from "node:test";
import ts from "typescript";
import {
  dependencyViolation,
  moduleSpecifiers,
  resolveDependency,
} from "./moduleBoundaryRules.mjs";

const root = fileURLToPath(new URL("../src/", import.meta.url));
const config = ts.readConfigFile(
  fileURLToPath(new URL("../tsconfig.json", import.meta.url)),
  ts.sys.readFile,
);
const { options } = ts.parseJsonConfigFileContent(
  config.config,
  ts.sys,
  path.dirname(root),
);

test("module boundaries reject foreign modules and reverse dependencies", () => {
  for (const [source, target] of [
    ["modules/memory/nested/Card.tsx", "modules/kitchen/MealViewer.tsx"],
    ["modules/families/Profile.tsx", "app/App.tsx"],
    ["shared/helper.ts", "modules/documents/types.ts"],
    ["shared/helper.ts", "app/App.tsx"],
  ])
    assert.ok(dependencyViolation(source, target), `${source} -> ${target}`);
  for (const [source, target] of [
    ["app/App.tsx", "modules/kitchen/MealViewer.tsx"],
    ["modules/memory/Card.tsx", "modules/memory/types.ts"],
    ["modules/memory/Card.tsx", "shared/api.ts"],
  ])
    assert.equal(dependencyViolation(source, target), null);
});

test("module boundaries inspect static, type, re-export and dynamic imports", () => {
  assert.deepEqual(
    moduleSpecifiers(`
    import type { Meal } from "a";
    export { MealViewer } from "b";
    export * from "c";
    const view = import("d");
    type Meal = import("e").Meal;
    const other = require("f");
    // import "not-a-dependency";
  `),
    ["a", "b", "c", "d", "e", "f"],
  );
});

async function files(directory) {
  const entries = await readdir(directory, { withFileTypes: true });
  const result = [];
  for (const entry of entries) {
    const filename = path.join(directory, entry.name);
    if (entry.isDirectory()) result.push(...(await files(filename)));
    else if (/\.(tsx?|css)$/.test(entry.name)) result.push(filename);
  }
  return result;
}

test("modules stay independent and shared code has no module or app dependencies", async () => {
  const violations = [];
  for (const filename of [
    ...(await files(path.join(root, "modules"))),
    ...(await files(path.join(root, "shared"))),
  ]) {
    const source = await readFile(filename, "utf8");
    const specifiers = filename.endsWith(".css")
      ? [...source.matchAll(/@import\s+["']([^"']+)["']/g)].map(
          (match) => match[1],
        )
      : moduleSpecifiers(source);
    for (const specifier of specifiers) {
      const target = resolveDependency(specifier, filename, options);
      if (!target) continue;
      const relative = path.relative(root, filename);
      const reason = dependencyViolation(relative, path.relative(root, target));
      if (reason) violations.push(`${relative}: ${specifier} (${reason})`);
    }
  }
  assert.deepEqual(violations, []);
});

test("native select controls stay inside the shared styled form adapter", async () => {
  const violations = [];
  for (const filename of await files(root)) {
    if (!filename.endsWith(".tsx")) continue;
    if (path.relative(root, filename) === "shared/SelectField.tsx") continue;
    const source = ts.createSourceFile(
      filename,
      await readFile(filename, "utf8"),
      ts.ScriptTarget.Latest,
      true,
      ts.ScriptKind.TSX,
    );
    function visit(node) {
      if (
        (ts.isJsxOpeningElement(node) || ts.isJsxSelfClosingElement(node)) &&
        node.tagName.getText(source) === "select"
      )
        violations.push(path.relative(root, filename));
      ts.forEachChild(node, visit);
    }
    visit(source);
  }
  assert.deepEqual(violations, []);
});
