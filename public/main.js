import { apiCall, setCsrfToken } from "./shared.js";

let id = null;
let page = 0;
let hasMore = true;

let overlay = null;

async function upload(data) {
  const blob = await (await fetch(data)).blob(); // convert base64 to blob
  const file = new File([blob], "camagru", { type: blob.type });

  const formData = new FormData();
  formData.append("file", file);

  return await fetch("https://file.hallaine.com/api", {
    method: "POST",
    body: formData,
  })
    .then(async (response) => (await response.json()).url)
    .catch(() => null);
}

async function renderFeed(clear = false) {
  if (clear) page = 0;
  else if (!hasMore) return;

  hasMore = false;
  const { data: images } = await apiCall(`/api/images?page=${++page}`, "GET");
  if (images.length === 5) hasMore = true;

  const feed = document.querySelector("#feed");
  if (clear) feed.innerHTML = "";

  for (const image of images) {
    const root = document.createElement("div");
    root.classList.add("post");
    root.innerHTML = `
      <div class="post-header">
        <b>${image.user.username}</b>
      </div>
      <div class="post-image">
        <img src="${image.content}" alt="Post Image">
      </div>
      <div class="post-content">
        <div class="post-actions">
          <button class="like-btn">${image.likes.some((userId) => userId === id) ? "Liked" : "Like"}</button>
          <button class="comment-btn">Comment</button>
          <button class="share-btn">Share</button>
          <button class="delete-btn">Delete</button>
        </div>
        <div class="post-likes">${image.likes.length} likes</div>
        <div class="post-comments">
          ${image.comments.map((comment) => `<div class="comment"><b>${comment.user.username}</b> ${comment.body}</div>`).join("")}
        </div>
        <div class="add-comment">
          <input type="text" placeholder="Add a comment...">
          <button>Post</button>
        </div>
      </div>
    `;
    feed.appendChild(root);

    const likeBtn = root.querySelector(".like-btn");
    if (id) {
      likeBtn.addEventListener("click", async () => {
        const { ok, data } = await apiCall(
          `/api/images/${image.id}/like`,
          "POST",
        );
        if (ok) likeBtn.innerHTML = data.liked ? "❤️" : "🤍";
      });

      const commentBtn = root.querySelector(".comment-btn");
      commentBtn.addEventListener("click", () => {
        root.querySelector(".add-comment input").focus();
      });

      const shareBtn = root.querySelector(".share-btn");
      shareBtn.addEventListener("click", async () => {
        if (navigator.share) {
          try {
            await navigator.share({
              title: "Check out this image from Camagru!",
              text: "I thought you might like this image.",
              url: image.content,
            });
          } catch (error) {
            console.error("Error sharing:", error);
          }
        } else {
          navigator.clipboard.writeText(image.content).then(() => {
            alert("Image URL copied to clipboard!");
          }, () => {
            alert("Failed to copy image URL to clipboard.");
          });
        }
      });

      const deleteBtn = root.querySelector(".delete-btn");
      if (id !== image.user.id) deleteBtn.style.display = "none";
      else {
        deleteBtn.addEventListener("click", async () => {
          if (!confirm("Are you sure you want to delete this image?")) return;

          const { ok, data } = await apiCall(
            `/api/images/${image.id}`,
            "DELETE",
          );

          if (ok) renderFeed(true);
          else alert(data.message);
        });
      }

      const addCommentBtn = root.querySelector(".add-comment button");
      const addCommentInput = root.querySelector(".add-comment input");

      if (id) {
        addCommentBtn.addEventListener("click", async () => {
          const body = addCommentInput.value.trim();
          if (body.length === 0) return alert("Comment cannot be empty");
          addCommentInput.value = "";

          const { ok, data } = await apiCall(
            `/api/images/${image.id}/comment`,
            "POST",
            { body },
          );

          if (!ok) return alert(data.message);

          const newComment = document.createElement("div");
          newComment.classList.add("comment");
          newComment.innerHTML = `<b>${document.getElementById("username").value}</b>: ${body}`;
          root.querySelector(".post-comments").appendChild(newComment);
        });
      } else {
        addCommentInput.style.display = "none";
        addCommentBtn.style.display = "none";
      }
    }
  }
}

window.addEventListener("scroll", () => {
  if (window.innerHeight + window.scrollY >= document.body.offsetHeight)
    renderFeed();
});

(async () => {
  const { data: settings } = await apiCall("/api/settings", "GET");

  setCsrfToken(settings.csrf_token);

  id = settings.id;
  if (id) {
    document.getElementById("notify-comments").checked =
      settings.notify_comments;
    document.getElementById("email").value = settings.email;
    document.getElementById("username").value = settings.username;
  } else {
    document.querySelector("a#logout").textContent = "Login";
    document.querySelector("a[data-target='settings']").style.display = "none";
    document.querySelector("a[data-target='create']").style.display = "none";
  }

  await renderFeed(true);
})();

document.querySelectorAll("nav a").forEach((link) => {
  link.addEventListener("click", (e) => {
    e.preventDefault();
    const targetId = link.dataset.target;
    if (!targetId) return;

    document.querySelectorAll("main section").forEach((sec) => {
      sec.classList.remove("active");
    });

    document.getElementById(targetId).classList.add("active");

    if (targetId === "create") {
      toggleCreateStep(false);
    }
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
  .then((s) => {
    document.querySelector("video").srcObject = s;
  })
  .catch((e) => console.error("Camera error:", e));

function toggleCreateStep(showPreview) {
  const video = document.querySelector("video");
  const preview = document.getElementById("preview");
  const overlay = document.getElementById("overlay");
  const captureBtn = document.getElementById("capture");
  const deleteBtn = document.getElementById("delete");
  const shareBtn = document.getElementById("share");
  const thumbnails = document.getElementById("thumbnails");

  if (showPreview) {
    video.style.display = "none";
    preview.style.display = "block";
    overlay.style.display = "block";
    deleteBtn.style.display = "inline-block";
    shareBtn.style.display = "inline-block";
    captureBtn.style.display = "none";
  } else {
    video.style.display = "block";
    preview.src = "#";
    preview.style.display = "none";
    overlay.src = "#";
    overlay.style.display = "none";
    thumbnails.style.display = "flex";
    deleteBtn.style.display = "none";
    shareBtn.style.display = "none";
    captureBtn.style.display = "inline-block";
  }
}

document.getElementById("upload").onchange = async (e) => {
  const file = e.target.files[0];
  if (!file) return;

  const preview = document.getElementById("preview");
  const overlay = document.getElementById("overlay");
  const thumbnails = document.getElementById("thumbnails");

  const reader = new FileReader();
  reader.readAsDataURL(file);
  await new Promise((resolve) => {
    reader.onload = resolve;
  });

  if (file.type === "image/gif") {
    preview.src = reader.result;
    overlay.style.display = "none";
    thumbnails.style.display = "none";
  } else {
    const img = new Image();
    img.src = reader.result;
    await new Promise((resolve) => {
      img.onload = resolve;
    });

    const canvas = document.createElement("canvas");
    canvas.width = img.width;
    canvas.height = img.height;
    const ctx = canvas.getContext("2d");
    ctx.drawImage(img, 0, 0);

    preview.src = canvas.toDataURL("image/png");
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

  if (navigator.share) {
    try {
      const response = await fetch(image);
      const blob = await response.blob();
      const file = new File([blob], 'image.png', { type: blob.type });

      await navigator.share({
        title: "Check out this image from Camagru!",
        text: "I thought you might like this image.",
        files: [file],
      });
    } catch (error) {
      console.error("Error sharing:", error);
    }
  } else {
    navigator.clipboard.writeText(image).then(() => {
      alert("Image URL copied to clipboard!");
    }, () => {
      alert("Failed to copy image URL to clipboard.");
    });
  }
};