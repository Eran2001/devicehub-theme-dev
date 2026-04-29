(function () {
  "use strict";

  document.addEventListener("DOMContentLoaded", function () {
    var mainHeader = document.querySelector("#wf_header .wf_navbar-wrapper.is--sticky");
    var secondaryNav = document.querySelector("#wf_header .devhub-secondary-nav");

    if (!mainHeader || !secondaryNav) {
      return;
    }

    function syncSecondaryNav() {
      var isSticky = mainHeader.classList.contains("on");
      secondaryNav.classList.toggle("is-sticky-active", isSticky);

      if (!isSticky) {
        secondaryNav.style.removeProperty("--devhub-secondary-sticky-top");
        return;
      }

      secondaryNav.style.setProperty(
        "--devhub-secondary-sticky-top",
        mainHeader.getBoundingClientRect().height + "px"
      );
    }

    syncSecondaryNav();
    window.addEventListener("scroll", syncSecondaryNav, { passive: true });
    window.addEventListener("resize", syncSecondaryNav);
  });
})();
