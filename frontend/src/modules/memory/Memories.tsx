import { useEffect, useState } from "react";
import {
  BookHeart,
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
import { MomentDetail } from "./MomentDetail";
import { MomentForm } from "./MomentForm";
import { momentKinds, type Moment, type MomentList } from "./memoryTypes";
import "./memory.css";

type View =
  | { mode: "detail"; moment: Moment }
  | { mode: "form"; moment?: Moment }
  | null;

export function Memories({
  family,
  onExpired,
}: {
  family: Family;
  onExpired: () => void;
}) {
  const base = `/families/${family.id}/moments`;
  const [items, setItems] = useState<Moment[]>([]);
  const [query, setQuery] = useState("");
  const [search, setSearch] = useState("");
  const [kind, setKind] = useState("");
  const [visibility, setVisibility] = useState("");
  const [page, setPage] = useState(1);
  const [hasMore, setHasMore] = useState(false);
  const [revision, setRevision] = useState(0);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState("");
  const [notice, setNotice] = useState("");
  const [view, setView] = useState<View>(null);

  useEffect(() => {
    const timer = window.setTimeout(() => {
      setSearch(query.trim());
      setPage(1);
    }, 250);
    return () => window.clearTimeout(timer);
  }, [query]);
  useEffect(() => {
    const controller = new AbortController();
    const params = new URLSearchParams({ page: String(page) });
    if (search) params.set("q", search);
    if (kind) params.set("kind", kind);
    if (visibility) params.set("visibility", visibility);
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
  }, [base, search, kind, visibility, page, revision]);
  useEffect(() => {
    const timer = window.setInterval(() => {
      if (document.visibilityState === "visible")
        setRevision((value) => value + 1);
    }, 60000);
    return () => window.clearInterval(timer);
  }, []);

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
            <button aria-label="Șterge căutarea" onClick={() => setQuery("")}>
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
        </select>
      </div>
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
      {notice && (
        <p className="notice" role="status">
          {notice}
        </p>
      )}
      <section aria-label="Lista amintirilor" aria-busy={loading}>
        <div className="section-header">
          <h2>
            {kind === "note"
              ? "Note"
              : kind === "moment"
                ? "Momente"
                : "Momente și note"}
          </h2>
          <button
            className="icon-button"
            aria-label="Actualizează amintirile"
            disabled={loading}
            onClick={() => setRevision((value) => value + 1)}
          >
            <RefreshCw size={18} />
          </button>
        </div>
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
                    {moment.visibility === "private" ? (
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
      {view?.mode === "detail" && (
        <MomentDetail
          key={view.moment.id}
          base={base}
          moment={view.moment}
          onClose={() => setView(null)}
          onEdit={(moment) => setView({ mode: "form", moment })}
          onUnavailable={unavailable}
        />
      )}
      {view?.mode === "form" && (
        <MomentForm
          base={base}
          moment={view.moment}
          onClose={() =>
            setView(
              view.moment ? { mode: "detail", moment: view.moment } : null,
            )
          }
          onUnavailable={unavailable}
          onReloaded={(moment) => setView({ mode: "form", moment })}
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
