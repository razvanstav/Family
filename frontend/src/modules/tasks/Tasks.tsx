import { SelectField } from "../../shared/SelectField";
import { useEffect, useRef, useState } from "react";
import {
  CalendarDays,
  Check,
  ClipboardList,
  Ellipsis,
  LockKeyhole,
  Plus,
  RefreshCw,
  Repeat,
  UsersRound,
} from "lucide-react";
import { api, ApiError, errorMessage, type Family } from "../../shared/api";
import { bucharestToday, dateLabel } from "../../shared/calendarDates";
import { PageBackdrop } from "../../shared/PageBackdrop";
import { SectionTabs } from "../../shared/SectionTabs";
import { TaskForm } from "./TaskForm";
import { setTaskCompletion } from "./taskApi";
import {
  recurrenceLabels,
  taskCompletionMessage,
  type Assignee,
  type Task,
  type TaskPage,
  type TaskStatus,
  type TaskVisibility,
} from "./taskData";
import "./tasks.css";

const statuses = [
  { value: "open", label: "De făcut" },
  { value: "completed", label: "Finalizate" },
] as const;

export function Tasks({
  family,
  requestedTask,
  onTaskOpened,
  onChanged,
  onExpired,
}: {
  family: Family;
  requestedTask: string | null;
  onTaskOpened: () => void;
  onChanged: () => void;
  onExpired?: () => void;
}) {
  const [status, setStatus] = useState<TaskStatus>("open");
  const [visibility, setVisibility] = useState<TaskVisibility>("family");
  const [assigneeFilter, setAssigneeFilter] = useState<Assignee | null>(null);
  const [data, setData] = useState<(TaskPage & { scope: string }) | null>(null);
  const [loading, setLoading] = useState(true);
  const [moreBusy, setMoreBusy] = useState(false);
  const [completing, setCompleting] = useState<string | null>(null);
  const [error, setError] = useState("");
  const [stale, setStale] = useState(false);
  const [message, setMessage] = useState("");
  const [revision, setRevision] = useState(0);
  const [editor, setEditor] = useState<{ task: Task | null } | null>(null);
  const sequence = useRef(0);
  const morePending = useRef(false);
  const completionPending = useRef(false);
  const addButton = useRef<HTMLButtonElement>(null);
  const list = useRef<HTMLUListElement>(null);
  const afterRefresh = useRef<"add" | "list" | null>(null);
  const endpoint = `/families/${family.id}/tasks?status=${status}&visibility=${visibility}${assigneeFilter ? `&assignee_membership_id=${encodeURIComponent(assigneeFilter.id)}` : ""}`;
  const current = data?.scope === endpoint ? data : null;
  const canCreate = family.role !== "limited";
  const today = bucharestToday();

  function unavailable(error: unknown) {
    if (
      error instanceof ApiError &&
      [401, 403, 404, 419].includes(error.status)
    ) {
      setData(null);
      setEditor(null);
      onExpired?.();
    }
  }
  useEffect(() => {
    const request = ++sequence.current;
    const controller = new AbortController();
    setLoading(true);
    setError("");
    setStale(false);
    setMoreBusy(false);
    morePending.current = false;
    api<TaskPage>(endpoint, { signal: controller.signal })
      .then((result) => {
        if (request === sequence.current)
          setData({ ...result, scope: endpoint });
      })
      .catch((error) => {
        if (controller.signal.aborted || request !== sequence.current) return;
        setData(null);
        setError(errorMessage(error));
        unavailable(error);
      })
      .finally(() => {
        if (!controller.signal.aborted && request === sequence.current)
          setLoading(false);
      });
    return () => {
      controller.abort();
      sequence.current++;
    };
  }, [endpoint, revision]);
  useEffect(() => {
    if (!requestedTask || loading || !current) return;
    if (requestedTask === "new") {
      if (canCreate) {
        setMessage("");
        setEditor({ task: null });
      }
      onTaskOpened();
      return;
    }
    const controller = new AbortController();
    function open(task: Task) {
      setMessage("");
      setVisibility(task.visibility);
      setStatus(task.completed_at ? "completed" : "open");
      setAssigneeFilter(null);
      setEditor({ task });
      onTaskOpened();
    }
    const existing = current.items.find((task) => task.id === requestedTask);
    if (existing) open(existing);
    else
      api<Task>(`/families/${family.id}/tasks/${requestedTask}`, {
        signal: controller.signal,
      })
        .then((task) => {
          if (!controller.signal.aborted) open(task);
        })
        .catch((error) => {
          if (controller.signal.aborted) return;
          setError(
            error instanceof ApiError && [403, 404].includes(error.status)
              ? "Sarcina nu mai este disponibilă sau nu mai ai acces la ea."
              : errorMessage(error),
          );
          onTaskOpened();
          if (error instanceof ApiError && [401, 419].includes(error.status))
            unavailable(error);
        });
    return () => controller.abort();
  }, [requestedTask, loading, current, family.id]);
  useEffect(() => {
    if (loading || !current || !afterRefresh.current) return;
    const target =
      afterRefresh.current === "list"
        ? list.current?.querySelector<HTMLButtonElement>(
            'button[role="checkbox"]:not([disabled])',
          )
        : null;
    (target ?? addButton.current ?? list.current)?.focus();
    afterRefresh.current = null;
  }, [loading, current]);
  async function loadMore() {
    if (!current?.next_page || loading || morePending.current) return;
    const request = sequence.current;
    morePending.current = true;
    setMoreBusy(true);
    setError("");
    try {
      const result = await api<TaskPage>(
        `${endpoint}&page=${current.next_page}`,
      );
      if (request === sequence.current)
        setData((previous) =>
          previous
            ? {
                ...result,
                scope: endpoint,
                items: [
                  ...previous.items,
                  ...result.items.filter(
                    (item) =>
                      !previous.items.some(
                        (existing) => existing.id === item.id,
                      ),
                  ),
                ],
              }
            : { ...result, scope: endpoint },
        );
    } catch (error) {
      if (request !== sequence.current) return;
      setError(errorMessage(error));
      unavailable(error);
    } finally {
      if (request === sequence.current) {
        morePending.current = false;
        setMoreBusy(false);
      }
    }
  }
  async function complete(task: Task) {
    if (completionPending.current || stale) return;
    const request = sequence.current;
    completionPending.current = true;
    setCompleting(task.id);
    setError("");
    setMessage("");
    try {
      const saved = await setTaskCompletion(family.id, task);
      onChanged();
      if (request === sequence.current) {
        setMessage(taskCompletionMessage(saved));
        afterRefresh.current = "list";
      }
      setRevision((value) => value + 1);
    } catch (error) {
      if (request !== sequence.current) return;
      setError(errorMessage(error));
      if (error instanceof ApiError && error.status === 412) setStale(true);
      unavailable(error);
    } finally {
      completionPending.current = false;
      setCompleting(null);
    }
  }
  return (
    <section className="tasks page-enter">
      <PageBackdrop image="calendar">
        <div className="page-heading">
          <h1>Sarcini</h1>
          {canCreate && (
            <button
              ref={addButton}
              className="button primary"
              aria-label="Adaugă sarcină"
              disabled={!current || loading}
              onClick={() => {
                setMessage("");
                setEditor({ task: null });
              }}
            >
              <Plus size={19} />
              <span>Adaugă sarcină</span>
            </button>
          )}
        </div>
      </PageBackdrop>
      <SectionTabs
        label="Starea sarcinilor"
        value={status}
        options={statuses}
        onChange={(next) => {
          setStatus(next);
          setMessage("");
        }}
      />
      <div className="tasks-filters">
        <div className="tasks-toolbar" role="group" aria-label="Acces sarcini">
          <button
            type="button"
            aria-pressed={visibility === "family"}
            onClick={() => {
              setVisibility("family");
              setMessage("");
            }}
          >
            <UsersRound size={17} aria-hidden="true" />
            Familie
          </button>
          <button
            type="button"
            aria-pressed={visibility === "private"}
            onClick={() => {
              setVisibility("private");
              setMessage("");
            }}
          >
            <LockKeyhole size={17} aria-hidden="true" />
            Personale
          </button>
        </div>
        <label className="field tasks-assignee-filter">
          Responsabil
          <SelectField
            aria-label="Responsabil"
            value={assigneeFilter?.id ?? ""}
            onChange={(event) => {
              const id = event.target.value;
              setAssigneeFilter(
                data?.assignees.find((person) => person.id === id) ??
                  (id === family.membership_id
                    ? { id, name: "Ale mele" }
                    : null),
              );
              setMessage("");
            }}
          >
            <option value="">Toți</option>
            <option value={family.membership_id}>Ale mele</option>
            {data?.assignees
              .filter((person) => person.id !== family.membership_id)
              .map((person) => (
                <option key={person.id} value={person.id}>
                  {person.name}
                </option>
              ))}
            {assigneeFilter &&
              assigneeFilter.id !== family.membership_id &&
              !data?.assignees.some(
                (person) => person.id === assigneeFilter.id,
              ) && (
                <option value={assigneeFilter.id}>
                  {assigneeFilter.name}
                  {data ? " (fost membru)" : ""}
                </option>
              )}
          </SelectField>
        </label>
      </div>
      {message && (
        <p className="notice" role="status">
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
            onClick={() => setRevision((value) => value + 1)}
          >
            <RefreshCw size={17} />
            Reîncarcă lista
          </button>
        </div>
      )}
      {loading ? (
        <p className="tasks-loading" role="status">
          Se încarcă sarcinile…
        </p>
      ) : (
        current && (
          <>
            {current.items.length ? (
              <ul
                ref={list}
                tabIndex={-1}
                className="task-list"
                aria-label={
                  status === "open" ? "Sarcini de făcut" : "Sarcini finalizate"
                }
              >
                {current.items.map((task) => (
                  <li
                    key={task.id}
                    className={
                      task.completed_at
                        ? "task-row task-row-completed"
                        : "task-row"
                    }
                  >
                    <button
                      type="button"
                      role="checkbox"
                      aria-checked={task.completed_at !== null}
                      aria-label={`${task.completed_at ? "Redeschide" : "Finalizează"}: ${task.title}`}
                      className="task-check"
                      disabled={
                        !task.can_complete || completing !== null || stale
                      }
                      aria-busy={completing === task.id}
                      onClick={() => void complete(task)}
                    >
                      <span>
                        {task.completed_at && (
                          <Check size={17} aria-hidden="true" />
                        )}
                      </span>
                    </button>
                    <div className="task-copy">
                      <h2>{task.title}</h2>
                      <div className="task-meta">
                        <span>{task.assignee_name}</span>
                        {task.due_on && (
                          <span
                            className={
                              !task.completed_at && task.due_on < today
                                ? "task-overdue"
                                : ""
                            }
                          >
                            <CalendarDays size={14} aria-hidden="true" />
                            {dateLabel(task.due_on, {
                              day: "numeric",
                              month: "short",
                              year: "numeric",
                            })}
                            {!task.completed_at && task.due_on < today && (
                              <strong>Depășit</strong>
                            )}
                          </span>
                        )}
                        {task.recurrence && (
                          <span>
                            <Repeat size={14} aria-hidden="true" />
                            {recurrenceLabels[task.recurrence]}
                          </span>
                        )}
                      </div>
                    </div>
                    {task.can_edit && (
                      <button
                        type="button"
                        className="icon-button task-edit"
                        aria-label={`Editează: ${task.title}`}
                        disabled={completing !== null}
                        onClick={() => {
                          setMessage("");
                          setEditor({ task });
                        }}
                      >
                        <Ellipsis size={21} />
                      </button>
                    )}
                  </li>
                ))}
              </ul>
            ) : (
              <div className="tasks-empty">
                <ClipboardList size={30} aria-hidden="true" />
                <p>
                  {assigneeFilter
                    ? status === "completed"
                      ? "Nu sunt sarcini finalizate pentru acest responsabil."
                      : "Nu sunt sarcini de făcut pentru acest responsabil."
                    : status === "completed"
                      ? "Nu sunt sarcini finalizate."
                      : visibility === "private"
                        ? "Nu ai sarcini personale de făcut."
                        : "Nu sunt sarcini de făcut în familie."}
                </p>
              </div>
            )}
            {current.next_page && (
              <button
                className="button secondary tasks-more"
                disabled={moreBusy}
                onClick={() => void loadMore()}
              >
                {moreBusy ? "Se încarcă…" : "Mai multe sarcini"}
              </button>
            )}
          </>
        )
      )}
      {editor && (
        <TaskForm
          family={family}
          task={editor.task}
          assignees={data?.assignees ?? []}
          onClose={() => {
            setEditor(null);
            requestAnimationFrame(() =>
              (list.current ?? addButton.current)?.focus(),
            );
          }}
          onSaved={(saved, action) => {
            setEditor(null);
            onChanged();
            setMessage(
              action === "completion"
                ? saved
                  ? taskCompletionMessage(saved)
                  : "Sarcina a fost finalizată."
                : saved
                  ? "Sarcina a fost salvată."
                  : "Sarcina a fost ștearsă.",
            );
            if (saved) {
              setVisibility(saved.visibility);
              setStatus(saved.completed_at ? "completed" : "open");
              if (
                assigneeFilter &&
                assigneeFilter.id !== saved.assignee_membership_id
              ) {
                setAssigneeFilter({
                  id: saved.assignee_membership_id,
                  name: saved.assignee_name,
                });
              }
            }
            afterRefresh.current = "add";
            setRevision((value) => value + 1);
          }}
          onUnavailable={() => {
            setEditor(null);
            setData(null);
            setRevision((value) => value + 1);
            onExpired?.();
          }}
        />
      )}
    </section>
  );
}
