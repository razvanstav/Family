import {
  useEffect,
  useRef,
  useState,
  type FormEvent,
  type MutableRefObject,
} from "react";
import { ExternalLink, LockKeyhole, Mail, Send } from "lucide-react";
import { api, ApiError, errorMessage } from "../../shared/api";
import { PageBackdrop } from "../../shared/PageBackdrop";
import { SectionTabs } from "../../shared/SectionTabs";
import { AdministrationOverview } from "./AdministrationOverview";
import { PublicRequests } from "./PublicRequests";
import "./administration.css";

type EmailSettings = {
  enabled: boolean;
  host: string;
  port: number;
  username: string;
  has_password: boolean;
  from_address: string;
  from_name: string;
  reply_to: string | null;
  version: number;
};

export type AdministrationMemory = {
  section: "overview" | "email" | "requests";
  email?: {
    saved: EmailSettings;
    draft: EmailSettings;
    password: string;
  };
};

const endpoint = "/admin/email-settings";

export function Administration({
  userEmail,
  onExpired,
  memory,
}: {
  userEmail: string;
  onExpired: () => void;
  memory: MutableRefObject<AdministrationMemory>;
}) {
  const [section, setSection] = useState(memory.current.section);
  return (
    <div className="administration-page">
      <PageBackdrop image="home">
        <div className="page-heading">
          <h1>Administrare</h1>
        </div>
      </PageBackdrop>
      <SectionTabs
        label="Administrare"
        value={section}
        options={[
          { value: "overview", label: "Prezentare" },
          { value: "email", label: "Email" },
          { value: "requests", label: "Solicitări" },
        ]}
        onChange={(value) => {
          memory.current.section = value;
          setSection(value);
        }}
      />
      {section === "overview" ? (
        <AdministrationOverview onExpired={onExpired} />
      ) : section === "requests" ? (
        <PublicRequests onExpired={onExpired} />
      ) : (
        <EmailConfiguration
          userEmail={userEmail}
          onExpired={onExpired}
          memory={memory}
        />
      )}
    </div>
  );
}

function EmailConfiguration({
  userEmail,
  onExpired,
  memory,
}: {
  userEmail: string;
  onExpired: () => void;
  memory: MutableRefObject<AdministrationMemory>;
}) {
  const expired = useRef(onExpired);
  useEffect(() => {
    expired.current = onExpired;
  }, [onExpired]);
  const [settings, setSettings] = useState(memory.current.email);
  const [loading, setLoading] = useState(!memory.current.email);
  const [revision, setRevision] = useState(0);
  const [error, setError] = useState("");

  useEffect(() => {
    const scope = memory.current;
    if (scope.email) {
      setSettings(scope.email);
      setLoading(false);
      return;
    }
    const controller = new AbortController();
    setLoading(true);
    setError("");
    api<EmailSettings>(endpoint, { signal: controller.signal })
      .then((data) => {
        if (controller.signal.aborted || memory.current !== scope) return;
        const cached = { saved: data, draft: data, password: "" };
        scope.email = cached;
        setSettings(cached);
      })
      .catch((error) => {
        if (controller.signal.aborted) return;
        setSettings(undefined);
        setError(errorMessage(error));
        if (error instanceof ApiError && [401, 403, 419].includes(error.status))
          expired.current();
      })
      .finally(() => {
        if (!controller.signal.aborted) setLoading(false);
      });
    return () => controller.abort();
  }, [revision]);

  return (
    <>
      {loading ? (
        <p role="status">Se încarcă setările…</p>
      ) : settings ? (
        <EmailSettingsForm
          key={revision}
          initial={settings}
          userEmail={userEmail}
          onExpired={onExpired}
          memory={memory}
          onReload={() => {
            delete memory.current.email;
            setSettings(undefined);
            setLoading(true);
            setRevision((value) => value + 1);
          }}
        />
      ) : (
        <>
          <p className="error" role="alert">
            {error}
          </p>
          <button
            className="button secondary"
            onClick={() => setRevision((value) => value + 1)}
          >
            Reîncearcă
          </button>
        </>
      )}
    </>
  );
}

function EmailSettingsForm({
  initial,
  userEmail,
  onExpired,
  onReload,
  memory,
}: {
  initial: NonNullable<AdministrationMemory["email"]>;
  userEmail: string;
  onExpired: () => void;
  onReload: () => void;
  memory: MutableRefObject<AdministrationMemory>;
}) {
  const [saved, setSaved] = useState(initial.saved);
  const [draft, setDraft] = useState(initial.draft);
  const [password, setPassword] = useState(initial.password);
  const [recipient, setRecipient] = useState(userEmail);
  const [busy, setBusy] = useState(false);
  const [testing, setTesting] = useState(false);
  const [stale, setStale] = useState(false);
  const [error, setError] = useState("");
  const [notice, setNotice] = useState("");
  const [testError, setTestError] = useState("");
  const [testNotice, setTestNotice] = useState("");
  const dirty =
    !!password ||
    (Object.keys(draft) as (keyof EmailSettings)[]).some(
      (key) => draft[key] !== saved[key],
    );
  const disabled = busy || testing;

  function remember(
    patch: Partial<NonNullable<AdministrationMemory["email"]>>,
  ) {
    if (memory.current.email === initial) Object.assign(initial, patch);
  }

  function change<K extends keyof EmailSettings>(
    key: K,
    value: EmailSettings[K],
  ) {
    const next = { ...draft, [key]: value };
    remember({ draft: next });
    setDraft(next);
    setNotice("");
    setTestNotice("");
  }

  function unavailable(error: unknown) {
    if (error instanceof ApiError && [401, 403, 419].includes(error.status))
      onExpired();
  }

  async function save(event: FormEvent) {
    event.preventDefault();
    if (disabled || stale) return;
    setBusy(true);
    setError("");
    setNotice("");
    setTestNotice("");
    setTestError("");
    try {
      const result = await api<EmailSettings>(endpoint, {
        method: "PUT",
        body: JSON.stringify({
          enabled: draft.enabled,
          host: draft.host,
          port: draft.port,
          username: draft.username.trim(),
          from_address: draft.from_address.trim(),
          from_name: draft.from_name.trim(),
          reply_to: draft.reply_to?.trim() || null,
          version: saved.version,
          ...(password ? { password } : {}),
        }),
      });
      remember({ saved: result, draft: result, password: "" });
      setSaved(result);
      setDraft(result);
      setPassword("");
      setNotice("Setările au fost salvate.");
    } catch (error) {
      setError(errorMessage(error));
      if (error instanceof ApiError && [409, 412].includes(error.status))
        setStale(true);
      unavailable(error);
    } finally {
      setBusy(false);
    }
  }

  async function test(event: FormEvent) {
    event.preventDefault();
    if (disabled || dirty || stale || !saved.enabled) return;
    setTesting(true);
    setTestError("");
    setTestNotice("");
    try {
      const result = await api<{ message: string }>("/admin/email-test", {
        method: "POST",
        body: JSON.stringify({ recipient: recipient.trim() }),
      });
      setTestNotice(result.message);
    } catch (error) {
      setTestError(errorMessage(error));
      unavailable(error);
    } finally {
      setTesting(false);
    }
  }

  return (
    <>
      <div className="section-header admin-section-header">
        <h2>Email</h2>
        <span className={`admin-status${saved.enabled ? " enabled" : ""}`}>
          {saved.enabled ? "Activat" : "Dezactivat"}
        </span>
      </div>
      <form onSubmit={save}>
        <fieldset className="admin-fields" disabled={disabled || stale}>
          <label className="admin-toggle">
            <input
              type="checkbox"
              checked={draft.enabled}
              onChange={(event) => change("enabled", event.target.checked)}
            />
            Trimitere email
          </label>
          <div className="admin-email-grid">
            <section className="admin-panel" aria-labelledby="admin-smtp-title">
              <div className="admin-panel-heading">
                <h3 id="admin-smtp-title">Brevo SMTP</h3>
                <a
                  href="https://app.brevo.com/settings/keys/smtp"
                  target="_blank"
                  rel="noopener noreferrer"
                >
                  Deschide Brevo <ExternalLink size={15} aria-hidden="true" />
                </a>
              </div>
              <div className="admin-server-fields">
                <label className="field">
                  Server SMTP
                  <input value={draft.host} readOnly />
                </label>
                <label className="field">
                  Port
                  <input value={draft.port} readOnly />
                </label>
              </div>
              <p className="admin-tls">
                <LockKeyhole size={16} aria-hidden="true" /> STARTTLS
                obligatoriu
              </p>
              <label className="field">
                Login SMTP
                <input
                  value={draft.username}
                  required={draft.enabled}
                  maxLength={254}
                  autoComplete="off"
                  autoCapitalize="none"
                  spellCheck={false}
                  onChange={(event) => change("username", event.target.value)}
                  aria-describedby="smtp-login-hint"
                />
                <small id="smtp-login-hint">
                  Copiază câmpul „Login” din Brevo → SMTP.
                </small>
              </label>
              <label className="field">
                Cheie SMTP
                <input
                  type="password"
                  value={password}
                  required={draft.enabled && !saved.has_password}
                  maxLength={500}
                  autoComplete="new-password"
                  placeholder={saved.has_password ? "Cheie salvată" : ""}
                  onChange={(event) => {
                    remember({ password: event.target.value });
                    setPassword(event.target.value);
                    setNotice("");
                    setTestNotice("");
                  }}
                  aria-describedby="smtp-key-hint"
                />
                <small id="smtp-key-hint">
                  {saved.has_password
                    ? "Lasă gol pentru a păstra cheia existentă."
                    : "Folosește cheia SMTP, nu cheia API Brevo."}
                </small>
              </label>
            </section>
            <section
              className="admin-panel"
              aria-labelledby="admin-sender-title"
            >
              <div className="admin-panel-heading">
                <h3 id="admin-sender-title">Expeditor</h3>
                <a
                  href="https://app.brevo.com/senders"
                  target="_blank"
                  rel="noopener noreferrer"
                >
                  Expeditori Brevo <ExternalLink size={15} aria-hidden="true" />
                </a>
              </div>
              <label className="field">
                Nume expeditor
                <input
                  value={draft.from_name}
                  required={draft.enabled}
                  maxLength={100}
                  autoComplete="off"
                  onChange={(event) => change("from_name", event.target.value)}
                />
              </label>
              <label className="field">
                Email expeditor
                <input
                  type="email"
                  value={draft.from_address}
                  required={draft.enabled}
                  maxLength={254}
                  autoComplete="off"
                  onChange={(event) =>
                    change("from_address", event.target.value)
                  }
                  aria-describedby="smtp-sender-hint"
                />
                <small id="smtp-sender-hint">
                  Adresa sau domeniul trebuie validat în Brevo.
                </small>
              </label>
              <label className="field">
                Email pentru răspunsuri (opțional)
                <input
                  type="email"
                  value={draft.reply_to ?? ""}
                  maxLength={254}
                  autoComplete="off"
                  onChange={(event) =>
                    change("reply_to", event.target.value || null)
                  }
                />
              </label>
            </section>
          </div>
        </fieldset>
        {error && (
          <p className="error" role="alert">
            {error}
          </p>
        )}
        {notice && (
          <p className="notice" role="status">
            {notice}
          </p>
        )}
        <div className="admin-actions">
          <button
            className="button primary"
            disabled={disabled || stale || !dirty}
          >
            {busy ? "Se salvează…" : "Salvează"}
          </button>
          {stale && (
            <button
              type="button"
              className="button secondary"
              onClick={onReload}
              disabled={disabled}
            >
              Renunță la modificări și reîncarcă
            </button>
          )}
        </div>
      </form>
      <section
        className="admin-panel admin-test"
        aria-labelledby="admin-test-title"
      >
        <h2 id="admin-test-title">
          <Mail size={22} aria-hidden="true" /> Test email
        </h2>
        <form onSubmit={test} className="admin-test-form">
          <label className="field">
            Destinatar
            <input
              type="email"
              required
              value={recipient}
              maxLength={254}
              autoComplete="off"
              disabled={disabled}
              onChange={(event) => {
                setRecipient(event.target.value);
                setTestNotice("");
                setTestError("");
              }}
              aria-describedby={
                !saved.enabled || dirty || stale ? "smtp-test-hint" : undefined
              }
            />
          </label>
          <button
            className="button secondary"
            disabled={disabled || dirty || stale || !saved.enabled}
          >
            <Send size={18} aria-hidden="true" />{" "}
            {testing ? "Se trimite…" : "Trimite email de test"}
          </button>
        </form>
        {(!saved.enabled || dirty || stale) && (
          <p id="smtp-test-hint" className="admin-help">
            Salvează setările și activează trimiterea înainte de test.
          </p>
        )}
        {testError && (
          <p className="error" role="alert">
            {testError}
          </p>
        )}
        {testNotice && (
          <p className="notice" role="status">
            {testNotice}
          </p>
        )}
      </section>
    </>
  );
}
