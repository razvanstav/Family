import type { ReactNode } from "react";
import { LockKeyhole } from "lucide-react";
import { visibilityLabel } from "../../shared/api";

export function KitchenCard({
  title,
  visibility,
  ariaLabel,
  photo,
  children,
  onOpen,
}: {
  title: string;
  visibility: keyof typeof visibilityLabel;
  ariaLabel: string;
  photo: ReactNode;
  children: ReactNode;
  onOpen: () => void;
}) {
  return (
    <button
      type="button"
      className="recipe-card"
      onClick={onOpen}
      aria-label={ariaLabel}
    >
      {photo}
      <div className="recipe-card-body">
        <div className="recipe-card-title">
          <h3>{title}</h3>
          {visibility !== "family" && (
            <LockKeyhole size={16} aria-label={visibilityLabel[visibility]} />
          )}
        </div>
        {children}
      </div>
    </button>
  );
}
