<?php

declare(strict_types=1);

// Framework-owned shell: never load public application CSS, navigation or analytics here.
$pageStatus = flash("status");
$pageStatusAutohide = is_array($pageStatus) && (bool) ($pageStatus["toast"] ?? false);
$pageMeta = page_meta([
    "page" => (string) ($pageTitle ?? ""),
    "section" => (string) ($pageTitleSection ?? "Developer Panel"),
    "suffix" => (string) ($pageTitleSuffix ?? ""),
    "tagline" => (string) ($pageTitleTagline ?? config("app.tagline", "")),
]);
$favicon = framework_brand_asset("favicon");
$touchIcon = framework_brand_asset("apple_touch_icon");
$manifest = framework_brand_asset("webmanifest");
$openGraphImage = framework_brand_asset("open_graph");
$faviconType = str_ends_with(strtolower((string) parse_url((string) $favicon, PHP_URL_PATH)), ".svg") ? "image/svg+xml" : "image/png";
?>
<!DOCTYPE html>
<html lang="en" data-fnlla-title-site="<?= h($pageMeta["site"]) ?>"
  data-fnlla-title-page="<?= h($pageMeta["page"]) ?>"
  data-fnlla-title-section="<?= h($pageMeta["section"]) ?>"
  data-fnlla-title-tagline="<?= h($pageMeta["tagline"]) ?>">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="theme-color" content="<?= h(framework_brand_color("blue", "#2563EB")) ?>">
  <meta name="robots" content="noindex, nofollow">
  <meta name="referrer" content="no-referrer">
  <title><?= h($pageMeta["title"]) ?></title>
  <?php if ($favicon !== null): ?><link rel="icon" type="<?= h($faviconType) ?>" href="<?= h($favicon) ?>"><?php endif; ?>
  <?php if ($touchIcon !== null): ?><link rel="apple-touch-icon" href="<?= h($touchIcon) ?>"><?php endif; ?>
  <?php if ($manifest !== null): ?><link rel="manifest" href="<?= h($manifest) ?>"><?php endif; ?>
  <?php if ($openGraphImage !== null): ?><meta property="og:image" content="<?= h($openGraphImage) ?>"><?php endif; ?>
  <link rel="stylesheet" href="<?= h(asset("vendor/fnlla-runtime/assets/css/fnlla-runtime.css")) ?>">
  <link rel="stylesheet" href="<?= h(asset("assets/app-base.css")) ?>">
  <link rel="stylesheet" href="<?= h(asset("assets/developer-panel.css")) ?>">
</head>
<body data-fnlla-theme="default" class="developer-workspace-layout">
  <?php if (is_array($pageStatus) && isset($pageStatus["title"], $pageStatus["text"])): ?>
  <section class="section pt-1 pb-0 developer-workspace-alert-section" id="page-status"<?= $pageStatusAutohide ? ' data-fnlla-alert-autohide="true"' : "" ?>>
    <div class="developer-workspace-alert-container">
      <div class="alert alert-dismissible alert-<?= h((string) ($pageStatus["variant"] ?? "info")) ?>" role="<?= in_array($pageStatus["variant"] ?? "", ["danger", "warning"], true) ? "alert" : "status" ?>" data-fnlla-alert>
        <div><h2 class="alert-title"><?= h((string) $pageStatus["title"]) ?></h2><p class="alert-text"><?= h((string) $pageStatus["text"]) ?></p></div>
        <button class="alert-close" type="button" aria-label="Close alert" data-fnlla-alert-close>&times;</button>
      </div>
    </div>
  </section>
  <?php endif; ?>
  <main><?= $content ?></main>
  <script nonce="<?= h(csp_nonce()) ?>" src="<?= h(asset("vendor/fnlla-runtime/assets/js/fnlla-runtime.js")) ?>"></script>
  <script nonce="<?= h(csp_nonce()) ?>">
    (function () {
      var pageStatus = document.getElementById("page-status");

      function dismissAlert(alert) {
        if (!alert) {
          return;
        }

        var target = alert.closest("#page-status") || alert;
        target.hidden = true;
        target.setAttribute("aria-hidden", "true");
      }

      document.addEventListener("click", function (event) {
      var toggle = event.target && event.target.closest ? event.target.closest("[data-developer-password-toggle]") : null;
      if (toggle) {
        var input = document.getElementById(toggle.getAttribute("data-developer-password-toggle"));
        if (input) {
          var showing = input.type === "password";
          input.type = showing ? "text" : "password";
          toggle.setAttribute("aria-pressed", String(showing));
          toggle.setAttribute("aria-label", showing ? "Hide password" : "Show password");
          toggle.title = showing ? "Hide password" : "Show password";
        }
      }
      var button = event.target && event.target.closest ? event.target.closest("[data-fnlla-alert-close]") : null;
      var alert = button ? button.closest("[data-fnlla-alert]") : null;
      if (alert) {
        event.preventDefault();
        dismissAlert(alert);
      }
      });

      if (pageStatus && pageStatus.getAttribute("data-fnlla-alert-autohide") === "true") {
        var pageAlert = pageStatus.querySelector("[data-fnlla-alert], .alert");
        var delay = pageAlert && (pageAlert.classList.contains("alert-warning") || pageAlert.classList.contains("alert-danger")) ? 10000 : 7000;
        window.setTimeout(function () {
          dismissAlert(pageAlert);
        }, delay);
      }
    })();
  </script>
</body>
</html>
