import { useEffect, useRef, useState, type FormEvent } from "react";
import { api, ApiError, visibilityLabel, type Member } from "../../shared/api";
import { Modal } from "../../shared/Modal";
import type { Recipe } from "./recipeTypes";

export type RecipeAction = "sharing" | "archive" | "restore";

export function RecipeManagement({
  recipe: initialRecipe,
  base,
  action,
  onClose,
  onSaved,
  onReloaded,
  onExpired,
}: {
  recipe: Recipe;
  base: string;
  action: RecipeAction;
  onClose: () => void;
  onSaved: (recipe: Recipe) => void;
  onReloaded: (recipe: Recipe) => void;
  onExpired: () => void;
}) {
  const [recipe, setRecipe] = useState(initialRecipe);
  const [visibility, setVisibility] = useState(recipe.visibility);
  const [grants, setGrants] = useState(recipe.grants);
  const [members, setMembers] = useState<Member[]>([]);
  const [membersReady, setMembersReady] = useState(action !== "sharing");
  const [membersLoading, setMembersLoading] = useState(action === "sharing");
  const [membersRevision, setMembersRevision] = useState(0);
  const [busy, setBusy] = useState(false);
  const [conflict, setConflict] = useState(false);
  const [error, setError] = useState("");
  const pending = useRef<AbortController | null>(null);
  useEffect(() => () => pending.current?.abort(), []);
  useEffect(() => {
    if (action !== "sharing") return;
    const controller = new AbortController();
    setMembersReady(false);
    setMembersLoading(true);
    api<Member[]>(`${base.replace(/\/recipes$/, "")}/members`, {
      signal: controller.signal,
    })
      .then((data) => {
        if (!controller.signal.aborted) {
          setMembers(data);
          setMembersReady(true);
        }
      })
      .catch((e: unknown) => {
        if (controller.signal.aborted) return;
        if (e instanceof ApiError && [401, 403, 404, 419].includes(e.status))
          onExpired();
        else setError("Membrii nu au putut fi încărcați. Reîncearcă.");
      })
      .finally(() => {
        if (!controller.signal.aborted) setMembersLoading(false);
      });
    return () => controller.abort();
  }, [action, base, membersRevision]);

  async function perform(reload = false) {
    if (busy || (!reload && conflict)) return;
    const controller = new AbortController();
    pending.current = controller;
    setBusy(true);
    setError("");
    try {
      const saved = await api<Recipe>(
        `${base}/${recipe.id}${reload ? "" : `/${action}`}`,
        {
          signal: controller.signal,
          ...(reload
            ? {}
            : {
                method: action === "sharing" ? "PUT" : "POST",
                headers: { "If-Match": `"${recipe.revision}"` },
                ...(action === "sharing"
                  ? {
                      body: JSON.stringify({
                        visibility,
                        membership_ids: visibility === "selected" ? grants : [],
                      }),
                    }
                  : {}),
              }),
        },
      );
      if (controller.signal.aborted) return;
      if (reload) {
        setRecipe(saved);
        setVisibility(saved.visibility);
        setGrants(saved.grants);
        setConflict(false);
        onReloaded(saved);
        setMembersRevision((value) => value + 1);
      } else onSaved(saved);
    } catch (e) {
      if (controller.signal.aborted) return;
      if (e instanceof ApiError && [401, 403, 404, 419].includes(e.status)) {
        onClose();
        onExpired();
      } else if (e instanceof ApiError && [409, 412].includes(e.status)) {
        setConflict(true);
        setError(
          "Rețeta s-a schimbat. Reîncarcă rețeta; alegerile nesalvate vor fi înlocuite.",
        );
      } else
        setError(
          e instanceof ApiError
            ? e.message
            : "Acțiunea nu a putut fi salvată. Reîncearcă.",
        );
    } finally {
      if (!controller.signal.aborted) setBusy(false);
    }
  }
  const wrongState =
    action === "restore" ? !recipe.archived_at : !!recipe.archived_at;
  return (
    <Modal
      title={
        action === "sharing"
          ? "Acces"
          : action === "archive"
            ? "Arhivează rețeta"
            : "Restaurează rețeta"
      }
      onClose={onClose}
    >
      <form
        onSubmit={(e: FormEvent) => {
          e.preventDefault();
          void perform();
        }}
      >
        <p className="recipe-management-title">{recipe.title}</p>
        {action === "sharing" ? (
          <>
            <label className="field">
              Acces
              <select
                value={visibility}
                disabled={busy || conflict || wrongState}
                onChange={(e) =>
                  setVisibility(e.target.value as Recipe["visibility"])
                }
                aria-describedby="recipe-sharing-hint"
              >
                {Object.entries(visibilityLabel).map(([value, label]) => (
                  <option key={value} value={value}>
                    {label}
                  </option>
                ))}
              </select>
              <small id="recipe-sharing-hint">
                {visibility === "private"
                  ? "Numai tu poți vedea rețeta, istoricul și fotografiile."
                  : visibility === "family"
                    ? "Toți membrii activi pot vedea rețeta, istoricul și fotografiile."
                    : "Numai tu și persoanele alese puteți vedea rețeta, istoricul și fotografiile."}
              </small>
            </label>
            {membersLoading && <p role="status">Se încarcă membrii…</p>}
            {visibility === "selected" && (
              <fieldset
                className="recipe-members"
                disabled={busy || conflict || !membersReady || wrongState}
              >
                <legend>Persoane</legend>
                {members
                  .filter((member) => member.user_id !== recipe.owner_id)
                  .map((member) => (
                    <label key={member.id}>
                      <input
                        type="checkbox"
                        checked={grants.includes(member.id)}
                        onChange={(e) =>
                          setGrants(
                            e.target.checked
                              ? [...grants, member.id]
                              : grants.filter((id) => id !== member.id),
                          )
                        }
                      />
                      <span>{member.name}</span>
                    </label>
                  ))}
                {membersReady &&
                  !members.some(
                    (member) => member.user_id !== recipe.owner_id,
                  ) && <p>Niciun alt membru activ.</p>}
              </fieldset>
            )}
            <p className="notice">
              Retragerea accesului blochează deschiderile viitoare, inclusiv din
              mese. Titlurile meselor și feedbackul rămân vizibile conform
              accesului mesei. Copiile deja salvate nu pot fi retrase.
            </p>
          </>
        ) : (
          <p className="notice">
            {action === "archive"
              ? "Rețeta va fi vizibilă doar pentru tine, în Arhivate. Mesele și voturile rămân, fără acces la rețeta și fotografia sursă. Versiunile se păstrează. Restaurarea va folosi accesul „Doar eu”."
              : "Rețeta va reveni în lista activă, cu acces „Doar eu”. Poți schimba accesul după restaurare."}
          </p>
        )}
        {wrongState && (
          <p className="notice">
            {recipe.archived_at
              ? action === "sharing"
                ? "Rețeta este arhivată. Restaureaz-o înainte de a schimba accesul."
                : "Rețeta este deja arhivată."
              : "Rețeta este deja activă."}
          </p>
        )}
        {error && (
          <p className="error" role="alert">
            {error}
          </p>
        )}
        {action === "sharing" && !membersReady && error && (
          <button
            className="button secondary full"
            type="button"
            onClick={() => {
              setError("");
              setMembersRevision((value) => value + 1);
            }}
          >
            Reîncearcă membrii
          </button>
        )}
        {conflict && (
          <button
            className="button secondary full"
            type="button"
            disabled={busy}
            onClick={() => void perform(true)}
          >
            Reîncarcă rețeta
          </button>
        )}
        <button
          className="button primary full"
          disabled={
            busy ||
            conflict ||
            wrongState ||
            !membersReady ||
            (action === "sharing" &&
              visibility === "selected" &&
              !grants.length)
          }
        >
          {busy
            ? "Se salvează…"
            : action === "sharing"
              ? "Salvează"
              : action === "archive"
                ? "Arhivează"
                : "Restaurează"}
        </button>
        <button
          className="text-button full center"
          type="button"
          onClick={onClose}
        >
          Înapoi
        </button>
      </form>
    </Modal>
  );
}
