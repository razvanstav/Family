import { useEffect, useRef, useState } from "react";
import { Trash2 } from "lucide-react";
import { api, ApiError, errorMessage, type Family } from "../../shared/api";
import { DatePicker } from "../../shared/DatePicker";
import { dateLabel } from "../../shared/calendarDates";
import { Modal } from "../../shared/Modal";
import { setTaskCompletion } from "./taskApi";
import {
  recurrenceLabels,
  type Assignee,
  type Task,
  type TaskRecurrence,
  type TaskVisibility,
} from "./taskData";

export function TaskForm({
  family,
  task,
  assignees,
  onClose,
  onSaved,
  onUnavailable,
}: {
  family: Family;
  task: Task | null;
  assignees: Assignee[];
  onClose: () => void;
  onSaved: (task: Task | null, action?: "completion") => void;
  onUnavailable: () => void;
}) {
  const [source, setSource] = useState(task);
  const [title, setTitle] = useState(task?.title ?? "");
  const [assignee, setAssignee] = useState(
    task?.assignee_membership_id ?? family.membership_id,
  );
  const [due, setDue] = useState(task?.due_on ?? "");
  const [recurrence, setRecurrence] = useState<TaskRecurrence>(
    task?.recurrence ?? null,
  );
  const [visibility, setVisibility] = useState<TaskVisibility>(
    task?.visibility ?? "family",
  );
  const [busy, setBusy] = useState(false);
  const [stale, setStale] = useState(false);
  const [deleting, setDeleting] = useState(false);
  const [error, setError] = useState("");
  const pending = useRef(false);
  const titleInput = useRef<HTMLInputElement>(null);
  const endpoint = `/families/${family.id}/tasks`;
  useEffect(() => {
    titleInput.current?.focus();
  }, []);
  const options =
    source &&
    !assignees.some((person) => person.id === source.assignee_membership_id)
      ? [
          ...assignees,
          {
            id: source.assignee_membership_id,
            name: `${source.assignee_name} (fost membru)`,
          },
        ]
      : assignees;
  function handleError(error: unknown) {
    setError(errorMessage(error));
    if (error instanceof ApiError) {
      if (error.status === 412) setStale(true);
      if ([401, 403, 404, 419].includes(error.status)) onUnavailable();
    }
  }
  async function submit(remove = false) {
    if (pending.current || stale || (!remove && recurrence && !due)) return;
    pending.current = true;
    setBusy(true);
    setError("");
    try {
      const saved = await api<Task | undefined>(
        source ? `${endpoint}/${source.id}` : endpoint,
        {
          method: remove ? "DELETE" : source ? "PATCH" : "POST",
          headers: source ? { "If-Match": `"${source.version}"` } : undefined,
          body: remove
            ? undefined
            : JSON.stringify({
                title: title.trim(),
                assignee_membership_id: assignee,
                due_on: due || null,
                recurrence,
                visibility,
              }),
        },
      );
      onSaved(saved ?? null);
    } catch (error) {
      handleError(error);
    } finally {
      pending.current = false;
      setBusy(false);
    }
  }
  async function reload() {
    if (!source || pending.current) return;
    pending.current = true;
    setBusy(true);
    setError("");
    try {
      const fresh = await api<Task>(`${endpoint}/${source.id}`);
      setSource(fresh);
      setTitle(fresh.title);
      setAssignee(fresh.assignee_membership_id);
      setDue(fresh.due_on ?? "");
      setRecurrence(fresh.recurrence);
      setVisibility(fresh.visibility);
      setStale(false);
      setDeleting(false);
    } catch (error) {
      handleError(error);
    } finally {
      pending.current = false;
      setBusy(false);
    }
  }
  async function complete() {
    if (!source?.can_complete || pending.current || stale) return;
    pending.current = true;
    setBusy(true);
    setError("");
    try {
      const saved = await setTaskCompletion(family.id, source);
      onSaved(saved, "completion");
    } catch (error) {
      handleError(error);
    } finally {
      pending.current = false;
      setBusy(false);
    }
  }
  return (
    <Modal
      title={
        deleting
          ? "Șterge sarcina"
          : source
            ? source.can_edit
              ? "Editează sarcina"
              : source.title
            : "Adaugă sarcină"
      }
      onClose={() => {
        if (!pending.current) onClose();
      }}
    >
      {deleting ? (
        <>
          <p className="modal-lead">Ștergi sarcina „{source?.title}”?</p>
          <div className="task-form-actions">
            <button
              className="button secondary"
              disabled={busy}
              onClick={() => setDeleting(false)}
            >
              Înapoi
            </button>
            <button
              className="button danger"
              disabled={busy || stale}
              onClick={() => void submit(true)}
            >
              {busy ? "Se șterge…" : "Șterge"}
            </button>
          </div>
        </>
      ) : source && !source.can_edit ? (
        <dl className="task-detail">
          <div>
            <dt>Responsabil</dt>
            <dd>{source.assignee_name}</dd>
          </div>
          <div>
            <dt>Termen</dt>
            <dd>{source.due_on ? dateLabel(source.due_on) : "Fără termen"}</dd>
          </div>
          <div>
            <dt>Acces</dt>
            <dd>{source.visibility === "family" ? "Familie" : "Personal"}</dd>
          </div>
          {source.recurrence && (
            <div>
              <dt>Repetare</dt>
              <dd>{recurrenceLabels[source.recurrence]}</dd>
            </div>
          )}
          <div>
            <dt>Stare</dt>
            <dd>{source.completed_at ? "Finalizată" : "De făcut"}</dd>
          </div>
        </dl>
      ) : (
        <form
          onSubmit={(event) => {
            event.preventDefault();
            void submit();
          }}
        >
          <fieldset className="task-fields" disabled={busy || stale}>
            <label className="field">
              Sarcină
              <input
                ref={titleInput}
                name="title"
                value={title}
                onChange={(event) => setTitle(event.target.value)}
                required
                maxLength={160}
              />
            </label>
            <label className="field">
              Responsabil
              <select
                name="assignee_membership_id"
                value={assignee}
                disabled={visibility === "private"}
                onChange={(event) => setAssignee(event.target.value)}
                required
              >
                {options.map((person) => (
                  <option key={person.id} value={person.id}>
                    {person.name}
                  </option>
                ))}
              </select>
            </label>
            <DatePicker
              label={recurrence ? "Termen" : "Termen (opțional)"}
              name="due_on"
              dialogLabel="Alege termenul"
              optional={!recurrence}
              value={due}
              onChange={setDue}
            />
            <label className="field">
              Repetare
              <select
                name="recurrence"
                value={recurrence ?? ""}
                aria-describedby={
                  recurrence && !source?.completed_at
                    ? "task-recurrence-hint"
                    : undefined
                }
                onChange={(event) =>
                  setRecurrence((event.target.value || null) as TaskRecurrence)
                }
              >
                <option value="">Fără</option>
                {Object.entries(recurrenceLabels).map(([value, label]) => (
                  <option key={value} value={value}>
                    {label}
                  </option>
                ))}
              </select>
              {recurrence && !source?.completed_at && (
                <small id="task-recurrence-hint">
                  {due
                    ? "La finalizare se creează următoarea sarcină, la primul termen viitor."
                    : "Alege un termen pentru repetare."}
                </small>
              )}
            </label>
            <label className="field">
              Acces
              <select
                name="visibility"
                aria-label="Acces"
                value={visibility}
                aria-describedby="task-access-hint"
                onChange={(event) => {
                  const next = event.target.value as TaskVisibility;
                  setVisibility(next);
                  if (next === "private") setAssignee(family.membership_id);
                }}
              >
                <option value="family">Familie</option>
                <option value="private">Personal</option>
              </select>
              <small id="task-access-hint">
                {visibility === "family"
                  ? "Membrii activi o pot vedea. Tu și responsabilul o puteți finaliza; numai tu o poți modifica."
                  : "Vizibilă numai pentru tine. Tu ești responsabilul."}
              </small>
            </label>
          </fieldset>
          <div className="task-form-actions">
            {source && (
              <button
                type="button"
                className="icon-button"
                aria-label="Șterge sarcina"
                disabled={busy || stale}
                onClick={() => setDeleting(true)}
              >
                <Trash2 size={19} />
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
                busy || stale || !title.trim() || (!!recurrence && !due)
              }
            >
              {busy ? "Se salvează…" : "Salvează"}
            </button>
          </div>
        </form>
      )}
      {!deleting && source?.can_complete && (
        <>
          <button
            type="button"
            className="button secondary full task-completion"
            disabled={busy || stale}
            onClick={() => void complete()}
          >
            {source.completed_at ? "Redeschide sarcina" : "Finalizează sarcina"}
          </button>
          {source.completed_at && source.recurrence && (
            <p className="task-recurrence-note">
              Modificarea sau redeschiderea nu schimbă sarcinile deja create.
            </p>
          )}
        </>
      )}
      {error && (
        <p className="error" role="alert">
          {error}
        </p>
      )}
      {stale && (
        <button
          className="button secondary full"
          disabled={busy}
          onClick={() => void reload()}
        >
          {busy ? "Se încarcă…" : "Reîncarcă sarcina"}
        </button>
      )}
    </Modal>
  );
}
