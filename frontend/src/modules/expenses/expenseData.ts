import { api } from "../../shared/api";
import { dateLabel } from "../../shared/calendarDates";

export const expenseCategories = [
  { value: "food", label: "Alimente" },
  { value: "home", label: "Casă" },
  { value: "transport", label: "Transport" },
  { value: "health", label: "Sănătate" },
  { value: "children", label: "Copii" },
  { value: "leisure", label: "Timp liber" },
  { value: "other", label: "Altele" },
] as const;
export type ExpenseCategory = (typeof expenseCategories)[number]["value"];
export type ExpenseVisibility = "family" | "private";
export type ExpensePaymentStatus = "paid" | "planned";
export const expenseRecurrences = [
  { value: "daily", label: "Zilnic" },
  { value: "weekly", label: "Săptămânal" },
  { value: "monthly", label: "Lunar" },
  { value: "yearly", label: "Anual" },
] as const;
export type ExpenseRecurrence =
  | (typeof expenseRecurrences)[number]["value"]
  | null;
export type ExpenseRequest = {
  id: string;
  date: string;
  visibility: ExpenseVisibility;
  payment_status?: ExpensePaymentStatus;
  expense?: Expense;
};
export type Expense = {
  id: string;
  amount_bani: number;
  category: ExpenseCategory;
  spent_on: string;
  note: string | null;
  visibility: ExpenseVisibility;
  owner_name: string;
  payer_membership_id: string;
  payer_name: string;
  version: number;
  can_edit: boolean;
  payment_status: ExpensePaymentStatus;
  recurrence: ExpenseRecurrence;
  has_next_payment: boolean;
  next_due_on?: string | null;
  recurrence_notice?: string | null;
};
export type Payer = { id: string; name: string };
export type ExpenseMonth = {
  month: string;
  visibility: ExpenseVisibility;
  payment_status: ExpensePaymentStatus;
  total_bani: number;
  count: number;
  categories: {
    category: ExpenseCategory;
    total_bani: number;
    count: number;
  }[];
  items: Expense[];
  next_page: number | null;
  payers: Payer[];
};
export const expenseCategoryLabel = (value: ExpenseCategory) =>
  expenseCategories.find((category) => category.value === value)!.label;
const money = new Intl.NumberFormat("ro-RO", {
  minimumFractionDigits: 2,
  maximumFractionDigits: 2,
});
export const expenseMoney = (bani: number) => `${money.format(bani / 100)} lei`;
export const expenseAmountInput = (bani: number) =>
  `${Math.floor(bani / 100)},${String(bani % 100).padStart(2, "0")}`;
export const expenseRecurrenceLabel = (value: ExpenseRecurrence) =>
  expenseRecurrences.find((item) => item.value === value)?.label ??
  "Fără repetare";

export function markExpensePaid(
  familyId: string,
  expense: Pick<Expense, "id" | "version">,
  signal?: AbortSignal,
): Promise<Expense> {
  return api<Expense>(`/families/${familyId}/expenses/${expense.id}/payment`, {
    method: "PATCH",
    headers: { "If-Match": `"${expense.version}"` },
    body: JSON.stringify({ payment_status: "paid" }),
    signal,
  });
}

export function expensePaymentMessage(
  expense: Pick<Expense, "next_due_on" | "recurrence_notice">,
) {
  const notice =
    expense.recurrence_notice?.trim() || "Plata a fost marcată plătită.";
  return expense.next_due_on
    ? `${notice} Următoarea plată: ${dateLabel(expense.next_due_on, {
        day: "numeric",
        month: "long",
        year: "numeric",
      })}.`
    : notice;
}
