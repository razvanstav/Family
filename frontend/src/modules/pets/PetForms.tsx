import { useEffect, useRef, useState, type FormEvent } from "react";
import { Archive, RotateCcw } from "lucide-react";
import { api, ApiError, errorMessage } from "../../shared/api";
import { bucharestToday, dateLabel } from "../../shared/calendarDates";
import { DatePicker } from "../../shared/DatePicker";
import { Modal } from "../../shared/Modal";
import { ReminderFields } from "../../shared/ReminderFields";
import { money } from "../../shared/numbers";
import { petEntryPayload } from "./petFormData";
import { careKinds, type CareKind, type Pet, type PetEntry } from "./petData";

function useMutation(onUnavailable: () => void) {
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState("");
  const [stale, setStale] = useState(false);
  const pending = useRef(false);
  async function run(action: () => Promise<void>) {
    if (pending.current) return;
    pending.current = true;
    setBusy(true);
    setError("");
    try {
      await action();
    } catch (error) {
      setError(errorMessage(error));
      if (error instanceof ApiError) {
        if ([409, 412].includes(error.status)) setStale(true);
        if ([401, 403, 404, 419].includes(error.status)) onUnavailable();
      }
    } finally {
      pending.current = false;
      setBusy(false);
    }
  }
  return { busy, error, stale, setStale, run };
}

function MutationFeedback({
  state,
  onReload,
}: {
  state: ReturnType<typeof useMutation>;
  onReload?: () => void;
}) {
  return (
    <>
      {state.error && (
        <p className="error" role="alert">
          {state.error}
        </p>
      )}
      {state.stale && onReload && (
        <button
          className="button secondary full"
          disabled={state.busy}
          onClick={onReload}
        >
          Reîncarcă datele
        </button>
      )}
    </>
  );
}

export function PetForm({
  pet,
  base,
  onClose,
  onSaved,
  onUnavailable,
}: {
  pet: Pet | null;
  base: string;
  onClose: () => void;
  onSaved: (pet: Pet) => void;
  onUnavailable: () => void;
}) {
  const [source, setSource] = useState(pet);
  const [name, setName] = useState(pet?.name ?? "");
  const [species, setSpecies] = useState(pet?.species ?? "");
  const [breed, setBreed] = useState(pet?.breed ?? "");
  const [birthDate, setBirthDate] = useState(pet?.birth_date ?? "");
  const [confirm, setConfirm] = useState(false);
  const nameInput = useRef<HTMLInputElement>(null);
  const state = useMutation(onUnavailable);
  const today = bucharestToday();
  const invalidBirth = birthDate > today;
  useEffect(() => nameInput.current?.focus(), []);
  async function save(event: FormEvent) {
    event.preventDefault();
    if (state.stale || invalidBirth) return;
    await state.run(async () =>
      onSaved(
        await api<Pet>(`${base}${source ? `/${source.id}` : ""}`, {
          method: source ? "PATCH" : "POST",
          headers: source ? { "If-Match": `"${source.version}"` } : undefined,
          body: JSON.stringify({
            name: name.trim(),
            species: species.trim(),
            breed: breed.trim() || null,
            birth_date: birthDate || null,
          }),
        }),
      ),
    );
  }
  async function reload() {
    if (!source) return;
    await state.run(async () => {
      const fresh = await api<Pet>(`${base}/${source.id}`);
      setSource(fresh);
      setName(fresh.name);
      setSpecies(fresh.species);
      setBreed(fresh.breed ?? "");
      setBirthDate(fresh.birth_date ?? "");
      setConfirm(false);
      state.setStale(false);
    });
  }
  return (
    <Modal
      title={source ? "Editează animalul" : "Adaugă animal"}
      onClose={() => {
        if (!state.busy) onClose();
      }}
    >
      <form onSubmit={save}>
        <fieldset
          className="pet-fields"
          disabled={
            state.busy ||
            state.stale ||
            !!source?.archived_at ||
            source?.can_manage === false
          }
        >
          <label className="field">
            Nume
            <input
              ref={nameInput}
              name="name"
              required
              maxLength={100}
              value={name}
              onChange={(event) => setName(event.target.value)}
            />
          </label>
          <label className="field">
            Specie
            <input
              name="species"
              required
              maxLength={60}
              value={species}
              onChange={(event) => setSpecies(event.target.value)}
            />
          </label>
          <label className="field">
            Rasă (opțional)
            <input
              name="breed"
              maxLength={100}
              value={breed}
              onChange={(event) => setBreed(event.target.value)}
            />
          </label>
          <DatePicker
            label="Data nașterii (opțional)"
            name="birth_date"
            dialogLabel="Alege data nașterii"
            emptyLabel="Fără dată"
            optional
            value={birthDate}
            onChange={setBirthDate}
          />
          {invalidBirth && (
            <p className="error" role="alert">
              Data nașterii nu poate fi în viitor.
            </p>
          )}
          <p className="pet-access-note">
            Profilul și îngrijirea sunt vizibile familiei. Membrii cu acces
            limitat pot doar consulta.
          </p>
          <button
            className="button primary full"
            type="submit"
            disabled={!name.trim() || !species.trim() || invalidBirth}
          >
            {state.busy ? "Se salvează…" : "Salvează"}
          </button>
        </fieldset>
      </form>
      {source?.can_manage && (
        <div className="pet-form-footer">
          {!confirm ? (
            <button
              className="button secondary full"
              disabled={state.busy || state.stale}
              onClick={() => setConfirm(true)}
            >
              {source.archived_at ? (
                <RotateCcw size={17} />
              ) : (
                <Archive size={17} />
              )}
              {source.archived_at
                ? "Restaurează animalul"
                : "Arhivează animalul"}
            </button>
          ) : (
            <>
              <p className="pet-access-note">
                {source.archived_at
                  ? "Restaurezi animalul în lista activă?"
                  : "Animalul și istoricul rămân în Arhivate. Scadențele și reminderele se opresc."}
              </p>
              <div className="pet-form-actions">
                <button
                  className="button secondary"
                  disabled={state.busy}
                  onClick={() => setConfirm(false)}
                >
                  Anulează
                </button>
                <button
                  className="button primary"
                  disabled={state.busy || state.stale}
                  onClick={() =>
                    void state.run(async () =>
                      onSaved(
                        await api<Pet>(`${base}/${source.id}/archive`, {
                          method: "POST",
                          headers: { "If-Match": `"${source.version}"` },
                          body: JSON.stringify({
                            archived: !source.archived_at,
                          }),
                        }),
                      ),
                    )
                  }
                >
                  {source.archived_at ? "Restaurează" : "Arhivează"}
                </button>
              </div>
            </>
          )}
        </div>
      )}
      <MutationFeedback state={state} onReload={() => void reload()} />
    </Modal>
  );
}

export function PetPhotoForm({
  pet,
  base,
  onClose,
  onSaved,
  onUnavailable,
}: {
  pet: Pet;
  base: string;
  onClose: () => void;
  onSaved: (pet: Pet) => void;
  onUnavailable: () => void;
}) {
  const [source, setSource] = useState(pet);
  const [file, setFile] = useState<File | null>(null);
  const state = useMutation(onUnavailable);
  async function submit(action: "replace" | "remove" | "retry") {
    if (state.stale) return;
    await state.run(async () => {
      const body = new FormData();
      body.set("action", action);
      if (file) body.set("photo", file);
      onSaved(
        await api<Pet>(`${base}/photo${action === "retry" ? "/retry" : ""}`, {
          method: "POST",
          headers: { "If-Match": `"${source.version}"` },
          body: action === "retry" ? undefined : body,
        }),
      );
    });
  }
  return (
    <Modal
      title="Fotografia animalului"
      onClose={() => {
        if (!state.busy) onClose();
      }}
    >
      <fieldset
        className="pet-fields"
        disabled={
          state.busy ||
          state.stale ||
          !source.can_manage ||
          !!source.archived_at
        }
      >
        <label className="field">
          Fotografie
          <input
            type="file"
            accept="image/jpeg,image/png,image/webp"
            onChange={(event) => setFile(event.target.files?.[0] ?? null)}
          />
          <small>JPG, PNG sau WebP, maximum 10 MB și 16 megapixeli.</small>
        </label>
        <button
          className="button primary full"
          disabled={!file}
          onClick={() => void submit("replace")}
        >
          {state.busy ? "Se salvează…" : "Salvează fotografia"}
        </button>
        {source.photo_status === "quarantine" && (
          <button
            className="button secondary full pet-photo-action"
            onClick={() => void submit("retry")}
          >
            Reia verificarea fotografiei
          </button>
        )}
        {source.photo_status && (
          <button
            className="button secondary full pet-photo-action"
            onClick={() => void submit("remove")}
          >
            Elimină fotografia
          </button>
        )}
      </fieldset>
      <MutationFeedback
        state={state}
        onReload={() =>
          void state.run(async () => {
            setSource(await api<Pet>(base));
            state.setStale(false);
          })
        }
      />
    </Modal>
  );
}

export function PetEntryForm({
  entry,
  base,
  familyId,
  userId,
  canManage,
  onClose,
  onSaved,
  onUnavailable,
}: {
  entry: PetEntry | null;
  base: string;
  familyId: string;
  userId: number;
  canManage: boolean;
  onClose: () => void;
  onSaved: (entry: PetEntry) => void;
  onUnavailable: () => void;
}) {
  const [source, setSource] = useState(entry);
  const [kind, setKind] = useState<CareKind>(entry?.kind ?? "care");
  const [title, setTitle] = useState(entry?.title ?? "");
  const [date, setDate] = useState(entry?.event_date ?? bucharestToday());
  const [weight, setWeight] = useState(entry?.weight_kg ?? "");
  const [cost, setCost] = useState(entry?.cost ?? "");
  const [notes, setNotes] = useState(entry?.notes ?? "");
  const [nextDue, setNextDue] = useState(entry?.next_due_on ?? "");
  const [offset, setOffset] = useState<number | null>(
    entry?.reminder_offset_minutes ?? null,
  );
  const [recipients, setRecipients] = useState<string[]>(
    entry?.reminder_recipients ?? [],
  );
  const [confirmArchive, setConfirmArchive] = useState(false);
  const titleInput = useRef<HTMLInputElement>(null);
  const state = useMutation(onUnavailable);
  const invalidDate = date > bucharestToday();
  const invalidDue = !!nextDue && nextDue < date;
  const deadlineCompleted =
    !!source?.next_due_completed_at && nextDue === source?.next_due_on;
  const editable = canManage && !source?.archived_at;
  useEffect(() => titleInput.current?.focus(), []);
  async function reload() {
    if (!source) return;
    await state.run(async () => {
      const fresh = await api<PetEntry>(`${base}/entries/${source.id}`);
      setSource(fresh);
      setKind(fresh.kind);
      setTitle(fresh.title);
      setDate(fresh.event_date);
      setWeight(fresh.weight_kg ?? "");
      setCost(fresh.cost ?? "");
      setNotes(fresh.notes ?? "");
      setNextDue(fresh.next_due_on ?? "");
      setOffset(fresh.reminder_offset_minutes);
      setRecipients(fresh.reminder_recipients);
      setConfirmArchive(false);
      state.setStale(false);
    });
  }
  async function submit(event: FormEvent) {
    event.preventDefault();
    if (state.stale || invalidDate || invalidDue || !editable) return;
    await state.run(async () =>
      onSaved(
        await api<PetEntry>(`${base}/entries${source ? `/${source.id}` : ""}`, {
          method: source ? "PATCH" : "POST",
          headers: source ? { "If-Match": `"${source.version}"` } : undefined,
          body: JSON.stringify(
            petEntryPayload({
              kind,
              title,
              date,
              weight,
              cost,
              notes,
              nextDue,
              offset,
              recipients,
            }),
          ),
        }),
      ),
    );
  }
  return (
    <Modal
      title={
        !source
          ? "Adaugă înregistrare"
          : editable
            ? "Editează înregistrarea"
            : source.title
      }
      onClose={() => {
        if (!state.busy) onClose();
      }}
    >
      {editable ? (
        <form onSubmit={submit}>
          <fieldset className="pet-fields" disabled={state.busy || state.stale}>
            <label className="field">
              Tip
              <select
                name="kind"
                value={kind}
                onChange={(event) => setKind(event.target.value as CareKind)}
              >
                {Object.entries(careKinds).map(([value, item]) => (
                  <option key={value} value={value}>
                    {item.label}
                  </option>
                ))}
              </select>
            </label>
            <label className="field">
              Titlu
              <input
                ref={titleInput}
                name="title"
                required
                maxLength={160}
                value={title}
                onChange={(event) => setTitle(event.target.value)}
              />
            </label>
            <DatePicker
              label="Data înregistrării"
              name="event_date"
              dialogLabel="Alege data înregistrării"
              value={date}
              onChange={setDate}
            />
            {invalidDate && (
              <p className="error" role="alert">
                Data înregistrării nu poate fi în viitor.
              </p>
            )}
            {kind === "weight" && (
              <label className="field">
                Greutate (kg)
                <input
                  name="weight_kg"
                  inputMode="decimal"
                  required
                  pattern="[0-9]+([.,][0-9]{1,3})?"
                  value={weight}
                  onChange={(event) => setWeight(event.target.value)}
                />
                <small>Maximum trei zecimale.</small>
              </label>
            )}
            <label className="field">
              Cost (lei, opțional)
              <input
                name="cost"
                inputMode="decimal"
                pattern="[0-9]+([.,][0-9]{1,2})?"
                value={cost}
                onChange={(event) => setCost(event.target.value)}
              />
            </label>
            <label className="field">
              Notițe (opțional)
              <textarea
                name="notes"
                rows={3}
                maxLength={4000}
                value={notes}
                onChange={(event) => setNotes(event.target.value)}
              />
            </label>
            <DatePicker
              label="Următoarea scadență (opțional)"
              name="next_due_on"
              dialogLabel="Alege următoarea scadență"
              optional
              value={nextDue}
              onChange={setNextDue}
            />
            {invalidDue && (
              <p className="error" role="alert">
                Scadența nu poate fi înaintea datei înregistrării.
              </p>
            )}
            {deadlineCompleted && (
              <p className="pet-access-note">
                Scadență încheiată. O dată nouă redeschide scadența.
              </p>
            )}
            {nextDue && !deadlineCompleted && (
              <ReminderFields
                familyId={familyId}
                userId={userId}
                allDay
                visibility="family"
                offset={offset}
                recipients={recipients}
                onOffset={setOffset}
                onRecipients={setRecipients}
                advanceDays={[1]}
              />
            )}
            <button
              type="submit"
              className="button primary full"
              disabled={
                !title.trim() ||
                !date ||
                invalidDate ||
                invalidDue ||
                (kind === "weight" && !weight) ||
                (nextDue !== "" &&
                  !deadlineCompleted &&
                  offset !== null &&
                  !recipients.length)
              }
            >
              {state.busy ? "Se salvează…" : "Salvează"}
            </button>
          </fieldset>
        </form>
      ) : (
        source && (
          <dl className="pet-detail">
            <div>
              <dt>Tip</dt>
              <dd>{careKinds[source.kind].label}</dd>
            </div>
            <div>
              <dt>Data înregistrării</dt>
              <dd>{dateLabel(source.event_date)}</dd>
            </div>
            {source.weight_kg !== null && (
              <div>
                <dt>Greutate</dt>
                <dd>
                  {new Intl.NumberFormat("ro-RO", {
                    maximumFractionDigits: 3,
                  }).format(Number(source.weight_kg))}{" "}
                  kg
                </dd>
              </div>
            )}
            {source.cost !== null && (
              <div>
                <dt>Cost</dt>
                <dd>{money(source.cost)}</dd>
              </div>
            )}
            {source.notes && (
              <div>
                <dt>Notițe</dt>
                <dd className="pet-notes">{source.notes}</dd>
              </div>
            )}
            {source.next_due_on && (
              <div>
                <dt>Următoarea scadență</dt>
                <dd>
                  {dateLabel(source.next_due_on)}
                  {source.next_due_completed_at ? " · Încheiată" : ""}
                </dd>
              </div>
            )}
            {source.archived_at && (
              <div>
                <dt>Stare</dt>
                <dd>Arhivată</dd>
              </div>
            )}
          </dl>
        )
      )}
      {source && canManage && !source.archived_at && source.next_due_on && (
        <button
          className="button secondary full pet-photo-action"
          disabled={state.busy || state.stale}
          onClick={() =>
            void state.run(async () =>
              onSaved(
                await api<PetEntry>(`${base}/entries/${source.id}/completion`, {
                  method: "PATCH",
                  headers: { "If-Match": `"${source.version}"` },
                  body: JSON.stringify({
                    completed: !source.next_due_completed_at,
                  }),
                }),
              ),
            )
          }
        >
          {source.next_due_completed_at
            ? "Redeschide scadența"
            : "Încheie scadența"}
        </button>
      )}
      {source && canManage && (
        <div className="pet-form-footer">
          {!confirmArchive ? (
            <button
              className="button secondary full"
              disabled={state.busy || state.stale}
              onClick={() => setConfirmArchive(true)}
            >
              {source.archived_at
                ? "Restaurează înregistrarea"
                : "Arhivează înregistrarea"}
            </button>
          ) : (
            <>
              <p className="pet-access-note">
                {source.archived_at
                  ? "Restaurezi înregistrarea?"
                  : "Înregistrarea rămâne în Arhivate. Scadența și reminderul se opresc."}
              </p>
              <div className="pet-form-actions">
                <button
                  className="button secondary"
                  disabled={state.busy}
                  onClick={() => setConfirmArchive(false)}
                >
                  Anulează
                </button>
                <button
                  className="button primary"
                  disabled={state.busy || state.stale}
                  onClick={() =>
                    void state.run(async () =>
                      onSaved(
                        await api<PetEntry>(
                          `${base}/entries/${source.id}/archive`,
                          {
                            method: "POST",
                            headers: { "If-Match": `"${source.version}"` },
                            body: JSON.stringify({
                              archived: !source.archived_at,
                            }),
                          },
                        ),
                      ),
                    )
                  }
                >
                  {source.archived_at ? "Restaurează" : "Arhivează"}
                </button>
              </div>
            </>
          )}
        </div>
      )}
      <MutationFeedback state={state} onReload={() => void reload()} />
    </Modal>
  );
}
