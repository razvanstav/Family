import assert from "node:assert/strict";
import { readFile } from "node:fs/promises";
import test from "node:test";
import ts from "typescript";

const source = await readFile(
  new URL("../src/modules/documents/documentFilters.ts", import.meta.url),
  "utf8",
);
const { outputText } = ts.transpileModule(source, {
  compilerOptions: { module: ts.ModuleKind.ES2022 },
});
const { filterDocuments } = await import(
  `data:text/javascript;base64,${Buffer.from(outputText).toString("base64")}`
);
const documents = [
  { id: "1", title: "Povestea casei", category: "home", visibility: "family" },
  {
    id: "2",
    title: "Povestea bunicilor",
    category: "memories",
    visibility: "private",
  },
  {
    id: "3",
    title: "Călătorie în familie",
    category: "memories",
    visibility: "selected",
  },
];

test("search ignores surrounding whitespace and case, including Romanian letters", () => {
  assert.deepEqual(
    filterDocuments(documents, "  CĂLĂTORIE  ", "all", "all").map((d) => d.id),
    ["3"],
  );
});

test("search, category and access filters are combined", () => {
  assert.deepEqual(
    filterDocuments(documents, "povestea", "memories", "private").map(
      (d) => d.id,
    ),
    ["2"],
  );
  assert.deepEqual(
    filterDocuments(documents, "povestea", "home", "private"),
    [],
  );
});

test("blank search and reset preserve the complete list and its order", () => {
  assert.deepEqual(filterDocuments(documents, " \t ", "all", "all"), documents);
  assert.deepEqual(filterDocuments([], "", "all", "all"), []);
});
