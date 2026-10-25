import { useState, type FormEvent } from "react";
import { CalendarDays, FolderHeart, Wallet } from "lucide-react";
import { api, errorMessage, type Session } from "../shared/api";
import { SectionTabs } from "../shared/SectionTabs";
import { Brand } from "../shared/Brand";
import { ActionButton } from "../shared/ActionButton";
import { FocusInput } from "../shared/FocusInput";
import { FeedbackMessage } from "../shared/FeedbackMessage";

const countryside = new URL(
  "../assets/home/warm-countryside.png",
  import.meta.url,
).href;

export type AuthMode = "register" | "login";

export function AuthEntry({
  mode,
  demo,
  expired,
  onMode,
  onAuthenticated,
}: {
  mode: AuthMode;
  demo: boolean;
  expired: boolean;
  onMode: (mode: AuthMode) => void;
  onAuthenticated: (session: Session) => void;
}) {
  const [name, setName] = useState("");
  const [email, setEmail] = useState("");
  const [password, setPassword] = useState("");
  const [familyName, setFamilyName] = useState("");
  const [familyMode, setFamilyMode] = useState<"create" | "join">("create");
  const [code, setCode] = useState("");
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState("");
  const registering = mode === "register";

  async function submit(event: FormEvent) {
    event.preventDefault();
    if (busy) return;
    setBusy(true);
    setError("");
    try {
      onAuthenticated(
        await api<Session>(registering ? "/register" : "/login", {
          method: "POST",
          body: JSON.stringify(
            registering
              ? {
                  name: name.trim(),
                  email: email.trim(),
                  password,
                  family_name: familyName.trim(),
                  ...(familyMode === "join"
                    ? { invitation_code: code.trim() }
                    : {}),
                }
              : { email: email.trim(), password },
          ),
        }),
      );
    } catch (e) {
      setError(errorMessage(e));
    } finally {
      setBusy(false);
    }
  }

  return (
    <main className="login-page login-landing">
      <div className="login-layout">
        <div className="login-brand">
          <Brand />
        </div>
        <section className="login-form-wrap" aria-labelledby="auth-title">
          <div className="login-form">
            <h1 id="auth-title">
              {registering ? "Creează cont" : "Autentificare"}
            </h1>
            {expired && (
              <FeedbackMessage variant="notice">
                Sesiunea nu mai este activă. Intră din nou în cont.
              </FeedbackMessage>
            )}
            <form onSubmit={submit}>
              {registering && (
                <label className="field">
                  Nume
                  <FocusInput
                    required
                    maxLength={100}
                    autoComplete="name"
                    disabled={busy}
                    value={name}
                    onChange={(e) => setName(e.target.value)}
                  />
                </label>
              )}
              <label className="field">
                Adresa de email
                <FocusInput
                  type="email"
                  required
                  maxLength={254}
                  autoComplete="username"
                  autoCapitalize="none"
                  spellCheck={false}
                  disabled={busy}
                  value={email}
                  onChange={(e) => setEmail(e.target.value)}
                />
              </label>
              <label className="field">
                Parola
                <FocusInput
                  type="password"
                  required
                  minLength={registering ? 12 : undefined}
                  maxLength={200}
                  autoComplete={
                    registering ? "new-password" : "current-password"
                  }
                  disabled={busy}
                  value={password}
                  onChange={(e) => setPassword(e.target.value)}
                  aria-describedby={
                    registering ? "registration-password-hint" : undefined
                  }
                />
                {registering && (
                  <small id="registration-password-hint">
                    Minimum 12 caractere.
                  </small>
                )}
              </label>
              {registering && (
                <>
                  <SectionTabs
                    label="Familie"
                    value={familyMode}
                    options={[
                      { value: "create", label: "Familie nouă" },
                      { value: "join", label: "Am un cod" },
                    ]}
                    onChange={(value) => {
                      if (!busy) {
                        setFamilyMode(value);
                        setError("");
                      }
                    }}
                  />
                  <label className="field">
                    Numele familiei
                    <FocusInput
                      required
                      maxLength={100}
                      autoComplete="off"
                      disabled={busy}
                      value={familyName}
                      onChange={(e) => setFamilyName(e.target.value)}
                    />
                  </label>
                  {familyMode === "join" && (
                    <label className="field">
                      Cod de invitație
                      <FocusInput
                        required
                        maxLength={200}
                        autoComplete="off"
                        autoCapitalize="none"
                        spellCheck={false}
                        disabled={busy}
                        value={code}
                        onChange={(e) => setCode(e.target.value)}
                      />
                    </label>
                  )}
                </>
              )}
              {error && (
                <FeedbackMessage variant="error">{error}</FeedbackMessage>
              )}
              <ActionButton
                type="submit"
                className="button primary full"
                loading={busy}
                loadingLabel={
                  registering ? "Se creează contul…" : "Se conectează…"
                }
              >
                {registering ? "Creează cont" : "Intră în cont"}
              </ActionButton>
            </form>
            <button
              className="text-button auth-switch"
              disabled={busy}
              onClick={() => {
                setError("");
                onMode(registering ? "login" : "register");
              }}
            >
              {registering ? "Ai deja cont? Intră în cont" : "Creează cont"}
            </button>
            {demo && !registering && (
              <div className="demo-accounts">
                <p>Conturi demo</p>
                <div>
                  {["ana", "mihai", "elena"].map((account) => (
                    <button
                      key={account}
                      disabled={busy}
                      onClick={() => {
                        setEmail(`${account}@example.test`);
                        setPassword("Familie-Demo-2026!");
                        setError("");
                      }}
                    >
                      {account === "ana"
                        ? "Ana"
                        : account === "mihai"
                          ? "Mihai"
                          : "Elena"}
                    </button>
                  ))}
                </div>
                <small>Date fictive.</small>
              </div>
            )}
          </div>
        </section>
        <section className="login-intro" aria-labelledby="login-intro-title">
          <h2 id="login-intro-title">Organizează viața de familie.</h2>
          <p>
            Amora aduce la un loc lucrurile de care aveți nevoie zi de zi și
            amintirile pe care vreți să le păstrați.
          </p>
          <ul className="login-features">
            <li>
              <FolderHeart size={22} strokeWidth={1.65} aria-hidden="true" />
              <span>Documente și amintiri, cu acces pentru cei apropiați.</span>
            </li>
            <li>
              <CalendarDays size={22} strokeWidth={1.65} aria-hidden="true" />
              <span>Calendar, sarcini și mese, organizate împreună.</span>
            </li>
            <li>
              <Wallet size={22} strokeWidth={1.65} aria-hidden="true" />
              <span>Cheltuielile familiei, ușor de urmărit.</span>
            </li>
          </ul>
          <img className="login-landscape" src={countryside} alt="" />
        </section>
      </div>
    </main>
  );
}
