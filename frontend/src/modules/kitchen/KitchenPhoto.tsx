import { CookingPot } from "lucide-react";
import { PrivatePhoto, type PhotoStatus } from "../../shared/PrivatePhoto";

export function KitchenPhoto({
  resource,
  base,
}: {
  resource: {
    id: string;
    version: number;
    recipe_version_id?: string;
    photo_status: PhotoStatus;
  };
  base: string;
}) {
  const source = resource.recipe_version_id
    ? `/api/v1${base}/${resource.id}/versions/${resource.recipe_version_id}/photo`
    : `/api/v1${base}/${resource.id}/photo?v=${resource.version}`;
  return (
    <PrivatePhoto
      src={source}
      status={resource.photo_status}
      className="recipe-photo"
      placeholder={<CookingPot size={34} aria-hidden="true" />}
    />
  );
}
