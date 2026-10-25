import { SelectField } from "../../shared/SelectField";
import { useEffect, useRef, useState } from "react";
import { ChevronLeft, ChevronRight, RefreshCw } from "lucide-react";
import { api, ApiError, errorMessage } from "../../shared/api";
import {
  publicRequestTypes,
  publicRequestStatuses,
  type PublicRequestType,
  type PublicRequestStatus,
} from "../../shared/publicRequests";
import { Modal } from "../../shared/Modal";
import "./publicRequests.css";

type PublicRequest = {
  id: string;
  reference: string;
  type: PublicRequestType;
  status: PublicRequestStatus;
  name: string;
  email: string;
  subject: string;
  message: string;
  version: number;
  created_at: string;
  updated_at: string;
};
type RequestPage = { items: PublicRequest[]; page: number; has_more: boolean };
const timestamp = new Intl.DateTimeFormat("ro-RO", {
  day: "numeric",
  month: "short",
  year: "numeric",
  hour: "2-digit",
  minute: "2-digit",
});

export function PublicRequests({ onExpired }: { onExpired: () => void }) {
  const [data, setData] = useState<RequestPage | null>(null);
  const [page, setPage] = useState(1);
  const [status, setStatus] = useState("");
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState("");
  const [revision, setRevision] = useState(0);
  const [selected, setSelected] = useState<PublicRequest | null>(null);
  const expired = useRef(onExpired);
  expired.current = onExpired;
  useEffect(() => {
    const controller = new AbortController();
    const params = new URLSearchParams({ page: String(page) });
    if (status) params.set("status", status);
    setLoading(true);
    setError("");
    api<RequestPage>(`/admin/requests?${params}`, { signal: controller.signal })
      .then((result) => {
        if (!controller.signal.aborted) setData(result);
      })
      .catch((cause) => {
        if (controller.signal.aborted) return;
        setData(null);
        setError(errorMessage(cause));
        if (cause instanceof ApiError && [401, 403, 419].includes(cause.status))
          expired.current();
      })
      .finally(() => {
        if (!controller.signal.aborted) setLoading(false);
      });
    return () => controller.abort();
  }, [page, status, revision]);
  return (
    <section aria-labelledby="admin-requests-title" className="public-requests">
      <div className="section-header">
        <h2 id="admin-requests-title">Solicitări</h2>
        <button
          className="icon-button"
          aria-label="Actualizează solicitările"
          disabled={loading}
          onClick={() => setRevision((value) => value + 1)}
        >
          <RefreshCw size={18} className={loading ? "spin" : undefined} />
        </button>
      </div>
      <label className="field public-request-filter">
        Stare
        <SelectField
          value={status}
          onChange={(event) => {
            setStatus(event.target.value);
            setPage(1);
          }}
        >
          <option value="">Toate</option>
          {Object.entries(publicRequestStatuses).map(([value, label]) => (
            <option value={value} key={value}>
              {label}
            </option>
          ))}
        </SelectField>
      </label>
      {error && (
        <p className="error" role="alert">
          {error}
        </p>
      )}
      {loading && <p role="status">Se încarcă solicitările…</p>}
      {!loading && !error && !data?.items.length && (
        <p className="notice">Nicio solicitare.</p>
      )}
      {!!data?.items.length && (
        <div className="public-request-list" aria-busy={loading}>
          {data.items.map((item) => (
            <button
              key={item.id}
              className="public-request-row"
              onClick={() => setSelected(item)}
            >
              <span>
                <strong>{item.subject}</strong>
                <span>
                  {item.reference} · {publicRequestTypes[item.type]}
                </span>
                <span>
                  {item.name} · {timestamp.format(new Date(item.created_at))}
                </span>
              </span>
              <span className="public-request-status">
                {publicRequestStatuses[item.status]}
              </span>
            </button>
          ))}
        </div>
      )}
      {(page > 1 || data?.has_more) && (
        <div className="public-request-pagination">
          <button
            className="button secondary"
            disabled={loading || page === 1}
            onClick={() => setPage((value) => value - 1)}
          >
            <ChevronLeft size={17} /> Înapoi
          </button>
          <span>Pagina {page}</span>
          <button
            className="button secondary"
            disabled={loading || !data?.has_more}
            onClick={() => setPage((value) => value + 1)}
          >
            Înainte <ChevronRight size={17} />
          </button>
        </div>
      )}
      {selected && (
        <RequestDialog
          key={selected.id}
          request={selected}
          onClose={() => setSelected(null)}
          onSaved={(saved) => {
            setSelected(saved);
            setData((current) =>
              current
                ? {
                    ...current,
                    items: current.items.map((item) =>
                      item.id === saved.id ? saved : item,
                    ),
                  }
                : current,
            );
            setRevision((value) => value + 1);
          }}
        />
      )}
    </section>
  );
}

function RequestDialog({
  request,
  onClose,
  onSaved,
}: {
  request: PublicRequest;
  onClose: () => void;
  onSaved: (request: PublicRequest) => void;
}) {
  const [status, setStatus] = useState(request.status);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState("");
  const [stale, setStale] = useState(false);
  return (
    <Modal
      title={request.reference}
      onClose={() => {
        if (!busy) onClose();
      }}
    >
      <p>
        <strong>{request.subject}</strong>
      </p>
      <dl className="public-request-details">
        <dt>Tip</dt>
        <dd>{publicRequestTypes[request.type]}</dd>
        <dt>Nume</dt>
        <dd>{request.name}</dd>
        <dt>Email</dt>
        <dd>
          <a href={`mailto:${request.email}`}>{request.email}</a>
        </dd>
        <dt>Primită</dt>
        <dd>{timestamp.format(new Date(request.created_at))}</dd>
      </dl>
      <p className="public-request-message">{request.message}</p>
      <form
        onSubmit={async (event) => {
          event.preventDefault();
          if (busy || stale) return;
          setBusy(true);
          setError("");
          try {
            const saved = await api<PublicRequest>(
              `/admin/requests/${request.id}`,
              {
                method: "PATCH",
                body: JSON.stringify({ status, version: request.version }),
              },
            );
            onSaved(saved);
          } catch (cause) {
            setError(errorMessage(cause));
            if (cause instanceof ApiError && cause.status === 412)
              setStale(true);
          } finally {
            setBusy(false);
          }
        }}
      >
        <label className="field">
          Stare
          <SelectField
            disabled={busy || stale}
            value={status}
            onChange={(event) =>
              setStatus(event.target.value as PublicRequestStatus)
            }
          >
            {Object.entries(publicRequestStatuses).map(([value, label]) => (
              <option key={value} value={value}>
                {label}
              </option>
            ))}
          </SelectField>
        </label>
        {error && (
          <p role="alert" className="error">
            {error}
            {stale && " Închide și actualizează lista înainte de a reîncerca."}
          </p>
        )}
        <button
          className="button primary full"
          disabled={busy || stale || status === request.status}
        >
          {busy ? "Se salvează…" : "Salvează"}
        </button>
      </form>
      <p className="public-request-note">
        Schimbarea stării nu trimite un email și nu execută automat ștergerea
        datelor ori rambursarea.
      </p>
    </Modal>
  );
}
