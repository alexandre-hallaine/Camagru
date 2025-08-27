document.getElementById("ping").onclick = async () => {
    const res = await fetch("/api");
    alert(await res.text());
};
