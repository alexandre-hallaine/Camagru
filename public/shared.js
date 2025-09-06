export function switchVisibility(hide, show) {
    for (let section of hide)
        document.getElementById(section).style.display = "none";
    document.getElementById(show).style.display = "block";
}

export async function apiCall(url, method, data) {
    if (data) data = JSON.stringify(data)

    const res = await fetch(url, {
        method,
        body: data,
        headers: { "Content-Type": "application/json" },
    });

    data = await res.json().catch(() => null);
    return { ok: res.ok, data };
}
