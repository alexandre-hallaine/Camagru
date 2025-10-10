import { apiCall, setCsrfToken } from "./shared.js";

let id = null;
let page = 0;
let hasMore = true;

let overlay = null;

async function renderFeed(clear = false) {
  if (clear) page = 0;
  else if (!hasMore) return;

  hasMore = false;
  const { data: images } = await apiCall(`/api/images?page=${++page}`, "GET");
  if (images.length == 5) hasMore = true;

  const feed = document.querySelector("#feed > div:first-child");
  if (clear) feed.innerHTML = "";

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

      if (ok) renderFeed(true);
      else alert(data.message);
    });

    const facebook = document.createElement("button");
    facebook.textContent = "Facebook";
    action.appendChild(facebook);
    facebook.addEventListener("click", () => {
      window.open(
        `https://www.facebook.com/sharer/sharer.php?u=${encodeURIComponent(
          window.location.href,
        )}`,
        "_blank",
      );
    });

    const twitter = document.createElement("button");
    twitter.textContent = "Twitter";
    action.appendChild(twitter);
    twitter.addEventListener("click", () => {
      window.open(
        `https://twitter.com/intent/tweet?url=${encodeURIComponent(
          window.location.href,
        )}`,
        "_blank",
      );
    });

    const pinterest = document.createElement("button");
    pinterest.textContent = "Pinterest";
    action.appendChild(pinterest);
    pinterest.addEventListener("click", () => {
      window.open(
        `https://pinterest.com/pin/create/button/?url=${encodeURIComponent(
          window.location.href,
        )}&media=${encodeURIComponent(
          image.content,
        )}&description=Check%20out%20this%20image!`,
        "_blank",
      );
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

window.addEventListener("scroll", () => {
  if (window.innerHeight + window.scrollY >= document.body.offsetHeight)
    renderFeed();
});

(async () => {
  const { data: settings } = await apiCall("/api/settings", "GET");

  setCsrfToken(settings.csrf_token);

  if ((id = settings.id)) {
    document.getElementById("notify-comments").checked =
      settings.notify_comments;
    document.getElementById("email").value = settings.email;
    document.getElementById("username").value = settings.username;
  } else {
    document.querySelector("header button#logout").textContent = "Login";
    document.querySelector("header button[data-target='settings']").remove();
    document.querySelector("header button[data-target='create']").remove();
  }

  await renderFeed(true);
})();

document.querySelectorAll("header button").forEach((btn) => {
  btn.addEventListener("click", () => {
    const targetId = btn.dataset.target;
    if (!targetId) return;
    document.querySelectorAll("main section").forEach((sec) => {
      sec.style.display = sec.id === targetId ? "flex" : "none";
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
      document.getElementById("username").value = data.username;
      document.getElementById("email").value = data.email;
      document.getElementById("password").value = "";
      renderFeed(true);
    }
  });

navigator.mediaDevices
  ?.getUserMedia({ video: true })
  .then((s) => (document.querySelector("video").srcObject = s))
  .catch((e) => console.error("Camera error:", e));

function toggleCreateStep(second) {
  if (!second) {
    document.getElementById("upload").value = "";
    document.getElementById("preview").src = "#";
    document.getElementById("thumbnails").style.display = "flex";
    document.getElementById("overlay").style.display = "block";
  }

  const actions = document.querySelectorAll("#create .action");
  actions[0].style.display = second ? "none" : "block";
  actions[1].style.display = second ? "block" : "none";
}

document.getElementById("upload").onchange = async (e) => {
  const file = e.target.files[0];
  if (!file) return;

  const preview = document.getElementById("preview");
  const overlay = document.getElementById("overlay");
  const thumbnails = document.getElementById("thumbnails");

  const reader = new FileReader();
  reader.readAsDataURL(file);
  await new Promise((resolve) => (reader.onload = resolve));

  if (file.type === "image/gif") preview.src = reader.result;
  else {
    const img = new Image();
    img.src = reader.result;
    await new Promise((resolve) => (img.onload = resolve));

    const canvas = document.createElement("canvas");
    canvas.width = img.width;
    canvas.height = img.height;
    const ctx = canvas.getContext("2d");
    ctx.drawImage(img, 0, 0);

    preview.src = canvas.toDataURL("image/png");
  }

  if (file.type === "image/gif") {
    overlay.style.display = "none";
    thumbnails.style.display = "none";
  }

  toggleCreateStep(true);
};

document.getElementById("capture").onclick = () => {
  const video = document.querySelector("video");
  const canvas = document.createElement("canvas");
  canvas.width = video.videoWidth;
  canvas.height = video.videoHeight;

  const ctx = canvas.getContext("2d");
  ctx.drawImage(video, 0, 0, canvas.width, canvas.height);

  document.getElementById("preview").src = canvas.toDataURL("image/png");

  toggleCreateStep(true);
};

(async () => {
  const { data } = await apiCall("/api/overlays", "GET");
  for (const overlayData of data) {
    const img = document.createElement("img");
    img.src = overlayData.content;
    document.getElementById("thumbnails").appendChild(img);
    img.onclick = () => {
      document.getElementById("overlay").src = img.src;
      overlay = overlayData.slug;
    };
  }
})();

document.getElementById("delete").onclick = () => {
  toggleCreateStep(false);
};

document.getElementById("share").onclick = async () => {
  const image = document.getElementById("preview").src;

  if (document.getElementById("overlay").style.display !== "none" && !overlay) {
    alert("You must select an overlay to share an image");
    return;
  }

  console.log({ image, overlay });

  const { ok, data } = await apiCall("/api/images", "POST", {
    image,
    overlay,
  });

  if (!ok) alert(data.message);
  else {
    toggleCreateStep(false);
    renderFeed(true);
  }
};
