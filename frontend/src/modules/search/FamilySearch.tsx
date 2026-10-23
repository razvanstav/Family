import { useEffect, useRef, useState } from "react";
import {
  BookHeart,
  FileText,
  UtensilsCrossed,
  Search,
  ChevronRight,
} from "lucide-react";
import { api, ApiError } from "../../shared/api";
import { Modal } from "../../shared/Modal";
import { dateLabel } from "../../shared/calendarDates";
import "./familySearch.css";

export type SearchResult = {
  type: "document" | "moment" | "recipe";
  id: string;
  title: string;
  excerpt: string | null;
  category: string;
  visibility: string;
  member_id: string | null;
  date: string;
};
type Results = { items: SearchResult[]; page: number; has_more: boolean };
const sources = {
  document: { label: "Document", icon: FileText },
  moment: { label: "Amintire", icon: BookHeart },
  recipe: { label: "Rețetă", icon: UtensilsCrossed },
};

export function FamilySearch({
  familyId,
  onClose,
  onOpen,
  onExpired,
}: {
  familyId: string;
  onClose: () => void;
  onOpen: (result: SearchResult) => void;
  onExpired: () => void;
}) {
  const [query, setQuery] = useState("");
  const [type, setType] = useState("");
  const [page, setPage] = useState(1);
  const [items, setItems] = useState<SearchResult[]>([]);
  const [hasMore, setHasMore] = useState(false);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState("");
  const [revision, setRevision] = useState(0);
  const term = query.trim().replace(/\s+/gu, " ");
  const ready = [...term].length >= 2;
  const loadedPosition = useRef("");
  const input = useRef<HTMLInputElement>(null);
  useEffect(() => {
    input.current?.focus();
  }, []);
  useEffect(() => {
    const controller = new AbortController();
    const position = `${term}:${type}`;
    if (page === 1 && loadedPosition.current !== position) setItems([]);
    setError("");
    setHasMore(false);
    setBusy(ready);
    if (!ready) return () => controller.abort();
    const timer = window.setTimeout(() => {
      const params = new URLSearchParams({ q: term, page: String(page) });
      if (type) params.set("type", type);
      api<Results>(`/families/${familyId}/search?${params}`, {
        signal: controller.signal,
      })
        .then((data) => {
          if (controller.signal.aborted) return;
          loadedPosition.current = position;
          setItems((current) =>
            page === 1
              ? data.items
              : [
                  ...new Map(
                    [...current, ...data.items].map((item) => [
                      `${item.type}:${item.id}`,
                      item,
                    ]),
                  ).values(),
                ],
          );
          setHasMore(data.has_more);
        })
        .catch((e) => {
          if (controller.signal.aborted) return;
          setError(
            e instanceof Error ? e.message : "Nu am putut căuta. Reîncearcă.",
          );
          if (e instanceof ApiError && [401, 404, 419].includes(e.status)) {
            setItems([]);
            onExpired();
          }
        })
        .finally(() => {
          if (!controller.signal.aborted) setBusy(false);
        });
    }, 250);
    return () => {
      controller.abort();
      window.clearTimeout(timer);
    };
  }, [familyId, term, type, page, revision]);
  useEffect(() => {
    const refresh = () => {
      if (ready && document.visibilityState === "visible") {
        setPage(1);
        setRevision((v) => v + 1);
      }
    };
    const timer = window.setInterval(refresh, 30000);
    document.addEventListener("visibilitychange", refresh);
    return () => {
      window.clearInterval(timer);
      document.removeEventListener("visibilitychange", refresh);
    };
  }, [ready]);
  return (
    <Modal title="Caută" onClose={onClose}>
      <label className="search family-search-input">
        <Search size={19} aria-hidden="true" />
        <input
          ref={input}
          type="search"
          aria-label="Caută în familie"
          placeholder="Caută în familie"
          maxLength={100}
          onKeyDown={(event) => {
            if (event.key === "Escape") {
              event.preventDefault();
              event.stopPropagation();
              onClose();
            }
          }}
          value={query}
          onChange={(e) => {
            setQuery(e.target.value);
            setPage(1);
          }}
        />
      </label>
      <p className="fine-print">
        Amintiri și rețete active; documente după titlu. Minimum două caractere.
      </p>
      <div className="family-search-filters" aria-label="Tip de rezultat">
        {[
          ["", "Toate"],
          ["moment", "Amintiri"],
          ["recipe", "Rețete"],
          ["document", "Documente"],
        ].map(([value, label]) => (
          <button
            type="button"
            key={value}
            aria-pressed={type === value}
            onClick={() => {
              setType(value);
              setPage(1);
            }}
          >
            {label}
          </button>
        ))}
      </div>
      {busy && (
        <p className="fine-print" role="status">
          Se caută…
        </p>
      )}
      {error && (
        <div className="error" role="alert">
          {error}{" "}
          <button
            className="text-button"
            onClick={() => setRevision((v) => v + 1)}
          >
            Reîncearcă
          </button>
        </div>
      )}
      {ready && !busy && !error && !items.length && (
        <p className="fine-print" role="status">
          Niciun rezultat.
        </p>
      )}
      <ul className="family-search-results">
        {items.map((item) => {
          const { label, icon: Icon } = sources[item.type];
          return (
            <li key={`${item.type}:${item.id}`}>
              <button
                className="family-search-result"
                aria-label={`Deschide ${item.title} · ${label}`}
                onClick={() => onOpen(item)}
              >
                <Icon
                  className={`family-search-icon ${item.type}`}
                  size={22}
                  aria-hidden="true"
                />
                <span className="family-search-copy">
                  <strong>{item.title}</strong>
                  <span>
                    {label} ·{" "}
                    {dateLabel(item.date, {
                      day: "numeric",
                      month: "short",
                      year: "numeric",
                    })}
                  </span>
                  {item.excerpt && (
                    <span className="family-search-excerpt">
                      {item.excerpt}
                    </span>
                  )}
                </span>
                <ChevronRight size={18} aria-hidden="true" />
              </button>
            </li>
          );
        })}
      </ul>
      {hasMore && (
        <button
          className="button secondary full"
          disabled={busy}
          onClick={() => setPage((v) => v + 1)}
        >
          Mai multe rezultate
        </button>
      )}
    </Modal>
  );
}
