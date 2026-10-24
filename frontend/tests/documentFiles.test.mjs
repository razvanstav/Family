import assert from "node:assert/strict";
import { readFile } from "node:fs/promises";
import test from "node:test";
import ts from "typescript";

const source = await readFile(
  new URL("../src/shared/documentFiles.ts", import.meta.url),
  "utf8",
);
const { outputText } = ts.transpileModule(source, {
  compilerOptions: { module: ts.ModuleKind.ES2022 },
});
const { documentFileType, documentFileState } = await import(
  `data:text/javascript;base64,${Buffer.from(outputText).toString("base64")}`
);

test("a document without an attachment has a neutral file state", () => {
  assert.equal(documentFileType(null), "Fără fișier");
  assert.equal(documentFileState(null), "Fără fișier");
  assert.notEqual(documentFileState(null), documentFileState("rejected"));
});

test("file format remains independent from scan status", () => {
  assert.equal(documentFileType("application/pdf"), "PDF");
  for (const mime of ["image/jpeg", "image/png", "image/webp"]) {
    assert.equal(documentFileType(mime), "Imagine");
  }
  assert.equal(documentFileState("ready"), null);
});

test("quarantined and rejected files retain their visible scan state", () => {
  assert.equal(documentFileState("quarantine"), "În verificare");
  assert.equal(documentFileState("rejected"), "Respins");
});
