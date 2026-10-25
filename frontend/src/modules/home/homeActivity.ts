export type ActivityTarget =
  | { kind: "task" | "memory" | "recipe" | "subscription"; id: string }
  | { kind: "calendar" | "meal"; id: string; date: string }
  | {
      kind: "expense";
      id: string;
      date: string;
      visibility: "family" | "private";
      payment_status: "paid" | "planned";
    }
  | { kind: "vehicle" | "pet"; id: string }
  | { kind: "vehicle_entry" | "pet_entry"; id: string; parent_id: string };

export const activityActions = {
  "task.created": "a adăugat o sarcină",
  "task.completed": "a finalizat o sarcină",
  "task.reopened": "a redeschis o sarcină",
  "expense.created": "a adăugat o cheltuială",
  "expense.paid": "a marcat o plată ca achitată",
  "meal.created": "a adăugat o masă",
  "recipe.created": "a adăugat o rețetă",
  "memory.created": "a adăugat o amintire",
  "calendar.created": "a adăugat un eveniment",
  "vehicle.created": "a adăugat o mașină",
  "vehicle.entry_created": "a adăugat o înregistrare auto",
  "pet.created": "a adăugat un animal",
  "pet.entry_created": "a adăugat o înregistrare pentru un animal",
  "subscription.created": "a adăugat un abonament",
} as const;

export type HomeActivity = {
  id: string;
  action: keyof typeof activityActions;
  actor_name: string;
  occurred_at: string;
  title: string;
  target: ActivityTarget;
};
