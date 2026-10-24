export type CalendarSource = {
  type:
    | "vehicle_entry"
    | "birthday"
    | "task"
    | "pet_entry"
    | "document_expiry"
    | "memory_anniversary"
    | "document_date"
    | "member_birth"
    | "profile_entry"
    | "expense"
    | "meal"
    | "memory_date"
    | "task_date"
    | "vehicle_date"
    | "pet_birth"
    | "pet_record"
    | "pet_due";
  id: string;
  parent_id: string;
  virtual?: boolean;
  kind?: string;
};

export const calendarSourceLabels: Record<CalendarSource["type"], string> = {
  vehicle_entry: "Auto",
  birthday: "Zi de naștere",
  task: "Sarcină",
  pet_entry: "Animale",
  document_expiry: "Document",
  memory_anniversary: "Amintire",
  document_date: "Document",
  member_birth: "Data nașterii",
  profile_entry: "Însemnare",
  expense: "Cheltuială",
  meal: "Masă",
  memory_date: "Amintire",
  task_date: "Sarcină",
  vehicle_date: "Auto",
  pet_birth: "Data nașterii animalului",
  pet_record: "Îngrijire animal",
  pet_due: "Scadență animal",
};

const profileLabels: Record<string, string> = {
  visit: "Vizită medicală",
  medication: "Medicament",
  book: "Carte citită",
  culture: "Activitate culturală",
  activity: "Activitate",
  achievement: "Realizare",
};
export const calendarSourceActions: Partial<
  Record<CalendarSource["type"], string>
> = {
  document_date: "Deschide documentul",
  member_birth: "Deschide profilul",
  profile_entry: "Deschide însemnarea",
  expense: "Deschide cheltuiala",
  meal: "Deschide masa",
  memory_date: "Deschide amintirea",
  task_date: "Deschide sarcina",
  vehicle_date: "Deschide în Auto",
  pet_birth: "Deschide animalul",
  pet_record: "Deschide în Animale",
  pet_due: "Deschide în Animale",
};

export function calendarSourceLabel(source: CalendarSource): string {
  if (source.type === "profile_entry")
    return (
      profileLabels[source.kind ?? ""] ?? calendarSourceLabels.profile_entry
    );
  return calendarSourceLabels[source.type];
}

export type CalendarVisibility = "private" | "family" | "selected" | "adults";

export type CalendarDestination =
  | {
      type: "document" | "expense" | "meal" | "memory" | "task" | "petProfile";
      id: string;
    }
  | { type: "profile"; memberId: string; entryId?: string; kind?: string }
  | { type: "vehicle"; vehicleId: string; entryId: string }
  | { type: "pet"; petId: string; entryId: string };

export function calendarSourceDestination(
  source: CalendarSource,
): CalendarDestination {
  switch (source.type) {
    case "document_date":
    case "document_expiry":
      return { type: "document", id: source.parent_id };
    case "member_birth":
    case "birthday":
      return { type: "profile", memberId: source.parent_id };
    case "profile_entry":
      return {
        type: "profile",
        memberId: source.parent_id,
        entryId: source.id,
        kind: source.kind,
      };
    case "expense":
    case "meal":
      return { type: source.type, id: source.id };
    case "memory_date":
    case "memory_anniversary":
      return { type: "memory", id: source.parent_id };
    case "task_date":
    case "task":
      return { type: "task", id: source.id };
    case "vehicle_date":
    case "vehicle_entry":
      return {
        type: "vehicle",
        vehicleId: source.parent_id,
        entryId: source.id,
      };
    case "pet_birth":
      return { type: "petProfile", id: source.parent_id };
    case "pet_record":
    case "pet_due":
    case "pet_entry":
      return { type: "pet", petId: source.parent_id, entryId: source.id };
  }
}

export function calendarMatchesAccess(
  visibility: CalendarVisibility,
  access: string,
): boolean {
  return (
    !access ||
    (access === "private" ? visibility === "private" : visibility !== "private")
  );
}

export type ReminderDelivery = {
  membership_id: string;
  state: "pending" | "delivered" | "cancelled" | "expired";
  in_app_enabled: boolean | null;
  email_state:
    | "pending"
    | "sending"
    | "sent"
    | "uncertain"
    | "cancelled"
    | "expired"
    | null;
};

export type CalendarItem = {
  id: string;
  title: string;
  notes: string | null;
  event_date: string;
  all_day: boolean;
  time: string | null;
  starts_at: string | null;
  timezone: string;
  visibility: CalendarVisibility;
  owner_name: string;
  can_edit: boolean;
  version: number;
  archived_at: string | null;
  source: CalendarSource | null;
  my_reminder_offset_minutes: number | null;
  my_reminder_version: number;
  reminder: {
    offset_minutes: number;
    recipients: string[];
    due_at: string;
    deliveries: ReminderDelivery[];
  } | null;
};
