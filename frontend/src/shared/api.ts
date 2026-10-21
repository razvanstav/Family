export type Family = {
  id: string;
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
  user_id: number;
  name: string;
  role: "owner" | "adult" | "limited";
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
  visibility: "private" | "family" | "selected";
  owner_id: number;
  owner_name: string;
  version: number;
  created_at: string;
  mime: string;
  bytes: number;
  status: "ready" | "quarantine" | "rejected";
  original_name?: string;
  grants?: string[];
};
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
};
