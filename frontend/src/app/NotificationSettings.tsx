import { useEffect, useRef, useState, type FormEvent } from "react";
import {
  ArrowLeft,
  BookHeart,
  Cake,
  CalendarDays,
  Clock3,
  ListTodo,
} from "lucide-react";
import { api, ApiError, errorMessage } from "../shared/api";
import { Modal } from "../shared/Modal";
import "./notificationSettings.css";

const categories = {
  calendar: {
    label: "Calendar",
    hint: "La momentul ales în eveniment.",
    icon: CalendarDays,
  },
  tasks: {
    label: "Sarcini",
    hint: "În ziua termenului, la 09:00.",
    icon: ListTodo,
  },
  birthdays: {
    label: "Aniversări",
    hint: "La momentul ales pentru fiecare persoană.",
    icon: Cake,
  },
  deadlines: {
    label: "Scadențe",
    hint: "Auto și animale: la momentul ales. Documente: cu 7 zile înainte, la 09:00.",
    icon: Clock3,
  },
  memories: {
    label: "Amintiri",
    hint: "În ziua asta, la 09:00, pentru amintiri din anii trecuți.",
    icon: BookHeart,
  },
} as const;

type NotificationSetting = {
  category: keyof typeof categories;
  in_app: boolean;
  email: boolean;
};
type NotificationSettingsData = {
  version: number;
  items: NotificationSetting[];
};

const endpoint = "/account/notification-settings";

export function NotificationSettings({
  onBack,
  onClose,
  onExpired,
  returnFocus,
}: {
  onBack: () => void;
  onClose: () => void;
  onExpired: () => void;
  returnFocus: HTMLElement | null;
}) {
  const [saved, setSaved] = useState<NotificationSettingsData | null>(null);
  const [items, setItems] = useState<NotificationSetting[]>([]);
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [conflict, setConflict] = useState(false);
  const [error, setError] = useState("");
  const [notice, setNotice] = useState("");
  const pending = useRef<AbortController | null>(null);
  const busy = loading || saving;
  const dirty =
    saved !== null &&
    (saved.version === 0 ||
      items.some((item) => {
        const previous = saved.items.find(
          (entry) => entry.category === item.category,
        );
        return (
          item.in_app !== previous?.in_app || item.email !== previous?.email
        );
      }));

  async function load() {
    if (pending.current) return;
    const controller = new AbortController();
    pending.current = controller;
    setLoading(true);
    setError("");
    setNotice("");
    try {
      const current = await api<NotificationSettingsData>(endpoint, {
        signal: controller.signal,
      });
      if (controller.signal.aborted) return;
      setSaved(current);
      setItems(current.items);
      setConflict(false);
    } catch (cause) {
      if (controller.signal.aborted) return;
      if (cause instanceof ApiError && [401, 419].includes(cause.status)) {
        onExpired();
      } else setError(errorMessage(cause));
    } finally {
      if (pending.current === controller) pending.current = null;
      if (!controller.signal.aborted) {
        setLoading(false);
      }
    }
  }

  useEffect(() => {
    void load();
    return () => {
      const controller = pending.current;
      pending.current = null;
      controller?.abort();
    };
  }, []);

  function change(
    category: NotificationSetting["category"],
    channel: "in_app" | "email",
    enabled: boolean,
  ) {
    setItems((current) =>
      current.map((item) =>
        item.category === category ? { ...item, [channel]: enabled } : item,
      ),
    );
    setNotice("");
  }

  async function save(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    if (!saved || busy || conflict || !dirty || pending.current) return;
    const controller = new AbortController();
    pending.current = controller;
    setSaving(true);
    setError("");
    setNotice("");
    try {
      const current = await api<NotificationSettingsData>(endpoint, {
        method: "PUT",
        headers: { "If-Match": `"${saved.version}"` },
        body: JSON.stringify({ items }),
        signal: controller.signal,
      });
      if (controller.signal.aborted) return;
      setSaved(current);
      setItems(current.items);
      setNotice("Setările au fost salvate.");
    } catch (cause) {
      if (controller.signal.aborted) return;
      if (cause instanceof ApiError && [401, 419].includes(cause.status)) {
        onExpired();
      } else if (
        cause instanceof ApiError &&
        [409, 412].includes(cause.status)
      ) {
        setConflict(true);
        setError(
          "Setările s-au schimbat între timp. Reîncărcarea înlocuiește modificările nesalvate.",
        );
      } else setError(errorMessage(cause));
    } finally {
      if (pending.current === controller) pending.current = null;
      if (!controller.signal.aborted) {
        setSaving(false);
      }
    }
  }

  return (
    <Modal
      returnFocus={returnFocus}
      title="Setări"
      onClose={() => {
        if (!saving) onClose();
      }}
      wide
    >
      <div className="notification-settings">
        <button
          className="text-button notification-settings-back"
          disabled={saving}
          onClick={onBack}
        >
          <ArrowLeft size={17} aria-hidden="true" />
          Contul meu
        </button>
        <h3 id="notification-settings-heading">Notificări</h3>
        <p className="notification-settings-scope">
          Preferințele se aplică tuturor familiilor tale. Orele sunt ale
          României.
        </p>
        {loading && !saved && <p role="status">Se încarcă setările…</p>}
        <form onSubmit={(event) => void save(event)} aria-busy={busy}>
          {saved && (
            <table
              className="notification-settings-table"
              aria-labelledby="notification-settings-heading"
            >
              <thead>
                <tr>
                  <td />
                  <th scope="col">În aplicație</th>
                  <th scope="col">Email</th>
                </tr>
              </thead>
              <tbody>
                {items.map((item) => {
                  const { label, hint, icon: Icon } = categories[item.category];
                  const description = `notification-setting-${item.category}`;
                  return (
                    <tr key={item.category}>
                      <th scope="row">
                        <span className="notification-setting-name">
                          <Icon
                            size={18}
                            className={`notification-setting-icon--${item.category}`}
                            aria-hidden="true"
                          />
                          {label}
                        </span>
                        <span
                          id={description}
                          className="notification-setting-hint"
                        >
                          {hint}
                        </span>
                      </th>
                      {(["in_app", "email"] as const).map((channel) => (
                        <td key={channel}>
                          <label className="notification-setting-control">
                            <input
                              type="checkbox"
                              checked={item[channel]}
                              disabled={busy || conflict}
                              aria-label={`${label} — ${channel === "in_app" ? "În aplicație" : "Email"}`}
                              aria-describedby={description}
                              onChange={(event) =>
                                change(
                                  item.category,
                                  channel,
                                  event.target.checked,
                                )
                              }
                            />
                          </label>
                        </td>
                      ))}
                    </tr>
                  );
                })}
              </tbody>
            </table>
          )}
          {error && (
            <p className="error" role="alert">
              {error}
            </p>
          )}
          {notice && (
            <p className="notice" role="status">
              {notice}
            </p>
          )}
          <div className="notification-settings-actions">
            {(!saved || conflict) && !loading && (
              <button
                type="button"
                className="button secondary"
                disabled={busy}
                onClick={() => void load()}
              >
                {conflict ? "Reîncarcă setările" : "Reîncearcă"}
              </button>
            )}
            {saved && (
              <button
                className="button primary"
                disabled={busy || conflict || !dirty}
              >
                {saving ? "Se salvează…" : "Salvează"}
              </button>
            )}
          </div>
        </form>
      </div>
    </Modal>
  );
}
