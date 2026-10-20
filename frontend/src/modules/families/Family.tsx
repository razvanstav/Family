import { useEffect, useRef, useState } from "react";
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
} from "lucide-react";
import { api, ApiError, type Member, type Invitation } from "../../shared/api";
import { Modal } from "../../shared/Modal";

const initials = (name: string) =>
  name
    .trim()
    .split(/\s+/)
    .map((part) => part[0])
    .slice(0, 2)
    .join("");
const roleLabel = (role: string) =>
  role === "owner"
    ? "Proprietar"
    : role === "limited"
      ? "Acces limitat"
      : "Membru adult";

export function FamilyPage({
  familyId,
  isOwner,
  userId,
  onExpired,
}: {
  familyId: string;
  isOwner: boolean;
  userId: number;
  onExpired: () => void;
}) {
  const [members, setMembers] = useState<Member[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState("");
  const [search, setSearch] = useState("");
  const [target, setTarget] = useState<Member | null>(null);
  const [busy, setBusy] = useState(false);
  const [revokeError, setRevokeError] = useState("");
  const [message, setMessage] = useState("");
  const [invitations, setInvitations] = useState<Invitation[]>([]);
  const [inviting, setInviting] = useState(false);
  const [email, setEmail] = useState("");
  const [role, setRole] = useState<"adult" | "limited">("adult");
  const [inviteError, setInviteError] = useState("");
  const [inviteLink, setInviteLink] = useState("");
  const [copied, setCopied] = useState(false);
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
    const check = () => {
      if (document.visibilityState === "visible") void load();
    };
    document.addEventListener("visibilitychange", check);
    return () => {
      sequence.current++;
      document.removeEventListener("visibilitychange", check);
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
      setMessage(`Acces revocat pentru ${target.name}.`);
      setTarget(null);
      await load();
    } catch (e) {
      setRevokeError(e instanceof Error ? e.message : "Revocarea nu a reușit.");
    } finally {
      setBusy(false);
    }
  }

  const visible = members.filter((member) =>
    member.name
      .toLocaleLowerCase("ro")
      .includes(search.trim().toLocaleLowerCase("ro")),
  );
  return (
    <div className="family-page">
      <section className="page-heading">
        <h1>Familia mea</h1>
        {isOwner && (
          <button
            className="button primary"
            onClick={() => {
              setEmail("");
              setRole("adult");
              setInviteError("");
              setInviteLink("");
              setCopied(false);
              setInviting(true);
            }}
          >
            <UserRoundPlus size={18} />
            Adaugă membru
          </button>
        )}
      </section>
      <div className="family-search search">
        <Search size={20} aria-hidden="true" />
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
      <section className="family-panel" aria-labelledby="members-title">
        <div className="section-header">
          <h2 id="members-title">
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
                <article className="family-member" key={member.id}>
                  <div className="family-member-top">
                    {member.user_id === userId && (
                      <span className="family-self">Tu</span>
                    )}
                  </div>
                  <div className="family-portrait" aria-hidden="true">
                    <span>{initials(member.name)}</span>
                  </div>
                  <h3>{member.name}</h3>
                  <p className="family-role">
                    <RoleIcon
                      className={`role-${member.role}`}
                      size={15}
                      aria-hidden="true"
                    />
                    {roleLabel(member.role)}
                  </p>
                  {canRevoke && (
                    <button
                      className="family-revoke"
                      aria-label={`Revocă accesul pentru ${member.name}`}
                      onClick={() => {
                        setRevokeError("");
                        setTarget(member);
                      }}
                    >
                      <UserRoundMinus size={17} />
                      Revocă accesul
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
            <form
              onSubmit={async (e) => {
                e.preventDefault();
                if (busy) return;
                setBusy(true);
                setInviteError("");
                try {
                  const result = await api<{ token: string }>(
                    `/families/${familyId}/invitations`,
                    { method: "POST", body: JSON.stringify({ email, role }) },
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
              <fieldset className="invite-roles" disabled={busy}>
                <legend>Rol</legend>
                {(["adult", "limited"] as const).map((value) => (
                  <label
                    className={role === value ? "selected" : ""}
                    key={value}
                  >
                    {value === "adult" ? (
                      <Users className="role-adult" size={21} />
                    ) : (
                      <LockKeyhole className="role-limited" size={21} />
                    )}
                    <span>{roleLabel(value)}</span>
                    <input
                      type="radio"
                      name="invite-role"
                      value={value}
                      checked={role === value}
                      onChange={() => setRole(value)}
                    />
                  </label>
                ))}
              </fieldset>
              <p className="fine-print">
                {role === "adult"
                  ? "Poate adăuga documente și vede ce îi este partajat."
                  : "Poate vedea documentele partajate cu el. Nu poate încărca documente."}
              </p>
              <button className="button primary full" disabled={busy}>
                <UserRoundPlus size={18} />
                {busy ? "Se creează…" : "Creează invitația"}
              </button>
            </form>
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
      {target && (
        <Modal
          title="Revocă accesul"
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
            Nu va mai putea accesa această familie sau documentele ei, inclusiv
            prin linkuri salvate.
          </p>
          <p className="fine-print">
            Copiile deja descărcate nu pot fi retrase. Reactivarea nu este
            disponibilă din aplicație.
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
            {busy ? "Se revocă…" : "Revocă accesul"}
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
