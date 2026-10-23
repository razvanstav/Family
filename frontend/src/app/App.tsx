import { useEffect, useRef, useState } from "react";
import {
  ChevronDown,
  Heart,
  House,
  CalendarDays,
  BookHeart,
  Users,
  UtensilsCrossed,
  CarFront,
} from "lucide-react";
import {
  api,
  ApiError,
  onSessionInvalidated,
  type Session,
  type CalendarRequest,
} from "../shared/api";
import { Documents } from "../modules/documents/Documents";
import { FamilyPage } from "../modules/families/Family";
import { AcceptInvitation } from "../modules/families/AcceptInvitation";
import { MobileNavigation } from "./MobileNavigation";
import { AccountDialog } from "./AccountDialog";
import { AuthEntry, type AuthMode } from "./AuthEntry";
import { UserAvatar } from "../shared/UserAvatar";
import type { Profile } from "../modules/families/profileData";
import { SectionTabs } from "../shared/SectionTabs";
import { observeSessionLifecycle } from "./sessionLifecycle";
import { CalendarPage } from "../modules/calendar/Calendar";
import { CalendarNotifications } from "../modules/calendar/CalendarNotifications";
import { Home } from "../modules/home/Home";
import { Kitchen } from "../modules/kitchen/Kitchen";
import { Memories } from "../modules/memory/Memories";
import { Vehicles } from "../modules/vehicles/Vehicles";

const pageLabels = {
  home: "Acasă",
  family: "Familia mea",
  kitchen: "Bucătărie",
  memory: "Amintiri",
  calendar: "Calendar",
  vehicles: "Auto",
};

export default function App() {
  const [session, setSession] = useState<Session | null>(null);
  const [authMode, setAuthMode] = useState<AuthMode>("register");
  const [familyId, setFamilyId] = useState("");
  const [error, setError] = useState("");
  const [page, setPage] = useState<keyof typeof pageLabels>("home");
  const [familySection, setFamilySection] = useState<"members" | "documents">(
    "members",
  );
  const [account, setAccount] = useState(false);
  const [ownProfile, setOwnProfile] = useState<{
    familyId: string;
    profile: Profile;
  } | null>(null);
  const [profileError, setProfileError] = useState("");
  const [profileRevision, setProfileRevision] = useState(0);
  const [contentRevision, setContentRevision] = useState(0);
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
    document.title = `${invitation ? "Invitație" : !session?.user || checkingSession ? (authMode === "register" ? "Creează cont" : "Autentificare") : page === "family" && familySection === "documents" ? "Documente · Familia mea" : pageLabels[page]} · Memoria Familiei`;
  }, [
    page,
    familySection,
    invitation,
    session?.user,
    checkingSession,
    authMode,
  ]);
  function acceptSession(data: Session) {
    sessionRevision.current++;
    knownActor.current = data.user?.id ?? null;
    setSession(data);
    if (!data.user) setAuthMode("login");
    setFamilyId(data.families[0]?.id ?? "");
    setPage("home");
    setFamilySection("members");
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
        if (knownActor.current !== null && !data.user) {
          setSessionNotice(true);
          setAuthMode("login");
        }
        knownActor.current = data.user?.id ?? null;
        setSession(data);
        setProfileRevision((value) => value + 1);
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
  useEffect(() => {
    setOwnProfile(null);
    setProfileError("");
    if (!family || !session?.user) return;
    const controller = new AbortController();
    api<Profile>(
      `/families/${family.id}/members/${family.membership_id}/profile`,
      { signal: controller.signal },
    )
      .then((profile) => {
        if (!controller.signal.aborted)
          setOwnProfile({ familyId: family.id, profile });
      })
      .catch((error) => {
        if (controller.signal.aborted) return;
        setProfileError(
          error instanceof Error
            ? error.message
            : "Nu am putut încărca fotografia.",
        );
        if (
          error instanceof ApiError &&
          [401, 403, 404, 419].includes(error.status)
        )
          void refresh();
      });
    return () => controller.abort();
  }, [family?.id, family?.membership_id, session?.user?.id, profileRevision]);
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
      <AuthEntry
        brand={<Brand />}
        mode={authMode}
        demo={session.demo}
        expired={sessionNotice}
        onAuthenticated={acceptSession}
        onMode={(mode) => {
          setAuthMode(mode);
          setSessionNotice(false);
        }}
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
  const currentProfile =
    ownProfile?.familyId === family?.id &&
    ownProfile?.profile.user_id === session.user.id
      ? ownProfile.profile
      : null;
  const userPhoto =
    family && currentProfile?.photo_status === "ready"
      ? `/api/v1/families/${family.id}/members/${family.membership_id}/profile/photo?v=${currentProfile.version}`
      : undefined;
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
            <Users size={23} aria-hidden="true" />
          </span>
          <strong>{family?.name ?? "Familia ta"}</strong>
        </div>
        <nav aria-label="Navigație principală">
          <button
            className={page === "home" ? "active" : ""}
            aria-current={page === "home" ? "page" : undefined}
            onClick={() => setPage("home")}
          >
            <span className="nav-symbol nav-symbol-home" aria-hidden="true">
              <House size={21} />
            </span>
            Acasă
          </button>
          <button
            className={page === "family" ? "active" : ""}
            aria-current={page === "family" ? "page" : undefined}
            onClick={() => setPage("family")}
          >
            <span className="nav-symbol nav-symbol-family" aria-hidden="true">
              <Users size={21} />
            </span>
            Familia mea
          </button>
          <button
            className={page === "kitchen" ? "active" : ""}
            aria-current={page === "kitchen" ? "page" : undefined}
            onClick={() => setPage("kitchen")}
          >
            <span className="nav-symbol nav-symbol-kitchen" aria-hidden="true">
              <UtensilsCrossed size={21} />
            </span>
            Bucătărie
          </button>
          <button
            className={page === "memory" ? "active" : ""}
            aria-current={page === "memory" ? "page" : undefined}
            onClick={() => setPage("memory")}
          >
            <span className="nav-symbol nav-symbol-memory" aria-hidden="true">
              <BookHeart size={21} />
            </span>
            Amintiri
          </button>
          <button
            className={page === "calendar" ? "active" : ""}
            aria-current={page === "calendar" ? "page" : undefined}
            onClick={() => setPage("calendar")}
          >
            <span className="nav-symbol nav-symbol-calendar" aria-hidden="true">
              <CalendarDays size={21} />
            </span>
            Calendar
          </button>
          <button
            className={page === "vehicles" ? "active" : ""}
            aria-current={page === "vehicles" ? "page" : undefined}
            onClick={() => setPage("vehicles")}
          >
            <span className="nav-symbol nav-symbol-vehicles" aria-hidden="true">
              <CarFront size={21} />
            </span>
            Auto
          </button>
        </nav>
        <div className="sidebar-bottom">
          <button className="sidebar-profile" onClick={() => setAccount(true)}>
            <UserAvatar name={session.user.name} photo={userPhoto} />
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
              <UserAvatar name={session.user.name} photo={userPhoto} />
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
              <Users size={16} aria-hidden="true" />
              <select
                aria-label="Familia activă"
                value={familyId}
                onChange={(e) => {
                  setFamilyId(e.target.value);
                  setCalendarTarget(null);
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
              <UserAvatar name={session.user.name} photo={userPhoto} />
            </button>
          </div>
        </header>
        <main
          className="page-view"
          key={`${session.user.id}:${family?.id ?? "none"}:${page}:${contentRevision}`}
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
            ) : page === "vehicles" ? (
              <Vehicles
                key={`${session.user.id}:${family.id}`}
                family={family}
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
      <MobileNavigation
        page={page}
        onPage={setPage}
        onAccount={() => setAccount(true)}
      />
      {account && (
        <AccountDialog
          key={`${session.user.id}:${family?.id ?? "none"}`}
          user={session.user}
          family={family}
          profile={currentProfile}
          profileError={profileError}
          demo={session.demo}
          logoutBusy={logoutBusy}
          logoutError={logoutError}
          onClose={() => setAccount(false)}
          onLogout={() => void logout()}
          onExpired={() => void refresh()}
          onReloadProfile={() => setProfileRevision((value) => value + 1)}
          onProfileSaved={(profile) => {
            if (family) setOwnProfile({ familyId: family.id, profile });
            setContentRevision((value) => value + 1);
          }}
          onUserSaved={(user) => {
            setSession((current) =>
              current?.user?.id === user.id ? { ...current, user } : current,
            );
            setOwnProfile((current) =>
              current?.profile.user_id === user.id
                ? {
                    ...current,
                    profile: { ...current.profile, name: user.name },
                  }
                : current,
            );
            setContentRevision((value) => value + 1);
            sessionChannel.current?.postMessage("changed");
          }}
        />
      )}
    </div>
  );
}

function Brand() {
  return (
    <div className="brand" aria-label="Memoria Familiei">
      <span className="brand-mark" aria-hidden="true">
        <House size={41} strokeWidth={1.7} />
        <Heart className="brand-heart" size={12} strokeWidth={1.6} />
      </span>
      <span className="brand-name">
        <span>Memoria</span>
        <span>Familiei</span>
      </span>
    </div>
  );
}
