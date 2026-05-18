(function () {
  "use strict";

  const config = window.devhubCheckoutData || {};
  const fields = config.fields || {};
  const locations = Array.isArray(config.pickupLocations)
    ? config.pickupLocations
    : [];
  const messages = config.messages || {};
  const paymentVisuals = config.paymentVisuals || {};

  const DELIVERY_FIELD = fields.deliveryMethod || "devicehub/delivery_method";
  const PICKUP_FIELD = fields.pickupStore || "devicehub/pickup_store";
  const BILLING_EMAIL_FIELD = fields.billingEmail || "devicehub/billing_email";
  const CART_STORE_KEY =
    window.wc?.wcBlocksData?.CART_STORE_KEY || "wc/store/cart";
  const CHECKOUT_STORE_KEY =
    window.wc?.wcBlocksData?.CHECKOUT_STORE_KEY || "wc/store/checkout";
  const VALIDATION_STORE_KEY =
    window.wc?.wcBlocksData?.VALIDATION_STORE_KEY || "wc/store/validation";
  const DELIVERY_ERROR_KEY = "devhub-pickup-store";
  const PLACE_ORDER_SELECTOR =
    ".wc-block-components-checkout-place-order-button";
  const ORDER_SUMMARY_SELECTOR =
    ".wc-block-checkout__sidebar .wp-block-woocommerce-checkout-order-summary-block";
  const ORDER_SUMMARY_ITEM_SELECTOR = ".wc-block-components-order-summary-item";
  const CHECKOUT_SIDEBAR_SELECTOR = ".wc-block-checkout__sidebar";
  const ORDER_NOTE_PLACEHOLDER_SELECTOR =
    ".devhub-checkout-order-note-placeholder";
  const PAYMENT_STEP_SELECTOR = ".wp-block-woocommerce-checkout-payment-block";
  const PAYMENT_PLACEHOLDER_SELECTOR = ".devhub-checkout-payment-placeholder";
  const MOBILE_SUMMARY_PLACEHOLDER_SELECTOR =
    ".devhub-checkout-mobile-summary-placeholder";
  const SIDEBAR_RELOCATION_CLASS = "devhub-checkout--sidebar-relocation";
  const MOBILE_SIDEBAR_SUMMARY_CLASS = "devhub-checkout-mobile-sidebar-summary";
  const EMPTY_CHECKOUT_BUTTON_SELECTOR =
    ".wc-block-checkout-empty .wp-block-button__link";
  const COUPON_BUTTON_SELECTOR =
    ".wp-block-woocommerce-checkout-order-summary-coupon-form-block .wc-block-components-totals-coupon__button";
  const COUPON_INPUT_SELECTOR =
    ".wp-block-woocommerce-checkout-order-summary-coupon-form-block .wc-block-components-totals-coupon__input input";
  const COUPON_INPUT_LABEL_SELECTOR =
    ".wp-block-woocommerce-checkout-order-summary-coupon-form-block .wc-block-components-totals-coupon__input label";
  const DISCOUNT_CHIP_SELECTORS = [
    ".wc-block-checkout__sidebar .wc-block-components-totals-discount__coupon-list-item",
    ".wc-block-checkout__sidebar .wc-block-components-totals-discount .wc-block-components-chip",
    ".wc-block-checkout__sidebar .wc-block-components-totals-discount__coupon-list-item .wc-block-components-chip__text",
    ".wc-block-checkout__sidebar .wc-block-components-totals-discount .wc-block-components-chip__text",
  ];
  const CONTACT_EMAIL_INPUT_SELECTOR =
    '.wc-block-checkout__contact-fields .wc-block-components-text-input input[type="email"]';
  const CONTACT_EMAIL_LABEL_SELECTOR =
    ".wc-block-checkout__contact-fields .wc-block-components-text-input label";
  const BILLING_STEP_SELECTOR =
    ".wc-block-checkout__shipping-fields, .wc-block-checkout__billing-address, .wp-block-woocommerce-checkout-billing-address-block";
  const BILLING_ADDRESS_FORM_SELECTOR = ".wc-block-components-address-form";
  const BILLING_ADDRESS_CARD_SELECTOR = ".wc-block-components-address-card";
  const BILLING_EMAIL_FIELD_CLASS = "devhub-checkout-billing-email-field";
  const ADDRESS_LINE_2_TOGGLE_SELECTOR =
    ".wc-block-components-address-form__address_2-toggle";
  const NATIVE_DELIVERY_STEP_SELECTOR =
    ".wc-block-checkout__shipping-method, #shipping-method";
  const NATIVE_DELIVERY_OPTION_SELECTOR = `${NATIVE_DELIVERY_STEP_SELECTOR} .wc-block-components-radio-control__option`;
  const NATIVE_DELIVERY_CARD_SELECTOR = `${NATIVE_DELIVERY_STEP_SELECTOR} .wc-block-checkout__shipping-method-option`;
  const NATIVE_PICKUP_STEP_SELECTOR = ".wc-block-checkout__pickup-options";
  const NATIVE_PICKUP_OPTION_SELECTOR =
    ".wc-block-checkout__pickup-options .wc-block-components-radio-control__option";
  const NATIVE_PICKUP_INPUT_SELECTOR =
    '.wc-block-checkout__pickup-options input[type="radio"]';
  const DESKTOP_SIDEBAR_MEDIA = "(min-width: 1025px)";
  const MOBILE_SUMMARY_MEDIA = "(max-width: 1024px)";
  const CHECKOUT_LOADING_OVERLAY_ID = "devhub-checkout-loading-overlay";
  const ORDER_DETAILS_LAYOUT_SELECTOR = ".devhub-order-details-layout";
  const ORDER_DETAILS_ITEMS_LIST_SELECTOR = ".devhub-order-items-list";
  const ORDER_DETAILS_BALANCED_CLASS = "devhub-order-details-layout--balanced";

  const state = {};

  let unsubscribe = null;
  let lastSignature = "";
  let hasBoundViewportListener = false;
  let orderSummaryObserver = null;
  let observedOrderSummary = null;
  let orderSummaryObserverTimer = null;
  let paymentMethodsObserver = null;
  let observedPaymentStep = null;
  let discountSummaryTimer = null;
  let discountSummaryRequest = null;

  function getCheckoutStore() {
    return window.wp?.data?.select?.(CHECKOUT_STORE_KEY) || null;
  }

  function getCartStore() {
    return window.wp?.data?.select?.(CART_STORE_KEY) || null;
  }

  function getCartDispatch() {
    return window.wp?.data?.dispatch?.(CART_STORE_KEY) || null;
  }

  function getCheckoutDispatch() {
    return window.wp?.data?.dispatch?.(CHECKOUT_STORE_KEY) || null;
  }

  function getCartData() {
    return getCartStore()?.getCartData?.() || {};
  }

  function getValidationDispatch() {
    return window.wp?.data?.dispatch?.(VALIDATION_STORE_KEY) || null;
  }

  function getAdditionalFields() {
    return getCheckoutStore()?.getAdditionalFields?.() || {};
  }

  function patchAdditionalFields(patch) {
    const dispatch = getCheckoutDispatch();
    if (!dispatch?.setAdditionalFields) {
      return;
    }

    dispatch.setAdditionalFields({
      ...getAdditionalFields(),
      ...patch,
    });
  }

  function setPrefersCollection(method) {
    const dispatch = getCheckoutDispatch();
    if (dispatch?.setPrefersCollection) {
      dispatch.setPrefersCollection(method === "pickup");
    }
  }

  function normalizeText(value) {
    return String(value ?? "")
      .replace(/\s+/g, " ")
      .trim()
      .toLowerCase();
  }

  function updateDiscountChipElement(element, desiredLabel) {
    if (!element) {
      return;
    }

    const textNodeTarget = element.querySelector(".wc-block-components-chip__text");

    if (textNodeTarget) {
      if (normalizeText(textNodeTarget.textContent) !== normalizeText(desiredLabel)) {
        textNodeTarget.textContent = desiredLabel;
      }
      return;
    }

    const removeButton = element.querySelector(
      ".wc-block-components-chip__remove, .wc-block-components-chip__remove-icon, button, svg",
    );
    const currentLabel = normalizeText(
      element.textContent.replace(/\s*[×x]\s*$/, ""),
    );

    if (currentLabel === normalizeText(desiredLabel)) {
      return;
    }

    if (removeButton && removeButton.parentNode === element) {
      element.textContent = desiredLabel + " ";
      element.appendChild(removeButton);
      return;
    }

    element.textContent = desiredLabel;
  }

  function isValidMethod(method) {
    return method === "home_delivery" || method === "pickup";
  }

  function getLocationMap() {
    return locations.reduce((carry, location) => {
      carry[location.value] = location;
      return carry;
    }, {});
  }

  function getNativeDeliveryOptions() {
    const cardOptions = Array.from(
      document.querySelectorAll(NATIVE_DELIVERY_CARD_SELECTOR),
    );

    if (cardOptions.length) {
      return cardOptions.map((option) => ({
        option,
        input: null,
        rateId: getNativeOptionRateId(option),
        text: normalizeText(option.textContent),
        selected:
          option.classList.contains(
            "wc-block-checkout__shipping-method-option--selected",
          ) ||
          option.getAttribute("aria-checked") === "true" ||
          option.getAttribute("aria-pressed") === "true",
      }));
    }

    return Array.from(
      document.querySelectorAll(NATIVE_DELIVERY_OPTION_SELECTOR),
    ).map((option) => ({
      option,
      input: option.querySelector('input[type="radio"]'),
      rateId: getNativeOptionRateId(option),
      text: normalizeText(option.textContent),
      selected: !!option.querySelector('input[type="radio"]:checked'),
    }));
  }

  function getNativeOptionRateId(option) {
    if (!option) {
      return "";
    }

    const input = option.matches?.('input[type="radio"]')
      ? option
      : option.querySelector?.('input[type="radio"]');
    const candidateValues = [
      input?.value,
      option.dataset?.rateId,
      option.dataset?.shippingRate,
      option.dataset?.shippingMethodId,
      option.getAttribute?.("data-rate-id"),
      option.getAttribute?.("data-shipping-rate"),
      option.getAttribute?.("data-shipping-method-id"),
      option.getAttribute?.("data-value"),
      option.getAttribute?.("value"),
      option.getAttribute?.("id"),
    ];

    for (const candidate of candidateValues) {
      const normalized = normalizeText(candidate);

      if (normalized) {
        return normalized;
      }
    }

    return "";
  }

  function getMethodFromRateId(rateId) {
    const normalizedRateId = normalizeText(rateId);

    if (!normalizedRateId) {
      return "";
    }

    const methodId = normalizedRateId.split(":")[0];

    if (methodId === "pickup_location" || methodId === "local_pickup") {
      return "pickup";
    }

    return "home_delivery";
  }

  function getMethodFromNativeOption(option) {
    const methodFromRateId = getMethodFromRateId(option?.rateId);

    if (methodFromRateId) {
      return methodFromRateId;
    }

    const text = normalizeText(option?.text);

    if (!text) {
      return "";
    }

    if (text.includes("pickup") || text.includes("collect")) {
      return "pickup";
    }

    if (
      text.includes("ship") ||
      text.includes("delivery") ||
      text.includes("home")
    ) {
      return "home_delivery";
    }

    return "";
  }

  function getNativePickupOptions() {
    return Array.from(
      document.querySelectorAll(NATIVE_PICKUP_OPTION_SELECTOR),
    ).map((option) => ({
      option,
      input: option.querySelector('input[type="radio"]'),
      text: normalizeText(option.textContent),
    }));
  }

  function findLocationByNativeText(text) {
    const normalizedText = normalizeText(text);

    return (
      locations.find((location) => {
        const name = normalizeText(location.name);
        const address = normalizeText(location.address);
        return (
          (name && normalizedText.includes(name)) ||
          (address && normalizedText.includes(address))
        );
      }) || null
    );
  }

  function syncPickupStoreFromNativeSelection() {
    const additionalFields = getAdditionalFields();
    const method = additionalFields[DELIVERY_FIELD];
    const pickupStore = additionalFields[PICKUP_FIELD] || "";

    if (method !== "pickup" || pickupStore) {
      return false;
    }

    const selectedOption = getNativePickupOptions().find(
      (option) => option.input?.checked,
    );
    const matchedLocation = selectedOption
      ? findLocationByNativeText(selectedOption.text)
      : null;

    if (!matchedLocation) {
      return false;
    }

    patchAdditionalFields({
      [PICKUP_FIELD]: matchedLocation.value,
    });

    return true;
  }

  function syncNativePickupSelection(pickupStore) {
    const selectedLocation = getLocationMap()[pickupStore] || null;

    if (!selectedLocation) {
      return;
    }

    const targetOption = getNativePickupOptions().find((option) => {
      const matchedLocation = findLocationByNativeText(option.text);
      return matchedLocation?.value === selectedLocation.value;
    });

    if (!targetOption?.input || targetOption.input.checked) {
      return;
    }

    targetOption.input.checked = true;
    targetOption.input.dispatchEvent(new Event("change", { bubbles: true }));
  }

  function syncNativeDeliverySelection(method) {
    const targetOption = getNativeDeliveryOptions().find(
      (option) => getMethodFromNativeOption(option) === method,
    );

    if (!targetOption?.input || targetOption.input.checked) {
      return;
    }

    targetOption.input.checked = true;
    targetOption.input.dispatchEvent(new Event("change", { bubbles: true }));
  }

  function getSelectedNativeDeliveryMethod() {
    const selectedOption = getNativeDeliveryOptions().find(
      (option) => option.input?.checked || option.selected,
    );

    return selectedOption ? getMethodFromNativeOption(selectedOption) : "";
  }

  function getOrderSummaryDeliveryLabel(method, pickupStore) {
    if (method === "pickup") {
      const selectedLocation = getLocationMap()[pickupStore] || null;

      if (selectedLocation?.name) {
        return `Pickup (${selectedLocation.name})`;
      }

      return "Store Pickup";
    }

    return "Home Delivery";
  }

  function syncOrderSummaryDeliveryLabel(method, pickupStore) {
    if (document.querySelector(NATIVE_DELIVERY_STEP_SELECTOR)) {
      return;
    }

    const orderSummary = document.querySelector(ORDER_SUMMARY_SELECTOR);

    if (!orderSummary) {
      return;
    }

    const targetLabel = getOrderSummaryDeliveryLabel(method, pickupStore);
    const candidates = orderSummary.querySelectorAll(
      ".wc-block-components-totals-item__label, .wc-block-components-totals-shipping__via",
    );

    Array.from(candidates).forEach((candidate) => {
      const text = normalizeText(candidate.textContent);

      if (
        !text ||
        (!text.includes("pickup") &&
          !text.includes("shipping") &&
          !text.includes("delivery") &&
          !text.includes("ship") &&
          !text.includes("collect"))
      ) {
        return;
      }

      candidate.textContent = targetLabel;
    });
  }

  function bindNativeDeliveryListeners() {
    getNativeDeliveryOptions().forEach((option) => {
      if (option.input) {
        if (option.input.dataset.devhubDeliveryBound === "true") {
          return;
        }

        option.input.dataset.devhubDeliveryBound = "true";
        option.input.addEventListener("change", () => {
          if (!option.input?.checked) {
            return;
          }

          const nextMethod = getMethodFromNativeOption(option);

          if (
            !isValidMethod(nextMethod) ||
            getAdditionalFields()[DELIVERY_FIELD] === nextMethod
          ) {
            return;
          }

          patchAdditionalFields({
            [DELIVERY_FIELD]: nextMethod,
            [PICKUP_FIELD]:
              nextMethod === "pickup"
                ? getAdditionalFields()[PICKUP_FIELD] || ""
                : "",
          });
        });
        return;
      }

      if (
        !option.option ||
        option.option.dataset.devhubDeliveryBound === "true"
      ) {
        return;
      }

      option.option.dataset.devhubDeliveryBound = "true";
      option.option.addEventListener("click", () => {
        const nextMethod = getMethodFromNativeOption(option);

        if (!isValidMethod(nextMethod)) {
          return;
        }

        window.requestAnimationFrame(() => {
          const currentFields = getAdditionalFields();

          if (
            currentFields[DELIVERY_FIELD] === nextMethod &&
            (nextMethod === "pickup" || !currentFields[PICKUP_FIELD])
          ) {
            return;
          }

          patchAdditionalFields({
            [DELIVERY_FIELD]: nextMethod,
            [PICKUP_FIELD]:
              nextMethod === "pickup" ? currentFields[PICKUP_FIELD] || "" : "",
          });
        });
      });
    });
  }

  function bindNativePickupListeners() {
    getNativePickupOptions().forEach((option) => {
      if (!option.input || option.input.dataset.devhubPickupBound === "true") {
        return;
      }

      option.input.dataset.devhubPickupBound = "true";
      option.input.addEventListener("change", () => {
        if (!option.input?.checked) {
          return;
        }

        const matchedLocation = findLocationByNativeText(option.text);
        const currentFields = getAdditionalFields();
        const nextPatch = {
          [DELIVERY_FIELD]: "pickup",
        };

        if (
          matchedLocation &&
          currentFields[PICKUP_FIELD] !== matchedLocation.value
        ) {
          nextPatch[PICKUP_FIELD] = matchedLocation.value;
        }

        if (
          currentFields[DELIVERY_FIELD] === nextPatch[DELIVERY_FIELD] &&
          !(PICKUP_FIELD in nextPatch)
        ) {
          return;
        }

        patchAdditionalFields(nextPatch);
      });
    });
  }

  function syncDefaults() {
    const additionalFields = getAdditionalFields();
    const patch = {};
    const currentMethod = additionalFields[DELIVERY_FIELD];
    const nativeStepExists = !!document.querySelector(
      NATIVE_DELIVERY_STEP_SELECTOR,
    );

    if (!isValidMethod(currentMethod)) {
      patch[DELIVERY_FIELD] = locations.length
        ? "home_delivery"
        : "home_delivery";
    }

    if (additionalFields[DELIVERY_FIELD] === "pickup" && !locations.length) {
      patch[DELIVERY_FIELD] = "home_delivery";
    }

    if (Object.keys(patch).length) {
      patchAdditionalFields(patch);
      return false;
    }

    const nativeMethod = getSelectedNativeDeliveryMethod();

    if (nativeStepExists) {
      if (
        isValidMethod(nativeMethod) &&
        nativeMethod !== additionalFields[DELIVERY_FIELD]
      ) {
        patchAdditionalFields({
          [DELIVERY_FIELD]: nativeMethod,
          [PICKUP_FIELD]:
            nativeMethod === "pickup"
              ? additionalFields[PICKUP_FIELD] || ""
              : "",
        });
        return false;
      }
    } else {
      setPrefersCollection(additionalFields[DELIVERY_FIELD]);
      syncNativeDeliverySelection(additionalFields[DELIVERY_FIELD]);
    }

    if (
      additionalFields[DELIVERY_FIELD] === "pickup" &&
      additionalFields[PICKUP_FIELD]
    ) {
      syncNativePickupSelection(additionalFields[PICKUP_FIELD]);
    }

    if (syncPickupStoreFromNativeSelection()) {
      return false;
    }

    return true;
  }

  function isCheckoutProcessing() {
    return !!getCheckoutStore()?.isProcessing?.();
  }

  function ensureCheckoutLoadingOverlay() {
    let overlay = document.getElementById(CHECKOUT_LOADING_OVERLAY_ID);

    if (overlay) {
      return overlay;
    }

    overlay = document.createElement("div");
    overlay.id = CHECKOUT_LOADING_OVERLAY_ID;
    overlay.className = "devhub-checkout-loading-overlay";
    overlay.setAttribute("role", "status");
    overlay.setAttribute("aria-live", "polite");
    overlay.setAttribute("aria-hidden", "true");
    overlay.innerHTML = [
      '<div class="devhub-checkout-loading-overlay__loader">',
      '<span class="devhub-checkout-loading-overlay__spinner" aria-hidden="true"></span>',
      '<span class="devhub-checkout-loading-overlay__dots" aria-hidden="true">',
      "<span></span><span></span><span></span>",
      "</span>",
      '<span class="screen-reader-text">Placing your order</span>',
      "</div>",
    ].join("");
    document.body.appendChild(overlay);

    return overlay;
  }

  function setCheckoutLoadingOverlay(isLoading) {
    const overlay = isLoading
      ? ensureCheckoutLoadingOverlay()
      : document.getElementById(CHECKOUT_LOADING_OVERLAY_ID);

    if (!overlay) {
      return;
    }

    overlay.classList.toggle("is-active", isLoading);
    overlay.setAttribute("aria-hidden", isLoading ? "false" : "true");
    document.body.classList.toggle("devhub-checkout-page-loading", isLoading);
  }

  function syncProcessingState(isProcessing) {
    const orderSummary = document.querySelector(ORDER_SUMMARY_SELECTOR);

    setCheckoutLoadingOverlay(isProcessing);

    if (orderSummary) {
      orderSummary.classList.toggle("devhub-checkout-processing", isProcessing);
      orderSummary.setAttribute(
        "aria-disabled",
        isProcessing ? "true" : "false",
      );
    }
  }

  function getCartItems() {
    const cartStore = getCartStore();
    const cartData = cartStore?.getCartData?.();
    const items = cartStore?.getCartItems?.() || cartData?.items || [];

    return Array.isArray(items) ? items : [];
  }

  function getCartItemKey(item) {
    return item?.key || item?.cart_item_key || item?.item_key || "";
  }

  function syncHeaderCartCount(count = getCartItems().length) {
    document.querySelectorAll(".wf_navbar-cart-item .cart_count").forEach(
      (element) => {
        element.textContent = String(Math.max(0, Number(count) || 0));
      },
    );
  }

  function refreshHeaderCartUi() {
    syncHeaderCartCount();
    window.jQuery?.(document.body).trigger("wc_fragment_refresh");
  }

  function getStoreApiNonce() {
    return (
      window.wc?.wcSettings?.getSetting?.("storeApiNonce") ||
      window.wcSettings?.storeApiNonce ||
      window.wc_store_api_nonce ||
      ""
    );
  }

  function getStoreApiRoot() {
    const root =
      window.wpApiSettings?.root || `${window.location.origin}/wp-json/`;
    return root.replace(/\/$/, "") + "/wc/store/v1";
  }

  async function removeCheckoutCartItem(cartItemKey, button) {
    if (!cartItemKey || button?.disabled) {
      return;
    }

    const row = button?.closest?.(ORDER_SUMMARY_ITEM_SELECTOR);
    if (button) {
      button.disabled = true;
      button.setAttribute("aria-busy", "true");
    }
    if (row) {
      row.classList.add("devhub-checkout-summary-item--removing");
    }

    try {
      const cartDispatch = getCartDispatch();
      let cartData = null;

      if (cartDispatch?.removeItemFromCart) {
        cartData = await cartDispatch.removeItemFromCart(cartItemKey);
      } else if (window.wp?.apiFetch) {
        cartData = await window.wp.apiFetch({
          method: "POST",
          path: "/wc/store/v1/cart/remove-item",
          data: { key: cartItemKey },
        });
      } else {
        const response = await window.fetch(
          `${getStoreApiRoot()}/cart/remove-item`,
          {
            method: "POST",
            credentials: "same-origin",
            headers: {
              "Content-Type": "application/json",
              Nonce: getStoreApiNonce(),
            },
            body: JSON.stringify({ key: cartItemKey }),
          },
        );

        if (!response.ok) {
          throw new Error("Unable to remove cart item.");
        }

        cartData = await response.json();
      }

      if (cartData && cartDispatch?.receiveCart) {
        cartDispatch.receiveCart(cartData);
      }

      if (cartDispatch?.refreshCartItems) {
        await cartDispatch.refreshCartItems();
      }

      window.wp?.data
        ?.dispatch?.(CART_STORE_KEY)
        ?.invalidateResolutionForStoreSelector?.("getCartData");
      window.wp?.data
        ?.dispatch?.(CART_STORE_KEY)
        ?.invalidateResolutionForStoreSelector?.("getCartItems");
      refreshHeaderCartUi();
      window.setTimeout(() => {
        render();
        enhanceOrderSummaryRemoveButtons();
        refreshHeaderCartUi();
      }, 120);
    } catch (error) {
      if (button) {
        button.disabled = false;
        button.removeAttribute("aria-busy");
      }
      if (row) {
        row.classList.remove("devhub-checkout-summary-item--removing");
      }
      // Woo Blocks will usually render its own store notice for API errors.
      // Keep this path non-destructive so checkout does not full-page reload.
      window.console?.error?.(error);
    }
  }

  function enhanceOrderSummaryRemoveButtons() {
    const summary = document.querySelector(
      `.${MOBILE_SIDEBAR_SUMMARY_CLASS}, ${ORDER_SUMMARY_SELECTOR}`,
    );
    if (!summary) {
      return;
    }

    const cartItems = getCartItems();
    const rows = Array.from(
      summary.querySelectorAll(ORDER_SUMMARY_ITEM_SELECTOR),
    );

    rows.forEach((row, index) => {
      const cartItemKey = getCartItemKey(cartItems[index]);

      row.classList.add("devhub-checkout-summary-item");

      if (cartItemKey) {
        row.dataset.devhubCartItemKey = cartItemKey;
      }

      let button = row.querySelector(".devhub-checkout-summary-remove");
      if (!button) {
        button = document.createElement("button");
        button.type = "button";
        button.className = "devhub-checkout-summary-remove";
        button.innerHTML = '<i class="fas fa-times" aria-hidden="true"></i>';
        button.addEventListener("click", (event) => {
          event.preventDefault();
          event.stopPropagation();
          removeCheckoutCartItem(button.dataset.cartItemKey, button);
        });
        row.appendChild(button);
      }

      if (cartItemKey) {
        button.dataset.cartItemKey = cartItemKey;
        button.disabled = false;
      }

      button.setAttribute("aria-label", "Remove item from order summary");
    });
  }

  function observeOrderSummary() {
    const summary = document.querySelector(
      `.${MOBILE_SIDEBAR_SUMMARY_CLASS}, ${ORDER_SUMMARY_SELECTOR}`,
    );

    if (!summary || summary === observedOrderSummary) {
      return;
    }

    if (orderSummaryObserver) {
      orderSummaryObserver.disconnect();
    }

    observedOrderSummary = summary;
    orderSummaryObserver = new MutationObserver(() => {
      window.clearTimeout(orderSummaryObserverTimer);
      orderSummaryObserverTimer = window.setTimeout(() => {
        enhanceOrderSummaryRemoveButtons();
        enhanceProductNameTooltips();
      }, 40);
    });

    orderSummaryObserver.observe(summary, {
      childList: true,
      subtree: true,
    });
  }

  function hideProductNameTooltip() {
    document
      .querySelectorAll(
        ".devhub-checkout-product-name-tooltip.is-tooltip-visible",
      )
      .forEach((name) => {
        name.classList.remove("is-tooltip-visible");
        name.setAttribute("aria-expanded", "false");
      });

    document.querySelector(".devhub-checkout-product-tooltip")?.remove();
  }

  function showProductNameTooltip(name) {
    const fullName = name?.dataset?.fullName || "";

    if (!fullName) {
      return;
    }

    hideProductNameTooltip();

    const tooltip = document.createElement("div");
    tooltip.className = "devhub-checkout-product-tooltip";
    tooltip.textContent = fullName;
    document.body.appendChild(tooltip);

    const rect = name.getBoundingClientRect();
    const tooltipRect = tooltip.getBoundingClientRect();
    const viewportGap = 12;
    const top = Math.max(viewportGap, rect.top - tooltipRect.height - 10);
    const left = Math.min(
      Math.max(viewportGap, rect.left),
      window.innerWidth - tooltipRect.width - viewportGap,
    );

    tooltip.style.top = `${top}px`;
    tooltip.style.left = `${left}px`;

    name.classList.add("is-tooltip-visible");
    name.setAttribute("aria-expanded", "true");
  }

  function enhanceProductNameTooltips() {
    document
      .querySelectorAll(
        ".devhub-checkout-mobile-sidebar-summary .wc-block-components-product-name, .devhub-order-item-card__name-text, .woocommerce-order-received .woocommerce-order-overview__email strong",
      )
      .forEach((name) => {
        const fullName = normalizeText(name.textContent || "");

        if (!fullName) {
          return;
        }

        name.classList.add("devhub-checkout-product-name-tooltip");
        name.dataset.fullName = fullName;
        name.setAttribute("title", fullName);
        name.setAttribute("aria-label", fullName);
        name.setAttribute("aria-expanded", "false");
        name.setAttribute("tabindex", "0");

        if (name.dataset.devhubTooltipBound === "true") {
          return;
        }

        name.dataset.devhubTooltipBound = "true";

        const showTooltip = (event) => {
          if (event.type === "touchend") {
            event.preventDefault();
          }

          event.stopPropagation();
          const isVisible = name.classList.contains("is-tooltip-visible");
          hideProductNameTooltip();

          if (!isVisible) {
            showProductNameTooltip(name);
          }
        };

        name.addEventListener("click", showTooltip);
        name.addEventListener("touchend", showTooltip);
        name.addEventListener("keydown", (event) => {
          if (event.key === "Enter" || event.key === " ") {
            event.preventDefault();
            showTooltip(event);
          }

          if (event.key === "Escape") {
            hideProductNameTooltip();
          }
        });
      });
  }

  function setValidationState(method, pickupStore) {
    const validation = getValidationDispatch();

    if (!validation?.setValidationErrors || !validation?.clearValidationError) {
      return;
    }

    if (method === "pickup" && !pickupStore) {
      validation.setValidationErrors({
        [DELIVERY_ERROR_KEY]: {
          message:
            messages.pickupRequired ||
            "Please select a pickup store to continue.",
          hidden: false,
        },
      });
      return;
    }

    validation.clearValidationError(DELIVERY_ERROR_KEY);
  }

  function bindEffectSixButton(button) {
    if (!button || button.dataset.devhubEffectSixBound === "true") {
      return;
    }

    const getOriginalHtml = () =>
      button.dataset.devhubOriginalHtml || button.innerHTML;
    const isDisabled = () =>
      button.disabled || button.getAttribute("aria-disabled") === "true";

    button.dataset.devhubEffectSixBound = "true";

    button.addEventListener("mouseover", () => {
      const originalHTML = getOriginalHtml();

      if (
        !originalHTML ||
        isDisabled() ||
        button.classList.contains("animating") ||
        button.classList.contains("mouseover")
      ) {
        return;
      }

      button.classList.add("animating", "mouseover");

      const tempDiv = document.createElement("div");
      tempDiv.innerHTML = originalHTML;

      const chars = Array.from(tempDiv.childNodes);
      window.setTimeout(
        () => button.classList.remove("animating"),
        (chars.length + 1) * 50,
      );

      const animationType = button.dataset.animation || "text-spin";
      button.innerHTML = "";

      chars.forEach((node) => {
        if (node.nodeType === Node.TEXT_NODE) {
          node.textContent.split("").forEach((char) => {
            button.innerHTML += `<span class="letter">${char === " " ? "&nbsp;" : char}</span>`;
          });
          return;
        }

        button.innerHTML += `<span class="letter">${node.outerHTML}</span>`;
      });

      button.querySelectorAll(".letter").forEach((span, index) => {
        window.setTimeout(() => span.classList.add(animationType), 50 * index);
      });
    });

    button.addEventListener("mouseout", () => {
      button.classList.remove("mouseover");
      button.innerHTML = getOriginalHtml();
    });
  }

  function enhanceActionButton(button, customClass, fallbackText) {
    if (!button) {
      return;
    }

    button.classList.add("wf-btn", "wf-btn-primary", customClass);

    const text = (
      button.textContent ||
      button.getAttribute("aria-label") ||
      fallbackText
    ).trim();
    const desiredHtml = `${text}<i class="fas fa-arrow-right" aria-hidden="true"></i>`;

    if (button.dataset.devhubOriginalHtml !== desiredHtml) {
      button.dataset.devhubOriginalHtml = desiredHtml;
    }

    if (
      !button.classList.contains("mouseover") &&
      !button.className.includes("--loading") &&
      button.innerHTML !== desiredHtml
    ) {
      button.innerHTML = desiredHtml;
    }

    bindEffectSixButton(button);
  }

  function enhancePlaceOrderButton() {
    const button = document.querySelector(PLACE_ORDER_SELECTOR);

    enhanceActionButton(
      button,
      "devhub-checkout-place-order-button",
      "Place Order",
    );
    bindPlaceOrderLoadingWidth(button);
  }

  function isPlaceOrderButtonLoading(button) {
    if (!button) {
      return false;
    }

    return (
      button.classList.contains("devhub-place-order-loading") ||
      button.className.includes("--loading") ||
      button.classList.contains("is-loading") ||
      button.getAttribute("aria-busy") === "true" ||
      !!button.querySelector(
        ".wc-block-components-spinner, .components-spinner, .spinner",
      )
    );
  }

  function lockPlaceOrderButtonWidth(button) {
    if (!button || button.dataset.devhubLoadingWidthLocked === "1") {
      return;
    }

    const rect = button.getBoundingClientRect();

    if (rect.width <= 0) {
      return;
    }

    button.dataset.devhubLoadingWidthLocked = "1";
    button.classList.add("devhub-place-order-loading");
    button.style.setProperty(
      "width",
      `${Math.ceil(rect.width)}px`,
      "important",
    );
  }

  function unlockPlaceOrderButtonWidth(button) {
    if (!button || button.dataset.devhubLoadingWidthLocked !== "1") {
      return;
    }

    delete button.dataset.devhubLoadingWidthLocked;
    button.classList.remove("devhub-place-order-loading");
    button.style.removeProperty("width");
  }

  function syncPlaceOrderLoadingWidth(button) {
    if (isPlaceOrderButtonLoading(button)) {
      lockPlaceOrderButtonWidth(button);
      setCheckoutLoadingOverlay(true);
      return;
    }

    unlockPlaceOrderButtonWidth(button);
    setCheckoutLoadingOverlay(isCheckoutProcessing());
  }

  function bindPlaceOrderLoadingWidth(button) {
    if (!button || button.dataset.devhubLoadingWidthBound === "1") {
      return;
    }

    button.dataset.devhubLoadingWidthBound = "1";
    button.addEventListener("click", () => {
      lockPlaceOrderButtonWidth(button);
      setCheckoutLoadingOverlay(true);
      window.setTimeout(() => {
        if (
          !button.className.includes("--loading") &&
          !button.classList.contains("is-loading") &&
          button.getAttribute("aria-busy") !== "true" &&
          !button.querySelector(
            ".wc-block-components-spinner, .components-spinner, .spinner",
          ) &&
          !isCheckoutProcessing()
        ) {
          unlockPlaceOrderButtonWidth(button);
          setCheckoutLoadingOverlay(false);
        }
      }, 250);
    });

    const observer = new MutationObserver(() =>
      syncPlaceOrderLoadingWidth(button),
    );
    observer.observe(button, {
      attributes: true,
      attributeFilter: ["class", "aria-busy", "disabled", "aria-disabled"],
      childList: true,
      subtree: true,
    });

    syncPlaceOrderLoadingWidth(button);
  }

  function enhanceCouponButton() {
    enhanceActionButton(
      document.querySelector(COUPON_BUTTON_SELECTOR),
      "devhub-checkout-coupon-button",
      "Apply",
    );
  }

  function enhanceEmptyCheckoutButton() {
    const button = document.querySelector(EMPTY_CHECKOUT_BUTTON_SELECTOR);

    if (!button) {
      return;
    }

    button.closest(".wp-block-button")?.classList.remove("btn--effect-six");
    button.classList.remove(
      "wf-btn",
      "wf-btn-primary",
      "mouseover",
      "animating",
    );
    button.classList.add("devhub-empty-checkout-button");

    if (
      button.innerHTML !==
      'Browse store <i class="fas fa-arrow-right" aria-hidden="true"></i>'
    ) {
      button.innerHTML =
        'Browse store <i class="fas fa-arrow-right" aria-hidden="true"></i>';
    }
  }

  function enhanceCouponInput() {
    const input = document.querySelector(COUPON_INPUT_SELECTOR);
    const label = document.querySelector(COUPON_INPUT_LABEL_SELECTOR);

    if (!input) {
      return;
    }

    input.placeholder = "Enter code";

    if (label) {
      label.textContent = "Coupon code";
    }
  }

  function replaceDiscountChipLabel() {
    const summary = config.discountSummary || {};
    const desiredLabel = String(summary.chip_label || "").trim();

    if (!desiredLabel) {
      return;
    }

    DISCOUNT_CHIP_SELECTORS.forEach((selector) => {
      document.querySelectorAll(selector).forEach((element) => {
        updateDiscountChipElement(element, desiredLabel);
      });
    });
  }

  function requestDiscountSummaryRefresh() {
    if (!window.devhubConfig?.ajaxUrl || discountSummaryRequest) {
      return;
    }

    const payload = new window.URLSearchParams({
      action: "devhub_cart_discount_summary",
    });

    discountSummaryRequest = window
      .fetch(window.devhubConfig.ajaxUrl, {
        method: "POST",
        headers: {
          "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8",
        },
        credentials: "same-origin",
        body: payload.toString(),
      })
      .then((response) => response.json())
      .then((result) => {
        if (!result || !result.success || !result.data) {
          return;
        }

        config.discountSummary = result.data.discountSummary || {};
        config.virtualCouponLabel =
          result.data.virtualCouponLabel || config.virtualCouponLabel;
        replaceDiscountChipLabel();
      })
      .catch(() => null)
      .finally(() => {
        discountSummaryRequest = null;
      });
  }

  function scheduleDiscountSummaryRefresh() {
    window.clearTimeout(discountSummaryTimer);
    discountSummaryTimer = window.setTimeout(
      requestDiscountSummaryRefresh,
      180,
    );
  }

  function enhanceContactInput() {
    const input = document.querySelector(CONTACT_EMAIL_INPUT_SELECTOR);
    const label = document.querySelector(CONTACT_EMAIL_LABEL_SELECTOR);
    const accountEmail = String(config.accountEmail || "").trim();

    if (!input) {
      return;
    }

    input.placeholder = "Enter email address";

    if (accountEmail) {
      input.value = accountEmail;
      input.defaultValue = accountEmail;
      input.readOnly = true;
    }

    if (label) {
      label.textContent = "Email address";
    }
  }

  function getBillingEmail() {
    const additionalFields = getAdditionalFields();
    const customBillingEmail = String(
      additionalFields[BILLING_EMAIL_FIELD] || "",
    ).trim();
    const configuredBillingEmail = String(config.billingEmail || "").trim();
    const cartBillingEmail = String(
      getCartData()?.billingAddress?.email || "",
    ).trim();

    return customBillingEmail || configuredBillingEmail || cartBillingEmail;
  }

  function syncInitialBillingEmail() {
    const configuredBillingEmail = String(config.billingEmail || "").trim();
    const cartBillingEmail = String(
      getCartData()?.billingAddress?.email || "",
    ).trim();
    const customBillingEmail = String(
      getAdditionalFields()[BILLING_EMAIL_FIELD] || "",
    ).trim();
    const initialBillingEmail = configuredBillingEmail || cartBillingEmail;

    if (state.billingEmailInitialized || !initialBillingEmail) {
      return;
    }

    state.billingEmailInitialized = true;

    if (customBillingEmail !== initialBillingEmail) {
      setBillingEmail(initialBillingEmail);
    }
  }

  function setBillingEmail(email) {
    patchAdditionalFields({
      [BILLING_EMAIL_FIELD]: email,
    });
  }

  function getBillingStepTitle(step) {
    return normalizeText(
      step?.querySelector(".wc-block-components-checkout-step__title")
        ?.textContent || "",
    );
  }

  function getBillingSteps() {
    return Array.from(document.querySelectorAll(BILLING_STEP_SELECTOR)).filter(
      (step) => {
        const title = getBillingStepTitle(step);
        return !title || title.includes("billing address");
      },
    );
  }

  function ensureBillingEmailFormField(step) {
    const form = step.querySelector(BILLING_ADDRESS_FORM_SELECTOR);

    if (!form) {
      return;
    }

    let field = form.querySelector(`.${BILLING_EMAIL_FIELD_CLASS}`);
    const billingEmail = getBillingEmail();

    if (!field) {
      field = document.createElement("div");
      field.className = `wc-block-components-text-input ${BILLING_EMAIL_FIELD_CLASS}`;
      field.innerHTML = `
				<input type="email" id="devhub-billing-email" autocomplete="email">
				<label for="devhub-billing-email">Email address</label>
			`;
      form.appendChild(field);

      field.querySelector("input")?.addEventListener("input", (event) => {
        setBillingEmail(event.target.value);
      });
    }

    const input = field.querySelector("input");
    if (
      input &&
      document.activeElement !== input &&
      input.value !== billingEmail
    ) {
      input.value = billingEmail;
    }
  }

  function enhanceBillingEmailField() {
    syncInitialBillingEmail();

    getBillingSteps().forEach((step) => {
      ensureBillingEmailFormField(step);
      step
        .querySelectorAll(`.${BILLING_EMAIL_FIELD_CLASS}`)
        .forEach((field) => {
          field.style.display = step.querySelector(
            BILLING_ADDRESS_FORM_SELECTOR,
          )
            ? ""
            : "none";
        });
    });
  }

  function expandAddressLineTwo() {
    document
      .querySelectorAll(ADDRESS_LINE_2_TOGGLE_SELECTOR)
      .forEach((toggle) => {
        if (toggle instanceof HTMLElement) {
          toggle.click();
        }
      });
  }

  function shouldUseCheckoutSidebar() {
    return (
      typeof window.matchMedia !== "function" ||
      window.matchMedia(DESKTOP_SIDEBAR_MEDIA).matches
    );
  }

  function shouldUseMobileSidebarSummary() {
    return (
      typeof window.matchMedia === "function" &&
      window.matchMedia(MOBILE_SUMMARY_MEDIA).matches
    );
  }

  function isElementVisible(element) {
    return !!(
      element &&
      (element.offsetParent !== null || element.getClientRects().length)
    );
  }

  function getVisibleOrderSummaryBlock() {
    const blocks = Array.from(
      document.querySelectorAll(
        ".wp-block-woocommerce-checkout-order-summary-block",
      ),
    );

    return blocks.find((block) => isElementVisible(block)) || blocks[0] || null;
  }

  function syncSidebarRelocationState() {
    if (!document.body) {
      return;
    }

    document.body.classList.toggle(
      SIDEBAR_RELOCATION_CLASS,
      !!document.querySelector(
        ".wc-block-checkout, .wp-block-woocommerce-checkout",
      ) && shouldUseCheckoutSidebar(),
    );
  }

  function findOrderNoteStep() {
    const candidates = Array.from(
      document.querySelectorAll(
        ".wc-block-components-checkout-step, .wp-block-woocommerce-checkout-order-note-block, .wc-block-checkout__additional-fields",
      ),
    );

    return (
      candidates.find((candidate) => {
        if (!candidate) {
          return false;
        }

        const headingText = normalizeText(
          candidate.querySelector(
            ".wc-block-components-checkout-step__title, .wc-block-components-checkbox__label",
          )?.textContent || "",
        );
        const textarea = candidate.querySelector("textarea");
        const placeholderText = normalizeText(
          textarea?.getAttribute("placeholder") || "",
        );

        return (
          headingText.includes("add a note to your order") ||
          placeholderText.includes("notes about your order")
        );
      }) || null
    );
  }

  function ensureOrderNotePlaceholder(noteStep) {
    if (!noteStep || !noteStep.parentElement) {
      return null;
    }

    let placeholder = document.querySelector(ORDER_NOTE_PLACEHOLDER_SELECTOR);

    if (placeholder) {
      return placeholder;
    }

    placeholder = document.createElement("div");
    placeholder.className = "devhub-checkout-order-note-placeholder";
    placeholder.hidden = true;
    noteStep.parentElement.insertBefore(placeholder, noteStep);

    return placeholder;
  }

  function ensurePaymentPlaceholder(paymentStep) {
    if (!paymentStep || !paymentStep.parentElement) {
      return null;
    }

    let placeholder = document.querySelector(PAYMENT_PLACEHOLDER_SELECTOR);

    if (placeholder) {
      return placeholder;
    }

    placeholder = document.createElement("div");
    placeholder.className = "devhub-checkout-payment-placeholder";
    placeholder.hidden = true;
    paymentStep.parentElement.insertBefore(placeholder, paymentStep);

    return placeholder;
  }

  function ensureMobileSummaryPlaceholder(orderSummary) {
    if (!orderSummary || !orderSummary.parentElement) {
      return null;
    }

    let placeholder = document.querySelector(
      MOBILE_SUMMARY_PLACEHOLDER_SELECTOR,
    );

    if (placeholder) {
      return placeholder;
    }

    placeholder = document.createElement("div");
    placeholder.className = "devhub-checkout-mobile-summary-placeholder";
    placeholder.hidden = true;
    orderSummary.parentElement.insertBefore(placeholder, orderSummary);

    return placeholder;
  }

  function forceExpandedOrderSummary(orderSummary) {
    if (!orderSummary) {
      return;
    }

    orderSummary
      .querySelectorAll(
        '.wc-block-components-checkout-order-summary__button[aria-expanded="false"]',
      )
      .forEach((button) => {
        button.click();
      });

    orderSummary
      .querySelectorAll(
        ".wc-block-components-checkout-order-summary__content, .wc-block-components-order-summary__content",
      )
      .forEach((content) => {
        content.hidden = false;
        content.removeAttribute("hidden");
        content.removeAttribute("aria-hidden");
      });
  }

  function moveSidebarSummaryForMobile() {
    const sidebarSummary = document.querySelector(
      `.${MOBILE_SIDEBAR_SUMMARY_CLASS}, ${ORDER_SUMMARY_SELECTOR}`,
    );

    if (!sidebarSummary) {
      return;
    }

    const placeholder = ensureMobileSummaryPlaceholder(sidebarSummary);

    if (shouldUseMobileSidebarSummary()) {
      const mainSummary = document.querySelector(
        ".wc-block-checkout__main .wp-block-woocommerce-checkout-order-summary-block:not(.devhub-checkout-mobile-sidebar-summary)",
      );
      const paymentStep = document.querySelector(PAYMENT_STEP_SELECTOR);
      const anchor = mainSummary || paymentStep;

      sidebarSummary.classList.add(MOBILE_SIDEBAR_SUMMARY_CLASS);
      forceExpandedOrderSummary(sidebarSummary);

      if (
        anchor?.parentElement &&
        (sidebarSummary.parentElement !== anchor.parentElement ||
          sidebarSummary.nextElementSibling !== anchor)
      ) {
        anchor.parentElement.insertBefore(sidebarSummary, anchor);
      }

      return;
    }

    sidebarSummary.classList.remove(MOBILE_SIDEBAR_SUMMARY_CLASS);

    if (
      placeholder?.parentElement &&
      sidebarSummary.previousElementSibling !== placeholder
    ) {
      placeholder.insertAdjacentElement("afterend", sidebarSummary);
    }
  }

  function moveOrderNoteStep() {
    const noteStep = findOrderNoteStep();
    if (!noteStep) {
      return;
    }

    const placeholder = ensureOrderNotePlaceholder(noteStep);
    const orderSummary = getVisibleOrderSummaryBlock();
    const targetParent = orderSummary?.parentElement || null;

    noteStep.classList.add("devhub-checkout-order-note-step");

    if (orderSummary && targetParent) {
      if (
        noteStep.parentElement !== targetParent ||
        noteStep.previousElementSibling !== orderSummary
      ) {
        orderSummary.insertAdjacentElement("afterend", noteStep);
      }
      return;
    }

    if (
      placeholder?.parentElement &&
      noteStep.previousElementSibling !== placeholder
    ) {
      placeholder.insertAdjacentElement("afterend", noteStep);
    }
  }

  function movePaymentStep() {
    const paymentStep = document.querySelector(PAYMENT_STEP_SELECTOR);
    if (!paymentStep) {
      return;
    }

    const placeholder = ensurePaymentPlaceholder(paymentStep);
    const orderSummary = getVisibleOrderSummaryBlock();
    const noteStep = document.querySelector(".devhub-checkout-order-note-step");
    const targetParent = orderSummary?.parentElement || null;

    paymentStep.classList.add("devhub-checkout-payment-step");

    if (orderSummary && targetParent) {
      const anchor = noteStep || orderSummary;

      if (
        anchor &&
        (paymentStep.parentElement !== targetParent ||
          paymentStep.previousElementSibling !== anchor)
      ) {
        anchor.insertAdjacentElement("afterend", paymentStep);
      }
      return;
    }

    if (
      placeholder?.parentElement &&
      paymentStep.previousElementSibling !== placeholder
    ) {
      placeholder.insertAdjacentElement("afterend", paymentStep);
    }
  }

  function moveTermsBeforePlaceOrder() {
    if (shouldUseCheckoutSidebar()) {
      return;
    }

    const termsBlock = document.querySelector(
      ".wp-block-woocommerce-checkout-terms-block, .wc-block-checkout__terms",
    );
    if (!termsBlock) {
      return;
    }

    const placeOrderBtn = document.querySelector(PLACE_ORDER_SELECTOR);
    if (!placeOrderBtn) {
      return;
    }

    // The Place Order button and Return to Cart link share an actions container.
    // CSS inside that container often reverses their visual order, so inserting
    // terms *inside* the container lands it between the two siblings visually.
    // Fix: walk up from the button until we find the container that also holds
    // the Return to Cart link, then insert terms before that entire container.
    const returnLink = document.querySelector(
      '.wc-block-components-checkout-return-to-cart-button, [class*="return-to-cart"]',
    );

    let anchor = placeOrderBtn;
    let el = placeOrderBtn;
    while (el.parentElement && el.parentElement !== document.body) {
      el = el.parentElement;
      if (returnLink && el.contains(returnLink)) {
        anchor = el;
        break;
      }
    }

    if (
      termsBlock.nextElementSibling !== anchor ||
      termsBlock.parentElement !== anchor.parentElement
    ) {
      anchor.insertAdjacentElement("beforebegin", termsBlock);
    }
  }

  function getPaymentMethodMeta(text) {
    const normalized = normalizeText(text);
    const methods = paymentVisuals.methods || {};

    if (
      normalized.includes("pay with card") ||
      normalized.includes("sampath") ||
      normalized.includes("paycorp")
    ) {
      return {
        key: "card",
        label: methods.card?.label || "",
        description: methods.card?.description || "",
        images: Array.isArray(methods.card?.images)
          ? methods.card.images
          : methods.card?.image
            ? [methods.card.image]
            : [],
      };
    }

    if (normalized.includes("cash on delivery")) {
      return {
        key: "cod",
        label: methods.cod?.label || "",
        description: methods.cod?.description || "",
        images: Array.isArray(methods.cod?.images)
          ? methods.cod.images
          : methods.cod?.image
            ? [methods.cod.image]
            : [],
      };
    }

    return null;
  }

  function applyPaymentMethodVisual(option, meta) {
    if (!option || !meta) {
      return;
    }

    option.classList.add("devhub-payment-method-card");
    option.classList.toggle("devhub-payment-method-card--card", meta.key === "card");
    option.classList.toggle("devhub-payment-method-card--cod", meta.key === "cod");

    const label =
      option.querySelector(".wc-block-components-radio-control__label") ||
      option.querySelector(".wc-block-components-payment-method-label");
    const labelGroup =
      option.querySelector(".wc-block-components-radio-control__label-group") ||
      label?.parentElement;
    const description =
      option.parentElement?.querySelector(
        ".wc-block-components-radio-control__description, .wc-block-components-radio-control-accordion-content",
      ) || option.querySelector(
        ".wc-block-components-radio-control__description, .wc-block-components-radio-control-accordion-content",
      );

    if (label && meta.label) {
      label.textContent = meta.label;
    }

    if (description && meta.description) {
      description.textContent = meta.description;
    }

    if (labelGroup) {
      labelGroup.classList.add("devhub-payment-method-card__label-group");

      let media = labelGroup.querySelector(".devhub-payment-method-card__media");
      if (!media) {
        media = document.createElement("span");
        media.className = "devhub-payment-method-card__media";
        labelGroup.appendChild(media);
      }

      media.innerHTML = "";

      (meta.images || []).forEach((src, index) => {
        if (!src) {
          return;
        }

        const image = document.createElement("img");
        image.className = "devhub-payment-method-card__image";
        image.src = src;
        image.alt =
          index === 0 ? meta.label : `${meta.label} option ${index + 1}`;
        media.appendChild(image);
      });
    }
  }

  function enhancePaymentMethodsUi() {
    const paymentStep = document.querySelector(PAYMENT_STEP_SELECTOR);
    if (!paymentStep) {
      return;
    }

    const title = paymentStep.querySelector(
      ".wc-block-components-checkout-step__title",
    );

    if (title && paymentVisuals.heading) {
      title.textContent = paymentVisuals.heading;
    }

    paymentStep
      .querySelectorAll(".wc-block-components-radio-control__option")
      .forEach((option) => {
        const labelText =
          option.querySelector(".wc-block-components-radio-control__label")
            ?.textContent ||
          option.querySelector(".wc-block-components-payment-method-label")
            ?.textContent ||
          option.textContent;
        const meta = getPaymentMethodMeta(labelText);

        if (!meta) {
          return;
        }

        applyPaymentMethodVisual(option, meta);
      });
  }

  function observePaymentMethods() {
    const paymentStep = document.querySelector(PAYMENT_STEP_SELECTOR);

    if (!paymentStep) {
      return;
    }

    if (!paymentMethodsObserver) {
      paymentMethodsObserver = new MutationObserver(() => {
        window.requestAnimationFrame(() => {
          enhancePaymentMethodsUi();
        });
      });
    }

    if (observedPaymentStep === paymentStep) {
      return;
    }

    paymentMethodsObserver.disconnect();
    observedPaymentStep = paymentStep;
    paymentMethodsObserver.observe(paymentStep, {
      childList: true,
      subtree: true,
      attributes: true,
    });
  }

  function render() {
    syncSidebarRelocationState();

    if (!syncDefaults()) {
      return;
    }

    const additionalFields = getAdditionalFields();
    const method = isValidMethod(additionalFields[DELIVERY_FIELD])
      ? additionalFields[DELIVERY_FIELD]
      : "home_delivery";
    const pickupStore = additionalFields[PICKUP_FIELD] || "";
    const isProcessing = isCheckoutProcessing();

    bindNativeDeliveryListeners();
    bindNativePickupListeners();

    const signature = JSON.stringify({
      method,
      pickupStore,
      locationCount: locations.length,
      isProcessing,
    });

    if (signature === lastSignature) {
      syncProcessingState(isProcessing);
      syncOrderSummaryDeliveryLabel(method, pickupStore);
      syncBillingTitleForPickup(method);
      enhanceOrderSummaryRemoveButtons();
      replaceDiscountChipLabel();
      moveSidebarSummaryForMobile();
      observeOrderSummary();
      enhanceProductNameTooltips();
      enhancePaymentMethodsUi();
      observePaymentMethods();
      return;
    }

    lastSignature = signature;
    setValidationState(method, pickupStore);
    syncProcessingState(isProcessing);
    syncOrderSummaryDeliveryLabel(method, pickupStore);
    syncBillingTitleForPickup(method);
    enhancePlaceOrderButton();
    enhanceCouponButton();
    enhanceEmptyCheckoutButton();
    enhanceCouponInput();
    replaceDiscountChipLabel();
    scheduleDiscountSummaryRefresh();
    enhanceContactInput();
    enhanceOrderSummaryRemoveButtons();
    expandAddressLineTwo();
    moveSidebarSummaryForMobile();
    observeOrderSummary();
    enhanceProductNameTooltips();
    moveOrderNoteStep();
    movePaymentStep();
    moveTermsBeforePlaceOrder();
    enhancePaymentMethodsUi();
    observePaymentMethods();
  }

  function syncBillingTitleForPickup(method) {
    const billingTitle = document.querySelector(
      ".wc-block-checkout__billing-address .wc-block-components-checkout-step__title, " +
        ".wp-block-woocommerce-checkout-billing-address-block .wc-block-components-checkout-step__title",
    );

    if (!billingTitle) {
      return;
    }

    const targetTitle =
      method === "pickup" ? "Billing address" : "Shipping address";

    if (billingTitle.textContent.trim() !== targetTitle) {
      billingTitle.textContent = targetTitle;
    }
  }

  function relabelAddressBlocks() {
    const additionalFields = getAdditionalFields();
    const nativeMethod = getSelectedNativeDeliveryMethod();
    const method = isValidMethod(nativeMethod)
      ? nativeMethod
      : isValidMethod(additionalFields[DELIVERY_FIELD])
        ? additionalFields[DELIVERY_FIELD]
        : "home_delivery";
    // Shipping-fields block is always visible and shown first → call it "Billing address"
    const shippingTitle = document.querySelector(
      ".wc-block-checkout__shipping-fields .wc-block-components-checkout-step__title",
    );
    if (
      shippingTitle &&
      shippingTitle.textContent.trim() !== "Billing address"
    ) {
      shippingTitle.textContent = "Billing address";
    }

    // Billing-address block appears when addresses differ → call it "Shipping address"
    const billingTitle = document.querySelector(
      ".wc-block-checkout__billing-address .wc-block-components-checkout-step__title, " +
        ".wp-block-woocommerce-checkout-billing-address-block .wc-block-components-checkout-step__title",
    );
    if (billingTitle) {
      const targetTitle =
        method === "pickup" ? "Billing address" : "Shipping address";
      if (billingTitle.textContent.trim() !== targetTitle) {
        billingTitle.textContent = targetTitle;
      }
    }

    // Change checkbox label from "Use same address for billing" → "Use same address for shipping"
    document
      .querySelectorAll(
        ".wc-block-checkout__shipping-fields .wc-block-components-checkbox__label",
      )
      .forEach((label) => {
        if (/billing/i.test(label.textContent)) {
          label.textContent = label.textContent.replace(
            /billing/gi,
            "shipping",
          );
        }
      });
  }

  function enforceTermsMessage() {
    const placeOrderBtn = document.querySelector(PLACE_ORDER_SELECTOR);
    if (!placeOrderBtn) return;

    placeOrderBtn.addEventListener("click", () => {
      const termsBlock = document.querySelector(".wc-block-checkout__terms");
      if (!termsBlock) return;

      const checkbox = termsBlock.querySelector('input[type="checkbox"]');
      const existing = termsBlock.querySelector(".devhub-terms-error");

      if (checkbox && !checkbox.checked) {
        if (!existing) {
          const wrapper = document.createElement("div");
          wrapper.className = "devhub-terms-error";
          wrapper.setAttribute("role", "alert");
          const p = document.createElement("p");
          p.innerHTML =
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" aria-hidden="true" focusable="false">' +
            '<path d="M12 3.2c-4.8 0-8.8 3.9-8.8 8.8 0 4.8 3.9 8.8 8.8 8.8 4.8 0 8.8-3.9 8.8-8.8 0-4.8-4-8.8-8.8-8.8zm0 16c-4 0-7.2-3.3-7.2-7.2C4.8 8 8 4.8 12 4.8s7.2 3.3 7.2 7.2c0 4-3.2 7.2-7.2 7.2zM11 17v-6h1.5v6H11zm0-8V7.5h1.5V9H11z"/>' +
            "</svg>" +
            "Please accept the Terms and Conditions to continue.";
          wrapper.appendChild(p);
          termsBlock.appendChild(wrapper);
        }
      } else if (existing) {
        existing.remove();
      }

      if (checkbox) {
        checkbox.addEventListener(
          "change",
          () => {
            const err = termsBlock.querySelector(".devhub-terms-error");
            if (err) err.remove();
          },
          { once: true },
        );
      }
    });
  }

  function syncOrderDetailsCardHeights() {
    const layout = document.querySelector(ORDER_DETAILS_LAYOUT_SELECTOR);
    const itemsList = layout?.querySelector(ORDER_DETAILS_ITEMS_LIST_SELECTOR);

    if (!layout || !itemsList) {
      return;
    }

    const hasOverflow = itemsList.scrollHeight - itemsList.clientHeight > 1;
    layout.classList.toggle(ORDER_DETAILS_BALANCED_CLASS, hasOverflow);
  }

  function boot() {
    if (
      !document.querySelector(
        ".wc-block-checkout, .wp-block-woocommerce-checkout",
      )
    ) {
      return;
    }

    if (!window.wp?.data || !window.wc?.wcBlocksData) {
      window.setTimeout(boot, 150);
      return;
    }

    syncSidebarRelocationState();
    render();
    enhancePlaceOrderButton();
    enhanceCouponButton();
    enhanceCouponInput();
    replaceDiscountChipLabel();
    scheduleDiscountSummaryRefresh();
    enhanceContactInput();
    enhanceOrderSummaryRemoveButtons();
    expandAddressLineTwo();
    relabelAddressBlocks();
    enhanceBillingEmailField();
    moveSidebarSummaryForMobile();
    observeOrderSummary();
    observePaymentMethods();
    enhanceProductNameTooltips();
    moveOrderNoteStep();
    movePaymentStep();
    moveTermsBeforePlaceOrder();
    enforceTermsMessage();
    syncOrderDetailsCardHeights();

    if (!hasBoundViewportListener) {
      hasBoundViewportListener = true;
      document.addEventListener("click", hideProductNameTooltip);
      window.addEventListener("scroll", hideProductNameTooltip, {
        passive: true,
      });
      window.addEventListener(
        "resize",
        () => {
          syncSidebarRelocationState();
          moveSidebarSummaryForMobile();
          observeOrderSummary();
          enhanceProductNameTooltips();
          hideProductNameTooltip();
          moveOrderNoteStep();
          movePaymentStep();
          moveTermsBeforePlaceOrder();
          syncOrderDetailsCardHeights();
        },
        { passive: true },
      );
    }

    if (unsubscribe) {
      return;
    }

    unsubscribe = window.wp.data.subscribe(() => {
      render();
      enhancePlaceOrderButton();
      enhanceCouponButton();
      enhanceEmptyCheckoutButton();
      enhanceCouponInput();
      replaceDiscountChipLabel();
      scheduleDiscountSummaryRefresh();
      enhanceContactInput();
      enhanceOrderSummaryRemoveButtons();
      expandAddressLineTwo();
      relabelAddressBlocks();
      enhanceBillingEmailField();
      moveSidebarSummaryForMobile();
      observeOrderSummary();
      enhanceProductNameTooltips();
      moveOrderNoteStep();
      movePaymentStep();
      moveTermsBeforePlaceOrder();
      syncOrderDetailsCardHeights();
    });
  }

  function enhanceOrderConfirmationDate() {
    if (!window.devhubOrderTime) return true;

    const dateEl = document.querySelector(
      "li.woocommerce-order-overview__date strong",
    );
    if (!dateEl) return false;
    if (dateEl.dataset.devhubEnhanced) return true;

    dateEl.textContent = dateEl.textContent + ", " + window.devhubOrderTime;

    // Rename the label — could be a text node or a <p> child
    const dateLi = dateEl.closest("li.woocommerce-order-overview__date");
    if (dateLi) {
      // WC Blocks: label in a <p> child
      const titleP = dateLi.querySelector("p");
      if (titleP) {
        titleP.textContent = "Date & Time:";
      } else {
        // Classic template: label is a raw text node
        for (const node of dateLi.childNodes) {
          if (node.nodeType === Node.TEXT_NODE && node.textContent.trim()) {
            node.textContent = " Date & Time: ";
            break;
          }
        }
      }
    }

    dateEl.dataset.devhubEnhanced = "1";
    return true;
  }

  function initOrderConfirmationDate() {
    if (enhanceOrderConfirmationDate()) return;
    const observer = new MutationObserver(() => {
      if (enhanceOrderConfirmationDate()) {
        observer.disconnect();
      }
    });
    observer.observe(document.body, { childList: true, subtree: true });
  }

  function initOrderDetailsCardHeights() {
    syncOrderDetailsCardHeights();

    window.addEventListener(
      "resize",
      () => {
        syncOrderDetailsCardHeights();
      },
      { passive: true },
    );
  }

  syncSidebarRelocationState();

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", () => {
      boot();
      initOrderConfirmationDate();
      initOrderDetailsCardHeights();
    });
  } else {
    boot();
    initOrderConfirmationDate();
    initOrderDetailsCardHeights();
  }
})();
