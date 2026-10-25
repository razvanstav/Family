import { useEffect, useRef, useState } from "react";
import { Trash2 } from "lucide-react";
import { api, ApiError, errorMessage, type Family } from "../../shared/api";
import { bucharestToday } from "../../shared/calendarDates";
import { Modal } from "../../shared/Modal";
import { DatePicker } from "../../shared/DatePicker";
import {
  expenseAmountInput,
  expenseCategories,
  expenseMoney,
  expenseRecurrences,
  type Expense,
  type ExpenseCategory,
  type ExpenseVisibility,
  type ExpensePaymentStatus,
  type ExpenseRecurrence,
  type Payer,
} from "./expenseData";

export function ExpenseForm({
  family,
  expense,
  payers,
  initialPaymentStatus = "paid",
  returnFocus,
  onClose,
  onSaved,
  onUnavailable,
}: {
  family: Family;
  expense: Expense | null;
  payers: Payer[];
  initialPaymentStatus?: ExpensePaymentStatus;
  returnFocus?: HTMLElement | null;
  onClose: () => void;
  onSaved: (result: Expense | null) => void;
  onUnavailable: () => void;
}) {
  const [source, setSource] = useState(expense);
  const [amount, setAmount] = useState(
    expense ? expenseAmountInput(expense.amount_bani) : "",
  );
  const [category, setCategory] = useState<ExpenseCategory>(
    expense?.category ?? "food",
  );
  const [date, setDate] = useState(expense?.spent_on ?? bucharestToday());
  const [paymentStatus, setPaymentStatus] = useState<ExpensePaymentStatus>(
    expense?.payment_status ?? initialPaymentStatus,
  );
  const [recurrence, setRecurrence] = useState<ExpenseRecurrence>(
    expense?.recurrence ?? null,
  );
  const [payer, setPayer] = useState(
    expense?.payer_membership_id ?? family.membership_id,
  );
  const [note, setNote] = useState(expense?.note ?? "");
  const [visibility, setVisibility] = useState<ExpenseVisibility>(
    expense?.visibility ?? "family",
  );
  const [busy, setBusy] = useState(false);
  const pending = useRef(false);
  const amountInput = useRef<HTMLInputElement>(null);
  useEffect(() => {
    amountInput.current?.focus();
  }, []);
  const [error, setError] = useState("");
  const [stale, setStale] = useState(false);
  const [deleting, setDeleting] = useState(false);
  const base = `/families/${family.id}/expenses`;
  const readOnly = source !== null && !source.can_edit;
  const payerOptions =
    source && !payers.some((member) => member.id === source.payer_membership_id)
      ? [
          ...payers,
          {
            id: source.payer_membership_id,
            name: `${source.payer_name} (fost membru)`,
          },
        ]
      : payers;
  function handleError(error: unknown) {
    setError(errorMessage(error));
    if (error instanceof ApiError) {
      if (error.status === 412) setStale(true);
      if ([401, 403, 404, 419].includes(error.status)) onUnavailable();
    }
  }
  async function submit(remove = false) {
    if (pending.current || stale) return;
    pending.current = true;
    setBusy(true);
    setError("");
    try {
      const result = await api<Expense | undefined>(
        source ? `${base}/${source.id}` : base,
        {
          method: remove ? "DELETE" : source ? "PATCH" : "POST",
          headers: source ? { "If-Match": `"${source.version}"` } : undefined,
          body: remove
            ? undefined
            : JSON.stringify({
                amount: amount.trim(),
                category,
                spent_on: date,
                payer_membership_id: payer,
                note: note.trim() || null,
                visibility,
                payment_status: paymentStatus,
                recurrence,
              }),
        },
      );
      onSaved(result ?? null);
    } catch (error) {
      handleError(error);
    } finally {
      pending.current = false;
      setBusy(false);
    }
  }
  async function reload() {
    if (!source || pending.current) return;
    pending.current = true;
    setBusy(true);
    setError("");
    try {
      const fresh = await api<Expense>(`${base}/${source.id}`);
      if (!fresh.can_edit) {
        onUnavailable();
        return;
      }
      setSource(fresh);
      setAmount(expenseAmountInput(fresh.amount_bani));
      setCategory(fresh.category);
      setDate(fresh.spent_on);
      setPaymentStatus(fresh.payment_status);
      setRecurrence(fresh.recurrence);
      setPayer(fresh.payer_membership_id);
      setNote(fresh.note ?? "");
      setVisibility(fresh.visibility);
      setStale(false);
      setDeleting(false);
    } catch (error) {
      handleError(error);
    } finally {
      pending.current = false;
      setBusy(false);
    }
  }
  return (
    <Modal
      returnFocus={returnFocus}
      title={
        deleting
          ? "Șterge cheltuiala"
          : source
            ? readOnly
              ? "Cheltuială"
              : "Editează cheltuiala"
            : "Adaugă cheltuială"
      }
      onClose={() => {
        if (!pending.current) onClose();
      }}
    >
      {deleting && source ? (
        <>
          <p className="modal-lead">
            Ștergi cheltuiala de {expenseMoney(source.amount_bani)}? Va fi
            eliminată din lista și totalul lunii.
          </p>
          <div className="expense-form-actions">
            <button
              className="button secondary"
              disabled={busy}
              onClick={() => setDeleting(false)}
            >
              Înapoi
            </button>
            <button
              className="button danger"
              disabled={busy || stale}
              onClick={() => void submit(true)}
            >
              {busy ? "Se șterge…" : "Șterge"}
            </button>
          </div>
        </>
      ) : (
        <form
          onSubmit={(event) => {
            event.preventDefault();
            void submit();
          }}
        >
          <fieldset
            className="expense-fields"
            disabled={busy || stale || readOnly}
          >
            <div className="expense-form-pair">
              <label className="field">
                Sumă (lei)
                <input
                  ref={amountInput}
                  name="amount"
                  inputMode="decimal"
                  value={amount}
                  onChange={(event) => setAmount(event.target.value)}
                  required
                  maxLength={9}
                  pattern="(?:0|[1-9][0-9]{0,5})(?:[.,][0-9]{1,2})?"
                  title="Sumă în lei, cu cel mult două zecimale."
                  placeholder="0,00"
                />
              </label>
              <label className="field">
                Categorie
                <select
                  name="category"
                  value={category}
                  onChange={(event) =>
                    setCategory(event.target.value as ExpenseCategory)
                  }
                >
                  {expenseCategories.map((item) => (
                    <option key={item.value} value={item.value}>
                      {item.label}
                    </option>
                  ))}
                </select>
              </label>
            </div>
            <div className="expense-form-pair">
              <label className="field">
                Stare
                <select
                  name="payment_status"
                  value={paymentStatus}
                  disabled={source !== null}
                  onChange={(event) =>
                    setPaymentStatus(event.target.value as ExpensePaymentStatus)
                  }
                >
                  <option value="paid">Plătită</option>
                  <option value="planned">De plătit</option>
                </select>
              </label>
              <label className="field">
                Repetare
                <select
                  name="recurrence"
                  value={recurrence ?? ""}
                  disabled={Boolean(source?.has_next_payment)}
                  aria-describedby={
                    source?.has_next_payment || recurrence
                      ? "expense-recurrence-hint"
                      : undefined
                  }
                  onChange={(event) =>
                    setRecurrence(
                      (event.target.value || null) as ExpenseRecurrence,
                    )
                  }
                >
                  <option value="">Fără repetare</option>
                  {expenseRecurrences.map((item) => (
                    <option key={item.value} value={item.value}>
                      {item.label}
                    </option>
                  ))}
                </select>
                {(source?.has_next_payment || recurrence) && (
                  <small id="expense-recurrence-hint">
                    {source?.has_next_payment
                      ? "Modifică repetarea în următoarea plată."
                      : paymentStatus === "planned"
                        ? "Următoarea plată se creează când aceasta este plătită."
                        : "Următoarea plată se creează la salvare."}
                  </small>
                )}
              </label>
            </div>
            <div className="expense-form-pair">
              <DatePicker
                value={date}
                onChange={setDate}
                label={paymentStatus === "planned" ? "Termen" : "Data"}
                name="spent_on"
                dialogLabel="Alege data cheltuielii"
              />
              <label className="field">
                {paymentStatus === "planned"
                  ? "Cine plătește"
                  : "Cine a plătit"}
                <select
                  name="payer"
                  value={payer}
                  onChange={(event) => setPayer(event.target.value)}
                  required
                >
                  {payerOptions.map((item) => (
                    <option key={item.id} value={item.id}>
                      {item.name}
                    </option>
                  ))}
                </select>
              </label>
            </div>
            <label className="field">
              Notă (opțional)
              <input
                name="note"
                value={note}
                onChange={(event) => setNote(event.target.value)}
                maxLength={240}
              />
            </label>
            <label className="field">
              Acces
              <select
                name="visibility"
                aria-label="Acces"
                value={visibility}
                aria-describedby="expense-access-hint"
                onChange={(event) =>
                  setVisibility(event.target.value as ExpenseVisibility)
                }
              >
                <option value="family">Familie</option>
                <option value="private">Privat</option>
              </select>
              <small id="expense-access-hint">
                {visibility === "family"
                  ? "Membrii activi ai familiei o pot vedea. Numai tu o poți modifica."
                  : "Vizibilă numai pentru tine."}
              </small>
            </label>
          </fieldset>
          <div className="expense-form-actions">
            {source && !readOnly && (
              <button
                type="button"
                className="icon-button"
                aria-label="Șterge cheltuiala"
                disabled={busy || stale}
                onClick={() => setDeleting(true)}
              >
                <Trash2 size={19} />
              </button>
            )}
            <button
              type="button"
              className="button secondary"
              disabled={busy}
              onClick={onClose}
            >
              {readOnly ? "Închide" : "Anulează"}
            </button>
            {!readOnly && (
              <button className="button primary" disabled={busy || stale}>
                {busy ? "Se salvează…" : "Salvează"}
              </button>
            )}
          </div>
        </form>
      )}
      {error && (
        <p className="error" role="alert">
          {error}
        </p>
      )}
      {stale && (
        <button
          className="button secondary full"
          disabled={busy}
          onClick={() => void reload()}
        >
          {busy ? "Se încarcă…" : "Reîncarcă cheltuiala"}
        </button>
      )}
    </Modal>
  );
}
