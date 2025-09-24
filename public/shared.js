let csrf_token = null;

export function setCsrfToken(token) {
  csrf_token = token;
}

export async function apiCall(url, method, data) {
  const headers = { "Content-Type": "application/json" };
  if (method === "POST" && csrf_token) {
    headers["X-CSRF-Token"] = csrf_token;
  }

  if (data) {
    data = JSON.stringify(data);
  }

  const res = await fetch(url, {
    method,
    body: data,
    headers,
  });

  data = await res.json().catch(() => null);
  return { ok: res.ok, data };
}
