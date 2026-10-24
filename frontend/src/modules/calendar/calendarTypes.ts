export type CalendarItem = {
  id: string;
  title: string;
  notes: string | null;
  event_date: string;
  all_day: boolean;
  time: string | null;
  starts_at: string | null;
  timezone: string;
  visibility: "private" | "family";
  owner_name: string;
  can_edit: boolean;
  version: number;
  archived_at: string | null;
  source: {
    type: "vehicle_entry" | "birthday" | "task" | "pet_entry";
    id: string;
    parent_id: string;
  } | null;
  my_reminder_offset_minutes: number | null;
  my_reminder_version: number;
  reminder: {
    offset_minutes: number;
    recipients: string[];
    due_at: string;
    deliveries: {
      membership_id: string;
      state: "pending" | "delivered" | "cancelled" | "expired";
    }[];
  } | null;
};
