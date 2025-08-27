document.getElementById("signupForm").addEventListener("submit", async (e) => {
    e.preventDefault();

    const data = {
        username: document.getElementById("username").value,
        email: document.getElementById("email").value,
        password: document.getElementById("password").value,
    };

    try {
        const res = await fetch("/api/signup", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify(data),
        });

        const result = await res.json();
        document.getElementById("result").textContent = JSON.stringify(result, null, 2);
    } catch (err) {
        document.getElementById("result").textContent = "Error: " + err;
    }
});