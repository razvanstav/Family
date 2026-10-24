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
export type ExpenseRequest = {
  id: string;
  date: string;
  visibility: ExpenseVisibility;
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
};
export type Payer = { id: string; name: string };
export type ExpenseMonth = {
  month: string;
  visibility: ExpenseVisibility;
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
