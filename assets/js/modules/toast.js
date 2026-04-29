(function () {
  "use strict";

  var icons = {
    success: "fa-check",
    warning: "fa-exclamation",
    error: "fa-times",
    info: "fa-info",
  };

  var titles = {
    success: "Success",
    warning: "Please check",
    error: "Something went wrong",
    info: "Notice",
  };

  function getRegion() {
    var region = document.querySelector(".devhub-toast-region");

    if (region) {
      return region;
    }

    region = document.createElement("div");
    region.className = "devhub-toast-region";
    region.setAttribute("aria-live", "polite");
    region.setAttribute("aria-atomic", "true");
    document.body.appendChild(region);

    return region;
  }

  function removeToast(toast) {
    if (!toast || toast.classList.contains("is-leaving")) {
      return;
    }

    toast.classList.add("is-leaving");
    window.setTimeout(function () {
      toast.remove();
    }, 220);
  }

  function showToast(options) {
    var settings = typeof options === "string" ? { message: options } : options || {};
    var type = ["success", "warning", "error", "info"].indexOf(settings.type) !== -1 ? settings.type : "info";
    var message = String(settings.message || "").trim();

    if (!message) {
      return null;
    }

    var toast = document.createElement("div");
    var close = document.createElement("button");
    var content = document.createElement("div");
    var title = document.createElement("p");
    var text = document.createElement("p");
    var icon = document.createElement("span");
    var duration = Number(settings.duration || 4200);

    toast.className = "devhub-toast devhub-toast--" + type;
    toast.setAttribute("role", type === "error" ? "alert" : "status");

    icon.className = "devhub-toast__icon";
    icon.innerHTML = '<i class="fas ' + icons[type] + '" aria-hidden="true"></i>';

    title.className = "devhub-toast__title";
    title.textContent = settings.title || titles[type];

    text.className = "devhub-toast__message";
    text.textContent = message;

    close.className = "devhub-toast__close";
    close.type = "button";
    close.setAttribute("aria-label", "Dismiss notification");
    close.innerHTML = '<i class="fas fa-times" aria-hidden="true"></i>';
    close.addEventListener("click", function () {
      removeToast(toast);
    });

    content.appendChild(title);
    content.appendChild(text);
    toast.appendChild(icon);
    toast.appendChild(content);
    toast.appendChild(close);

    getRegion().appendChild(toast);
    window.requestAnimationFrame(function () {
      toast.classList.add("is-visible");
    });

    if (duration > 0) {
      window.setTimeout(function () {
        removeToast(toast);
      }, duration);
    }

    return toast;
  }

  window.devhubToast = showToast;
  window.devhubNotify = {
    success: function (message, options) {
      return showToast(Object.assign({}, options, { type: "success", message: message }));
    },
    warning: function (message, options) {
      return showToast(Object.assign({}, options, { type: "warning", message: message }));
    },
    error: function (message, options) {
      return showToast(Object.assign({}, options, { type: "error", message: message }));
    },
    info: function (message, options) {
      return showToast(Object.assign({}, options, { type: "info", message: message }));
    },
  };
})();
