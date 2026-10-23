export type Family = {
  id: string;
  membership_id: string;
  name: string;
  role: "owner" | "adult" | "limited";
};
export type Session = {
  user: { id: number; name: string; email: string } | null;
  families: Family[];
  csrf_token: string;
  demo: boolean;
};
export type Member = {
  id: string;
  user_id: number | null;
  name: string;
  role: "owner" | "adult" | "limited";
  age?: number | null;
  photo_status?: "ready" | "quarantine" | "rejected" | null;
  photo_version?: number;
};
export type Invitation = {
  id: string;
  email: string;
  role: "adult" | "limited";
  expires_at: string;
};
export type Document = {
  id: string;
  title: string;
  category: string;
  visibility: "private" | "family" | "selected" | "adults";
  owner_id: number;
  subject_membership_id: string | null;
  owner_name: string;
  version: number;
  created_at: string;
  expires_on: string | null;
  mime: string;
  bytes: number;
  status: "ready" | "quarantine" | "rejected";
  original_name?: string;
  grants?: string[];
};
export type DocumentSummary = Pick<
  Document,
  | "id"
  | "title"
  | "category"
  | "visibility"
  | "mime"
  | "status"
  | "owner_id"
  | "subject_membership_id"
>;
export const familyDocumentCategories: readonly string[] = ["home", "memories"];
export const personalDocumentCategories: readonly string[] = [
  "personal",
  "health",
];
export type CalendarRequest = {
  id?: string;
  date: string;
  monthOnly?: boolean;
};
export type VehicleRequest = { vehicleId: string; entryId: string };
export type DocumentRequest = { id: string; memberId: string | null };
let csrf = "";
const sessionListeners = new Set<() => void>();
export function onSessionInvalidated(listener: () => void) {
  sessionListeners.add(listener);
  return () => {
    sessionListeners.delete(listener);
  };
}
export class ApiError extends Error {
  constructor(
    public status: number,
    message: string,
  ) {
    super(message);
  }
}
export const errorMessage = (error: unknown) =>
  error instanceof Error ? error.message : "Nu am putut salva. Reîncearcă.";
export async function api<T>(
  path: string,
  options: RequestInit = {},
): Promise<T> {
  const headers = new Headers(options.headers);
  headers.set("Accept", "application/json");
  if (csrf) headers.set("X-CSRF-TOKEN", csrf);
  if (options.body && !(options.body instanceof FormData))
    headers.set("Content-Type", "application/json");
  const response = await fetch(`/api/v1${path}`, {
    ...options,
    headers,
    credentials: "same-origin",
    cache: "no-store",
  });
  if (!response.ok) {
    if (response.status === 401 || response.status === 419) {
      csrf = "";
      if (path !== "/session")
        sessionListeners.forEach((listener) => listener());
    }
    const body = await response.json().catch(() => ({}));
    const firstError = body.errors
      ? Object.values(body.errors).flat()[0]
      : undefined;
    const message = String(firstError ?? body.message ?? "").trim();
    throw new ApiError(
      response.status,
      message || "Acțiunea nu a putut fi finalizată.",
    );
  }
  if (response.status === 204) return undefined as T;
  const data = await response.json();
  if (data.csrf_token) csrf = data.csrf_token;
  return data as T;
}
export const visibilityLabel = {
  private: "Doar eu",
  family: "Familia mea",
  selected: "Persoane alese",
  adults: "Adulții familiei",
};
export function shareableMembers(members: Member[], actorId?: number) {
  return members.filter(
    (member) => member.user_id !== null && member.user_id !== actorId,
  );
}
export const initials = (name: string) =>
  name
    .trim()
    .split(/\s+/)
    .map((part) => part[0])
    .slice(0, 2)
    .join("");
