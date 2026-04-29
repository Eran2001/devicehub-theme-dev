/**
 * DeviceHub — Single Product
 *
 * Handles: tabs, color swatches, storage options,
 *          variation ID resolution, bundle carousel, buy now.
 */
(function () {
  "use strict";

  document.addEventListener("DOMContentLoaded", function () {
    devhubInitTabs();
    devhubInitGallery();
    devhubInitImageZoom();
    devhubInitImageLightbox();
    devhubInitColorSwatches();
    devhubInitStorageOptions();
    devhubResolveVariation();
    devhubInitBundleCarousel();
    devhubInitPaymentCarousel();
    devhubInitBuyNow();
    devhubInitPromoCountdown();
  });

  // ── Tabs ──────────────────────────────────────────────────────────────────

  function devhubInitTabs() {
    var tabBtns = document.querySelectorAll(".devhub-single__tab-btn");
    var tabPanels = document.querySelectorAll(".devhub-single__tab-panel");
    if (!tabBtns.length) return;

    tabBtns.forEach(function (btn) {
      btn.addEventListener("click", function () {
        var target = btn.getAttribute("data-tab");

        tabBtns.forEach(function (b) {
          b.classList.remove("devhub-single__tab-btn--active");
          b.setAttribute("aria-selected", "false");
        });
        tabPanels.forEach(function (p) {
          p.classList.remove("devhub-single__tab-panel--active");
          p.setAttribute("hidden", "");
        });

        btn.classList.add("devhub-single__tab-btn--active");
        btn.setAttribute("aria-selected", "true");

        var panelId =
          "devhubTab" + target.charAt(0).toUpperCase() + target.slice(1);
        var panel = document.getElementById(panelId);
        if (panel) {
          panel.classList.add("devhub-single__tab-panel--active");
          panel.removeAttribute("hidden");
        }
      });
    });
  }

  // ── Gallery thumbnails ────────────────────────────────────────────────────

  function devhubInitGallery() {
    var root = document.querySelector(".devhub-single");
    var mainImg = document.querySelector(".devhub-single__main-image img");
    var mainImageBox = document.querySelector(".devhub-single__main-image");
    var slider = document.getElementById("devhubGallerySlider");
    var viewport = document.getElementById("devhubGalleryViewport");
    var track =
      document.getElementById("devhubGalleryTrack") ||
      document.querySelector(".devhub-single__thumbnails");
    var prevBtn = document.getElementById("devhubGalleryPrev");
    var nextBtn = document.getElementById("devhubGalleryNext");
    if (!root || !track || !mainImg) return;

    var activeIndex = 0;
    var scrollIndex = 0;
    var defaultImages = [];

    if (mainImg) {
      mainImg.draggable = false;
    }

    if (mainImageBox) {
      mainImageBox.addEventListener("dragstart", function (event) {
        event.preventDefault();
      });
      mainImageBox.addEventListener("selectstart", function (event) {
        event.preventDefault();
      });
    }

    try {
      defaultImages = JSON.parse(root.getAttribute("data-default-gallery") || "[]");
    } catch (e) {
      defaultImages = [];
    }

    if (!Array.isArray(defaultImages) || !defaultImages.length) {
      defaultImages = Array.from(track.querySelectorAll(".devhub-single__thumb")).map(function (thumb) {
        var img = thumb.querySelector("img");
        return {
          main_src: thumb.getAttribute("data-main-src") || (img ? img.getAttribute("src") : ""),
          thumb_src: img ? img.getAttribute("src") : "",
          alt: thumb.getAttribute("data-alt") || (mainImg ? mainImg.getAttribute("alt") : ""),
        };
      }).filter(function (image) {
        return !!image.main_src;
      });
    }

    function escapeAttr(value) {
      return String(value || "")
        .replace(/&/g, "&amp;")
        .replace(/"/g, "&quot;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;");
    }

    function getThumbs() {
      return Array.from(track.querySelectorAll(".devhub-single__thumb"));
    }

    function isVerticalMode() {
      var screenWidth = window.innerWidth || document.documentElement.clientWidth;
      return screenWidth > 576 && screenWidth <= 768;
    }

    function getGap() {
      var styles = window.getComputedStyle(track);
      var gapProp = isVerticalMode() ? styles.rowGap : styles.columnGap;
      return parseFloat(gapProp || styles.gap || "0") || 0;
    }

    function setArrowMode(isVertical) {
      [prevBtn, nextBtn].forEach(function (btn) {
        if (!btn) return;
        btn.classList.toggle("devhub-single__gallery-arrow--vertical", isVertical);
      });

      if (prevBtn) {
        var prevIcon = prevBtn.querySelector(".fas");
        if (prevIcon) {
          prevIcon.className = "fas " + (isVertical ? "fa-chevron-up" : "fa-chevron-left");
        }
      }

      if (nextBtn) {
        var nextIcon = nextBtn.querySelector(".fas");
        if (nextIcon) {
          nextIcon.className = "fas " + (isVertical ? "fa-chevron-down" : "fa-chevron-right");
        }
      }
    }

    function resetCarousel() {
      if (!slider || !viewport || !prevBtn || !nextBtn) {
        return;
      }

      slider.style.height = "";
      viewport.style.height = "";
      track.style.transform = "";
      getThumbs().forEach(function (thumb) {
        thumb.style.width = "";
        thumb.style.height = "";
        thumb.style.flex = "";
      });
      prevBtn.hidden = true;
      nextBtn.hidden = true;
    }

    function resetThumbSizing(thumbs) {
      thumbs.forEach(function (thumb) {
        thumb.style.width = "";
        thumb.style.height = "";
        thumb.style.flex = "";
      });
    }

    function getHorizontalMetrics(thumbs) {
      var gap = getGap();
      var viewportWidth = viewport.getBoundingClientRect().width;

      if (viewportWidth <= 0 || !thumbs.length) {
        return null;
      }

      var totalWidth = 0;
      var visibleCount = 0;

      thumbs.forEach(function (thumb, index) {
        var thumbWidth = thumb.getBoundingClientRect().width;
        if (thumbWidth <= 0) {
          return;
        }

        var nextRight = totalWidth + thumbWidth;
        if (nextRight <= viewportWidth + 1) {
          visibleCount++;
        }

        totalWidth = nextRight + (index < thumbs.length - 1 ? gap : 0);
      });

      if (totalWidth <= viewportWidth + 1) {
        visibleCount = thumbs.length;
      }

      visibleCount = Math.max(1, Math.min(visibleCount, thumbs.length));

      return {
        gap: gap,
        hasOverflow: totalWidth > viewportWidth + 1,
        maxStart: Math.max(thumbs.length - visibleCount, 0),
        stepWidth: thumbs[0].getBoundingClientRect().width + gap,
        visibleCount: visibleCount,
        viewportWidth: viewportWidth,
      };
    }

    function syncHorizontalCarousel(thumbs) {
      slider.style.height = "";
      viewport.style.height = "";
      resetThumbSizing(thumbs);
      track.style.transform = "";

      var metrics = getHorizontalMetrics(thumbs);

      if (!metrics || metrics.stepWidth <= metrics.gap) {
        requestAnimationFrame(syncCarousel);
        return;
      }

      scrollIndex = Math.max(0, Math.min(scrollIndex, metrics.maxStart));

      prevBtn.hidden = !metrics.hasOverflow;
      nextBtn.hidden = !metrics.hasOverflow;

      track.style.transform = "translateX(-" + scrollIndex * metrics.stepWidth + "px)";

      if (metrics.hasOverflow) {
        prevBtn.style.visibility = scrollIndex <= 0 ? "hidden" : "visible";
        nextBtn.style.visibility = scrollIndex >= metrics.maxStart ? "hidden" : "visible";
      }
    }

    function syncCarousel() {
      if (!slider || !viewport || !prevBtn || !nextBtn || !mainImageBox) {
        return;
      }

      var thumbs = getThumbs();
      var verticalMode = isVerticalMode();
      setArrowMode(verticalMode);

      if (!thumbs.length) {
        scrollIndex = 0;
        resetCarousel();
        return;
      }

      if (!verticalMode) {
        syncHorizontalCarousel(thumbs);
        return;
      }

      var sliderHeight = mainImageBox.getBoundingClientRect().height;
      var gap = getGap();
      var maxStart = Math.max(thumbs.length - 2, 0);
      var hasOverflow = maxStart > 0;
      if (sliderHeight <= 0) {
        requestAnimationFrame(syncCarousel);
        return;
      }

      slider.style.height = sliderHeight + "px";
      prevBtn.hidden = !hasOverflow;
      nextBtn.hidden = !hasOverflow;

      var arrowHeight = hasOverflow ? (prevBtn.offsetHeight || 28) + (nextBtn.offsetHeight || 28) + gap * 2 : 0;
      var viewportHeight = sliderHeight - arrowHeight;
      var thumbHeight = (viewportHeight - gap) / 2;

      scrollIndex = Math.min(scrollIndex, maxStart);
      viewport.style.height = viewportHeight + "px";

      thumbs.forEach(function (thumb) {
        thumb.style.width = "100%";
        thumb.style.height = thumbHeight + "px";
        thumb.style.flex = "0 0 " + thumbHeight + "px";
      });

      track.style.transform = "translateY(-" + scrollIndex * (thumbHeight + gap) + "px)";

      if (hasOverflow) {
        prevBtn.style.visibility = scrollIndex <= 0 ? "hidden" : "visible";
        nextBtn.style.visibility = scrollIndex >= maxStart ? "hidden" : "visible";
      }
    }

    function activateThumb(index) {
      var thumbs = getThumbs();
      if (!thumbs.length) {
        return;
      }

      index = Math.max(0, Math.min(index, thumbs.length - 1));
      activeIndex = index;

      thumbs.forEach(function (thumb, thumbIndex) {
        thumb.classList.toggle("devhub-single__thumb--active", thumbIndex === activeIndex);
      });

      var activeThumb = thumbs[activeIndex];
      var nextSrc = activeThumb.getAttribute("data-main-src");
      var nextAlt = activeThumb.getAttribute("data-alt");
      var nextFullSrc = activeThumb.getAttribute("data-full-src");

      if (nextSrc) {
        mainImg.src = nextSrc;
      }
      if (nextAlt !== null) {
        mainImg.alt = nextAlt || "";
      }
      if (nextFullSrc) {
        mainImg.setAttribute("data-full-src", nextFullSrc);
      } else {
        mainImg.removeAttribute("data-full-src");
      }

      if (isVerticalMode()) {
        if (activeIndex < scrollIndex) {
          scrollIndex = activeIndex;
        } else if (activeIndex > scrollIndex + 1) {
          scrollIndex = activeIndex - 1;
        }
      } else {
        var metrics = getHorizontalMetrics(thumbs);
        if (metrics) {
          if (activeIndex < scrollIndex) {
            scrollIndex = activeIndex;
          } else if (activeIndex > scrollIndex + metrics.visibleCount - 1) {
            scrollIndex = activeIndex - metrics.visibleCount + 1;
          }
          scrollIndex = Math.max(0, Math.min(scrollIndex, metrics.maxStart));
        }
      }

      syncCarousel();
    }

    function bindThumbClicks() {
      getThumbs().forEach(function (thumb, index) {
        var img = thumb.querySelector("img");
        if (img) {
          img.draggable = false;
        }

        if (thumb.dataset.devhubBound === "true") {
          return;
        }

        thumb.dataset.devhubBound = "true";
        thumb.addEventListener("click", function () {
          activateThumb(index);
        });
      });
    }

    function setImages(images) {
      var nextImages = Array.isArray(images) && images.length ? images : defaultImages;

      track.innerHTML = nextImages.map(function (image, index) {
        var mainSrc = image.main_src || image.full_src || image.src || image.thumb_src || "";
        var thumbSrc = image.thumb_src || image.gallery_thumbnail_src || image.src || mainSrc;
        var alt = image.alt || "";

        var fullSrc = image.full_src || mainSrc;

        return (
          '<button class="devhub-single__thumb' +
          (index === 0 ? " devhub-single__thumb--active" : "") +
          '" type="button" data-main-src="' +
          escapeAttr(mainSrc) +
          '" data-full-src="' +
          escapeAttr(fullSrc) +
          '" data-alt="' +
          escapeAttr(alt) +
          '" aria-label="' +
          escapeAttr("View image " + (index + 1)) +
          '">' +
          '<img src="' +
          escapeAttr(thumbSrc) +
          '" alt="" draggable="false">' +
          "</button>"
        );
      }).join("");

      activeIndex = 0;
      scrollIndex = 0;
      bindThumbClicks();
      activateThumb(0);
      requestAnimationFrame(syncCarousel);
    }

    if (prevBtn) {
      prevBtn.addEventListener("click", function () {
        if (scrollIndex > 0) {
          scrollIndex--;
          syncCarousel();
        }
      });
    }

    if (nextBtn) {
      nextBtn.addEventListener("click", function () {
        var thumbs = getThumbs();
        var metrics = isVerticalMode() ? null : getHorizontalMetrics(thumbs);
        var maxStart = isVerticalMode()
          ? Math.max(thumbs.length - 2, 0)
          : (metrics ? metrics.maxStart : 0);
        if (scrollIndex < maxStart) {
          scrollIndex++;
          syncCarousel();
        }
      });
    }

    root.devhubGalleryController = {
      setImages: setImages,
      restoreDefault: function () {
        setImages(defaultImages);
      },
    };

    bindThumbClicks();
    activateThumb(0);
    requestAnimationFrame(syncCarousel);

    var resizeTimer;
    window.addEventListener("resize", function () {
      clearTimeout(resizeTimer);
      resizeTimer = setTimeout(syncCarousel, 100);
    });
  }

  // ── Color swatches ────────────────────────────────────────────────────────

  function devhubInitColorSwatches() {
    var swatches = document.querySelectorAll(".devhub-single__color-swatch");
    if (!swatches.length) return;

    swatches.forEach(function (swatch) {
      swatch.addEventListener("click", function () {
        if (swatch.disabled || swatch.classList.contains("devhub-single__color-swatch--disabled")) {
          return;
        }

        var isActive = swatch.classList.contains("devhub-single__color-swatch--active");
        var input = document.getElementById("devhubAttr_pa_color");

        if (isActive) {
          swatch.classList.remove("devhub-single__color-swatch--active");
          if (input) input.value = "";
          devhubResolveVariation();
          return;
        }

        swatches.forEach(function (s) {
          s.classList.remove("devhub-single__color-swatch--active");
        });
        swatch.classList.add("devhub-single__color-swatch--active");

        if (input) input.value = swatch.getAttribute("data-value");

        devhubResolveVariation();
      });
    });

    // Auto-select if only one option
    if (swatches.length === 1) {
      swatches[0].click();
    }
  }

  // ── Storage options ───────────────────────────────────────────────────────

  function devhubInitStorageOptions() {
    var btns = document.querySelectorAll(".devhub-single__storage-btn");
    if (!btns.length) return;

    btns.forEach(function (btn) {
      btn.addEventListener("click", function () {
        if (btn.disabled || btn.classList.contains("devhub-single__storage-btn--disabled")) {
          return;
        }

        var isActive = btn.classList.contains("devhub-single__storage-btn--active");
        var input = document.getElementById("devhubAttr_pa_storage");

        if (isActive) {
          btn.classList.remove("devhub-single__storage-btn--active");
          if (input) input.value = "";
          devhubResolveVariation();
          return;
        }

        btns.forEach(function (b) {
          b.classList.remove("devhub-single__storage-btn--active");
        });
        btn.classList.add("devhub-single__storage-btn--active");

        if (input) input.value = btn.getAttribute("data-value");

        devhubResolveVariation();
      });
    });

    // Auto-select if only one option
    if (btns.length === 1) {
      btns[0].click();
    }
  }

  function devhubUpdateOptionAvailability(variations, selectedColor, selectedStorage) {
    var swatches = document.querySelectorAll(".devhub-single__color-swatch");
    var storageBtns = document.querySelectorAll(".devhub-single__storage-btn");
    var colorInput = document.getElementById("devhubAttr_pa_color");
    var storageInput = document.getElementById("devhubAttr_pa_storage");

    function hasMatchingVariation(color, storage) {
      for (var i = 0; i < variations.length; i++) {
        var v = variations[i];
        var attr = v.attributes || {};
        var colorOk = !color || attr["attribute_pa_color"] === color;
        var storageOk = !storage || attr["attribute_pa_storage"] === storage;
        if (colorOk && storageOk) {
          return true;
        }
      }
      return false;
    }

    swatches.forEach(function (swatch) {
      var swatchColor = swatch.getAttribute("data-value");
      var enabled = hasMatchingVariation(swatchColor, selectedStorage);
      swatch.disabled = !enabled;
      swatch.classList.toggle("devhub-single__color-swatch--disabled", !enabled);

      if (!enabled && swatch.classList.contains("devhub-single__color-swatch--active")) {
        swatch.classList.remove("devhub-single__color-swatch--active");
        if (colorInput) colorInput.value = "";
      }
    });

    storageBtns.forEach(function (btn) {
      var storageValue = btn.getAttribute("data-value");
      var enabled = hasMatchingVariation(selectedColor, storageValue);
      btn.disabled = !enabled;
      btn.classList.toggle("devhub-single__storage-btn--disabled", !enabled);

      if (!enabled && btn.classList.contains("devhub-single__storage-btn--active")) {
        btn.classList.remove("devhub-single__storage-btn--active");
        if (storageInput) storageInput.value = "";
      }
    });
  }

  // ── Variation resolver ────────────────────────────────────────────────────

  function devhubResolveVariation() {
    var el = document.querySelector(".devhub-single");
    if (!el) return;

    var variations;
    try {
      variations = JSON.parse(el.getAttribute("data-variations") || "[]");
    } catch (e) {
      return;
    }
    if (!variations.length) return;

    var colorInput = document.getElementById("devhubAttr_pa_color");
    var storageInput = document.getElementById("devhubAttr_pa_storage");
    var varIdInput = document.getElementById("devhubVariationId");
    var priceBox = document.querySelector(".devhub-single__price");
    var stockBox = document.querySelector(".devhub-single__stock");
    var cartForm = document.querySelector(".devhub-single__cart-form");
    var cartBtn = cartForm ? cartForm.querySelector('button[name="add-to-cart"]') : null;
    var buyBtn = cartForm ? cartForm.querySelector(".devhub-single__btn--buy") : null;
    var galleryController = el.devhubGalleryController || null;

    var selectedColor = colorInput ? colorInput.value : "";
    var selectedStorage = storageInput ? storageInput.value : "";

    function syncPurchaseButtons(isAvailable) {
      if (cartBtn) {
        cartBtn.disabled = !isAvailable;
        cartBtn.setAttribute("aria-disabled", isAvailable ? "false" : "true");
      }

      if (buyBtn) {
        buyBtn.disabled = !isAvailable;
        buyBtn.setAttribute("aria-disabled", isAvailable ? "false" : "true");
      }
    }

    function normalizePriceHtml(html) {
      if (!html) return html;

      var temp = document.createElement("div");
      temp.innerHTML = html;

      if (
        temp.childElementCount === 1 &&
        temp.firstElementChild &&
        temp.firstElementChild.classList &&
        temp.firstElementChild.classList.contains("price")
      ) {
        return temp.firstElementChild.innerHTML;
      }

      return html;
    }

    if (priceBox && !el.dataset.basePriceHtml) {
      el.dataset.basePriceHtml = priceBox.innerHTML;
    }
    if (stockBox && !el.dataset.baseStockHtml) {
      el.dataset.baseStockHtml = stockBox.innerHTML;
    }
    if (!el.dataset.baseIsPurchasable) {
      el.dataset.baseIsPurchasable =
        stockBox && stockBox.classList.contains("devhub-single__stock--out") ? "0" : "1";
    }

    devhubUpdateOptionAvailability(variations, selectedColor, selectedStorage);

    selectedColor = colorInput ? colorInput.value : "";
    selectedStorage = storageInput ? storageInput.value : "";

    var match = null;
    for (var i = 0; i < variations.length; i++) {
      var v = variations[i];
      var attr = v.attributes;
      var colorOk =
        !attr["attribute_pa_color"] ||
        attr["attribute_pa_color"] === selectedColor;
      var storageOk =
        !attr["attribute_pa_storage"] ||
        attr["attribute_pa_storage"] === selectedStorage;
      if (colorOk && storageOk) {
        match = v;
        break;
      }
    }

    if (match) {
      if (varIdInput) varIdInput.value = match.id;
      if (priceBox && match.price_html) {
        priceBox.innerHTML = normalizePriceHtml(match.price_html);
      }
      if (stockBox) {
        stockBox.classList.remove("devhub-single__stock--in", "devhub-single__stock--out");
        stockBox.classList.add(match.in_stock ? "devhub-single__stock--in" : "devhub-single__stock--out");
        stockBox.innerHTML =
          '<span class="devhub-single__stock-dot" aria-hidden="true"></span>' +
          (match.in_stock ? "In stock" : "Out of stock");
      }
      if (galleryController) {
        if (Array.isArray(match.gallery_images) && match.gallery_images.length) {
          galleryController.setImages(match.gallery_images);
        } else {
          galleryController.restoreDefault();
        }
      }
      syncPurchaseButtons(!!match.in_stock);
      return;
    }

    if (varIdInput) varIdInput.value = "";
    if (priceBox && el.dataset.basePriceHtml) {
      priceBox.innerHTML = el.dataset.basePriceHtml;
    }
    if (stockBox && el.dataset.baseStockHtml) {
      stockBox.innerHTML = el.dataset.baseStockHtml;
      stockBox.classList.remove("devhub-single__stock--in", "devhub-single__stock--out");
      stockBox.classList.add(el.dataset.baseStockHtml.indexOf("Out of stock") !== -1 ? "devhub-single__stock--out" : "devhub-single__stock--in");
    }
    if (galleryController) {
      galleryController.restoreDefault();
    }
    syncPurchaseButtons(el.dataset.baseIsPurchasable === "1");

    if (cartForm && !cartForm.dataset.devhubVariationGuardBound) {
      cartForm.dataset.devhubVariationGuardBound = "true";
      cartForm.addEventListener("submit", function (event) {
        if ((cartBtn && cartBtn.disabled) || !varIdInput || varIdInput.value) return;
        event.preventDefault();
        window.alert("Please select an available color and storage combination.");
      });
    }
  }

  // ── Bundle carousel ───────────────────────────────────────────────────────
  // Fix: use requestAnimationFrame to defer initial slide() until after
  // the browser has painted and viewport has its real width.

  function devhubInitBundleCarousel() {
    var viewport = document.querySelector(".devhub-single__bundles-viewport");
    var track = document.getElementById("devhubBundlesTrack");
    var nextBtn = document.getElementById("devhubBundleNext");
    var prevBtn = document.getElementById("devhubBundlePrev");
    var bundleSlider = document.querySelector(".devhub-single__bundles-slider");
    var bundleInput = document.getElementById("devhubBundlePackageId");
    var cartForm = document.querySelector(".devhub-single__cart-form");
    if (!track || !viewport) return;

    var cards = track.querySelectorAll(".devhub-single__bundle-card");
    var current = 0;
    var total = cards.length;
    var bundleRequired =
      bundleSlider && bundleSlider.getAttribute("data-bundle-required") === "1";
    var bundleClearable =
      bundleSlider && bundleSlider.getAttribute("data-bundle-clearable") === "1";

    function setSelectedPackage(packageId) {
      if (bundleInput) {
        bundleInput.value = packageId || "0";
      }
    }

    // Card selection — click to select, click again to deselect when allowed.
    cards.forEach(function (card) {
      card.addEventListener("click", function (e) {
        if (e.target.closest(".devhub-single__bundle-link")) return;

        var isActive = card.classList.contains("devhub-single__bundle-card--active");
        var packageId = card.getAttribute("data-package-id") || "0";

        if (isActive && !bundleRequired && bundleClearable) {
          card.classList.remove("devhub-single__bundle-card--active");
          setSelectedPackage("0");
          return;
        }

        if (isActive) return;

        cards.forEach(function (c) {
          c.classList.remove("devhub-single__bundle-card--active");
        });
        card.classList.add("devhub-single__bundle-card--active");
        setSelectedPackage(packageId);
      });
    });

    var preselectedCard = track.querySelector(".devhub-single__bundle-card--active");
    if (preselectedCard && bundleInput && !bundleInput.value) {
      setSelectedPackage(preselectedCard.getAttribute("data-package-id") || "0");
    }

    if (cartForm && !cartForm.dataset.devhubBundleGuardBound) {
      cartForm.dataset.devhubBundleGuardBound = "true";
      cartForm.addEventListener("submit", function (event) {
        if (!bundleRequired || !bundleInput) return;
        if (parseInt(bundleInput.value || "0", 10) > 0) return;
        event.preventDefault();
        window.alert("Please select a bundle package to continue.");
      });
    }

    function getGap() {
      var styles = window.getComputedStyle(track);
      return parseFloat(styles.gap || styles.columnGap || "0") || 0;
    }

    function getVisibleCount() {
      var screenWidth = window.innerWidth || document.documentElement.clientWidth;
      if (screenWidth <= 576) return 1;
      if (screenWidth < 992) return 2;
      if (screenWidth < 1200) return 3;
      return 4;
    }

    function getCardWidth() {
      // Use viewport's actual rendered width — never 0
      var viewportWidth = viewport.getBoundingClientRect().width;
      var visible = getVisibleCount();
      var gap = getGap();
      return (viewportWidth - gap * (visible - 1)) / visible;
    }

    function slide() {
      var visible = getVisibleCount();
      var gap = getGap();
      var maxStart = Math.max(total - visible, 0);
      var cardWidth = getCardWidth();

      // Guard: if viewport not rendered yet, retry on next frame
      if (cardWidth <= 0) {
        requestAnimationFrame(slide);
        return;
      }

      current = Math.min(current, maxStart);

      cards.forEach(function (card) {
        card.style.width = cardWidth + "px";
        card.style.flexShrink = "0";
      });

      var hasOverflow = total > visible;
      if (prevBtn) prevBtn.hidden = !hasOverflow;
      if (nextBtn) nextBtn.hidden = !hasOverflow;

      var offset = current * (cardWidth + gap);
      track.style.transform = "translateX(-" + offset + "px)";

      if (prevBtn && hasOverflow)
        prevBtn.style.visibility = current <= 0 ? "hidden" : "visible";
      if (nextBtn && hasOverflow)
        nextBtn.style.visibility = current >= maxStart ? "hidden" : "visible";
    }

    if (nextBtn) {
      nextBtn.addEventListener("click", function () {
        var maxStart = Math.max(total - getVisibleCount(), 0);
        if (current < maxStart) {
          current++;
          slide();
        }
      });
    }

    if (prevBtn) {
      prevBtn.addEventListener("click", function () {
        if (current > 0) {
          current--;
          slide();
        }
      });
    }

    // Defer initial render until browser has painted
    requestAnimationFrame(slide);

    var resizeTimer;
    window.addEventListener("resize", function () {
      clearTimeout(resizeTimer);
      resizeTimer = setTimeout(function () {
        slide();
      }, 100);
    });
  }

  // ── Image zoom (cursor-tracking magnifier) ────────────────────────────────

  function devhubInitImageZoom() {
    var box = document.querySelector(".devhub-single__main-image");
    if (!box) return;

    function getImg() {
      return box.querySelector("img");
    }

    box.addEventListener("mouseenter", function (e) {
      var img = getImg();
      if (!img) return;
      var rect = box.getBoundingClientRect();
      var x = ((e.clientX - rect.left) / rect.width) * 100;
      var y = ((e.clientY - rect.top) / rect.height) * 100;
      img.style.transformOrigin = x + "% " + y + "%";
      img.style.transition = "transform 0.22s ease";
      img.style.transform = "scale(2.2)";
    });

    box.addEventListener("mousemove", function (e) {
      var img = getImg();
      if (!img) return;
      var rect = box.getBoundingClientRect();
      var x = ((e.clientX - rect.left) / rect.width) * 100;
      var y = ((e.clientY - rect.top) / rect.height) * 100;
      // Update origin instantly so zoom follows cursor in real time
      img.style.transition = "none";
      img.style.transformOrigin = x + "% " + y + "%";
    });

    box.addEventListener("mouseleave", function () {
      var img = getImg();
      if (!img) return;
      img.style.transition = "transform 0.22s ease, transform-origin 0.22s ease";
      img.style.transform = "";
      img.style.transformOrigin = "";
    });
  }

  // ── Image lightbox ────────────────────────────────────────────────────────

  function devhubInitImageLightbox() {
    var mainImageBox = document.querySelector(".devhub-single__main-image");
    if (!mainImageBox) return;

    // Build lightbox DOM once
    var lightbox = document.createElement("div");
    lightbox.className = "devhub-lightbox";
    lightbox.setAttribute("role", "dialog");
    lightbox.setAttribute("aria-modal", "true");
    lightbox.setAttribute("aria-label", "Product image viewer");

    var inner = document.createElement("div");
    inner.className = "devhub-lightbox__inner";

    var img = document.createElement("img");
    img.className = "devhub-lightbox__img";
    img.alt = "";
    img.draggable = false;

    var closeBtn = document.createElement("button");
    closeBtn.className = "devhub-lightbox__close";
    closeBtn.setAttribute("aria-label", "Close");
    closeBtn.innerHTML = "&times;";

    var prevBtn = document.createElement("button");
    prevBtn.className = "devhub-lightbox__nav devhub-lightbox__nav--prev";
    prevBtn.setAttribute("aria-label", "Previous image");
    prevBtn.innerHTML = '<i class="fas fa-chevron-left" aria-hidden="true"></i>';
    prevBtn.hidden = true;

    var nextBtn = document.createElement("button");
    nextBtn.className = "devhub-lightbox__nav devhub-lightbox__nav--next";
    nextBtn.setAttribute("aria-label", "Next image");
    nextBtn.innerHTML = '<i class="fas fa-chevron-right" aria-hidden="true"></i>';
    nextBtn.hidden = true;

    var counter = document.createElement("div");
    counter.className = "devhub-lightbox__counter";
    counter.hidden = true;

    inner.appendChild(img);
    lightbox.appendChild(closeBtn);
    lightbox.appendChild(prevBtn);
    lightbox.appendChild(inner);
    lightbox.appendChild(nextBtn);
    lightbox.appendChild(counter);
    document.body.appendChild(lightbox);

    lightbox.addEventListener("dragstart", function (event) {
      event.preventDefault();
    });
    lightbox.addEventListener("selectstart", function (event) {
      event.preventDefault();
    });

    var images = [];
    var currentIndex = 0;

    function collectImages() {
      var thumbs = document.querySelectorAll(".devhub-single__thumb");
      var list = Array.from(thumbs).map(function (thumb) {
        return {
          src: thumb.getAttribute("data-full-src") || thumb.getAttribute("data-main-src") || "",
          alt: thumb.getAttribute("data-alt") || "",
        };
      }).filter(function (item) { return !!item.src; });
      return list;
    }

    function updateNav() {
      var multiple = images.length > 1;
      prevBtn.hidden = !multiple || currentIndex <= 0;
      nextBtn.hidden = !multiple || currentIndex >= images.length - 1;
      counter.hidden = !multiple;
      if (multiple) {
        counter.textContent = (currentIndex + 1) + " / " + images.length;
      }
    }

    function showAt(index) {
      currentIndex = Math.max(0, Math.min(index, images.length - 1));
      img.src = images[currentIndex].src;
      img.alt = images[currentIndex].alt;
      updateNav();
    }

    function openLightbox() {
      var mainImg = mainImageBox.querySelector("img");
      if (!mainImg) return;

      images = collectImages();
      var currentSrc = mainImg.getAttribute("data-full-src") || mainImg.src;

      // Find which index is currently showing
      currentIndex = 0;
      for (var i = 0; i < images.length; i++) {
        if (images[i].src === currentSrc) { currentIndex = i; break; }
      }

      // Fallback: no thumbs found
      if (!images.length) {
        images = [{ src: currentSrc, alt: mainImg.alt || "" }];
        currentIndex = 0;
      }

      showAt(currentIndex);
      lightbox.classList.add("devhub-lightbox--open");
      document.body.style.overflow = "hidden";
      closeBtn.focus();
    }

    function closeLightbox() {
      lightbox.classList.remove("devhub-lightbox--open");
      document.body.style.overflow = "";
    }

    mainImageBox.addEventListener("click", openLightbox);

    closeBtn.addEventListener("click", function (e) {
      e.stopPropagation();
      closeLightbox();
    });

    prevBtn.addEventListener("click", function (e) {
      e.stopPropagation();
      if (currentIndex > 0) showAt(currentIndex - 1);
    });

    nextBtn.addEventListener("click", function (e) {
      e.stopPropagation();
      if (currentIndex < images.length - 1) showAt(currentIndex + 1);
    });

    // Click backdrop to close
    lightbox.addEventListener("click", function (e) {
      if (e.target === lightbox) closeLightbox();
    });

    document.addEventListener("keydown", function (e) {
      if (!lightbox.classList.contains("devhub-lightbox--open")) return;
      if (e.key === "Escape") closeLightbox();
      if (e.key === "ArrowLeft" && currentIndex > 0) showAt(currentIndex - 1);
      if (e.key === "ArrowRight" && currentIndex < images.length - 1) showAt(currentIndex + 1);
    });
  }

  // ── Buy Now ───────────────────────────────────────────────────────────────

  function devhubInitPaymentCarousel() {
    var viewport = document.getElementById("devhubPaymentViewport");
    var prevBtn = document.getElementById("devhubPaymentPrev");
    var nextBtn = document.getElementById("devhubPaymentNext");
    if (!viewport || !prevBtn || !nextBtn) return;

    function syncButtons() {
      var maxScroll = viewport.scrollWidth - viewport.clientWidth;
      var hasOverflow = maxScroll > 4;

      prevBtn.hidden = !hasOverflow;
      nextBtn.hidden = !hasOverflow;

      if (!hasOverflow) return;

      prevBtn.style.visibility = viewport.scrollLeft <= 4 ? "hidden" : "visible";
      nextBtn.style.visibility =
        viewport.scrollLeft >= maxScroll - 4 ? "hidden" : "visible";
    }

    function scrollByAmount(direction) {
      viewport.scrollBy({
        left: direction * Math.max(viewport.clientWidth * 0.75, 120),
        behavior: "smooth",
      });
    }

    prevBtn.addEventListener("click", function () {
      scrollByAmount(-1);
    });

    nextBtn.addEventListener("click", function () {
      scrollByAmount(1);
    });

    viewport.addEventListener("scroll", syncButtons, { passive: true });

    var resizeTimer;
    window.addEventListener("resize", function () {
      clearTimeout(resizeTimer);
      resizeTimer = setTimeout(syncButtons, 100);
    });

    requestAnimationFrame(syncButtons);
  }

  function devhubInitBuyNow() {
    var buyBtn = document.querySelector(".devhub-single__btn--buy");
    var form = document.querySelector(".devhub-single__cart-form");
    var submitBtn = form
      ? form.querySelector('button[name="add-to-cart"]')
      : null;
    if (!buyBtn || !form || !submitBtn) return;

    buyBtn.addEventListener("click", function () {
      if (buyBtn.disabled || submitBtn.disabled) return;
      if (!form.querySelector('[name="devhub_buy_now"]')) {
        var flag = document.createElement("input");
        flag.type = "hidden";
        flag.name = "devhub_buy_now";
        flag.value = "1";
        form.appendChild(flag);
      }
      submitBtn.click();
    });
  }

  // ── Promo bar countdown ───────────────────────────────────────────────────

  function devhubInitPromoCountdown() {
    var bar = document.querySelector(".devhub-promo-bar");
    if (!bar) return;

    var endTs = parseInt(bar.getAttribute("data-end"), 10);
    if (!endTs || isNaN(endTs)) return;

    var dEl = bar.querySelector('[data-unit="d"]');
    var hEl = bar.querySelector('[data-unit="h"]');
    var mEl = bar.querySelector('[data-unit="m"]');
    var sEl = bar.querySelector('[data-unit="s"]');
    if (!hEl || !mEl || !sEl) return;

    function pad(n) {
      return n < 10 ? "0" + n : String(n);
    }

    function tick() {
      var now = Math.floor(Date.now() / 1000);
      var diff = endTs - now;

      if (diff <= 0) {
        if (dEl) dEl.textContent = "00";
        hEl.textContent = "00";
        mEl.textContent = "00";
        sEl.textContent = "00";
        bar.style.display = "none";
        return;
      }

      var d = Math.floor(diff / 86400);
      var h = Math.floor((diff % 86400) / 3600);
      var m = Math.floor((diff % 3600) / 60);
      var s = diff % 60;

      if (dEl) dEl.textContent = pad(d);
      hEl.textContent = pad(h);
      mEl.textContent = pad(m);
      sEl.textContent = pad(s);
    }

    tick();
    setInterval(tick, 1000);
  }
})();
