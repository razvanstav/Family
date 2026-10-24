import { useEffect, useRef, useState } from "react";
import { api, ApiError, errorMessage } from "../../shared/api";
import { DatePicker } from "../../shared/DatePicker";
import { Modal } from "../../shared/Modal";
import {
  observeCredentialPrivacy,
  subscriptionSecretFields,
} from "./subscriptionPrivacy";
import {
  subscriptionAmountInput,
  subscriptionRecurrences,
  subscriptionServices,
  type Subscription,
  type SubscriptionCurrency,
  type SubscriptionRecurrence,
} from "./subscriptionData";

export function SubscriptionForm({
  base,
  subscription,
  onClose,
  onSaved,
  onUnavailable,
}: {
  base: string;
  subscription: Subscription | null;
  onClose: () => void;
  onSaved: (item: Subscription | null) => void;
  onUnavailable: () => void;
}) {
  const [source, setSource] = useState(subscription);
  const [service, setService] = useState(
    subscription?.service_key ?? "netflix",
  );
  const [name, setName] = useState(subscription?.service_name ?? "Netflix");
  const [url, setUrl] = useState(
    subscription
      ? (subscription.website_url ?? "")
      : subscriptionServices[0].url,
  );
  const [amount, setAmount] = useState(
    subscriptionAmountInput(subscription?.amount_minor ?? null),
  );
  const [currency, setCurrency] = useState<SubscriptionCurrency>(
    subscription?.currency ?? "RON",
  );
  const [recurrence, setRecurrence] = useState<SubscriptionRecurrence>(
    subscription?.recurrence ?? "monthly",
  );
  const [date, setDate] = useState(subscription?.next_due_on ?? "");
  const [note, setNote] = useState(subscription?.note ?? "");
  const [secretAction, setSecretAction] = useState<
    "keep" | "replace" | "remove"
  >(subscription ? "keep" : "replace");
  const [login, setLogin] = useState("");
  const [password, setPassword] = useState("");
  const [pin, setPin] = useState("");
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState("");
  const [stale, setStale] = useState(false);
  const [deleting, setDeleting] = useState(false);
  const [privacyNotice, setPrivacyNotice] = useState("");
  const secretDraft = useRef(false);
  secretDraft.current = Boolean(login || password || pin);
  const controller = useRef<AbortController | null>(null);
  const pending = useRef(false);
  useEffect(() => () => controller.current?.abort(), []);

  function resetSecrets() {
    setLogin("");
    setPassword("");
    setPin("");
  }
  useEffect(() => {
    const hide = () => {
      if (secretDraft.current)
        setPrivacyNotice(
          "Datele de acces nesalvate au fost șterse când ai părăsit pagina. Introdu-le din nou.",
        );
      resetSecrets();
      if (subscription) setSecretAction("keep");
    };
    return observeCredentialPrivacy(document, window, hide);
  }, []);
  function changedService(key: string) {
    setService(key);
    const preset = subscriptionServices.find((item) => item.key === key);
    setName(preset?.name ?? "");
    setUrl(preset?.url ?? "");
  }
  function handleError(error: unknown) {
    setError(errorMessage(error));
    if (error instanceof ApiError) {
      if ([409, 412].includes(error.status)) setStale(true);
      if ([401, 403, 404, 419].includes(error.status)) onUnavailable();
    }
  }
  async function submit(remove = false) {
    if (pending.current || stale) return;
    if (
      !remove &&
      source &&
      secretAction === "replace" &&
      !login.trim() &&
      !password &&
      !pin
    ) {
      setError(
        "Completează datele noi de acces sau alege Șterge datele de acces.",
      );
      return;
    }
    const request = new AbortController();
    controller.current = request;
    pending.current = true;
    setBusy(true);
    setError("");
    try {
      const saved = await api<Subscription | undefined>(
        source ? `${base}/${source.id}` : base,
        {
          method: remove ? "DELETE" : source ? "PATCH" : "POST",
          signal: request.signal,
          headers: source ? { "If-Match": `"${source.version}"` } : undefined,
          body: remove
            ? undefined
            : JSON.stringify({
                service_key: service,
                service_name: name.trim(),
                website_url: url.trim() || null,
                amount: amount.trim() || null,
                currency,
                recurrence,
                next_due_on: date || null,
                note: note.trim() || null,
                ...subscriptionSecretFields(secretAction, {
                  login,
                  password,
                  pin,
                }),
              }),
        },
      );
      if (!request.signal.aborted) {
        resetSecrets();
        onSaved(saved ?? null);
      }
    } catch (error) {
      if (!request.signal.aborted) handleError(error);
    } finally {
      pending.current = false;
      if (!request.signal.aborted) setBusy(false);
    }
  }
  async function reload() {
    if (!source || pending.current) return;
    const request = new AbortController();
    controller.current = request;
    pending.current = true;
    setBusy(true);
    setError("");
    try {
      const fresh = await api<Subscription>(`${base}/${source.id}`, {
        signal: request.signal,
      });
      if (request.signal.aborted) return;
      if (!fresh.can_edit) {
        onUnavailable();
        return;
      }
      setSource(fresh);
      setService(fresh.service_key);
      setName(fresh.service_name);
      setUrl(fresh.website_url ?? "");
      setAmount(subscriptionAmountInput(fresh.amount_minor));
      setCurrency(fresh.currency ?? "RON");
      setRecurrence(fresh.recurrence ?? "monthly");
      setDate(fresh.next_due_on ?? "");
      setNote(fresh.note ?? "");
      setSecretAction("keep");
      resetSecrets();
      setStale(false);
      setDeleting(false);
    } catch (error) {
      if (!request.signal.aborted) handleError(error);
    } finally {
      pending.current = false;
      if (!request.signal.aborted) setBusy(false);
    }
  }
  return (
    <Modal
      title={
        deleting
          ? "Șterge abonamentul"
          : source
            ? "Editează abonamentul"
            : "Adaugă abonament"
      }
      onClose={() => {
        if (!pending.current) onClose();
      }}
      wide
    >
      {privacyNotice && (
        <p className="notice" role="status">
          {privacyNotice}
        </p>
      )}
      {deleting ? (
        <>
          <p>
            Ștergi {source?.service_name}? Familia și persoanele invitate nu îl
            vor mai putea deschide, iar datele de acces salvate vor fi șterse.
          </p>
          <div className="subscription-actions">
            <button
              className="button secondary"
              disabled={busy}
              onClick={() => setDeleting(false)}
            >
              Înapoi
            </button>
            <button
              className="button danger"
              disabled={busy || stale}
              onClick={() => void submit(true)}
            >
              {busy ? "Se șterge…" : "Șterge"}
            </button>
          </div>
        </>
      ) : (
        <form
          onSubmit={(event) => {
            event.preventDefault();
            void submit();
          }}
        >
          <fieldset className="subscription-fields" disabled={busy || stale}>
            <label className="field">
              Serviciu
              <select
                value={service}
                onChange={(event) => changedService(event.target.value)}
              >
                {subscriptionServices.map((item) => (
                  <option key={item.key} value={item.key}>
                    {item.name}
                  </option>
                ))}
                <option value="custom">Alt serviciu</option>
              </select>
            </label>
            {service === "custom" && (
              <label className="field">
                Numele serviciului
                <input
                  value={name}
                  onChange={(event) => setName(event.target.value)}
                  maxLength={120}
                  required
                />
              </label>
            )}
            <label className="field">
              Linkul serviciului (opțional)
              <input
                type="url"
                pattern="https://.+"
                title="Folosește un link HTTPS."
                value={url}
                onChange={(event) => setUrl(event.target.value)}
                maxLength={2048}
              />
            </label>
            <div className="subscription-form-pair">
              <label className="field">
                Cost (opțional)
                <input
                  inputMode="decimal"
                  value={amount}
                  onChange={(event) => setAmount(event.target.value)}
                  pattern="(?:0|[1-9][0-9]{0,5})(?:[.,][0-9]{1,2})?"
                  title="Sumă cu cel mult două zecimale."
                  maxLength={9}
                />
              </label>
              <label className="field">
                Monedă
                <select
                  value={currency}
                  onChange={(event) =>
                    setCurrency(event.target.value as SubscriptionCurrency)
                  }
                >
                  <option value="RON">Lei</option>
                  <option value="EUR">Euro</option>
                  <option value="USD">Dolari americani</option>
                </select>
              </label>
            </div>
            <div className="subscription-form-pair">
              <label className="field">
                Repetare
                <select
                  value={recurrence}
                  onChange={(event) =>
                    setRecurrence(event.target.value as SubscriptionRecurrence)
                  }
                >
                  {subscriptionRecurrences.map((item) => (
                    <option key={item.value} value={item.value}>
                      {item.label}
                    </option>
                  ))}
                </select>
              </label>
              <DatePicker
                value={date}
                onChange={setDate}
                label="Următoarea plată (opțional)"
                name="next_due_on"
                optional
                max="2100-12-31"
                emptyLabel="Fără dată"
              />
            </div>
            <label className="field">
              Notă (opțional)
              <textarea
                value={note}
                onChange={(event) => setNote(event.target.value)}
                maxLength={1000}
                rows={2}
              />
            </label>
            {source && (
              <label className="field">
                Date de acces
                <select
                  value={secretAction}
                  onChange={(event) => {
                    setSecretAction(event.target.value as typeof secretAction);
                    resetSecrets();
                  }}
                >
                  <option value="keep">Păstrează datele existente</option>
                  <option value="replace">Înlocuiește datele de acces</option>
                  <option value="remove">Șterge datele de acces</option>
                </select>
              </label>
            )}
            {secretAction === "replace" && (
              <>
                <label className="field">
                  Utilizator sau email (opțional)
                  <input
                    value={login}
                    onChange={(event) => {
                      setLogin(event.target.value);
                      setPrivacyNotice("");
                    }}
                    autoComplete="off"
                    maxLength={320}
                    spellCheck={false}
                  />
                </label>
                <label className="field">
                  Parolă (opțional)
                  <input
                    type="password"
                    value={password}
                    onChange={(event) => {
                      setPassword(event.target.value);
                      setPrivacyNotice("");
                    }}
                    autoComplete="new-password"
                    maxLength={500}
                  />
                </label>
                <label className="field">
                  PIN (opțional)
                  <input
                    type="password"
                    inputMode="numeric"
                    value={pin}
                    onChange={(event) => {
                      setPin(event.target.value);
                      setPrivacyNotice("");
                    }}
                    autoComplete="off"
                    maxLength={32}
                  />
                </label>
              </>
            )}
            <p className="subscription-access-hint">
              Familia vede automat abonamentul și datele de acces. Poți invita
              separat alte conturi prin email.
            </p>
          </fieldset>
          <div className="subscription-actions">
            {source && (
              <button
                type="button"
                className="button danger"
                disabled={busy || stale}
                onClick={() => setDeleting(true)}
              >
                Șterge abonamentul
              </button>
            )}
            <button
              type="button"
              className="button secondary"
              disabled={busy}
              onClick={onClose}
            >
              Anulează
            </button>
            <button className="button primary" disabled={busy || stale}>
              {busy ? "Se salvează…" : "Salvează"}
            </button>
          </div>
        </form>
      )}
      {error && (
        <p className="error" role="alert">
          {error}
        </p>
      )}
      {stale && (
        <button
          className="button secondary"
          disabled={busy}
          onClick={() => void reload()}
        >
          Reîncarcă abonamentul
        </button>
      )}
    </Modal>
  );
}
