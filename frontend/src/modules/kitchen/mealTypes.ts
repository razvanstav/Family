export const mealTypes = {
  breakfast: "Mic dejun",
  lunch: "Prânz",
  dinner: "Cină",
  snack: "Gustare",
};
export type MealVote = {
  liked: boolean | null;
  comment: string | null;
  version: number;
};
export type Meal = {
  id: string;
  dish_title: string;
  meal_date: string;
  meal_type: keyof typeof mealTypes;
  visibility: "private" | "family";
  owner_id: number;
  owner_name: string;
  version: number;
  created_at: string;
  photo_status: "ready" | "quarantine" | "rejected" | null;
  photo_is_example: boolean;
  recipe_available: boolean;
  liked_count: number;
  disliked_count: number;
  voting_open: boolean;
  my_vote: MealVote | null;
};
export type MealList = { items: Meal[]; page: number; has_more: boolean };
export type MealDetailData = Meal & {
  feedback: {
    membership_id: string;
    author_name: string;
    liked: boolean;
    comment: string | null;
  }[];
  feedback_page: number;
  feedback_has_more: boolean;
};
