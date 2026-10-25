import { useEffect, useRef, useState, type FormEvent } from "react";
import { ChevronDown } from "lucide-react";
import { api, ApiError, errorMessage } from "../../shared/api";
import { bucharestToday } from "../../shared/calendarDates";
import { DatePicker } from "../../shared/DatePicker";
import { Modal } from "../../shared/Modal";
import { ScorePicker } from "./ScorePicker";
import { dishCategories, type DishCategory } from "./diningTypes";
import { mealTypes, type MealDetailData } from "./mealTypes";

export function QuickMealForm({
  base,
  initialDate,
  onClose,
  onSaved,
  onExpired,
}: {
  base: string;
  initialDate: string;
  onClose: () => void;
  onSaved: (meal: MealDetailData) => void;
  onExpired: () => void;
}) {
  const [title, setTitle] = useState("");
  const [date, setDate] = useState(initialDate);
  const [type, setType] = useState<keyof typeof mealTypes>("snack");
  const [category, setCategory] = useState<DishCategory>("other");
  const [score, setScore] = useState<number | null>(null);
  const [visibility, setVisibility] = useState("family");
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState("");
  const request = useRef<AbortController | null>(null);
  const titleInput = useRef<HTMLInputElement>(null);
  const pending = useRef(false);
  useEffect(() => () => request.current?.abort(), []);
  useEffect(() => {
    titleInput.current?.focus({ preventScroll: true });
  }, []);
  async function save(event: FormEvent) {
    event.preventDefault();
    if (pending.current) return;
    pending.current = true;
    const controller = new AbortController();
    request.current = controller;
    setBusy(true);
    setError("");
    try {
      const meal = await api<MealDetailData>(base, {
        method: "POST",
        signal: controller.signal,
        body: JSON.stringify({
          dish_title: title.trim(),
          meal_date: date,
          meal_type: type,
          category,
          visibility,
          ...(score !== null ? { score } : {}),
        }),
      });
      if (!controller.signal.aborted) onSaved(meal);
    } catch (cause) {
      if (controller.signal.aborted) return;
      if (
        cause instanceof ApiError &&
        [401, 403, 404, 419].includes(cause.status)
      ) {
        onClose();
        onExpired();
      } else setError(errorMessage(cause));
    } finally {
      pending.current = false;
      if (!controller.signal.aborted) setBusy(false);
    }
  }
  return (
    <Modal title="Adaugă masă" onClose={() => !busy && onClose()}>
      <form onSubmit={(event) => void save(event)}>
        <fieldset className="quick-meal-fields" disabled={busy}>
          <label className="field">
            Preparat
            <input
              ref={titleInput}
              autoFocus
              required
              maxLength={160}
              value={title}
              onChange={(event) => setTitle(event.target.value)}
            />
          </label>
          <label className="field">
            Masă
            <select
              value={type}
              onChange={(event) =>
                setType(event.target.value as keyof typeof mealTypes)
              }
            >
              {Object.entries(mealTypes).map(([key, label]) => (
                <option key={key} value={key}>
                  {label}
                </option>
              ))}
            </select>
          </label>
          <details className="quick-meal-options">
            <summary>
              Opțiuni <ChevronDown size={16} aria-hidden="true" />
            </summary>
            <DatePicker
              label="Data"
              value={date}
              min="1900-01-01"
              max="2100-12-31"
              required
              onChange={(value) => {
                setDate(value);
                if (value > bucharestToday()) setScore(null);
              }}
            />
            <label className="field">
              Categorie
              <select
                value={category}
                onChange={(event) =>
                  setCategory(event.target.value as DishCategory)
                }
              >
                {Object.entries(dishCategories).map(([key, label]) => (
                  <option key={key} value={key}>
                    {label}
                  </option>
                ))}
              </select>
            </label>
            <ScorePicker
              label="Nota preparatului (opțional)"
              value={score}
              onChange={setScore}
              disabled={date > bucharestToday()}
            />
          </details>
          <label className="field">
            Acces
            <select
              value={visibility}
              onChange={(event) => setVisibility(event.target.value)}
              aria-describedby="quick-meal-access"
            >
              <option value="family">Familia mea</option>
              <option value="private">Doar eu</option>
            </select>
            <small id="quick-meal-access">
              {visibility === "family"
                ? "Membrii activi văd masa și evaluările."
                : "Numai tu poți vedea masa și evaluările."}
            </small>
          </label>
        </fieldset>
        {error && (
          <p className="error" role="alert">
            {error}
          </p>
        )}
        <div className="meal-vote-actions">
          <button
            type="button"
            className="button secondary"
            disabled={busy}
            onClick={onClose}
          >
            Anulează
          </button>
          <button className="button primary" disabled={busy || !title.trim()}>
            {busy ? "Se salvează…" : "Salvează"}
          </button>
        </div>
      </form>
    </Modal>
  );
}
