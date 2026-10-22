export const momentKinds = { moment: "Moment", note: "Notă" } as const;

export type Moment = {
  id: string;
  kind: keyof typeof momentKinds;
  title: string;
  body: string | null;
  occurred_on: string;
  visibility: "private" | "family";
  owner_id: number;
  owner_name: string;
  version: number;
  can_edit: boolean;
  people: { id: string; name: string }[];
  created_at: string;
  updated_at: string;
};

export type MomentList = {
  items: Moment[];
  page: number;
  has_more: boolean;
};
