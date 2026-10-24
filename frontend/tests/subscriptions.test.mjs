import assert from "node:assert/strict";
import { readFile } from "node:fs/promises";
import test from "node:test";
import ts from "typescript";

const source = await readFile(
  new URL(
    "../src/modules/subscriptions/subscriptionPrivacy.ts",
    import.meta.url,
  ),
  "utf8",
);
const js = ts.transpileModule(source, {
  compilerOptions: {
    target: ts.ScriptTarget.ES2022,
    module: ts.ModuleKind.ES2022,
  },
}).outputText;
const { subscriptionSecretFields, observeCredentialPrivacy } = await import(
  `data:text/javascript;base64,${Buffer.from(js).toString("base64")}`
);

test("keeping or removing credentials never attaches draft secret values", () => {
  const fields = {
    login: " person@example.test ",
    password: "  secret spaces  ",
    pin: "0012",
  };
  assert.deepEqual(subscriptionSecretFields("keep", fields), {
    secrets_action: "keep",
  });
  assert.deepEqual(subscriptionSecretFields("remove", fields), {
    secrets_action: "remove",
  });
  assert.deepEqual(subscriptionSecretFields("replace", fields), {
    secrets_action: "replace",
    login: "person@example.test",
    password: "  secret spaces  ",
    pin: "0012",
  });
  assert.deepEqual(
    subscriptionSecretFields("replace", { login: "  ", password: "", pin: "" }),
    { secrets_action: "replace", login: null, password: null, pin: null },
  );
});

test("leaving a page clears credentials and cancels a pending reveal before returning", () => {
  const document = Object.assign(new EventTarget(), {
    visibilityState: "visible",
  });
  const window = new EventTarget();
  const controller = new AbortController();
  let revealed = { password: "test only" };
  let hides = 0;
  const stop = observeCredentialPrivacy(document, window, () => {
    hides++;
    revealed = null;
    controller.abort();
  });
  document.dispatchEvent(new Event("visibilitychange"));
  assert.equal(hides, 0);
  window.dispatchEvent(new Event("blur"));
  assert.equal(revealed, null);
  assert.equal(controller.signal.aborted, true);
  document.visibilityState = "hidden";
  document.dispatchEvent(new Event("visibilitychange"));
  window.dispatchEvent(new Event("pagehide"));
  assert.equal(hides, 3);
  document.visibilityState = "visible";
  document.dispatchEvent(new Event("visibilitychange"));
  window.dispatchEvent(new Event("focus"));
  assert.equal(revealed, null, "returning does not reveal credentials again");
  stop();
  window.dispatchEvent(new Event("blur"));
  document.visibilityState = "hidden";
  document.dispatchEvent(new Event("visibilitychange"));
  assert.equal(hides, 3, "cleanup removes every privacy listener");
});
