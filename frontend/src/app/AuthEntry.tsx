import { useState, type FormEvent, type ReactNode } from "react";
import { api, errorMessage, type Session } from "../shared/api";
import { SectionTabs } from "../shared/SectionTabs";

export type AuthMode = "register" | "login";

export function AuthEntry({
  brand,
  mode,
  demo,
  expired,
  onMode,
  onAuthenticated,
}: {
  brand: ReactNode;
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
    <div className="login-page">
      <div className="login-brand">{brand}</div>
      <div className="login-form-wrap">
        <div className="login-form">
          <h1>{registering ? "Creează cont" : "Autentificare"}</h1>
          {expired && (
            <p className="notice" role="status">
              Sesiunea nu mai este activă. Intră din nou în cont.
            </p>
          )}
          <form onSubmit={submit}>
            {registering && (
              <label className="field">
                Nume
                <input
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
              <input
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
              <input
                type="password"
                required
                minLength={registering ? 12 : undefined}
                maxLength={200}
                autoComplete={registering ? "new-password" : "current-password"}
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
                  <input
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
                    <input
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
              <p className="error" role="alert">
                {error}
              </p>
            )}
            <button className="button primary full" disabled={busy}>
              {busy
                ? registering
                  ? "Se creează contul…"
                  : "Se conectează…"
                : registering
                  ? "Creează cont"
                  : "Intră în cont"}
            </button>
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
      </div>
    </div>
  );
}
