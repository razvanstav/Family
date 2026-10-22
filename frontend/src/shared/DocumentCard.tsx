import {
  AlertCircle,
  Check,
  Clock3,
  FileText,
  FolderHeart,
  HeartPulse,
  House,
  Image,
  LockKeyhole,
  Users,
} from "lucide-react";
import { visibilityLabel, type DocumentSummary } from "./api";
import "./documentCards.css";

export const categories = [
  { id: "all", label: "Toate", icon: FolderHeart },
  { id: "personal", label: "Personale", icon: FileText },
  { id: "home", label: "Casă", icon: House },
  { id: "health", label: "Sănătate", icon: HeartPulse },
  { id: "memories", label: "Amintiri", icon: Image },
];
const categoryPhotos: Record<string, string> = {
  personal: new URL("../assets/categories/personal.webp", import.meta.url).href,
  home: new URL("../assets/categories/home.webp", import.meta.url).href,
  health: new URL("../assets/categories/health.webp", import.meta.url).href,
  memories: new URL("../assets/categories/memories.webp", import.meta.url).href,
};
export const categoryName = (id: string) =>
  categories.find((category) => category.id === id)?.label ?? id;
export function FilePreview({
  mime,
  category,
}: {
  mime: string;
  category: string;
}) {
  const Icon = mime === "application/pdf" ? FileText : Image;
  return (
    <div className={`file-preview color-${category}`} aria-hidden="true">
      <Icon size={36} strokeWidth={1.25} />
    </div>
  );
}
export function DocumentCard({
  document: doc,
  onOpen,
}: {
  document: DocumentSummary;
  onOpen: (id: string) => void;
}) {
  return (
    <button
      className="document-card"
      aria-label={`${doc.title} · ${categoryName(doc.category)} · ${visibilityLabel[doc.visibility]}${doc.status === "quarantine" ? " · În verificare" : doc.status === "rejected" ? " · Respins" : ""}`}
      onClick={() => onOpen(doc.id)}
    >
      <div className="card-top">
        <FilePreview mime={doc.mime} category={doc.category} />
        <span className="category-label">{categoryName(doc.category)}</span>
        <span
          className="mini-lock"
          aria-label={visibilityLabel[doc.visibility]}
        >
          {doc.visibility === "private" ? (
            <LockKeyhole size={15} />
          ) : (
            <Users size={15} />
          )}
        </span>
      </div>
      <div className="card-bottom">
        <h3 title={doc.title}>{doc.title}</h3>
        {doc.status === "ready" ? (
          <p>{doc.mime === "application/pdf" ? "PDF" : "Imagine"}</p>
        ) : (
          <p className={`card-status ${doc.status}`}>
            {doc.status === "quarantine" ? (
              <>
                <Clock3 size={13} />
                În verificare
              </>
            ) : (
              <>
                <AlertCircle size={13} />
                Respins
              </>
            )}
          </p>
        )}
      </div>
    </button>
  );
}
export function DocumentCategories({
  selected,
  onSelect,
  categoryIds,
}: {
  selected?: string;
  onSelect: (id: string) => void;
  categoryIds?: readonly string[];
}) {
  return (
    <div
      className="categories"
      aria-label="Categorii"
      style={
        categoryIds
          ? {
              gridTemplateColumns: `repeat(${categoryIds.length}, minmax(0, 1fr))`,
            }
          : undefined
      }
    >
      {categories
        .filter(
          ({ id }) =>
            id !== "all" && (!categoryIds || categoryIds.includes(id)),
        )
        .map(({ id, label, icon: Icon }) => (
          <button
            key={id}
            className={`category color-${id} ${selected === id ? "selected" : ""}`}
            aria-pressed={selected === undefined ? undefined : selected === id}
            onClick={() => onSelect(id)}
          >
            <img src={categoryPhotos[id]} alt="" width="768" height="512" />
            <span className="category-caption">
              <Icon size={16} strokeWidth={1.7} aria-hidden="true" />
              {label}
            </span>
            {selected === id && (
              <Check className="category-check" size={15} aria-hidden="true" />
            )}
          </button>
        ))}
    </div>
  );
}
