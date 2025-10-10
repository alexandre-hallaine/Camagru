import { apiCall, setCsrfToken } from "./shared.js";

let id = null;
let page = 1;

async function renderFeed() {
  const { data: images } = await apiCall(`/api/images?page=${page}`, "GET");

  document.getElementById("prev").disabled = page === 1;
  document.getElementById("next").disabled = images.length < 5;

  const feed = document.querySelector("#feed > div:first-child");
  feed.innerHTML = "";

  for (const image of images) {
    const div = document.createElement("div");
    div.classList.add("card");
    div.classList.add("image");
    feed.appendChild(div);

    const img = document.createElement("img");
    img.src = image.content;
    div.appendChild(img);

    const action = document.createElement("div");
    action.classList.add("action");
    div.appendChild(action);

    const username = document.createElement("h3");
    username.textContent = image.user.username;
    action.appendChild(username);

    const date = document.createElement("em");
    date.textContent = `Posted on ${new Date(image.created_at).toLocaleString()}`;
    action.appendChild(date);

    const like = document.createElement("button");
    like.textContent = image.liked ? "Unlike" : "Like";
    if (id) action.appendChild(like);
    like.addEventListener("click", async () => {
      const { ok, data } = await apiCall(
        `/api/images/${image.id}/like`,
        "POST",
      );
      if (ok) like.textContent = data.liked ? "Unlike" : "Like";
    });

    const deleteBtn = document.createElement("button");
    deleteBtn.textContent = "Delete";
    if (image.user.id === id) action.appendChild(deleteBtn);
    deleteBtn.addEventListener("click", async () => {
      if (!confirm("Are you sure you want to delete this image?")) return;

      const { ok, data } = await apiCall(`/api/images/${image.id}`, "DELETE");

      if (ok) {
        alert("Image deleted");
        renderFeed();
      } else alert(data.message);
    });

    const comments = document.createElement("div");
    comments.classList.add("comments");
    div.appendChild(comments);

    function createCommentElement(comment) {
      const commentDiv = document.createElement("div");
      comments.appendChild(commentDiv);

      const author = document.createElement("strong");
      author.textContent = comment.user.username;
      commentDiv.appendChild(author);

      const date = document.createElement("em");
      date.textContent = ` (${new Date(comment.created_at).toLocaleString()})`;
      commentDiv.appendChild(date);

      const body = document.createElement("span");
      body.textContent = `: ${comment.body}`;
      commentDiv.appendChild(body);
    }

    const commentAction = document.createElement("div");
    commentAction.classList.add("action");
    if (id) comments.appendChild(commentAction);

    const commentInput = document.createElement("input");
    commentInput.type = "text";
    commentAction.appendChild(commentInput);

    const commentButton = document.createElement("button");
    commentButton.textContent = "Post";
    commentAction.appendChild(commentButton);
    commentButton.addEventListener("click", async () => {
      const { ok, data } = await apiCall(
        `/api/images/${image.id}/comment`,
        "POST",
        { body: commentInput.value },
      );
      if (!ok) {
        alert(data.message);
        return;
      }

      createCommentElement({
        user: {
          username: document.getElementById("username").value,
        },
        body: commentInput.value,
        created_at: data.created_at,
      });

      commentInput.value = "";
    });

    for (const data of image.comments) createCommentElement(data);
  }
}

(async () => {
  const { data: settings } = await apiCall("/api/settings", "GET");

  setCsrfToken(settings.csrf_token);

  if (!(id = settings.id)) {
    document.querySelector("header button#logout").textContent = "Login";
    document.querySelector("header button[data-target='settings']").remove();
    document.querySelector("header button[data-target='create']").remove();
  }

  document.getElementById("notify-comments").checked = settings.notify_comments;
  document.getElementById("email").value = settings.email;
  document.getElementById("username").value = settings.username;

  document.getElementById("prev").addEventListener("click", () => {
    page--;
    renderFeed();
  });

  document.getElementById("next").addEventListener("click", () => {
    page++;
    renderFeed();
  });

  await renderFeed();
})();

document.querySelectorAll("header button").forEach((btn) => {
  btn.addEventListener("click", () => {
    const targetId = btn.dataset.target;
    if (!targetId) return;
    document.querySelectorAll("main section").forEach((sec) => {
      sec.style.display = sec.id === targetId ? "block" : "none";
    });
  });
});

document.getElementById("logout").addEventListener("click", async () => {
  const { ok } = await apiCall("/api/auth/logout", "POST");
  if (ok) window.location.href = "/auth/";
});

document
  .querySelector("section#settings form")
  .addEventListener("submit", async (e) => {
    e.preventDefault();

    const { ok, data } = await apiCall("/api/settings", "POST", {
      notify_comments: document.getElementById("notify-comments").checked,
      email: document.getElementById("email").value,
      username: document.getElementById("username").value,
      password: document.getElementById("password").value,
    });

    if (!ok) alert(data.message);
    else {
      alert("Settings updated");
      window.location.reload();
    }
  });

navigator.mediaDevices
  ?.getUserMedia({ video: true })
  .then((s) => (document.querySelector("video").srcObject = s))
  .catch((e) => console.error("Camera error:", e));

function toggleCreateStep(second) {
  document.querySelector("section#create > div:first-child").style.display =
    second ? "none" : "block";
  document.querySelector("section#create > div:last-child").style.display =
    second ? "flex" : "none";
}

document.getElementById("upload").onchange = (e) => {
  const file = e.target.files[0];
  if (!file) return;

  const canvas = document.querySelector("canvas");
  canvas.getContext("2d").clearRect(0, 0, canvas.width, canvas.height);

  const img = new Image();
  img.onload = () =>
    canvas.getContext("2d").drawImage(img, 0, 0, canvas.width, canvas.height);
  img.src = URL.createObjectURL(file);

  toggleCreateStep(true);
};

document.getElementById("capture").onclick = () => {
  const canvas = document.querySelector("canvas");
  canvas
    .getContext("2d")
    .drawImage(
      document.querySelector("video"),
      0,
      0,
      canvas.width,
      canvas.height,
    );

  toggleCreateStep(true);
};

let selectedOverlay = null;
(async () => {
  const { data } = await apiCall("/api/overlays", "GET");
  for (const overlayData of data) {
    const img = document.createElement("img");
    img.src = overlayData.content;
    img.dataset.slug = overlayData.slug;
    img.onclick = () => {
      overlay.src = img.src;
      selectedOverlay = overlayData.slug;
    };
    document.getElementById("thumbnails").appendChild(img);
  }
})();

document.getElementById("delete").onclick = () => {
  toggleCreateStep(false);
};

document.getElementById("share").onclick = async () => {
  if (!document.getElementById("overlay").src) {
    alert("You must select an overlay to share an image");
    return;
  }

  const offscreenCanvas = new OffscreenCanvas(640, 480);
  const ctx = offscreenCanvas.getContext("2d");
  ctx.drawImage(document.querySelector("canvas"), 0, 0);

  const blob = await offscreenCanvas.convertToBlob({
    type: "image/png",
  });
  const buffer = await blob.arrayBuffer();
  const base64 = `data:${blob.type};base64,${btoa(String.fromCharCode(...new Uint8Array(buffer)))}`;

  const { ok, data } = await apiCall("/api/images", "POST", {
    image: base64,
    overlay: selectedOverlay,
  });

  if (!ok) alert(data.message);
  else {
    alert("Image shared");
    renderFeed();
    toggleCreateStep(false);
  }
};
