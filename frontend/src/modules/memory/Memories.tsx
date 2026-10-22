import { useEffect, useState } from "react";
import {
  BookHeart,
  Image,
  LockKeyhole,
  Plus,
  RefreshCw,
  Search,
  StickyNote,
  Users,
  X,
} from "lucide-react";
import { api, ApiError, visibilityLabel, type Family } from "../../shared/api";
import { dateLabel } from "../../shared/calendarDates";
import { PrivatePhoto } from "../../shared/PrivatePhoto";
import { MomentDetail } from "./MomentDetail";
import { MomentForm } from "./MomentForm";
import { Timeline } from "./Timeline";
import { MealViewer } from "../kitchen/MealViewer";
import { momentKinds, type Moment, type MomentList } from "./memoryTypes";
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
}: {
  family: Family;
  actorId: number;
  requestedMoment: string | null;
  onMomentOpened: () => void;
  onExpired: () => void;
}) {
  const base = `/families/${family.id}/moments`;
  const [section, setSection] = useState<"moments" | "timeline">("moments");
  const [items, setItems] = useState<Moment[]>([]);
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

  useEffect(() => {
    if (!requestedMoment) return;
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
    if (archived) params.set("archived", "1");
    setLoading(true);
    setError("");
    api<MomentList>(`${base}?${params}`, { signal: controller.signal })
      .then((data) => {
        if (controller.signal.aborted) return;
        setItems(data.items);
        setHasMore(data.has_more);
      })
      .catch((e: unknown) => {
        if (controller.signal.aborted) return;
        setItems([]);
        if (e instanceof ApiError && [401, 403, 404, 419].includes(e.status)) {
          setView(null);
          onExpired();
        } else setError("Amintirile nu au putut fi încărcate. Reîncearcă.");
      })
      .finally(() => {
        if (!controller.signal.aborted) setLoading(false);
      });
    return () => controller.abort();
  }, [base, search, kind, visibility, archived, page, revision, section]);
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
    setNotice("Accesul s-a schimbat. Lista va fi actualizată.");
    setRevision((value) => value + 1);
    onExpired();
  }
  function clearFilters() {
    setQuery("");
    setSearch("");
    setKind("");
    setVisibility("");
    setPage(1);
  }
  const filtered = Boolean(search || kind || visibility);

  return (
    <div className="memories-page">
      <section className="page-heading">
        <h1>Amintiri</h1>
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
      </section>
      <nav className="memory-sections" aria-label="Vizualizare Amintiri">
        <button
          aria-pressed={section === "moments"}
          onClick={() => setSection("moments")}
        >
          Momente și note
        </button>
        <button
          aria-pressed={section === "timeline"}
          onClick={() => setSection("timeline")}
        >
          Cronologie
        </button>
      </nav>
      {section === "timeline" && (
        <Timeline
          familyId={family.id}
          revision={revision}
          onMoment={(moment) => setView({ mode: "detail", moment })}
          onMeal={(id) => setView({ mode: "meal", id })}
          onUnavailable={unavailable}
        />
      )}
      {section === "moments" && (
        <>
          <div className="memory-toolbar">
            <label className="search">
              <Search size={18} aria-hidden="true" />
              <input
                type="search"
                aria-label="Caută amintiri după titlu sau text"
                placeholder="Caută amintiri"
                maxLength={120}
                value={query}
                onChange={(event) => setQuery(event.target.value)}
              />
              {query && (
                <button
                  aria-label="Șterge căutarea"
                  onClick={() => setQuery("")}
                >
                  <X size={16} />
                </button>
              )}
            </label>
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
          {notice && (
            <p className="notice" role="status">
              {notice}
            </p>
          )}
          <section
            aria-label={archived ? "Amintiri arhivate" : "Lista amintirilor"}
            aria-busy={loading}
          >
            {loading ? (
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
              <div className="moment-grid">
                {items.map((moment) => (
                  <button
                    className="moment-card"
                    key={moment.id}
                    onClick={() => setView({ mode: "detail", moment })}
                  >
                    {moment.photo_status && (
                      <PrivatePhoto
                        src={`/api/v1${base}/${moment.id}/photo?v=${moment.version}`}
                        status={moment.photo_status}
                        className="moment-photo"
                        placeholder={<Image size={28} aria-hidden="true" />}
                      />
                    )}
                    <span className="moment-card-top">
                      <span className="moment-kind">
                        {moment.kind === "moment" ? (
                          <BookHeart size={20} />
                        ) : (
                          <StickyNote size={20} />
                        )}
                        {momentKinds[moment.kind]}
                      </span>
                      <time dateTime={moment.occurred_on}>
                        {dateLabel(moment.occurred_on, {
                          day: "numeric",
                          month: "short",
                          year: "numeric",
                        })}
                      </time>
                    </span>
                    <strong>{moment.title}</strong>
                    {moment.body && (
                      <span className="moment-excerpt">{moment.body}</span>
                    )}
                    <span className="moment-card-bottom">
                      <span>
                        {moment.visibility !== "family" ? (
                          <LockKeyhole size={15} />
                        ) : (
                          <Users size={15} />
                        )}
                        {visibilityLabel[moment.visibility]}
                      </span>
                      <span className="moment-card-author">
                        {moment.owner_name}
                      </span>
                    </span>
                  </button>
                ))}
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
      {view?.mode === "meal" && (
        <MealViewer
          key={view.id}
          familyId={family.id}
          actorId={actorId}
          mealId={view.id}
          onClose={() => setView(null)}
          onUnavailable={unavailable}
        />
      )}
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
