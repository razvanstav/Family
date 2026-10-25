import { useEffect, useRef, useState, type FormEvent } from "react";
import { api, ApiError, errorMessage } from "../../shared/api";
import { ScorePicker } from "./ScorePicker";
import { diningScore, diningRatingCount } from "./diningTypes";
import type { MealDetailData } from "./mealTypes";

export function MealRatingPanel({
  meal,
  base,
  onChanged,
  onExpired,
}: {
  meal: MealDetailData;
  base: string;
  onChanged: (meal: MealDetailData) => void;
  onExpired: () => void;
}) {
  const [score, setScore] = useState(meal.my_rating?.score ?? null);
  const [comment, setComment] = useState(meal.my_rating?.comment ?? "");
  const [version, setVersion] = useState(meal.my_rating?.version ?? 0);
  const [busy, setBusy] = useState(false);
  const [stale, setStale] = useState(false);
  const [error, setError] = useState("");
  const [notice, setNotice] = useState("");
  const request = useRef<AbortController | null>(null);
  const pending = useRef(false);
  useEffect(() => () => request.current?.abort(), []);
  function receive(value: MealDetailData, reset: boolean) {
    onChanged(value);
    if (reset) {
      setScore(value.my_rating?.score ?? null);
      setComment(value.my_rating?.comment ?? "");
      setVersion(value.my_rating?.version ?? 0);
      setStale(false);
    }
  }
  async function act(mode: "save" | "withdraw" | "reload" | "page", page = 1) {
    if (pending.current) return;
    pending.current = true;
    const controller = new AbortController();
    request.current = controller;
    setBusy(true);
    setError("");
    setNotice("");
    const reading = mode === "reload" || mode === "page";
    try {
      const value = await api<MealDetailData>(
        `${base}/${meal.id}${reading ? `?page=${page}` : "/rating"}`,
        {
          method: reading ? "GET" : mode === "withdraw" ? "DELETE" : "PUT",
          signal: controller.signal,
          ...(!reading ? { headers: { "If-Match": `"${version}"` } } : {}),
          ...(mode === "save"
            ? {
                body: JSON.stringify({
                  score,
                  comment: comment.trim() || null,
                }),
              }
            : {}),
        },
      );
      if (controller.signal.aborted) return;
      receive(value, mode !== "page");
      if (!reading)
        setNotice(
          mode === "save"
            ? "Nota a fost salvată."
            : "Nota și observațiile au fost retrase.",
        );
    } catch (cause) {
      if (controller.signal.aborted) return;
      if (
        cause instanceof ApiError &&
        [401, 403, 404, 419].includes(cause.status)
      )
        onExpired();
      else {
        if (cause instanceof ApiError && cause.status === 412) setStale(true);
        setError(errorMessage(cause));
      }
    } finally {
      pending.current = false;
      if (!controller.signal.aborted) setBusy(false);
    }
  }
  return (
    <section className="meal-rating-panel" aria-label="Notele preparatului">
      <div className="section-header">
        <h3>Note</h3>
        {meal.rating_count > 0 && meal.rating_average !== null && (
          <span className="meal-rating-summary">
            {diningScore(meal.rating_average)} ·{" "}
            {diningRatingCount(meal.rating_count)}
          </span>
        )}
      </div>
      {meal.voting_open && (
        <form
          onSubmit={(event: FormEvent) => {
            event.preventDefault();
            void act("save");
          }}
        >
          <ScorePicker
            label="Nota ta"
            value={score}
            onChange={setScore}
            disabled={busy || stale}
          />
          <label className="field">
            Observații (opțional)
            <textarea
              value={comment}
              maxLength={1000}
              rows={2}
              disabled={busy || stale}
              onChange={(event) => setComment(event.target.value)}
            />
          </label>
          <div className="meal-vote-actions">
            <button
              className="button primary"
              disabled={busy || stale || score === null}
            >
              Salvează nota
            </button>
            {meal.my_rating?.score != null && (
              <button
                type="button"
                className="button secondary"
                disabled={busy || stale}
                onClick={() => void act("withdraw")}
              >
                Retrage nota
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
        <>
          <p className="notice">
            Reîncărcarea înlocuiește nota și observațiile nesalvate.
          </p>
          <button
            className="button secondary"
            disabled={busy}
            onClick={() => void act("reload")}
          >
            Reîncarcă nota
          </button>
        </>
      )}
      {notice && (
        <p className="notice" role="status">
          {notice}
        </p>
      )}
      {(meal.ratings ?? []).map((rating) => (
        <article className="meal-rating-row" key={rating.membership_id}>
          <strong>{rating.author_name}</strong>
          <span>{rating.score}/10</span>
          {rating.comment && <p>{rating.comment}</p>}
        </article>
      ))}
      {(meal.rating_page > 1 || meal.rating_has_more) && (
        <div className="recipe-pagination">
          <button
            className="button secondary"
            disabled={busy || meal.rating_page === 1}
            onClick={() => void act("page", meal.rating_page - 1)}
          >
            Înapoi
          </button>
          <span>Pagina {meal.rating_page}</span>
          <button
            className="button secondary"
            disabled={busy || !meal.rating_has_more}
            onClick={() => void act("page", meal.rating_page + 1)}
          >
            Înainte
          </button>
        </div>
      )}
    </section>
  );
}
