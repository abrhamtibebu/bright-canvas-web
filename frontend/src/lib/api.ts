export class ApiError extends Error {
  status: number;
  errors?: Record<string, string[]>;

  constructor(status: number, message: string, errors?: Record<string, string[]>) {
    super(message);
    this.name = "ApiError";
    this.status = status;
    this.errors = errors;
  }

  firstError(): string | undefined {
    if (!this.errors) return undefined;
    for (const messages of Object.values(this.errors)) {
      if (messages?.[0]) return messages[0];
    }
    return undefined;
  }

  firstField(): string | undefined {
    if (!this.errors) return undefined;
    return Object.keys(this.errors).find((key) => (this.errors?.[key]?.length ?? 0) > 0);
  }
}

export function isApiError(error: unknown): error is ApiError {
  if (error instanceof ApiError) return true;
  if (!error || typeof error !== "object") return false;
  const candidate = error as { name?: string; status?: unknown; message?: unknown };
  return (
    (candidate.name === "ApiError" || typeof candidate.status === "number") &&
    typeof candidate.message === "string"
  );
}

function fieldErrors(error: unknown): Record<string, string[]> | undefined {
  if (!error || typeof error !== "object") return undefined;
  return asErrorMap((error as { errors?: unknown }).errors);
}

export function firstApiField(error: unknown): string | undefined {
  if (error instanceof ApiError) return error.firstField();
  const errors = fieldErrors(error);
  return errors ? Object.keys(errors).find((key) => (errors[key]?.length ?? 0) > 0) : undefined;
}

export function apiFieldErrorMap(error: unknown): Record<string, string> {
  const errors = error instanceof ApiError ? error.errors : fieldErrors(error);
  if (!errors) return {};
  const mapped: Record<string, string> = {};
  for (const [key, messages] of Object.entries(errors)) {
    const name = key.split(".")[0] ?? key;
    if (messages?.[0] && !mapped[name]) mapped[name] = messages[0];
  }
  return mapped;
}

export function formatApiError(error: unknown): string {
  if (isApiError(error)) {
    const detail = error instanceof ApiError ? error.firstError() : fieldErrors(error)?.[firstApiField(error) ?? ""]?.[0];
    if (error.status === 422) return detail || error.message || "Please check the highlighted fields and try again.";
    if (error.status === 413) return "Photos are too large. Use images under 2 MB.";
    if (error.status === 404) return "This link is not valid.";
    if (error.status >= 500) return "The server could not save your details. Please try again.";
    return detail || error.message || "Request failed.";
  }
  const message = error instanceof Error ? error.message : "";
  if (/failed to fetch|networkerror|load failed/i.test(message)) {
    return "Could not reach the server (network or CORS). Check your connection and try again.";
  }
  if (message.includes("VITE_API_URL")) {
    return "The app is not configured with an API address. Please contact Validity.";
  }
  return message || "Something went wrong. Check your connection and try again.";
}

/** API origin from VITE_API_URL in frontend/.env (required). */
function apiOrigin(): string {
  const configured = String(import.meta.env.VITE_API_URL ?? "").trim().replace(/\/$/, "");
  if (!configured) {
    throw new Error("Set VITE_API_URL in frontend/.env to the Laravel API address.");
  }
  return configured;
}

export function apiUrl(path: string): string {
  const normalized = path.startsWith("/") ? path : `/${path}`;
  return `${apiOrigin()}${normalized}`;
}

const tokenKey = "validity-token";

export function authToken(): string | null {
  if (typeof window === "undefined") return null;
  return sessionStorage.getItem(tokenKey);
}

function setAuthToken(token: string | null) {
  if (typeof window === "undefined") return;
  if (token) sessionStorage.setItem(tokenKey, token);
  else sessionStorage.removeItem(tokenKey);
}

function asErrorMap(value: unknown): Record<string, string[]> | undefined {
  if (!value || typeof value !== "object") return undefined;
  const mapped: Record<string, string[]> = {};
  for (const [key, messages] of Object.entries(value as Record<string, unknown>)) {
    if (Array.isArray(messages) && typeof messages[0] === "string") mapped[key] = messages as string[];
    else if (typeof messages === "string") mapped[key] = [messages];
  }
  return Object.keys(mapped).length ? mapped : undefined;
}

async function parseErrorBody(response: Response): Promise<{ message?: string; errors?: Record<string, string[]> }> {
  const raw = await response.text().catch(() => "");
  if (!raw) return {};
  try {
    const data = JSON.parse(raw) as { message?: string; errors?: unknown };
    return { message: data.message, errors: asErrorMap(data.errors) };
  } catch {
    if (/<html/i.test(raw)) return { message: `The server returned HTTP ${response.status}.` };
    return { message: raw.slice(0, 180) };
  }
}

export async function api<T>(path: string, init: RequestInit = {}): Promise<T> {
  const headers = new Headers(init.headers);
  headers.set("Accept", "application/json");
  if (init.body && !(init.body instanceof FormData) && !headers.has("Content-Type")) {
    headers.set("Content-Type", "application/json");
  }
  const token = authToken();
  if (token) headers.set("Authorization", `Bearer ${token}`);

  const response = await fetch(apiUrl(path), { ...init, headers });
  if (response.status === 204) return undefined as T;

  if (!response.ok) {
    const data = await parseErrorBody(response);
    throw new ApiError(response.status, data.message ?? `Request failed (HTTP ${response.status})`, data.errors);
  }

  const raw = await response.text().catch(() => "");
  if (!raw) return undefined as T;
  try {
    return JSON.parse(raw) as T;
  } catch {
    throw new ApiError(response.status, "The server returned an unexpected response.");
  }
}

export async function authorizedBlob(path: string): Promise<string | null> {
  const token = authToken();
  const headers = new Headers();
  headers.set("Accept", "application/json");
  if (token) headers.set("Authorization", `Bearer ${token}`);
  const response = await fetch(apiUrl(path), { headers });
  if (!response.ok) return null;
  return URL.createObjectURL(await response.blob());
}

export async function downloadCsv(path: string, filename: string): Promise<void> {
  const token = authToken();
  const headers = new Headers();
  headers.set("Accept", "text/csv");
  if (token) headers.set("Authorization", `Bearer ${token}`);
  const response = await fetch(apiUrl(path), { headers });
  if (!response.ok) {
    throw new ApiError(response.status, "Could not download the CSV export.");
  }
  const blob = await response.blob();
  const url = URL.createObjectURL(blob);
  const link = document.createElement("a");
  link.href = url;
  link.download = filename;
  document.body.appendChild(link);
  link.click();
  link.remove();
  URL.revokeObjectURL(url);
}

export async function login(email: string, password: string) {
  const user = await api<{ name: string; email: string; token: string }>("/api/login", {
    method: "POST",
    body: JSON.stringify({ email, password }),
  });
  setAuthToken(user.token);
  return user;
}

export async function logout() {
  try {
    await api("/api/logout", { method: "POST" });
  } finally {
    setAuthToken(null);
  }
}
