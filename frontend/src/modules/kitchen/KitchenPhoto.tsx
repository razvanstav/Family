import { useEffect, useState } from "react";
import { CookingPot } from "lucide-react";

export function KitchenPhoto({
  resource,
  base,
}: {
  resource: {
    id: string;
    version: number;
    recipe_version_id?: string;
    photo_status: "ready" | "quarantine" | "rejected" | null;
  };
  base: string;
}) {
  const [failed, setFailed] = useState(false);
  const source = resource.recipe_version_id
    ? `/api/v1${base}/${resource.id}/versions/${resource.recipe_version_id}/photo`
    : `/api/v1${base}/${resource.id}/photo?v=${resource.version}`;
  useEffect(() => setFailed(false), [source, resource.photo_status]);
  return (
    <div className="recipe-photo">
      {resource.photo_status === "ready" && !failed ? (
        <img
          src={source}
          alt=""
          loading="lazy"
          onError={() => setFailed(true)}
        />
      ) : (
        <div className="recipe-photo-empty">
          <CookingPot size={34} aria-hidden="true" />
          <span>
            {resource.photo_status === "quarantine"
              ? "Fotografie în verificare"
              : resource.photo_status === "rejected"
                ? "Fotografie respinsă"
                : failed
                  ? "Fotografie indisponibilă"
                  : "Fără fotografie"}
          </span>
        </div>
      )}
    </div>
  );
}
