function switchVisibility(hide, show) {
    for (let section of hide)
        document.getElementById(section).style.display = "none";
    document.getElementById(show).style.display = "block";
}

async function apiCall(url, method, data) {
    try {
        const res = await fetch(url, {
            method,
            headers: { "Content-Type": "application/json" },
            body: data ? JSON.stringify(data) : undefined
        });
        const result = await res.json();
        return { ok: res.ok, data: result };
    } catch (e) {
        return { ok: false, data: { error: "Request failed" } };
    }
}

function showResult(message) {
    document.getElementById("result").textContent = message;
}

document.addEventListener("DOMContentLoaded", async () => {
    const token = new URLSearchParams(window.location.search).get("token");

    let ok, data;
    if (token)
        ({ ok, data } = await apiCall("/api/auth/verify", "POST", {token}));
    else
        ({ ok, data } = await apiCall("/api/auth/check", "GET"));

    if (!ok)
        showResult(data.error);
    else if (data.authenticated)
        showResult(`Welcome back, ${data.username}!`);
    else if (token)
        showResult("Your account has been successfully verified!");
    else
        showResult("You are not signed in.");
});

document.getElementById("signup-form").addEventListener("submit", async e => {
    e.preventDefault();
    
    const { ok, data } = await apiCall("/api/auth/register", "POST", {
        username: document.getElementById("signup-username").value,
        email: document.getElementById("signup-email").value,
        password: document.getElementById("signup-password").value
    });

    showResult(ok ? data.message : data.error);
});

document.getElementById("signin-form").addEventListener("submit", async e => {
    e.preventDefault();

    const { ok, data } = await apiCall("/api/auth/login", "POST", {
        username: document.getElementById("signin-username").value,
        password: document.getElementById("signin-password").value
    });

    showResult(ok ? data.message : data.error);
});

document.getElementById("show-signin").addEventListener("click", e => {
    e.preventDefault();
    switchVisibility(["signup"], "signin");
});

document.getElementById("show-signup").addEventListener("click", e => {
    e.preventDefault();
    switchVisibility(["signin"], "signup");
});
