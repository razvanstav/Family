import { useEffect, useRef, useState } from "react";
import {
  BookHeart,
  ChevronRight,
  Image,
  LockKeyhole,
  RefreshCw,
  StickyNote,
  Users,
  UtensilsCrossed,
} from "lucide-react";
import { api, ApiError, visibilityLabel } from "../../shared/api";
import { dateLabel } from "../../shared/calendarDates";
import { PrivatePhoto, type PhotoStatus } from "../../shared/PrivatePhoto";
import { observePageResume } from "../../shared/pageResume";
import { momentKinds, type Moment } from "./memoryTypes";

type TimelineCard = {
  id: string;
  kind: "memory.moment" | "kitchen.meal";
  title: string;
  occurred_on: string;
  source_version: number;
  owner_name: string;
  visibility: keyof typeof visibilityLabel;
  photo_status: PhotoStatus;
  photo_is_example: boolean;
  moment_kind?: keyof typeof momentKinds;
  meal_type?: string;
  excerpt?: string | null;
};
type TimelinePage = {
  items: TimelineCard[];
  has_more: boolean;
  next_cursor: string | null;
};

export function Timeline({
  familyId,
  revision,
  onMoment,
  onMeal,
  onUnavailable,
  mealTypeLabels,
}: {
  familyId: string;
  revision: number;
  onMoment: (moment: Moment) => void;
  onMeal: (id: string) => void;
  onUnavailable: () => void;
  mealTypeLabels: Readonly<Record<string, string>>;
}) {
  const base = `/families/${familyId}`;
  const [data, setData] = useState<TimelinePage | null>(null);
  const [kind, setKind] = useState("all");
  const [positions, setPositions] = useState<string[]>([""]);
  const [page, setPage] = useState(0);
  const [refresh, setRefresh] = useState(0);
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [error, setError] = useState("");
  const [opening, setOpening] = useState<string | null>(null);
  const pending = useRef<AbortController | null>(null);
  const loadedPosition = useRef("");
  const cursor = positions[page];
  useEffect(() => {
    const controller = new AbortController();
    const params = new URLSearchParams({ kind });
    if (cursor) params.set("cursor", cursor);
    const position = `${base}:${kind}:${cursor}`;
    if (loadedPosition.current !== position) {
      setLoading(true);
      setData(null);
    }
    setRefreshing(true);
    setError("");
    api<TimelinePage>(`${base}/timeline?${params}`, {
      signal: controller.signal,
    })
      .then((result) => {
        if (!controller.signal.aborted) {
          loadedPosition.current = position;
          setData(result);
        }
      })
      .catch((e: unknown) => {
        if (controller.signal.aborted) return;
        loadedPosition.current = "";
        setData(null);
        if (e instanceof ApiError && [401, 403, 404, 419].includes(e.status))
          onUnavailable();
        else
          setError(
            e instanceof ApiError && e.status === 422
              ? "Pagina nu mai este disponibilă. Actualizează cronologia."
              : "Cronologia nu a putut fi încărcată. Reîncearcă.",
          );
      })
      .finally(() => {
        if (!controller.signal.aborted) {
          setLoading(false);
          setRefreshing(false);
        }
      });
    return () => controller.abort();
  }, [base, kind, cursor, refresh, revision]);
  useEffect(() => {
    const unsubscribe = observePageResume(document, window, () =>
      setRefresh((value) => value + 1),
    );
    return () => {
      unsubscribe();
      pending.current?.abort();
    };
  }, []);
  function reset() {
    setPositions([""]);
    setPage(0);
    setRefresh((value) => value + 1);
  }
  async function open(card: TimelineCard) {
    if (pending.current) return;
    if (card.kind === "kitchen.meal") {
      onMeal(card.id);
      return;
    }
    const controller = new AbortController();
    pending.current = controller;
    setOpening(card.id);
    setError("");
    try {
      const moment = await api<Moment>(`${base}/moments/${card.id}`, {
        signal: controller.signal,
      });
      if (controller.signal.aborted) return;
      if (moment.archived_at) reset();
      else onMoment(moment);
    } catch (e) {
      if (controller.signal.aborted) return;
      if (e instanceof ApiError && [401, 403, 404, 419].includes(e.status))
        onUnavailable();
      else setError("Amintirea nu a putut fi deschisă. Reîncearcă.");
    } finally {
      if (!controller.signal.aborted) {
        pending.current = null;
        setOpening(null);
      }
    }
  }
  const groups: { date: string; items: TimelineCard[] }[] = [];
  for (const card of data?.items ?? []) {
    const previous = groups[groups.length - 1];
    if (previous?.date === card.occurred_on) previous.items.push(card);
    else groups.push({ date: card.occurred_on, items: [card] });
  }
  return (
    <section aria-label="Cronologie" aria-busy={refreshing}>
      <div className="timeline-tools">
        <div className="memory-kind-filter" aria-label="Tip de activitate">
          {[
            ["all", "Toate"],
            ["memory", "Amintiri"],
            ["meals", "Mese"],
          ].map(([value, label]) => (
            <button
              key={value}
              aria-pressed={kind === value}
              onClick={() => {
                setKind(value);
                setPositions([""]);
                setPage(0);
              }}
            >
              {label}
            </button>
          ))}
        </div>
        <button
          className="icon-button"
          aria-label="Actualizează cronologia"
          disabled={refreshing}
          onClick={reset}
        >
          <RefreshCw size={18} />
        </button>
      </div>
      {error && (
        <p className="error" role="alert">
          {error}
        </p>
      )}
      {loading ? (
        <p role="status">Se încarcă cronologia…</p>
      ) : !data ? (
        <button className="button secondary" onClick={reset}>
          Reîncearcă
        </button>
      ) : (
        <>
          {groups.length === 0 && (
            <div className="empty">
              <BookHeart size={34} aria-hidden="true" />
              <h2>
                {data.has_more || page > 0
                  ? "Nicio activitate vizibilă pe această pagină"
                  : "Nicio activitate"}
              </h2>
            </div>
          )}
          {groups.map((group) => (
            <section
              className="timeline-day"
              key={group.date}
              aria-label={dateLabel(group.date)}
            >
              <h2>
                <time dateTime={group.date}>{dateLabel(group.date)}</time>
              </h2>
              <div className="timeline-rows">
                {group.items.map((card) => {
                  const isMeal = card.kind === "kitchen.meal";
                  const Icon = isMeal
                    ? UtensilsCrossed
                    : card.moment_kind === "note"
                      ? StickyNote
                      : BookHeart;
                  return (
                    <button
                      className="timeline-row"
                      key={`${card.kind}:${card.id}`}
                      disabled={opening !== null}
                      aria-label={`Deschide ${isMeal ? "masa" : "amintirea"} ${card.title}`}
                      onClick={() => void open(card)}
                    >
                      {card.photo_status ? (
                        <PrivatePhoto
                          src={`/api/v1${base}/${isMeal ? "meals" : "moments"}/${card.id}/photo?v=${card.source_version}`}
                          status={card.photo_status}
                          className="timeline-photo"
                          placeholder={<Image size={22} aria-hidden="true" />}
                        />
                      ) : (
                        <span
                          className={`timeline-icon ${isMeal ? "timeline-meal-icon" : ""}`}
                        >
                          <Icon size={25} aria-hidden="true" />
                        </span>
                      )}
                      <span className="timeline-row-body">
                        <span className="timeline-kind">
                          <Icon size={14} aria-hidden="true" />
                          {isMeal && card.meal_type
                            ? mealTypeLabels[card.meal_type]
                            : momentKinds[card.moment_kind ?? "moment"]}
                        </span>
                        <strong>{card.title}</strong>
                        {card.excerpt && (
                          <span className="timeline-excerpt">
                            {card.excerpt}
                          </span>
                        )}
                        <span className="timeline-meta">
                          <span>
                            {card.visibility === "family" ? (
                              <Users size={13} />
                            ) : (
                              <LockKeyhole size={13} />
                            )}
                            {visibilityLabel[card.visibility]}
                          </span>
                          <span>{card.owner_name}</span>
                        </span>
                        {card.photo_is_example && (
                          <span className="timeline-example">
                            Imagine de exemplu
                          </span>
                        )}
                        {opening === card.id && (
                          <span role="status">Se deschide…</span>
                        )}
                      </span>
                      <ChevronRight size={18} aria-hidden="true" />
                    </button>
                  );
                })}
              </div>
            </section>
          ))}
          {(page > 0 || data.has_more) && (
            <nav className="memory-pagination" aria-label="Paginare cronologie">
              <button
                className="button secondary"
                disabled={page === 0}
                onClick={() => setPage((value) => value - 1)}
              >
                Înapoi
              </button>
              <span>Pagina {page + 1}</span>
              <button
                className="button secondary"
                disabled={!data.next_cursor}
                onClick={() => {
                  if (data.next_cursor) {
                    setPositions((current) => [
                      ...current.slice(0, page + 1),
                      data.next_cursor!,
                    ]);
                    setPage((value) => value + 1);
                  }
                }}
              >
                Înainte
              </button>
            </nav>
          )}
        </>
      )}
    </section>
  );
}
