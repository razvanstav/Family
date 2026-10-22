import type { ReactNode } from "react";
import type { Member } from "../../shared/api";

export function eligibleParents(members: Member[], childId?: string) {
  return members.filter(
    (member) =>
      member.user_id !== null &&
      member.id !== childId &&
      member.role !== "limited" &&
      (member.age == null || member.age >= 18),
  );
}

export function ParentPicker({
  members,
  childId,
  selected,
  onChange,
  children,
}: {
  members: Member[];
  childId?: string;
  selected: string[];
  onChange: (value: string[]) => void;
  children?: ReactNode;
}) {
  return (
    <fieldset className="profile-parent-picker">
      <legend>Părinți desemnați</legend>
      {eligibleParents(members, childId).map((member) => (
        <label className="profile-checkbox" key={member.id}>
          <input
            type="checkbox"
            checked={selected.includes(member.id)}
            disabled={!selected.includes(member.id) && selected.length >= 2}
            onChange={(event) =>
              onChange(
                event.target.checked
                  ? [...selected, member.id]
                  : selected.filter((id) => id !== member.id),
              )
            }
          />
          {member.name}
        </label>
      ))}
      {children}
    </fieldset>
  );
}
