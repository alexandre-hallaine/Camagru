let csrf_token = null;

export function setCsrfToken(token) {
  csrf_token = token;
}

export async function apiCall(url, method, data) {
  const headers = { "Content-Type": "application/json" };
  if (method !== "GET") headers["X-CSRF-Token"] = csrf_token;

  const res = await fetch(url, {
    method,
    body: data ? JSON.stringify(data) : null,
    headers,
  });

  data = await res.json().catch(() => null);
  return { ok: res.ok && data, data };
}
