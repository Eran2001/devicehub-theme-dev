(function () {
  "use strict";

  document.addEventListener("DOMContentLoaded", function () {
    var loader = document.getElementById("devhub-page-loader");
    if (!loader) return;
    loader.classList.add("is-ready");
    setTimeout(function () {
      loader.remove();
    }, 350);
  });
})();
