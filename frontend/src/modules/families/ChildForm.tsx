import { useState, type FormEvent } from "react";
import { UserRoundPlus } from "lucide-react";
import { api, ApiError, type Member } from "../../shared/api";
import { bucharestToday } from "../../shared/calendarDates";
import { ParentPicker, eligibleParents } from "./ParentPicker";

export function ChildForm({
  familyId,
  members,
  actorId,
  busy,
  onBusy,
  onCreated,
  onExpired,
}: {
  familyId: string;
  members: Member[];
  actorId: number;
  busy: boolean;
  onBusy: (value: boolean) => void;
  onCreated: (member: Member) => void;
  onExpired: () => void;
}) {
  const [name, setName] = useState("");
  const [birth, setBirth] = useState("");
  const [parents, setParents] = useState(() =>
    eligibleParents(members)
      .filter((member) => member.user_id === actorId)
      .map((member) => member.id),
  );
  const [error, setError] = useState("");
  async function submit(event: FormEvent) {
    event.preventDefault();
    if (busy) return;
    onBusy(true);
    setError("");
    try {
      onCreated(
        await api<Member>(`/families/${familyId}/members/children`, {
          method: "POST",
          body: JSON.stringify({
            name,
            birth_date: birth,
            parent_ids: parents,
          }),
        }),
      );
    } catch (error) {
      setError(
        error instanceof Error
          ? error.message
          : "Profilul nu a putut fi creat.",
      );
      if (
        error instanceof ApiError &&
        [401, 403, 404, 419].includes(error.status)
      )
        onExpired();
    } finally {
      onBusy(false);
    }
  }
  return (
    <form onSubmit={submit}>
      <fieldset className="profile-form-fields" disabled={busy}>
        <label className="field">
          Numele copilului
          <input
            required
            maxLength={100}
            value={name}
            autoComplete="off"
            onChange={(event) => setName(event.target.value)}
          />
        </label>
        <label className="field">
          Data nașterii
          <input
            type="date"
            required
            min="1900-01-01"
            max={bucharestToday()}
            value={birth}
            onChange={(event) => setBirth(event.target.value)}
          />
        </label>
        <ParentPicker
          members={members}
          selected={parents}
          onChange={setParents}
        >
          <p className="fine-print">
            Fără cont sau email. Datele sunt adăugate numai de părinții
            desemnați, maximum doi.
          </p>
        </ParentPicker>
        <button
          className="button primary full"
          disabled={!name.trim() || !birth || !parents.length}
        >
          <UserRoundPlus size={18} />
          {busy ? "Se adaugă…" : "Adaugă copil"}
        </button>
      </fieldset>
      {error && (
        <p className="error" role="alert">
          {error}
        </p>
      )}
    </form>
  );
}
