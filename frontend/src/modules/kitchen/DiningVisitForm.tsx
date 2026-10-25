import { SelectField } from "../../shared/SelectField";
import { useEffect, useRef, useState, type FormEvent } from "react";
import { Plus, Search, Trash2 } from "lucide-react";
import { api, ApiError, errorMessage, type Family } from "../../shared/api";
import { bucharestToday } from "../../shared/calendarDates";
import { DatePicker } from "../../shared/DatePicker";
import { Modal } from "../../shared/Modal";
import { mealTypes } from "./mealTypes";
import { ScorePicker } from "./ScorePicker";
import {
  dishCategories,
  type DishCategory,
  type DiningVisitDetail,
  type Restaurant,
} from "./diningTypes";

type DishDraft = {
  key: number;
  title: string;
  category: DishCategory;
  score: number | null;
  comment: string;
};
const freshDish = (key: number): DishDraft => ({
  key,
  title: "",
  category: "other",
  score: null,
  comment: "",
});

export function DiningVisitForm({
  family,
  restaurants,
  initialRestaurant,
  restaurantQuery,
  onRestaurantQuery,
  directoryLoading,
  directoryError,
  hasMoreRestaurants,
  onMoreRestaurants,
  onRestaurantCreated,
  onClose,
  onSaved,
  onExpired,
}: {
  family: Family;
  restaurants: Restaurant[];
  initialRestaurant: Restaurant | null;
  restaurantQuery: string;
  onRestaurantQuery: (query: string) => void;
  directoryLoading: boolean;
  directoryError: string;
  hasMoreRestaurants: boolean;
  onMoreRestaurants: () => void;
  onRestaurantCreated: (restaurant: Restaurant) => void;
  onClose: () => void;
  onSaved: (visit: DiningVisitDetail) => void;
  onExpired: () => void;
}) {
  const [restaurant, setRestaurant] = useState<Restaurant | null>(
    initialRestaurant,
  );
  const [newRestaurant, setNewRestaurant] = useState(false);
  const [name, setName] = useState("");
  const [city, setCity] = useState("");
  const [date, setDate] = useState(bucharestToday);
  const [mealType, setMealType] = useState<keyof typeof mealTypes>("dinner");
  const [visibility, setVisibility] = useState<"private" | "family">("family");
  const [score, setScore] = useState<number | null>(null);
  const [comment, setComment] = useState("");
  const [dishes, setDishes] = useState<DishDraft[]>([freshDish(1)]);
  const nextKey = useRef(2);
  const pending = useRef<AbortController | null>(null);
  const titleInput = useRef<HTMLInputElement>(null);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState("");
  const canRate = date <= bucharestToday();
  const options =
    restaurant && !restaurants.some((item) => item.id === restaurant.id)
      ? [restaurant, ...restaurants]
      : restaurants;
  useEffect(() => () => pending.current?.abort(), []);

  function changeDish(key: number, values: Partial<DishDraft>) {
    setDishes((current) =>
      current.map((dish) => (dish.key === key ? { ...dish, ...values } : dish)),
    );
  }
  async function save(event: FormEvent) {
    event.preventDefault();
    if (pending.current) return;
    if (!restaurant && !newRestaurant) {
      setError("Alege restaurantul sau adaugă unul nou.");
      return;
    }
    const controller = new AbortController();
    pending.current = controller;
    setBusy(true);
    setError("");
    try {
      let selected = restaurant;
      if (newRestaurant) {
        selected = await api<Restaurant>(`/families/${family.id}/restaurants`, {
          method: "POST",
          signal: controller.signal,
          body: JSON.stringify({
            name: name.trim(),
            city: city.trim() || null,
          }),
        });
        if (controller.signal.aborted) return;
        setRestaurant(selected);
        setNewRestaurant(false);
        onRestaurantCreated(selected);
      }
      const visit = await api<DiningVisitDetail>(
        `/families/${family.id}/dining-visits`,
        {
          method: "POST",
          signal: controller.signal,
          body: JSON.stringify({
            restaurant_id: selected!.id,
            meal_date: date,
            meal_type: mealType,
            visibility,
            restaurant_score: canRate ? score : null,
            restaurant_comment:
              !canRate || score === null ? null : comment.trim() || null,
            dishes: dishes.map((dish) => ({
              dish_title: dish.title.trim(),
              category: dish.category,
              score: canRate ? dish.score : null,
              comment:
                !canRate || dish.score === null
                  ? null
                  : dish.comment.trim() || null,
            })),
          }),
        },
      );
      if (!controller.signal.aborted) onSaved(visit);
    } catch (cause) {
      if (controller.signal.aborted) return;
      setError(errorMessage(cause));
      if (
        cause instanceof ApiError &&
        [401, 403, 404, 419].includes(cause.status)
      )
        onExpired();
    } finally {
      if (!controller.signal.aborted) {
        pending.current = null;
        setBusy(false);
      }
    }
  }
  return (
    <Modal
      title="Adaugă vizită"
      wide
      onClose={() => {
        if (!pending.current) onClose();
      }}
    >
      <form onSubmit={(event) => void save(event)}>
        {error && (
          <p className="error" role="alert">
            {error}
          </p>
        )}
        <fieldset disabled={busy} className="dining-form-fields">
          {newRestaurant ? (
            <div className="dining-restaurant-fields">
              <label className="field">
                Restaurant
                <input
                  value={name}
                  onChange={(event) => setName(event.target.value)}
                  required
                  maxLength={160}
                  autoFocus
                  autoComplete="off"
                />
              </label>
              <label className="field">
                Localitate (opțional)
                <input
                  value={city}
                  onChange={(event) => setCity(event.target.value)}
                  maxLength={120}
                  autoComplete="address-level2"
                />
              </label>
              <button
                type="button"
                className="text-button"
                onClick={() => setNewRestaurant(false)}
              >
                Alege un restaurant salvat
              </button>
            </div>
          ) : (
            <div className="dining-restaurant-fields">
              <label className="search dining-directory-search">
                <Search size={18} aria-hidden="true" />
                <input
                  type="search"
                  aria-label="Caută restaurant"
                  value={restaurantQuery}
                  onChange={(event) => onRestaurantQuery(event.target.value)}
                  placeholder="Caută restaurant"
                />
              </label>
              <label className="field">
                Restaurant
                <SelectField
                  value={restaurant?.id ?? ""}
                  onChange={(event) =>
                    setRestaurant(
                      options.find((item) => item.id === event.target.value) ??
                        null,
                    )
                  }
                  required
                >
                  <option value="">Alege restaurantul</option>
                  {options.map((item) => (
                    <option key={item.id} value={item.id}>
                      {item.name}
                      {item.city ? ` · ${item.city}` : ""}
                    </option>
                  ))}
                </SelectField>
              </label>
              {directoryLoading && (
                <p role="status">Se încarcă restaurantele…</p>
              )}
              {directoryError && (
                <p className="error" role="alert">
                  {directoryError}
                </p>
              )}
              <div className="dining-inline-actions">
                {hasMoreRestaurants && (
                  <button
                    type="button"
                    className="text-button"
                    disabled={directoryLoading}
                    onClick={onMoreRestaurants}
                  >
                    Mai multe restaurante
                  </button>
                )}
                <button
                  type="button"
                  className="text-button"
                  onClick={() => setNewRestaurant(true)}
                >
                  <Plus size={17} aria-hidden="true" /> Adaugă restaurant
                </button>
              </div>
            </div>
          )}
          <div className="dining-form-grid">
            <DatePicker
              label="Data"
              value={date}
              onChange={setDate}
              min="1900-01-01"
              max="2100-12-31"
            />
            <label className="field">
              Tipul mesei
              <SelectField
                value={mealType}
                onChange={(event) =>
                  setMealType(event.target.value as keyof typeof mealTypes)
                }
              >
                {Object.entries(mealTypes).map(([value, label]) => (
                  <option key={value} value={value}>
                    {label}
                  </option>
                ))}
              </SelectField>
            </label>
          </div>
          <div className="dining-dish-drafts">
            {!canRate && (
              <p className="notice">
                Notele se pot adăuga începând cu ziua vizitei.
              </p>
            )}
            {dishes.map((dish, index) => (
              <fieldset className="dining-dish-draft" key={dish.key}>
                <legend>Preparat {index + 1}</legend>
                <div className="dining-dish-name">
                  <label className="field">
                    Nume preparat
                    <input
                      ref={index === dishes.length - 1 ? titleInput : undefined}
                      value={dish.title}
                      onChange={(event) =>
                        changeDish(dish.key, { title: event.target.value })
                      }
                      required
                      maxLength={160}
                    />
                  </label>
                  {dishes.length > 1 && (
                    <button
                      type="button"
                      className="icon-button"
                      aria-label={`Elimină preparatul ${index + 1}`}
                      onClick={() => {
                        setDishes((current) =>
                          current.filter((item) => item.key !== dish.key),
                        );
                        window.requestAnimationFrame(() =>
                          titleInput.current?.focus(),
                        );
                      }}
                    >
                      <Trash2 size={18} aria-hidden="true" />
                    </button>
                  )}
                </div>
                <div className="dining-form-grid">
                  <label className="field">
                    Categorie
                    <SelectField
                      value={dish.category}
                      onChange={(event) =>
                        changeDish(dish.key, {
                          category: event.target.value as DishCategory,
                        })
                      }
                    >
                      {Object.entries(dishCategories).map(([value, label]) => (
                        <option key={value} value={value}>
                          {label}
                        </option>
                      ))}
                    </SelectField>
                  </label>
                  <ScorePicker
                    value={dish.score}
                    onChange={(value) => changeDish(dish.key, { score: value })}
                    label={`Nota preparatului ${index + 1} (1–10)`}
                    disabled={!canRate}
                  />
                </div>
                {canRate && dish.score !== null && (
                  <label className="field">
                    Observații despre preparat (opțional)
                    <textarea
                      rows={2}
                      maxLength={1000}
                      value={dish.comment}
                      onChange={(event) =>
                        changeDish(dish.key, { comment: event.target.value })
                      }
                    />
                  </label>
                )}
              </fieldset>
            ))}
          </div>
          <button
            type="button"
            className="button secondary"
            disabled={dishes.length >= 20}
            onClick={() => {
              const key = nextKey.current++;
              setDishes((current) => [...current, freshDish(key)]);
              window.requestAnimationFrame(() =>
                titleInput.current?.focus({ preventScroll: false }),
              );
            }}
          >
            <Plus size={18} aria-hidden="true" /> Adaugă preparat
          </button>
          {dishes.length >= 20 && (
            <p className="dining-limit">Maximum 20 de preparate la o vizită.</p>
          )}
          <ScorePicker
            label="Nota restaurantului (1–10)"
            value={score}
            onChange={setScore}
            disabled={!canRate}
          />
          {canRate && score !== null && (
            <label className="field">
              Observații despre restaurant (opțional)
              <textarea
                rows={2}
                maxLength={1000}
                value={comment}
                onChange={(event) => setComment(event.target.value)}
              />
            </label>
          )}
          <label className="field">
            Acces
            <SelectField
              value={visibility}
              onChange={(event) =>
                setVisibility(event.target.value as "private" | "family")
              }
            >
              <option value="family">Familia mea</option>
              <option value="private">Doar eu</option>
            </SelectField>
            <small>
              {visibility === "family"
                ? "Membrii activi ai familiei văd vizita, preparatele și evaluările."
                : "Numai tu vezi vizita, preparatele și evaluările."}
            </small>
          </label>
        </fieldset>
        <div className="dining-form-actions">
          <button
            type="button"
            className="button secondary"
            disabled={busy}
            onClick={onClose}
          >
            Anulează
          </button>
          <button className="button primary" disabled={busy}>
            {busy ? "Se salvează…" : "Salvează"}
          </button>
        </div>
      </form>
    </Modal>
  );
}
