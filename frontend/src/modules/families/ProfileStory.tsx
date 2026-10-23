import { useEffect, useRef, useState } from "react";
import {
  BookHeart,
  Image,
  Plus,
  RefreshCw,
  LockKeyhole,
  Users,
  ChevronRight,
} from "lucide-react";
import { api, ApiError, visibilityLabel } from "../../shared/api";
import { dateLabel } from "../../shared/calendarDates";
import { PrivatePhoto } from "../../shared/PrivatePhoto";
import { kinds, message, type Entry, type StoryItem } from "./profileData";

type StoryPage = {
  items: StoryItem[];
  has_more: boolean;
  next_cursor: string | null;
};

export function ProfileStory({
  base,
  familyId,
  revision,
  canWrite,
  onEntry,
  onMoment,
  onAdd,
  onRefresh,
  onUnavailable,
  onEntries,
}: {
  base: string;
  familyId: string;
  revision: number;
  canWrite: boolean;
  onEntry: (entry: Entry) => void;
  onMoment: (id: string) => void;
  onAdd: () => void;
  onRefresh: () => void;
  onUnavailable: () => void;
  onEntries: (entries: Entry[]) => void;
}) {
  const [data, setData] = useState<StoryPage | null>(null);
  const [pages, setPages] = useState<(string | null)[]>([null]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState("");
  const loadedPosition = useRef("");
  const cursor = pages.at(-1);
  const endpoint = `${base}/story${cursor ? `?cursor=${encodeURIComponent(cursor)}` : ""}`;
  useEffect(() => {
    const controller = new AbortController();
    if (loadedPosition.current !== endpoint) setData(null);
    setLoading(true);
    setError("");
    api<StoryPage>(endpoint, { signal: controller.signal })
      .then((result) => {
        if (controller.signal.aborted) return;
        setData(result);
        loadedPosition.current = endpoint;
        onEntries(
          result.items.filter(
            (item): item is Entry & { source: "entry" } =>
              item.source === "entry",
          ),
        );
      })
      .catch((e: unknown) => {
        if (controller.signal.aborted) return;
        setData(null);
        setError(message(e));
        if (e instanceof ApiError && [401, 403, 404, 419].includes(e.status))
          onUnavailable();
      })
      .finally(() => {
        if (!controller.signal.aborted) setLoading(false);
      });
    return () => controller.abort();
  }, [endpoint, revision]);
  const years = new Map<string, StoryItem[]>();
  for (const item of data?.items ?? []) {
    const year = item.occurred_on.slice(0, 4);
    years.set(year, [...(years.get(year) ?? []), item]);
  }
  return (
    <section
      className="profile-story"
      aria-labelledby="profile-story-title"
      aria-busy={loading}
    >
      <div className="section-header">
        <h2 id="profile-story-title">Cronologie</h2>
        <div className="member-profile-actions">
          <button
            className="icon-button"
            aria-label="Actualizează profilul"
            disabled={loading}
            onClick={onRefresh}
          >
            <RefreshCw size={17} />
          </button>
          {canWrite && (
            <button
              className="icon-button primary"
              aria-label="Adaugă însemnare"
              onClick={onAdd}
            >
              <Plus size={20} />
            </button>
          )}
        </div>
      </div>
      {error ? (
        <div className="notice">
          <p className="error" role="alert">
            {error}
          </p>
          <button className="text-button" onClick={onRefresh}>
            Reîncearcă
          </button>
        </div>
      ) : loading && !data ? (
        <p role="status">Se încarcă cronologia…</p>
      ) : !data?.items.length ? (
        <div className="member-profile-empty">
          <BookHeart size={28} aria-hidden="true" />
          <p>Niciun moment sau însemnare disponibilă.</p>
          {canWrite && (
            <button className="button secondary" onClick={onAdd}>
              Adaugă însemnare
            </button>
          )}
        </div>
      ) : (
        Array.from(years, ([year, items]) => (
          <section
            className="profile-story-year"
            key={year}
            aria-labelledby={`story-year-${year}`}
          >
            <h3 id={`story-year-${year}`}>{year}</h3>
            <div className="profile-story-items">
              {items.map((item) => {
                const Icon =
                  item.source === "entry" ? kinds[item.kind].icon : BookHeart;
                const category =
                  item.source === "entry" ? item.category : "memory";
                return (
                  <button
                    key={`${item.source}:${item.id}`}
                    className={`profile-story-item profile-entry--${category}`}
                    aria-label={`Deschide ${item.title}`}
                    onClick={() =>
                      item.source === "entry"
                        ? onEntry(item)
                        : onMoment(item.id)
                    }
                  >
                    <span className="profile-story-dot">
                      <Icon size={19} aria-hidden="true" />
                    </span>
                    {item.source === "moment" && item.photo_status && (
                      <PrivatePhoto
                        src={`/api/v1/families/${familyId}/moments/${item.id}/photo?v=${item.version}`}
                        status={item.photo_status}
                        className="profile-story-photo"
                        placeholder={<Image size={26} aria-hidden="true" />}
                      />
                    )}
                    <span className="profile-story-copy">
                      <span className="profile-story-meta">
                        <span>
                          {item.source === "entry"
                            ? kinds[item.kind].label
                            : item.kind === "note"
                              ? "Notă"
                              : "Moment"}
                        </span>
                        <time dateTime={item.occurred_on}>
                          {dateLabel(item.occurred_on, {
                            day: "numeric",
                            month: "long",
                          })}
                        </time>
                      </span>
                      <strong>{item.title}</strong>
                      {(item.source === "entry"
                        ? item.notes
                        : item.excerpt) && (
                        <span className="profile-story-excerpt">
                          {item.source === "entry" ? item.notes : item.excerpt}
                        </span>
                      )}
                      <span className="profile-story-footer">
                        <span>
                          {item.visibility === "family" ? (
                            <Users size={13} aria-hidden="true" />
                          ) : (
                            <LockKeyhole size={13} aria-hidden="true" />
                          )}
                          {visibilityLabel[item.visibility]}
                        </span>
                        <span>
                          {item.source === "entry"
                            ? item.author_name
                            : item.owner_name}
                        </span>
                        <ChevronRight size={15} aria-hidden="true" />
                      </span>
                    </span>
                  </button>
                );
              })}
            </div>
          </section>
        ))
      )}
      {data && !error && (pages.length > 1 || data.has_more) && (
        <nav
          className="member-profile-pagination"
          aria-label="Paginare cronologie profil"
        >
          <button
            className="button secondary"
            disabled={pages.length === 1 || loading}
            onClick={() => setPages((current) => current.slice(0, -1))}
          >
            Înapoi
          </button>
          <span>Pagina {pages.length}</span>
          <button
            className="button secondary"
            disabled={!data.next_cursor || loading}
            onClick={() =>
              setPages((current) => [...current, data.next_cursor])
            }
          >
            Înainte
          </button>
        </nav>
      )}
    </section>
  );
}
