import { useEffect, useRef, useState } from "react";
import { api, ApiError, errorMessage } from "../../shared/api";
import "./administrationOverview.css";

type AdministrationStats = {
  users: { total: number; last_30_days: number };
  families: { total: number };
  email: {
    total: number;
    sent: number;
    failed: number;
    last_30_days: number;
  };
  recent_emails: {
    id: number;
    recipient: string;
    subject: string;
    status: "sent" | "failed";
    sent_at: string | null;
    created_at: string;
  }[];
};

const numberFormat = new Intl.NumberFormat("ro-RO");
const timestampFormat = new Intl.DateTimeFormat("ro-RO", {
  day: "numeric",
  month: "short",
  year: "numeric",
  hour: "2-digit",
  minute: "2-digit",
});

export function AdministrationOverview({
  onExpired,
}: {
  onExpired: () => void;
}) {
  const [data, setData] = useState<AdministrationStats | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState("");
  const [revision, setRevision] = useState(0);
  const onExpiredRef = useRef(onExpired);

  useEffect(() => {
    onExpiredRef.current = onExpired;
  }, [onExpired]);

  useEffect(() => {
    const controller = new AbortController();
    setData(null);
    setLoading(true);
    setError("");
    api<AdministrationStats>("/admin/overview", {
      signal: controller.signal,
    })
      .then((result) => {
        if (!controller.signal.aborted) setData(result);
      })
      .catch((error) => {
        if (controller.signal.aborted) return;
        setData(null);
        setError(errorMessage(error));
        if (error instanceof ApiError && [401, 403, 419].includes(error.status))
          onExpiredRef.current();
      })
      .finally(() => {
        if (!controller.signal.aborted) setLoading(false);
      });
    return () => controller.abort();
  }, [revision]);

  if (loading) return <p role="status">Se încarcă statisticile…</p>;

  if (!data)
    return (
      <div>
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
    );

  return (
    <div className="admin-overview">
      <dl className="admin-overview-stats" aria-label="Statistici aplicație">
        <div className="admin-overview-stat">
          <dt>Utilizatori</dt>
          <dd>{numberFormat.format(data.users.total)}</dd>
          <dd className="admin-overview-stat-detail">
            {numberFormat.format(data.users.last_30_days)}{" "}
            {data.users.last_30_days === 1 ? "nou" : "noi"} în ultimele 30 de
            zile
          </dd>
        </div>
        <div className="admin-overview-stat">
          <dt>Familii</dt>
          <dd>{numberFormat.format(data.families.total)}</dd>
        </div>
        <div className="admin-overview-stat">
          <dt>Emailuri trimise</dt>
          <dd>{numberFormat.format(data.email.sent)}</dd>
        </div>
        <div className="admin-overview-stat">
          <dt>Emailuri eșuate</dt>
          <dd>{numberFormat.format(data.email.failed)}</dd>
        </div>
      </dl>

      <section
        className="admin-overview-emails"
        aria-labelledby="admin-overview-emails-title"
      >
        <div className="section-header">
          <h2 id="admin-overview-emails-title">Ultimele emailuri</h2>
        </div>
        <p className="admin-overview-email-count">
          {numberFormat.format(data.email.total)}{" "}
          {data.email.total === 1 ? "înregistrat" : "înregistrate"} ·{" "}
          {numberFormat.format(data.email.last_30_days)} în ultimele 30 de zile
        </p>
        <p className="admin-overview-email-help">
          „Trimis” înseamnă acceptat de serverul SMTP. Livrarea în inbox nu este
          confirmată.
        </p>
        {data.recent_emails.length === 0 ? (
          <p className="admin-overview-empty">
            Nu există emailuri înregistrate.
          </p>
        ) : (
          <ul className="admin-overview-email-list">
            {data.recent_emails.slice(0, 10).map((email) => {
              const timestamp = email.sent_at ?? email.created_at;
              return (
                <li key={email.id}>
                  <div className="admin-overview-email-content">
                    <strong>{email.subject}</strong>
                    <span>{email.recipient}</span>
                  </div>
                  <div className="admin-overview-email-meta">
                    <span
                      className={`admin-overview-email-status ${email.status}`}
                    >
                      {email.status === "sent" ? "Trimis" : "Eșuat"}
                    </span>
                    <time dateTime={timestamp}>
                      {timestampFormat.format(new Date(timestamp))}
                    </time>
                  </div>
                </li>
              );
            })}
          </ul>
        )}
      </section>
    </div>
  );
}
