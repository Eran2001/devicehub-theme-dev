(function () {
  "use strict";

  const config = window.devhubConfig || {};
  const ajaxUrl = config.ajaxUrl || "";
  const nonce = config.searchNonce || "";
  const forms = document.querySelectorAll("#wf_header .header-search-form.product-search");

  if (!ajaxUrl || !nonce || !forms.length) {
    return;
  }

  const debounce = (callback, delay) => {
    let timer = 0;

    return function (...args) {
      window.clearTimeout(timer);
      timer = window.setTimeout(() => callback.apply(this, args), delay);
    };
  };

  const createNode = (tag, className, text) => {
    const node = document.createElement(tag);
    if (className) {
      node.className = className;
    }
    if (typeof text === "string") {
      node.textContent = text;
    }
    return node;
  };

  const setPanelState = (panel, isActive) => {
    panel.classList.toggle("is-active", isActive);
    panel.setAttribute("aria-hidden", isActive ? "false" : "true");
  };

  const renderLoading = (panel) => {
    panel.replaceChildren();
    const loading = createNode("div", "devhub-header-search-panel__status");
    loading.appendChild(createNode("span", "devhub-header-search-panel__spinner"));
    loading.appendChild(createNode("span", "", "Searching..."));
    panel.appendChild(loading);
    setPanelState(panel, true);
  };

  const renderEmpty = (panel) => {
    panel.replaceChildren(createNode("div", "devhub-header-search-panel__empty", "No results found."));
    setPanelState(panel, true);
  };

  const renderProduct = (product) => {
    const item = createNode("a", "devhub-header-search-panel__result");
    item.href = product.url;

    const thumb = createNode("span", "devhub-header-search-panel__thumb");
    if (product.image) {
      const img = document.createElement("img");
      img.src = product.image;
      img.alt = product.name || "";
      img.loading = "lazy";
      thumb.appendChild(img);
    }

    item.appendChild(thumb);
    item.appendChild(createNode("span", "devhub-header-search-panel__name", product.name || ""));

    return item;
  };

  const renderCategory = (category) => {
    const link = createNode("a", "devhub-header-search-panel__category", category.name || "");
    link.href = category.url;
    return link;
  };

  const renderResults = (panel, data) => {
    const products = Array.isArray(data.products) ? data.products : [];
    const categories = Array.isArray(data.categories) ? data.categories : [];

    if (!products.length && !categories.length) {
      renderEmpty(panel);
      return;
    }

    panel.replaceChildren();

    const content = createNode("div", "devhub-header-search-panel__results");
    const productList = createNode("div", "devhub-header-search-panel__products");
    products.forEach((product) => productList.appendChild(renderProduct(product)));
    content.appendChild(productList);

    if (categories.length) {
      const categoryBlock = createNode("div", "devhub-header-search-panel__categories");
      categoryBlock.appendChild(createNode("p", "devhub-header-search-panel__label", "Categories"));
      categories.forEach((category) => categoryBlock.appendChild(renderCategory(category)));
      content.appendChild(categoryBlock);
    }

    panel.appendChild(content);
    setPanelState(panel, true);
  };

  forms.forEach((formWrap) => {
    const form = formWrap.querySelector("form");
    const input = formWrap.querySelector('input[type="search"]');
    const panel = formWrap.querySelector("[data-devhub-search-panel]");
    let controller = null;

    if (!form || !input || !panel) {
      return;
    }

    const runSearch = debounce(() => {
      const term = input.value.trim();

      if (term.length < 2) {
        if (controller) {
          controller.abort();
          controller = null;
        }
        panel.replaceChildren();
        setPanelState(panel, false);
        return;
      }

      if (controller) {
        controller.abort();
      }

      controller = new AbortController();
      renderLoading(panel);

      const params = new URLSearchParams({
        action: "devhub_header_search",
        nonce,
        term,
      });

      fetch(`${ajaxUrl}?${params.toString()}`, {
        credentials: "same-origin",
        signal: controller.signal,
      })
        .then((response) => response.json())
        .then((response) => {
          if (!response || !response.success) {
            renderEmpty(panel);
            return;
          }

          renderResults(panel, response.data || {});
        })
        .catch((error) => {
          if (error.name !== "AbortError") {
            renderEmpty(panel);
          }
        });
    }, 220);

    input.addEventListener("input", runSearch);

    input.addEventListener("focus", () => {
      if (input.value.trim().length >= 2 && panel.childElementCount) {
        setPanelState(panel, true);
      }
    });

    form.addEventListener("submit", () => {
      setPanelState(panel, false);
    });

    document.addEventListener("click", (event) => {
      if (!formWrap.contains(event.target)) {
        setPanelState(panel, false);
      }
    });
  });
})();
