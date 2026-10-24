import { useState, type FormEvent } from "react";
import { LockKeyhole, Users, Pencil } from "lucide-react";
import { api, ApiError, visibilityLabel, type Member } from "../../shared/api";
import { bucharestToday, dateLabel } from "../../shared/calendarDates";
import { Modal } from "../../shared/Modal";
import { DatePicker } from "../../shared/DatePicker";
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
  const [birthdayShared, setBirthdayShared] = useState(profile.birthday_shared);
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
            birthday_shared: !!birth && birthdayShared,
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
          <DatePicker
            label="Data nașterii"
            dialogLabel="Alege data nașterii"
            emptyLabel={
              profile.user_id === null ? "Alege data" : "Fără dată de naștere"
            }
            optional={profile.user_id !== null}
            required={profile.user_id === null}
            min="1900-01-01"
            max={today}
            value={birth}
            onChange={setBirth}
          />
          <label className="profile-checkbox">
            <input
              type="checkbox"
              checked={!!birth && birthdayShared}
              disabled={!birth}
              onChange={(e) => setBirthdayShared(e.target.checked)}
            />
            Ziua de naștere în Calendarul familiei
          </label>
          <p className="fine-print">
            Familia vede ziua și luna. Anul nașterii rămâne vizibil numai
            persoanei și administratorului.
            {birth.slice(5) === "02-29"
              ? " În anii fără 29 februarie, aniversarea apare pe 28 februarie."
              : ""}
          </p>
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
  async function save() {
    setBusy(true);
    setError("");
    try {
      const saved = await api<Entry>(
        `${base}/entries${entry ? `/${entry.id}` : ""}`,
        {
          method: entry ? "PATCH" : "POST",
          headers: entry ? { "If-Match": `"${entry.version}"` } : {},
          body: JSON.stringify({
            kind,
            title,
            notes: notes || null,
            occurred_on: date,
            visibility,
          }),
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
            void save();
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
            <DatePicker
              label="Data"
              value={date}
              min="1900-01-01"
              max="2100-12-31"
              onChange={setDate}
              required
            />
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
              <button
                className="button secondary"
                disabled={busy || stale}
                onClick={() => setEditing(true)}
              >
                <Pencil size={17} />
                Editează
              </button>
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
