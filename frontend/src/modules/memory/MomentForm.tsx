import { useEffect, useRef, useState, type FormEvent } from "react";
import { api, ApiError, shareableMembers, type Member } from "../../shared/api";
import { bucharestToday } from "../../shared/calendarDates";
import { Modal } from "../../shared/Modal";
import { momentKinds, type Moment } from "./memoryTypes";
import { MomentPhotoEditor } from "./MomentPhotoEditor";

export function MomentForm({
  base,
  actorId,
  moment: initialMoment,
  onClose,
  onSaved,
  onReloaded,
  onUnavailable,
}: {
  base: string;
  actorId: number;
  moment?: Moment;
  onClose: () => void;
  onSaved: (moment: Moment) => void;
  onReloaded: (moment: Moment) => void;
  onUnavailable: () => void;
}) {
  const [moment, setMoment] = useState(initialMoment);
  const [kind, setKind] = useState<Moment["kind"]>(
    initialMoment?.kind ?? "moment",
  );
  const [people, setPeople] = useState(
    initialMoment?.people.map((person) => person.id) ?? [],
  );
  const [members, setMembers] = useState<Member[] | null>(null);
  const [visibility, setVisibility] = useState<Moment["visibility"]>(
    initialMoment?.visibility ?? "private",
  );
  const [grants, setGrants] = useState(initialMoment?.grants ?? []);
  const [photoIds, setPhotoIds] = useState(
    initialMoment?.photos.map((photo) => photo.id) ?? [],
  );
  const [uploads, setUploads] = useState<File[]>([]);
  const [membersError, setMembersError] = useState(false);
  const [membersRevision, setMembersRevision] = useState(0);
  const [busy, setBusy] = useState(false);
  const [conflict, setConflict] = useState(false);
  const [error, setError] = useState("");
  const titleInput = useRef<HTMLInputElement>(null);
  const pending = useRef<AbortController | null>(null);

  useEffect(() => {
    titleInput.current?.focus();
  }, [moment?.version]);
  useEffect(() => () => pending.current?.abort(), []);
  useEffect(() => {
    const controller = new AbortController();
    setMembersError(false);
    setMembers(null);
    api<Member[]>(`${base.slice(0, -"/moments".length)}/members`, {
      signal: controller.signal,
    })
      .then((data) => {
        if (controller.signal.aborted) return;
        setMembers(data);
        const active = new Set(data.map((member) => member.id));
        setPeople((current) => current.filter((id) => active.has(id)));
        setGrants((current) => current.filter((id) => active.has(id)));
      })
      .catch((e: unknown) => {
        if (controller.signal.aborted) return;
        if (e instanceof ApiError && [401, 403, 404, 419].includes(e.status))
          onUnavailable();
        else setMembersError(true);
      });
    return () => controller.abort();
  }, [base, membersRevision]);

  async function reload() {
    if (!moment || busy) return;
    const controller = new AbortController();
    pending.current = controller;
    setBusy(true);
    setError("");
    try {
      const current = await api<Moment>(`${base}/${moment.id}`, {
        signal: controller.signal,
      });
      if (controller.signal.aborted) return;
      if (!current.can_edit) {
        onReloaded(current);
        return;
      }
      setMoment(current);
      setKind(current.kind);
      setPeople(current.people.map((person) => person.id));
      setVisibility(current.visibility);
      setGrants(current.grants);
      setPhotoIds(current.photos.map((photo) => photo.id));
      setUploads([]);
      setConflict(false);
      setMembersRevision((value) => value + 1);
      onReloaded(current);
    } catch (e) {
      if (controller.signal.aborted) return;
      if (e instanceof ApiError && [401, 403, 404, 419].includes(e.status))
        onUnavailable();
      else setError("Amintirea nu a putut fi reîncărcată. Reîncearcă.");
    } finally {
      if (!controller.signal.aborted) setBusy(false);
    }
  }

  async function save(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    if (busy || conflict || !members || membersError) return;
    const form = new FormData(event.currentTarget);
    const body = {
      kind,
      title: String(form.get("title") ?? "").trim(),
      body: String(form.get("body") ?? "").trim() || null,
      occurred_on: String(form.get("occurred_on")),
      visibility,
    };
    if (!body.title || (kind === "note" && !body.body)) {
      setError(
        !body.title ? "Completează titlul." : "Completează textul notei.",
      );
      return;
    }
    Object.entries(body).forEach(([key, value]) => form.set(key, value ?? ""));
    people.forEach((id) => form.append("membership_ids[]", id));
    if (visibility === "selected")
      grants.forEach((id) => form.append("grant_membership_ids[]", id));
    form.set("photos_mode", "edit");
    photoIds.forEach((id) => form.append("photo_ids[]", id));
    uploads.forEach((file) => form.append("photos[]", file));
    if (moment) form.set("_method", "PATCH");
    const controller = new AbortController();
    pending.current = controller;
    setBusy(true);
    setError("");
    try {
      const saved = await api<Moment>(moment ? `${base}/${moment.id}` : base, {
        method: "POST",
        ...(moment ? { headers: { "If-Match": `"${moment.version}"` } } : {}),
        body: form,
        signal: controller.signal,
      });
      if (!controller.signal.aborted) onSaved(saved);
    } catch (e) {
      if (controller.signal.aborted) return;
      if (e instanceof ApiError && [401, 403, 404, 419].includes(e.status))
        onUnavailable();
      else if (e instanceof ApiError && [409, 412].includes(e.status)) {
        setConflict(true);
        setError(
          "Amintirea s-a schimbat între timp. Reîncărcarea înlocuiește modificările nesalvate.",
        );
      } else
        setError(
          e instanceof ApiError
            ? e.message
            : "Amintirea nu a putut fi salvată. Reîncearcă.",
        );
    } finally {
      if (!controller.signal.aborted) setBusy(false);
    }
  }

  function addPhotos(files: File[]) {
    if (photoIds.length + uploads.length + files.length > 20) {
      setError("O amintire poate avea cel mult 20 de fotografii.");
      return;
    }
    const next = [...uploads, ...files];
    if (
      next.some(
        (file) =>
          !file.size ||
          !["image/jpeg", "image/png", "image/webp"].includes(file.type),
      )
    ) {
      setError("Alege fotografii JPG, PNG sau WebP cu conținut valid.");
      return;
    }
    if (next.reduce((total, file) => total + file.size, 0) > 10 * 1024 * 1024) {
      setError("Adaugă cel mult 10 MB de fotografii noi la o salvare.");
      return;
    }
    setUploads(next);
    setError("");
  }

  return (
    <Modal
      title={moment ? "Editează amintirea" : "Adaugă amintire"}
      onClose={onClose}
      wide
    >
      <form key={moment?.version ?? 0} onSubmit={(event) => void save(event)}>
        <fieldset className="moment-fields" disabled={busy || conflict}>
          <div className="moment-form-row">
            <label className="field">
              Tip
              <select
                value={kind}
                onChange={(event) =>
                  setKind(event.target.value as Moment["kind"])
                }
              >
                {Object.entries(momentKinds).map(([value, label]) => (
                  <option key={value} value={value}>
                    {label}
                  </option>
                ))}
              </select>
            </label>
            <label className="field">
              Data
              <input
                type="date"
                name="occurred_on"
                required
                min="1900-01-01"
                max="2100-12-31"
                defaultValue={moment?.occurred_on ?? bucharestToday()}
              />
            </label>
          </div>
          <label className="field">
            Titlu
            <input
              name="title"
              required
              maxLength={160}
              defaultValue={moment?.title ?? ""}
              ref={titleInput}
            />
          </label>
          <label className="field">
            Text
            <textarea
              name="body"
              rows={7}
              maxLength={8000}
              required={kind === "note"}
              defaultValue={moment?.body ?? ""}
            />
          </label>
          <MomentPhotoEditor
            base={base}
            moment={moment}
            photoIds={photoIds}
            uploads={uploads}
            onAdd={addPhotos}
            onRemove={(id) =>
              setPhotoIds((current) => current.filter((value) => value !== id))
            }
            onRemoveUpload={(index) =>
              setUploads((current) =>
                current.filter((_, position) => position !== index),
              )
            }
          />
          <div className="field">
            <label htmlFor="moment-access">Acces</label>
            <select
              id="moment-access"
              value={visibility}
              onChange={(event) =>
                setVisibility(event.target.value as Moment["visibility"])
              }
              aria-describedby={moment ? "moment-access-hint" : undefined}
            >
              <option value="private">Doar eu</option>
              <option value="family">Familia mea</option>
              <option value="selected">Persoane alese</option>
            </select>
            {moment && (
              <small id="moment-access-hint">
                Schimbarea accesului se aplică tuturor fotografiilor. Copiile
                deja salvate nu pot fi retrase.
              </small>
            )}
          </div>
          {visibility === "selected" && (
            <fieldset className="moment-people">
              <legend>Persoane cu acces</legend>
              {shareableMembers(members ?? [], actorId).map((member) => (
                <label key={member.id}>
                  <input
                    type="checkbox"
                    checked={grants.includes(member.id)}
                    onChange={(event) =>
                      setGrants((current) =>
                        event.target.checked
                          ? [...current, member.id]
                          : current.filter((id) => id !== member.id),
                      )
                    }
                  />
                  <span>{member.name}</span>
                </label>
              ))}
              {members && !shareableMembers(members, actorId).length && (
                <p>Niciun alt membru activ.</p>
              )}
            </fieldset>
          )}
          <fieldset
            className="moment-people"
            aria-describedby="moment-people-hint"
          >
            <legend>Persoane menționate</legend>
            <p id="moment-people-hint">
              Menționarea unei persoane nu îi acordă acces la amintire.
            </p>
            {membersError ? (
              <div>
                <p className="error" role="alert">
                  Membrii nu au putut fi încărcați.
                </p>
                <button
                  type="button"
                  className="button secondary"
                  onClick={() => setMembersRevision((value) => value + 1)}
                >
                  Reîncearcă
                </button>
              </div>
            ) : !members ? (
              <p role="status">Se încarcă membrii…</p>
            ) : (
              members.map((member) => (
                <label key={member.id}>
                  <input
                    type="checkbox"
                    checked={people.includes(member.id)}
                    onChange={(event) =>
                      setPeople((current) =>
                        event.target.checked
                          ? [...current, member.id]
                          : current.filter((id) => id !== member.id),
                      )
                    }
                  />
                  <span>{member.name}</span>
                </label>
              ))
            )}
          </fieldset>
        </fieldset>
        {error && (
          <p className="error" role="alert">
            {error}
          </p>
        )}
        <div className="moment-actions">
          {conflict && (
            <button
              type="button"
              className="button secondary"
              disabled={busy}
              onClick={() => void reload()}
            >
              Reîncarcă amintirea
            </button>
          )}
          <button
            type="button"
            className="button secondary"
            disabled={busy}
            onClick={onClose}
          >
            Anulează
          </button>
          <button
            className="button primary"
            disabled={
              busy ||
              conflict ||
              !members ||
              membersError ||
              (visibility === "selected" && !grants.length)
            }
          >
            {busy ? "Se salvează…" : "Salvează"}
          </button>
        </div>
      </form>
    </Modal>
  );
}
