export function documentFileType(mime: string | null): string {
  return mime === null
    ? "Fără fișier"
    : mime === "application/pdf"
      ? "PDF"
      : "Imagine";
}

export function documentFileState(
  status: "ready" | "quarantine" | "rejected" | null,
): string | null {
  switch (status) {
    case "quarantine":
      return "În verificare";
    case "rejected":
      return "Respins";
    case null:
      return "Fără fișier";
    default:
      return null;
  }
}
