export const momentKinds = { moment: "Moment", note: "Notă" } as const;

export type Moment = {
  id: string;
  kind: keyof typeof momentKinds;
  title: string;
  body: string | null;
  occurred_on: string;
  visibility: "private" | "family" | "selected";
  owner_id: number;
  owner_name: string;
  version: number;
  can_edit: boolean;
  can_manage: boolean;
  grants: string[];
  photo_status: PhotoStatus;
  photos: MomentPhoto[];
  archived_at: string | null;
  people: { id: string; name: string }[];
  created_at: string;
  updated_at: string;
};

export type MomentPhoto = { id: string; status: Exclude<PhotoStatus, null> };

export type MomentList = {
  items: Moment[];
  page: number;
  has_more: boolean;
  years: number[];
  today: string;
  on_this_day: Moment[];
};
import type { PhotoStatus } from "../../shared/PrivatePhoto";
