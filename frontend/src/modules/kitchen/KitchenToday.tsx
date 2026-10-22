import { useEffect, useState } from "react";
import { ChevronRight, ThumbsUp, UtensilsCrossed } from "lucide-react";
import { api, ApiError, type Family } from "../../shared/api";
import { bucharestToday } from "../../shared/calendarDates";
import { KitchenPhoto } from "./KitchenPhoto";
import { MealViewer } from "./MealViewer";
import { mealTypes, type Meal, type MealList } from "./mealTypes";

export function KitchenToday({
  family,
  actorId,
  revision,
  onAll,
  onExpired,
}: {
  family: Family;
  actorId: number;
  revision: number;
  onAll: () => void;
  onExpired: () => void;
}) {
  const base = `/families/${family.id}/meals`;
  const today = bucharestToday();
  const [items, setItems] = useState<Meal[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState("");
  const [refresh, setRefresh] = useState(0);
  const [selected, setSelected] = useState<string | null>(null);
  useEffect(() => {
    const controller = new AbortController();
    setLoading(true);
    setError("");
    api<MealList>(`${base}?date=${today}&page=1`, { signal: controller.signal })
      .then((data) => {
        if (!controller.signal.aborted) setItems(data.items.slice(0, 3));
      })
      .catch((e: unknown) => {
        if (controller.signal.aborted) return;
        setItems([]);
        if (e instanceof ApiError && [401, 403, 404, 419].includes(e.status))
          onExpired();
        else setError("Mesele nu au putut fi încărcate.");
      })
      .finally(() => {
        if (!controller.signal.aborted) setLoading(false);
      });
    return () => controller.abort();
  }, [base, today, revision, refresh]);
  return (
    <section
      className="kitchen-today"
      aria-labelledby="kitchen-today-title"
      aria-busy={loading}
    >
      <div className="section-header">
        <h2 id="kitchen-today-title">Mesele de azi</h2>
        <button
          className="text-button"
          onClick={onAll}
          aria-label="Vezi toate mesele"
        >
          <span>Vezi toate</span> <ChevronRight size={17} />
        </button>
      </div>
      {loading && !items.length ? (
        <p className="loading-text" role="status">
          Se încarcă mesele…
        </p>
      ) : error ? (
        <div className="error" role="alert">
          {error}
          <button
            className="text-button"
            onClick={() => setRefresh((value) => value + 1)}
          >
            Reîncearcă
          </button>
        </div>
      ) : !items.length ? (
        <div className="kitchen-today-empty">
          <UtensilsCrossed size={22} aria-hidden="true" />
          <span>Nicio masă adăugată azi.</span>
        </div>
      ) : (
        <ul className="kitchen-today-list">
          {items.map((meal) => (
            <li key={meal.id}>
              <button
                className="kitchen-today-row"
                onClick={() => setSelected(meal.id)}
                aria-label={`Deschide masa ${meal.dish_title}`}
              >
                {meal.photo_status === "ready" ? (
                  <KitchenPhoto resource={meal} base={base} />
                ) : (
                  <span className="kitchen-today-icon">
                    <UtensilsCrossed size={23} aria-hidden="true" />
                  </span>
                )}
                <span className="kitchen-today-copy">
                  <strong>{meal.dish_title}</strong>
                  <small>
                    {mealTypes[meal.meal_type]}
                    {meal.photo_is_example ? " · Exemplu" : ""}
                  </small>
                </span>
                <span
                  className="kitchen-today-votes"
                  aria-label={`Mi-a plăcut: ${meal.liked_count}`}
                >
                  <ThumbsUp size={15} aria-hidden="true" />
                  {meal.liked_count}
                </span>
                <ChevronRight
                  size={16}
                  className="kitchen-today-arrow"
                  aria-hidden="true"
                />
              </button>
            </li>
          ))}
        </ul>
      )}
      {selected && (
        <MealViewer
          familyId={family.id}
          actorId={actorId}
          mealId={selected}
          onClose={() => {
            setSelected(null);
            setRefresh((value) => value + 1);
          }}
          onUnavailable={() => {
            setSelected(null);
            setItems([]);
            setRefresh((value) => value + 1);
          }}
        />
      )}
    </section>
  );
}
