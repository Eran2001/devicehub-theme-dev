/**
 * DeviceHub — Brand Filter
 *
 * Filters product cards within a section grid by brand slug.
 * Works by toggling visibility on .devhub-product-card[data-brands].
 *
 * Markup contract:
 *  - Filter buttons: .devhub-brand-tab[data-brand][data-section]
 *  - Grid container: #{section_id}-grid
 *  - Product cards:  .devhub-product-card[data-brands="slug1 slug2"]
 *
 * No dependencies. Loaded only on is_front_page() via inc/enqueue.php.
 */

(function () {
  "use strict";

  function initBrandFilters() {
    document.querySelectorAll(".devhub-brand-tab").forEach(function (btn) {
      btn.addEventListener("click", function () {
        const sectionId = this.dataset.section;
        const brand = this.dataset.brand;

        // Update active state — scoped to this section's tabs only
        document
          .querySelectorAll("#" + sectionId + " .devhub-brand-tab")
          .forEach(function (tab) {
            tab.classList.remove("devhub-brand-tab--active");
            tab.setAttribute("aria-pressed", "false");
          });

        this.classList.add("devhub-brand-tab--active");
        this.setAttribute("aria-pressed", "true");

        // Filter cards — scoped to this section's grid only
        const grid = document.querySelector("#" + sectionId + "-grid");
        let visibleCount = 0;

        grid.querySelectorAll(".devhub-product-card").forEach(function (card) {
          const cardBrands = card.dataset.brands
            ? card.dataset.brands.split(" ")
            : [];

          const visible = brand === "all" || cardBrands.includes(brand);
          card.style.display = visible ? "" : "none";
          if (visible) visibleCount++;
        });

        // Show/hide empty state
        let empty = grid.querySelector(".devhub-brand-empty");
        if (visibleCount === 0) {
          if (!empty) {
            empty = document.createElement("div");
            empty.className = "devhub-brand-empty";
            empty.innerHTML =
              '<p>No products found for this brand.</p>';
            grid.appendChild(empty);
          }
          empty.style.display = "";
        } else if (empty) {
          empty.remove();
        }
      });
    });
  }

  function initBrandScrollers() {
    const controls = document.querySelectorAll("[data-brand-scroll]");

    function getTrack(sectionId) {
      return document.querySelector("#" + sectionId + " .devhub-products__brands");
    }

    function updateState(sectionId) {
      const track = getTrack(sectionId);
      if (!track) return;

      const prev = document.querySelector(
        '[data-brand-scroll="' + sectionId + '"][data-brand-direction="prev"]'
      );
      const next = document.querySelector(
        '[data-brand-scroll="' + sectionId + '"][data-brand-direction="next"]'
      );
      const hasOverflow = track.scrollWidth > track.clientWidth + 2;
      const atStart = track.scrollLeft <= 2;
      const atEnd = track.scrollLeft + track.clientWidth >= track.scrollWidth - 2;

      [prev, next].forEach(function (btn) {
        if (!btn) return;
        btn.classList.toggle("is-hidden", !hasOverflow);
      });

      if (prev) prev.disabled = !hasOverflow || atStart;
      if (next) next.disabled = !hasOverflow || atEnd;
    }

    controls.forEach(function (btn) {
      const sectionId = btn.dataset.brandScroll;
      const direction = btn.dataset.brandDirection;
      const track = getTrack(sectionId);

      if (!track) return;

      btn.addEventListener("click", function () {
        const amount = Math.max(180, Math.round(track.clientWidth * 0.72));
        track.scrollBy({
          left: direction === "prev" ? -amount : amount,
          behavior: "smooth",
        });
      });

      track.addEventListener("scroll", function () {
        updateState(sectionId);
      }, { passive: true });

      updateState(sectionId);
    });

    window.addEventListener("resize", function () {
      controls.forEach(function (btn) {
        updateState(btn.dataset.brandScroll);
      });
    });
  }

  function init() {
    initBrandFilters();
    initBrandScrollers();
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", init);
  } else {
    init();
  }
})();
