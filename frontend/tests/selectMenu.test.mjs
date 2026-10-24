import assert from "node:assert/strict";
import { readFile } from "node:fs/promises";
import test from "node:test";
import ts from "typescript";

const source = await readFile(
  new URL("../src/shared/selectMenuOptions.ts", import.meta.url),
  "utf8",
);
const js = ts.transpileModule(source, {
  compilerOptions: {
    target: ts.ScriptTarget.ES2022,
    module: ts.ModuleKind.ES2022,
  },
}).outputText;
const { getEnabledOptionIndex, findSelectOption } = await import(
  `data:text/javascript;base64,${Buffer.from(js).toString("base64")}`
);

const options = Object.freeze([
  { value: "disabled-start", label: "Indisponibil", disabled: true },
  { value: "family", label: "Familie" },
  { value: "disabled-middle", label: "Personal", disabled: true },
  { value: "shared", label: "Partajate cu mine" },
  { value: "disabled-end", label: "Arhivate", disabled: true },
]);

test("arrow navigation skips disabled options and wraps both boundaries", () => {
  assert.equal(getEnabledOptionIndex(options, 1, "next"), 3);
  assert.equal(getEnabledOptionIndex(options, 3, "next"), 1);
  assert.equal(getEnabledOptionIndex(options, 3, "previous"), 1);
  assert.equal(getEnabledOptionIndex(options, 1, "previous"), 3);
});

test("Home and End select the first and last enabled option", () => {
  assert.equal(getEnabledOptionIndex(options, 3, "first"), 1);
  assert.equal(getEnabledOptionIndex(options, 1, "last"), 3);
});

test("navigation from no active option starts at the relevant enabled edge", () => {
  for (const index of [-1, options.length, Number.NaN]) {
    assert.equal(getEnabledOptionIndex(options, index, "next"), 1);
    assert.equal(getEnabledOptionIndex(options, index, "previous"), 3);
  }
  assert.equal(getEnabledOptionIndex(options, 2, "next"), 3);
  assert.equal(getEnabledOptionIndex(options, 2, "previous"), 1);
});

test("a sole enabled option remains reachable after wrapping", () => {
  const single = [
    { value: "disabled", label: "Inactiv", disabled: true },
    { value: "only", label: "Activ" },
  ];
  assert.equal(getEnabledOptionIndex(single, 1, "next"), 1);
  assert.equal(getEnabledOptionIndex(single, 1, "previous"), 1);
});

test("navigation returns no option for empty and entirely disabled lists", () => {
  const disabled = [{ value: "none", label: "Inactiv", disabled: true }];
  for (const direction of ["next", "previous", "first", "last"]) {
    assert.equal(getEnabledOptionIndex([], -1, direction), -1);
    assert.equal(getEnabledOptionIndex(disabled, 0, direction), -1);
  }
});

test("typeahead matches Romanian labels without accents and ignores case", () => {
  const labels = ["Școală", "Ţară", "Îngrijire", "Ăsta", "Ştiinţă"].map(
    (label) => ({ value: label, label }),
  );
  assert.equal(findSelectOption(labels, "sco"), 0);
  assert.equal(findSelectOption(labels, "ȚA"), 1);
  assert.equal(findSelectOption(labels, "ing"), 2);
  assert.equal(findSelectOption(labels, "asta"), 3);
  assert.equal(findSelectOption(labels, "ȘTIINȚ"), 4);
});

test("typeahead searches inclusively, wraps, and skips disabled matches", () => {
  const labels = [
    { value: "disabled", label: "Martie", disabled: true },
    { value: "march", label: "Martie" },
    { value: "april", label: "Aprilie" },
    { value: "may", label: "Mai" },
  ];
  assert.equal(findSelectOption(labels, "ma", 1), 1);
  assert.equal(findSelectOption(labels, "ma", 2), 3);
  assert.equal(findSelectOption(labels, "ma", 4), 1);
  assert.equal(findSelectOption(labels, "ma", -1), 3);
  assert.equal(findSelectOption(labels, "ap", 3), 2);
});

test("typeahead matches year labels without confusing values and labels", () => {
  const years = [2025, 2026, 2027].map((year) => ({
    value: `year-${year}`,
    label: String(year),
  }));
  assert.equal(findSelectOption(years, "202", 1), 1);
  assert.equal(findSelectOption(years, "2027"), 2);
  assert.equal(findSelectOption(years, "year-2026"), -1);
});

test("typeahead returns no option for empty queries or unavailable matches", () => {
  assert.equal(findSelectOption([], "a"), -1);
  assert.equal(findSelectOption(options, ""), -1);
  assert.equal(findSelectOption(options, "  "), -1);
  assert.equal(findSelectOption(options, "inexistent"), -1);
  assert.equal(findSelectOption(options, "Indisponibil"), -1);
  assert.equal(
    findSelectOption([{ value: "none", label: "A", disabled: true }], "a"),
    -1,
  );
});
