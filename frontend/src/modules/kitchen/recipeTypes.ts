export const recipeCategories = {
  main: "Fel principal",
  soup: "Supe și ciorbe",
  dessert: "Desert",
  breakfast: "Mic dejun",
  salad: "Salată",
  snack: "Gustare",
};

export const recipeDifficulties = {
  easy: "Ușor",
  medium: "Mediu",
  advanced: "Avansat",
};

export type Recipe = {
  id: string;
  title: string;
  category: keyof typeof recipeCategories;
  difficulty: keyof typeof recipeDifficulties;
  duration_minutes: number;
  servings: number;
  ingredients: string[];
  instructions: string;
  visibility: "private" | "family";
  owner_id: number;
  owner_name: string;
  version: number;
  current_version: number;
  version_created_at: string;
  recipe_version_id: string;
  is_example: boolean;
  photo_status: "ready" | "quarantine" | "rejected" | null;
  created_at: string;
};

export type RecipeList = { items: Recipe[]; has_more: boolean; page: number };
