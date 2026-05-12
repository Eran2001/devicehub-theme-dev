(function () {
  "use strict";

  function getTrack() {
    return document.getElementById("devhub-archive-mobile-grid");
  }

  function getVisibleCards(track) {
    return Array.prototype.filter.call(
      track.querySelectorAll(".devhub-product-card"),
      function (card) {
        return card.style.display !== "none";
      }
    );
  }

  function updateCarousel() {
    const track = getTrack();
    if (!track) return;

    const carousel = track.closest("[data-archive-carousel]");
    const prev = document.querySelector(
      '[data-archive-scroll="product-list"][data-archive-direction="prev"]'
    );
    const next = document.querySelector(
      '[data-archive-scroll="product-list"][data-archive-direction="next"]'
    );
    const isMobile = window.matchMedia("(max-width: 575px)").matches;
    const visibleCards = getVisibleCards(track);
    const hasMultipleCards = visibleCards.length > 1;

    track.classList.toggle(
      "devhub-archive__grid--single-card",
      isMobile && visibleCards.length <= 1
    );

    if (carousel) {
      carousel.classList.toggle(
        "devhub-archive__carousel--active",
        isMobile && hasMultipleCards
      );
    }

    if (!isMobile || !hasMultipleCards) {
      [prev, next].forEach(function (btn) {
        if (!btn) return;
        btn.classList.add("is-hidden");
        btn.disabled = true;
      });
      track.scrollLeft = 0;
      return;
    }

    const maxScrollLeft = Math.max(0, track.scrollWidth - track.clientWidth);
    const atStart = track.scrollLeft <= 2;
    const atEnd = track.scrollLeft >= maxScrollLeft - 2;

    [prev, next].forEach(function (btn) {
      if (!btn) return;
      btn.classList.remove("is-hidden");
    });

    if (prev) prev.disabled = atStart;
    if (next) next.disabled = atEnd;
  }

  function init() {
    const track = getTrack();
    if (!track) return;

    document.querySelectorAll("[data-archive-scroll]").forEach(function (btn) {
      const direction = btn.dataset.archiveDirection;

      btn.addEventListener("click", function () {
        const amount = Math.max(220, Math.round(track.clientWidth * 0.76));
        track.scrollBy({
          left: direction === "prev" ? -amount : amount,
          behavior: "smooth",
        });
      });
    });

    track.addEventListener(
      "scroll",
      function () {
        updateCarousel();
      },
      { passive: true }
    );

    window.addEventListener("resize", updateCarousel);
    updateCarousel();
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", init);
  } else {
    init();
  }
})();
