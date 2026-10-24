import { useEffect, useRef, useState } from "react";
import {
  Bike,
  Check,
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
import { ExpenseForm } from "./ExpenseForm";
import { MonthSelect } from "../../shared/MonthSelect";
import {
  expenseCategoryLabel,
  expenseMoney,
  type Expense,
  type ExpenseCategory,
  type ExpenseMonth,
  type ExpenseVisibility,
  type ExpenseRequest,
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
  const [data, setData] = useState<ExpenseMonth | null>(null);
  const [loading, setLoading] = useState(true);
  const [moreBusy, setMoreBusy] = useState(false);
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
  const endpoint = `/families/${family.id}/expenses?month=${month}&visibility=${visibility}`;
  const current =
    data?.month === month && data.visibility === visibility ? data : null;
  const canCreate = family.role !== "limited";
  useEffect(() => {
    if (focusAfterSave.current && !loading && current) {
      focusAfterSave.current = false;
      addButton.current?.focus();
    }
  }, [loading, current]);
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
    if (month !== targetMonth || visibility !== requestedExpense.visibility) {
      setMonth(targetMonth);
      setVisibility(requestedExpense.visibility);
      return;
    }
    if (loading || !current) return;
    const controller = new AbortController();
    function open(expense: Expense) {
      setEditor({ expense });
      onExpenseOpened();
    }
    const existing = current.items.find(
      (expense) => expense.id === requestedExpense.id,
    );
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
  }, [requestedExpense, month, visibility, loading, current, family.id]);
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
                  {visibility === "family" ? "Total familie" : "Total personal"}
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
                              {item.payer_name !== item.owner_name && (
                                <> · Adăugat de {item.owner_name}</>
                              )}
                            </span>
                          </div>
                          <strong className="expense-row-amount">
                            {expenseMoney(item.amount_bani)}
                          </strong>
                          {item.can_edit && (
                            <button
                              className="icon-button"
                              disabled={loading || moreBusy}
                              aria-label={`Editează ${item.note || expenseCategoryLabel(item.category)}, ${expenseMoney(item.amount_bani)}`}
                              onClick={() => setEditor({ expense: item })}
                            >
                              <Ellipsis size={20} />
                            </button>
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
                    {visibility === "family"
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
            {current.categories.length > 0 && (
              <aside
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
              </aside>
            )}
          </div>
        )
      )}
      {editor && current && (
        <ExpenseForm
          family={family}
          expense={editor.expense}
          payers={current.payers}
          onClose={() => setEditor(null)}
          onSaved={(result) => {
            setEditor(null);
            setMessage(result ? "Cheltuială salvată." : "Cheltuială ștearsă.");
            if (result) {
              setMonth(result.spent_on.slice(0, 7));
              setVisibility(result.visibility);
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
