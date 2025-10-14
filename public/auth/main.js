import { apiCall } from "../shared.js";

document.querySelectorAll("header button").forEach((btn) => {
  btn.addEventListener("click", () => {
    const targetId = btn.dataset.target;
    document.querySelectorAll("main section").forEach((sec) => {
      sec.style.display = sec.id === targetId ? "block" : "none";
    });
  });
});

(async () => {
  const token = new URLSearchParams(window.location.search).get("token");
  if (!token) return;

  const { ok, data } = await apiCall("/api/auth/token", "POST", {
    token,
  });
  if (ok) window.location.href = "/";
})();

document.getElementById("login-form").addEventListener("submit", async (e) => {
  e.preventDefault();

  const { ok, data } = await apiCall("/api/auth/login", "POST", {
    username: document.getElementById("login-username").value,
    password: document.getElementById("login-password").value,
  });

  if (ok) window.location.href = "/";
  else alert(data.message);
});

document
  .getElementById("register-form")
  .addEventListener("submit", async (e) => {
    e.preventDefault();

    const { data } = await apiCall("/api/auth/register", "POST", {
      email: document.getElementById("register-email").value,
      username: document.getElementById("register-username").value,
      password: document.getElementById("register-password").value,
    });

    alert(data.message);
  });

document.getElementById("reset-form").addEventListener("submit", async (e) => {
  e.preventDefault();

  const { data } = await apiCall("/api/auth/reset", "POST", {
    username: document.getElementById("reset-username").value,
    password: document.getElementById("reset-password").value,
  });

  alert(data.message);
});
