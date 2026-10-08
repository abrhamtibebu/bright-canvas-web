export class ApiError extends Error {
  status: number;

  constructor(status: number, message: string) {
    super(message);
    this.status = status;
  }
}

function apiOrigin(): string {
  const configured = import.meta.env.VITE_API_URL?.trim().replace(/\/$/, "");
  if (!configured) {
    throw new Error("Set VITE_API_URL in frontend/.env to the Laravel API address.");
  }
  return configured;
}

export function apiUrl(path: string): string {
  return `${apiOrigin()}${path.startsWith("/") ? path : `/${path}`}`;
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

  const data = (await response.json().catch(() => ({}))) as { message?: string };
  if (!response.ok) {
    throw new ApiError(response.status, data.message ?? "Request failed");
  }
  return data as T;
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
