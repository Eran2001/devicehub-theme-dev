(function () {
  "use strict";

  function prefersReducedMotion() {
    return window.matchMedia("(prefers-reduced-motion: reduce)").matches;
  }

  function uniqueElements(selectors) {
    var seen = new Set();
    var elements = [];

    selectors.forEach(function (selector) {
      document.querySelectorAll(selector).forEach(function (element) {
        if (!seen.has(element)) {
          seen.add(element);
          elements.push(element);
        }
      });
    });

    return elements;
  }

  function markVisible(element) {
    element.classList.add("devhub-motion-visible");
  }

  function prepareElements(elements, baseDelay) {
    elements.forEach(function (element, index) {
      element.classList.add("devhub-motion-item");
      element.style.setProperty(
        "--devhub-motion-delay",
        Math.min(baseDelay + index * 42, 260) + "ms"
      );
    });
  }

  function revealPageContent() {
    var content = document.getElementById("content");
    var pageTargets = [];

    if (content) {
      pageTargets = Array.prototype.slice.call(content.children).filter(function (child) {
        return !["SCRIPT", "STYLE", "NOSCRIPT"].includes(child.tagName);
      });
    }

    prepareElements(pageTargets, 0);

    window.requestAnimationFrame(function () {
      pageTargets.forEach(markVisible);
    });
  }

  function revealOnScroll() {
    var scrollTargets = uniqueElements([
      ".devhub-page-bar",
      ".devhub-flash",
      ".devhub-products",
      ".devhub-categories",
      ".devhub-preorder",
      ".devhub-broadbands",
      ".devhub-hero",
      ".devhub-before-broadbands-banner",
      ".devhub-before-electronics-banner",
      ".devhub-before-accessories-banner",
      ".devhub-archive__sidebar",
      ".devhub-archive__main",
      ".devhub-archive__toolbar",
      ".devhub-archive__grid .devhub-product-card",
      ".devhub-single__gallery",
      ".devhub-single__info > *",
      ".devhub-single__tabs",
      ".devhub-search__intro",
      ".devhub-search__grid .devhub-product-card",
      ".devhub-account-wrap",
      ".devhub-account-sidebar > *",
      ".devhub-account-content > *",
      ".devhub-auth__card",
      ".devhub-dashboard-card",
      ".devhub-address-card",
      ".devhub-empty-state",
      ".devhub-delivery-method",
      ".woocommerce-cart-form",
      ".cart_totals",
      ".wc-block-cart__main > *",
      ".wc-block-cart__sidebar > *",
      ".wc-block-checkout__main > *",
      ".wc-block-checkout__sidebar > *",
      ".woocommerce-order > *",
      ".woocommerce-order-details",
      ".woocommerce-customer-details",
    ]);

    prepareElements(scrollTargets, 0);

    if (!("IntersectionObserver" in window)) {
      scrollTargets.forEach(markVisible);
      return;
    }

    var observer = new IntersectionObserver(
      function (entries) {
        entries.forEach(function (entry) {
          if (!entry.isIntersecting) {
            return;
          }

          markVisible(entry.target);
          observer.unobserve(entry.target);
        });
      },
      {
        rootMargin: "0px 0px -8% 0px",
        threshold: 0.08,
      }
    );

    scrollTargets.forEach(function (element) {
      observer.observe(element);
    });
  }

  document.addEventListener("DOMContentLoaded", function () {
    if (prefersReducedMotion()) {
      document.documentElement.classList.add("devhub-motion-disabled");
      document.documentElement.classList.remove("devhub-motion-ready");
      return;
    }

    if (!document.documentElement.classList.contains("devhub-motion-ready")) {
      document.documentElement.classList.add("devhub-motion-ready");
    }
    revealPageContent();
    revealOnScroll();
  });
})();
