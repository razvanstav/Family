import {
  useEffect,
  useRef,
  useState,
  type Dispatch,
  type ReactNode,
  type SetStateAction,
} from "react";
import {
  Check,
  Crown,
  LockKeyhole,
  RefreshCw,
  Search,
  Users,
  UserRoundMinus,
  X,
  UserRoundPlus,
  Mail,
  Copy,
  MoreHorizontal,
  ChevronRight,
} from "lucide-react";
import {
  api,
  ApiError,
  errorMessage,
  initials,
  type Member,
  type Invitation,
  type Family,
} from "../../shared/api";
import { Modal } from "../../shared/Modal";
import { MemberProfile } from "./MemberProfile";
import { PrivatePhoto } from "../../shared/PrivatePhoto";
import { SectionTabs } from "../../shared/SectionTabs";
import { PageBackdrop } from "../../shared/PageBackdrop";
import { ChildForm } from "./ChildForm";

const roleLabel = (role: string) =>
  role === "owner"
    ? "Proprietar"
    : role === "limited"
      ? "Acces limitat"
      : "Membru adult";

type EditableRole = "adult" | "limited";
type InvitationCode = {
  id: string;
  expires_at: string;
  created_at: string;
  used_at: string | null;
  revoked_at: string | null;
};

function InvitationCodes({
  family,
  cache,
  onCache,
  busy,
  onBusy,
  onExpired,
}: {
  family: Family;
  cache: InvitationCode[] | null;
  onCache: Dispatch<SetStateAction<InvitationCode[] | null>>;
  busy: boolean;
  onBusy: (value: boolean) => void;
  onExpired: () => void;
}) {
  const base = `/families/${family.id}/invitation-codes`;
  const [revision, setRevision] = useState(0);
  const [loading, setLoading] = useState(cache === null);
  const [error, setError] = useState("");
  const [generated, setGenerated] = useState<
    (InvitationCode & { code: string }) | null
  >(null);
  const [copied, setCopied] = useState(false);
  const [creating, setCreating] = useState(false);
  const date = (value: string) =>
    new Intl.DateTimeFormat("ro-RO", {
      dateStyle: "medium",
      timeStyle: "short",
      timeZone: "Europe/Bucharest",
    }).format(new Date(value));
  useEffect(() => {
    if (cache !== null && revision === 0) return;
    const controller = new AbortController();
    setLoading(true);
    setError("");
    api<InvitationCode[]>(base, { signal: controller.signal })
      .then((items) => {
        if (!controller.signal.aborted) {
          onCache(items);
          setGenerated((current) =>
            current &&
            items.some(
              (item) =>
                item.id === current.id &&
                !item.used_at &&
                !item.revoked_at &&
                new Date(item.expires_at).getTime() > Date.now(),
            )
              ? current
              : null,
          );
        }
      })
      .catch((e) => {
        if (controller.signal.aborted) return;
        setError(errorMessage(e));
        if (e instanceof ApiError && [401, 403, 404, 419].includes(e.status))
          onExpired();
      })
      .finally(() => {
        if (!controller.signal.aborted) setLoading(false);
      });
    return () => controller.abort();
  }, [base, revision]);
  async function create() {
    if (busy || loading) return;
    onBusy(true);
    setCreating(true);
    setError("");
    setCopied(false);
    try {
      const result = await api<InvitationCode & { code: string }>(base, {
        method: "POST",
      });
      setGenerated(result);
      const row: InvitationCode = {
        id: result.id,
        expires_at: result.expires_at,
        created_at: result.created_at,
        used_at: result.used_at,
        revoked_at: result.revoked_at,
      };
      onCache((current) => [row, ...(current ?? [])]);
    } catch (e) {
      setError(errorMessage(e));
      if (e instanceof ApiError && [401, 403, 404, 419].includes(e.status))
        onExpired();
    } finally {
      setCreating(false);
      onBusy(false);
    }
  }
  async function revoke(code: InvitationCode) {
    if (busy) return;
    onBusy(true);
    setError("");
    try {
      await api(`${base}/${code.id}`, { method: "DELETE" });
      onCache((current) =>
        (current ?? []).map((item) =>
          item.id === code.id
            ? { ...item, revoked_at: new Date().toISOString() }
            : item,
        ),
      );
      if (generated?.id === code.id) {
        setGenerated(null);
        setCopied(false);
      }
    } catch (e) {
      if (e instanceof ApiError && e.status === 404) {
        setGenerated(null);
        setRevision((value) => value + 1);
      } else {
        setError(errorMessage(e));
        if (e instanceof ApiError && [401, 403, 419].includes(e.status))
          onExpired();
      }
    } finally {
      onBusy(false);
    }
  }
  return (
    <div className="family-invitation-codes">
      {generated && (
        <div className="family-code-result">
          <label className="field">
            Numele familiei
            <input
              readOnly
              value={family.name}
              onFocus={(e) => e.target.select()}
            />
          </label>
          <label className="field">
            Cod de invitație
            <textarea
              rows={2}
              readOnly
              value={generated.code}
              onFocus={(e) => e.target.select()}
            />
          </label>
          <p className="fine-print">
            Cod pentru un singur membru adult, valabil până la{" "}
            {date(generated.expires_at)}. Afișat doar acum. Trimite numele
            familiei și codul persoanei invitate.
          </p>
          <button
            className="button primary full"
            disabled={busy}
            onClick={async () => {
              try {
                await navigator.clipboard.writeText(generated.code);
                setCopied(true);
                setError("");
              } catch {
                setError("Selectează și copiază codul din câmp.");
              }
            }}
          >
            {copied ? <Check size={18} /> : <Copy size={18} />}
            {copied ? "Copiat" : "Copiază codul"}
          </button>
          <span className="sr-only" role="status">
            {copied ? "Cod copiat." : ""}
          </span>
        </div>
      )}
      {!generated && (
        <>
          <p className="fine-print">
            Creează un cod pentru un singur membru adult. Este valabil 7 zile și
            se afișează o singură dată.
          </p>
          <button
            className="button primary full"
            disabled={busy || loading}
            onClick={() => void create()}
          >
            <UserRoundPlus size={18} />
            {creating ? "Se creează…" : "Generează codul"}
          </button>
        </>
      )}
      <section
        className="family-codes-list"
        aria-labelledby="family-codes-title"
      >
        <div className="section-header">
          <h3 id="family-codes-title">Coduri generate</h3>
          <button
            className="icon-button"
            aria-label="Actualizează codurile de invitație"
            disabled={busy || loading}
            onClick={() => setRevision((value) => value + 1)}
          >
            <RefreshCw size={17} />
          </button>
        </div>
        {loading ? (
          <p className="fine-print" role="status">
            Se încarcă codurile…
          </p>
        ) : cache?.length ? (
          <ul>
            {cache.map((code) => {
              const active =
                !code.used_at &&
                !code.revoked_at &&
                new Date(code.expires_at).getTime() > Date.now();
              const status = code.used_at
                ? "Folosit"
                : code.revoked_at
                  ? "Anulat"
                  : active
                    ? "Activ"
                    : "Expirat";
              return (
                <li key={code.id}>
                  <div>
                    <strong>
                      {code.created_at
                        ? `Creat ${date(code.created_at)}`
                        : "Cod generat"}
                    </strong>
                    <p>
                      {status} · Expiră {date(code.expires_at)}
                    </p>
                  </div>
                  {active && (
                    <button
                      className="icon-button"
                      aria-label={`Anulează codul care expiră ${date(code.expires_at)}`}
                      disabled={busy || loading}
                      onClick={() => void revoke(code)}
                    >
                      <X size={18} />
                    </button>
                  )}
                </li>
              );
            })}
          </ul>
        ) : (
          !error && <p className="fine-print">Niciun cod generat.</p>
        )}
      </section>
      {error && (
        <p className="error" role="alert">
          {error}
        </p>
      )}
    </div>
  );
}

function RolePicker({
  value,
  onChange,
  disabled = false,
}: {
  value: EditableRole;
  onChange: (value: EditableRole) => void;
  disabled?: boolean;
}) {
  return (
    <>
      <fieldset className="invite-roles" disabled={disabled}>
        <legend>Rol</legend>
        {(["adult", "limited"] as const).map((role) => (
          <label className={value === role ? "selected" : ""} key={role}>
            {role === "adult" ? (
              <Users className="role-adult" size={21} />
            ) : (
              <LockKeyhole className="role-limited" size={21} />
            )}
            <span>{roleLabel(role)}</span>
            <input
              type="radio"
              name="member-role"
              value={role}
              checked={value === role}
              onChange={() => onChange(role)}
            />
          </label>
        ))}
      </fieldset>
      <p className="fine-print">
        {value === "adult"
          ? "Poate adăuga documente și gestiona accesul la cele proprii."
          : "Poate vedea documentele proprii și cele partajate. Nu poate încărca documente noi."}
      </p>
    </>
  );
}

export function FamilyPage({
  family,
  userId,
  onExpired,
  navigation,
  onMemory,
}: {
  family: Family;
  userId: number;
  onExpired: () => void;
  navigation: ReactNode;
  onMemory: (id: string) => void;
}) {
  const familyId = family.id;
  const isOwner = family.role === "owner";
  const [members, setMembers] = useState<Member[]>([]);
  const [profileMember, setProfileMember] = useState<Member | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState("");
  const [search, setSearch] = useState("");
  const [target, setTarget] = useState<Member | null>(null);
  const [busy, setBusy] = useState(false);
  const [revokeError, setRevokeError] = useState("");
  const [managing, setManaging] = useState<Member | null>(null);
  const [memberRole, setMemberRole] = useState<EditableRole>("adult");
  const [roleError, setRoleError] = useState("");
  const [roleStale, setRoleStale] = useState(false);
  const [message, setMessage] = useState("");
  const [invitations, setInvitations] = useState<Invitation[]>([]);
  const [inviting, setInviting] = useState(false);
  const [inviteMode, setInviteMode] = useState<"invitation" | "child" | "code">(
    "invitation",
  );
  const [email, setEmail] = useState("");
  const [role, setRole] = useState<"adult" | "limited">("adult");
  const [inviteError, setInviteError] = useState("");
  const [inviteLink, setInviteLink] = useState("");
  const [copied, setCopied] = useState(false);
  const [invitationCodes, setInvitationCodes] = useState<
    InvitationCode[] | null
  >(null);
  const sequence = useRef(0);

  async function load() {
    const request = ++sequence.current;
    setLoading(true);
    setError("");
    try {
      const [data, pending] = await Promise.all([
        api<Member[]>(`/families/${familyId}/members`),
        isOwner
          ? api<Invitation[]>(`/families/${familyId}/invitations`)
          : Promise.resolve([]),
      ]);
      if (request === sequence.current) {
        setMembers(data);
        setInvitations(pending);
      }
    } catch (e) {
      if (request !== sequence.current) return;
      setMembers([]);
      setError("Nu am putut încărca membrii.");
      if (e instanceof ApiError && [401, 404, 419].includes(e.status))
        onExpired();
    } finally {
      if (request === sequence.current) setLoading(false);
    }
  }
  useEffect(() => {
    void load();
    return () => {
      sequence.current++;
    };
  }, [familyId]);

  async function revoke() {
    if (!target || busy) return;
    setBusy(true);
    setRevokeError("");
    try {
      await api(`/families/${familyId}/members/${target.id}`, {
        method: "DELETE",
      });
      setMessage(
        target.user_id === null
          ? `${target.name}: profil eliminat din familie.`
          : `Acces revocat pentru ${target.name}.`,
      );
      setTarget(null);
      await load();
    } catch (e) {
      setRevokeError(e instanceof Error ? e.message : "Revocarea nu a reușit.");
    } finally {
      setBusy(false);
    }
  }

  async function saveRole() {
    if (!managing || busy || roleStale) return;
    setBusy(true);
    setRoleError("");
    try {
      await api(`/families/${familyId}/members/${managing.id}/role`, {
        method: "PATCH",
        body: JSON.stringify({
          role: memberRole,
          expected_role: managing.role,
        }),
      });
      setMessage(`Rol actualizat pentru ${managing.name}.`);
      setManaging(null);
      await load();
    } catch (e) {
      setRoleError(
        e instanceof Error ? e.message : "Rolul nu a putut fi salvat.",
      );
      if (e instanceof ApiError && [403, 404, 409].includes(e.status)) {
        setRoleStale(true);
        await load();
      }
      if (e instanceof ApiError && [401, 419].includes(e.status)) onExpired();
    } finally {
      setBusy(false);
    }
  }

  const visible = members.filter((member) =>
    member.name
      .toLocaleLowerCase("ro")
      .includes(search.trim().toLocaleLowerCase("ro")),
  );
  if (profileMember)
    return (
      <MemberProfile
        key={profileMember.id}
        family={family}
        userId={userId}
        member={profileMember}
        members={members}
        onMember={(id) => {
          const relative = members.find((member) => member.id === id);
          if (relative) setProfileMember(relative);
        }}
        onMemory={onMemory}
        onBack={() => {
          setProfileMember(null);
          void load();
        }}
        onExpired={onExpired}
      />
    );
  return (
    <div className="family-page">
      <PageBackdrop image="family">
        <section className="page-heading">
          <h1>Familia mea</h1>
          {isOwner && (
            <button
              className="button primary"
              aria-label="Adaugă membru"
              onClick={() => {
                setEmail("");
                setInviteMode("invitation");
                setRole("adult");
                setInviteError("");
                setInviteLink("");
                setCopied(false);
                setInviting(true);
              }}
            >
              <UserRoundPlus size={18} />
              <span>Adaugă membru</span>
            </button>
          )}
        </section>
      </PageBackdrop>
      {navigation}
      <section className="family-panel" aria-labelledby="members-title">
        <div className="section-header">
          <h2 id="members-title" className="family-panel-title">
            <span className="family-section-icon">
              <Users size={20} aria-hidden="true" />
            </span>
            {loading
              ? "Membri"
              : `${visible.length} ${visible.length === 1 ? "membru" : "membri"}`}
          </h2>
          <button
            className="text-button"
            aria-label="Actualizează membrii"
            onClick={() => void load()}
            disabled={loading || busy}
          >
            <RefreshCw size={17} className={loading ? "spin" : ""} />
            <span>Actualizează</span>
          </button>
        </div>
        <div className="family-search search">
          <Search size={19} aria-hidden="true" />
          <input
            id="family-search"
            type="search"
            aria-label="Caută membri"
            placeholder="Caută un membru…"
            value={search}
            onChange={(e) => setSearch(e.target.value)}
          />
          {search && (
            <button aria-label="Șterge căutarea" onClick={() => setSearch("")}>
              <X size={18} />
            </button>
          )}
        </div>
        {message && (
          <div role="status" className="notice">
            <Check size={18} />
            <span>{message}</span>
            <button
              aria-label="Închide notificarea"
              onClick={() => setMessage("")}
            >
              <X size={16} />
            </button>
          </div>
        )}
        {error ? (
          <div role="alert" className="error">
            {error}
            <button className="text-button" onClick={() => void load()}>
              Reîncearcă
            </button>
          </div>
        ) : loading ? (
          <div
            className="family-grid"
            role="status"
            aria-label="Se încarcă membrii"
          >
            <div className="family-skeleton skeleton" />
            <div className="family-skeleton skeleton" />
          </div>
        ) : visible.length ? (
          <section className="family-grid" aria-labelledby="members-title">
            {visible.map((member) => {
              const RoleIcon =
                member.role === "owner"
                  ? Crown
                  : member.role === "limited"
                    ? LockKeyhole
                    : Users;
              const canRevoke =
                isOwner && member.role !== "owner" && member.user_id !== userId;
              return (
                <article
                  className={`family-member member-${member.role}`}
                  key={member.id}
                >
                  <button
                    className="family-member-info family-profile-link"
                    aria-label={`Profil: ${member.name}`}
                    aria-describedby={`family-member-role-${member.id}`}
                    onClick={() => setProfileMember(member)}
                  >
                    <div
                      className={`family-portrait portrait-${member.role}`}
                      aria-hidden="true"
                    >
                      {member.photo_status === "ready" ? (
                        <PrivatePhoto
                          src={`/api/v1/families/${familyId}/members/${member.id}/profile/photo?v=${member.photo_version}`}
                          status={member.photo_status}
                          className="family-member-photo"
                          placeholder={<Users size={23} />}
                          alt=""
                        />
                      ) : (
                        <span>{initials(member.name)}</span>
                      )}
                    </div>
                    <span className="family-member-label">
                      <span className="family-member-name" title={member.name}>
                        {member.name}
                      </span>
                      <span
                        className="family-role"
                        id={`family-member-role-${member.id}`}
                      >
                        <RoleIcon
                          className={`role-${member.role}`}
                          size={15}
                          aria-hidden="true"
                        />
                        {member.age != null && member.age < 18
                          ? `Copil · ${member.age} ani`
                          : roleLabel(member.role)}
                      </span>
                    </span>
                    <ChevronRight
                      className="family-profile-icon"
                      size={18}
                      aria-hidden="true"
                    />
                  </button>
                  {member.user_id === userId && (
                    <span className="family-self">Tu</span>
                  )}
                  {canRevoke && (
                    <button
                      className="family-manage"
                      aria-label={`Gestionează ${member.name}`}
                      aria-haspopup="dialog"
                      disabled={busy}
                      onClick={() => {
                        setRoleError("");
                        setRoleStale(false);
                        setMemberRole(
                          member.role === "limited" ? "limited" : "adult",
                        );
                        setManaging(member);
                      }}
                    >
                      <MoreHorizontal size={21} aria-hidden="true" />
                    </button>
                  )}
                </article>
              );
            })}
          </section>
        ) : (
          <div className="empty">
            <Users size={36} />
            <h3>{search ? "Niciun rezultat" : "Niciun membru"}</h3>
            {search && (
              <button
                className="button secondary"
                onClick={() => setSearch("")}
              >
                Resetează căutarea
              </button>
            )}
          </div>
        )}
      </section>
      {isOwner && !loading && !error && invitations.length > 0 && (
        <section
          className="family-panel invitations-panel"
          aria-labelledby="invitations-title"
        >
          <div className="section-header">
            <h2 id="invitations-title">
              <span className="family-section-icon">
                <Mail size={20} aria-hidden="true" />
              </span>
              Invitații în așteptare{" "}
              <span className="invitation-count">{invitations.length}</span>
            </h2>
          </div>
          <div className="invitation-list">
            {invitations.map((invitation) => (
              <article className="invitation-row" key={invitation.id}>
                <span className="invitation-icon">
                  <Mail size={21} aria-hidden="true" />
                </span>
                <div>
                  <h3>{invitation.email}</h3>
                  <p>
                    {roleLabel(invitation.role)} · Expiră{" "}
                    {new Date(invitation.expires_at).toLocaleDateString(
                      "ro-RO",
                    )}
                  </p>
                </div>
                <button
                  className="text-button"
                  disabled={busy}
                  aria-label={`Anulează invitația pentru ${invitation.email}`}
                  onClick={async () => {
                    setBusy(true);
                    setMessage("");
                    try {
                      await api(
                        `/families/${familyId}/invitations/${invitation.id}`,
                        { method: "DELETE" },
                      );
                      setMessage("Invitație anulată.");
                      await load();
                    } catch (e) {
                      setError(
                        e instanceof Error
                          ? e.message
                          : "Anularea nu a reușit.",
                      );
                    } finally {
                      setBusy(false);
                    }
                  }}
                >
                  <X size={17} aria-hidden="true" />
                  <span>Anulează</span>
                </button>
              </article>
            ))}
          </div>
        </section>
      )}
      {inviting && (
        <Modal
          title={inviteLink ? "Link de invitație" : "Adaugă membru"}
          onClose={() => {
            if (!busy) setInviting(false);
          }}
        >
          {inviteLink ? (
            <>
              <p className="invited-email">
                <Mail size={20} aria-hidden="true" />
                {email}
              </p>
              <label className="field">
                Link
                <input
                  readOnly
                  value={inviteLink}
                  onFocus={(e) => e.target.select()}
                />
              </label>
              <p className="fine-print">
                Valabil 7 zile, afișat doar acum. Trimite-l doar persoanei
                invitate. Nu se trimite automat un email.
              </p>
              <button
                className="button primary full"
                onClick={async () => {
                  try {
                    await navigator.clipboard.writeText(inviteLink);
                    setCopied(true);
                    setInviteError("");
                  } catch {
                    setInviteError("Selectează și copiază linkul din câmp.");
                  }
                }}
              >
                {copied ? <Check size={18} /> : <Copy size={18} />}
                {copied ? "Copiat" : "Copiază linkul"}
              </button>
              <span className="sr-only" role="status">
                {copied ? "Link copiat" : ""}
              </span>
            </>
          ) : (
            <>
              <SectionTabs
                label="Tip de membru"
                className="section-tabs--scroll"
                value={inviteMode}
                options={[
                  { value: "invitation", label: "Invită membru" },
                  { value: "child", label: "Copil sub 14 ani" },
                  { value: "code", label: "Cod de invitație" },
                ]}
                onChange={(value) => {
                  if (!busy) {
                    setInviteMode(value);
                    setInviteError("");
                  }
                }}
              />
              {inviteMode === "code" ? (
                <InvitationCodes
                  family={family}
                  cache={invitationCodes}
                  onCache={setInvitationCodes}
                  busy={busy}
                  onBusy={setBusy}
                  onExpired={onExpired}
                />
              ) : inviteMode === "child" ? (
                <ChildForm
                  familyId={familyId}
                  members={members}
                  actorId={userId}
                  busy={busy}
                  onBusy={setBusy}
                  onExpired={onExpired}
                  onCreated={(child) => {
                    setMembers((current) =>
                      [...current, child].sort((a, b) =>
                        a.name.localeCompare(b.name, "ro"),
                      ),
                    );
                    setInviting(false);
                    setProfileMember(child);
                  }}
                />
              ) : (
                <form
                  onSubmit={async (e) => {
                    e.preventDefault();
                    if (busy) return;
                    setBusy(true);
                    setInviteError("");
                    try {
                      const result = await api<{ token: string }>(
                        `/families/${familyId}/invitations`,
                        {
                          method: "POST",
                          body: JSON.stringify({ email, role }),
                        },
                      );
                      setInviteLink(
                        `${new URL("/app/", document.baseURI).origin}/app/#invite=${familyId}.${result.token}`,
                      );
                      await load();
                    } catch (e) {
                      setInviteError(
                        e instanceof Error
                          ? e.message
                          : "Invitația nu a putut fi creată.",
                      );
                    } finally {
                      setBusy(false);
                    }
                  }}
                >
                  <label className="field">
                    Adresa de email
                    <input
                      type="email"
                      disabled={busy}
                      autoComplete="email"
                      required
                      maxLength={254}
                      value={email}
                      onChange={(e) => setEmail(e.target.value)}
                    />
                  </label>
                  <RolePicker value={role} onChange={setRole} disabled={busy} />
                  <button className="button primary full" disabled={busy}>
                    <UserRoundPlus size={18} />
                    {busy ? "Se creează…" : "Creează invitația"}
                  </button>
                </form>
              )}
            </>
          )}
          {inviteError && (
            <p className="error" role="alert">
              {inviteError}
            </p>
          )}
          <button
            className="button secondary full"
            disabled={busy}
            onClick={() => setInviting(false)}
          >
            {inviteLink ? "Închide" : "Anulează"}
          </button>
        </Modal>
      )}
      {managing && (
        <Modal
          title="Gestionează membrul"
          onClose={() => {
            if (!busy) setManaging(null);
          }}
        >
          <div className="revoke-person">
            <span
              className={`family-portrait portrait-${managing.role}`}
              aria-hidden="true"
            >
              {initials(managing.name)}
            </span>
            <strong>{managing.name}</strong>
          </div>
          {managing.user_id === null ? (
            <button
              className="button primary full"
              onClick={() => {
                setProfileMember(managing);
                setManaging(null);
              }}
            >
              Deschide profilul
            </button>
          ) : (
            <form
              onSubmit={(e) => {
                e.preventDefault();
                void saveRole();
              }}
            >
              <RolePicker
                value={memberRole}
                onChange={setMemberRole}
                disabled={busy || roleStale}
              />
              {roleError && (
                <p className="error" role="alert">
                  {roleError}
                </p>
              )}
              {roleStale ? (
                <button
                  type="button"
                  className="button secondary full"
                  onClick={() => setManaging(null)}
                >
                  Înapoi la membri
                </button>
              ) : (
                <button
                  className="button primary full"
                  disabled={busy || memberRole === managing.role}
                >
                  {busy ? "Se salvează…" : "Salvează rolul"}
                </button>
              )}
            </form>
          )}
          <button
            className="button member-revoke-action full"
            disabled={busy || roleStale}
            onClick={() => {
              setTarget(managing);
              setManaging(null);
              setRevokeError("");
            }}
          >
            <UserRoundMinus size={18} />
            {managing.user_id === null
              ? "Elimină din familie"
              : "Revocă accesul"}
          </button>
        </Modal>
      )}
      {target && (
        <Modal
          title={
            target.user_id === null ? "Elimină din familie" : "Revocă accesul"
          }
          onClose={() => {
            if (!busy) setTarget(null);
          }}
        >
          <div className="revoke-person">
            <span className="avatar" aria-hidden="true">
              {initials(target.name)}
            </span>
            <strong>{target.name}</strong>
          </div>
          <p className="revoke-consequence">
            {target.user_id === null
              ? "Profilul copilului nu va mai fi disponibil în această familie. Însemnările nu sunt șterse."
              : "Nu va mai putea accesa această familie sau documentele ei, inclusiv prin linkuri salvate."}
          </p>
          <p className="fine-print">
            {target.user_id !== null &&
              "Copiile deja descărcate nu pot fi retrase. "}
            Reactivarea nu este disponibilă din aplicație.
          </p>
          {revokeError && (
            <p className="error" role="alert">
              {revokeError}
            </p>
          )}
          <button
            className="button danger-button full"
            disabled={busy}
            onClick={() => void revoke()}
          >
            {busy
              ? "Se elimină…"
              : target.user_id === null
                ? "Elimină din familie"
                : "Revocă accesul"}
          </button>
          <button
            className="button secondary full"
            disabled={busy}
            onClick={() => setTarget(null)}
          >
            Anulează
          </button>
        </Modal>
      )}
    </div>
  );
}
