import { BookOpen, Activity, Stethoscope, Pill, Landmark } from "lucide-react";
import type { PhotoStatus } from "../../shared/PrivatePhoto";

export type Category = "health" | "learning" | "activities";
export type Tab = "overview" | "documents" | Category;
export const categories = {
  health: { label: "Sănătate" },
  learning: { label: "Școală și cultură" },
  activities: { label: "Activități" },
};
export const kinds = {
  visit: { label: "Vizită medicală", category: "health", icon: Stethoscope },
  medication: { label: "Medicament", category: "health", icon: Pill },
  book: { label: "Carte citită", category: "learning", icon: BookOpen },
  culture: {
    label: "Activitate culturală",
    category: "learning",
    icon: Landmark,
  },
  activity: { label: "Activitate", category: "activities", icon: Activity },
} as const;
export type Kind = keyof typeof kinds;
export type Profile = {
  id: string;
  user_id: number | null;
  name: string;
  role: string;
  birth_date: string | null;
  age: number | null;
  parents: { id: string; name: string }[];
  version: number;
  photo_status: PhotoStatus;
  can_write: boolean;
  can_configure: boolean;
};
export type Entry = {
  id: string;
  kind: Kind;
  category: Category;
  title: string;
  notes: string | null;
  occurred_on: string;
  visibility: "private" | "family";
  version: number;
  author_name: string;
  can_manage: boolean;
};
export type EntryList = { items: Entry[]; page: number; has_more: boolean };
export const message = (e: unknown) =>
  e instanceof Error ? e.message : "Nu am putut salva. Reîncearcă.";
