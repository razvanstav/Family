import type { Meal } from "./mealTypes";

export const dishCategories = {
  pasta: "Paste",
  soup: "Supe și ciorbe",
  pizza: "Pizza",
  main: "Fel principal",
  salad: "Salată",
  side: "Garnitură",
  dessert: "Desert",
  snack: "Gustare",
  drink: "Băutură",
  other: "Altele",
} as const;

export type DishCategory = keyof typeof dishCategories;
export type DiningScope = "mine" | "family";
export type Restaurant = { id: string; name: string; city: string | null };
export type DiningRating = {
  score: number | null;
  comment: string | null;
  version: number;
};
export type DiningPage<T> = { items: T[]; page: number; has_more: boolean };
export type DiningVisit = {
  id: string;
  restaurant: Restaurant;
  meal_date: string;
  meal_type: Meal["meal_type"];
  visibility: "private" | "family";
  owner_id: number;
  owner_name: string;
  version: number;
  created_at: string;
  rating_average: number | null;
  rating_count: number;
  my_rating: DiningRating | null;
  dishes: Meal[];
};
export type VisitRating = {
  membership_id: string;
  author_name: string;
  score: number;
  comment: string | null;
};
export type DiningVisitDetail = DiningVisit & {
  ratings: VisitRating[];
  rating_page: number;
  rating_has_more: boolean;
};
export type DishRanking = {
  dish_title: string;
  category: DishCategory;
  restaurant: Restaurant | null;
  average_score: number;
  rating_count: number;
  last_eaten_on: string;
  meal_id: string;
};
export type RestaurantRanking = {
  restaurant: Restaurant;
  average_score: number;
  rating_count: number;
  last_visited_on: string;
  visit_id: string;
};

const numberFormat = new Intl.NumberFormat("ro-RO", {
  maximumFractionDigits: 2,
});
export function diningScore(score: number): string {
  return `${numberFormat.format(score)}/10`;
}
export function diningRatingCount(count: number): string {
  return `${count} ${count === 1 ? "evaluare" : "evaluări"}`;
}
