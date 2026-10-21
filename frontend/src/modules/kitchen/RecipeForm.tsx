import { useEffect, useRef, useState, type FormEvent } from "react";
import { ImagePlus } from "lucide-react";
import { api, ApiError } from "../../shared/api";
import { Modal } from "../../shared/Modal";
import {
  recipeCategories,
  recipeDifficulties,
  type Recipe,
} from "./recipeTypes";

export function RecipeForm({
  base,
  recipe: initialRecipe,
  onClose,
  onSaved,
  onReloaded,
  onExpired,
}: {
  base: string;
  recipe?: Recipe;
  onClose: () => void;
  onSaved: (recipe: Recipe) => void;
  onReloaded?: (recipe: Recipe) => void;
  onExpired: () => void;
}) {
  const [busy, setBusy] = useState(false);
  const [recipe, setRecipe] = useState(initialRecipe);
  const [conflict, setConflict] = useState(false);
  const [photoAction, setPhotoAction] = useState("keep");
  const [error, setError] = useState("");
  const [visibility, setVisibility] = useState("private");
  const titleInput = useRef<HTMLInputElement>(null);
  const pendingRequest = useRef<AbortController | null>(null);
  useEffect(() => {
    titleInput.current?.focus();
    return () => pendingRequest.current?.abort();
  }, [recipe?.version]);
  async function reload() {
    if (!recipe || busy) return;
    const controller = new AbortController();
    pendingRequest.current = controller;
    setBusy(true);
    try {
      const current = await api<Recipe>(`${base}/${recipe.id}`, {
        signal: controller.signal,
      });
      if (controller.signal.aborted) return;
      setRecipe(current);
      onReloaded?.(current);
      setPhotoAction("keep");
      setConflict(false);
      setError("");
    } catch (e) {
      if (controller.signal.aborted) return;
      if (e instanceof ApiError && [401, 403, 404, 419].includes(e.status))
        onExpired();
      else
        setError(
          "Rețeta nu a putut fi reîncărcată. Reîncearcă; modificările nesalvate vor fi înlocuite.",
        );
    } finally {
      if (!controller.signal.aborted) setBusy(false);
    }
  }
  async function save(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    if (busy || conflict) return;
    const form = new FormData(event.currentTarget);
    const ingredients = String(form.get("ingredients_text") ?? "")
      .split(/\r?\n/)
      .map((line) => line.trim())
      .filter(Boolean);
    form.delete("ingredients_text");
    if (
      !ingredients.length ||
      ingredients.length > 60 ||
      ingredients.some((line) => line.length > 200)
    ) {
      setError(
        "Adaugă între 1 și 60 de ingrediente, fiecare pe un rând de cel mult 200 de caractere.",
      );
      return;
    }
    ingredients.forEach((ingredient) =>
      form.append("ingredients[]", ingredient),
    );
    const photo = form.get("photo");
    if (photo instanceof File && !photo.name) form.delete("photo");
    else if (photo instanceof File && !photo.size) {
      setError("Fotografia aleasă este goală. Alege alt fișier.");
      return;
    }
    if (photo instanceof File && photo.size > 10 * 1024 * 1024) {
      setError("Fotografia poate avea cel mult 10 MB.");
      return;
    }
    setBusy(true);
    setError("");
    const controller = new AbortController();
    pendingRequest.current = controller;
    try {
      const saved = await api<Recipe>(
        recipe ? `${base}/${recipe.id}/versions` : base,
        {
          method: "POST",
          ...(recipe ? { headers: { "If-Match": `"${recipe.version}"` } } : {}),
          body: form,
          signal: controller.signal,
        },
      );
      if (!controller.signal.aborted) onSaved(saved);
    } catch (e) {
      if (controller.signal.aborted) return;
      if (e instanceof ApiError && [401, 403, 404, 419].includes(e.status))
        onExpired();
      else if (e instanceof ApiError && e.status === 412) {
        setConflict(true);
        setError(
          "Rețeta s-a schimbat între timp. Reîncarcă rețeta; modificările nesalvate vor fi înlocuite.",
        );
      } else
        setError(
          e instanceof ApiError
            ? e.message
            : "Rețeta nu a putut fi salvată. Reîncearcă.",
        );
    } finally {
      if (!controller.signal.aborted) setBusy(false);
    }
  }
  return (
    <Modal
      title={recipe ? "Editează rețeta" : "Adaugă rețetă"}
      onClose={onClose}
      wide
    >
      <form key={recipe?.version ?? 0} onSubmit={(e) => void save(e)}>
        {recipe && (
          <p className="notice">
            Salvarea creează o versiune nouă. Mesele deja adăugate păstrează
            rețeta și fotografia folosite atunci.
          </p>
        )}
        <label className="field">
          Titlu
          <input
            name="title"
            required
            maxLength={160}
            ref={titleInput}
            defaultValue={recipe?.title}
          />
        </label>
        <div className="recipe-form-row">
          <label className="field">
            Categorie
            <select name="category" defaultValue={recipe?.category ?? "main"}>
              {Object.entries(recipeCategories).map(([key, label]) => (
                <option key={key} value={key}>
                  {label}
                </option>
              ))}
            </select>
          </label>
          <label className="field">
            Dificultate
            <select
              name="difficulty"
              defaultValue={recipe?.difficulty ?? "easy"}
            >
              {Object.entries(recipeDifficulties).map(([key, label]) => (
                <option key={key} value={key}>
                  {label}
                </option>
              ))}
            </select>
          </label>
        </div>
        <div className="recipe-form-row">
          <label className="field">
            Durată (minute)
            <input
              type="number"
              name="duration_minutes"
              required
              min={1}
              max={1440}
              defaultValue={recipe?.duration_minutes ?? 30}
            />
          </label>
          <label className="field">
            Porții
            <input
              type="number"
              name="servings"
              required
              min={1}
              max={50}
              defaultValue={recipe?.servings ?? 4}
            />
          </label>
        </div>
        <label className="field">
          Ingrediente
          <textarea
            name="ingredients_text"
            required
            rows={5}
            maxLength={12060}
            aria-describedby="ingredient-hint"
            defaultValue={recipe?.ingredients.join("\n")}
          />
          <small id="ingredient-hint">
            Un ingredient pe rând, cu cantitatea sa. Maximum 60 de ingrediente.
          </small>
        </label>
        <label className="field">
          Preparare
          <textarea
            name="instructions"
            required
            rows={6}
            maxLength={16000}
            defaultValue={recipe?.instructions}
          />
        </label>
        {recipe && (
          <label className="field">
            Fotografie
            <select
              name="photo_action"
              value={photoAction}
              onChange={(e) => setPhotoAction(e.target.value)}
            >
              <option value="keep">
                {recipe.photo_status
                  ? "Păstrează fotografia"
                  : "Fără fotografie"}
              </option>
              <option value="replace">
                {recipe.photo_status
                  ? "Înlocuiește fotografia"
                  : "Adaugă fotografie"}
              </option>
              {recipe.photo_status && (
                <option value="remove">Elimină din versiunea nouă</option>
              )}
            </select>
          </label>
        )}
        {(!recipe || photoAction === "replace") && (
          <label className="field">
            <span className="recipe-photo-label">
              <ImagePlus size={17} aria-hidden="true" /> Fotografie (opțional)
            </span>
            <input
              type="file"
              name="photo"
              required={!!recipe}
              accept="image/jpeg,image/png,image/webp"
              aria-describedby="recipe-photo-hint"
            />
            <small id="recipe-photo-hint">
              JPG, PNG sau WebP, maximum 10 MB și 16 megapixeli. Fotografia
              apare după verificare.
            </small>
          </label>
        )}
        {!recipe && (
          <label className="field">
            Acces
            <select
              name="visibility"
              value={visibility}
              onChange={(e) => setVisibility(e.target.value)}
              aria-describedby="recipe-access-hint"
            >
              <option value="private">Doar eu</option>
              <option value="family">Familia mea</option>
            </select>
            <small id="recipe-access-hint">
              {visibility === "private"
                ? "Numai tu poți vedea rețeta și fotografia."
                : "Toți membrii activi ai familiei pot vedea rețeta și fotografia."}
            </small>
          </label>
        )}
        {error && (
          <p className="error" role="alert">
            {error}
          </p>
        )}
        {conflict && (
          <button
            className="button secondary full"
            type="button"
            disabled={busy}
            onClick={() => void reload()}
          >
            Reîncarcă rețeta
          </button>
        )}
        <button
          className="button primary full"
          type="submit"
          disabled={busy || conflict}
        >
          {busy ? "Se salvează…" : "Salvează"}
        </button>
      </form>
    </Modal>
  );
}
