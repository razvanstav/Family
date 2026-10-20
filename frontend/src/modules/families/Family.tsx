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
} from "lucide-react";
import { api, ApiError, type Member } from "../../shared/api";
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
  const sequence = useRef(0);

  async function load() {
    const request = ++sequence.current;
    setLoading(true);
    setError("");
    try {
      const data = await api<Member[]>(`/families/${familyId}/members`);
      if (request === sequence.current) setMembers(data);
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
              <article
                className={`family-member family-color-${members.indexOf(member) % 4}`}
                key={member.id}
              >
                <div className="family-member-top">
                  <span className="family-role-icon" aria-hidden="true">
                    <RoleIcon size={19} strokeWidth={1.6} />
                  </span>
                  {member.user_id === userId && (
                    <span className="family-self">Tu</span>
                  )}
                </div>
                <div className="family-portrait" aria-hidden="true">
                  <span>{initials(member.name)}</span>
                </div>
                <h3>{member.name}</h3>
                <p className="family-role">{roleLabel(member.role)}</p>
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
            <button className="button secondary" onClick={() => setSearch("")}>
              Resetează căutarea
            </button>
          )}
        </div>
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
