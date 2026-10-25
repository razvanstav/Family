import { useEffect, useRef, useState } from "react";
import {
  Bike,
  Check,
  CircleCheck,
  ChevronLeft,
  ChevronRight,
  Ellipsis,
  HeartPulse,
  House,
  LockKeyhole,
  Plus,
  Receipt,
  RefreshCw,
  ShoppingBasket,
  Smile,
  Ticket,
  Users,
  Wallet,
  X,
} from "lucide-react";
import { api, ApiError, errorMessage, type Family } from "../../shared/api";
import {
  bucharestToday,
  dateLabel,
  moveMonth,
} from "../../shared/calendarDates";
import { PageBackdrop } from "../../shared/PageBackdrop";
import { SectionTabs } from "../../shared/SectionTabs";
import { observePageResume } from "../../shared/pageResume";
import { ExpenseForm } from "./ExpenseForm";
import { MonthSelect } from "../../shared/MonthSelect";
import { SelectMenu } from "../../shared/SelectMenu";
import {
  expenseCategoryLabel,
  expenseMoney,
  expensePaymentMessage,
  expensePayerOptions,
  expenseRecurrenceLabel,
  markExpensePaid,
  type Expense,
  type ExpenseCategory,
  type ExpenseMonth,
  type ExpenseVisibility,
  type ExpenseRequest,
  type ExpensePaymentStatus,
  type Payer,
} from "./expenseData";
import "./expenses.css";

const categoryIcons = {
  food: ShoppingBasket,
  home: House,
  transport: Bike,
  health: HeartPulse,
  children: Smile,
  leisure: Ticket,
  other: Receipt,
};
const paymentStatuses = [
  { value: "paid", label: "Plătite" },
  { value: "planned", label: "De plătit" },
] as const;
function CategoryIcon({ category }: { category: ExpenseCategory }) {
  const Icon = categoryIcons[category];
  return (
    <span
      className={`expense-category-icon expense-category-${category}`}
      aria-hidden="true"
    >
      <Icon size={20} />
    </span>
  );
}

export function Expenses({
  family,
  onExpired,
  requestedExpense,
  onExpenseOpened,
}: {
  family: Family;
  onExpired: () => void;
  requestedExpense: ExpenseRequest | null;
  onExpenseOpened: () => void;
}) {
  const [month, setMonth] = useState(() =>
    (requestedExpense?.date ?? bucharestToday()).slice(0, 7),
  );
  const [visibility, setVisibility] = useState<ExpenseVisibility>(
    requestedExpense?.visibility ?? "family",
  );
  const [paymentStatus, setPaymentStatus] = useState<ExpensePaymentStatus>(
    requestedExpense?.expense?.payment_status ??
      requestedExpense?.payment_status ??
      "paid",
  );
  const [data, setData] = useState<ExpenseMonth | null>(null);
  const [payer, setPayer] = useState<Payer | null>(null);
  const [loading, setLoading] = useState(true);
  const [moreBusy, setMoreBusy] = useState(false);
  const [payingId, setPayingId] = useState<string | null>(null);
  const paymentPending = useRef(false);
  const [error, setError] = useState("");
  const [message, setMessage] = useState("");
  const [revision, setRevision] = useState(0);
  const [editor, setEditor] = useState<{ expense: Expense | null } | null>(
    null,
  );
  const sequence = useRef(0);
  const morePending = useRef(false);
  const addButton = useRef<HTMLButtonElement>(null);
  const focusAfterSave = useRef(false);
  const endpoint = `/families/${family.id}/expenses?month=${month}&visibility=${visibility}&payment_status=${paymentStatus}${payer ? `&payer_membership_id=${encodeURIComponent(payer.id)}` : ""}`;
  const current =
    data?.month === month &&
    data.visibility === visibility &&
    data.payment_status === paymentStatus &&
    data.payer_membership_id === (payer?.id ?? null)
      ? data
      : null;
  const canCreate = family.role !== "limited";
  const payerOptions = expensePayerOptions(
    data?.payers ?? [],
    data?.member_totals ?? [],
    payer,
  );
  useEffect(() => {
    if (focusAfterSave.current && !loading && current) {
      focusAfterSave.current = false;
      addButton.current?.focus();
    }
  }, [loading, current]);
  useEffect(
    () =>
      observePageResume(document, window, () =>
        setRevision((value) => value + 1),
      ),
    [],
  );
  function unavailable(error: unknown) {
    if (
      error instanceof ApiError &&
      [401, 403, 404, 419].includes(error.status)
    ) {
      setData(null);
      setEditor(null);
      onExpired();
    }
  }
  useEffect(() => {
    const request = ++sequence.current;
    const controller = new AbortController();
    setLoading(true);
    setError("");
    setMoreBusy(false);
    morePending.current = false;
    api<ExpenseMonth>(endpoint, { signal: controller.signal })
      .then((result) => {
        if (request === sequence.current) setData(result);
      })
      .catch((error) => {
        if (controller.signal.aborted || request !== sequence.current) return;
        setData(null);
        setError(errorMessage(error));
        unavailable(error);
      })
      .finally(() => {
        if (!controller.signal.aborted && request === sequence.current)
          setLoading(false);
      });
    return () => {
      controller.abort();
      sequence.current++;
    };
  }, [endpoint, revision]);
  useEffect(() => {
    if (!requestedExpense) return;
    const targetMonth = requestedExpense.date.slice(0, 7);
    const targetStatus =
      requestedExpense.expense?.payment_status ??
      requestedExpense.payment_status;
    if (
      month !== targetMonth ||
      visibility !== requestedExpense.visibility ||
      payer !== null ||
      (targetStatus && paymentStatus !== targetStatus)
    ) {
      setMonth(targetMonth);
      setVisibility(requestedExpense.visibility);
      setPayer(null);
      if (targetStatus) setPaymentStatus(targetStatus);
      return;
    }
    if (loading || !current) return;
    const controller = new AbortController();
    function open(expense: Expense) {
      setMonth(expense.spent_on.slice(0, 7));
      setVisibility(expense.visibility);
      setPaymentStatus(expense.payment_status);
      setEditor({ expense });
      onExpenseOpened();
    }
    const existing =
      current.items.find((expense) => expense.id === requestedExpense.id) ??
      requestedExpense.expense;
    if (existing) open(existing);
    else
      api<Expense>(`/families/${family.id}/expenses/${requestedExpense.id}`, {
        signal: controller.signal,
      })
        .then((expense) => {
          if (!controller.signal.aborted) open(expense);
        })
        .catch((cause) => {
          if (controller.signal.aborted) return;
          setError(errorMessage(cause));
          onExpenseOpened();
          unavailable(cause);
        });
    return () => controller.abort();
  }, [
    requestedExpense,
    month,
    visibility,
    paymentStatus,
    payer,
    loading,
    current,
    family.id,
  ]);
  async function pay(expense: Expense) {
    if (paymentPending.current || expense.payment_status !== "planned") return;
    paymentPending.current = true;
    setPayingId(expense.id);
    setError("");
    try {
      const result = await markExpensePaid(family.id, expense);
      setMessage(expensePaymentMessage(result));
      focusAfterSave.current = true;
      setRevision((value) => value + 1);
    } catch (cause) {
      setError(errorMessage(cause));
      unavailable(cause);
    } finally {
      paymentPending.current = false;
      setPayingId(null);
    }
  }
  async function loadMore() {
    if (!current?.next_page || loading || morePending.current) return;
    const request = sequence.current;
    morePending.current = true;
    setMoreBusy(true);
    setError("");
    try {
      const result = await api<ExpenseMonth>(
        `${endpoint}&page=${current.next_page}`,
      );
      if (request === sequence.current)
        setData((previous) =>
          previous
            ? {
                ...result,
                items: [
                  ...previous.items,
                  ...result.items.filter(
                    (item) =>
                      !previous.items.some(
                        (existing) => existing.id === item.id,
                      ),
                  ),
                ],
              }
            : result,
        );
    } catch (error) {
      if (request !== sequence.current) return;
      setError(errorMessage(error));
      unavailable(error);
    } finally {
      if (request === sequence.current) {
        setMoreBusy(false);
        morePending.current = false;
      }
    }
  }
  const groups = new Map<string, Expense[]>();
  for (const item of current?.items ?? [])
    groups.set(item.spent_on, [...(groups.get(item.spent_on) ?? []), item]);
  return (
    <div className="expenses-page">
      <PageBackdrop image="family">
        <section className="page-heading">
          <h1>Cheltuieli</h1>
          {canCreate && (
            <button
              ref={addButton}
              className="button primary"
              aria-label="Adaugă cheltuială"
              disabled={!current || loading}
              onClick={() => setEditor({ expense: null })}
            >
              <Plus size={21} />
              <span>Adaugă cheltuială</span>
            </button>
          )}
        </section>
      </PageBackdrop>
      <SectionTabs
        label="Starea cheltuielilor"
        value={paymentStatus}
        options={paymentStatuses}
        onChange={setPaymentStatus}
      />
      <section className="expenses-toolbar" aria-label="Filtre cheltuieli">
        <div className="expense-month-picker">
          <button
            className="icon-button"
            aria-label="Luna precedentă"
            disabled={month === "1900-01"}
            onClick={() => setMonth(moveMonth(month, -1))}
          >
            <ChevronLeft size={19} />
          </button>
          <MonthSelect month={month} onChange={setMonth} />
          <button
            className="icon-button"
            aria-label="Luna următoare"
            disabled={month === "2099-12"}
            onClick={() => setMonth(moveMonth(month, 1))}
          >
            <ChevronRight size={19} />
          </button>
        </div>
        <div
          className="expense-visibility"
          role="group"
          aria-label="Acces cheltuieli"
        >
          <button
            aria-pressed={visibility === "family"}
            onClick={() => setVisibility("family")}
          >
            Familie
          </button>
          <button
            aria-pressed={visibility === "private"}
            onClick={() => setVisibility("private")}
          >
            <LockKeyhole size={15} aria-hidden="true" />
            Personale
          </button>
        </div>
        <div className="expense-member-filter">
          <span aria-hidden="true">Plătitor</span>
          <SelectMenu
            label="Plătitor"
            value={payer?.id ?? ""}
            options={payerOptions}
            onChange={(value) =>
              setPayer(
                value
                  ? {
                      id: value,
                      name: payerOptions.find(
                        (option) => option.value === value,
                      )!.label,
                    }
                  : null,
              )
            }
          />
        </div>
        <button
          className="icon-button"
          aria-label="Actualizează cheltuielile"
          disabled={loading || moreBusy}
          onClick={() => setRevision((value) => value + 1)}
        >
          <RefreshCw size={18} className={loading ? "spin" : ""} />
        </button>
      </section>
      {message && (
        <div className="notice" role="status">
          <Check size={18} />
          <span>{message}</span>
          <button
            aria-label="Închide notificarea"
            onClick={() => setMessage("")}
          >
            <X size={17} />
          </button>
        </div>
      )}
      {error && (
        <div className="error" role="alert">
          {error}{" "}
          <button
            className="text-button"
            onClick={() => setRevision((value) => value + 1)}
          >
            Reîncearcă
          </button>
        </div>
      )}
      {!current && loading ? (
        <p className="expenses-loading" role="status">
          Se încarcă cheltuielile…
        </p>
      ) : (
        current && (
          <div className="expenses-layout" aria-busy={loading}>
            <section
              className="expense-total"
              aria-labelledby="expense-total-label"
            >
              <span className="expense-wallet" aria-hidden="true">
                <Wallet size={24} />
              </span>
              <div>
                <h2 id="expense-total-label">
                  {paymentStatus === "planned"
                    ? payer
                      ? `De plătit · ${payer.name}`
                      : "Total de plătit"
                    : payer
                      ? `Total · ${payer.name}`
                      : visibility === "family"
                        ? "Total familie"
                        : "Total personal"}
                </h2>
                <strong>{expenseMoney(current.total_bani)}</strong>
              </div>
              <p>
                {dateLabel(`${month}-01`, { month: "long", year: "numeric" })}
                <span>
                  {current.count}{" "}
                  {current.count === 1 ? "cheltuială" : "cheltuieli"}
                </span>
              </p>
            </section>
            <section className="expense-list" aria-label="Cheltuieli pe zile">
              {current.items.length ? (
                [...groups.entries()].map(([day, items]) => (
                  <section
                    className="expense-day"
                    key={day}
                    aria-label={dateLabel(day)}
                  >
                    <h2>
                      {day === bucharestToday()
                        ? "Azi"
                        : dateLabel(day, {
                            weekday: "long",
                            day: "numeric",
                            month: "long",
                          })}
                    </h2>
                    <ul>
                      {items.map((item) => (
                        <li key={item.id}>
                          <CategoryIcon category={item.category} />
                          <div className="expense-row-copy">
                            <strong>
                              {item.note || expenseCategoryLabel(item.category)}
                            </strong>
                            <span>
                              {item.note && (
                                <>{expenseCategoryLabel(item.category)} · </>
                              )}
                              {item.payer_name}
                              {item.recurrence && (
                                <>
                                  {" "}
                                  · {expenseRecurrenceLabel(item.recurrence)}
                                </>
                              )}
                              {item.payer_name !== item.owner_name && (
                                <> · Adăugat de {item.owner_name}</>
                              )}
                            </span>
                          </div>
                          <strong className="expense-row-amount">
                            {expenseMoney(item.amount_bani)}
                          </strong>
                          {item.can_edit && (
                            <div className="expense-row-actions">
                              {item.payment_status === "planned" && (
                                <button
                                  className="icon-button"
                                  disabled={
                                    loading || moreBusy || payingId !== null
                                  }
                                  aria-label={`Marchează plătită: ${item.note || expenseCategoryLabel(item.category)}, ${expenseMoney(item.amount_bani)}`}
                                  onClick={() => void pay(item)}
                                >
                                  {payingId === item.id ? (
                                    <RefreshCw size={20} className="spin" />
                                  ) : (
                                    <CircleCheck size={20} />
                                  )}
                                </button>
                              )}
                              <button
                                className="icon-button"
                                disabled={
                                  loading || moreBusy || payingId !== null
                                }
                                aria-label={`Editează ${item.note || expenseCategoryLabel(item.category)}, ${expenseMoney(item.amount_bani)}`}
                                onClick={() => setEditor({ expense: item })}
                              >
                                <Ellipsis size={20} />
                              </button>
                            </div>
                          )}
                        </li>
                      ))}
                    </ul>
                  </section>
                ))
              ) : (
                <div className="expense-empty">
                  <Receipt size={32} aria-hidden="true" />
                  <p>
                    {paymentStatus === "planned"
                      ? payer
                        ? "Nicio plată de făcut pentru membrul selectat în această lună."
                        : "Nicio plată de făcut în această lună."
                      : payer
                        ? "Nicio cheltuială pentru membrul selectat în această lună."
                        : visibility === "family"
                          ? "Nicio cheltuială a familiei în această lună."
                          : "Nicio cheltuială personală în această lună."}
                  </p>
                  {canCreate && (
                    <button
                      className="button secondary"
                      onClick={() => setEditor({ expense: null })}
                    >
                      Adaugă cheltuială
                    </button>
                  )}
                </div>
              )}
              {current.next_page && (
                <button
                  className="button secondary expense-more"
                  disabled={moreBusy || loading}
                  onClick={() => void loadMore()}
                >
                  {moreBusy ? "Se încarcă…" : "Mai multe cheltuieli"}
                </button>
              )}
            </section>
            {(current.member_totals.length > 0 ||
              current.categories.length > 0) && (
              <aside
                className="expense-summaries"
                aria-label="Totaluri cheltuieli"
              >
                {current.member_totals.length > 0 && (
                  <section
                    className="expense-breakdown expense-members"
                    aria-labelledby="expense-members-title"
                  >
                    <h2 id="expense-members-title">Pe membri</h2>
                    <ul>
                      {current.member_totals.map((item) => (
                        <li key={item.payer_membership_id}>
                          <button
                            aria-pressed={
                              payer?.id === item.payer_membership_id
                            }
                            aria-label={`${item.payer_name}: ${expenseMoney(item.total_bani)}`}
                            onClick={() =>
                              setPayer(
                                payer?.id === item.payer_membership_id
                                  ? null
                                  : {
                                      id: item.payer_membership_id,
                                      name: item.payer_name,
                                    },
                              )
                            }
                          >
                            <Users size={18} aria-hidden="true" />
                            <span>{item.payer_name}</span>
                            <strong>{expenseMoney(item.total_bani)}</strong>
                          </button>
                        </li>
                      ))}
                    </ul>
                  </section>
                )}
                {current.categories.length > 0 && (
                  <section
                    className="expense-breakdown"
                    aria-labelledby="expense-breakdown-title"
                  >
                    <h2 id="expense-breakdown-title">Categorii</h2>
                    <ul>
                      {current.categories.map((item) => (
                        <li key={item.category}>
                          <CategoryIcon category={item.category} />
                          <span>{expenseCategoryLabel(item.category)}</span>
                          <strong>{expenseMoney(item.total_bani)}</strong>
                        </li>
                      ))}
                    </ul>
                  </section>
                )}
              </aside>
            )}
          </div>
        )
      )}
      {editor && current && (
        <ExpenseForm
          family={family}
          expense={editor.expense}
          initialPaymentStatus={paymentStatus}
          payers={current.payers}
          onClose={() => setEditor(null)}
          onSaved={(result) => {
            setEditor(null);
            setMessage(
              result?.recurrence_notice || result?.next_due_on
                ? expensePaymentMessage(result)
                : result
                  ? "Cheltuială salvată."
                  : "Cheltuială ștearsă.",
            );
            if (result) {
              setMonth(result.spent_on.slice(0, 7));
              setVisibility(result.visibility);
              setPaymentStatus(result.payment_status);
              if (payer)
                setPayer({
                  id: result.payer_membership_id,
                  name: result.payer_name,
                });
            }
            setRevision((value) => value + 1);
            focusAfterSave.current = true;
          }}
          onUnavailable={() => {
            setEditor(null);
            setData(null);
            setRevision((value) => value + 1);
            onExpired();
          }}
        />
      )}
    </div>
  );
}
