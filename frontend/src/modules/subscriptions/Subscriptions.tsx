import { useEffect, useRef, useState, type ReactNode } from "react";
import {
  CalendarDays,
  KeyRound,
  Plus,
  RefreshCw,
  Tv,
  Users,
} from "lucide-react";
import { api, ApiError, errorMessage, type Family } from "../../shared/api";
import { dateLabel } from "../../shared/calendarDates";
import { PageBackdrop } from "../../shared/PageBackdrop";
import { observePageResume } from "../../shared/pageResume";
import { SubscriptionForm } from "./SubscriptionForm";
import { SubscriptionDetail } from "./SubscriptionDetail";
import {
  subscriptionFrequency,
  subscriptionMoney,
  type Subscription,
  type SubscriptionList,
} from "./subscriptionData";
import "./subscriptions.css";

type View =
  | { mode: "detail"; subscription: Subscription }
  | { mode: "form"; subscription: Subscription | null }
  | null;

export function Subscriptions({
  family,
  sectionTabs,
  requestedSubscription = null,
  onSubscriptionOpened,
  onExpired,
  onChanged,
}: {
  family: Family;
  actorId: number;
  sectionTabs?: ReactNode;
  requestedSubscription?: string | null;
  onSubscriptionOpened?: () => void;
  onExpired: () => void;
  onChanged?: () => void;
}) {
  const base = `/families/${family.id}/subscriptions`;
  const [scope, setScope] = useState<"family" | "shared">("family");
  const [items, setItems] = useState<Subscription[]>([]);
  const [nextPage, setNextPage] = useState<number | null>(null);
  const [revision, setRevision] = useState(0);
  const [loading, setLoading] = useState(true);
  const [moreBusy, setMoreBusy] = useState(false);
  const [error, setError] = useState("");
  const [notice, setNotice] = useState("");
  const [view, setView] = useState<View>(null);
  const moreRequest = useRef<AbortController | null>(null);
  const loadingMore = useRef(false);
  const itemsRef = useRef(items);
  itemsRef.current = items;
  useEffect(() => () => moreRequest.current?.abort(), []);
  useEffect(
    () =>
      observePageResume(document, window, () =>
        setRevision((value) => value + 1),
      ),
    [],
  );
  useEffect(() => {
    const controller = new AbortController();
    moreRequest.current?.abort();
    loadingMore.current = false;
    setMoreBusy(false);
    setLoading(true);
    setError("");
    setItems([]);
    setNextPage(null);
    api<SubscriptionList>(`${base}?scope=${scope}`, {
      signal: controller.signal,
    })
      .then((data) => {
        if (!controller.signal.aborted) {
          setItems(data.items);
          setNextPage(data.next_page);
        }
      })
      .catch((error: unknown) => {
        if (controller.signal.aborted) return;
        setError(errorMessage(error));
        if (
          error instanceof ApiError &&
          [401, 403, 404, 419].includes(error.status)
        ) {
          setView(null);
          onExpired();
        }
      })
      .finally(() => {
        if (!controller.signal.aborted) setLoading(false);
      });
    return () => controller.abort();
  }, [base, scope, revision]);
  useEffect(() => {
    if (!requestedSubscription) return;
    const existing = itemsRef.current.find(
      (item) => item.id === requestedSubscription,
    );
    if (existing) {
      setView({ mode: "detail", subscription: existing });
      onSubscriptionOpened?.();
      return;
    }
    const controller = new AbortController();
    api<Subscription>(`${base}/${requestedSubscription}`, {
      signal: controller.signal,
    })
      .then((item) => {
        if (!controller.signal.aborted) {
          setScope(
            item.access === "shared" || item.access === "invited"
              ? "shared"
              : "family",
          );
          setView({ mode: "detail", subscription: item });
          onSubscriptionOpened?.();
        }
      })
      .catch((error: unknown) => {
        if (!controller.signal.aborted) {
          setNotice(errorMessage(error));
          onSubscriptionOpened?.();
        }
      });
    return () => controller.abort();
  }, [base, requestedSubscription]);
  function unavailable() {
    setView(null);
    setItems([]);
    setNotice("Abonamentul nu mai este disponibil sau accesul s-a schimbat.");
    setRevision((value) => value + 1);
    onExpired();
  }
  function changed(item: Subscription | null, id?: string) {
    if (item)
      setItems((current) =>
        current.map((existing) => (existing.id === item.id ? item : existing)),
      );
    else if (id)
      setItems((current) => current.filter((existing) => existing.id !== id));
    onChanged?.();
  }
  async function loadMore() {
    if (!nextPage || loadingMore.current || loading) return;
    const request = new AbortController();
    moreRequest.current = request;
    loadingMore.current = true;
    setMoreBusy(true);
    setError("");
    try {
      const data = await api<SubscriptionList>(
        `${base}?scope=${scope}&page=${nextPage}`,
        { signal: request.signal },
      );
      if (!request.signal.aborted) {
        setItems((current) => [
          ...current,
          ...data.items.filter(
            (item) => !current.some((old) => old.id === item.id),
          ),
        ]);
        setNextPage(data.next_page);
      }
    } catch (error) {
      if (!request.signal.aborted) {
        setError(errorMessage(error));
        if (
          error instanceof ApiError &&
          [401, 403, 404, 419].includes(error.status)
        )
          onExpired();
      }
    } finally {
      if (!request.signal.aborted) {
        loadingMore.current = false;
        setMoreBusy(false);
      }
    }
  }
  return (
    <div className="subscriptions-page">
      <PageBackdrop image="calendar">
        <section className="page-heading">
          <h1>Abonamente</h1>
          {family.role !== "limited" && (
            <button
              className="button primary"
              aria-label="Adaugă abonament"
              onClick={() => setView({ mode: "form", subscription: null })}
            >
              <Plus size={19} aria-hidden="true" />
              <span>Adaugă abonament</span>
            </button>
          )}
        </section>
      </PageBackdrop>
      {sectionTabs}
      <div className="subscriptions-toolbar">
        <div
          className="subscription-scopes"
          role="group"
          aria-label="Acces abonamente"
        >
          <button
            aria-pressed={scope === "family"}
            onClick={() => setScope("family")}
          >
            <Users size={17} aria-hidden="true" />
            Familie
          </button>
          <button
            aria-pressed={scope === "shared"}
            onClick={() => setScope("shared")}
          >
            Partajate cu mine
          </button>
        </div>
        <button
          className="icon-button"
          disabled={loading || moreBusy}
          aria-label="Actualizează abonamentele"
          onClick={() => setRevision((value) => value + 1)}
        >
          <RefreshCw size={19} aria-hidden="true" />
        </button>
      </div>
      {notice && (
        <p className="notice" role="status">
          {notice}
        </p>
      )}
      {error && (
        <div className="subscription-error">
          <p className="error" role="alert">
            {error}
          </p>
          <button
            className="button secondary"
            disabled={loading}
            onClick={() => setRevision((value) => value + 1)}
          >
            Reîncarcă abonamentele
          </button>
        </div>
      )}
      {loading ? (
        <p role="status">Se încarcă abonamentele…</p>
      ) : items.length ? (
        <ul
          className="subscription-grid"
          aria-label={
            scope === "family"
              ? "Abonamentele familiei"
              : "Abonamente partajate cu mine"
          }
        >
          {items.map((item) => {
            const money = subscriptionMoney(item);
            return (
              <li key={item.id}>
                <button
                  className="subscription-card"
                  onClick={() =>
                    setView({ mode: "detail", subscription: item })
                  }
                >
                  <span
                    className="subscription-service-icon"
                    aria-hidden="true"
                  >
                    <Tv size={23} />
                  </span>
                  <span className="subscription-card-copy">
                    <strong>{item.service_name}</strong>
                    <span>
                      {item.access === "invited"
                        ? `Invitație de la ${item.owner_name}`
                        : item.access === "shared"
                          ? `Partajat de ${item.owner_name}`
                          : item.owner_name}
                    </span>
                    {money && (
                      <span className="subscription-cost">
                        {money}
                        {item.recurrence && (
                          <> · {subscriptionFrequency(item.recurrence)}</>
                        )}
                      </span>
                    )}
                    {item.next_due_on && (
                      <span className="subscription-due">
                        <CalendarDays size={15} aria-hidden="true" />
                        <time dateTime={item.next_due_on}>
                          {dateLabel(item.next_due_on, {
                            day: "numeric",
                            month: "short",
                            year: "numeric",
                          })}
                        </time>
                      </span>
                    )}
                  </span>
                  {item.access === "invited" ? (
                    <span className="subscription-pending">Invitație</span>
                  ) : item.has_credentials ? (
                    <KeyRound
                      className="subscription-key"
                      size={18}
                      aria-label="Date de acces salvate"
                    />
                  ) : null}
                </button>
              </li>
            );
          })}
        </ul>
      ) : (
        !error && (
          <div className="subscription-empty">
            <Tv size={32} aria-hidden="true" />
            <p>
              {scope === "family"
                ? "Niciun abonament adăugat."
                : "Niciun abonament partajat cu tine."}
            </p>
            {scope === "family" && family.role !== "limited" && (
              <button
                className="button secondary"
                onClick={() => setView({ mode: "form", subscription: null })}
              >
                Adaugă abonament
              </button>
            )}
          </div>
        )
      )}
      {nextPage && (
        <button
          className="button secondary subscription-more"
          disabled={loading || moreBusy}
          onClick={() => void loadMore()}
        >
          {moreBusy ? "Se încarcă…" : "Mai multe abonamente"}
        </button>
      )}
      {view?.mode === "form" && (
        <SubscriptionForm
          base={base}
          subscription={view.subscription}
          onClose={() => setView(null)}
          onUnavailable={unavailable}
          onSaved={(item) => {
            setView(null);
            setScope("family");
            setRevision((value) => value + 1);
            setNotice(
              item ? "Abonamentul a fost salvat." : "Abonamentul a fost șters.",
            );
            onChanged?.();
          }}
        />
      )}
      {view?.mode === "detail" && (
        <SubscriptionDetail
          base={base}
          subscription={view.subscription}
          onClose={() => setView(null)}
          onEdit={(item) => setView({ mode: "form", subscription: item })}
          onChanged={(item) => changed(item, view.subscription.id)}
          onUnavailable={unavailable}
        />
      )}
    </div>
  );
}
