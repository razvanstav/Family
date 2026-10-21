import type { Document } from "../../shared/api";

export function filterDocuments(
  documents: Document[],
  search: string,
  category: string,
  privacy: string,
) {
  const query = search.trim().toLocaleLowerCase("ro");
  return documents.filter(
    (document) =>
      (category === "all" || document.category === category) &&
      (privacy === "all" || document.visibility === privacy) &&
      document.title.toLocaleLowerCase("ro").includes(query),
  );
}
