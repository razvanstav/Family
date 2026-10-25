import { SelectField } from "../../shared/SelectField";
import { useEffect, useState, type FormEvent } from "react";
import { api, ApiError } from "../../shared/api";
import { Modal } from "../../shared/Modal";
import { DatePicker } from "../../shared/DatePicker";
import { bucharestToday } from "../../shared/calendarDates";
import { ReminderFields } from "../../shared/ReminderFields";
import {
  entryKinds,
  type EntryKind,
  type Vehicle,
  type VehicleEntry,
} from "./vehicleData";

function useSave() {
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState("");
  const [stale, setStale] = useState(false);
  async function run(action: () => Promise<void>) {
    setBusy(true);
    setError("");
    try {
      await action();
    } catch (e) {
      setError(
        e instanceof Error ? e.message : "Nu am putut salva. Reîncearcă.",
      );
      if (e instanceof ApiError && e.status === 412) setStale(true);
    } finally {
      setBusy(false);
    }
  }
  return { busy, error, stale, run };
}
function FormError({
  error,
  stale,
  reload,
}: {
  error: string;
  stale: boolean;
  reload: () => void;
}) {
  return (
    <>
      {error && (
        <p className="error" role="alert">
          {error}
        </p>
      )}
      {stale && (
        <button
          type="button"
          className="button secondary full"
          onClick={reload}
        >
          Renunță la modificări și reîncarcă
        </button>
      )}
    </>
  );
}

export function VehicleForm({
  vehicle,
  base,
  onClose,
  onSaved,
}: {
  vehicle: Vehicle | null;
  base: string;
  onClose: () => void;
  onSaved: (v: Vehicle) => void;
}) {
  const [current, setCurrent] = useState(vehicle);
  const [name, setName] = useState(vehicle?.name ?? "");
  const [make, setMake] = useState(vehicle?.make ?? "");
  const [model, setModel] = useState(vehicle?.model ?? "");
  const [registration, setRegistration] = useState(vehicle?.registration ?? "");
  const [year, setYear] = useState(String(vehicle?.year ?? ""));
  const [confirm, setConfirm] = useState(false);
  const save = useSave();
  async function submit(event: FormEvent) {
    event.preventDefault();
    await save.run(async () =>
      onSaved(
        await api<Vehicle>(`${base}${current ? `/${current.id}` : ""}`, {
          method: current ? "PATCH" : "POST",
          headers: current ? { "If-Match": `"${current.version}"` } : {},
          body: JSON.stringify({
            name,
            make,
            model,
            registration: registration || null,
            year: year ? Number(year) : null,
          }),
        }),
      ),
    );
  }
  return (
    <Modal
      title={current ? "Editează mașina" : "Adaugă mașină"}
      onClose={onClose}
    >
      <form onSubmit={submit}>
        <fieldset
          className="vehicle-fields"
          disabled={save.busy || save.stale || !!current?.archived_at}
        >
          <label className="field">
            Nume
            <input
              required
              maxLength={100}
              value={name}
              onChange={(e) => setName(e.target.value)}
            />
          </label>
          <div className="vehicle-field-pair">
            <label className="field">
              Marcă
              <input
                required
                maxLength={60}
                value={make}
                onChange={(e) => setMake(e.target.value)}
              />
            </label>
            <label className="field">
              Model
              <input
                required
                maxLength={60}
                value={model}
                onChange={(e) => setModel(e.target.value)}
              />
            </label>
          </div>
          <div className="vehicle-field-pair">
            <label className="field">
              Număr de înmatriculare
              <input
                maxLength={24}
                value={registration}
                onChange={(e) =>
                  setRegistration(e.target.value.toLocaleUpperCase("ro-RO"))
                }
              />
            </label>
            <label className="field">
              An fabricație
              <input
                type="number"
                min={1900}
                max={new Date().getFullYear() + 1}
                value={year}
                onChange={(e) => setYear(e.target.value)}
              />
            </label>
          </div>
          <p className="vehicle-access-note">
            Mașina și înregistrările sunt vizibile familiei. Membrii cu acces
            limitat pot doar consulta.
          </p>
          <button className="button primary full" type="submit">
            {save.busy ? "Se salvează…" : "Salvează"}
          </button>
        </fieldset>
      </form>
      {current && !save.stale && (
        <div className="vehicle-form-footer">
          {!confirm ? (
            <button
              className="text-button"
              disabled={save.busy}
              onClick={() => setConfirm(true)}
            >
              {current.archived_at ? "Restaurează mașina" : "Arhivează mașina"}
            </button>
          ) : (
            <>
              <p>
                {current.archived_at
                  ? "Restaurezi mașina în lista activă?"
                  : "Mașina și istoricul rămân în Arhivate. Poți restaura mașina oricând."}
              </p>
              <button
                className="button secondary full"
                disabled={save.busy}
                onClick={() =>
                  void save.run(async () =>
                    onSaved(
                      await api<Vehicle>(`${base}/${current.id}/archive`, {
                        method: "POST",
                        headers: { "If-Match": `"${current.version}"` },
                        body: JSON.stringify({
                          archived: !current.archived_at,
                        }),
                      }),
                    ),
                  )
                }
              >
                {current.archived_at ? "Restaurează" : "Arhivează"}
              </button>
            </>
          )}
        </div>
      )}
      <FormError
        error={save.error}
        stale={save.stale}
        reload={() =>
          void save.run(async () => {
            const fresh = await api<Vehicle>(`${base}/${current!.id}`);
            setCurrent(fresh);
            setName(fresh.name);
            setMake(fresh.make);
            setModel(fresh.model);
            setRegistration(fresh.registration ?? "");
            setYear(String(fresh.year ?? ""));
            onSaved(fresh);
          })
        }
      />
    </Modal>
  );
}

export function VehiclePhotoForm({
  vehicle,
  base,
  onClose,
  onSaved,
}: {
  vehicle: Vehicle;
  base: string;
  onClose: () => void;
  onSaved: (v: Vehicle) => void;
}) {
  const [file, setFile] = useState<File | null>(null);
  const save = useSave();
  async function submit(action: "replace" | "remove" | "scan") {
    await save.run(async () => {
      const data = new FormData();
      data.set("action", action);
      if (file) data.set("photo", file);
      onSaved(
        await api<Vehicle>(
          `${base}/${vehicle.id}/photo${action === "scan" ? "/scan" : ""}`,
          {
            method: "POST",
            headers: { "If-Match": `"${vehicle.version}"` },
            body: action === "scan" ? undefined : data,
          },
        ),
      );
    });
  }
  return (
    <Modal title="Fotografia mașinii" onClose={onClose}>
      <fieldset className="vehicle-fields" disabled={save.busy || save.stale}>
        <label className="field">
          Fotografie
          <input
            type="file"
            accept="image/jpeg,image/png,image/webp"
            onChange={(e) => setFile(e.target.files?.[0] ?? null)}
          />
          <small>JPG, PNG sau WebP, maximum 10 MB și 16 megapixeli.</small>
        </label>
        <button
          className="button primary full"
          disabled={!file}
          onClick={() => void submit("replace")}
        >
          Salvează fotografia
        </button>
        {vehicle.photo_status === "quarantine" && (
          <button
            className="button secondary full"
            onClick={() => void submit("scan")}
          >
            Reia verificarea fotografiei
          </button>
        )}
        {vehicle.photo_status && (
          <button className="text-button" onClick={() => void submit("remove")}>
            Elimină fotografia
          </button>
        )}
      </fieldset>
      <FormError
        error={save.error}
        stale={save.stale}
        reload={() =>
          void save.run(async () =>
            onSaved(await api<Vehicle>(`${base}/${vehicle.id}`)),
          )
        }
      />
    </Modal>
  );
}

export function VehicleEntryForm({
  entry,
  base,
  familyId,
  userId,
  canManage,
  defaultState,
  onClose,
  onSaved,
}: {
  entry: VehicleEntry | null;
  base: string;
  familyId: string;
  userId: number;
  canManage: boolean;
  defaultState: "planned" | "completed";
  onClose: () => void;
  onSaved: () => void;
}) {
  const [kind, setKind] = useState<EntryKind>(entry?.kind ?? "service");
  const [title, setTitle] = useState(entry?.title ?? "");
  const [state, setState] = useState(entry?.state ?? defaultState);
  const [date, setDate] = useState(entry?.event_date ?? bucharestToday());
  const [cost, setCost] = useState(entry?.cost ?? "");
  const [km, setKm] = useState(String(entry?.odometer_km ?? ""));
  const [notes, setNotes] = useState(entry?.notes ?? "");
  const [reminderOffset, setReminderOffset] = useState<number | null>(
    entry?.reminder_offset_minutes ?? null,
  );
  const [recipients, setRecipients] = useState<string[]>(
    entry?.reminder_recipients ?? [],
  );
  const [confirm, setConfirm] = useState(false);
  const save = useSave();
  async function mutate(archive = false) {
    await save.run(async () => {
      await api(
        `${base}/entries${entry ? `/${entry.id}` : ""}${archive ? "/archive" : ""}`,
        {
          method: !entry || archive ? "POST" : "PATCH",
          headers: entry ? { "If-Match": `"${entry.version}"` } : {},
          body: JSON.stringify(
            archive
              ? { archived: !entry?.archived_at }
              : {
                  kind,
                  title,
                  state,
                  event_date: date,
                  cost:
                    state === "completed" && cost !== ""
                      ? cost.replace(",", ".")
                      : null,
                  odometer_km:
                    state === "completed" && km !== "" ? Number(km) : null,
                  notes: notes || null,
                  reminder_offset_minutes:
                    state === "planned" ? reminderOffset : null,
                  reminder_recipients:
                    state === "planned" && reminderOffset !== null
                      ? recipients
                      : [],
                },
          ),
        },
      );
      onSaved();
    });
  }
  return (
    <Modal
      title={entry ? entry.title : "Adaugă cost sau revizie"}
      onClose={onClose}
    >
      <form
        onSubmit={(e) => {
          e.preventDefault();
          void mutate();
        }}
      >
        <fieldset
          className="vehicle-fields"
          disabled={
            save.busy || save.stale || !canManage || !!entry?.archived_at
          }
        >
          <label className="field">
            Tip
            <SelectField
              value={kind}
              onChange={(e) => {
                const k = e.target.value as EntryKind;
                setKind(k);
                if (k === "fuel" || k === "other") setState("completed");
                if (!title || title === entryKinds[kind].label)
                  setTitle(entryKinds[k].label);
              }}
            >
              {Object.entries(entryKinds).map(([value, item]) => (
                <option key={value} value={value}>
                  {item.label}
                </option>
              ))}
            </SelectField>
          </label>
          <label className="field">
            Titlu
            <input
              required
              maxLength={160}
              value={title}
              onChange={(e) => setTitle(e.target.value)}
            />
          </label>
          <div className="vehicle-field-pair">
            <label className="field">
              Stare
              <SelectField
                value={state}
                onChange={(e) => setState(e.target.value as typeof state)}
              >
                <option
                  value="planned"
                  disabled={kind === "fuel" || kind === "other"}
                >
                  Planificat
                </option>
                <option value="completed">Efectuat</option>
              </SelectField>
            </label>
            <DatePicker
              label={state === "planned" ? "Scadență" : "Data efectuării"}
              required
              min="1900-01-01"
              max={state === "completed" ? bucharestToday() : "2100-12-31"}
              value={date}
              onChange={setDate}
            />
          </div>
          {state === "completed" && (
            <div className="vehicle-field-pair">
              <label className="field">
                Cost (RON)
                <input
                  type="number"
                  min="0"
                  max="9999999999.99"
                  step="0.01"
                  value={cost}
                  onChange={(e) => setCost(e.target.value)}
                />
              </label>
              <label className="field">
                Kilometraj (km)
                <input
                  type="number"
                  min="0"
                  max="9999999"
                  step="1"
                  value={km}
                  onChange={(e) => setKm(e.target.value)}
                />
              </label>
            </div>
          )}
          <label className="field">
            Notițe
            <textarea
              rows={3}
              maxLength={4000}
              value={notes}
              onChange={(e) => setNotes(e.target.value)}
            />
          </label>
          {state === "planned" && (
            <ReminderFields
              familyId={familyId}
              userId={userId}
              allDay
              visibility="family"
              offset={reminderOffset}
              recipients={recipients}
              onOffset={setReminderOffset}
              onRecipients={setRecipients}
              advanceDays={[1, 7, 30]}
            />
          )}
          {canManage && !entry?.archived_at && (
            <button
              type="submit"
              className="button primary full"
              disabled={
                state === "planned" &&
                reminderOffset !== null &&
                !recipients.length
              }
            >
              {save.busy ? "Se salvează…" : "Salvează"}
            </button>
          )}
        </fieldset>
      </form>
      {canManage && entry && !save.stale && (
        <div className="vehicle-form-footer">
          {!confirm ? (
            <button className="text-button" onClick={() => setConfirm(true)}>
              {entry.archived_at
                ? "Restaurează înregistrarea"
                : "Arhivează înregistrarea"}
            </button>
          ) : (
            <>
              <p>
                {entry.archived_at
                  ? "Restaurezi înregistrarea?"
                  : "Înregistrarea rămâne în Arhivate și poate fi restaurată."}
              </p>
              <button
                className="button secondary full"
                disabled={save.busy}
                onClick={() => void mutate(true)}
              >
                {entry.archived_at ? "Restaurează" : "Arhivează"}
              </button>
            </>
          )}
        </div>
      )}
      <FormError error={save.error} stale={save.stale} reload={onSaved} />
    </Modal>
  );
}
