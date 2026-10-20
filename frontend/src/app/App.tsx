import { useEffect, useState, type FormEvent } from "react";
import {
  ArrowRight,
  ChevronDown,
  FolderHeart,
  Heart,
  LogOut,
  Search,
  Users,
} from "lucide-react";
import { api, type Session } from "../shared/api";
import { Documents } from "../modules/documents/Documents";
import { FamilyPage } from "../modules/families/Family";
import { AcceptInvitation } from "../modules/families/AcceptInvitation";
import { Modal } from "../shared/Modal";

export default function App() {
  const [session, setSession] = useState<Session | null>(null);
  const [familyId, setFamilyId] = useState("");
  const [error, setError] = useState("");
  const [page, setPage] = useState<"documents" | "family">("documents");
  const [account, setAccount] = useState(false);
  const [invitation, setInvitation] = useState(() =>
    new URLSearchParams(window.location.hash.slice(1)).get("invite"),
  );
  useEffect(() => {
    const changed = () =>
      setInvitation(
        new URLSearchParams(window.location.hash.slice(1)).get("invite"),
      );
    window.addEventListener("hashchange", changed);
    return () => window.removeEventListener("hashchange", changed);
  }, []);
  useEffect(() => {
    document.title = `${invitation ? "Invitație" : page === "documents" ? "Documente" : "Familia mea"} · Memoria Familiei`;
  }, [page, invitation]);
  async function refresh() {
    try {
      const data = await api<Session>("/session");
      setSession(data);
      setFamilyId((id) =>
        data.families.some((f) => f.id === id)
          ? id
          : (data.families[0]?.id ?? ""),
      );
      setError("");
    } catch {
      setError(
        "Nu am putut contacta aplicația. Verifică dacă serverul este pornit.",
      );
    }
  }
  useEffect(() => {
    void refresh();
    const checkSession = () => {
      if (document.visibilityState === "visible") void refresh();
    };
    document.addEventListener("visibilitychange", checkSession);
    return () => document.removeEventListener("visibilitychange", checkSession);
  }, []);
  const family = session?.families.find((f) => f.id === familyId);
  if (!session)
    return (
      <div className="startup">
        <Brand />
        <p role="status">{error || "Pregătim spațiul familiei…"}</p>
        {error && (
          <button className="button primary" onClick={() => void refresh()}>
            Reîncearcă
          </button>
        )}
      </div>
    );
  if (invitation)
    return (
      <AcceptInvitation
        key={invitation}
        invitation={invitation}
        session={session}
        onSession={setSession}
        onDone={(data, id) => {
          setSession(data);
          setFamilyId(id);
          setPage("family");
          setInvitation(null);
          window.history.replaceState(null, "", window.location.pathname);
        }}
        onCancel={() => {
          setInvitation(null);
          window.history.replaceState(null, "", window.location.pathname);
          void refresh();
        }}
      />
    );
  if (!session.user)
    return (
      <Login
        demo={session.demo}
        onLogin={(data) => {
          setSession(data);
          setFamilyId(data.families[0]?.id ?? "");
        }}
      />
    );
  async function logout() {
    try {
      setSession(await api<Session>("/logout", { method: "POST" }));
      setAccount(false);
      setFamilyId("");
    } catch {
      setError("Deconectarea nu a reușit. Reîncearcă.");
    }
  }
  const initials = session.user.name
    .split(" ")
    .map((n) => n[0])
    .slice(0, 2)
    .join("");
  return (
    <div className="app-shell">
      <aside className="sidebar">
        <Brand />
        <div className="sidebar-family">
          <span className="family-symbol">
            <Heart size={23} />
          </span>
          <strong>{family?.name ?? "Familia ta"}</strong>
        </div>
        <nav aria-label="Navigație principală">
          <button
            className={page === "documents" ? "active" : ""}
            onClick={() => setPage("documents")}
          >
            <FolderHeart size={21} /> Documente <span className="nav-dot" />
          </button>
          <button
            className={page === "family" ? "active" : ""}
            onClick={() => setPage("family")}
          >
            <Users size={21} /> Familia mea
          </button>
        </nav>
        <div className="sidebar-bottom">
          <button className="sidebar-profile" onClick={() => setAccount(true)}>
            <span className="avatar">{initials}</span>
            <span>
              <strong>{session.user.name}</strong>
              <small>Contul meu</small>
            </span>
            <ChevronDown size={16} />
          </button>
        </div>
      </aside>
      <div className="main-column">
        <header className="topbar">
          <div className="mobile-brand">
            <button
              className="avatar"
              aria-label="Contul meu"
              onClick={() => setAccount(true)}
            >
              {initials}
            </button>
          </div>
          <span className="desktop-breadcrumb">
            Spațiul familiei <span>/</span>{" "}
            <strong>{page === "documents" ? "Documente" : "Membri"}</strong>
          </span>
          <div className="topbar-actions">
            <label className="family-selector">
              <Heart size={16} />
              <select
                aria-label="Familia activă"
                value={familyId}
                onChange={(e) => {
                  setFamilyId(e.target.value);
                  setPage("documents");
                }}
              >
                {session.families.map((f) => (
                  <option key={f.id} value={f.id}>
                    {f.name}
                  </option>
                ))}
              </select>
              <ChevronDown size={14} />
            </label>
            <button
              className="avatar profile-button"
              aria-label="Contul meu"
              onClick={() => setAccount(true)}
            >
              {initials}
            </button>
          </div>
        </header>
        <main>
          {error && (
            <p className="error" role="alert">
              {error}
            </p>
          )}
          {family ? (
            page === "documents" ? (
              <Documents
                key={family.id}
                family={family}
                userId={session.user.id}
                onExpired={() => void refresh()}
              />
            ) : (
              <FamilyPage
                key={family.id}
                familyId={family.id}
                onExpired={() => void refresh()}
                isOwner={family.role === "owner"}
                userId={session.user.id}
              />
            )
          ) : (
            <div className="empty">
              <Users size={40} />
              <h1>Nicio familie activă</h1>
              <p>
                Nu mai ești membru activ. Cere proprietarului familiei să
                verifice accesul.
              </p>
              <button
                className="button secondary"
                onClick={() => void refresh()}
              >
                Verifică din nou
              </button>
            </div>
          )}
        </main>
      </div>
      <nav className="mobile-nav" aria-label="Navigație mobil">
        <button
          aria-label="Documente"
          className={page === "documents" ? "active" : ""}
          onClick={() => setPage("documents")}
        >
          <FolderHeart size={21} />
          <span>Documente</span>
        </button>
        <button
          aria-label="Familia mea"
          className={page === "family" ? "active" : ""}
          onClick={() => setPage("family")}
        >
          <Users size={21} />
          <span>Familie</span>
        </button>
        <button
          aria-label={page === "documents" ? "Caută documente" : "Caută membri"}
          onClick={() => {
            window.setTimeout(
              () =>
                document
                  .getElementById(
                    page === "documents" ? "document-search" : "family-search",
                  )
                  ?.focus(),
              0,
            );
          }}
        >
          <Search size={21} />
          <span>Caută</span>
        </button>
        <button onClick={() => setAccount(true)} aria-label="Contul meu">
          <span className="mobile-initial" aria-hidden="true">
            {initials}
          </span>
          <span>Cont</span>
        </button>
      </nav>
      {account && (
        <Modal title="Contul meu" onClose={() => setAccount(false)}>
          <div className="account-info">
            <span className="avatar big">{initials}</span>
            <h3>{session.user.name}</h3>
            <p>{session.user.email}</p>
          </div>
          {session.demo && (
            <p className="demo-note">Cont demo · date fictive.</p>
          )}
          <button className="button primary full" onClick={() => void logout()}>
            <LogOut size={18} /> Deconectează-te
          </button>
        </Modal>
      )}
    </div>
  );
}

function Brand({ compact = false }: { compact?: boolean }) {
  return (
    <div className={`brand ${compact ? "compact" : ""}`}>
      <span className="brand-mark">
        <Heart size={23} strokeWidth={1.8} />
      </span>
      {!compact && (
        <span>
          memoria{" "}
          <strong>
            familiei<span className="brand-dot">.</span>
          </strong>
        </span>
      )}
    </div>
  );
}
function Login({
  demo,
  onLogin,
}: {
  demo: boolean;
  onLogin: (session: Session) => void;
}) {
  const [email, setEmail] = useState("");
  const [password, setPassword] = useState("");
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState("");
  async function login(e: FormEvent) {
    e.preventDefault();
    setBusy(true);
    setError("");
    try {
      onLogin(
        await api<Session>("/login", {
          method: "POST",
          body: JSON.stringify({ email, password }),
        }),
      );
    } catch (e) {
      setError(e instanceof Error ? e.message : "Autentificarea nu a reușit.");
    } finally {
      setBusy(false);
    }
  }
  return (
    <div className="login-page">
      <div className="login-brand">
        <Brand />
      </div>
      <div className="login-form-wrap">
        <div className="login-form">
          <h1>Autentificare</h1>
          <form onSubmit={login}>
            <label className="field">
              Adresa de email
              <input
                type="email"
                autoComplete="username"
                required
                value={email}
                onChange={(e) => setEmail(e.target.value)}
                placeholder="nume@exemplu.ro"
              />
            </label>
            <label className="field">
              Parola
              <input
                type="password"
                autoComplete="current-password"
                required
                value={password}
                onChange={(e) => setPassword(e.target.value)}
                placeholder="Parola contului tău"
              />
            </label>
            {error && (
              <p role="alert" className="error">
                {error}
              </p>
            )}
            <button className="button primary full" disabled={busy}>
              {busy ? "Se conectează…" : "Intră în cont"}
              <ArrowRight size={19} />
            </button>
          </form>
          {demo && (
            <div className="demo-accounts">
              <p>Conturi demo</p>
              <div>
                {["ana", "mihai", "elena"].map((name) => (
                  <button
                    key={name}
                    onClick={() => {
                      setEmail(`${name}@example.test`);
                      setPassword("Familie-Demo-2026!");
                      setError("");
                    }}
                  >
                    {name === "ana"
                      ? "Ana"
                      : name === "mihai"
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
