import { useEffect, useRef, useState, type FormEvent } from "react";
import {
  CalendarDays,
  ChevronLeft,
  ChevronRight,
  RefreshCw,
  ThumbsDown,
  ThumbsUp,
  UtensilsCrossed,
} from "lucide-react";
import {
  api,
  ApiError,
  initials,
  visibilityLabel,
  type Family,
} from "../../shared/api";
import { bucharestToday, dateLabel } from "../../shared/calendarDates";
import { Modal } from "../../shared/Modal";
import { KitchenPhoto } from "./KitchenPhoto";
import {
  mealTypes,
  type Meal,
  type MealList,
  type MealDetailData,
} from "./mealTypes";
import type { Recipe } from "./recipeTypes";
import "./meals.css";

function VoteCounts({ meal }: { meal: Meal }) {
  return (
    <div className="meal-vote-counts">
      <span>
        <ThumbsUp size={15} aria-hidden="true" />
        Mi-a plăcut: {meal.liked_count}
      </span>
      <span>
        <ThumbsDown size={15} aria-hidden="true" />
        Nu mi-a plăcut: {meal.disliked_count}
      </span>
    </div>
  );
}

function moveDay(date: string, delta: number): string {
  const value = new Date(`${date}T12:00:00Z`);
  value.setUTCDate(value.getUTCDate() + delta);
  return value.toISOString().slice(0, 10);
}

export function Meals({
  active,
  family,
  requestedRecipe,
  onRecipeConsumed,
  onChooseRecipe,
  onRecipe,
  onExpired,
}: {
  active: boolean;
  family: Family;
  requestedRecipe: Recipe | null;
  onRecipeConsumed: () => void;
  onChooseRecipe: () => void;
  onRecipe: (recipe: Recipe) => void;
  onExpired: () => void;
}) {
  const base = `/families/${family.id}/meals`;
  const [date, setDate] = useState(bucharestToday);
  const [items, setItems] = useState<Meal[]>([]);
  const [page, setPage] = useState(1);
  const [hasMore, setHasMore] = useState(false);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState("");
  const [revision, setRevision] = useState(0);
  const [selected, setSelected] = useState<Meal | MealDetailData | null>(null);
  const [notice, setNotice] = useState("");
  const loadedPosition = useRef("");
  useEffect(() => {
    if (!active) return;
    const controller = new AbortController();
    setLoading(true);
    setError("");
    const position = `${base}?date=${date}&page=${page}`;
    if (loadedPosition.current !== position) setItems([]);
    api<MealList>(position, {
      signal: controller.signal,
    })
      .then((data) => {
        if (controller.signal.aborted) return;
        loadedPosition.current = position;
        setItems(data.items);
        setHasMore(data.has_more);
        setSelected((current) =>
          current && data.items.some((item) => item.id === current.id)
            ? current
            : null,
        );
      })
      .catch((e) => {
        if (controller.signal.aborted) return;
        setItems([]);
        setSelected(null);
        if (e instanceof ApiError && [401, 403, 404, 419].includes(e.status))
          onExpired();
        else setError("Mesele nu au putut fi încărcate. Reîncearcă.");
      })
      .finally(() => {
        if (!controller.signal.aborted) setLoading(false);
      });
    return () => controller.abort();
  }, [active, base, date, page, revision]);
  useEffect(() => {
    if (!active) return;
    const timer = window.setInterval(() => {
      if (document.visibilityState === "visible")
        setRevision((value) => value + 1);
    }, 60000);
    return () => window.clearInterval(timer);
  }, [active]);
  function chooseDate(value: string) {
    if (value >= "1900-01-01" && value <= "2100-12-31") {
      setDate(value);
      setPage(1);
    }
  }
  function changed(meal: MealDetailData) {
    setSelected(meal);
    setItems((current) =>
      current.map((item) => (item.id === meal.id ? meal : item)),
    );
  }
  return (
    <>
      <div className="meal-day-controls">
        <button
          className="icon-button"
          aria-label="Ziua anterioară"
          disabled={date === "1900-01-01"}
          onClick={() => chooseDate(moveDay(date, -1))}
        >
          <ChevronLeft size={20} />
        </button>
        <label className="meal-day-picker">
          <CalendarDays size={17} aria-hidden="true" />
          <input
            type="date"
            aria-label="Ziua meselor"
            value={date}
            min="1900-01-01"
            max="2100-12-31"
            onChange={(e) => chooseDate(e.target.value)}
          />
        </label>
        <button
          className="icon-button"
          aria-label="Ziua următoare"
          disabled={date === "2100-12-31"}
          onClick={() => chooseDate(moveDay(date, 1))}
        >
          <ChevronRight size={20} />
        </button>
        <button
          className="button secondary"
          onClick={() => chooseDate(bucharestToday())}
        >
          Azi
        </button>
      </div>
      {notice && (
        <p className="notice" role="status">
          {notice}
        </p>
      )}
      <section aria-labelledby="meals-title" aria-busy={loading}>
        <div className="section-header">
          <h2 id="meals-title">
            {dateLabel(date, {
              weekday: "long",
              day: "numeric",
              month: "long",
            })}
          </h2>
          <button
            className="icon-button"
            aria-label="Actualizează mesele"
            disabled={loading}
            onClick={() => setRevision((value) => value + 1)}
          >
            <RefreshCw size={18} />
          </button>
        </div>
        {loading && !items.length ? (
          <p className="loading-text" role="status">
            Se încarcă mesele…
          </p>
        ) : error ? (
          <div className="empty">
            <p className="error" role="alert">
              {error}
            </p>
            <button
              className="button secondary"
              onClick={() => setRevision((value) => value + 1)}
            >
              Reîncearcă
            </button>
          </div>
        ) : !items.length ? (
          <div className="empty">
            <UtensilsCrossed size={34} aria-hidden="true" />
            <h3>Nicio masă în această zi</h3>
            {family.role !== "limited" && (
              <button className="button primary" onClick={onChooseRecipe}>
                Alege o rețetă
              </button>
            )}
          </div>
        ) : (
          <>
            <div className="meal-list">
              {items.map((meal) => (
                <button
                  className="meal-row"
                  key={meal.id}
                  onClick={() => setSelected(meal)}
                  aria-label={`Deschide masa ${meal.dish_title}`}
                >
                  {meal.photo_status === "ready" ? (
                    <KitchenPhoto resource={meal} base={base} />
                  ) : (
                    <span className="meal-dish-icon">
                      <UtensilsCrossed size={23} aria-hidden="true" />
                    </span>
                  )}
                  <div className="meal-row-body">
                    <span className="meal-type">
                      {mealTypes[meal.meal_type]} ·{" "}
                      {visibilityLabel[meal.visibility]}
                    </span>
                    <h3>{meal.dish_title}</h3>
                    <VoteCounts meal={meal} />
                  </div>
                  <ChevronRight
                    className="meal-row-arrow"
                    size={18}
                    aria-hidden="true"
                  />
                </button>
              ))}
            </div>
            {(page > 1 || hasMore) && (
              <div className="recipe-pagination">
                <button
                  className="button secondary"
                  disabled={page === 1}
                  onClick={() => setPage((value) => value - 1)}
                >
                  Înapoi
                </button>
                <span>Pagina {page}</span>
                <button
                  className="button secondary"
                  disabled={!hasMore}
                  onClick={() => setPage((value) => value + 1)}
                >
                  Înainte
                </button>
              </div>
            )}
          </>
        )}
      </section>
      {requestedRecipe && (
        <MealForm
          recipe={requestedRecipe}
          base={base}
          onClose={onRecipeConsumed}
          onExpired={onExpired}
          onSaved={(meal) => {
            onRecipeConsumed();
            setDate(meal.meal_date);
            setPage(1);
            setNotice("Masa a fost adăugată.");
            setRevision((value) => value + 1);
            setSelected(meal);
          }}
        />
      )}
      {selected && (
        <MealDetail
          key={selected.id}
          meal={selected}
          base={base}
          onClose={() => setSelected(null)}
          onChanged={changed}
          onRecipe={(recipe) => {
            setSelected(null);
            onRecipe(recipe);
          }}
          onExpired={onExpired}
        />
      )}
    </>
  );
}

function MealForm({
  recipe,
  base,
  onClose,
  onSaved,
  onExpired,
}: {
  recipe: Recipe;
  base: string;
  onClose: () => void;
  onSaved: (meal: MealDetailData) => void;
  onExpired: () => void;
}) {
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState("");
  const [visibility, setVisibility] = useState("family");
  const dateInput = useRef<HTMLInputElement>(null);
  const request = useRef<AbortController | null>(null);
  useEffect(() => {
    dateInput.current?.focus();
    return () => request.current?.abort();
  }, []);
  async function save(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    if (busy) return;
    const values = new FormData(event.currentTarget);
    const controller = new AbortController();
    request.current = controller;
    setBusy(true);
    setError("");
    try {
      const meal = await api<MealDetailData>(base, {
        method: "POST",
        signal: controller.signal,
        body: JSON.stringify({
          recipe_id: recipe.id,
          recipe_version_id: recipe.recipe_version_id,
          meal_date: values.get("meal_date"),
          meal_type: values.get("meal_type"),
          visibility,
        }),
      });
      if (!controller.signal.aborted) onSaved(meal);
    } catch (e) {
      if (controller.signal.aborted) return;
      if (e instanceof ApiError && [401, 403, 404, 419].includes(e.status)) {
        onClose();
        onExpired();
      } else
        setError(
          e instanceof ApiError
            ? e.message
            : "Masa nu a putut fi adăugată. Reîncearcă.",
        );
    } finally {
      if (!controller.signal.aborted) setBusy(false);
    }
  }
  return (
    <Modal title="Adaugă masă" onClose={onClose}>
      <p className="meal-recipe-name">{recipe.title}</p>
      <form onSubmit={(e) => void save(e)}>
        <label className="field">
          Data
          <input
            name="meal_date"
            ref={dateInput}
            type="date"
            required
            min="1900-01-01"
            max="2100-12-31"
            defaultValue={bucharestToday()}
          />
        </label>
        <label className="field">
          Masă
          <select name="meal_type" defaultValue="lunch">
            {Object.entries(mealTypes).map(([key, label]) => (
              <option key={key} value={key}>
                {label}
              </option>
            ))}
          </select>
        </label>
        <label className="field">
          Acces
          <select
            value={visibility}
            onChange={(e) => setVisibility(e.target.value)}
            aria-describedby="meal-access-hint"
          >
            <option value="family">Familia mea</option>
            <option value="private">Doar eu</option>
          </select>
          <small id="meal-access-hint">
            {visibility === "family"
              ? "Membrii activi văd masa, voturile și comentariile. Rețeta și fotografia își păstrează accesul."
              : "Numai tu poți vedea masa, votul și comentariul tău."}
          </small>
        </label>
        {error && (
          <p className="error" role="alert">
            {error}
          </p>
        )}
        <button className="button primary full" disabled={busy}>
          {busy ? "Se salvează…" : "Salvează"}
        </button>
      </form>
    </Modal>
  );
}

export function MealDetail({
  meal,
  base,
  onClose,
  onChanged,
  onRecipe,
  onExpired,
}: {
  meal: Meal | MealDetailData;
  base: string;
  onClose: () => void;
  onChanged: (meal: MealDetailData) => void;
  onRecipe: (recipe: Recipe) => void;
  onExpired: () => void;
}) {
  const [data, setData] = useState<MealDetailData | null>(
    "feedback" in meal ? meal : null,
  );
  const [loading, setLoading] = useState(!("feedback" in meal));
  const [loadError, setLoadError] = useState("");
  const [error, setError] = useState("");
  const [conflict, setConflict] = useState(false);
  const [status, setStatus] = useState("");
  const [busy, setBusy] = useState(false);
  const [liked, setLiked] = useState<boolean | null>(
    meal.my_vote?.liked ?? null,
  );
  const [comment, setComment] = useState(meal.my_vote?.comment ?? "");
  const [voteVersion, setVoteVersion] = useState(meal.my_vote?.version ?? 0);
  const request = useRef<AbortController | null>(null);
  function receive(value: MealDetailData, resetDraft: boolean) {
    setData(value);
    onChanged(value);
    if (resetDraft) {
      setStatus("");
      setLiked(value.my_vote?.liked ?? null);
      setComment(value.my_vote?.comment ?? "");
      setVoteVersion(value.my_vote?.version ?? 0);
      setConflict(false);
      setError("");
    }
  }
  useEffect(() => {
    if ("feedback" in meal) setData(meal);
  }, [meal]);
  function expired(e: unknown): boolean {
    if (e instanceof ApiError && [401, 403, 404, 419].includes(e.status)) {
      onClose();
      onExpired();
      return true;
    }
    return false;
  }
  async function load(page = 1, resetDraft = true) {
    request.current?.abort();
    const controller = new AbortController();
    request.current = controller;
    setLoading(true);
    setLoadError("");
    try {
      const value = await api<MealDetailData>(
        `${base}/${meal.id}?page=${page}`,
        { signal: controller.signal },
      );
      if (!controller.signal.aborted) receive(value, resetDraft);
    } catch (e) {
      if (!controller.signal.aborted && !expired(e))
        setLoadError("Masa nu a putut fi reîncărcată. Reîncearcă.");
    } finally {
      if (!controller.signal.aborted) setLoading(false);
    }
  }
  useEffect(() => {
    if (!("feedback" in meal)) void load();
    return () => request.current?.abort();
  }, [meal.id]);
  async function save(event?: FormEvent, withdraw = false) {
    event?.preventDefault();
    if (busy || loading || conflict || (!withdraw && liked === null)) return;
    request.current?.abort();
    const controller = new AbortController();
    request.current = controller;
    setBusy(true);
    setError("");
    try {
      const value = await api<MealDetailData>(`${base}/${meal.id}/vote`, {
        method: withdraw ? "DELETE" : "PUT",
        signal: controller.signal,
        headers: { "If-Match": `"${voteVersion}"` },
        ...(withdraw
          ? {}
          : {
              body: JSON.stringify({ liked, comment: comment.trim() || null }),
            }),
      });
      if (!controller.signal.aborted) {
        receive(value, true);
        setStatus(
          withdraw
            ? "Votul și comentariul au fost retrase."
            : "Votul a fost salvat.",
        );
      }
    } catch (e) {
      if (controller.signal.aborted || expired(e)) return;
      if (e instanceof ApiError && e.status === 412) {
        setConflict(true);
        setError(
          "Votul tău s-a schimbat. Reîncarcă masa; votul și comentariul nesalvate vor fi înlocuite.",
        );
      } else
        setError(
          e instanceof ApiError
            ? e.message
            : "Votul nu a putut fi salvat. Reîncearcă.",
        );
    } finally {
      if (!controller.signal.aborted) setBusy(false);
    }
  }
  const current = data ?? meal;
  async function openRecipe() {
    if (busy || loading) return;
    const controller = new AbortController();
    request.current = controller;
    setBusy(true);
    setError("");
    try {
      const value = await api<Recipe>(`${base}/${meal.id}/recipe`, {
        signal: controller.signal,
      });
      if (!controller.signal.aborted) onRecipe(value);
    } catch (e) {
      if (!controller.signal.aborted && !expired(e))
        setError("Rețeta nu a putut fi încărcată. Reîncearcă.");
    } finally {
      if (!controller.signal.aborted) setBusy(false);
    }
  }
  return (
    <Modal title={current.dish_title} onClose={onClose} wide>
      <div className="meal-detail">
        {current.photo_status === "ready" && (
          <KitchenPhoto resource={current} base={base} />
        )}
        <p className="meal-detail-meta">
          {dateLabel(current.meal_date, {
            day: "numeric",
            month: "long",
            year: "numeric",
          })}{" "}
          · {mealTypes[current.meal_type]} ·{" "}
          {visibilityLabel[current.visibility]}
        </p>
        {current.photo_is_example && current.photo_status === "ready" && (
          <p className="meal-example">Imagine de exemplu, generată.</p>
        )}
        <div className="meal-counts-toolbar">
          <VoteCounts meal={current} />
          <button
            className="icon-button"
            aria-label="Actualizează voturile"
            disabled={busy || loading || conflict}
            onClick={() => void load(data?.feedback_page ?? 1, false)}
          >
            <RefreshCw size={17} />
          </button>
        </div>
        <p className="recipe-author">Adăugată de {current.owner_name}</p>
        {current.recipe_available && (
          <button
            className="button secondary"
            disabled={busy || loading}
            onClick={() => void openRecipe()}
          >
            Rețeta folosită
          </button>
        )}
        {loading && (
          <p role="status" className="loading-text">
            Se încarcă voturile…
          </p>
        )}
        {loadError && (
          <div className="meal-reload">
            <p className="error" role="alert">
              {loadError}
            </p>
            <button className="button secondary" onClick={() => void load()}>
              Reîncearcă
            </button>
          </div>
        )}
        {current.voting_open ? (
          <form className="meal-vote-form" onSubmit={(e) => void save(e)}>
            <h3>Votul meu</h3>
            <div className="meal-vote-options">
              <button
                type="button"
                aria-pressed={liked === true}
                disabled={busy || loading || conflict || !!loadError}
                onClick={() => {
                  setLiked(true);
                  setStatus("");
                }}
              >
                <ThumbsUp size={18} aria-hidden="true" />
                Mi-a plăcut
              </button>
              <button
                type="button"
                aria-pressed={liked === false}
                disabled={busy || loading || conflict || !!loadError}
                onClick={() => {
                  setLiked(false);
                  setStatus("");
                }}
              >
                <ThumbsDown size={18} aria-hidden="true" />
                Nu mi-a plăcut
              </button>
            </div>
            <label className="field">
              Comentariu (opțional)
              <textarea
                rows={3}
                maxLength={1000}
                value={comment}
                disabled={busy || loading || conflict || !!loadError}
                onChange={(e) => {
                  setComment(e.target.value);
                  setStatus("");
                }}
                aria-describedby="meal-comment-hint"
              />
              <small id="meal-comment-hint">
                Maximum 1.000 de caractere. Vizibil persoanelor care au acces la
                masă.
              </small>
            </label>
            {error && (
              <p className="error" role="alert">
                {error}
              </p>
            )}
            {status && (
              <p className="notice" role="status">
                {status}
              </p>
            )}
            {conflict ? (
              <button
                type="button"
                className="button secondary"
                disabled={loading}
                onClick={() => void load()}
              >
                Reîncarcă masa
              </button>
            ) : (
              <div className="meal-vote-actions">
                <button
                  className="button primary"
                  disabled={liked === null || busy || loading || !!loadError}
                >
                  {busy ? "Se salvează…" : "Salvează votul"}
                </button>
                {current.my_vote && current.my_vote.liked !== null && (
                  <button
                    type="button"
                    className="button secondary"
                    disabled={busy || loading || !!loadError}
                    onClick={() => void save(undefined, true)}
                  >
                    Retrage votul
                  </button>
                )}
              </div>
            )}
          </form>
        ) : (
          <p className="notice">Poți vota începând cu ziua mesei.</p>
        )}
        <section className="meal-feedback">
          <h3>Voturi și comentarii</h3>
          {data && !data.feedback.length && (
            <p className="meal-no-feedback">Niciun vot încă.</p>
          )}
          {data?.feedback.map((vote) => (
            <article className="meal-feedback-row" key={vote.membership_id}>
              <span className="avatar" aria-hidden="true">
                {initials(vote.author_name)}
              </span>
              <div>
                <div className="meal-feedback-author">
                  <strong>{vote.author_name}</strong>
                  {vote.liked ? (
                    <ThumbsUp size={16} aria-label="Mi-a plăcut" />
                  ) : (
                    <ThumbsDown size={16} aria-label="Nu mi-a plăcut" />
                  )}
                </div>
                {vote.comment && <p>{vote.comment}</p>}
              </div>
            </article>
          ))}
          {data && (data.feedback_page > 1 || data.feedback_has_more) && (
            <div className="recipe-pagination">
              <button
                className="button secondary"
                disabled={data.feedback_page === 1 || loading || busy}
                onClick={() => void load(data.feedback_page - 1, false)}
              >
                Înapoi
              </button>
              <span>Pagina {data.feedback_page}</span>
              <button
                className="button secondary"
                disabled={!data.feedback_has_more || loading || busy}
                onClick={() => void load(data.feedback_page + 1, false)}
              >
                Înainte
              </button>
            </div>
          )}
        </section>
      </div>
    </Modal>
  );
}
