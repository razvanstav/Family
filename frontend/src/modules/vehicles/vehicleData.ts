import {
  CalendarDays,
  ShieldCheck,
  Wrench,
  Fuel,
  Route,
  ReceiptText,
} from "lucide-react";
import type { PhotoStatus } from "../../shared/PrivatePhoto";
import { bucharestToday } from "../../shared/calendarDates";

export type Vehicle = {
  id: string;
  name: string;
  make: string;
  model: string;
  registration: string | null;
  year: number | null;
  version: number;
  photo_status: PhotoStatus;
  archived_at: string | null;
  can_manage: boolean;
};
export const entryKinds = {
  itp: { label: "ITP", icon: CalendarDays, color: "blue" },
  rca: { label: "Asigurare RCA", icon: ShieldCheck, color: "green" },
  rovinieta: { label: "Rovinietă", icon: Route, color: "ochre" },
  service: { label: "Revizie service", icon: Wrench, color: "blue" },
  fuel: { label: "Alimentare", icon: Fuel, color: "ochre" },
  other: { label: "Alt cost", icon: ReceiptText, color: "green" },
} as const;
export type EntryKind = keyof typeof entryKinds;
export type VehicleEntry = {
  id: string;
  kind: EntryKind;
  title: string;
  state: "planned" | "completed";
  event_date: string;
  cost: string | null;
  odometer_km: number | null;
  notes: string | null;
  version: number;
  archived_at: string | null;
  calendar_event_id: string | null;
  reminder_offset_minutes: number | null;
  reminder_recipients: string[];
};
export type EntryPage = {
  items: VehicleEntry[];
  page: number;
  has_more: boolean;
};
export type VehicleDocument = {
  id: string;
  title: string;
  visibility: string;
  mime: string;
  status: PhotoStatus;
};
export const money = (value: string) =>
  new Intl.NumberFormat("ro-RO", {
    style: "currency",
    currency: "RON",
    maximumFractionDigits: 2,
  }).format(Number(value));
export function dueLabel(date: string): { text: string; overdue: boolean } {
  const days = Math.round(
    (Date.parse(`${date}T12:00:00Z`) -
      Date.parse(`${bucharestToday()}T12:00:00Z`)) /
      86400000,
  );
  return {
    text:
      days < 0
        ? `Întârziat cu ${-days} ${days === -1 ? "zi" : "zile"}`
        : days === 0
          ? "Astăzi"
          : days === 1
            ? "Mâine"
            : `În ${days} zile`,
    overdue: days < 0,
  };
}
