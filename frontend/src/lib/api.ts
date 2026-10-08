export class ApiError extends Error {
  status: number;

  constructor(status: number, message: string) {
    super(message);
    this.status = status;
  }
}

function xsrfToken(): string | undefined {
  if (typeof document === "undefined") return undefined;
  const match = document.cookie.match(/(?:^|; )XSRF-TOKEN=([^;]*)/);
  return match ? decodeURIComponent(match[1]) : undefined;
}

export async function ensureCsrf(): Promise<void> {
  await fetch("/sanctum/csrf-cookie", { credentials: "include" });
}

export async function api<T>(path: string, init: RequestInit = {}): Promise<T> {
  const headers = new Headers(init.headers);
  headers.set("Accept", "application/json");
  if (init.body && !(init.body instanceof FormData) && !headers.has("Content-Type")) {
    headers.set("Content-Type", "application/json");
  }
  const token = xsrfToken();
  if (token) headers.set("X-XSRF-TOKEN", token);

  const response = await fetch(path, { ...init, headers, credentials: "include" });
  if (response.status === 204) return undefined as T;

  const data = (await response.json().catch(() => ({}))) as { message?: string };
  if (!response.ok) {
    throw new ApiError(response.status, data.message ?? "Request failed");
  }
  return data as T;
}

export async function login(email: string, password: string) {
  await ensureCsrf();
  return api<{ name: string; email: string }>("/api/login", {
    method: "POST",
    body: JSON.stringify({ email, password }),
  });
}

export async function logout() {
  await ensureCsrf();
  await api("/api/logout", { method: "POST" });
}
