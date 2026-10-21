import { useEffect, useState } from "react";
import {
  CookingPot,
  LockKeyhole,
  Plus,
  RefreshCw,
  Search,
  X,
} from "lucide-react";
import { api, ApiError, type Family } from "../../shared/api";
import { recipeCategories, type Recipe, type RecipeList } from "./recipeTypes";
import "./kitchen.css";
import { KitchenPhoto } from "./KitchenPhoto";
import { Meals } from "./Meals";
import { RecipeForm } from "./RecipeForm";
import { RecipeDetail } from "./RecipeDetail";
import { RecipeMeta } from "./RecipeMeta";

export function Kitchen({
  family,
  userId,
  onExpired,
}: {
  family: Family;
  userId: number;
  onExpired: () => void;
}) {
  const base = `/families/${family.id}/recipes`;
  const [section, setSection] = useState<"recipes" | "meals">("recipes");
  const [mealTarget, setMealTarget] = useState<Recipe | null>(null);
  const [items, setItems] = useState<Recipe[]>([]);
  const [query, setQuery] = useState("");
  const [search, setSearch] = useState("");
  const [category, setCategory] = useState("");
  const [visibility, setVisibility] = useState("");
  const [page, setPage] = useState(1);
  const [hasMore, setHasMore] = useState(false);
  const [revision, setRevision] = useState(0);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState("");
  const [notice, setNotice] = useState("");
  const [adding, setAdding] = useState(false);
  const [selected, setSelected] = useState<Recipe | null>(null);

  useEffect(() => {
    const timer = window.setTimeout(() => {
      setSearch(query.trim());
      setPage(1);
    }, 250);
    return () => window.clearTimeout(timer);
  }, [query]);

  useEffect(() => {
    if (section !== "recipes") return;
    const controller = new AbortController();
    setLoading(true);
    setError("");
    const params = new URLSearchParams({ page: String(page) });
    if (search) params.set("q", search);
    if (category) params.set("category", category);
    if (visibility) params.set("visibility", visibility);
    api<RecipeList>(`${base}?${params}`, { signal: controller.signal })
      .then((data) => {
        if (controller.signal.aborted) return;
        setItems(data.items);
        setHasMore(data.has_more);
      })
      .catch((e: unknown) => {
        if (controller.signal.aborted) return;
        setItems([]);
        setSelected(null);
        if (e instanceof ApiError && [401, 403, 404, 419].includes(e.status))
          onExpired();
        else setError("Rețetele nu au putut fi încărcate. Reîncearcă.");
      })
      .finally(() => {
        if (!controller.signal.aborted) setLoading(false);
      });
    return () => controller.abort();
  }, [base, search, category, visibility, page, revision, section]);

  useEffect(() => {
    const timer = window.setInterval(() => {
      if (document.visibilityState === "visible")
        setRevision((value) => value + 1);
    }, 60000);
    return () => window.clearInterval(timer);
  }, []);

  return (
    <div className="kitchen-page">
      <section className="page-heading">
        <h1>Bucătărie</h1>
        {family.role !== "limited" && section === "recipes" && (
          <button
            className="button primary"
            aria-label="Adaugă rețetă"
            onClick={() => setAdding(true)}
          >
            <Plus size={19} />
            <span>Adaugă rețetă</span>
          </button>
        )}
      </section>
      <nav className="kitchen-sections" aria-label="Secțiuni Bucătărie">
        <button
          aria-pressed={section === "recipes"}
          onClick={() => setSection("recipes")}
        >
          Rețete
        </button>
        <button
          aria-pressed={section === "meals"}
          onClick={() => setSection("meals")}
        >
          Mese
        </button>
      </nav>
      {section === "recipes" && (
        <>
          <div className="kitchen-toolbar">
            <label className="search">
              <Search size={18} aria-hidden="true" />
              <input
                type="search"
                aria-label="Caută rețete după titlu"
                placeholder="Caută rețete"
                maxLength={120}
                value={query}
                onChange={(e) => setQuery(e.target.value)}
              />
              {query && (
                <button
                  aria-label="Șterge căutarea"
                  onClick={() => setQuery("")}
                >
                  <X size={16} />
                </button>
              )}
            </label>
            <select
              aria-label="Filtrează după acces"
              value={visibility}
              onChange={(e) => {
                setVisibility(e.target.value);
                setPage(1);
              }}
            >
              <option value="">Orice acces</option>
              <option value="private">Doar eu</option>
              <option value="family">Familia mea</option>
            </select>
          </div>
          <div className="kitchen-categories" aria-label="Categorii de rețete">
            <button
              aria-pressed={!category}
              onClick={() => {
                setCategory("");
                setPage(1);
              }}
            >
              Toate
            </button>
            {Object.entries(recipeCategories).map(([key, label]) => (
              <button
                key={key}
                aria-pressed={category === key}
                onClick={() => {
                  setCategory(key);
                  setPage(1);
                }}
              >
                {label}
              </button>
            ))}
          </div>
          {notice && (
            <p className="notice" role="status">
              {notice}
            </p>
          )}
          <section aria-labelledby="recipes-title" aria-busy={loading}>
            <div className="section-header">
              <h2 id="recipes-title">Rețete</h2>
              <button
                className="icon-button"
                disabled={loading}
                aria-label="Actualizează rețetele"
                onClick={() => setRevision((value) => value + 1)}
              >
                <RefreshCw size={18} />
              </button>
            </div>
            {loading ? (
              <p className="loading-text" role="status">
                Se încarcă rețetele…
              </p>
            ) : error ? (
              <div className="empty">
                <p className="error" role="alert">
                  {error}
                </p>
                <button
                  className="button secondary"
                  onClick={() => setRevision((value) => value + 1)}
                >
                  Reîncearcă
                </button>
              </div>
            ) : items.length === 0 ? (
              <div className="empty">
                <CookingPot size={36} aria-hidden="true" />
                <h3>
                  {search || category || visibility
                    ? "Nicio rețetă găsită"
                    : "Nicio rețetă"}
                </h3>
                {search || category || visibility ? (
                  <button
                    className="button secondary"
                    onClick={() => {
                      setQuery("");
                      setSearch("");
                      setCategory("");
                      setVisibility("");
                      setPage(1);
                    }}
                  >
                    Resetează filtrele
                  </button>
                ) : (
                  family.role !== "limited" && (
                    <button
                      className="button primary"
                      onClick={() => setAdding(true)}
                    >
                      Adaugă rețetă
                    </button>
                  )
                )}
              </div>
            ) : (
              <>
                <div className="recipe-grid">
                  {items.map((recipe) => (
                    <button
                      className="recipe-card"
                      key={recipe.id}
                      onClick={() => setSelected(recipe)}
                      aria-label={`Deschide rețeta ${recipe.title}`}
                    >
                      <KitchenPhoto resource={recipe} base={base} />
                      <div className="recipe-card-body">
                        <div className="recipe-card-title">
                          <h3>{recipe.title}</h3>
                          {recipe.visibility === "private" && (
                            <LockKeyhole size={16} aria-label="Doar eu" />
                          )}
                        </div>
                        <RecipeMeta recipe={recipe} />
                        <div className="recipe-tags">
                          <span
                            className={`recipe-category ${recipe.category}`}
                          >
                            {recipeCategories[recipe.category]}
                          </span>
                          {recipe.is_example && (
                            <span className="recipe-example">Exemplu</span>
                          )}
                        </div>
                      </div>
                    </button>
                  ))}
                </div>
                {(page > 1 || hasMore) && (
                  <div className="recipe-pagination">
                    <button
                      className="button secondary"
                      disabled={page === 1}
                      onClick={() => setPage((value) => value - 1)}
                    >
                      Înapoi
                    </button>
                    <span>Pagina {page}</span>
                    <button
                      className="button secondary"
                      disabled={!hasMore}
                      onClick={() => setPage((value) => value + 1)}
                    >
                      Înainte
                    </button>
                  </div>
                )}
              </>
            )}
          </section>
        </>
      )}
      {section === "meals" && (
        <Meals
          family={family}
          requestedRecipe={mealTarget}
          onRecipeConsumed={() => setMealTarget(null)}
          onRecipe={setSelected}
          onChooseRecipe={() => setSection("recipes")}
          onExpired={onExpired}
        />
      )}
      {adding && (
        <RecipeForm
          base={base}
          onClose={() => setAdding(false)}
          onExpired={onExpired}
          onSaved={(recipe) => {
            setAdding(false);
            setQuery("");
            setSearch("");
            setCategory("");
            setVisibility("");
            setPage(1);
            setNotice("Rețeta a fost adăugată.");
            setRevision((value) => value + 1);
            setSelected(recipe);
          }}
        />
      )}
      {selected && (
        <RecipeDetail
          key={selected.id}
          recipe={selected}
          base={base}
          userId={userId}
          canAddMeal={family.role !== "limited"}
          readOnly={section === "meals"}
          onMeal={(recipe) => {
            setMealTarget(recipe);
            setSelected(null);
            setSection("meals");
          }}
          onClose={() => setSelected(null)}
          onChanged={(recipe) => {
            setSelected(recipe);
            if (recipe.version === recipe.current_version) {
              setItems((current) =>
                current.map((item) => (item.id === recipe.id ? recipe : item)),
              );
            }
          }}
          onExpired={onExpired}
        />
      )}
    </div>
  );
}
