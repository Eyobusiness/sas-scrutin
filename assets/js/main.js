(function () {
  "use strict";

  function onReady(callback) {
    if (document.readyState === "loading") {
      document.addEventListener("DOMContentLoaded", callback);
    } else {
      callback();
    }
  }

  function initValidation() {
    var forms = document.querySelectorAll(".needs-validation");
    Array.prototype.slice.call(forms).forEach(function (form) {
      form.addEventListener("submit", function (event) {
        if (!form.checkValidity()) {
          event.preventDefault();
          event.stopPropagation();
        }
        form.classList.add("was-validated");
      });
    });
  }

  function initTableSearch() {
    var inputs = document.querySelectorAll("[data-table-search]");
    inputs.forEach(function (input) {
      var selector = input.getAttribute("data-table-search");
      var table = document.querySelector(selector);
      if (!table) {
        return;
      }

      var rows = Array.prototype.slice.call(table.querySelectorAll("tbody tr"));
      input.addEventListener("input", function () {
        var query = input.value.trim().toLowerCase();
        rows.forEach(function (row) {
          var match = row.textContent.toLowerCase().indexOf(query) !== -1;
          var wasHidden = row.style.display === "none";
          row.style.display = match ? "" : "none";
          if (match && (wasHidden || query.length > 0)) {
            row.classList.remove("sas-row-filtered-in");
            void row.offsetWidth;
            row.classList.add("sas-row-filtered-in");
          }
        });
      });
    });
  }

  function initThemeToggle() {
    var root = document.documentElement;
    var resultsToggleBtn = document.querySelector("[data-results-theme-toggle]");

    // Sur la page publique des résultats, respecter le thème choisi (dark par défaut ou light)
    if (document.body && document.body.classList.contains("results-page")) {
      try {
        var savedResTheme = localStorage.getItem("sas_results_theme");
        if (savedResTheme === "light" || savedResTheme === "dark") {
          root.setAttribute("data-theme", savedResTheme);
        }
      } catch (e) {
        // Ignorer si stockage indisponible
      }

      if (resultsToggleBtn) {
        var updateToggleUi = function () {
          var current = root.getAttribute("data-theme") || "dark";
          var icon = resultsToggleBtn.querySelector("i.bi");
          var label = resultsToggleBtn.querySelector("[data-theme-label]");
          if (icon) {
            icon.className = current === "dark" ? "bi bi-sun-fill" : "bi bi-moon-stars-fill";
          }
          if (label) {
            label.textContent = current === "dark" ? "Mode Jour" : "Mode Nuit";
          }
        };
        updateToggleUi();

        resultsToggleBtn.addEventListener("click", function () {
          var next = (root.getAttribute("data-theme") || "dark") === "dark" ? "light" : "dark";
          root.setAttribute("data-theme", next);
          try {
            localStorage.setItem("sas_results_theme", next);
          } catch (e) {
            // Ignorer
          }
          updateToggleUi();
        });
      }
      return;
    }

    // Forcer le mode clair (Jour) sur l'administration et les autres pages
    root.setAttribute("data-theme", "light");
    root.setAttribute("data-bs-theme", "light");
    try {
      localStorage.removeItem("admin_theme");
    } catch (e) {
      // Ignorer si le stockage local est désactivé
    }
  }

  function initTableDropdowns() {
    if (typeof bootstrap === "undefined" || !bootstrap.Dropdown) {
      return;
    }

    var toggles = document.querySelectorAll(".table-responsive [data-bs-toggle='dropdown'], .table [data-bs-toggle='dropdown']");
    toggles.forEach(function (toggleEl) {
      bootstrap.Dropdown.getOrCreateInstance(toggleEl, {
        popperConfig: function (defaultBsPopperConfig) {
          return Object.assign({}, defaultBsPopperConfig, {
            strategy: "fixed"
          });
        }
      });

      var parentRow = toggleEl.closest("tr");
      if (parentRow) {
        toggleEl.addEventListener("show.bs.dropdown", function () {
          parentRow.classList.add("sas-dropdown-open");
        });
        toggleEl.addEventListener("hide.bs.dropdown", function () {
          parentRow.classList.remove("sas-dropdown-open");
        });
      }
    });
  }

  function initFormLoadingIndicator() {
    var forms = document.querySelectorAll("form");
    Array.prototype.slice.call(forms).forEach(function (form) {
      form.addEventListener("submit", function (event) {
        var submitBtn = event.submitter || form.querySelector("button[type='submit'], input[type='submit']");
        window.setTimeout(function () {
          if (event.defaultPrevented) {
            return;
          }
          if (typeof form.checkValidity === "function" && !form.checkValidity()) {
            return;
          }
          if (!submitBtn || submitBtn.tagName !== "BUTTON" || submitBtn.classList.contains("is-loading")) {
            return;
          }

          submitBtn.classList.add("is-loading");
          if (!submitBtn.querySelector(".sas-btn-spinner")) {
            var spinner = document.createElement("span");
            spinner.className = "sas-btn-spinner";
            spinner.setAttribute("aria-hidden", "true");
            submitBtn.insertBefore(spinner, submitBtn.firstChild);
          }
        }, 0);
      });
    });
  }

  function animateCounterElement(el) {
    if (!el || el.getAttribute("data-counter-done") === "1") {
      return;
    }
    el.setAttribute("data-counter-done", "1");

    var finalText = el.textContent.trim();
    // Ignorer si la chaîne contient plusieurs nombres distincts (ex: "12 / 15")
    var matches = finalText.match(/\d+(?:[.,]\d+)?/g);
    if (!matches || matches.length !== 1) {
      return;
    }

    var numStr = matches[0];
    var hasComma = numStr.indexOf(",") !== -1;
    var decimals = 0;
    if (hasComma) {
      decimals = numStr.split(",")[1].length;
    } else if (numStr.indexOf(".") !== -1) {
      decimals = numStr.split(".")[1].length;
    }

    var targetVal = parseFloat(numStr.replace(",", "."));
    if (isNaN(targetVal) || targetVal <= 0) {
      return;
    }

    var prefix = finalText.slice(0, finalText.indexOf(numStr));
    var suffix = finalText.slice(finalText.indexOf(numStr) + numStr.length);
    var duration = 620;
    var startTime = null;

    function step(timestamp) {
      if (!startTime) {
        startTime = timestamp;
      }
      var progress = Math.min((timestamp - startTime) / duration, 1);
      var eased = 1 - Math.pow(1 - progress, 3);
      var currentVal = targetVal * eased;

      if (progress < 1) {
        var formatted = decimals > 0
          ? currentVal.toFixed(decimals)
          : Math.round(currentVal).toString();
        if (hasComma) {
          formatted = formatted.replace(".", ",");
        }
        el.textContent = prefix + formatted + suffix;
        window.requestAnimationFrame(step);
      } else {
        // Toujours restaurer exactement le texte serveur d'origine
        el.textContent = finalText;
      }
    }

    window.requestAnimationFrame(step);
  }

  function initProgressiveAnimations() {
    var prefersReduced = window.matchMedia && window.matchMedia("(prefers-reduced-motion: reduce)").matches;
    if (prefersReduced) {
      return;
    }

    document.documentElement.classList.add("js-anim-ready");

    // Animation douce des compteurs statistiques (KPIs & scores)
    var counters = Array.prototype.slice.call(
      document.querySelectorAll(".kpi-val, .metric-value, .podium-votes-count")
    );
    if ("IntersectionObserver" in window && typeof window.requestAnimationFrame === "function") {
      var counterObserver = new IntersectionObserver(function (entries, obs) {
        entries.forEach(function (entry) {
          if (entry.isIntersecting) {
            animateCounterElement(entry.target);
            obs.unobserve(entry.target);
          }
        });
      }, { threshold: 0.25 });

      counters.forEach(function (counter) {
        counterObserver.observe(counter);
      });
    }
  }

  onReady(function () {
    var body = document.body;
    var sidebar = document.querySelector(".admin-sidebar");
    var backdrop = document.querySelector("[data-sidebar-backdrop]");
    var mobileToggles = document.querySelectorAll("[data-sidebar-toggle]");
    var miniToggles = document.querySelectorAll("[data-sidebar-mini-toggle]");
    var desktopMedia = window.matchMedia("(min-width: 992px)");
    var storageKey = "admin_sidebar_mini";

    function syncMiniButtonState() {
      var isMini = body.classList.contains("sidebar-mini");
      miniToggles.forEach(function (btn) {
        btn.setAttribute("aria-pressed", isMini ? "true" : "false");
        btn.setAttribute("title", isMini ? "Étendre la barre latérale" : "Réduire la barre latérale");
      });
    }

    function applySavedMiniState() {
      if (!sidebar) {
        return;
      }

      if (!desktopMedia.matches) {
        body.classList.remove("sidebar-mini");
        syncMiniButtonState();
        return;
      }

      try {
        var saved = localStorage.getItem(storageKey);
        if (saved === "1") {
          body.classList.add("sidebar-mini");
        } else {
          body.classList.remove("sidebar-mini");
        }
      } catch (e) {
        body.classList.remove("sidebar-mini");
      }

      syncMiniButtonState();
    }

    function setMiniSidebar(nextState) {
      if (!desktopMedia.matches) {
        return;
      }

      body.classList.toggle("sidebar-mini", nextState);
      try {
        localStorage.setItem(storageKey, nextState ? "1" : "0");
      } catch (e) {
        // Ignore storage errors
      }
      syncMiniButtonState();
    }

    function toggleMiniSidebar() {
      if (!desktopMedia.matches) {
        return;
      }

      var next = !body.classList.contains("sidebar-mini");
      setMiniSidebar(next);
    }

    function openMobileSidebar() {
      if (!sidebar || desktopMedia.matches) {
        return;
      }

      body.classList.add("sidebar-open");
      if (backdrop) {
        backdrop.classList.add("show");
      }
    }

    function closeMobileSidebar() {
      body.classList.remove("sidebar-open");
      if (backdrop) {
        backdrop.classList.remove("show");
      }
    }

    function toggleMobileSidebar() {
      if (desktopMedia.matches) {
        toggleMiniSidebar();
        return;
      }

      if (body.classList.contains("sidebar-open")) {
        closeMobileSidebar();
      } else {
        openMobileSidebar();
      }
    }

    mobileToggles.forEach(function (btn) {
      btn.addEventListener("click", toggleMobileSidebar);
    });

    miniToggles.forEach(function (btn) {
      btn.addEventListener("click", toggleMiniSidebar);
    });

    if (backdrop) {
      backdrop.addEventListener("click", closeMobileSidebar);
    }

    document.addEventListener("keydown", function (event) {
      if (event.key === "Escape" && body.classList.contains("sidebar-open")) {
        closeMobileSidebar();
      }
    });

    if (sidebar) {
      var navLinks = sidebar.querySelectorAll(".nav-link");
      navLinks.forEach(function (link) {
        var label = link.querySelector(".nav-label");
        if (label && !link.getAttribute("title")) {
          link.setAttribute("title", label.textContent.trim());
        }

        link.addEventListener("click", function () {
          if ( link.getAttribute("data-bs-toggle") === "collapse" ) {
            return;
          }
          if (!desktopMedia.matches) {
            closeMobileSidebar();
          }
        });
      });
    }

    var handleViewportChange = function () {
      if (desktopMedia.matches) {
        closeMobileSidebar();
      }
      applySavedMiniState();
    };

    if (typeof desktopMedia.addEventListener === "function") {
      desktopMedia.addEventListener("change", handleViewportChange);
    } else if (typeof desktopMedia.addListener === "function") {
      desktopMedia.addListener(handleViewportChange);
    }

    applySavedMiniState();
    initThemeToggle();
    initValidation();
    initTableSearch();
    initTableDropdowns();
    initFormLoadingIndicator();
    initProgressiveAnimations();
  });
})();
