import { useEffect, useRef, useState } from "react";
import { CalendarDays, ClipboardList, Plus, RefreshCw } from "lucide-react";
import { ApiError, errorMessage, type Family } from "../../shared/api";
import { dateLabel } from "../../shared/calendarDates";
import { taskCompletionMessage, type Task } from "./taskData";
import { setTaskCompletion } from "./taskApi";
import "./tasks.css";

export function TaskHomeCard({
  family,
  tasks,
  today,
  loading,
  onChanged,
  onNew,
  onAll,
  onExpired,
}: {
  family: Family;
  tasks: Task[] | null;
  today: string;
  loading: boolean;
  onChanged: () => void;
  onNew: () => void;
  onAll: () => void;
  onExpired: () => void;
}) {
  const [busy, setBusy] = useState<string | null>(null);
  const [error, setError] = useState("");
  const [message, setMessage] = useState("");
  const [stale, setStale] = useState(false);
  const pending = useRef(false);
  const active = useRef(true);
  const list = useRef<HTMLUListElement>(null);
  const allButton = useRef<HTMLButtonElement>(null);
  const focusAfterRefresh = useRef(false);
  useEffect(() => {
    active.current = true;
    return () => {
      active.current = false;
    };
  }, []);
  useEffect(() => {
    if (loading) return;
    setStale(false);
    if (tasks) setError("");
    if (focusAfterRefresh.current) {
      (
        list.current?.querySelector<HTMLButtonElement>(
          'button[role="checkbox"]:not([disabled])',
        ) ?? allButton.current
      )?.focus();
      focusAfterRefresh.current = false;
    }
  }, [tasks, loading]);
  async function complete(task: Task) {
    if (pending.current || stale || loading) return;
    pending.current = true;
    setBusy(task.id);
    setError("");
    setMessage("");
    try {
      const saved = await setTaskCompletion(family.id, task);
      if (!active.current) return;
      setMessage(taskCompletionMessage(saved));
      focusAfterRefresh.current = true;
      onChanged();
    } catch (error) {
      if (!active.current) return;
      setError(errorMessage(error));
      if (error instanceof ApiError) {
        if (error.status === 412) setStale(true);
        if ([401, 403, 404, 419].includes(error.status)) onExpired();
      }
    } finally {
      pending.current = false;
      if (active.current) setBusy(null);
    }
  }
  return (
    <>
      <div className="section-header">
        <h2>
          <span className="task-home-icon">
            <ClipboardList size={20} aria-hidden="true" />
          </span>
          Sarcini
        </h2>
      </div>
      {message && (
        <p role="status" className="notice">
          {message}
        </p>
      )}
      {error && (
        <div className="tasks-error">
          <p className="error" role="alert">
            {error}
          </p>
          <button
            className="button secondary"
            disabled={loading || busy !== null}
            onClick={onChanged}
          >
            <RefreshCw size={17} aria-hidden="true" />
            Reîncarcă sarcinile
          </button>
        </div>
      )}
      {tasks ? (
        tasks.length ? (
          <ul
            ref={list}
            className="task-list task-home-list"
            aria-label="Sarcini de făcut"
          >
            {tasks.map((task) => (
              <li className="task-row" key={task.id}>
                <button
                  type="button"
                  role="checkbox"
                  aria-checked={false}
                  aria-label={`Finalizează: ${task.title}`}
                  className="task-check"
                  disabled={
                    !task.can_complete || busy !== null || stale || loading
                  }
                  aria-busy={busy === task.id}
                  onClick={() => void complete(task)}
                >
                  <span />
                </button>
                <div className="task-copy">
                  <h3>{task.title}</h3>
                  <div className="task-meta">
                    <span>{task.assignee_name}</span>
                    {task.due_on && (
                      <span
                        className={task.due_on < today ? "task-overdue" : ""}
                      >
                        <CalendarDays size={14} aria-hidden="true" />
                        <time dateTime={task.due_on}>
                          {dateLabel(task.due_on, {
                            day: "numeric",
                            month: "short",
                            year: "numeric",
                          })}
                        </time>
                        {task.due_on < today && <strong>Depășit</strong>}
                      </span>
                    )}
                  </div>
                </div>
              </li>
            ))}
          </ul>
        ) : (
          <p className="task-home-empty">Nu sunt sarcini de făcut.</p>
        )
      ) : loading ? (
        <p role="status" className="task-home-empty">
          Se încarcă sarcinile…
        </p>
      ) : null}
      <div className="task-home-actions">
        {family.role !== "limited" && (
          <button
            className="button primary"
            disabled={busy !== null}
            onClick={onNew}
          >
            <Plus size={17} aria-hidden="true" />
            Adaugă sarcină
          </button>
        )}
        <button
          ref={allButton}
          className="button secondary"
          disabled={busy !== null}
          onClick={onAll}
        >
          Toate sarcinile
        </button>
      </div>
    </>
  );
}
