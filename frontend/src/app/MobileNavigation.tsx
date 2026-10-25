import { useEffect, useRef, useState } from "react";
import {
  BookHeart,
  CalendarDays,
  ListTodo,
  CarFront,
  ChevronRight,
  House,
  MoreHorizontal,
  UserRound,
  Users,
  UtensilsCrossed,
  Wallet,
  Tv,
  PawPrint,
  Settings,
} from "lucide-react";
import { Modal } from "../shared/Modal";

type Page =
  | "home"
  | "family"
  | "kitchen"
  | "expenses"
  | "subscriptions"
  | "memory"
  | "calendar"
  | "tasks"
  | "pets"
  | "vehicles"
  | "admin";

export function MobileNavigation({
  page,
  onPage,
  onAccount,
  admin,
  hasFamily,
}: {
  page: Page;
  onPage: (page: Page) => void;
  onAccount: (returnFocus: HTMLElement | null) => void;
  admin: boolean;
  hasFamily: boolean;
}) {
  const [more, setMore] = useState(false);
  const moreTrigger = useRef<HTMLButtonElement>(null);
  useEffect(() => {
    const desktop = window.matchMedia("(min-width: 701px)");
    const close = () => {
      if (desktop.matches) setMore(false);
    };
    desktop.addEventListener("change", close);
    return () => desktop.removeEventListener("change", close);
  }, []);
  if (!hasFamily)
    return (
      <nav className="mobile-nav mobile-admin-nav" aria-label="Navigație mobil">
        {admin && (
          <button
            aria-current={page === "admin" ? "page" : undefined}
            className={page === "admin" ? "active" : ""}
            onClick={() => onPage("admin")}
          >
            <Settings size={21} aria-hidden="true" />
            <span>Administrare</span>
          </button>
        )}
        <button onClick={(event) => onAccount(event.currentTarget)}>
          <UserRound size={21} aria-hidden="true" />
          <span>Contul meu</span>
        </button>
      </nav>
    );
  return (
    <>
      <nav className="mobile-nav" aria-label="Navigație mobil">
        {(
          [
            ["home", "Acasă", "Acasă", House],
            ["family", "Familia mea", "Familie", Users],
            ["kitchen", "Mese", "Mese", UtensilsCrossed],
            ["expenses", "Cheltuieli", "Cheltuieli", Wallet],
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
          ref={moreTrigger}
          className={
            more ||
            page === "calendar" ||
            page === "tasks" ||
            page === "memory" ||
            page === "vehicles" ||
            page === "pets" ||
            page === "subscriptions" ||
            page === "admin"
              ? "active"
              : ""
          }
          aria-label="Mai multe"
          aria-current={
            page === "calendar" ||
            page === "tasks" ||
            page === "memory" ||
            page === "vehicles" ||
            page === "pets" ||
            page === "subscriptions" ||
            page === "admin"
              ? "page"
              : undefined
          }
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
          <nav className="more-menu" aria-label="Mai multe secțiuni">
            <button
              aria-current={page === "subscriptions" ? "page" : undefined}
              onClick={() => {
                setMore(false);
                onPage("subscriptions");
              }}
            >
              <Tv size={22} />
              <span>Abonamente</span>
              <ChevronRight size={18} />
            </button>
            <button
              aria-current={page === "calendar" ? "page" : undefined}
              onClick={() => {
                setMore(false);
                onPage("calendar");
              }}
            >
              <CalendarDays size={22} />
              <span>Calendar</span>
              <ChevronRight size={18} />
            </button>
            <button
              aria-current={page === "tasks" ? "page" : undefined}
              onClick={() => {
                setMore(false);
                onPage("tasks");
              }}
            >
              <ListTodo size={22} />
              <span>Sarcini</span>
              <ChevronRight size={18} />
            </button>
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
            {admin && (
              <button
                aria-current={page === "admin" ? "page" : undefined}
                onClick={() => {
                  setMore(false);
                  onPage("admin");
                }}
              >
                <Settings size={22} />
                <span>Administrare</span>
                <ChevronRight size={18} />
              </button>
            )}
            <button
              aria-current={page === "pets" ? "page" : undefined}
              onClick={() => {
                setMore(false);
                onPage("pets");
              }}
            >
              <PawPrint size={22} />
              <span>Animale</span>
              <ChevronRight size={18} />
            </button>
            <button
              onClick={() => {
                setMore(false);
                onAccount(moreTrigger.current);
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
