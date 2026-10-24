import { HeartPulse, PawPrint, Scale, Syringe } from "lucide-react";
import type { PhotoStatus } from "../../shared/PrivatePhoto";

export type Pet = {
  id: string;
  name: string;
  species: string;
  breed: string | null;
  birth_date: string | null;
  photo_status: PhotoStatus;
  version: number;
  archived_at: string | null;
  can_manage: boolean;
};
export const careKinds = {
  care: { label: "Îngrijire", icon: PawPrint, tone: "green" },
  weight: { label: "Greutate", icon: Scale, tone: "ochre" },
  vaccination: { label: "Vaccinare", icon: Syringe, tone: "rose" },
  visit: { label: "Vizită", icon: HeartPulse, tone: "rose" },
} as const;
export type CareKind = keyof typeof careKinds;
export type PetEntry = {
  id: string;
  kind: CareKind;
  title: string;
  event_date: string;
  weight_kg: string | null;
  cost: string | null;
  notes: string | null;
  next_due_on: string | null;
  next_due_completed_at: string | null;
  version: number;
  archived_at: string | null;
  calendar_event_id: string | null;
  reminder_offset_minutes: number | null;
  reminder_recipients: string[];
};
export type PetEntryPage = {
  items: PetEntry[];
  page: number;
  has_more: boolean;
};
export type PetDocument = {
  id: string;
  title: string;
  visibility: string;
  mime: string | null;
  status: PhotoStatus;
};
