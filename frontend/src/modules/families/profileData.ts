import {
  BookOpen,
  Activity,
  Stethoscope,
  Pill,
  Landmark,
  Award,
} from "lucide-react";
import type { PhotoStatus } from "../../shared/PrivatePhoto";

export type Category = "health" | "learning" | "activities";
export type Tab = "overview" | "documents" | Category;
export type ProfileRequest = {
  memberId: string;
  entryId?: string;
  category?: Category;
};
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
  achievement: { label: "Realizare", category: "activities", icon: Award },
} as const;
export type Kind = keyof typeof kinds;
export type Profile = {
  id: string;
  user_id: number | null;
  name: string;
  role: string;
  birth_date: string | null;
  birthday_shared: boolean;
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
export type StoryItem =
  | (Entry & { source: "entry" })
  | {
      source: "moment";
      id: string;
      kind: "moment" | "note";
      title: string;
      excerpt: string | null;
      occurred_on: string;
      visibility: "private" | "family" | "selected";
      photo_status: PhotoStatus;
      version: number;
      owner_name: string;
    };
export { errorMessage as message } from "../../shared/api";
