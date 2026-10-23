export type TaskVisibility = "family" | "private";
export type TaskStatus = "open" | "completed";
export type Assignee = { id: string; name: string };
export type Task = {
  id: string;
  title: string;
  assignee_membership_id: string;
  assignee_name: string;
  due_on: string | null;
  visibility: TaskVisibility;
  owner_name: string;
  completed_at: string | null;
  version: number;
  can_edit: boolean;
  can_complete: boolean;
};
export type TaskPage = {
  items: Task[];
  assignees: Assignee[];
  next_page: number | null;
};
