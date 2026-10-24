import { api } from "../../shared/api";
import type { Task } from "./taskData";

export function setTaskCompletion(familyId: string, task: Task) {
  return api<Task>(`/families/${familyId}/tasks/${task.id}/completion`, {
    method: "PATCH",
    headers: { "If-Match": `"${task.version}"` },
    body: JSON.stringify({ completed: task.completed_at === null }),
  });
}
