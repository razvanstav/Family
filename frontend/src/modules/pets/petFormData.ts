import type { CareKind } from "./petData";

export type PetEntryInput = {
  kind: CareKind;
  title: string;
  date: string;
  weight: string;
  cost: string;
  notes: string;
  nextDue: string;
  offset: number | null;
  recipients: string[];
};

export function petEntryPayload(input: PetEntryInput) {
  const decimal = (value: string) => value.trim().replace(",", ".") || null;
  return {
    kind: input.kind,
    title: input.title.trim(),
    event_date: input.date,
    weight_kg: input.kind === "weight" ? decimal(input.weight) : null,
    cost: decimal(input.cost),
    notes: input.notes.trim() || null,
    next_due_on: input.nextDue || null,
    reminder_offset_minutes: input.nextDue ? input.offset : null,
    reminder_recipients:
      input.nextDue && input.offset !== null ? input.recipients : [],
  };
}
