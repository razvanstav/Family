import { useState, type FormEvent } from "react";
import { Camera, LogOut } from "lucide-react";
import {
  api,
  ApiError,
  errorMessage,
  type Family,
  type Session,
} from "../shared/api";
import { Modal } from "../shared/Modal";
import { UserAvatar } from "../shared/UserAvatar";
import { ProfilePhotoEditor } from "../shared/ProfilePhotoEditor";
import type { Profile } from "../modules/families/profileData";

type User = NonNullable<Session["user"]>;

export function AccountDialog({
  user,
  family,
  profile,
  profileError,
  demo,
  logoutBusy,
  logoutError,
  onUserSaved,
  onProfileSaved,
  onReloadProfile,
  onLogout,
  onClose,
  onExpired,
}: {
  user: User;
  family?: Family;
  profile: Profile | null;
  profileError: string;
  demo: boolean;
  logoutBusy: boolean;
  logoutError: string;
  onUserSaved: (user: User) => void;
  onProfileSaved: (profile: Profile) => void;
  onReloadProfile: () => void;
  onLogout: () => void;
  onClose: () => void;
  onExpired: () => void;
}) {
  const [name, setName] = useState(user.name);
  const [expectedName, setExpectedName] = useState(user.name);
  const [editingPhoto, setEditingPhoto] = useState(false);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState("");
  const [notice, setNotice] = useState("");
  const [stale, setStale] = useState(false);
  const base = family
    ? `/families/${family.id}/members/${family.membership_id}/profile`
    : "";
  const photo =
    profile?.photo_status === "ready"
      ? `/api/v1${base}/photo?v=${profile.version}`
      : undefined;
  async function save(event: FormEvent) {
    event.preventDefault();
    if (busy || stale) return;
    setBusy(true);
    setError("");
    setNotice("");
    try {
      const saved = await api<User>("/account", {
        method: "PATCH",
        body: JSON.stringify({
          name: name.trim(),
          expected_name: expectedName,
        }),
      });
      onUserSaved(saved);
      setExpectedName(saved.name);
      setName(saved.name);
      setNotice("Numele a fost salvat.");
    } catch (e) {
      setError(errorMessage(e));
      if (e instanceof ApiError && e.status === 412) setStale(true);
    } finally {
      setBusy(false);
    }
  }
  async function reloadName() {
    setBusy(true);
    setError("");
    try {
      const current = await api<Session>("/session");
      if (!current.user || current.user.id !== user.id) {
        onExpired();
        return;
      }
      onUserSaved(current.user);
      setName(current.user.name);
      setExpectedName(current.user.name);
      setStale(false);
    } catch (e) {
      setError(errorMessage(e));
    } finally {
      setBusy(false);
    }
  }
  if (editingPhoto && profile)
    return (
      <ProfilePhotoEditor
        profile={profile}
        base={base}
        onClose={() => setEditingPhoto(false)}
        onSaved={(saved) => {
          onProfileSaved(saved);
          setEditingPhoto(false);
          setNotice("Fotografia a fost actualizată.");
        }}
        onReload={() => {
          setEditingPhoto(false);
          onReloadProfile();
        }}
      />
    );
  return (
    <Modal
      title="Contul meu"
      onClose={() => {
        if (!busy && !logoutBusy) onClose();
      }}
    >
      <div className="account-info">
        <UserAvatar name={user.name} photo={photo} className="avatar big" />
        <p>{user.email}</p>
      </div>
      {family && (
        <div className="account-photo-controls">
          <span>{family.name}</span>
          {profile?.can_write && (
            <button
              className="button secondary"
              disabled={busy || logoutBusy}
              onClick={() => setEditingPhoto(true)}
            >
              <Camera size={18} />
              Schimbă fotografia
            </button>
          )}
          {!profile && !profileError && (
            <p role="status">Se încarcă fotografia…</p>
          )}
          {profile?.photo_status === "quarantine" && (
            <p role="status">Fotografie în verificare.</p>
          )}
          {profile?.photo_status === "rejected" && (
            <p className="error" role="alert">
              Fotografie respinsă. Alege o altă imagine.
            </p>
          )}
          {profile?.photo_status === "quarantine" && profile.can_write && (
            <button
              className="text-button"
              disabled={busy}
              onClick={async () => {
                setBusy(true);
                setError("");
                try {
                  onProfileSaved(
                    await api<Profile>(`${base}/photo/scan`, {
                      method: "POST",
                      headers: { "If-Match": `"${profile.version}"` },
                    }),
                  );
                } catch (e) {
                  setError(errorMessage(e));
                } finally {
                  setBusy(false);
                }
              }}
            >
              Reverifică fotografia
            </button>
          )}
          {profileError && (
            <>
              <p className="error" role="alert">
                {profileError}
              </p>
              <button className="text-button" onClick={onReloadProfile}>
                Reîncarcă fotografia
              </button>
            </>
          )}
        </div>
      )}
      <form onSubmit={save}>
        <label className="field">
          Nume
          <input
            autoComplete="name"
            value={name}
            required
            maxLength={100}
            disabled={busy || stale || logoutBusy}
            onChange={(e) => {
              setName(e.target.value);
              setNotice("");
            }}
            aria-describedby="account-name-hint"
          />
          <small id="account-name-hint">
            Numele se schimbă în toate familiile tale.
          </small>
        </label>
        {error && (
          <p className="error" role="alert">
            {error}
          </p>
        )}
        {notice && (
          <p className="notice" role="status">
            {notice}
          </p>
        )}
        <button
          className="button primary full"
          disabled={
            busy ||
            stale ||
            logoutBusy ||
            !name.trim() ||
            name.trim() === expectedName
          }
        >
          {busy ? "Se salvează…" : "Salvează"}
        </button>
        {stale && (
          <button
            type="button"
            className="button secondary full account-reload"
            disabled={busy}
            onClick={() => void reloadName()}
          >
            Renunță la modificări și reîncarcă
          </button>
        )}
      </form>
      {demo && <p className="demo-note">Cont demo · date fictive.</p>}
      {logoutError && (
        <p className="error" role="alert">
          {logoutError}
        </p>
      )}
      <button
        className="button secondary full"
        disabled={busy || logoutBusy}
        onClick={onLogout}
      >
        <LogOut size={18} />
        {logoutBusy ? "Se deconectează…" : "Deconectează-te"}
      </button>
    </Modal>
  );
}
