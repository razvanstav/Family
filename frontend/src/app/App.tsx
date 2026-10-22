import { useEffect, useRef, useState, type FormEvent } from "react";
import {
  ArrowRight,
  ChevronDown,
  Heart,
  House,
  LogOut,
  CalendarDays,
  BookHeart,
  Users,
  UtensilsCrossed,
} from "lucide-react";
import {
  api,
  ApiError,
  initials,
  onSessionInvalidated,
  type Session,
  type DocumentRequest,
  type CalendarRequest,
  personalDocumentCategories,
} from "../shared/api";
import { Documents } from "../modules/documents/Documents";
import { FamilyPage } from "../modules/families/Family";
import { AcceptInvitation } from "../modules/families/AcceptInvitation";
import { Modal } from "../shared/Modal";
import { SectionTabs } from "../shared/SectionTabs";
import { observeSessionLifecycle } from "./sessionLifecycle";
import { CalendarPage } from "../modules/calendar/Calendar";
import { CalendarNotifications } from "../modules/calendar/CalendarNotifications";
import { Home } from "../modules/home/Home";
import { Kitchen } from "../modules/kitchen/Kitchen";
import { Memories } from "../modules/memory/Memories";

const pageLabels = {
  home: "Acasă",
  family: "Familia mea",
  kitchen: "Bucătărie",
  memory: "Amintiri",
  calendar: "Calendar",
};

export default function App() {
  const [session, setSession] = useState<Session | null>(null);
  const [familyId, setFamilyId] = useState("");
  const [error, setError] = useState("");
  const [page, setPage] = useState<keyof typeof pageLabels>("home");
  const [familySection, setFamilySection] = useState<"members" | "documents">(
    "members",
  );
  const [documentTarget, setDocumentTarget] = useState<DocumentRequest | null>(
    null,
  );
  const [account, setAccount] = useState(false);
  const [memoryTarget, setMemoryTarget] = useState<string | null>(null);
  const [calendarRevision, setCalendarRevision] = useState(0);
  const [calendarTarget, setCalendarTarget] = useState<CalendarRequest | null>(
    null,
  );
  const [checkingSession, setCheckingSession] = useState(true);
  const [sessionNotice, setSessionNotice] = useState(false);
  const [logoutBusy, setLogoutBusy] = useState(false);
  const [logoutError, setLogoutError] = useState("");
  const sessionRevision = useRef(0);
  const knownActor = useRef<number | null>(null);
  const sessionChannel = useRef<BroadcastChannel | null>(null);
  const sessionRequest = useRef<Promise<void> | null>(null);
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
    document.title = `${invitation ? "Invitație" : !session?.user || checkingSession ? "Autentificare" : page === "family" && familySection === "documents" ? "Documente · Familia mea" : pageLabels[page]} · Memoria Familiei`;
  }, [page, familySection, invitation, session?.user, checkingSession]);
  function acceptSession(data: Session) {
    sessionRevision.current++;
    knownActor.current = data.user?.id ?? null;
    setSession(data);
    setFamilyId(data.families[0]?.id ?? "");
    setPage("home");
    setFamilySection("members");
    setDocumentTarget(null);
    setCalendarTarget(null);
    setMemoryTarget(null);
    setAccount(false);
    setError("");
    setSessionNotice(false);
    setCheckingSession(document.visibilityState !== "visible");
    sessionChannel.current?.postMessage("changed");
  }
  function refresh(): Promise<void> {
    if (sessionRequest.current) return sessionRequest.current;
    const revision = ++sessionRevision.current;
    setCheckingSession(true);
    setAccount(false);
    setError("");
    const pending = (async () => {
      try {
        const data = await api<Session>("/session");
        if (revision !== sessionRevision.current) return;
        if (knownActor.current !== null && !data.user) setSessionNotice(true);
        knownActor.current = data.user?.id ?? null;
        setSession(data);
        setFamilyId((id) =>
          data.families.some((f) => f.id === id)
            ? id
            : (data.families[0]?.id ?? ""),
        );
        setCheckingSession(document.visibilityState !== "visible");
      } catch {
        if (revision !== sessionRevision.current) return;
        setSession(null);
        setFamilyId("");
        setError(
          "Nu am putut verifica sesiunea. Verifică conexiunea și reîncearcă.",
        );
      }
    })();
    sessionRequest.current = pending;
    void pending.finally(() => {
      if (sessionRequest.current === pending) sessionRequest.current = null;
    });
    return pending;
  }
  useEffect(() => {
    void refresh();
    const stopObserving = observeSessionLifecycle(document, window, {
      resume: () => {
        void refresh();
      },
      suspend: () => {
        sessionRevision.current++;
        sessionRequest.current = null;
        setCheckingSession(true);
        setAccount(false);
      },
    });
    const channel =
      typeof BroadcastChannel === "undefined"
        ? null
        : new BroadcastChannel("memoria-session");
    sessionChannel.current = channel;
    if (channel)
      channel.onmessage = (event) => {
        if (event.data === "changed") {
          sessionRequest.current = null;
          void refresh();
        }
      };
    const unsubscribe = onSessionInvalidated(() => {
      setSessionNotice(true);
      sessionRequest.current = null;
      void refresh();
    });
    return () => {
      sessionRevision.current++;
      sessionRequest.current = null;
      unsubscribe();
      stopObserving();
      channel?.close();
      sessionChannel.current = null;
    };
  }, []);
  const family = session?.families.find((f) => f.id === familyId);
  if (!session || checkingSession)
    return (
      <div className="startup">
        <Brand />
        <p role="status">{error || "Se verifică sesiunea…"}</p>
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
        onSession={acceptSession}
        onDone={(data, id) => {
          acceptSession(data);
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
        expired={sessionNotice}
        onLogin={acceptSession}
      />
    );
  async function logout() {
    if (logoutBusy) return;
    setLogoutBusy(true);
    setLogoutError("");
    try {
      acceptSession(await api<Session>("/logout", { method: "POST" }));
    } catch (e) {
      if (!(e instanceof ApiError && [401, 419].includes(e.status)))
        setLogoutError(
          "Deconectarea nu a reușit. Verifică conexiunea și reîncearcă.",
        );
    } finally {
      setLogoutBusy(false);
    }
  }
  const userInitials = initials(session.user.name);
  const familyNavigation = (
    <SectionTabs
      label="Secțiuni Familia mea"
      value={familySection}
      options={[
        { value: "members", label: "Membri" },
        { value: "documents", label: "Documente" },
      ]}
      onChange={setFamilySection}
    />
  );
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
            className={page === "home" ? "active" : ""}
            onClick={() => setPage("home")}
          >
            <House size={21} /> Acasă
          </button>
          <button
            className={page === "family" ? "active" : ""}
            onClick={() => setPage("family")}
          >
            <Users size={21} /> Familia mea
          </button>
          <button
            className={page === "kitchen" ? "active" : ""}
            onClick={() => setPage("kitchen")}
          >
            <UtensilsCrossed size={21} /> Bucătărie
          </button>
          <button
            className={page === "memory" ? "active" : ""}
            onClick={() => setPage("memory")}
          >
            <BookHeart size={21} /> Amintiri
          </button>
          <button
            className={page === "calendar" ? "active" : ""}
            onClick={() => setPage("calendar")}
          >
            <CalendarDays size={21} /> Calendar
          </button>
        </nav>
        <div className="sidebar-bottom">
          <button className="sidebar-profile" onClick={() => setAccount(true)}>
            <span className="avatar">{userInitials}</span>
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
              {userInitials}
            </button>
          </div>
          <span className="desktop-breadcrumb">
            {page === "family" ? "Familia mea" : "Spațiul familiei"}{" "}
            <span>/</span>{" "}
            <strong>
              {page === "family"
                ? familySection === "documents"
                  ? "Documente"
                  : "Membri"
                : pageLabels[page]}
            </strong>
          </span>
          <div className="topbar-actions">
            <label className="family-selector">
              <Heart size={16} />
              <select
                aria-label="Familia activă"
                value={familyId}
                onChange={(e) => {
                  setFamilyId(e.target.value);
                  setCalendarTarget(null);
                  setDocumentTarget(null);
                  setMemoryTarget(null);
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
            {family && (
              <CalendarNotifications
                key={`${session.user.id}:${family.id}`}
                familyId={family.id}
                revision={calendarRevision}
                onExpired={() => void refresh()}
                onOpenEvent={(event) => {
                  setCalendarTarget(event);
                  setPage("calendar");
                }}
              />
            )}
            <button
              className="avatar profile-button"
              aria-label="Contul meu"
              onClick={() => setAccount(true)}
            >
              {userInitials}
            </button>
          </div>
        </header>
        <main
          className="page-view"
          key={`${session.user.id}:${family?.id ?? "none"}:${page}`}
        >
          {sessionNotice && (
            <p className="notice" role="status">
              Sesiunea s-a schimbat. Reia acțiunea.
            </p>
          )}
          {error && (
            <p className="error" role="alert">
              {error}
            </p>
          )}
          {family ? (
            page === "home" ? (
              <Home
                key={`${session.user.id}:${family.id}`}
                family={family}
                onExpired={() => void refresh()}
                onMemories={(id) => {
                  setMemoryTarget(id ?? null);
                  setPage("memory");
                }}
                onKitchen={() => setPage("kitchen")}
                onDocuments={(request) => {
                  setDocumentTarget(request);
                  setFamilySection(
                    request.memberId ||
                      request.ownerId !== undefined ||
                      request.upload ||
                      (request.category &&
                        personalDocumentCategories.includes(request.category))
                      ? "members"
                      : "documents",
                  );
                  setPage("family");
                }}
                onCalendar={(target) => {
                  setCalendarTarget(target);
                  setPage("calendar");
                }}
              />
            ) : page === "family" && familySection === "documents" ? (
              <Documents
                key={`${session.user.id}:${family.id}`}
                family={family}
                userId={session.user.id}
                navigation={familyNavigation}
                requestedAction={documentTarget}
                onActionOpened={() => setDocumentTarget(null)}
                onExpired={() => void refresh()}
              />
            ) : page === "kitchen" ? (
              <Kitchen
                key={`${session.user.id}:${family.id}`}
                family={family}
                userId={session.user.id}
                onExpired={() => void refresh()}
              />
            ) : page === "memory" ? (
              <Memories
                key={`${session.user.id}:${family.id}`}
                family={family}
                actorId={session.user.id}
                requestedMoment={memoryTarget}
                onMomentOpened={() => setMemoryTarget(null)}
                onExpired={() => void refresh()}
              />
            ) : page === "calendar" ? (
              <CalendarPage
                key={`${session.user.id}:${family.id}`}
                family={family}
                userId={session.user.id}
                requestedEvent={calendarTarget}
                onEventOpened={() => setCalendarTarget(null)}
                onChanged={() => setCalendarRevision((value) => value + 1)}
                onExpired={() => void refresh()}
              />
            ) : (
              <FamilyPage
                key={`${session.user.id}:${family.id}`}
                family={family}
                onExpired={() => void refresh()}
                userId={session.user.id}
                navigation={familyNavigation}
                requestedDocument={documentTarget}
                onDocumentOpened={() => setDocumentTarget(null)}
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
          aria-label="Acasă"
          className={page === "home" ? "active" : ""}
          onClick={() => setPage("home")}
        >
          <House size={21} />
          <span>Acasă</span>
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
          aria-label="Bucătărie"
          className={page === "kitchen" ? "active" : ""}
          onClick={() => setPage("kitchen")}
        >
          <UtensilsCrossed size={21} />
          <span>Bucătărie</span>
        </button>
        <button
          aria-label="Amintiri"
          className={page === "memory" ? "active" : ""}
          onClick={() => setPage("memory")}
        >
          <BookHeart size={21} />
          <span>Amintiri</span>
        </button>
        <button
          aria-label="Calendar"
          className={page === "calendar" ? "active" : ""}
          onClick={() => setPage("calendar")}
        >
          <CalendarDays size={21} />
          <span>Calendar</span>
        </button>
        <button onClick={() => setAccount(true)} aria-label="Contul meu">
          <span className="mobile-initial" aria-hidden="true">
            {userInitials}
          </span>
          <span>Cont</span>
        </button>
      </nav>
      {account && (
        <Modal title="Contul meu" onClose={() => setAccount(false)}>
          <div className="account-info">
            <span className="avatar big">{userInitials}</span>
            <h3>{session.user.name}</h3>
            <p>{session.user.email}</p>
          </div>
          {session.demo && (
            <p className="demo-note">Cont demo · date fictive.</p>
          )}
          {logoutError && (
            <p className="error" role="alert">
              {logoutError}
            </p>
          )}
          <button
            className="button primary full"
            disabled={logoutBusy}
            onClick={() => void logout()}
          >
            <LogOut size={18} />{" "}
            {logoutBusy ? "Se deconectează…" : "Deconectează-te"}
          </button>
        </Modal>
      )}
    </div>
  );
}

function Brand() {
  return (
    <div className="brand">
      <span className="brand-mark">
        <Heart size={23} strokeWidth={1.8} />
      </span>
      <span>
        memoria{" "}
        <strong>
          familiei<span className="brand-dot">.</span>
        </strong>
      </span>
    </div>
  );
}
function Login({
  demo,
  expired,
  onLogin,
}: {
  demo: boolean;
  expired: boolean;
  onLogin: (session: Session) => void;
}) {
  const [email, setEmail] = useState("");
  const [password, setPassword] = useState("");
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState("");
  async function login(e: FormEvent) {
    e.preventDefault();
    if (busy) return;
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
          {expired && (
            <p className="notice" role="status">
              Sesiunea nu mai este activă. Intră din nou în cont.
            </p>
          )}
          <form onSubmit={login}>
            <label className="field">
              Adresa de email
              <input
                type="email"
                autoComplete="username"
                disabled={busy}
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
                disabled={busy}
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
                    disabled={busy}
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
