import { dateLabel } from "../../shared/calendarDates";

export type TaskVisibility = "family" | "private";
export type TaskStatus = "open" | "completed";
export type TaskRecurrence = "daily" | "weekly" | "monthly" | "yearly" | null;
export const recurrenceLabels = {
  daily: "Zilnic",
  weekly: "Săptămânal",
  monthly: "Lunar",
  yearly: "Anual",
} as const;
export type Assignee = { id: string; name: string };
export type Task = {
  id: string;
  title: string;
  assignee_membership_id: string;
  assignee_name: string;
  due_on: string | null;
  recurrence: TaskRecurrence;
  visibility: TaskVisibility;
  owner_name: string;
  completed_at: string | null;
  version: number;
  can_edit: boolean;
  can_complete: boolean;
  recurrence_notice?: string;
  next_due_on?: string | null;
};
export function taskCompletionMessage(task: Task): string {
  if (!task.completed_at) return "Sarcina a fost redeschisă.";
  const notice = task.recurrence_notice?.trim() || "Sarcina a fost finalizată.";
  return task.next_due_on
    ? `${notice} Următorul termen: ${dateLabel(task.next_due_on, {
        day: "numeric",
        month: "long",
        year: "numeric",
      })}.`
    : notice;
}
export type TaskPage = {
  items: Task[];
  assignees: Assignee[];
  next_page: number | null;
};
