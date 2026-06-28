/*
  ============================================================================
  Documentation-only behavior for FNLLA PHP docs.
  Copyright (c) 2026 TechAyo LTD (techayo.co.uk). Released under the MIT License.
  ============================================================================
*/

(function () {
  var storageKey = "fnlla-php-docs-theme";
  var themeColors = {
    default: "#18352f",
    dark: "#0d1723"
  };

  function normalizeTheme(theme) {
    return theme === "dark" ? "dark" : "default";
  }

  function readStoredTheme() {
    try {
      return normalizeTheme(window.localStorage.getItem(storageKey));
    } catch (error) {
      return "default";
    }
  }

  function storeTheme(theme) {
    try {
      window.localStorage.setItem(storageKey, normalizeTheme(theme));
    } catch (error) {
      return;
    }
  }

  function applyTheme(theme) {
    var normalizedTheme = normalizeTheme(theme);
    var themeToggle = document.querySelector("[data-doc-theme-toggle]");
    var themeMeta = document.querySelector('meta[name="theme-color"]');

    if (window.FNLLAUI && typeof window.FNLLAUI.setTheme === "function") {
      window.FNLLAUI.setTheme(normalizedTheme);
    } else if (document.body) {
      document.body.setAttribute("data-fnlla-theme", normalizedTheme);
    }

    if (themeToggle) {
      themeToggle.checked = normalizedTheme === "dark";
    }

    if (themeMeta) {
      themeMeta.setAttribute("content", themeColors[normalizedTheme]);
    }
  }

  function initThemeToggle() {
    var themeToggle = document.querySelector("[data-doc-theme-toggle]");
    applyTheme(readStoredTheme());

    if (!themeToggle) {
      return;
    }

    themeToggle.addEventListener("change", function () {
      var nextTheme = themeToggle.checked ? "dark" : "default";
      applyTheme(nextTheme);
      storeTheme(nextTheme);
    });
  }

  function initNav() {
    var nav = document.querySelector(".doc-nav");

    if (!nav) {
      return;
    }

    var toggle = nav.querySelector("[data-doc-nav-toggle]");
    var panel = nav.querySelector("[data-doc-nav-panel]");
    var mobileQuery = window.matchMedia ? window.matchMedia("(max-width: 47.9375rem)") : null;

    if (!toggle || !panel || !mobileQuery) {
      return;
    }

    function syncNavState(isOpen) {
      nav.classList.toggle("is-open", isOpen);
      toggle.setAttribute("aria-expanded", isOpen ? "true" : "false");
      panel.hidden = !isOpen;
    }

    function syncMode() {
      if (mobileQuery.matches) {
        syncNavState(false);
        return;
      }

      nav.classList.remove("is-open");
      toggle.setAttribute("aria-expanded", "false");
      panel.hidden = false;
    }

    toggle.addEventListener("click", function () {
      if (!mobileQuery.matches) {
        return;
      }

      syncNavState(panel.hidden);
    });

    if (typeof mobileQuery.addEventListener === "function") {
      mobileQuery.addEventListener("change", syncMode);
    } else if (typeof mobileQuery.addListener === "function") {
      mobileQuery.addListener(syncMode);
    }

    syncMode();
  }

  function initTocLinks() {
    var links = document.querySelectorAll(".doc-toc-list a[href^='#']");

    if (!links.length) {
      return;
    }

    Array.prototype.forEach.call(links, function (link) {
      link.addEventListener("click", function (event) {
        var targetId = link.getAttribute("href").slice(1);
        var target = document.getElementById(targetId);

        if (!target) {
          return;
        }

        event.preventDefault();
        target.scrollIntoView({ behavior: "smooth", block: "start" });
        window.history.replaceState(null, "", "#" + targetId);
      });
    });
  }

  initThemeToggle();
  initNav();
  initTocLinks();
})();
