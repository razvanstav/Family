import { useEffect, useRef, useState, type ReactNode } from "react";
import {
  BookHeart,
  History,
  Plus,
  RefreshCw,
  Search,
  X,
  SlidersHorizontal,
} from "lucide-react";
import { api, ApiError, type Family } from "../../shared/api";
import { bucharestToday, dateLabel } from "../../shared/calendarDates";
import { MomentCard } from "./MomentCard";
import { MomentDetail } from "./MomentDetail";
import { MomentForm } from "./MomentForm";
import { Timeline } from "./Timeline";
import { SectionTabs } from "../../shared/SectionTabs";
import { PageBackdrop } from "../../shared/PageBackdrop";
import { type Moment, type MomentList } from "./memoryTypes";
import "./memory.css";

type View =
  | { mode: "detail"; moment: Moment }
  | { mode: "form"; moment?: Moment }
  | { mode: "meal"; id: string }
  | null;

export function Memories({
  family,
  actorId,
  requestedMoment,
  onMomentOpened,
  onExpired,
  mealTypeLabels,
  renderMeal,
}: {
  family: Family;
  actorId: number;
  requestedMoment: string | null;
  onMomentOpened: () => void;
  onExpired: () => void;
  mealTypeLabels: Readonly<Record<string, string>>;
  renderMeal: (props: {
    mealId: string;
    onClose: () => void;
    onUnavailable: () => void;
  }) => ReactNode;
}) {
  const base = `/families/${family.id}/moments`;
  const [section, setSection] = useState<"moments" | "timeline">("moments");
  const [items, setItems] = useState<Moment[]>([]);
  const [years, setYears] = useState<number[]>([]);
  const [year, setYear] = useState("");
  const [onThisDay, setOnThisDay] = useState<Moment[]>([]);
  const [today, setToday] = useState(bucharestToday);
  const [query, setQuery] = useState("");
  const [search, setSearch] = useState("");
  const [kind, setKind] = useState("");
  const [visibility, setVisibility] = useState("");
  const [archived, setArchived] = useState(false);
  const [page, setPage] = useState(1);
  const [hasMore, setHasMore] = useState(false);
  const [revision, setRevision] = useState(0);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState("");
  const [notice, setNotice] = useState("");
  const [view, setView] = useState<View>(null);
  const [searchOpen, setSearchOpen] = useState(false);
  const [filtersOpen, setFiltersOpen] = useState(false);
  const searchInput = useRef<HTMLInputElement>(null);
  const loadedPosition = useRef("");

  useEffect(() => {
    if (!requestedMoment) return;
    const existing = items.find((item) => item.id === requestedMoment);
    if (existing) {
      setView({ mode: "detail", moment: existing });
      onMomentOpened();
      return;
    }
    const controller = new AbortController();
    api<Moment>(`${base}/${requestedMoment}`, { signal: controller.signal })
      .then((moment) => {
        if (controller.signal.aborted) return;
        setView({ mode: "detail", moment });
        onMomentOpened();
      })
      .catch((e: unknown) => {
        if (controller.signal.aborted) return;
        setNotice(
          e instanceof ApiError && [401, 403, 404, 419].includes(e.status)
            ? "Amintirea nu mai este disponibilă sau nu mai ai acces."
            : "Nu am putut deschide amintirea. Reîncearcă din listă.",
        );
        onMomentOpened();
      });
    return () => controller.abort();
  }, [base, requestedMoment]);

  useEffect(() => {
    const timer = window.setTimeout(() => {
      setSearch(query.trim());
      setPage(1);
    }, 250);
    return () => window.clearTimeout(timer);
  }, [query]);
  useEffect(() => {
    if (section !== "moments") return;
    const controller = new AbortController();
    const params = new URLSearchParams({ page: String(page) });
    if (search) params.set("q", search);
    if (kind) params.set("kind", kind);
    if (visibility) params.set("visibility", visibility);
    if (year) params.set("year", year);
    if (archived) params.set("archived", "1");
    const position = `${base}?${params}`;
    if (loadedPosition.current !== position) setItems([]);
    setLoading(true);
    setError("");
    api<MomentList>(position, { signal: controller.signal })
      .then((data) => {
        if (controller.signal.aborted) return;
        loadedPosition.current = position;
        setItems(data.items);
        setHasMore(data.has_more);
        setYears(data.years);
        setOnThisDay(data.on_this_day);
        setToday(data.today);
      })
      .catch((e: unknown) => {
        if (controller.signal.aborted) return;
        setItems([]);
        setOnThisDay([]);
        setYears([]);
        if (e instanceof ApiError && [401, 403, 404, 419].includes(e.status)) {
          setView(null);
          onExpired();
        } else setError("Amintirile nu au putut fi încărcate. Reîncearcă.");
      })
      .finally(() => {
        if (!controller.signal.aborted) setLoading(false);
      });
    return () => controller.abort();
  }, [base, search, kind, visibility, year, archived, page, revision, section]);
  useEffect(() => {
    if (section !== "moments") return;
    const timer = window.setInterval(() => {
      if (document.visibilityState === "visible")
        setRevision((value) => value + 1);
    }, 60000);
    return () => window.clearInterval(timer);
  }, [section]);

  function unavailable() {
    setView(null);
    setItems([]);
    setOnThisDay([]);
    setYears([]);
    setNotice("Accesul s-a schimbat. Lista va fi actualizată.");
    setRevision((value) => value + 1);
    onExpired();
  }
  function clearFilters() {
    setQuery("");
    setSearch("");
    setKind("");
    setVisibility("");
    setYear("");
    setPage(1);
  }
  const filtered = Boolean(search || kind || visibility || year);
  const showOnThisDay = !filtered && !query && !archived && page === 1;
  const groups = new Map<string, Moment[]>();
  for (const moment of items) {
    const momentYear = moment.occurred_on.slice(0, 4);
    const group = groups.get(momentYear) ?? [];
    group.push(moment);
    groups.set(momentYear, group);
  }

  return (
    <div className="memories-page">
      <PageBackdrop image="memory">
        <section className="page-heading">
          <h1>Amintiri</h1>
          <div className="page-heading-actions">
            <button
              className="icon-button"
              aria-label="Caută amintiri"
              aria-expanded={searchOpen || Boolean(query)}
              onClick={() => {
                setSection("moments");
                setSearchOpen(true);
                searchInput.current?.focus();
              }}
            >
              <Search size={21} aria-hidden="true" />
            </button>
            {family.role !== "limited" && (
              <button
                className="button primary"
                aria-label="Adaugă amintire"
                onClick={() => setView({ mode: "form" })}
              >
                <Plus size={19} />
                <span>Adaugă amintire</span>
              </button>
            )}
          </div>
        </section>
      </PageBackdrop>
      <SectionTabs
        label="Vizualizare Amintiri"
        value={section}
        options={[
          { value: "moments", label: "Momente și note" },
          { value: "timeline", label: "Cronologie" },
        ]}
        onChange={setSection}
      />
      {section === "timeline" && (
        <Timeline
          familyId={family.id}
          mealTypeLabels={mealTypeLabels}
          revision={revision}
          onMoment={(moment) => setView({ mode: "detail", moment })}
          onMeal={(id) => setView({ mode: "meal", id })}
          onUnavailable={unavailable}
        />
      )}
      {section === "moments" && (
        <>
          {showOnThisDay && !error && (!loading || items.length > 0) && (
            <section className="memory-on-day" aria-labelledby="on-this-day">
              <div className="memory-on-day-heading">
                <span className="memory-on-day-icon">
                  <History size={23} aria-hidden="true" />
                </span>
                <h2 id="on-this-day">În ziua asta</h2>
                <time dateTime={today}>
                  {dateLabel(today, { day: "numeric", month: "long" })}
                </time>
              </div>
              {onThisDay.length > 0 ? (
                <div className="moment-grid">
                  {onThisDay.map((moment) => (
                    <MomentCard
                      key={moment.id}
                      base={base}
                      moment={moment}
                      anniversary={
                        Number(today.slice(0, 4)) -
                        Number(moment.occurred_on.slice(0, 4))
                      }
                      onOpen={(moment) => setView({ mode: "detail", moment })}
                    />
                  ))}
                </div>
              ) : (
                <p className="memory-on-day-empty">
                  Nicio amintire din anii trecuți pentru această zi.
                </p>
              )}
            </section>
          )}
          {(searchOpen || query) && (
            <label className="search memory-search">
              <Search size={18} aria-hidden="true" />
              <input
                type="search"
                ref={searchInput}
                autoFocus
                aria-label="Caută amintiri după titlu sau text"
                placeholder="Caută amintiri"
                maxLength={120}
                value={query}
                onChange={(event) => setQuery(event.target.value)}
              />
              <button
                aria-label="Șterge și închide căutarea"
                onClick={() => {
                  setQuery("");
                  setSearch("");
                  setSearchOpen(false);
                }}
              >
                <X size={16} />
              </button>
            </label>
          )}
          <div className="memory-filter-row">
            <div className="memory-kind-filter" aria-label="Tip de amintire">
              {[
                ["", "Toate"],
                ["moment", "Momente"],
                ["note", "Note"],
              ].map(([value, label]) => (
                <button
                  key={value}
                  aria-pressed={kind === value}
                  onClick={() => {
                    setKind(value);
                    setPage(1);
                  }}
                >
                  {label}
                </button>
              ))}
            </div>
            <div className="memory-filter-actions">
              <label className="memory-year-filter">
                <span>An</span>
                <select
                  value={year}
                  onChange={(event) => {
                    setYear(event.target.value);
                    setPage(1);
                  }}
                >
                  <option value="">Toți anii</option>
                  {year && !years.includes(Number(year)) && (
                    <option value={year}>{year}</option>
                  )}
                  {years.map((value) => (
                    <option key={value} value={value}>
                      {value}
                    </option>
                  ))}
                </select>
              </label>
              <button
                className={`icon-button memory-filter-toggle${visibility || archived ? " active" : ""}`}
                aria-label="Filtre pentru amintiri"
                aria-expanded={filtersOpen}
                aria-controls="memory-filters"
                onClick={() => setFiltersOpen((value) => !value)}
              >
                <SlidersHorizontal size={19} aria-hidden="true" />
              </button>
            </div>
          </div>
          {filtersOpen && (
            <div className="memory-options" id="memory-filters">
              <div className="memory-toolbar">
                <select
                  aria-label="Filtrează după acces"
                  value={visibility}
                  onChange={(event) => {
                    setVisibility(event.target.value);
                    setPage(1);
                  }}
                >
                  <option value="">Orice acces</option>
                  <option value="private">Doar eu</option>
                  <option value="family">Familia mea</option>
                  <option value="selected">Persoane alese</option>
                </select>
                <button
                  className="icon-button"
                  aria-label="Actualizează amintirile"
                  disabled={loading}
                  onClick={() => setRevision((value) => value + 1)}
                >
                  <RefreshCw size={18} />
                </button>
              </div>
              <label className="memory-archive-filter">
                <input
                  type="checkbox"
                  checked={archived}
                  onChange={(event) => {
                    setArchived(event.target.checked);
                    setPage(1);
                  }}
                />
                Arhivate
              </label>
            </div>
          )}
          {notice && (
            <p className="notice" role="status">
              {notice}
            </p>
          )}
          <section
            aria-label={archived ? "Amintiri arhivate" : "Lista amintirilor"}
            aria-busy={loading}
          >
            {loading && !items.length ? (
              <p role="status">Se încarcă amintirile…</p>
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
            ) : items.length === 0 ? (
              <div className="empty">
                <BookHeart size={36} aria-hidden="true" />
                <h3>
                  {filtered
                    ? "Nicio amintire găsită"
                    : archived
                      ? "Nicio amintire arhivată"
                      : page > 1
                        ? "Nicio amintire pe această pagină"
                        : "Nicio amintire"}
                </h3>
                {filtered ? (
                  <button className="button secondary" onClick={clearFilters}>
                    Șterge filtrele
                  </button>
                ) : (
                  page === 1 &&
                  !archived &&
                  family.role !== "limited" && (
                    <button
                      className="button primary"
                      onClick={() => setView({ mode: "form" })}
                    >
                      <Plus size={17} />
                      Adaugă amintire
                    </button>
                  )
                )}
              </div>
            ) : (
              <div className="memory-years">
                {Array.from(groups, ([groupYear, moments]) => {
                  const cover = moments.find(
                    (moment) => moment.photo_status === "ready",
                  );
                  const album = cover
                    ? [
                        cover,
                        ...moments.filter((moment) => moment.id !== cover.id),
                      ]
                    : moments;
                  return (
                    <section
                      className="memory-year-group"
                      key={groupYear}
                      aria-labelledby={`memory-year-${groupYear}`}
                    >
                      <h2
                        id={`memory-year-${groupYear}`}
                        className="memory-year-heading"
                      >
                        {groupYear}
                      </h2>
                      <div className="moment-grid">
                        {album.map((moment, index) => (
                          <MomentCard
                            key={moment.id}
                            base={base}
                            moment={moment}
                            lead={index === 0 && moment === cover}
                            onOpen={(moment) =>
                              setView({ mode: "detail", moment })
                            }
                          />
                        ))}
                      </div>
                    </section>
                  );
                })}
              </div>
            )}
            {!loading && !error && (page > 1 || hasMore) && (
              <nav className="memory-pagination" aria-label="Paginare amintiri">
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
              </nav>
            )}
          </section>
        </>
      )}
      {view?.mode === "meal" &&
        renderMeal({
          mealId: view.id,
          onClose: () => setView(null),
          onUnavailable: unavailable,
        })}
      {view?.mode === "detail" && (
        <MomentDetail
          key={view.moment.id}
          base={base}
          moment={view.moment}
          onClose={() => setView(null)}
          onEdit={(moment) => setView({ mode: "form", moment })}
          onUnavailable={unavailable}
          onChanged={(moment) => {
            setView({ mode: "detail", moment });
            setRevision((value) => value + 1);
          }}
        />
      )}
      {view?.mode === "form" && (
        <MomentForm
          base={base}
          actorId={actorId}
          moment={view.moment}
          onClose={() =>
            setView(
              view.moment ? { mode: "detail", moment: view.moment } : null,
            )
          }
          onUnavailable={unavailable}
          onReloaded={(moment) => {
            setView({ mode: moment.can_edit ? "form" : "detail", moment });
            setRevision((value) => value + 1);
          }}
          onSaved={(moment) => {
            setView({ mode: "detail", moment });
            setNotice("Amintirea a fost salvată.");
            setRevision((value) => value + 1);
          }}
        />
      )}
    </div>
  );
}
