import { useEffect, useRef, useState } from "react";
import {
  Eye,
  EyeOff,
  ExternalLink,
  RefreshCw,
  UserPlus,
  X,
} from "lucide-react";
import { api, ApiError, errorMessage } from "../../shared/api";
import { dateLabel } from "../../shared/calendarDates";
import { Modal } from "../../shared/Modal";
import { observePageResume } from "../../shared/pageResume";
import { observeCredentialPrivacy } from "./subscriptionPrivacy";
import {
  subscriptionFrequency,
  subscriptionInvitationExpiry,
  subscriptionMoney,
  type Subscription,
  type SubscriptionCredentials,
  type SubscriptionShare,
} from "./subscriptionData";

type ShareResult = {
  subscription: Subscription;
  items: SubscriptionShare[];
  invitation_code?: string;
};

export function SubscriptionDetail({
  base,
  subscription,
  onClose,
  onEdit,
  onChanged,
  onUnavailable,
}: {
  base: string;
  subscription: Subscription;
  onClose: () => void;
  onEdit: (item: Subscription) => void;
  onChanged: (item: Subscription | null) => void;
  onUnavailable: () => void;
}) {
  const [source, setSource] = useState(subscription);
  const [credentials, setCredentials] =
    useState<SubscriptionCredentials | null>(null);
  const [shares, setShares] = useState<SubscriptionShare[] | null>(null);
  const [sharing, setSharing] = useState(false);
  const [email, setEmail] = useState("");
  const [invitationToken, setInvitationToken] = useState("");
  const [invitationCode, setInvitationCode] = useState<string | null>(null);
  const [copied, setCopied] = useState(false);
  const [copyFallback, setCopyFallback] = useState(false);
  const [busy, setBusy] = useState(false);
  const [revealing, setRevealing] = useState(false);
  const [error, setError] = useState("");
  const [notice, setNotice] = useState("");
  const [stale, setStale] = useState(false);
  const pending = useRef(false);
  const controller = useRef<AbortController | null>(null);
  const revealRequest = useRef<AbortController | null>(null);
  const active = useRef(true);
  const privacyRevision = useRef(0);
  const codeState = useRef({ present: false, copied: false, token: false });
  codeState.current = {
    present: invitationCode !== null,
    copied,
    token: Boolean(invitationToken),
  };
  const url = `${base}/${source.id}`;
  function hideCredentials() {
    revealRequest.current?.abort();
    revealRequest.current = null;
    setCredentials(null);
    setRevealing(false);
  }
  useEffect(() => {
    active.current = true;
    const stop = observeCredentialPrivacy(document, window, () => {
      privacyRevision.current++;
      hideCredentials();
      if (codeState.current.present && !codeState.current.copied)
        setNotice(
          "Codul invitației a fost șters când ai părăsit pagina. Revocă invitația și creează una nouă pentru un alt cod.",
        );
      else if (codeState.current.token)
        setNotice(
          "Codul invitației a fost șters când ai părăsit pagina. Introdu-l din nou.",
        );
      setInvitationCode(null);
      setInvitationToken("");
      setCopied(false);
      setCopyFallback(false);
    });
    return () => {
      active.current = false;
      controller.current?.abort();
      revealRequest.current?.abort();
      stop();
    };
  }, []);
  useEffect(
    () =>
      observePageResume(document, window, () => {
        if (!pending.current) void reload();
      }),
    [],
  );
  function handleError(error: unknown) {
    setError(errorMessage(error));
    if (error instanceof ApiError) {
      if ([409, 412].includes(error.status)) {
        setStale(true);
        hideCredentials();
      }
      if ([401, 403, 404, 419].includes(error.status)) {
        hideCredentials();
        onUnavailable();
      }
    }
  }
  function update(item: Subscription) {
    setSource(item);
    hideCredentials();
    onChanged(item);
  }
  async function reveal() {
    if (credentials) {
      hideCredentials();
      return;
    }
    if (revealing || busy || stale || !source.has_credentials) return;
    const request = new AbortController();
    revealRequest.current = request;
    setRevealing(true);
    setError("");
    try {
      const result = await api<SubscriptionCredentials>(`${url}/credentials`, {
        signal: request.signal,
      });
      if (
        !request.signal.aborted &&
        active.current &&
        document.visibilityState === "visible"
      )
        setCredentials(result);
    } catch (error) {
      if (!request.signal.aborted && active.current) handleError(error);
    } finally {
      if (!request.signal.aborted && active.current) setRevealing(false);
    }
  }
  async function reload() {
    if (pending.current) return;
    const request = new AbortController();
    controller.current = request;
    pending.current = true;
    setBusy(true);
    setError("");
    hideCredentials();
    setInvitationCode(null);
    try {
      const fresh = await api<Subscription>(url, { signal: request.signal });
      if (request.signal.aborted) return;
      setSource(fresh);
      onChanged(fresh);
      setStale(false);
      setShares(null);
      setSharing(false);
    } catch (error) {
      if (!request.signal.aborted) handleError(error);
    } finally {
      pending.current = false;
      if (!request.signal.aborted) setBusy(false);
    }
  }
  async function invitation(accept: boolean) {
    if (pending.current || stale) return;
    if (accept && !invitationToken.trim()) return;
    const request = new AbortController();
    controller.current = request;
    pending.current = true;
    setBusy(true);
    setError("");
    try {
      const result = await api<Subscription | undefined>(
        `${url}/${accept ? "accept" : "decline"}`,
        {
          method: "POST",
          signal: request.signal,
          headers: { "If-Match": `"${source.version}"` },
          body: accept
            ? JSON.stringify({ token: invitationToken.trim() })
            : undefined,
        },
      );
      if (request.signal.aborted) return;
      if (result) {
        setInvitationToken("");
        update(result);
        setNotice("Abonamentul este disponibil în Partajate cu mine.");
      } else {
        onChanged(null);
        onClose();
      }
    } catch (error) {
      if (!request.signal.aborted) {
        if (error instanceof ApiError && error.status === 403)
          setError(errorMessage(error));
        else handleError(error);
      }
    } finally {
      pending.current = false;
      if (!request.signal.aborted) setBusy(false);
    }
  }
  async function loadShares() {
    if (pending.current || stale) return;
    if (sharing) {
      setSharing(false);
      return;
    }
    if (shares) {
      setSharing(true);
      return;
    }
    const request = new AbortController();
    controller.current = request;
    pending.current = true;
    setBusy(true);
    setError("");
    try {
      const result = await api<{ items: SubscriptionShare[] }>(
        `${url}/shares`,
        { signal: request.signal },
      );
      if (!request.signal.aborted) {
        setShares(result.items);
        setSharing(true);
      }
    } catch (error) {
      if (!request.signal.aborted) handleError(error);
    } finally {
      pending.current = false;
      if (!request.signal.aborted) setBusy(false);
    }
  }
  async function changeShare(share?: SubscriptionShare) {
    if (pending.current || stale) return;
    const request = new AbortController();
    const privateRevision = privacyRevision.current;
    controller.current = request;
    pending.current = true;
    setBusy(true);
    setError("");
    setNotice("");
    setInvitationCode(null);
    setCopied(false);
    setCopyFallback(false);
    try {
      const result = await api<ShareResult>(
        `${url}/shares${share ? `/${share.id}` : ""}`,
        {
          method: share ? "DELETE" : "POST",
          signal: request.signal,
          headers: { "If-Match": `"${source.version}"` },
          body: share ? undefined : JSON.stringify({ email: email.trim() }),
        },
      );
      if (request.signal.aborted) return;
      update(result.subscription);
      setShares(result.items);
      setEmail("");
      const keepCode =
        privateRevision === privacyRevision.current &&
        document.visibilityState === "visible";
      if (!share && keepCode) setInvitationCode(result.invitation_code ?? null);
      setNotice(
        share
          ? "Accesul a fost revocat."
          : keepCode
            ? "Invitația este disponibilă în contul persoanei."
            : "Invitația a fost creată, dar codul a fost șters când ai părăsit pagina. Revocă invitația și creează una nouă pentru un alt cod.",
      );
    } catch (error) {
      if (!request.signal.aborted) handleError(error);
    } finally {
      pending.current = false;
      if (!request.signal.aborted) setBusy(false);
    }
  }
  async function copyCode() {
    if (!invitationCode) return;
    try {
      await navigator.clipboard.writeText(invitationCode);
      if (active.current) setCopied(true);
    } catch {
      if (active.current) setCopyFallback(true);
    }
  }
  const money = subscriptionMoney(source);
  const invited = source.access === "invited";
  return (
    <Modal
      title={source.service_name}
      onClose={() => {
        if (!pending.current) onClose();
      }}
      wide
    >
      <p className="subscription-author">Adăugat de {source.owner_name}</p>
      {invited ? (
        <>
          <p>
            {source.owner_name} îți oferă acces individual la acest abonament și
            la datele sale de conectare. Familia ta nu primește acces.
          </p>
          {source.invitation_expires_at && (
            <p className="subscription-access-hint">
              Invitația expiră la{" "}
              {subscriptionInvitationExpiry(source.invitation_expires_at)}.
            </p>
          )}
          <form
            onSubmit={(event) => {
              event.preventDefault();
              void invitation(true);
            }}
          >
            <label className="field">
              Codul invitației
              <input
                value={invitationToken}
                onChange={(event) => {
                  setInvitationToken(event.target.value);
                  setNotice("");
                }}
                required
                maxLength={64}
                autoComplete="off"
                autoCapitalize="none"
                spellCheck={false}
              />
            </label>
            <div className="subscription-actions">
              <button
                type="button"
                className="button secondary"
                disabled={busy || stale}
                onClick={() => void invitation(false)}
              >
                Refuză
              </button>
              <button
                className="button primary"
                disabled={busy || stale || !invitationToken.trim()}
              >
                Acceptă accesul
              </button>
            </div>
          </form>
        </>
      ) : (
        <>
          <dl className="subscription-details">
            {money && (
              <div>
                <dt>Cost</dt>
                <dd>
                  {money}
                  {source.recurrence && (
                    <span> · {subscriptionFrequency(source.recurrence)}</span>
                  )}
                </dd>
              </div>
            )}
            {!money && source.recurrence && (
              <div>
                <dt>Repetare</dt>
                <dd>{subscriptionFrequency(source.recurrence)}</dd>
              </div>
            )}
            {source.next_due_on && (
              <div>
                <dt>Următoarea plată</dt>
                <dd>
                  <time dateTime={source.next_due_on}>
                    {dateLabel(source.next_due_on)}
                  </time>
                </dd>
              </div>
            )}
            <div>
              <dt>Acces</dt>
              <dd>
                {source.access === "shared" ? "Partajat cu tine" : "Familie"}
              </dd>
            </div>
          </dl>
          {source.website_url && (
            <a
              className="button secondary subscription-website"
              href={source.website_url}
              target="_blank"
              rel="noopener noreferrer"
            >
              <ExternalLink size={17} aria-hidden="true" />
              Deschide serviciul
            </a>
          )}
          {source.note && <p className="subscription-note">{source.note}</p>}
          <section
            className="subscription-credentials"
            aria-label="Date de acces"
          >
            {source.has_credentials ? (
              <>
                <button
                  className="button secondary"
                  disabled={busy || revealing || stale}
                  onClick={() => void reveal()}
                >
                  {credentials ? (
                    <EyeOff size={18} aria-hidden="true" />
                  ) : (
                    <Eye size={18} aria-hidden="true" />
                  )}
                  {revealing
                    ? "Se încarcă…"
                    : credentials
                      ? "Ascunde datele de acces"
                      : "Arată datele de acces"}
                </button>
                {credentials && (
                  <dl className="subscription-details subscription-secret-values">
                    {credentials.login && (
                      <div>
                        <dt>Utilizator sau email</dt>
                        <dd>{credentials.login}</dd>
                      </div>
                    )}
                    {credentials.password && (
                      <div>
                        <dt>Parolă</dt>
                        <dd>{credentials.password}</dd>
                      </div>
                    )}
                    {credentials.pin && (
                      <div>
                        <dt>PIN</dt>
                        <dd>{credentials.pin}</dd>
                      </div>
                    )}
                  </dl>
                )}
              </>
            ) : (
              <p>Fără date de acces salvate.</p>
            )}
          </section>
          {source.can_edit && (
            <>
              <div className="subscription-actions">
                <button
                  className="button secondary"
                  disabled={busy || stale}
                  onClick={() => onEdit(source)}
                >
                  Editează abonamentul
                </button>
                <button
                  className="button secondary"
                  disabled={busy || stale}
                  aria-expanded={sharing}
                  onClick={() => void loadShares()}
                >
                  <UserPlus size={17} aria-hidden="true" />
                  Acces
                </button>
              </div>
              {sharing && (
                <section
                  className="subscription-sharing"
                  aria-label="Acces abonament"
                >
                  <p>
                    Familia vede automat abonamentul și datele de acces.
                    Invitațiile oferă acces doar persoanei care le acceptă.
                  </p>
                  <form
                    className="subscription-share-form"
                    onSubmit={(event) => {
                      event.preventDefault();
                      void changeShare();
                    }}
                  >
                    <label className="field">
                      Adresa de email
                      <input
                        type="email"
                        value={email}
                        onChange={(event) => setEmail(event.target.value)}
                        required
                        maxLength={320}
                        autoComplete="off"
                      />
                    </label>
                    <button className="button primary" disabled={busy || stale}>
                      Invită
                    </button>
                  </form>
                  {invitationCode && (
                    <div className="subscription-invitation-code">
                      <button
                        className="button secondary"
                        onClick={() => void copyCode()}
                      >
                        {copied ? "Cod copiat" : "Copiază codul"}
                      </button>
                      <p className="subscription-access-hint">
                        Trimite codul numai persoanei invitate. Este valabil 7
                        zile și nu mai poate fi afișat după închiderea
                        ferestrei.
                      </p>
                      {copyFallback && (
                        <label className="field">
                          Codul invitației
                          <input
                            readOnly
                            value={invitationCode}
                            onFocus={(event) => event.target.select()}
                          />
                        </label>
                      )}
                    </div>
                  )}
                  {shares?.length ? (
                    <ul className="subscription-shares">
                      {shares.map((share) => (
                        <li key={share.id}>
                          <div>
                            <strong>{share.name}</strong>
                            <span>{share.email}</span>
                            <span>
                              {share.status === "accepted"
                                ? "Acces acceptat"
                                : "Invitație în așteptare"}
                            </span>
                            {share.status === "pending" && share.expires_at && (
                              <span>
                                Expiră la{" "}
                                {subscriptionInvitationExpiry(share.expires_at)}
                              </span>
                            )}
                          </div>
                          <button
                            className="icon-button"
                            disabled={busy || stale}
                            aria-label={`Revocă accesul: ${share.email}`}
                            onClick={() => void changeShare(share)}
                          >
                            <X size={19} aria-hidden="true" />
                          </button>
                        </li>
                      ))}
                    </ul>
                  ) : (
                    <p>Nu ai invitat alte persoane.</p>
                  )}
                  <p className="subscription-access-hint">
                    Revocarea oprește accesul în Amora. Schimbă parola
                    serviciului dacă a fost deja văzută.
                  </p>
                </section>
              )}
            </>
          )}
        </>
      )}
      {notice && (
        <p className="notice" role="status">
          {notice}
        </p>
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
          <RefreshCw size={17} aria-hidden="true" />
          Reîncarcă abonamentul
        </button>
      )}
    </Modal>
  );
}
