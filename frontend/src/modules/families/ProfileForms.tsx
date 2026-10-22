import { useState, type FormEvent } from "react";
import { LockKeyhole, Users, Archive, RotateCcw, Pencil } from "lucide-react";
import { api, ApiError, visibilityLabel, type Member } from "../../shared/api";
import { bucharestToday, dateLabel } from "../../shared/calendarDates";
import { Modal } from "../../shared/Modal";
import { ParentPicker } from "./ParentPicker";
import {
  kinds,
  message,
  type Kind,
  type Profile,
  type Entry,
} from "./profileData";

export function ProfileSettings({
  profile,
  members,
  base,
  onClose,
  onSaved,
  onReload,
}: {
  profile: Profile;
  members: Member[];
  base: string;
  onClose: () => void;
  onSaved: (profile: Profile) => void;
  onReload: () => void;
}) {
  const [birth, setBirth] = useState(profile.birth_date ?? "");
  const [name, setName] = useState(profile.name);
  const [parents, setParents] = useState(
    profile.parents.map((parent) => parent.id),
  );
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState("");
  const [stale, setStale] = useState(false);
  const today = bucharestToday();
  const minor =
    birth !== "" &&
    birth > `${Number(today.slice(0, 4)) - 18}${today.slice(4)}`;
  async function save(e: FormEvent) {
    e.preventDefault();
    setBusy(true);
    setError("");
    try {
      onSaved(
        await api<Profile>(base, {
          method: "PATCH",
          headers: { "If-Match": `"${profile.version}"` },
          body: JSON.stringify({
            ...(profile.user_id === null ? { name } : {}),
            birth_date: birth || null,
            parent_ids: minor ? parents : [],
          }),
        }),
      );
    } catch (e) {
      setError(message(e));
      if (e instanceof ApiError && [403, 404, 412].includes(e.status))
        setStale(true);
    } finally {
      setBusy(false);
    }
  }
  return (
    <Modal
      title="Data nașterii și părinți"
      onClose={() => {
        if (!busy) onClose();
      }}
    >
      <form onSubmit={save}>
        <fieldset className="profile-form-fields" disabled={busy || stale}>
          <label className="field">
            Data nașterii
            <input
              type="date"
              required={profile.user_id === null}
              min="1900-01-01"
              max={today}
              value={birth}
              onChange={(e) => setBirth(e.target.value)}
            />
          </label>
          {profile.user_id === null && (
            <label className="field">
              Numele copilului
              <input
                required
                maxLength={100}
                value={name}
                onChange={(event) => setName(event.target.value)}
              />
            </label>
          )}
          {minor && (
            <ParentPicker
              members={members}
              childId={profile.id}
              selected={parents}
              onChange={setParents}
            >
              <p className="fine-print">
                {profile.user_id === null ? (
                  "Profil fără cont. Datele sunt adăugate de părinții desemnați până la 18 ani."
                ) : (
                  <>
                    Sub 14 ani: numai părinții desemnați adaugă date. De la 14
                    ani: și copilul. Însemnările private ale copilului rămân
                    doar ale lui. Administrarea de către părinți se încheie la
                    18 ani.
                  </>
                )}
              </p>
            </ParentPicker>
          )}
          <button
            className="button primary full"
            disabled={minor && !parents.length}
          >
            Salvează
          </button>
        </fieldset>
        {error && (
          <p role="alert" className="error">
            {error}
          </p>
        )}
        {stale && (
          <button
            type="button"
            className="button secondary full"
            onClick={onReload}
          >
            Renunță la modificări și reîncarcă
          </button>
        )}
      </form>
    </Modal>
  );
}

export function ProfilePhoto({
  profile,
  base,
  onClose,
  onSaved,
  onReload,
}: {
  profile: Profile;
  base: string;
  onClose: () => void;
  onSaved: (profile: Profile) => void;
  onReload: () => void;
}) {
  const [file, setFile] = useState<File | null>(null);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState("");
  const [stale, setStale] = useState(false);
  async function save(action: "replace" | "remove") {
    setBusy(true);
    setError("");
    const data = new FormData();
    data.set("action", action);
    if (file && action === "replace") data.set("photo", file);
    try {
      onSaved(
        await api<Profile>(`${base}/photo`, {
          method: "POST",
          headers: { "If-Match": `"${profile.version}"` },
          body: data,
        }),
      );
    } catch (e) {
      setError(message(e));
      if (e instanceof ApiError && [403, 404, 412].includes(e.status))
        setStale(true);
    } finally {
      setBusy(false);
    }
  }
  return (
    <Modal
      title="Fotografie de profil"
      onClose={() => {
        if (!busy) onClose();
      }}
    >
      <fieldset className="profile-form-fields" disabled={busy || stale}>
        <label className="field">
          Fotografie
          <input
            type="file"
            accept="image/jpeg,image/png,image/webp"
            onChange={(e) => setFile(e.target.files?.[0] ?? null)}
          />
          <small>
            JPG, PNG sau WebP, maximum 10 MB și 16 megapixeli. Fotografia este
            vizibilă membrilor familiei.
          </small>
        </label>
        <button
          className="button primary full"
          disabled={!file}
          onClick={() => void save("replace")}
        >
          Salvează fotografia
        </button>
        {profile.photo_status && (
          <button
            className="button secondary full profile-photo-remove"
            onClick={() => void save("remove")}
          >
            Elimină fotografia
          </button>
        )}
      </fieldset>
      {error && (
        <p role="alert" className="error">
          {error}
        </p>
      )}
      {stale && (
        <button className="button secondary full" onClick={onReload}>
          Renunță la modificări și reîncarcă
        </button>
      )}
    </Modal>
  );
}

export function ProfileEntryModal({
  entry,
  defaultKind,
  base,
  onClose,
  onSaved,
  onReload,
}: {
  entry: Entry | null;
  defaultKind: Kind;
  base: string;
  onClose: () => void;
  onSaved: (entry: Entry) => void;
  onReload: () => void;
}) {
  const [editing, setEditing] = useState(!entry);
  const [kind, setKind] = useState(entry?.kind ?? defaultKind);
  const [title, setTitle] = useState(entry?.title ?? "");
  const [notes, setNotes] = useState(entry?.notes ?? "");
  const [date, setDate] = useState(entry?.occurred_on ?? bucharestToday());
  const [visibility, setVisibility] = useState<"private" | "family">(
    entry?.visibility ?? "private",
  );
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState("");
  const [stale, setStale] = useState(false);
  const [confirm, setConfirm] = useState(false);
  async function mutate(archive = false) {
    setBusy(true);
    setError("");
    try {
      const saved = await api<Entry>(
        `${base}/entries${entry ? `/${entry.id}` : ""}${archive ? "/archive" : ""}`,
        {
          method: archive || !entry ? "POST" : "PATCH",
          headers: entry ? { "If-Match": `"${entry.version}"` } : {},
          body: JSON.stringify(
            archive
              ? { archived: !entry?.archived_at }
              : {
                  kind,
                  title,
                  notes: notes || null,
                  occurred_on: date,
                  visibility,
                },
          ),
        },
      );
      onSaved(saved);
    } catch (e) {
      setError(message(e));
      if (e instanceof ApiError && [403, 404, 409, 412].includes(e.status))
        setStale(true);
    } finally {
      setBusy(false);
    }
  }
  return (
    <Modal
      title={entry ? entry.title : "Adaugă însemnare"}
      onClose={() => {
        if (!busy) onClose();
      }}
    >
      {editing ? (
        <form
          onSubmit={(e) => {
            e.preventDefault();
            void mutate();
          }}
        >
          <fieldset className="profile-form-fields" disabled={busy || stale}>
            <label className="field">
              Tip
              <select
                value={kind}
                onChange={(e) => setKind(e.target.value as Kind)}
              >
                {(Object.entries(kinds) as [Kind, (typeof kinds)[Kind]][]).map(
                  ([key, value]) => (
                    <option key={key} value={key}>
                      {value.label}
                    </option>
                  ),
                )}
              </select>
            </label>
            <label className="field">
              Titlu
              <input
                value={title}
                onChange={(e) => setTitle(e.target.value)}
                maxLength={160}
                required
              />
            </label>
            <label className="field">
              Data
              <input
                type="date"
                value={date}
                min="1900-01-01"
                max="2100-12-31"
                onChange={(e) => setDate(e.target.value)}
                required
              />
            </label>
            <label className="field">
              Detalii
              <textarea
                value={notes}
                onChange={(e) => setNotes(e.target.value)}
                rows={4}
                maxLength={8000}
              />
            </label>
            <label className="field">
              Acces
              <select
                value={visibility}
                onChange={(e) =>
                  setVisibility(e.target.value as "private" | "family")
                }
              >
                <option value="private">Privat</option>
                <option value="family">Familia mea</option>
              </select>
              <small>
                {visibility === "private"
                  ? "Doar tu poți vedea însemnarea."
                  : "Vizibilă membrilor activi ai familiei."}
              </small>
            </label>
            <button className="button primary full" disabled={!title.trim()}>
              {busy ? "Se salvează…" : "Salvează"}
            </button>
          </fieldset>
        </form>
      ) : (
        entry && (
          <div className="profile-entry-detail">
            <p>
              {kinds[entry.kind].label} · {dateLabel(entry.occurred_on)}
            </p>
            <p className="profile-entry-detail-access">
              {entry.visibility === "private" ? (
                <LockKeyhole size={16} />
              ) : (
                <Users size={16} />
              )}{" "}
              {visibilityLabel[entry.visibility]}
            </p>
            {entry.notes && (
              <p className="profile-entry-notes">{entry.notes}</p>
            )}
            <p>Adăugat de {entry.author_name}</p>
            {entry.can_manage && (
              <div className="profile-entry-detail-actions">
                {!entry.archived_at && (
                  <button
                    className="button secondary"
                    disabled={busy || stale}
                    onClick={() => setEditing(true)}
                  >
                    <Pencil size={17} />
                    Editează
                  </button>
                )}
                <button
                  className="button secondary"
                  disabled={busy || stale}
                  onClick={() => setConfirm(true)}
                >
                  {entry.archived_at ? (
                    <RotateCcw size={17} />
                  ) : (
                    <Archive size={17} />
                  )}{" "}
                  {entry.archived_at ? "Restaurează" : "Arhivează"}
                </button>
              </div>
            )}
            {confirm && (
              <div className="profile-archive-confirm">
                <p>
                  {entry.archived_at
                    ? "Însemnarea va rămâne privată după restaurare."
                    : "Arhivarea retrage accesul familiei. Însemnarea rămâne privată în Arhivate."}
                </p>
                <button
                  className="button primary"
                  disabled={busy || stale}
                  onClick={() => void mutate(true)}
                >
                  {entry.archived_at ? "Restaurează" : "Arhivează"}
                </button>
              </div>
            )}
          </div>
        )
      )}
      {error && (
        <p className="error" role="alert">
          {error}
        </p>
      )}
      {stale && (
        <button className="button secondary full" onClick={onReload}>
          Renunță la modificări și reîncarcă
        </button>
      )}
    </Modal>
  );
}
