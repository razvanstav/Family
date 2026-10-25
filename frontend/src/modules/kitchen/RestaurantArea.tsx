import { useEffect, useRef, useState, type FormEvent } from "react";
import {
  ChevronLeft,
  ChevronRight,
  LockKeyhole,
  MapPin,
  Plus,
  Star,
  Store,
  Trophy,
  Users,
} from "lucide-react";
import { api, ApiError, errorMessage, type Family } from "../../shared/api";
import { bucharestToday, dateLabel } from "../../shared/calendarDates";
import { Modal } from "../../shared/Modal";
import { observePageResume } from "../../shared/pageResume";
import { SelectMenu } from "../../shared/SelectMenu";
import { DiningVisitForm } from "./DiningVisitForm";
import { ScorePicker } from "./ScorePicker";
import { mealTypes } from "./mealTypes";
import {
  dishCategories,
  diningRatingCount,
  diningScore,
  type DiningPage,
  type DiningScope,
  type DiningVisit,
  type DiningVisitDetail,
  type DishCategory,
  type DishRanking,
  type Restaurant,
  type RestaurantRanking,
} from "./diningTypes";
import "./dining.css";

type View = "history" | "rankings";
type RankingType = "dishes" | "restaurants";
type Result = DiningVisit | DishRanking | RestaurantRanking;
const pageLimit = 10000;

export function RestaurantArea({
  family,
  onExpired,
  onOpenMeal,
  requestedNewVisit = false,
  onNewVisitOpened,
  refreshToken = 0,
}: {
  family: Family;
  onExpired: () => void;
  onOpenMeal?: (id: string, date: string) => void;
  requestedNewVisit?: boolean;
  onNewVisitOpened?: () => void;
  refreshToken?: number;
}) {
  const base = `/families/${family.id}`;
  const [view, setView] = useState<View>("history");
  const [rankingType, setRankingType] = useState<RankingType>("dishes");
  const [historyScope, setHistoryScope] = useState<DiningScope>("family");
  const [rankingScope, setRankingScope] = useState<DiningScope>("mine");
  const scope = view === "history" ? historyScope : rankingScope;
  const [filterRestaurant, setFilterRestaurant] = useState<Restaurant | null>(
    null,
  );
  const [category, setCategory] = useState<"" | DishCategory>("");
  const [page, setPage] = useState(1);
  const [revision, setRevision] = useState(0);
  const [data, setData] = useState<DiningPage<Result> | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState("");
  const [notice, setNotice] = useState("");
  const [adding, setAdding] = useState(false);
  const [selected, setSelected] = useState<
    DiningVisit | DiningVisitDetail | null
  >(null);
  const [requestedVisitId, setRequestedVisitId] = useState<string | null>(null);
  const [detailLoading, setDetailLoading] = useState(false);
  const [detailError, setDetailError] = useState("");
  const [detailRevision, setDetailRevision] = useState(0);
  const detailRequest = useRef<AbortController | null>(null);
  const [restaurants, setRestaurants] = useState<Restaurant[]>([]);
  const [directoryQuery, setDirectoryQuery] = useState("");
  const [directorySearch, setDirectorySearch] = useState("");
  const [directoryPage, setDirectoryPage] = useState(1);
  const [directoryRevision, setDirectoryRevision] = useState(0);
  const [directoryMore, setDirectoryMore] = useState(false);
  const [directoryLoading, setDirectoryLoading] = useState(true);
  const [directoryError, setDirectoryError] = useState("");
  const loadedPosition = useRef("");
  const selectedId = selected?.id ?? requestedVisitId;
  const params = new URLSearchParams({ scope, page: String(page) });
  if (filterRestaurant) params.set("restaurant_id", filterRestaurant.id);
  if (view === "rankings") {
    params.set("type", rankingType);
    if (rankingType === "dishes" && category) params.set("category", category);
  }
  const endpoint = `${base}/${view === "history" ? "dining-visits" : "dining-rankings"}?${params}`;
  const current = loadedPosition.current === endpoint ? data : null;
  const canCreate = family.role !== "limited";

  function unavailable(cause: unknown) {
    if (
      cause instanceof ApiError &&
      [401, 403, 404, 419].includes(cause.status)
    ) {
      setData(null);
      setSelected(null);
      setRequestedVisitId(null);
      setAdding(false);
      onExpired();
    }
  }
  useEffect(() => {
    if (!requestedNewVisit) return;
    if (canCreate) setAdding(true);
    onNewVisitOpened?.();
  }, [requestedNewVisit, canCreate]);
  useEffect(() => {
    return observePageResume(document, window, () => {
      setRevision((value) => value + 1);
      if (selectedId) setDetailRevision((value) => value + 1);
    });
  }, [selectedId]);
  useEffect(() => {
    const timer = window.setTimeout(() => {
      setDirectorySearch(directoryQuery.trim());
      setDirectoryPage(1);
    }, 250);
    return () => window.clearTimeout(timer);
  }, [directoryQuery]);
  useEffect(() => {
    const controller = new AbortController();
    setDirectoryLoading(true);
    setDirectoryError("");
    const filters = new URLSearchParams({ page: String(directoryPage) });
    if (directorySearch) filters.set("q", directorySearch);
    api<DiningPage<Restaurant>>(`${base}/restaurants?${filters}`, {
      signal: controller.signal,
    })
      .then((result) => {
        if (controller.signal.aborted) return;
        setRestaurants((previous) =>
          directoryPage === 1
            ? result.items
            : [
                ...previous,
                ...result.items.filter(
                  (item) =>
                    !previous.some((existing) => existing.id === item.id),
                ),
              ],
        );
        setDirectoryMore(result.has_more);
      })
      .catch((cause: unknown) => {
        if (controller.signal.aborted) return;
        setDirectoryError(errorMessage(cause));
        unavailable(cause);
      })
      .finally(() => {
        if (!controller.signal.aborted) setDirectoryLoading(false);
      });
    return () => controller.abort();
  }, [base, directorySearch, directoryPage, directoryRevision, refreshToken]);
  useEffect(() => {
    const controller = new AbortController();
    setLoading(true);
    setError("");
    api<DiningPage<Result>>(endpoint, { signal: controller.signal })
      .then((result) => {
        if (controller.signal.aborted) return;
        loadedPosition.current = endpoint;
        setData(result);
      })
      .catch((cause: unknown) => {
        if (controller.signal.aborted) return;
        setData(null);
        setError(errorMessage(cause));
        unavailable(cause);
      })
      .finally(() => {
        if (!controller.signal.aborted) setLoading(false);
      });
    return () => controller.abort();
  }, [endpoint, revision, refreshToken]);
  useEffect(() => {
    if (!selectedId) return;
    if (selected && "ratings" in selected && detailRevision === 0) return;
    const controller = new AbortController();
    detailRequest.current = controller;
    setDetailLoading(true);
    setDetailError("");
    api<DiningVisitDetail>(`${base}/dining-visits/${selectedId}`, {
      signal: controller.signal,
    })
      .then((result) => {
        if (!controller.signal.aborted) {
          setSelected(result);
          setRequestedVisitId(null);
        }
      })
      .catch((cause: unknown) => {
        if (controller.signal.aborted) return;
        setDetailError(errorMessage(cause));
        unavailable(cause);
      })
      .finally(() => {
        if (!controller.signal.aborted) setDetailLoading(false);
      });
    return () => {
      controller.abort();
      if (detailRequest.current === controller) detailRequest.current = null;
    };
  }, [base, selectedId, detailRevision, refreshToken]);

  function changeView(next: View) {
    setView(next);
    setPage(1);
    setNotice("");
  }
  function retryDirectory() {
    setDirectoryPage(1);
    setDirectoryRevision((value) => value + 1);
  }
  function retryResults() {
    setRevision((value) => value + 1);
    if (directoryError) retryDirectory();
  }
  function openVisit(visit: DiningVisit | DiningVisitDetail) {
    setDetailError("");
    setDetailRevision(0);
    setSelected(visit);
    setRequestedVisitId(null);
  }
  function closeVisit() {
    detailRequest.current?.abort();
    setSelected(null);
    setRequestedVisitId(null);
    setDetailError("");
    setDetailLoading(false);
  }
  const restaurantOptions =
    filterRestaurant &&
    !restaurants.some((item) => item.id === filterRestaurant.id)
      ? [filterRestaurant, ...restaurants]
      : restaurants;
  return (
    <div className="dining-area">
      <div className="dining-toolbar">
        <div
          className="dining-segments"
          role="group"
          aria-label="Vizualizare restaurante"
        >
          {(
            [
              ["history", "Istoric"],
              ["rankings", "Clasamente"],
            ] as const
          ).map(([value, label]) => (
            <button
              key={value}
              className={view === value ? "active" : ""}
              aria-pressed={view === value}
              onClick={() => changeView(value)}
            >
              {value === "history" ? (
                <Store size={18} aria-hidden="true" />
              ) : (
                <Trophy size={18} aria-hidden="true" />
              )}
              {label}
            </button>
          ))}
        </div>
        <SelectMenu
          label={view === "history" ? "Vizite" : "Evaluări"}
          value={scope}
          options={[
            {
              value: "mine",
              label: view === "history" ? "Vizitele mele" : "Notele mele",
            },
            { value: "family", label: "Familie" },
          ]}
          onChange={(value) => {
            if (view === "history") setHistoryScope(value as DiningScope);
            else setRankingScope(value as DiningScope);
            setPage(1);
          }}
        />
      </div>
      <div className="dining-filters">
        {view === "rankings" && (
          <SelectMenu
            label="Clasament"
            value={rankingType}
            options={[
              { value: "dishes", label: "Preparate" },
              { value: "restaurants", label: "Restaurante" },
            ]}
            onChange={(value) => {
              setRankingType(value as RankingType);
              setPage(1);
            }}
          />
        )}
        <SelectMenu
          label="Restaurant"
          value={filterRestaurant?.id ?? ""}
          options={[
            { value: "", label: "Toate restaurantele" },
            ...restaurantOptions.map((item) => ({
              value: item.id,
              label: `${item.name}${item.city ? ` · ${item.city}` : ""}`,
            })),
          ]}
          onChange={(value) => {
            setFilterRestaurant(
              restaurants.find((item) => item.id === value) ?? null,
            );
            setPage(1);
          }}
        />
        {view === "rankings" && rankingType === "dishes" && (
          <SelectMenu
            label="Categorie preparat"
            value={category}
            options={[
              { value: "", label: "Toate preparatele" },
              ...Object.entries(dishCategories).map(([value, label]) => ({
                value,
                label,
              })),
            ]}
            onChange={(value) => {
              setCategory(value as "" | DishCategory);
              setPage(1);
            }}
          />
        )}
        {directoryMore && (
          <button
            className="text-button"
            disabled={directoryLoading}
            onClick={() =>
              setDirectoryPage((value) => Math.min(value + 1, pageLimit))
            }
          >
            Mai multe restaurante
          </button>
        )}
      </div>
      {notice && (
        <p className="notice" role="status">
          {notice}
        </p>
      )}
      {directoryError && directoryError !== error && !adding && (
        <div className="notice">
          <p className="error" role="alert">
            {directoryError}
          </p>
          <button
            className="text-button"
            disabled={directoryLoading}
            onClick={retryDirectory}
          >
            Reîncearcă restaurantele
          </button>
        </div>
      )}
      {error ? (
        <div className="notice">
          <p className="error" role="alert">
            {error}
          </p>
          <button
            className="text-button"
            disabled={loading}
            onClick={retryResults}
          >
            Reîncearcă
          </button>
        </div>
      ) : loading && !current ? (
        <p className="dining-empty" role="status">
          Se încarcă {view === "history" ? "vizitele" : "clasamentul"}…
        </p>
      ) : !current?.items.length ? (
        <div className="dining-empty">
          {view === "history" ? (
            <Store size={28} aria-hidden="true" />
          ) : (
            <Trophy size={28} aria-hidden="true" />
          )}
          <p>
            {view === "history"
              ? "Nicio vizită în această selecție."
              : "Nicio evaluare în această selecție."}
          </p>
          {view === "history" && canCreate && (
            <button
              className="button secondary"
              onClick={() => setAdding(true)}
            >
              <Plus size={18} aria-hidden="true" />
              Adaugă vizită
            </button>
          )}
        </div>
      ) : (
        <>
          <ul
            className="dining-list"
            aria-label={
              view === "history" ? "Istoricul vizitelor" : "Clasament"
            }
            aria-busy={loading}
          >
            {current.items.map((item) => {
              if (view === "history") {
                const visit = item as DiningVisit;
                return (
                  <li key={visit.id}>
                    <button
                      className="dining-visit-row"
                      onClick={() => openVisit(visit)}
                    >
                      <span className="dining-place-icon">
                        <Store size={22} aria-hidden="true" />
                      </span>
                      <span className="dining-row-copy">
                        <strong>{visit.restaurant.name}</strong>
                        <span className="dining-row-meta">
                          <time dateTime={visit.meal_date}>
                            {dateLabel(visit.meal_date, {
                              day: "numeric",
                              month: "short",
                              year: "numeric",
                            })}
                          </time>{" "}
                          · {mealTypes[visit.meal_type]}
                          {visit.restaurant.city
                            ? ` · ${visit.restaurant.city}`
                            : ""}
                        </span>
                        <span className="dining-dish-preview">
                          {visit.dishes
                            .map((dish) => dish.dish_title)
                            .join(" · ")}
                        </span>
                        <span className="dining-row-meta">
                          {visit.owner_name} ·{" "}
                          {visit.visibility === "private" ? (
                            <>
                              <LockKeyhole size={12} aria-hidden="true" />
                              Doar eu
                            </>
                          ) : (
                            <>
                              <Users size={12} aria-hidden="true" />
                              Familie
                            </>
                          )}
                        </span>
                      </span>
                      <span className="dining-row-score">
                        {visit.rating_average === null ? (
                          <span className="dining-unrated">Fără notă</span>
                        ) : (
                          <>
                            <strong>
                              <Star size={15} aria-hidden="true" />
                              {diningScore(visit.rating_average)}
                            </strong>
                            <small>
                              {diningRatingCount(visit.rating_count)}
                            </small>
                          </>
                        )}
                        <ChevronRight size={18} aria-hidden="true" />
                      </span>
                    </button>
                  </li>
                );
              }
              if (rankingType === "dishes") {
                const dish = item as DishRanking;
                return (
                  <li
                    key={`${dish.restaurant?.id ?? "home"}:${dish.category}:${dish.dish_title}`}
                  >
                    <button
                      className="dining-ranking-row"
                      disabled={!onOpenMeal}
                      onClick={() =>
                        onOpenMeal?.(dish.meal_id, dish.last_eaten_on)
                      }
                    >
                      <span className="dining-place-icon">
                        <Trophy size={21} aria-hidden="true" />
                      </span>
                      <span className="dining-row-copy">
                        <strong>{dish.dish_title}</strong>
                        <span className="dining-row-meta">
                          {dish.restaurant?.name ?? "Acasă"}
                          {dish.restaurant?.city
                            ? ` · ${dish.restaurant.city}`
                            : ""}
                        </span>
                        <span className="dining-row-meta">
                          {dishCategories[dish.category]} ·{" "}
                          {diningRatingCount(dish.rating_count)}
                        </span>
                      </span>
                      <span className="dining-row-score">
                        <strong>{diningScore(dish.average_score)}</strong>
                        <ChevronRight size={18} aria-hidden="true" />
                      </span>
                    </button>
                  </li>
                );
              }
              const restaurant = item as RestaurantRanking;
              return (
                <li key={restaurant.restaurant.id}>
                  <button
                    className="dining-ranking-row"
                    onClick={() => {
                      setDetailError("");
                      setSelected(null);
                      setDetailRevision(0);
                      setRequestedVisitId(restaurant.visit_id);
                    }}
                  >
                    <span className="dining-place-icon">
                      <Store size={21} aria-hidden="true" />
                    </span>
                    <span className="dining-row-copy">
                      <strong>{restaurant.restaurant.name}</strong>
                      {restaurant.restaurant.city && (
                        <span className="dining-row-meta">
                          <MapPin size={13} aria-hidden="true" />
                          {restaurant.restaurant.city}
                        </span>
                      )}
                      <span className="dining-row-meta">
                        {diningRatingCount(restaurant.rating_count)}
                      </span>
                    </span>
                    <span className="dining-row-score">
                      <strong>{diningScore(restaurant.average_score)}</strong>
                      <ChevronRight size={18} aria-hidden="true" />
                    </span>
                  </button>
                </li>
              );
            })}
          </ul>
          {(page > 1 || current.has_more) && (
            <div className="dining-pagination">
              <button
                className="button secondary"
                disabled={loading || page <= 1}
                onClick={() => setPage((value) => value - 1)}
              >
                <ChevronLeft size={18} aria-hidden="true" />
                Înapoi
              </button>
              <span>Pagina {page}</span>
              <button
                className="button secondary"
                disabled={loading || !current.has_more || page >= pageLimit}
                onClick={() => setPage((value) => value + 1)}
              >
                Înainte
                <ChevronRight size={18} aria-hidden="true" />
              </button>
            </div>
          )}
        </>
      )}
      {adding && (
        <DiningVisitForm
          family={family}
          restaurants={restaurants}
          initialRestaurant={filterRestaurant}
          restaurantQuery={directoryQuery}
          onRestaurantQuery={setDirectoryQuery}
          directoryLoading={directoryLoading}
          directoryError={directoryError}
          hasMoreRestaurants={directoryMore}
          onMoreRestaurants={() =>
            setDirectoryPage((value) => Math.min(value + 1, pageLimit))
          }
          onRestaurantCreated={(restaurant) =>
            setRestaurants((previous) => [
              restaurant,
              ...previous.filter((item) => item.id !== restaurant.id),
            ])
          }
          onClose={() => {
            setAdding(false);
            setDirectoryQuery("");
          }}
          onSaved={(visit) => {
            setAdding(false);
            setDirectoryQuery("");
            setPage(1);
            setNotice("Vizită salvată.");
            setRevision((value) => value + 1);
            openVisit(visit);
          }}
          onExpired={onExpired}
        />
      )}
      {selectedId && (
        <Modal
          title={selected?.restaurant.name ?? "Vizită la restaurant"}
          onClose={closeVisit}
          wide
        >
          {detailLoading && !(selected && "ratings" in selected) ? (
            <p role="status">Se încarcă vizita…</p>
          ) : detailError ? (
            <div className="notice">
              <p className="error" role="alert">
                {detailError}
              </p>
              <button
                className="text-button"
                onClick={() => setDetailRevision((value) => value + 1)}
              >
                Reîncearcă
              </button>
            </div>
          ) : selected && "ratings" in selected ? (
            <VisitDetail
              family={family}
              visit={selected}
              onOpenMeal={onOpenMeal}
              onChanged={(visit, changed) => {
                setSelected(visit);
                if (changed) setRevision((value) => value + 1);
              }}
              onExpired={onExpired}
              onClose={closeVisit}
            />
          ) : null}
        </Modal>
      )}
    </div>
  );
}

function VisitDetail({
  family,
  visit,
  onOpenMeal,
  onChanged,
  onExpired,
  onClose,
}: {
  family: Family;
  visit: DiningVisitDetail;
  onOpenMeal?: (id: string, date: string) => void;
  onChanged: (visit: DiningVisitDetail, changed: boolean) => void;
  onExpired: () => void;
  onClose: () => void;
}) {
  const [source, setSource] = useState(visit.my_rating);
  const [score, setScore] = useState(visit.my_rating?.score ?? null);
  const [comment, setComment] = useState(visit.my_rating?.comment ?? "");
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState("");
  const [stale, setStale] = useState(false);
  const [notice, setNotice] = useState("");
  const pending = useRef<AbortController | null>(null);
  const base = `/families/${family.id}/dining-visits/${visit.id}`;
  useEffect(() => () => pending.current?.abort(), []);
  async function save(event: FormEvent | null, withdraw = false) {
    event?.preventDefault();
    if (pending.current || stale || (!withdraw && score === null)) return;
    const controller = new AbortController();
    pending.current = controller;
    setBusy(true);
    setError("");
    setNotice("");
    try {
      const result = await api<DiningVisitDetail>(`${base}/rating`, {
        method: withdraw ? "DELETE" : "PUT",
        signal: controller.signal,
        headers: { "If-Match": `"${source?.version ?? 0}"` },
        body: withdraw
          ? undefined
          : JSON.stringify({ score, comment: comment.trim() || null }),
      });
      if (controller.signal.aborted) return;
      setSource(result.my_rating);
      setScore(result.my_rating?.score ?? null);
      setComment(result.my_rating?.comment ?? "");
      setNotice(withdraw ? "Evaluare retrasă." : "Evaluare salvată.");
      onChanged(result, true);
    } catch (cause) {
      if (controller.signal.aborted) return;
      setError(errorMessage(cause));
      if (cause instanceof ApiError && cause.status === 412) setStale(true);
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
  async function reload(page = 1, replaceDraft = false) {
    if (pending.current) return;
    const controller = new AbortController();
    pending.current = controller;
    setBusy(true);
    setError("");
    try {
      const result = await api<DiningVisitDetail>(
        `${base}?rating_page=${page}`,
        { signal: controller.signal },
      );
      if (controller.signal.aborted) return;
      if (replaceDraft) {
        setSource(result.my_rating);
        setScore(result.my_rating?.score ?? null);
        setComment(result.my_rating?.comment ?? "");
        setStale(false);
      }
      onChanged(result, false);
    } catch (cause) {
      if (!controller.signal.aborted) {
        setError(errorMessage(cause));
        if (
          cause instanceof ApiError &&
          [401, 403, 404, 419].includes(cause.status)
        )
          onExpired();
      }
    } finally {
      if (!controller.signal.aborted) {
        pending.current = null;
        setBusy(false);
      }
    }
  }
  return (
    <div className="dining-visit-detail">
      <div className="dining-detail-meta">
        <time dateTime={visit.meal_date}>{dateLabel(visit.meal_date)}</time>
        <span>{mealTypes[visit.meal_type]}</span>
        {visit.restaurant.city && (
          <span>
            <MapPin size={14} aria-hidden="true" />
            {visit.restaurant.city}
          </span>
        )}
        <span>
          {visit.visibility === "private" ? (
            <LockKeyhole size={14} aria-hidden="true" />
          ) : (
            <Users size={14} aria-hidden="true" />
          )}
          {visit.visibility === "private" ? "Doar eu" : "Familie"}
        </span>
      </div>
      <div className="dining-detail-score">
        {visit.rating_average === null ? (
          "Fără evaluări pentru restaurant."
        ) : (
          <>
            <Star size={19} aria-hidden="true" />
            <strong>{diningScore(visit.rating_average)}</strong>
            <span>{diningRatingCount(visit.rating_count)}</span>
          </>
        )}
      </div>
      <section aria-labelledby={`visit-dishes-${visit.id}`}>
        <h3 id={`visit-dishes-${visit.id}`}>Preparate</h3>
        <ul className="dining-detail-dishes">
          {visit.dishes.map((dish) => (
            <li key={dish.id}>
              <button
                onClick={() => {
                  if (onOpenMeal) {
                    onClose();
                    onOpenMeal(dish.id, dish.meal_date);
                  }
                }}
                disabled={!onOpenMeal}
              >
                <span>
                  <strong>{dish.dish_title}</strong>
                  <small>{dishCategories[dish.category ?? "other"]}</small>
                </span>
                <span className="dining-row-score">
                  {dish.rating_average == null ? (
                    <small>Fără notă</small>
                  ) : (
                    <>
                      <strong>{diningScore(dish.rating_average)}</strong>
                      <small>{diningRatingCount(dish.rating_count ?? 0)}</small>
                    </>
                  )}
                  <ChevronRight size={17} aria-hidden="true" />
                </span>
              </button>
            </li>
          ))}
        </ul>
      </section>
      <section
        className="dining-own-rating"
        aria-labelledby={`visit-own-rating-${visit.id}`}
      >
        <h3 id={`visit-own-rating-${visit.id}`}>Nota mea pentru restaurant</h3>
        {visit.meal_date > bucharestToday() ? (
          <p className="notice">
            Poți evalua restaurantul începând cu ziua vizitei.
          </p>
        ) : (
          <form onSubmit={(event) => void save(event)}>
            {notice && (
              <p className="notice" role="status">
                {notice}
              </p>
            )}
            {error && (
              <p className="error" role="alert">
                {error}
              </p>
            )}
            {stale && (
              <div className="notice">
                <p>Reîncărcarea înlocuiește nota și observațiile nesalvate.</p>
                <button
                  type="button"
                  className="text-button"
                  disabled={busy}
                  onClick={() => void reload(1, true)}
                >
                  Reîncarcă evaluarea
                </button>
              </div>
            )}
            <ScorePicker
              value={score}
              onChange={setScore}
              disabled={busy || stale}
              required
            />
            <label className="field">
              Observații (opțional)
              <textarea
                rows={2}
                value={comment}
                maxLength={1000}
                disabled={busy || stale}
                onChange={(event) => setComment(event.target.value)}
              />
            </label>
            <div className="dining-form-actions">
              <button
                className="button primary"
                disabled={busy || stale || score === null}
              >
                {busy ? "Se salvează…" : "Salvează nota"}
              </button>
              {source?.score != null && (
                <button
                  type="button"
                  className="text-button"
                  disabled={busy || stale}
                  onClick={() => void save(null, true)}
                >
                  Retrage nota
                </button>
              )}
            </div>
          </form>
        )}
      </section>
      <section
        className="dining-rating-list"
        aria-labelledby={`visit-ratings-${visit.id}`}
      >
        <h3 id={`visit-ratings-${visit.id}`}>Evaluări</h3>
        {!visit.ratings.length ? (
          <p>Nicio evaluare pe această pagină.</p>
        ) : (
          <ul>
            {visit.ratings.map((rating) => (
              <li key={rating.membership_id}>
                <div>
                  <strong>{rating.author_name}</strong>
                  <span>{diningScore(rating.score)}</span>
                </div>
                {rating.comment && <p>{rating.comment}</p>}
              </li>
            ))}
          </ul>
        )}
        {(visit.rating_page > 1 || visit.rating_has_more) && (
          <div className="dining-pagination">
            <button
              className="button secondary"
              disabled={busy || visit.rating_page <= 1}
              onClick={() => void reload(visit.rating_page - 1)}
            >
              <ChevronLeft size={17} aria-hidden="true" />
              Înapoi
            </button>
            <span>Pagina {visit.rating_page}</span>
            <button
              className="button secondary"
              disabled={busy || !visit.rating_has_more}
              onClick={() => void reload(visit.rating_page + 1)}
            >
              Înainte
              <ChevronRight size={17} aria-hidden="true" />
            </button>
          </div>
        )}
      </section>
    </div>
  );
}
