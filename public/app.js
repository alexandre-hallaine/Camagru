async function checkAuth() {
    try {
        const res = await fetch("/api/check-auth");
        const data = await res.json();
        if (data.authenticated) {
            showDashboard(data.user);
        } else {
            showAuthForms();
        }
    } catch (e) {
        showAuthForms();
    }
}

function showAuthForms() {
    document.getElementById("auth-container").style.display = "block";
    document.getElementById("dashboard").style.display = "none";
}

function showDashboard(user) {
    document.getElementById("auth-container").style.display = "none";
    document.getElementById("dashboard").style.display = "block";
    document.getElementById("user-username").textContent = user.username;
    
    const userInfo = document.getElementById("user-info");
    userInfo.innerHTML = `
        <h3>USER_DATA</h3>
        <p><strong>USERNAME:</strong> ${user.username}</p>
        <p><strong>EMAIL:</strong> ${user.email}</p>
        <p><strong>STATUS:</strong> ${user.confirmed ? 'VERIFIED' : 'PENDING'}</p>
        <p><strong>NOTIFICATIONS:</strong> ${user.notify_on_comment ? 'ON' : 'OFF'}</p>
        <p><strong>JOINED:</strong> ${new Date(user.created_at).toLocaleDateString()}</p>
    `;
}

function switchForm(hide, show) {
    document.getElementById(hide).style.display = "none";
    document.getElementById(show).style.display = "block";
}

function showResult(message) {
    document.getElementById("result").textContent = message;
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

document.getElementById("show-signin").addEventListener("click", e => {
    e.preventDefault();
    switchForm("signup-section", "signin-section");
});

document.getElementById("show-signup").addEventListener("click", e => {
    e.preventDefault();
    switchForm("signin-section", "signup-section");
});

document.getElementById("logout-btn").addEventListener("click", async () => {
    const { ok, data } = await apiCall("/api/logout", "POST");
    if (ok) {
        showAuthForms();
        showResult(data.message);
    } else {
        showResult("Logout failed");
    }
});

document.getElementById("signupForm").addEventListener("submit", async e => {
    e.preventDefault();
    const formData = {
        username: document.getElementById("signup-username").value,
        email: document.getElementById("signup-email").value,
        password: document.getElementById("signup-password").value
    };
    
    const { ok, data } = await apiCall("/api/signup", "POST", formData);
    if (ok) {
        showResult(data.message);
        document.getElementById("show-signin").click();
    } else {
        showResult(data.error);
    }
});

document.getElementById("signinForm").addEventListener("submit", async e => {
    e.preventDefault();
    const formData = {
        username: document.getElementById("signin-username").value,
        password: document.getElementById("signin-password").value
    };
    
    const { ok, data } = await apiCall("/api/signin", "POST", formData);
    if (ok) {
        showResult(data.message);
        checkAuth();
    } else {
        showResult(data.error);
    }
});

document.addEventListener("DOMContentLoaded", checkAuth);