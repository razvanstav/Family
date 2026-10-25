export const publicRequestTypes = {
  support: "Contact și asistență",
  privacy: "Cerere privind datele personale",
  withdrawal: "Retragere sau rambursare",
  "illegal-content": "Semnalează conținut ilegal",
} as const;

export type PublicRequestType = keyof typeof publicRequestTypes;
export type PublicRequestStatus = "received" | "in_progress" | "closed";
export const publicRequestStatuses: Record<PublicRequestStatus, string> = {
  received: "Primită",
  in_progress: "În lucru",
  closed: "Închisă",
};

export function downloadText(filename: string, content: string) {
  const url = URL.createObjectURL(
    new Blob([content], { type: "text/plain;charset=utf-8" }),
  );
  const anchor = document.createElement("a");
  anchor.href = url;
  anchor.download = filename;
  anchor.click();
  setTimeout(() => URL.revokeObjectURL(url), 1000);
}
