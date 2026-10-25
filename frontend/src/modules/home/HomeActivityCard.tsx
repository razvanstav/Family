import { useState } from "react";
import {
  Activity,
  BookHeart,
  CalendarDays,
  Car,
  CheckCheck,
  ChevronDown,
  ChevronRight,
  CreditCard,
  PawPrint,
  Receipt,
  UtensilsCrossed,
  type LucideIcon,
} from "lucide-react";
import {
  activityActions,
  type ActivityTarget,
  type HomeActivity,
} from "./homeActivity";

const activityIcons: Record<ActivityTarget["kind"], LucideIcon> = {
  task: CheckCheck,
  expense: Receipt,
  meal: UtensilsCrossed,
  recipe: UtensilsCrossed,
  memory: BookHeart,
  calendar: CalendarDays,
  vehicle: Car,
  vehicle_entry: Car,
  pet: PawPrint,
  pet_entry: PawPrint,
  subscription: CreditCard,
};
const activityTime = new Intl.DateTimeFormat("ro-RO", {
  hour: "2-digit",
  minute: "2-digit",
  timeZone: "Europe/Bucharest",
});

export function HomeActivityCard({
  items,
  onOpen,
}: {
  items: HomeActivity[] | null;
  onOpen: (target: ActivityTarget) => void;
}) {
  const [expanded, setExpanded] = useState(false);
  return (
    <section
      className="home-section home-activity"
      aria-labelledby="home-activity"
    >
      <div className="section-header">
        <h2 id="home-activity">
          <span className="home-section-icon home-icon--activity">
            <Activity size={20} aria-hidden="true" />
          </span>
          Activitatea de azi
        </h2>
      </div>
      {items &&
        (items.length ? (
          <>
            <ul className="home-rows" id="home-activity-items">
              {(expanded ? items : items.slice(0, 5)).map((item) => {
                const Icon = activityIcons[item.target.kind];
                return (
                  <li key={item.id}>
                    <button
                      className="home-row home-activity-row"
                      onClick={() => onOpen(item.target)}
                    >
                      <span
                        className={`home-activity-icon home-activity-icon--${item.target.kind}`}
                      >
                        <Icon size={20} aria-hidden="true" />
                      </span>
                      <span className="home-activity-copy">
                        <span className="home-activity-action">
                          <strong>{item.actor_name}</strong>{" "}
                          {activityActions[item.action]}
                        </span>
                        <span className="home-activity-title">
                          {item.title}
                        </span>
                      </span>
                      <time dateTime={item.occurred_at}>
                        {activityTime.format(new Date(item.occurred_at))}
                      </time>
                      <ChevronRight size={16} aria-hidden="true" />
                    </button>
                  </li>
                );
              })}
            </ul>
            {items.length > 5 && (
              <button
                className="text-button home-activity-more"
                aria-expanded={expanded}
                aria-controls="home-activity-items"
                onClick={() => setExpanded((value) => !value)}
              >
                {expanded
                  ? "Arată mai puține"
                  : `Arată încă ${items.length - 5}`}
                <ChevronDown size={16} aria-hidden="true" />
              </button>
            )}
            {expanded && items.length === 20 && (
              <p className="home-activity-limit">
                Cele mai recente 20 de acțiuni de azi.
              </p>
            )}
          </>
        ) : (
          <p className="home-empty">Nicio activitate astăzi.</p>
        ))}
    </section>
  );
}
