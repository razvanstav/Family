import { useEffect, useRef, useState } from "react";
import {
  ChevronDown,
  House,
  CalendarDays,
  ListTodo,
  BookHeart,
  Users,
  UtensilsCrossed,
  CarFront,
  Search,
  Wallet,
  Tv,
  PawPrint,
  Settings,
} from "lucide-react";
import {
  api,
  ApiError,
  onSessionInvalidated,
  type Session,
  type CalendarRequest,
  type VehicleRequest,
  type PetRequest,
  type DocumentRequest,
  type DocumentDetail,
  type Member,
  familyDocumentCategories,
} from "../shared/api";
import {
  Documents,
  DocumentCreateDialog,
  DocumentDetailDialog,
} from "../modules/documents/Documents";
import { FamilyPage } from "../modules/families/Family";
import { AcceptInvitation } from "../modules/families/AcceptInvitation";
import { MobileNavigation } from "./MobileNavigation";
import { AccountDialog } from "./AccountDialog";
import { AuthEntry, type AuthMode } from "./AuthEntry";
import { Brand } from "../shared/Brand";
import {
  Administration,
  type AdministrationMemory,
} from "../modules/administration/Administration";
import { UserAvatar } from "../shared/UserAvatar";
import {
  kinds,
  type Kind,
  type Profile,
  type ProfileRequest,
} from "../modules/families/profileData";
import { SectionTabs } from "../shared/SectionTabs";
import { observeSessionLifecycle } from "./sessionLifecycle";
import { observeVisualViewport } from "../shared/visualViewport";
import { bucharestToday } from "../shared/calendarDates";
import { CalendarPage } from "../modules/calendar/Calendar";
import { CalendarNotifications } from "../modules/calendar/CalendarNotifications";
import { Home } from "../modules/home/Home";
import { Kitchen } from "../modules/kitchen/Kitchen";
import type { MealRequest } from "../modules/kitchen/mealTypes";
import { Memories } from "../modules/memory/Memories";
import { Vehicles } from "../modules/vehicles/Vehicles";
import { Expenses } from "../modules/expenses/Expenses";
import { Subscriptions } from "../modules/subscriptions/Subscriptions";
import { UpcomingPaymentsCard } from "../modules/expenses/UpcomingPaymentsCard";
import type { Expense, ExpenseRequest } from "../modules/expenses/expenseData";
import { Tasks } from "../modules/tasks/Tasks";
import { TaskHomeCard } from "../modules/tasks/TaskHomeCard";
import type { Task } from "../modules/tasks/taskData";
import { Pets } from "../modules/pets/Pets";
import type {
  DocumentCreationRender,
  DocumentDetailRender,
} from "../shared/documentIntegration";
import {
  FamilySearch,
  type SearchResult,
} from "../modules/search/FamilySearch";

const pageLabels = {
  home: "Acasă",
  family: "Familia mea",
  kitchen: "Bucătărie",
  memory: "Amintiri",
  expenses: "Cheltuieli",
  subscriptions: "Abonamente",
  calendar: "Calendar",
  tasks: "Sarcini",
  vehicles: "Auto",
  pets: "Animale",
  admin: "Administrare",
};

export default function App() {
  useEffect(() => observeVisualViewport(window, document.documentElement), []);
  const [session, setSession] = useState<Session | null>(null);
  const [authMode, setAuthMode] = useState<AuthMode>("login");
  const [familyId, setFamilyId] = useState("");
  const [error, setError] = useState("");
  const [page, navigatePage] = useState<keyof typeof pageLabels>("home");
  const [familySection, setFamilySection] = useState<"members" | "documents">(
    "members",
  );
  const [account, setAccount] = useState(false);
  const accountReturnFocus = useRef<HTMLElement | null>(null);
  const [ownProfile, setOwnProfile] = useState<{
    familyId: string;
    profile: Profile;
  } | null>(null);
  const [profileError, setProfileError] = useState("");
  const [profileRevision, setProfileRevision] = useState(0);
  const [contentRevision, setContentRevision] = useState(0);
  const [memoryTarget, setMemoryTarget] = useState<string | null>(null);
  const [calendarRevision, setCalendarRevision] = useState(0);
  const [searchOpen, setSearchOpen] = useState(false);
  const [recipeTarget, setRecipeTarget] = useState<string | null>(null);
  const [documentTarget, setDocumentTarget] = useState<DocumentRequest | null>(
    null,
  );
  const [vehicleTarget, setVehicleTarget] = useState<VehicleRequest | null>(
    null,
  );
  const [calendarTarget, setCalendarTarget] = useState<CalendarRequest | null>(
    null,
  );
  const [taskTarget, setTaskTarget] = useState<string | null>(null);
  const [petTarget, setPetTarget] = useState<PetRequest | null>(null);
  const [profileTarget, setProfileTarget] = useState<ProfileRequest | null>(
    null,
  );
  const [expenseTarget, setExpenseTarget] = useState<ExpenseRequest | null>(
    null,
  );
  const [subscriptionTarget, setSubscriptionTarget] = useState<string | null>(
    null,
  );
  const [mealDateTarget, setMealDateTarget] = useState<MealRequest | null>(
    null,
  );
  const [petProfileTarget, setPetProfileTarget] = useState<string | null>(null);
  const [checkingSession, setCheckingSession] = useState(true);
  const [sessionNotice, setSessionNotice] = useState(false);
  const [logoutBusy, setLogoutBusy] = useState(false);
  const [logoutError, setLogoutError] = useState("");
  const sessionRevision = useRef(0);
  const adminMemory = useRef<AdministrationMemory>({ section: "overview" });
  const knownActor = useRef<number | null>(null);
  const sessionChannel = useRef<BroadcastChannel | null>(null);
  const sessionRequest = useRef<Promise<void> | null>(null);
  const [invitation, setInvitation] = useState(() =>
    new URLSearchParams(window.location.hash.slice(1)).get("invite"),
  );
  function openAccount(returnFocus: HTMLElement | null) {
    accountReturnFocus.current = returnFocus;
    setAccount(true);
  }
  useEffect(() => {
    if (session?.user && !session.user.is_admin && page === "admin")
      navigatePage("home");
  }, [session?.user?.is_admin, page]);
  useEffect(() => {
    const changed = () =>
      setInvitation(
        new URLSearchParams(window.location.hash.slice(1)).get("invite"),
      );
    window.addEventListener("hashchange", changed);
    return () => window.removeEventListener("hashchange", changed);
  }, []);
  useEffect(() => {
    document.title = `${invitation ? "Invitație" : !session?.user || checkingSession ? (authMode === "register" ? "Creează cont" : "Autentificare") : page === "family" && familySection === "documents" ? "Documente · Familia mea" : pageLabels[page]} · ${!session?.user && !invitation ? "Amora" : "Memoria Familiei"}`;
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
    if (knownActor.current !== (data.user?.id ?? null) || !data.user?.is_admin)
      adminMemory.current = { section: "overview" };
    knownActor.current = data.user?.id ?? null;
    setSession(data);
    if (!data.user) setAuthMode("login");
    setFamilyId(data.families[0]?.id ?? "");
    navigatePage(data.user?.is_admin ? "admin" : "home");
    setFamilySection("members");
    setCalendarTarget(null);
    setVehicleTarget(null);
    setTaskTarget(null);
    setPetTarget(null);
    setProfileTarget(null);
    setExpenseTarget(null);
    setSubscriptionTarget(null);
    setMealDateTarget(null);
    setPetProfileTarget(null);
    setRecipeTarget(null);
    setDocumentTarget(null);
    setSearchOpen(false);
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
        if (
          knownActor.current !== (data.user?.id ?? null) ||
          !data.user?.is_admin
        )
          adminMemory.current = { section: "overview" };
        if (knownActor.current !== (data.user?.id ?? null))
          navigatePage(data.user?.is_admin ? "admin" : "home");
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
      adminMemory.current = { section: "overview" };
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
    setProfileTarget(null);
    setExpenseTarget(null);
    setSubscriptionTarget(null);
    setMealDateTarget(null);
    setPetProfileTarget(null);
  }, [familyId]);
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
          navigatePage("family");
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
  const authenticatedUser = session.user;
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
  const renderDocumentCreation: DocumentCreationRender = (props) =>
    family && (
      <DocumentCreateDialog
        endpoint={`/families/${family.id}`}
        categoryIds={familyDocumentCategories}
        initialCategory="home"
        {...props}
        onAccessChanged={() => void refresh()}
      />
    );
  const renderDocumentDetail: DocumentDetailRender = (props) =>
    family && (
      <DocumentDetailDialog
        key={props.id}
        endpoint={`/families/${family.id}`}
        userId={session.user!.id}
        canEdit={family.role !== "limited"}
        initialDocument={null}
        {...props}
      />
    );
  return (
    <div className="app-shell">
      <aside className="sidebar">
        <button
          className="sidebar-home"
          aria-label="Acasă"
          onClick={() => navigatePage("home")}
        >
          <Brand />
        </button>
        {family && (
          <div className="sidebar-family">
            <span className="family-symbol">
              <Users size={23} aria-hidden="true" />
            </span>
            <strong>{family?.name ?? "Familia ta"}</strong>
          </div>
        )}
        {session.families.length > 0 && (
          <nav aria-label="Navigație principală">
            <button
              className={page === "home" ? "active" : ""}
              aria-current={page === "home" ? "page" : undefined}
              onClick={() => navigatePage("home")}
            >
              <span className="nav-symbol nav-symbol-home" aria-hidden="true">
                <House size={21} />
              </span>
              Acasă
            </button>
            <button
              className={page === "family" ? "active" : ""}
              aria-current={page === "family" ? "page" : undefined}
              onClick={() => navigatePage("family")}
            >
              <span className="nav-symbol nav-symbol-family" aria-hidden="true">
                <Users size={21} />
              </span>
              Familia mea
            </button>
            <button
              className={page === "kitchen" ? "active" : ""}
              aria-current={page === "kitchen" ? "page" : undefined}
              onClick={() => navigatePage("kitchen")}
            >
              <span
                className="nav-symbol nav-symbol-kitchen"
                aria-hidden="true"
              >
                <UtensilsCrossed size={21} />
              </span>
              Bucătărie
            </button>
            <button
              className={page === "expenses" ? "active" : ""}
              aria-current={page === "expenses" ? "page" : undefined}
              onClick={() => navigatePage("expenses")}
            >
              <span
                className="nav-symbol nav-symbol-expenses"
                aria-hidden="true"
              >
                <Wallet size={21} />
              </span>
              Cheltuieli
            </button>
            <button
              className={page === "subscriptions" ? "active" : ""}
              aria-current={page === "subscriptions" ? "page" : undefined}
              onClick={() => navigatePage("subscriptions")}
            >
              <span
                className="nav-symbol nav-symbol-subscriptions"
                aria-hidden="true"
              >
                <Tv size={21} />
              </span>
              Abonamente
            </button>
            <button
              className={page === "memory" ? "active" : ""}
              aria-current={page === "memory" ? "page" : undefined}
              onClick={() => navigatePage("memory")}
            >
              <span className="nav-symbol nav-symbol-memory" aria-hidden="true">
                <BookHeart size={21} />
              </span>
              Amintiri
            </button>
            <button
              className={page === "vehicles" ? "active" : ""}
              aria-current={page === "vehicles" ? "page" : undefined}
              onClick={() => navigatePage("vehicles")}
            >
              <span
                className="nav-symbol nav-symbol-vehicles"
                aria-hidden="true"
              >
                <CarFront size={21} />
              </span>
              Auto
            </button>
            <button
              className={page === "calendar" ? "active" : ""}
              aria-current={page === "calendar" ? "page" : undefined}
              onClick={() => navigatePage("calendar")}
            >
              <span
                className="nav-symbol nav-symbol-calendar"
                aria-hidden="true"
              >
                <CalendarDays size={21} />
              </span>
              Calendar
            </button>
            <button
              className={page === "tasks" ? "active" : ""}
              aria-current={page === "tasks" ? "page" : undefined}
              onClick={() => navigatePage("tasks")}
            >
              <span className="nav-symbol nav-symbol-tasks" aria-hidden="true">
                <ListTodo size={21} />
              </span>
              Sarcini
            </button>
            <button
              className={page === "pets" ? "active" : ""}
              aria-current={page === "pets" ? "page" : undefined}
              onClick={() => navigatePage("pets")}
            >
              <span className="nav-symbol nav-symbol-pets" aria-hidden="true">
                <PawPrint size={21} />
              </span>
              Animale
            </button>
          </nav>
        )}
        <div className="sidebar-bottom">
          {session.user.is_admin && (
            <button
              className="sidebar-admin"
              aria-current={page === "admin" ? "page" : undefined}
              onClick={() => navigatePage("admin")}
            >
              <Settings size={20} aria-hidden="true" />
              Administrare
            </button>
          )}
          <button
            className="sidebar-profile"
            onClick={(event) => openAccount(event.currentTarget)}
          >
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
              className="mobile-home"
              aria-label="Acasă"
              onClick={() => navigatePage("home")}
            >
              <Brand compact />
            </button>
          </div>
          <span className="desktop-breadcrumb">
            {page === "admin"
              ? "Amora"
              : page === "family"
                ? "Familia mea"
                : "Spațiul familiei"}{" "}
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
            {page !== "admin" && session.families.length > 0 && (
              <label className="family-selector">
                <Users size={16} aria-hidden="true" />
                <select
                  aria-label="Familia activă"
                  value={familyId}
                  onChange={(e) => {
                    setFamilyId(e.target.value);
                    setCalendarTarget(null);
                    setVehicleTarget(null);
                    setTaskTarget(null);
                    setPetTarget(null);
                    setRecipeTarget(null);
                    setDocumentTarget(null);
                    setSearchOpen(false);
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
            )}
            {family && page !== "admin" && (
              <button
                className="icon-button"
                aria-label="Caută în familie"
                onClick={() => setSearchOpen(true)}
              >
                <Search size={21} />
              </button>
            )}
            {family && page !== "admin" && (
              <CalendarNotifications
                key={`${session.user.id}:${family.id}`}
                familyId={family.id}
                revision={calendarRevision}
                onExpired={() => void refresh()}
                onOpenEvent={(event) => {
                  setCalendarTarget(event);
                  navigatePage("calendar");
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
          key={`${session.user.id}:${family?.id ?? "none"}:${contentRevision}`}
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
          {page === "admin" && session.user.is_admin ? (
            <Administration
              userEmail={session.user.email}
              onExpired={() => void refresh()}
              memory={adminMemory}
            />
          ) : family ? (
            page === "home" ? (
              <Home<Task, Expense>
                key={`${session.user.id}:${family.id}`}
                family={family}
                onExpired={() => void refresh()}
                canAddActivity={currentProfile?.can_write ?? false}
                onCreate={(action) => {
                  if (action === "activity") {
                    if (!currentProfile?.can_write) return;
                    setDocumentTarget(null);
                    setProfileTarget({
                      memberId: family.membership_id,
                      entryId: "new",
                      category: "activities",
                    });
                    setFamilySection("members");
                    navigatePage("family");
                  } else if (action === "expense") {
                    if (family.role === "limited") return;
                    setSubscriptionTarget(null);
                    setExpenseTarget({
                      id: "new",
                      date: bucharestToday(),
                      visibility: "family",
                      payment_status: "paid",
                    });
                    navigatePage("expenses");
                  } else {
                    if (family.role === "limited") return;
                    setMemoryTarget("new");
                    navigatePage("memory");
                  }
                }}
                onMemories={(id) => {
                  setMemoryTarget(id ?? null);
                  navigatePage("memory");
                }}
                onKitchen={() => navigatePage("kitchen")}
                onActivity={(target) => {
                  switch (target.kind) {
                    case "task":
                      setTaskTarget(target.id);
                      navigatePage("tasks");
                      break;
                    case "expense":
                      setSubscriptionTarget(null);
                      setExpenseTarget({
                        id: target.id,
                        date: target.date,
                        visibility: target.visibility,
                        payment_status: target.payment_status,
                      });
                      navigatePage("expenses");
                      break;
                    case "meal":
                      setRecipeTarget(null);
                      setMealDateTarget({ id: target.id, date: target.date });
                      navigatePage("kitchen");
                      break;
                    case "recipe":
                      setMealDateTarget(null);
                      setRecipeTarget(target.id);
                      navigatePage("kitchen");
                      break;
                    case "memory":
                      setMemoryTarget(target.id);
                      navigatePage("memory");
                      break;
                    case "calendar":
                      setCalendarTarget({ id: target.id, date: target.date });
                      navigatePage("calendar");
                      break;
                    case "vehicle":
                      setVehicleTarget({ vehicleId: target.id });
                      navigatePage("vehicles");
                      break;
                    case "vehicle_entry":
                      setVehicleTarget({
                        vehicleId: target.parent_id,
                        entryId: target.id,
                      });
                      navigatePage("vehicles");
                      break;
                    case "pet":
                      setPetTarget(null);
                      setPetProfileTarget(target.id);
                      navigatePage("pets");
                      break;
                    case "pet_entry":
                      setPetProfileTarget(null);
                      setPetTarget({
                        petId: target.parent_id,
                        entryId: target.id,
                      });
                      navigatePage("pets");
                      break;
                    case "subscription":
                      setExpenseTarget(null);
                      setSubscriptionTarget(target.id);
                      navigatePage("subscriptions");
                      break;
                  }
                }}
                renderTasks={({ tasks, today, loading, onChanged }) => (
                  <TaskHomeCard
                    family={family}
                    tasks={tasks}
                    today={today}
                    loading={loading}
                    onChanged={() => {
                      onChanged();
                      setCalendarRevision((value) => value + 1);
                    }}
                    onNew={() => {
                      setTaskTarget("new");
                      navigatePage("tasks");
                    }}
                    onAll={() => {
                      setTaskTarget(null);
                      navigatePage("tasks");
                    }}
                    onExpired={() => void refresh()}
                  />
                )}
                renderUpcomingPayments={({ payments, loading, onChanged }) => (
                  <UpcomingPaymentsCard
                    family={family}
                    payments={payments}
                    loading={loading}
                    onChanged={() => {
                      onChanged();
                      setCalendarRevision((value) => value + 1);
                    }}
                    onOpen={(expense) => {
                      setSubscriptionTarget(null);
                      setExpenseTarget({
                        id: expense.id,
                        date: expense.spent_on,
                        visibility: expense.visibility,
                        payment_status: "planned",
                        expense,
                      });
                      navigatePage("expenses");
                    }}
                    onExpired={() => void refresh()}
                  />
                )}
                onCalendar={(target) => {
                  setCalendarTarget(target);
                  navigatePage("calendar");
                }}
              />
            ) : page === "family" && familySection === "documents" ? (
              <Documents
                key={`${session.user.id}:${family.id}`}
                family={family}
                userId={session.user.id}
                navigation={familyNavigation}
                requestedDocument={documentTarget?.id}
                initialDocument={documentTarget?.document}
                onDocumentOpened={() => setDocumentTarget(null)}
                onExpired={() => void refresh()}
              />
            ) : page === "expenses" ? (
              <Expenses
                key={`${session.user.id}:${family.id}`}
                family={family}
                requestedExpense={expenseTarget}
                onExpenseOpened={() => setExpenseTarget(null)}
                onExpired={() => void refresh()}
              />
            ) : page === "subscriptions" ? (
              <Subscriptions
                key={`${session.user.id}:${family.id}`}
                family={family}
                requestedSubscription={subscriptionTarget}
                onSubscriptionOpened={() => setSubscriptionTarget(null)}
                onChanged={() => setCalendarRevision((value) => value + 1)}
                onExpired={() => void refresh()}
              />
            ) : page === "tasks" ? (
              <Tasks
                key={`${session.user.id}:${family.id}`}
                family={family}
                requestedTask={taskTarget}
                onTaskOpened={() => setTaskTarget(null)}
                onChanged={() => setCalendarRevision((value) => value + 1)}
                onExpired={() => void refresh()}
              />
            ) : page === "kitchen" ? (
              <Kitchen
                key={`${session.user.id}:${family.id}`}
                family={family}
                userId={session.user.id}
                requestedRecipe={recipeTarget}
                onRecipeOpened={() => setRecipeTarget(null)}
                requestedMeal={mealDateTarget}
                onMealOpened={() => setMealDateTarget(null)}
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
                userId={session.user.id}
                requestedEntry={vehicleTarget}
                onEntryOpened={() => setVehicleTarget(null)}
                onChanged={() => setCalendarRevision((value) => value + 1)}
                onExpired={() => void refresh()}
                renderDocumentCreation={renderDocumentCreation}
                renderDocumentDetail={renderDocumentDetail}
              />
            ) : page === "pets" ? (
              <Pets
                key={`${session.user.id}:${family.id}`}
                family={family}
                userId={session.user.id}
                requestedEntry={petTarget}
                requestedProfile={petProfileTarget}
                onProfileOpened={() => setPetProfileTarget(null)}
                onEntryOpened={() => setPetTarget(null)}
                onChanged={() => setCalendarRevision((value) => value + 1)}
                onExpired={() => void refresh()}
                renderDocumentCreation={renderDocumentCreation}
                renderDocumentDetail={renderDocumentDetail}
              />
            ) : page === "calendar" ? (
              <CalendarPage
                key={`${session.user.id}:${family.id}`}
                family={family}
                userId={session.user.id}
                requestedEvent={calendarTarget}
                onEventOpened={() => setCalendarTarget(null)}
                onChanged={() => setCalendarRevision((value) => value + 1)}
                onVehicle={(target) => {
                  setVehicleTarget(target);
                  navigatePage("vehicles");
                }}
                onTask={(id) => {
                  setTaskTarget(id);
                  navigatePage("tasks");
                }}
                onPet={(target) => {
                  setPetProfileTarget(null);
                  setPetTarget(target);
                  navigatePage("pets");
                }}
                onDocument={async (id, signal) => {
                  const document = await api<DocumentDetail>(
                    `/families/${family.id}/documents/${id}`,
                    { signal },
                  );
                  const common = familyDocumentCategories.includes(
                    document.category,
                  );
                  let memberId = common ? null : document.subject_membership_id;
                  if (!common && !memberId) {
                    const members = await api<Member[]>(
                      `/families/${family.id}/members`,
                      { signal },
                    );
                    memberId =
                      members.find(
                        (member) => member.user_id === document.owner_id,
                      )?.id ?? null;
                  }
                  if (signal.aborted) return;
                  setProfileTarget(null);
                  setDocumentTarget({ id: document.id, memberId, document });
                  setFamilySection(common ? "documents" : "members");
                  navigatePage("family");
                }}
                onMemory={(id) => {
                  setMemoryTarget(id);
                  navigatePage("memory");
                }}
                onProfile={(memberId, entryId, kind) => {
                  const category =
                    kind && Object.hasOwn(kinds, kind)
                      ? kinds[kind as Kind].category
                      : undefined;
                  setDocumentTarget(null);
                  setProfileTarget({ memberId, entryId, category });
                  setFamilySection("members");
                  navigatePage("family");
                }}
                onExpense={(id, date, visibility) => {
                  setSubscriptionTarget(null);
                  setExpenseTarget({
                    id,
                    date,
                    visibility: visibility === "private" ? "private" : "family",
                  });
                  navigatePage("expenses");
                }}
                onSubscription={(id) => {
                  setExpenseTarget(null);
                  setSubscriptionTarget(id);
                  navigatePage("subscriptions");
                }}
                onMeal={(id, date) => {
                  setRecipeTarget(null);
                  setMealDateTarget({ id, date });
                  navigatePage("kitchen");
                }}
                onPetProfile={(id) => {
                  setPetTarget(null);
                  setPetProfileTarget(id);
                  navigatePage("pets");
                }}
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
                requestedProfile={profileTarget}
                onProfileOpened={() => setProfileTarget(null)}
                onDocumentOpened={() => setDocumentTarget(null)}
                renderDocuments={(props) => (
                  <Documents
                    key={`${authenticatedUser.id}:${family.id}:${props.member.id}`}
                    family={family}
                    userId={authenticatedUser.id}
                    {...props}
                  />
                )}
                renderDocumentDetail={({ document, onClose }) => (
                  <DocumentDetailDialog
                    key={document.id}
                    id={document.id}
                    initialDocument={document}
                    endpoint={`/families/${family.id}`}
                    userId={authenticatedUser.id}
                    canEdit={family.role !== "limited"}
                    onClose={onClose}
                    onChanged={() => setCalendarRevision((value) => value + 1)}
                  />
                )}
                onMemory={(id) => {
                  setMemoryTarget(id);
                  navigatePage("memory");
                }}
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
        onPage={navigatePage}
        onAccount={openAccount}
        admin={session.user.is_admin}
        hasFamily={session.families.length > 0}
      />
      {account && (
        <AccountDialog
          key={`${session.user.id}:${family?.id ?? "none"}`}
          user={session.user}
          family={family}
          profile={currentProfile}
          profileError={profileError}
          returnFocusTarget={accountReturnFocus.current}
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
              current?.user?.id === user.id
                ? { ...current, user: { ...current.user, ...user } }
                : current,
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
      {searchOpen && family && (
        <FamilySearch
          key={`${session.user.id}:${family.id}`}
          familyId={family.id}
          onClose={() => setSearchOpen(false)}
          onExpired={() => void refresh()}
          onOpen={(result: SearchResult) => {
            setSearchOpen(false);
            if (result.type === "moment") {
              setMemoryTarget(result.id);
              navigatePage("memory");
            } else if (result.type === "recipe") {
              setRecipeTarget(result.id);
              navigatePage("kitchen");
            } else {
              const common =
                familyDocumentCategories.includes(result.category) ||
                !result.member_id;
              setDocumentTarget({
                id: result.id,
                memberId: common ? null : result.member_id,
              });
              setFamilySection(common ? "documents" : "members");
              navigatePage("family");
            }
          }}
        />
      )}
    </div>
  );
}
