import { useEffect, useState } from "react";
import { api, ApiError } from "../../shared/api";
import { Modal } from "../../shared/Modal";
import { MealDetail } from "./Meals";
import { RecipeDetail } from "./RecipeDetail";
import type { MealDetailData } from "./mealTypes";
import type { Recipe } from "./recipeTypes";
import "./kitchen.css";

export function MealViewer({
  familyId,
  actorId,
  mealId,
  onClose,
  onUnavailable,
}: {
  familyId: string;
  actorId: number;
  mealId: string;
  onClose: () => void;
  onUnavailable: () => void;
}) {
  const base = `/families/${familyId}/meals`;
  const [meal, setMeal] = useState<MealDetailData | null>(null);
  const [recipe, setRecipe] = useState<Recipe | null>(null);
  const [revision, setRevision] = useState(0);
  const [error, setError] = useState("");
  useEffect(() => {
    const controller = new AbortController();
    setError("");
    api<MealDetailData>(`${base}/${mealId}`, { signal: controller.signal })
      .then((data) => {
        if (controller.signal.aborted) return;
        setMeal(data);
        if (!data.recipe_available) setRecipe(null);
      })
      .catch((e: unknown) => {
        if (controller.signal.aborted) return;
        setMeal(null);
        setRecipe(null);
        if (e instanceof ApiError && [401, 403, 404, 419].includes(e.status))
          onUnavailable();
        else setError("Masa nu a putut fi încărcată. Reîncearcă.");
      });
    return () => controller.abort();
  }, [base, mealId, revision]);
  useEffect(() => {
    const timer = window.setInterval(() => {
      if (document.visibilityState === "visible")
        setRevision((value) => value + 1);
    }, 60000);
    return () => window.clearInterval(timer);
  }, []);
  if (recipe)
    return (
      <RecipeDetail
        recipe={recipe}
        base={`/families/${familyId}/recipes`}
        userId={actorId}
        canAddMeal={false}
        readOnly
        onMeal={() => setRecipe(null)}
        onClose={() => setRecipe(null)}
        onChanged={setRecipe}
        onExpired={onUnavailable}
      />
    );
  if (meal)
    return (
      <MealDetail
        meal={meal}
        base={base}
        onClose={onClose}
        onChanged={setMeal}
        onRecipe={setRecipe}
        onExpired={onUnavailable}
      />
    );
  return (
    <Modal title="Masă" onClose={onClose}>
      {error ? (
        <>
          <p className="error" role="alert">
            {error}
          </p>
          <button
            className="button secondary"
            onClick={() => setRevision((value) => value + 1)}
          >
            Reîncearcă
          </button>
        </>
      ) : (
        <p role="status">Se încarcă masa…</p>
      )}
    </Modal>
  );
}
