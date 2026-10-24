import { useEffect, useRef, useState } from "react";
import { CalendarDays, Check, RefreshCw, Wallet } from "lucide-react";
import { ApiError, errorMessage, type Family } from "../../shared/api";
import { dateLabel } from "../../shared/calendarDates";
import {
  expenseCategoryLabel,
  expenseMoney,
  expensePaymentMessage,
  markExpensePaid,
  type Expense,
} from "./expenseData";
import "./upcomingPayments.css";

export function UpcomingPaymentsCard({
  family,
  payments,
  loading,
  onChanged,
  onOpen,
  onExpired,
}: {
  family: Family;
  payments: Expense[];
  loading: boolean;
  onChanged: () => void;
  onOpen: (expense: Expense) => void;
  onExpired: () => void;
}) {
  const [busy, setBusy] = useState<string | null>(null);
  const [error, setError] = useState("");
  const [message, setMessage] = useState("");
  const [stale, setStale] = useState(false);
  const pending = useRef(false);
  const active = useRef(true);
  const request = useRef<AbortController | null>(null);
  const list = useRef<HTMLUListElement>(null);
  const focusIndex = useRef<number | null>(null);
  const previousPayments = useRef(payments);
  useEffect(() => {
    active.current = true;
    return () => {
      active.current = false;
      request.current?.abort();
    };
  }, []);
  useEffect(() => {
    if (loading || payments === previousPayments.current) return;
    previousPayments.current = payments;
    pending.current = false;
    setBusy(null);
    setStale(false);
    setError("");
  }, [payments, loading]);
  useEffect(() => {
    if (loading || busy !== null) return;
    if (focusIndex.current !== null) {
      const rows = list.current?.querySelectorAll<HTMLLIElement>("li");
      const row = rows?.[Math.min(focusIndex.current, rows.length - 1)];
      (
        row?.querySelector<HTMLButtonElement>(
          'button[role="checkbox"]:not([disabled])',
        ) ?? row?.querySelector<HTMLButtonElement>("button:not([disabled])")
      )?.focus();
      focusIndex.current = null;
    }
  }, [payments, loading, busy]);

  async function pay(expense: Expense) {
    if (pending.current || stale || loading) return;
    pending.current = true;
    setBusy(expense.id);
    setError("");
    setMessage("");
    const controller = new AbortController();
    request.current = controller;
    let refreshing = false;
    try {
      const saved = await markExpensePaid(
        family.id,
        expense,
        controller.signal,
      );
      if (!active.current || controller.signal.aborted) return;
      setMessage(expensePaymentMessage(saved));
      focusIndex.current = payments.findIndex((item) => item.id === expense.id);
      refreshing = true;
      onChanged();
    } catch (failure) {
      if (!active.current || controller.signal.aborted) return;
      setError(errorMessage(failure));
      if (failure instanceof ApiError) {
        if ([409, 412].includes(failure.status)) setStale(true);
        if ([401, 403, 404, 419].includes(failure.status)) onExpired();
      }
    } finally {
      if (request.current === controller) request.current = null;
      if (!refreshing) {
        pending.current = false;
        if (active.current) setBusy(null);
      }
    }
  }

  return (
    <>
      <div className="section-header">
        <h2>
          <span className="upcoming-payments-icon">
            <Wallet size={20} aria-hidden="true" />
          </span>
          Plăți următoare
        </h2>
      </div>
      {message && (
        <p className="notice" role="status">
          {message}
        </p>
      )}
      {error && (
        <div className="upcoming-payments-error">
          <p className="error" role="alert">
            {error}
          </p>
          <button
            className="button secondary"
            disabled={loading || busy !== null}
            onClick={onChanged}
          >
            <RefreshCw size={17} aria-hidden="true" />
            Reîncarcă plățile
          </button>
        </div>
      )}
      <ul
        ref={list}
        className="upcoming-payments-list"
        aria-label="Plăți planificate în următoarele șapte zile"
      >
        {payments.map((expense) => {
          const title = expense.note || expenseCategoryLabel(expense.category);
          return (
            <li key={expense.id} className="upcoming-payment-row">
              <div className="upcoming-payment-copy">
                <button
                  className="upcoming-payment-title"
                  disabled={busy !== null || loading || stale}
                  onClick={() => onOpen(expense)}
                >
                  {title}
                </button>
                <div className="upcoming-payment-meta">
                  <span>
                    <CalendarDays size={14} aria-hidden="true" />
                    <time dateTime={expense.spent_on}>
                      {dateLabel(expense.spent_on, {
                        day: "numeric",
                        month: "short",
                      })}
                    </time>
                  </span>
                  <span>{expense.payer_name}</span>
                </div>
              </div>
              <strong className="upcoming-payment-amount">
                {expenseMoney(expense.amount_bani)}
              </strong>
              <button
                type="button"
                role="checkbox"
                aria-checked={false}
                aria-label={`Marchează ca plătită: ${title}`}
                className="upcoming-payment-check"
                aria-busy={busy === expense.id}
                disabled={
                  !expense.can_edit || busy !== null || stale || loading
                }
                onClick={() => void pay(expense)}
              >
                <Check size={18} aria-hidden="true" />
              </button>
            </li>
          );
        })}
      </ul>
    </>
  );
}
