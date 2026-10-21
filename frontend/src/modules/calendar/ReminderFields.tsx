import { useEffect, useState, type Dispatch, type SetStateAction } from "react";
import { api, ApiError, type Member } from "../../shared/api";

export function ReminderFields({
  familyId,
  userId,
  allDay,
  visibility,
  offset,
  recipients,
  onOffset,
  onRecipients,
}: {
  familyId: string;
  userId: number;
  allDay: boolean;
  visibility: "private" | "family";
  offset: number | null;
  recipients: string[];
  onOffset: (offset: number | null) => void;
  onRecipients: Dispatch<SetStateAction<string[]>>;
}) {
  const [members, setMembers] = useState<Member[] | null>(null);
  const [error, setError] = useState("");
  const [attempt, setAttempt] = useState(0);
  useEffect(() => {
    if (offset === null || members) return;
    let active = true;
    setError("");
    api<Member[]>(`/families/${familyId}/members`)
      .then((data) => {
        if (!active) return;
        setMembers(data);
      })
      .catch((e) => {
        if (active)
          setError(
            e instanceof ApiError
              ? e.message
              : "Nu am putut încărca membrii. Reîncearcă.",
          );
      });
    return () => {
      active = false;
    };
  }, [familyId, offset !== null, attempt]);
  useEffect(() => {
    if (!members || offset === null) return;
    const allowed = members.filter(
      (m) => visibility === "family" || m.user_id === userId,
    );
    onRecipients((current) =>
      current.length
        ? current.filter((id) => allowed.some((m) => m.id === id))
        : allowed.filter((m) => m.user_id === userId).map((m) => m.id),
    );
  }, [members, visibility, offset !== null, userId]);
  const available = members?.filter(
    (m) => visibility === "family" || m.user_id === userId,
  );
  return (
    <div className="calendar-reminder-fields">
      <label className="field">
        Reminder în aplicație
        <select
          value={offset === null ? "off" : offset}
          onChange={(e) => {
            const value =
              e.target.value === "off" ? null : Number(e.target.value);
            onOffset(value);
            if (value === null) onRecipients([]);
            else if (!recipients.length && members)
              onRecipients(
                members.filter((m) => m.user_id === userId).map((m) => m.id),
              );
          }}
        >
          <option value="off">Fără reminder</option>
          <option value="0">
            {allDay ? "În aceeași zi, la 09:00" : "La ora evenimentului"}
          </option>
          {!allDay && (
            <>
              <option value="15">Cu 15 minute înainte</option>
              <option value="60">Cu o oră înainte</option>
            </>
          )}
          <option value="1440">
            {allDay ? "În ziua precedentă, la 09:00" : "Cu 24 de ore înainte"}
          </option>
        </select>
        {offset !== null && (
          <small>
            {allDay ? "Ora României. " : ""}Dacă momentul ales a trecut la
            salvare, reminderul nu se trimite.
          </small>
        )}
      </label>
      {offset !== null && (
        <fieldset className="calendar-recipients">
          <legend>Destinatari</legend>
          {!members && !error && <p role="status">Se încarcă membrii…</p>}
          {available?.map((member) => (
            <label className="calendar-check" key={member.id}>
              <input
                type="checkbox"
                checked={recipients.includes(member.id)}
                onChange={(e) =>
                  onRecipients(
                    e.target.checked
                      ? [...recipients, member.id]
                      : recipients.filter((id) => id !== member.id),
                  )
                }
              />
              {member.name}
              {member.user_id === userId ? " (eu)" : ""}
            </label>
          ))}
          {members && !recipients.length && (
            <p className="error">Alege cel puțin un destinatar.</p>
          )}
          {error && (
            <>
              <p className="error" role="alert">
                {error}
              </p>
              <button
                className="text-button"
                type="button"
                onClick={() => setAttempt((n) => n + 1)}
              >
                Reîncearcă
              </button>
            </>
          )}
        </fieldset>
      )}
    </div>
  );
}
