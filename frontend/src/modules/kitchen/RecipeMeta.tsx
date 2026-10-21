import { ChefHat, Clock3 } from "lucide-react";
import { recipeDifficulties, type Recipe } from "./recipeTypes";

export function RecipeMeta({ recipe }: { recipe: Recipe }) {
  return (
    <div className="recipe-meta">
      <span>
        <Clock3 size={15} aria-hidden="true" /> {recipe.duration_minutes} min
      </span>
      <span>
        <ChefHat size={16} aria-hidden="true" />{" "}
        {recipeDifficulties[recipe.difficulty]}
      </span>
    </div>
  );
}
