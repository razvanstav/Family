import { useEffect, useState } from "react";
import type { Family } from "../shared/api";
import { SectionTabs } from "../shared/SectionTabs";
import { Expenses } from "../modules/expenses/Expenses";
import type { ExpenseRequest } from "../modules/expenses/expenseData";
import { Subscriptions } from "../modules/subscriptions/Subscriptions";

export function ExpensesWorkspace({
  family,
  actorId,
  requestedExpense,
  requestedSubscription,
  onExpenseOpened,
  onSubscriptionOpened,
  onChanged,
  onExpired,
}: {
  family: Family;
  actorId: number;
  requestedExpense: ExpenseRequest | null;
  requestedSubscription: string | null;
  onExpenseOpened: () => void;
  onSubscriptionOpened: () => void;
  onChanged: () => void;
  onExpired: () => void;
}) {
  const [section, setSection] = useState(
    requestedSubscription ? "subscriptions" : "expenses",
  );
  useEffect(() => {
    if (requestedExpense) setSection("expenses");
  }, [requestedExpense]);
  useEffect(() => {
    if (requestedSubscription) setSection("subscriptions");
  }, [requestedSubscription]);
  const tabs = (
    <SectionTabs
      label="Secțiuni Cheltuieli"
      value={section}
      onChange={setSection}
      options={[
        { value: "expenses", label: "Cheltuieli" },
        { value: "subscriptions", label: "Abonamente" },
      ]}
    />
  );
  return section === "subscriptions" ? (
    <Subscriptions
      key={`${actorId}:${family.id}`}
      family={family}
      actorId={actorId}
      sectionTabs={tabs}
      requestedSubscription={requestedSubscription}
      onSubscriptionOpened={onSubscriptionOpened}
      onChanged={onChanged}
      onExpired={onExpired}
    />
  ) : (
    <Expenses
      key={`${actorId}:${family.id}`}
      family={family}
      sectionTabs={tabs}
      requestedExpense={requestedExpense}
      onExpenseOpened={onExpenseOpened}
      onExpired={onExpired}
    />
  );
}
