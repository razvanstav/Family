import { useEffect, useState } from "react";
import {
  BookHeart,
  CalendarDays,
  CarFront,
  ChevronRight,
  House,
  MoreHorizontal,
  UserRound,
  Users,
  UtensilsCrossed,
} from "lucide-react";
import { Modal } from "../shared/Modal";

type Page = "home" | "family" | "kitchen" | "memory" | "calendar" | "vehicles";

export function MobileNavigation({
  page,
  onPage,
  onAccount,
}: {
  page: Page;
  onPage: (page: Page) => void;
  onAccount: () => void;
}) {
  const [more, setMore] = useState(false);
  useEffect(() => {
    const desktop = window.matchMedia("(min-width: 701px)");
    const close = () => {
      if (desktop.matches) setMore(false);
    };
    desktop.addEventListener("change", close);
    return () => desktop.removeEventListener("change", close);
  }, []);
  return (
    <>
      <nav className="mobile-nav" aria-label="Navigație mobil">
        {(
          [
            ["home", "Acasă", "Acasă", House],
            ["family", "Familia mea", "Familie", Users],
            ["kitchen", "Bucătărie", "Bucătărie", UtensilsCrossed],
            ["calendar", "Calendar", "Calendar", CalendarDays],
          ] as const
        ).map(([value, label, text, Icon]) => (
          <button
            key={value}
            aria-label={label}
            aria-current={page === value ? "page" : undefined}
            className={page === value ? "active" : ""}
            onClick={() => onPage(value)}
          >
            <Icon size={21} aria-hidden="true" />
            <span>{text}</span>
          </button>
        ))}
        <button
          className={
            more || page === "memory" || page === "vehicles" ? "active" : ""
          }
          aria-label="Mai multe"
          aria-haspopup="dialog"
          aria-expanded={more}
          onClick={() => setMore(true)}
        >
          <MoreHorizontal size={23} aria-hidden="true" />
          <span>Mai multe</span>
        </button>
      </nav>
      {more && (
        <Modal title="Mai multe" onClose={() => setMore(false)}>
          <nav className="mobile-more-menu" aria-label="Mai multe secțiuni">
            <button
              aria-current={page === "memory" ? "page" : undefined}
              onClick={() => {
                setMore(false);
                onPage("memory");
              }}
            >
              <BookHeart size={22} />
              <span>Amintiri</span>
              <ChevronRight size={18} />
            </button>
            <button
              aria-current={page === "vehicles" ? "page" : undefined}
              onClick={() => {
                setMore(false);
                onPage("vehicles");
              }}
            >
              <CarFront size={22} />
              <span>Auto</span>
              <ChevronRight size={18} />
            </button>
            <button
              onClick={() => {
                setMore(false);
                onAccount();
              }}
            >
              <UserRound size={22} />
              <span>Contul meu</span>
              <ChevronRight size={18} />
            </button>
          </nav>
        </Modal>
      )}
    </>
  );
}
