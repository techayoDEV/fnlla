<?php

declare(strict_types=1);

/*
===============================================================================
FNLLA VIEW LAYOUT
File: views\layouts\app.php
Copyright (c) 2026 TechAyo LTD (techayo.co.uk). Released under the MIT License.
===============================================================================

FNLLA is produced, maintained and distributed by TechAyo LTD
(techayo.co.uk). This repository is the authoritative maintainer workspace for
the FNLLA framework released under the MIT License and its related delivery scripts, tests,
templates and release metadata.

Purpose:
- Defines the shared delivery shell for server-rendered pages built on FNLLA's integrated UI surface.
*/

$pageStatus = flash("status");
$layoutChromeMode = (string) ($layoutChromeMode ?? "default");
$isClientPreviewChrome = $layoutChromeMode === "client-preview";
$isDeveloperPanelChrome = $layoutChromeMode === "developer-panel";
$currentPath = current_path();
$hasDocumentationWorkspace = has_local_docs_workspace();
$isDocsPath = $currentPath === "/docs" || str_starts_with($currentPath, "/docs/");
$maintenanceAccess = maintenance_access();
$developerAccess = developer_access();
$isMaintenanceLocked = $maintenanceAccess->enabled() && !$maintenanceAccess->isUnlocked();
$hasDeveloperLoginRoute = app(\Fnlla\Php\Routing\Router::class)->routeByName("developer.login") !== null;
$hasDeveloperPanelRoute = app(\Fnlla\Php\Routing\Router::class)->routeByName("developer.panel") !== null;
$hasDeveloperHealthRoute = app(\Fnlla\Php\Routing\Router::class)->routeByName("health") !== null;
$hasFrameworkUpdateRoute = app(\Fnlla\Php\Routing\Router::class)->routeByName("maintenance.framework_update") !== null;
$developerSessionActive = $developerAccess->isUnlocked() && $hasDeveloperPanelRoute;
$developerEntryHref = $developerSessionActive && $hasDeveloperPanelRoute
    ? route("developer.panel")
    : ($hasDeveloperLoginRoute ? route("developer.login") : "");
$publicNavigationAvailable = !$isMaintenanceLocked || $developerSessionActive;
$showCookieConsent = !$isClientPreviewChrome && !$isDeveloperPanelChrome && !$isMaintenanceLocked;
$publicIntegrationConfig = [
    "ga4" => [
        "enabled" => (bool) config("integrations.ga4.enabled", false),
        "measurementId" => (string) config("integrations.ga4.measurement_id", ""),
    ],
    "clarity" => [
        "enabled" => (bool) config("integrations.clarity.enabled", false),
        "projectId" => (string) config("integrations.clarity.project_id", ""),
    ],
    "heatmaps" => [
        "enabled" => (bool) config("integrations.heatmaps.enabled", false),
        "provider" => (string) config("integrations.heatmaps.provider", ""),
    ],
    "apiHooks" => [
        "enabled" => (bool) config("integrations.api_hooks.enabled", false),
        "endpoint" => (string) config("integrations.api_hooks.endpoint", ""),
    ],
];
$internalHeatmapConfig = [
    "enabled" => (bool) config("observability.metrics.enabled", false)
        && (bool) config("observability.analytics.enabled", true)
        && (bool) config("observability.heatmap.enabled", true),
    "endpoint" => route("fnlla.analytics.event"),
    "sampleRate" => max(1, min(100, (int) config("observability.heatmap.sample_rate", 100))),
];
$publicIntegrationRuntimeEnabled = !$isClientPreviewChrome && !$isDeveloperPanelChrome && (
    $internalHeatmapConfig["enabled"]
    ||
    ($publicIntegrationConfig["ga4"]["enabled"] && $publicIntegrationConfig["ga4"]["measurementId"] !== "")
    || ($publicIntegrationConfig["clarity"]["enabled"] && $publicIntegrationConfig["clarity"]["projectId"] !== "")
    || ($publicIntegrationConfig["heatmaps"]["enabled"] && $publicIntegrationConfig["heatmaps"]["provider"] !== "")
    || ($publicIntegrationConfig["apiHooks"]["enabled"] && $publicIntegrationConfig["apiHooks"]["endpoint"] !== "")
);
$pageMeta = page_meta([
    "site" => (string) config("app.name"),
    "page" => (string) ($pageTitle ?? ""),
    "section" => (string) ($pageTitleSection ?? ""),
    "suffix" => (string) ($pageTitleSuffix ?? ""),
    "tagline" => (string) ($pageTitleTagline ?? config("app.tagline", "")),
    "home" => (bool) ($pageTitleHome ?? false),
]);
?>
<!DOCTYPE html>
<html
  lang="en"
  data-fnlla-title-site="<?= h($pageMeta["site"]) ?>"
  <?php if ($pageMeta["page"] !== ""): ?>data-fnlla-title-page="<?= h($pageMeta["page"]) ?>"<?php endif; ?>
  <?php if ($pageMeta["section"] !== ""): ?>data-fnlla-title-section="<?= h($pageMeta["section"]) ?>"<?php endif; ?>
  <?php if ($pageMeta["suffix"] !== ""): ?>data-fnlla-title-suffix="<?= h($pageMeta["suffix"]) ?>"<?php endif; ?>
  <?php if ($pageMeta["tagline"] !== ""): ?>data-fnlla-title-tagline="<?= h($pageMeta["tagline"]) ?>"<?php endif; ?>
  <?php if ($pageMeta["home"] === true): ?>data-fnlla-title-home="true"<?php endif; ?>
>
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="theme-color" content="#15304f">
  <title><?= h($pageMeta["title"]) ?></title>
  <link rel="stylesheet" href="<?= h(asset("vendor/fnlla-runtime/assets/css/fnlla-runtime.css")) ?>">
  <link rel="stylesheet" href="<?= h(asset("assets/app.css")) ?>">
</head>
<body data-fnlla-theme="default"<?= $isClientPreviewChrome ? ' class="client-preview-layout"' : ($isDeveloperPanelChrome ? ' class="developer-workspace-layout"' : "") ?>>
  <?php if (!$isClientPreviewChrome && !$isDeveloperPanelChrome): ?>
  <header class="project-header">
    <section class="section project-header-section">
      <div class="container">
        <nav class="navbar" aria-label="Primary navigation">
          <a class="navbar-brand project-brand" href="<?= h(route("home")) ?>">
            <span class="project-brand-mark" aria-hidden="true"><?= h(project_brand_mark()) ?></span>
            <span class="project-brand-name"><?= h((string) config("app.name")) ?></span>
          </a>
          <button class="btn btn-outline btn-sm navbar-toggle" type="button" data-fnlla-nav-toggle aria-controls="primary-navigation-panel" aria-expanded="false" aria-label="Toggle navigation menu">Menu</button>
          <div class="navbar-panel" id="primary-navigation-panel">
            <ul class="navbar-menu">
              <?php if ($publicNavigationAvailable): ?>
              <li><a class="project-nav-link" href="<?= h(route("home")) ?>" <?= $currentPath === "/" ? 'aria-current="page"' : "" ?>>Home</a></li>
              <li><a class="project-nav-link" href="<?= h(route("about")) ?>" <?= $currentPath === "/about" ? 'aria-current="page"' : "" ?>>About</a></li>
              <li><a class="project-nav-link" href="<?= h(route("services")) ?>" <?= $currentPath === "/services" ? 'aria-current="page"' : "" ?>>Services</a></li>
              <li><a class="project-nav-link" href="<?= h(route("contact")) ?>" <?= $currentPath === "/contact" ? 'aria-current="page"' : "" ?>>Contact</a></li>
              <?php else: ?>
              <li><span class="project-nav-link" aria-current="page">Maintenance access required</span></li>
              <?php endif; ?>
            </ul>
          </div>
        </nav>
      </div>
    </section>
  </header>
  <?php endif; ?>

  <?php if (!$isClientPreviewChrome && is_array($pageStatus) && isset($pageStatus["title"], $pageStatus["text"])): ?>
  <section class="section pt-1 pb-0 <?= $isDeveloperPanelChrome ? "developer-workspace-alert-section" : "" ?>" id="page-status">
    <div class="<?= $isDeveloperPanelChrome ? "developer-workspace-alert-container" : "container" ?>">
      <div class="alert alert-dismissible alert-<?= h((string) ($pageStatus["variant"] ?? "info")) ?>" role="<?= (($pageStatus["variant"] ?? "") === "danger" || ($pageStatus["variant"] ?? "") === "warning") ? "alert" : "status" ?>" data-fnlla-alert>
        <div>
          <h2 class="alert-title"><?= h((string) $pageStatus["title"]) ?></h2>
          <p class="alert-text"><?= h((string) $pageStatus["text"]) ?></p>
        </div>
        <button class="alert-close" type="button" aria-label="Close alert" data-fnlla-alert-close>&times;</button>
      </div>
    </div>
  </section>
  <?php endif; ?>

  <main>
    <?= $content ?>
  </main>

  <?php if (!$isClientPreviewChrome && !$isDeveloperPanelChrome): ?>
  <footer class="project-footer">
    <section class="section">
      <div class="container">
        <p><strong class="project-footer-brand"><?= h((string) config("app.name")) ?></strong></p>
        <p>This project base is the beginning of the real application.</p>
        <?php if ($publicNavigationAvailable): ?>
        <nav class="project-footer-nav" aria-label="Footer navigation">
          <p class="project-footer-links">
            <a href="<?= h(route("home")) ?>">Home</a>
            <a href="<?= h(route("about")) ?>">About</a>
            <a href="<?= h(route("services")) ?>">Services</a>
            <a href="<?= h(route("contact")) ?>">Contact</a>
            <a href="<?= h(route("terms")) ?>">Terms</a>
            <a href="<?= h(route("privacy")) ?>">Privacy</a>
            <button class="project-footer-cookie-link" type="button" data-fnlla-cookie-settings-open>Cookie Settings</button>
          </p>
          <?php if ($developerEntryHref !== ""): ?>
          <a class="project-footer-developer-link" href="<?= h($developerEntryHref) ?>">Developer</a>
          <?php endif; ?>
        </nav>
        <?php else: ?>
        <p><a class="project-footer-locked-link" href="<?= h(route("maintenance.home")) ?>">Return to maintenance access</a></p>
        <?php endif; ?>
      </div>
    </section>
  </footer>
  <?php endif; ?>

  <?php if ($showCookieConsent): ?>
  <section class="fnlla-cookie-banner" data-fnlla-cookie-banner aria-label="Cookie consent" hidden>
    <div class="fnlla-cookie-banner-copy">
      <p class="fnlla-cookie-kicker">Cookie consent</p>
      <h2 class="fnlla-cookie-title">Control how this site uses cookies.</h2>
      <p class="fnlla-cookie-text">Essential cookies keep sessions, security and forms working. Optional analytics and marketing tools stay off unless you allow them.</p>
    </div>
    <div class="fnlla-cookie-banner-actions">
      <button class="btn btn-outline btn-sm fnlla-cookie-button" type="button" data-fnlla-cookie-settings-open>Manage settings</button>
      <button class="btn btn-outline btn-sm fnlla-cookie-button" type="button" data-fnlla-cookie-reject>Reject optional</button>
      <button class="btn btn-primary btn-sm fnlla-cookie-button" type="button" data-fnlla-cookie-accept>Accept all</button>
    </div>
  </section>
  <div class="modal fnlla-cookie-modal" id="fnlla-cookie-settings-modal" data-fnlla-cookie-modal role="dialog" aria-modal="true" aria-labelledby="fnlla-cookie-settings-title" hidden>
    <div class="modal-content fnlla-cookie-modal-content">
      <div class="fnlla-cookie-modal-header">
        <div>
          <p class="fnlla-cookie-kicker">Privacy controls</p>
          <h2 class="fnlla-cookie-title" id="fnlla-cookie-settings-title">Cookie Settings</h2>
        </div>
        <button class="fnlla-cookie-modal-close" type="button" data-fnlla-cookie-settings-close aria-label="Close cookie settings">Close</button>
      </div>
      <div class="fnlla-cookie-options">
        <label class="fnlla-cookie-option fnlla-cookie-option-locked">
          <span>
            <strong>Essential cookies</strong>
            <small>Required for security, sessions, CSRF protection and saved privacy choices. These cannot be switched off.</small>
          </span>
          <input type="checkbox" checked disabled>
        </label>
        <label class="fnlla-cookie-option">
          <span>
            <strong>Analytics cookies</strong>
            <small>Allow consent-aware analytics or heatmap tools after the developer connects them.</small>
          </span>
          <input type="checkbox" data-fnlla-cookie-choice="analytics">
        </label>
        <label class="fnlla-cookie-option">
          <span>
            <strong>Marketing cookies</strong>
            <small>Allow future advertising, remarketing or campaign tags after the developer connects them.</small>
          </span>
          <input type="checkbox" data-fnlla-cookie-choice="marketing">
        </label>
      </div>
      <p class="fnlla-cookie-text">
        Read the <a href="<?= h(route("privacy")) ?>#cookies">Privacy Policy cookie section</a> for the starter wording this project ships with.
      </p>
      <div class="fnlla-cookie-modal-actions">
        <button class="btn btn-outline btn-sm fnlla-cookie-button" type="button" data-fnlla-cookie-reject>Reject optional</button>
        <button class="btn btn-primary btn-sm fnlla-cookie-button" type="button" data-fnlla-cookie-save>Save settings</button>
      </div>
    </div>
  </div>
  <?php endif; ?>

  <script nonce="<?= h(csp_nonce()) ?>" src="<?= h(asset("vendor/fnlla-runtime/assets/js/fnlla-runtime.js")) ?>"></script>
  <script nonce="<?= h(csp_nonce()) ?>">
    document.addEventListener("click", function (event) {
      var closeButton = event.target && event.target.closest ? event.target.closest("[data-fnlla-alert-close]") : null;

      if (!closeButton) {
        return;
      }

      var alert = closeButton.closest("[data-fnlla-alert], .alert");

      if (!alert) {
        return;
      }

      var statusContainer = alert.closest("#page-status");
      var target = statusContainer || alert;

      event.preventDefault();
      target.hidden = true;
      target.setAttribute("aria-hidden", "true");
    });
  </script>
  <?php if ($showCookieConsent): ?>
  <script nonce="<?= h(csp_nonce()) ?>">
    (function () {
      var banner = document.querySelector("[data-fnlla-cookie-banner]");
      var modal = document.querySelector("[data-fnlla-cookie-modal]");
      var settingsButtons = document.querySelectorAll("[data-fnlla-cookie-settings-open]");
      var closeButtons = document.querySelectorAll("[data-fnlla-cookie-settings-close]");
      var acceptButtons = document.querySelectorAll("[data-fnlla-cookie-accept]");
      var rejectButtons = document.querySelectorAll("[data-fnlla-cookie-reject]");
      var saveButtons = document.querySelectorAll("[data-fnlla-cookie-save]");
      var choices = document.querySelectorAll("[data-fnlla-cookie-choice]");
      var storageKey = "fnlla_cookie_consent_v1";
      var consentEndpoint = "<?= h(route("fnlla.consent")) ?>";

      function readConsent() {
        try {
          var saved = window.localStorage.getItem(storageKey);

          return saved ? JSON.parse(saved) : null;
        } catch (error) {
          return null;
        }
      }

      function dispatchConsent(preferences) {
        if (!preferences) {
          return;
        }

        window.dispatchEvent(new CustomEvent("fnlla:cookie-consent-ready", { detail: preferences }));

        if (preferences.analytics === true) {
          window.dispatchEvent(new CustomEvent("fnlla:analytics-consent-granted", { detail: preferences }));
        }

        if (preferences.marketing === true) {
          window.dispatchEvent(new CustomEvent("fnlla:marketing-consent-granted", { detail: preferences }));
        }
      }

      function syncPageStatusOffset() {
        if (!banner || banner.hidden) {
          document.documentElement.style.setProperty("--fnlla-cookie-banner-offset", "0px");
          return;
        }

        document.documentElement.style.setProperty(
          "--fnlla-cookie-banner-offset",
          String(banner.offsetHeight + 12) + "px"
        );
      }

      function syncChoices(preferences) {
        choices.forEach(function (choice) {
          choice.checked = preferences ? preferences[choice.dataset.fnllaCookieChoice] === true : false;
        });
      }

      function closeModal() {
        if (modal) {
          modal.hidden = true;
        }

        syncPageStatusOffset();
      }

      function saveConsent(preferences) {
        var saved = {
          essential: true,
          analytics: preferences.analytics === true,
          marketing: preferences.marketing === true,
          savedAt: new Date().toISOString()
        };

        try {
          window.localStorage.setItem(storageKey, JSON.stringify(saved));
        } catch (error) {
          return;
        }

        if (banner) {
          banner.hidden = true;
        }

        closeModal();
        syncPageStatusOffset();
        syncChoices(saved);
        dispatchConsent(saved);
        sendConsent(saved);
        window.dispatchEvent(new CustomEvent("fnlla:cookies-updated", { detail: saved }));
      }

      function sendConsent(preferences) {
        var payload = JSON.stringify({
          schema: "fnlla.cookie_consent_event.v1",
          source: "cookie-banner",
          preferences: {
            analytics: preferences.analytics === true,
            marketing: preferences.marketing === true
          }
        });

        if (navigator.sendBeacon) {
          navigator.sendBeacon(consentEndpoint, new Blob([payload], { type: "application/json" }));
          return;
        }

        if (window.fetch) {
          window.fetch(consentEndpoint, {
            method: "POST",
            headers: { "Content-Type": "application/json", "Accept": "application/json" },
            body: payload,
            credentials: "same-origin",
            keepalive: true
          }).catch(function () {});
        }
      }

      function openModal() {
        syncChoices(readConsent());

        if (modal) {
          modal.hidden = false;

          var firstChoice = modal.querySelector("[data-fnlla-cookie-choice]");
          if (firstChoice) {
            firstChoice.focus();
          }
        }
      }

      if (banner && readConsent() === null) {
        banner.hidden = false;
        syncPageStatusOffset();
      }

      dispatchConsent(readConsent());
      syncPageStatusOffset();
      window.addEventListener("resize", syncPageStatusOffset);

      settingsButtons.forEach(function (button) {
        button.addEventListener("click", openModal);
      });

      closeButtons.forEach(function (button) {
        button.addEventListener("click", closeModal);
      });

      acceptButtons.forEach(function (button) {
        button.addEventListener("click", function () {
          saveConsent({ analytics: true, marketing: true });
        });
      });

      rejectButtons.forEach(function (button) {
        button.addEventListener("click", function () {
          saveConsent({ analytics: false, marketing: false });
        });
      });

      saveButtons.forEach(function (button) {
        button.addEventListener("click", function () {
          var preferences = {};
          choices.forEach(function (choice) {
            preferences[choice.dataset.fnllaCookieChoice] = choice.checked;
          });
          saveConsent(preferences);
        });
      });

      if (modal) {
        modal.addEventListener("click", function (event) {
          if (event.target === modal) {
            closeModal();
          }
        });

        window.addEventListener("keydown", function (event) {
          if (event.key === "Escape" && !modal.hidden) {
            closeModal();
          }
        });
      }
    })();
  </script>
  <?php endif; ?>
  <?php if ($publicIntegrationRuntimeEnabled): ?>
  <script nonce="<?= h(csp_nonce()) ?>">
    (function () {
      var config = <?= json_encode($publicIntegrationConfig, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) ?>;
      var internalHeatmap = <?= json_encode($internalHeatmapConfig, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) ?>;
      var storageKey = "fnlla_cookie_consent_v1";
      var loaded = {};
      var lastScrollDepth = 0;

      function readConsent() {
        try {
          var saved = window.localStorage.getItem(storageKey);

          return saved ? JSON.parse(saved) : null;
        } catch (error) {
          return null;
        }
      }

      function loadScriptOnce(key, src, setup) {
        if (loaded[key]) {
          return;
        }

        loaded[key] = true;
        if (typeof setup === "function") {
          setup();
        }

        var script = document.createElement("script");
        script.async = true;
        script.src = src;
        document.head.appendChild(script);
      }

      function enableGa4() {
        var measurementId = config.ga4 && config.ga4.measurementId;

        if (!config.ga4 || config.ga4.enabled !== true || !measurementId) {
          return;
        }

        loadScriptOnce("ga4", "https://www.googletagmanager.com/gtag/js?id=" + encodeURIComponent(measurementId), function () {
          window.dataLayer = window.dataLayer || [];
          window.gtag = window.gtag || function () { window.dataLayer.push(arguments); };
          window.gtag("js", new Date());
          window.gtag("config", measurementId, { anonymize_ip: true });
        });
      }

      function enableClarity() {
        var projectId = config.clarity && config.clarity.projectId;

        if (!config.clarity || config.clarity.enabled !== true || !projectId || loaded.clarity) {
          return;
        }

        loaded.clarity = true;
        window.clarity = window.clarity || function () { (window.clarity.q = window.clarity.q || []).push(arguments); };
        loadScriptOnce("clarity-script", "https://www.clarity.ms/tag/" + encodeURIComponent(projectId));
      }

      function enableHeatmapAdapter(preferences) {
        if (!config.heatmaps || config.heatmaps.enabled !== true || !config.heatmaps.provider) {
          return;
        }

        window.dispatchEvent(new CustomEvent("fnlla:heatmap-adapter-ready", {
          detail: {
            provider: config.heatmaps.provider,
            consent: preferences,
            externalRecorder: false
          }
        }));
      }

      function shouldSample() {
        var rate = internalHeatmap && Number(internalHeatmap.sampleRate || 100);

        return rate >= 100 || Math.random() * 100 < Math.max(1, Math.min(100, rate));
      }

      function deviceBucket() {
        var width = window.innerWidth || document.documentElement.clientWidth || 0;

        if (width > 1024) {
          return "desktop";
        }

        if (width > 640) {
          return "tablet";
        }

        return "mobile";
      }

      function sendFnllaBehaviorEvent(type, extra, preferences) {
        if (!internalHeatmap || internalHeatmap.enabled !== true || !internalHeatmap.endpoint || !shouldSample()) {
          return;
        }

        var payload = Object.assign({
          schema: "fnlla.behavior_event.v1",
          type: type,
          path: window.location.pathname,
          device: deviceBucket(),
          viewport: {
            width: window.innerWidth || 0,
            height: window.innerHeight || 0
          },
          consent: {
            analytics: preferences && preferences.analytics === true,
            marketing: preferences && preferences.marketing === true
          }
        }, extra || {});
        var body = JSON.stringify(payload);

        if (navigator.sendBeacon) {
          navigator.sendBeacon(internalHeatmap.endpoint, new Blob([body], { type: "application/json" }));
          return;
        }

        if (window.fetch) {
          window.fetch(internalHeatmap.endpoint, {
            method: "POST",
            headers: { "Content-Type": "application/json", "Accept": "application/json" },
            body: body,
            credentials: "same-origin",
            keepalive: true
          }).catch(function () {});
        }
      }

      function elementBucket(target) {
        var element = target && target.closest ? target.closest("button,a,input,select,textarea,label,summary") : null;

        return element ? String(element.tagName || "element").toLowerCase() : "page";
      }

      function enableFnllaHeatmap(preferences) {
        if (!internalHeatmap || internalHeatmap.enabled !== true || loaded.fnllaHeatmap) {
          return;
        }

        loaded.fnllaHeatmap = true;
        sendFnllaBehaviorEvent("view", {}, preferences);

        document.addEventListener("click", function (event) {
          var doc = document.documentElement;
          var maxX = Math.max(1, doc.scrollWidth || window.innerWidth || 1);
          var maxY = Math.max(1, doc.scrollHeight || window.innerHeight || 1);

          sendFnllaBehaviorEvent("click", {
            element: elementBucket(event.target),
            position: {
              x_percent: Math.max(0, Math.min(100, ((event.pageX || 0) / maxX) * 100)),
              y_percent: Math.max(0, Math.min(100, ((event.pageY || 0) / maxY) * 100))
            }
          }, preferences);
        }, { passive: true });

        window.addEventListener("scroll", function () {
          var doc = document.documentElement;
          var scrollTop = window.scrollY || doc.scrollTop || 0;
          var maxScroll = Math.max(1, (doc.scrollHeight || 1) - (window.innerHeight || 1));
          var depth = Math.max(0, Math.min(100, (scrollTop / maxScroll) * 100));
          var bucket = depth >= 100 ? 100 : (depth >= 75 ? 75 : (depth >= 50 ? 50 : (depth >= 25 ? 25 : 0)));

          if (bucket > lastScrollDepth) {
            lastScrollDepth = bucket;
            sendFnllaBehaviorEvent("scroll", { depth: bucket }, preferences);
          }
        }, { passive: true });
      }

      function sendApiHook(eventName, preferences) {
        var endpoint = config.apiHooks && config.apiHooks.endpoint;

        if (!config.apiHooks || config.apiHooks.enabled !== true || !endpoint || !window.fetch) {
          return;
        }

        window.fetch(endpoint, {
          method: "POST",
          headers: { "Content-Type": "application/json", "Accept": "application/json" },
          body: JSON.stringify({
            schema: "fnlla.integration_event.v1",
            event: eventName,
            path: window.location.pathname,
            consent: {
              analytics: preferences.analytics === true,
              marketing: preferences.marketing === true
            },
            occurredAt: new Date().toISOString()
          }),
          credentials: "omit",
          keepalive: true
        }).catch(function () {});
      }

      function enableAnalyticsAdapters(preferences) {
        if (!preferences || preferences.analytics !== true) {
          return;
        }

        enableGa4();
        enableClarity();
        enableHeatmapAdapter(preferences);
        enableFnllaHeatmap(preferences);
        sendApiHook("analytics_consent_granted", preferences);
      }

      window.addEventListener("fnlla:analytics-consent-granted", function (event) {
        enableAnalyticsAdapters(event.detail || {});
      });
      window.addEventListener("fnlla:cookies-updated", function (event) {
        sendApiHook("cookie_preferences_updated", event.detail || {});
      });

      enableAnalyticsAdapters(readConsent());
    })();
  </script>
  <?php endif; ?>
</body>
</html>
