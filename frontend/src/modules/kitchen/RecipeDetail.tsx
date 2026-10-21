import { useEffect, useRef, useState } from "react";
import {
  LockKeyhole,
  Users,
  UtensilsCrossed,
  Pencil,
  History,
} from "lucide-react";
import { api, ApiError, visibilityLabel } from "../../shared/api";
import { Modal } from "../../shared/Modal";
import { recipeCategories, type Recipe, type RecipeList } from "./recipeTypes";
import { KitchenPhoto } from "./KitchenPhoto";
import { RecipeMeta } from "./RecipeMeta";
import { RecipeForm } from "./RecipeForm";

const versionDate = new Intl.DateTimeFormat("ro-RO", {
  dateStyle: "medium",
  timeStyle: "short",
  timeZone: "Europe/Bucharest",
});

export function RecipeDetail({
  recipe,
  base,
  userId,
  canAddMeal,
  readOnly = false,
  onMeal,
  onClose,
  onChanged,
  onExpired,
}: {
  recipe: Recipe;
  base: string;
  userId: number;
  canAddMeal: boolean;
  readOnly?: boolean;
  onMeal: (recipe: Recipe) => void;
  onClose: () => void;
  onChanged: (recipe: Recipe) => void;
  onExpired: () => void;
}) {
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState("");
  const [editing, setEditing] = useState(false);
  const [history, setHistory] = useState<RecipeList | null>(null);
  const [showHistory, setShowHistory] = useState(false);
  const [viewed, setViewed] = useState<Recipe | null>(null);
  const request = useRef<AbortController | null>(null);
  useEffect(() => () => request.current?.abort(), []);
  const current = viewed ?? recipe;
  const isCurrent = current.version === recipe.current_version;
  function expired(e: unknown): boolean {
    if (e instanceof ApiError && [401, 403, 404, 419].includes(e.status)) {
      onClose();
      onExpired();
      return true;
    }
    return false;
  }
  async function loadHistory(page = 1) {
    request.current?.abort();
    const controller = new AbortController();
    request.current = controller;
    setBusy(true);
    setError("");
    setShowHistory(true);
    try {
      const data = await api<RecipeList>(
        `${base}/${recipe.id}/versions?page=${page}`,
        { signal: controller.signal },
      );
      if (controller.signal.aborted) return;
      setHistory(data);
      if (page === 1 && data.items[0]) onChanged(data.items[0]);
    } catch (e) {
      if (!controller.signal.aborted && !expired(e))
        setError("Istoricul nu a putut fi încărcat. Reîncearcă.");
    } finally {
      if (!controller.signal.aborted) setBusy(false);
    }
  }
  async function retry() {
    const controller = new AbortController();
    request.current = controller;
    setBusy(true);
    setError("");
    try {
      const updated = await api<Recipe>(`${base}/${recipe.id}/photo/scan`, {
        method: "POST",
        signal: controller.signal,
      });
      if (!controller.signal.aborted) {
        setViewed(null);
        onChanged(updated);
      }
    } catch (e) {
      if (!controller.signal.aborted && !expired(e))
        setError("Fotografia nu a putut fi verificată. Reîncearcă.");
    } finally {
      if (!controller.signal.aborted) setBusy(false);
    }
  }
  if (editing)
    return (
      <RecipeForm
        recipe={recipe}
        base={base}
        onClose={() => setEditing(false)}
        onExpired={onExpired}
        onReloaded={onChanged}
        onSaved={(saved) => {
          onChanged(saved);
          setViewed(null);
          setHistory(null);
          setShowHistory(false);
          setEditing(false);
        }}
      />
    );
  return (
    <Modal title={current.title} onClose={onClose} wide>
      <div className="recipe-detail">
        <div className="recipe-version-toolbar">
          <p>
            Versiunea {current.version} ·{" "}
            {versionDate.format(new Date(current.version_created_at))}
          </p>
          {!readOnly && (
            <div className="recipe-actions">
              {canAddMeal && recipe.owner_id === userId && isCurrent && (
                <button
                  className="button secondary"
                  disabled={busy}
                  onClick={() => setEditing(true)}
                >
                  <Pencil size={16} />
                  Editează
                </button>
              )}
              <button
                className="button secondary"
                aria-expanded={showHistory}
                disabled={busy}
                onClick={() =>
                  showHistory ? setShowHistory(false) : void loadHistory()
                }
              >
                <History size={16} />
                Istoric
              </button>
            </div>
          )}
        </div>
        {readOnly && <p className="notice">Rețeta folosită la masă.</p>}
        {!readOnly && !isCurrent && (
          <p className="notice">
            Versiune anterioară.{" "}
            <button className="text-button" onClick={() => setViewed(null)}>
              Vezi versiunea curentă
            </button>
          </p>
        )}
        {showHistory && (
          <section
            className="recipe-history"
            aria-label="Istoricul versiunilor"
            aria-busy={busy}
          >
            <h3>Istoric</h3>
            {busy && <p role="status">Se încarcă…</p>}
            {history?.items.map((item) => (
              <button
                key={item.recipe_version_id}
                className="recipe-history-row"
                aria-pressed={
                  item.recipe_version_id === current.recipe_version_id
                }
                onClick={() => setViewed(item)}
              >
                <span>
                  <strong>
                    Versiunea {item.version}
                    {item.version === item.current_version ? " · curentă" : ""}
                  </strong>
                  <span>{item.title}</span>
                </span>
                <time dateTime={item.version_created_at}>
                  {versionDate.format(new Date(item.version_created_at))}
                </time>
              </button>
            ))}
            {history && (history.page > 1 || history.has_more) && (
              <div className="recipe-pagination">
                <button
                  className="button secondary"
                  disabled={busy || history.page === 1}
                  onClick={() => void loadHistory(history.page - 1)}
                >
                  Înapoi
                </button>
                <span>Pagina {history.page}</span>
                <button
                  className="button secondary"
                  disabled={busy || !history.has_more}
                  onClick={() => void loadHistory(history.page + 1)}
                >
                  Înainte
                </button>
              </div>
            )}
            {error && (
              <button
                className="button secondary"
                onClick={() => void loadHistory(history?.page ?? 1)}
              >
                Reîncearcă istoricul
              </button>
            )}
          </section>
        )}
        <KitchenPhoto resource={current} base={base} />
        <div className="recipe-detail-meta">
          <RecipeMeta recipe={current} />
          <span>
            <Users size={16} aria-hidden="true" />
            {current.servings} porții
          </span>
        </div>
        <div className="recipe-tags">
          <span className={`recipe-category ${current.category}`}>
            {recipeCategories[current.category]}
          </span>
          <span className="recipe-access">
            {recipe.visibility === "private" ? (
              <LockKeyhole size={13} />
            ) : (
              <Users size={13} />
            )}
            {visibilityLabel[recipe.visibility]}
          </span>
        </div>
        <p className="recipe-author">Adăugată de {recipe.owner_name}</p>
        {canAddMeal && isCurrent && !readOnly && (
          <button
            className="button primary full"
            onClick={() => onMeal(current)}
          >
            <UtensilsCrossed size={18} />
            Adaugă ca masă
          </button>
        )}
        {recipe.is_example && (
          <p className="notice">Rețetă de exemplu · imagine generată.</p>
        )}
        {current.photo_status === "quarantine" && (
          <div className="status-box">
            <div>
              <strong>Fotografia este în verificare.</strong>
              {recipe.owner_id === userId && isCurrent && !readOnly && (
                <button
                  className="button secondary retry-scan"
                  disabled={busy}
                  onClick={() => void retry()}
                >
                  {busy ? "Se verifică…" : "Reîncearcă verificarea"}
                </button>
              )}
            </div>
          </div>
        )}
        {current.photo_status === "rejected" && (
          <p className="error">
            Fotografia nu a trecut verificarea și nu este disponibilă.
          </p>
        )}
        {error && (
          <p className="error" role="alert">
            {error}
          </p>
        )}
        <section>
          <h3>Ingrediente</h3>
          <ul>
            {current.ingredients.map((ingredient, index) => (
              <li key={index}>{ingredient}</li>
            ))}
          </ul>
        </section>
        <section>
          <h3>Preparare</h3>
          <p className="recipe-instructions">{current.instructions}</p>
        </section>
      </div>
    </Modal>
  );
}
