import assert from "node:assert/strict";
import { readFile } from "node:fs/promises";
import test from "node:test";
import ts from "typescript";

async function load(name) {
  const source = await readFile(
    new URL(`../src/shared/${name}.ts`, import.meta.url),
    "utf8",
  );
  const js = ts.transpileModule(source, {
    compilerOptions: {
      target: ts.ScriptTarget.ES2022,
      module: ts.ModuleKind.ES2022,
    },
  }).outputText;
  return import(
    `data:text/javascript;base64,${Buffer.from(js).toString("base64")}`
  );
}
const { observeVisualViewport } = await load("visualViewport");
const { lockPageScroll } = await load("pageScroll");
const { revealModalFocus } = await load("modalFocus");

function style() {
  const values = new Map();
  const writes = [];
  return {
    writes,
    getPropertyValue: (name) => values.get(name)?.value ?? "",
    getPropertyPriority: (name) => values.get(name)?.priority ?? "",
    setProperty: (name, value, priority = "") => {
      writes.push({ name, value, priority });
      values.set(name, { value, priority });
    },
    removeProperty: (name) => values.delete(name),
  };
}
function viewportFixture() {
  const viewport = Object.assign(new EventTarget(), {
    height: 844,
    offsetTop: 0,
    scale: 1,
  });
  const frames = new Map();
  let id = 0;
  const window = Object.assign(new EventTarget(), {
    innerHeight: 844,
    visualViewport: viewport,
    requestAnimationFrame: (callback) => (frames.set(++id, callback), id),
    cancelAnimationFrame: (frame) => frames.delete(frame),
  });
  const root = { style: style(), clientHeight: 844 };
  const stop = observeVisualViewport(window, root);
  const flush = () => {
    for (const [id, callback] of frames) {
      frames.delete(id);
      callback();
    }
  };
  return { viewport, window, root, frames, stop, flush };
}

test("keyboard opens, pans and dismisses without retaining its space", () => {
  const f = viewportFixture();
  assert.equal(
    f.root.style.getPropertyValue("--visual-viewport-height"),
    "844px",
  );
  f.viewport.height = 500;
  f.viewport.dispatchEvent(new Event("resize"));
  f.viewport.offsetTop = 70;
  f.viewport.dispatchEvent(new Event("scroll"));
  assert.equal(
    f.frames.size,
    1,
    "one animation frame coalesces keyboard events",
  );
  f.flush();
  assert.equal(
    f.root.style.getPropertyValue("--visual-viewport-height"),
    "500px",
  );
  assert.equal(f.root.style.getPropertyValue("--visual-viewport-top"), "70px");
  f.viewport.height = 844;
  f.viewport.dispatchEvent(new Event("resize"));
  f.flush();
  assert.equal(
    f.root.style.getPropertyValue("--visual-viewport-height"),
    "844px",
  );
  assert.equal(
    f.root.style.getPropertyValue("--visual-viewport-top"),
    "0px",
    "stale iOS offset is bounded",
  );
  f.stop();
});

test("orientation, page restoration, zoom and cleanup do not leave stale dimensions or listeners", () => {
  const f = viewportFixture();
  f.root.clientHeight = f.window.innerHeight = f.viewport.height = 390;
  f.window.dispatchEvent(new Event("resize"));
  f.flush();
  assert.equal(
    f.root.style.getPropertyValue("--visual-viewport-height"),
    "390px",
  );
  f.viewport.scale = 2;
  f.viewport.dispatchEvent(new Event("resize"));
  f.flush();
  assert.equal(f.root.style.getPropertyValue("--visual-viewport-height"), "");
  f.viewport.scale = 1;
  f.window.dispatchEvent(new Event("pageshow"));
  f.flush();
  assert.equal(
    f.root.style.getPropertyValue("--visual-viewport-height"),
    "390px",
  );
  f.viewport.dispatchEvent(new Event("resize"));
  f.stop();
  assert.equal(f.frames.size, 0);
  f.viewport.dispatchEvent(new Event("scroll"));
  f.window.dispatchEvent(new Event("resize"));
  assert.equal(f.frames.size, 0);
  assert.equal(f.root.style.getPropertyValue("--visual-viewport-height"), "");
  assert.doesNotThrow(() =>
    observeVisualViewport({ visualViewport: null }, {})(),
  );
});

test("Safari keyboard pan does not use a changing innerHeight as layout size", () => {
  const f = viewportFixture();
  f.viewport.height = 420;
  f.viewport.offsetTop = 110;
  // WebKit can report the visible bottom here, not the layout viewport height.
  f.window.innerHeight = 530;
  f.viewport.dispatchEvent(new Event("resize"));
  f.flush();
  assert.equal(f.root.style.getPropertyValue("--visual-viewport-top"), "110px");
  assert.equal(
    f.root.style.getPropertyValue("--visual-viewport-height"),
    "420px",
  );
  f.window.innerHeight = 420;
  f.viewport.dispatchEvent(new Event("scroll"));
  f.flush();
  assert.equal(f.root.style.getPropertyValue("--visual-viewport-top"), "110px");
  f.viewport.height = 844;
  f.viewport.dispatchEvent(new Event("resize"));
  f.flush();
  assert.equal(f.root.style.getPropertyValue("--visual-viewport-top"), "0px");
  assert.equal(
    f.root.style.getPropertyValue("--visual-viewport-height"),
    "844px",
    "restores the whole viewport even before innerHeight catches up",
  );
  f.stop();
});

test("unchanged viewport scroll and transient zero sizes do not relayout a focused form", () => {
  const f = viewportFixture();
  const initialWrites = f.root.style.writes.length;
  for (let index = 0; index < 5; index++) {
    f.viewport.dispatchEvent(new Event("scroll"));
    f.flush();
  }
  assert.equal(f.root.style.writes.length, initialWrites);
  f.viewport.height = 0;
  f.viewport.dispatchEvent(new Event("resize"));
  f.flush();
  assert.equal(
    f.root.style.getPropertyValue("--visual-viewport-height"),
    "844px",
  );
  assert.equal(f.root.style.writes.length, initialWrites);
  f.stop();
});

test("nested dialogs restore original page position and inline styles only after the final close", () => {
  const document = { body: { style: style() } };
  document.body.style.setProperty("position", "relative", "important");
  const scrolls = [];
  const window = {
    scrollX: 0,
    scrollY: 620,
    scrollTo: (value) => scrolls.push(value),
  };
  const closeOuter = lockPageScroll(document, window);
  assert.equal(document.body.style.getPropertyValue("position"), "fixed");
  assert.equal(document.body.style.getPropertyValue("top"), "-620px");
  const closeInner = lockPageScroll(document, window);
  // Native input reveal may pan the browser while the background stays fixed.
  window.scrollY = 140;
  closeOuter();
  closeOuter();
  assert.equal(scrolls.length, 0);
  closeInner();
  assert.deepEqual(scrolls, [{ left: 0, top: 620, behavior: "instant" }]);
  assert.equal(document.body.style.getPropertyValue("top"), "");
  assert.equal(document.body.style.getPropertyValue("position"), "relative");
  assert.equal(
    document.body.style.getPropertyPriority("position"),
    "important",
  );
  const closeAgain = lockPageScroll(document, window);
  closeAgain();
  assert.equal(scrolls.length, 2, "a later dialog gets a new balanced lock");
});

test("keyboard resize and next-field navigation reveal focus by scrolling only the form", () => {
  const dialog = {};
  let activeDialog = dialog;
  let popover = null;
  const active = {
    closest: (selector) => (selector === "dialog" ? activeDialog : popover),
    getBoundingClientRect: () => ({ top: 450, bottom: 494, height: 44 }),
  };
  const panel = {
    closest: () => dialog,
    scrollTop: 100,
    contains: (element) => element === active,
    getBoundingClientRect: () => ({ top: 70, bottom: 420 }),
  };
  revealModalFocus(panel, active);
  assert.equal(panel.scrollTop, 190, "input appears 16px above the keyboard");
  active.getBoundingClientRect = () => ({ top: 360, bottom: 404, height: 44 });
  revealModalFocus(panel, active);
  assert.equal(panel.scrollTop, 190, "visible focus does not keep scrolling");
  active.getBoundingClientRect = () => ({ top: 30, bottom: 74, height: 44 });
  revealModalFocus(panel, active);
  assert.equal(panel.scrollTop, 134, "previous field is revealed at the top");
  revealModalFocus(panel, null);
  revealModalFocus(panel, {});
  assert.equal(panel.scrollTop, 134, "other dialogs do not move this form");
  activeDialog = {};
  revealModalFocus(panel, active);
  assert.equal(
    panel.scrollTop,
    134,
    "a nested dialog's focus does not scroll the containing dialog",
  );
  activeDialog = dialog;
  popover = {};
  revealModalFocus(panel, active);
  assert.equal(
    panel.scrollTop,
    134,
    "date picker controls in the top layer do not scroll the underlying form",
  );
});
