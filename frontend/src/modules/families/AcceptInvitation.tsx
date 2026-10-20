import { useEffect, useState, type FormEvent } from "react";
import { ArrowRight, LockKeyhole, Users } from "lucide-react";
import { api, type Session } from "../../shared/api";

type Details = {
  family_name: string;
  email: string;
  role: string;
  existing_account: boolean;
};

export function AcceptInvitation({
  invitation,
  session,
  onSession,
  onDone,
  onCancel,
}: {
  invitation: string;
  session: Session;
  onSession: (session: Session) => void;
  onDone: (session: Session, familyId: string) => void;
  onCancel: () => void;
}) {
  const [familyId, token] = invitation.split(".");
  const [details, setDetails] = useState<Details | null>(null);
  const [error, setError] = useState("");
  const [loading, setLoading] = useState(true);
  const [busy, setBusy] = useState(false);
  const [name, setName] = useState("");
  const [password, setPassword] = useState("");
  const [confirmation, setConfirmation] = useState("");
  useEffect(() => {
    let alive = true;
    setLoading(true);
    setError("");
    api<Details>(`/invitations/${encodeURIComponent(familyId)}/inspect`, {
      method: "POST",
      body: JSON.stringify({ token }),
    })
      .then((data) => {
        if (alive) setDetails(data);
      })
      .catch(() => {
        if (alive) {
          setDetails(null);
          setError("Invitația nu este disponibilă sau a expirat.");
        }
      })
      .finally(() => {
        if (alive) setLoading(false);
      });
    return () => {
      alive = false;
    };
  }, [invitation, session.user?.id]);
  const wrongAccount =
    !!session.user &&
    !!details &&
    session.user.email.toLowerCase() !== details.email;
  async function submit(e: FormEvent) {
    e.preventDefault();
    if (!details || busy) return;
    setBusy(true);
    setError("");
    try {
      if (!session.user && details.existing_account) {
        onSession(
          await api<Session>("/login", {
            method: "POST",
            body: JSON.stringify({ email: details.email, password }),
          }),
        );
        setPassword("");
      } else {
        onDone(
          await api<Session>(`/invitations/${familyId}/accept`, {
            method: "POST",
            body: JSON.stringify({
              token,
              name,
              password,
              password_confirmation: confirmation,
            }),
          }),
          familyId,
        );
      }
    } catch (e) {
      setError(
        e instanceof Error ? e.message : "Invitația nu a putut fi acceptată.",
      );
    } finally {
      setBusy(false);
    }
  }
  return (
    <div className="login-page invitation-page">
      <div className="login-form-wrap">
        <div className="login-form">
          <span className="join-icon">
            <Users size={28} aria-hidden="true" />
          </span>
          <h1>{details?.family_name ?? "Invitație"}</h1>
          {loading ? (
            <p role="status">Se încarcă…</p>
          ) : (
            details && (
              <>
                <p className="join-email">{details.email}</p>
                <p className="join-role">
                  {details.role === "limited" ? (
                    <LockKeyhole className="role-limited" size={17} />
                  ) : (
                    <Users className="role-adult" size={17} />
                  )}
                  {details.role === "limited"
                    ? "Acces limitat"
                    : "Membru adult"}
                </p>
                {wrongAccount ? (
                  <>
                    <p className="fine-print">
                      Ești conectat ca {session.user?.email}. Folosește contul
                      invitat.
                    </p>
                    <button
                      className="button primary full"
                      disabled={busy}
                      onClick={async () => {
                        setBusy(true);
                        setError("");
                        try {
                          onSession(
                            await api<Session>("/logout", { method: "POST" }),
                          );
                        } catch (e) {
                          setError(
                            e instanceof Error
                              ? e.message
                              : "Deconectarea nu a reușit.",
                          );
                        } finally {
                          setBusy(false);
                        }
                      }}
                    >
                      Schimbă contul
                    </button>
                  </>
                ) : (
                  <form onSubmit={submit}>
                    {!session.user && (
                      <>
                        {!details.existing_account && (
                          <label className="field">
                            Numele tău
                            <input
                              autoComplete="name"
                              required
                              maxLength={120}
                              value={name}
                              onChange={(e) => setName(e.target.value)}
                            />
                          </label>
                        )}
                        <label className="field">
                          Parola
                          <input
                            type="password"
                            autoComplete={
                              details.existing_account
                                ? "current-password"
                                : "new-password"
                            }
                            required
                            minLength={
                              details.existing_account ? undefined : 12
                            }
                            maxLength={200}
                            value={password}
                            onChange={(e) => setPassword(e.target.value)}
                          />
                        </label>
                        {!details.existing_account && (
                          <>
                            <p className="fine-print">Minimum 12 caractere.</p>
                            <label className="field">
                              Confirmă parola
                              <input
                                type="password"
                                autoComplete="new-password"
                                required
                                value={confirmation}
                                onChange={(e) =>
                                  setConfirmation(e.target.value)
                                }
                              />
                            </label>
                          </>
                        )}
                      </>
                    )}
                    <button className="button primary full" disabled={busy}>
                      {busy
                        ? "Se procesează…"
                        : !session.user && details.existing_account
                          ? "Intră în cont"
                          : !session.user
                            ? "Creează contul și acceptă"
                            : "Acceptă invitația"}
                      <ArrowRight size={18} />
                    </button>
                  </form>
                )}
              </>
            )
          )}
          {error && (
            <p className="error" role="alert">
              {error}
            </p>
          )}
          <button
            className="button secondary full"
            disabled={busy}
            onClick={onCancel}
          >
            Înapoi la aplicație
          </button>
        </div>
      </div>
    </div>
  );
}
