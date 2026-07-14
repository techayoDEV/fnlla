<?php

declare(strict_types=1);

$clientPreview ??= [];
$maintenanceAccess ??= [];
$maintenanceRedirectTarget ??= "";
$status = flash("status");
$oldUsername = (string) old("maintenance_username");
$countdown = $clientPreview["countdown"] ?? ["hours" => "00", "minutes" => "00", "seconds" => "00"];
?>
<section class="section client-preview-stage">
  <div class="container client-preview-shell">
    <section class="card client-preview-card" id="client-preview-access" aria-label="<?= h((string) ($clientPreview["kicker"] ?? "Private Client Preview")) ?>">
      <div class="card-body client-preview-card-body">
        <p class="feature-kicker client-preview-kicker"><?= h((string) ($clientPreview["kicker"] ?? "Private Client Preview")) ?></p>

        <header class="client-preview-header">
          <div class="client-preview-header-copy">
            <h1 class="content-title client-preview-title"><?= h((string) ($clientPreview["title"] ?? "Your project is being restored")) ?></h1>
            <?php if (($clientPreview["show_last_updated"] ?? false) === true && trim((string) ($clientPreview["last_updated_value"] ?? "")) !== ""): ?>
            <p class="client-preview-meta">
              <span class="client-preview-meta-label"><?= h((string) ($clientPreview["last_updated_label"] ?? "Last updated")) ?>:</span>
              <span class="client-preview-meta-value"><?= h((string) $clientPreview["last_updated_value"]) ?></span>
            </p>
            <?php endif; ?>
          </div>
        </header>

        <?php if (is_array($status) && isset($status["title"], $status["text"])): ?>
        <div class="alert alert-<?= h((string) ($status["variant"] ?? "info")) ?> client-preview-alert" role="<?= (($status["variant"] ?? "") === "danger" || ($status["variant"] ?? "") === "warning") ? "alert" : "status" ?>">
          <h2 class="alert-title"><?= h((string) $status["title"]) ?></h2>
          <p class="alert-text"><?= h((string) $status["text"]) ?></p>
        </div>
        <?php endif; ?>

        <div class="client-preview-status-list" aria-label="Project restoration status">
          <div class="client-preview-status-item">
            <span class="client-preview-status-dot" aria-hidden="true"></span>
            <div class="client-preview-status-copy">
              <strong><?= h((string) ($clientPreview["status_title"] ?? "")) ?></strong>
              <span><?= h((string) ($clientPreview["status_body"] ?? "")) ?></span>
            </div>
          </div>
        </div>

        <?php if (($clientPreview["countdown_enabled"] ?? false) === true): ?>
        <div class="client-preview-countdown-card" aria-live="polite">
          <div class="client-preview-countdown-heading">
            <span class="client-preview-countdown-label"><?= h((string) ($clientPreview["countdown_label"] ?? "Full Access Restoration in")) ?>:</span>
          </div>
          <div class="client-preview-countdown-grid">
            <div class="client-preview-countdown-part">
              <strong class="client-preview-countdown-value" id="client-preview-countdown-hours"><?= h((string) ($countdown["hours"] ?? "00")) ?></strong>
              <span class="client-preview-countdown-unit">Hours</span>
            </div>
            <div class="client-preview-countdown-separator" aria-hidden="true">:</div>
            <div class="client-preview-countdown-part">
              <strong class="client-preview-countdown-value" id="client-preview-countdown-minutes"><?= h((string) ($countdown["minutes"] ?? "00")) ?></strong>
              <span class="client-preview-countdown-unit">Minutes</span>
            </div>
            <div class="client-preview-countdown-separator" aria-hidden="true">:</div>
            <div class="client-preview-countdown-part">
              <strong class="client-preview-countdown-value" id="client-preview-countdown-seconds"><?= h((string) ($countdown["seconds"] ?? "00")) ?></strong>
              <span class="client-preview-countdown-unit">Seconds</span>
            </div>
          </div>

          <?php if (($clientPreview["progress_enabled"] ?? false) === true): ?>
          <div class="client-preview-progress">
            <div class="client-preview-progress-heading">
              <span class="client-preview-progress-label"><?= h((string) ($clientPreview["progress_label"] ?? "Restoration progress")) ?></span>
              <strong class="client-preview-progress-value" id="client-preview-progress-value"><?= h((string) ($clientPreview["progress_percent"] ?? 0)) ?>%</strong>
            </div>
            <div class="client-preview-progress-bar" id="client-preview-progress-bar" role="progressbar" aria-label="<?= h((string) ($clientPreview["progress_label"] ?? "Restoration progress")) ?>" aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?= h((string) ($clientPreview["progress_percent"] ?? 0)) ?>">
              <span class="client-preview-progress-fill" id="client-preview-progress-fill" style="width: <?= h((string) ($clientPreview["progress_percent"] ?? 0)) ?>%;"></span>
            </div>
          </div>
          <?php endif; ?>

          <strong
            id="client-preview-countdown-source"
            hidden
            data-target-epoch="<?= h((string) ($clientPreview["restore_at_timestamp"] ?? 0)) ?>"
            data-start-epoch="<?= h((string) ($clientPreview["started_at_timestamp"] ?? 0)) ?>"
            data-progress-enabled="<?= (($clientPreview["progress_enabled"] ?? false) === true) ? "true" : "false" ?>"
          ></strong>
        </div>
        <?php endif; ?>

        <?php if (trim((string) ($clientPreview["message"] ?? "")) !== ""): ?>
        <div class="client-preview-note-row">
          <span class="client-preview-note-icon" aria-hidden="true">
            <svg class="client-preview-icon-svg" viewBox="0 0 24 24" focusable="false" aria-hidden="true">
              <path d="M12 20.2 4.9 13.5a4.7 4.7 0 0 1 6.7-6.6L12 7.3l.4-.4a4.7 4.7 0 0 1 6.7 6.6Z"></path>
            </svg>
          </span>
          <p class="client-preview-note-text"><?= h((string) $clientPreview["message"]) ?></p>
        </div>
        <?php endif; ?>

        <?php if (trim((string) ($clientPreview["support_email"] ?? "")) !== ""): ?>
        <div class="client-preview-support-row">
          <span class="client-preview-support-icon" aria-hidden="true">
            <svg class="client-preview-icon-svg" viewBox="0 0 24 24" focusable="false" aria-hidden="true">
              <path d="M5.25 8.1c0-.97.78-1.75 1.75-1.75h10c.97 0 1.75.78 1.75 1.75v7.8c0 .97-.78 1.75-1.75 1.75H7c-.97 0-1.75-.78-1.75-1.75Z"></path>
              <path d="m6.35 8.35 4.87 3.86a1.25 1.25 0 0 0 1.56 0l4.87-3.86"></path>
            </svg>
          </span>
          <div class="client-preview-support-copy">
            <strong><?= h((string) ($clientPreview["support_heading"] ?? "Need assistance?")) ?></strong>
            <p class="client-preview-note-text">Contact us at <a class="client-preview-support-link" href="mailto:<?= h((string) $clientPreview["support_email"]) ?>"><?= h((string) strtoupper((string) $clientPreview["support_email"])) ?></a></p>
          </div>
        </div>
        <?php endif; ?>

        <?php if (($clientPreview["login_disabled"] ?? false) === true): ?>
        <div class="client-preview-lockout">
          <p class="client-preview-lockout-text"><?= h((string) ($clientPreview["locked_notice"] ?? "")) ?></p>
        </div>
        <?php else: ?>
        <div class="client-preview-actions" data-client-preview-modal-launch>
          <button class="btn btn-primary" type="button" data-fnlla-modal-open="#client-preview-unlock-modal"><?= h((string) ($clientPreview["unlock_button_label"] ?? "Unlock preview")) ?></button>
        </div>

        <article class="card card-soft site-card-muted client-preview-fallback-card" data-client-preview-fallback>
          <div class="card-body">
            <p class="feature-kicker">Fallback access</p>
            <p class="content-text">The browser could not open the protected access modal, so the inline unlock form is active instead.</p>
            <form class="form stack gap-md" action="<?= h(route("maintenance.unlock")) ?>" method="post" novalidate>
              <?= csrf_field() ?>
              <input type="hidden" name="maintenance_redirect" value="<?= h((string) $maintenanceRedirectTarget) ?>">
              <?php if (($maintenanceAccess["username_required"] ?? false) === true): ?>
              <div class="form-group">
                <label class="label" for="client-preview-username-fallback">Username</label>
                <input class="input" id="client-preview-username-fallback" name="maintenance_username" type="text" autocomplete="username" value="<?= h($oldUsername) ?>" required>
              </div>
              <?php endif; ?>
              <div class="form-group">
                <label class="label" for="client-preview-password-fallback">Password</label>
                <div class="password-field">
                  <input class="input" id="client-preview-password-fallback" name="maintenance_password" type="password" autocomplete="current-password" required>
                  <button class="password-toggle" type="button" data-fnlla-password-toggle data-fnlla-password-target="#client-preview-password-fallback" aria-label="Toggle password visibility">Show</button>
                </div>
              </div>
              <div class="d-flex flex-wrap gap-md">
                <button class="btn btn-outline" type="submit"><?= h((string) ($clientPreview["unlock_button_label"] ?? "Unlock preview")) ?></button>
              </div>
            </form>
          </div>
        </article>

        <div
          class="modal"
          id="client-preview-unlock-modal"
          data-fnlla-modal
          data-fnlla-modal-locked
          role="dialog"
          aria-modal="true"
          aria-labelledby="client-preview-unlock-modal-title"
          hidden
        >
          <div class="modal-content client-preview-unlock-modal-content">
            <div class="mb-3">
              <p class="feature-kicker mb-2"><?= h((string) ($clientPreview["kicker"] ?? "Private Client Preview")) ?></p>
              <h2 class="content-title mb-0" id="client-preview-unlock-modal-title">Unlock the protected preview</h2>
            </div>
            <p class="content-text">Enter the maintenance credentials to reopen the requested route in this browser session.</p>
            <form class="form stack gap-md" action="<?= h(route("maintenance.unlock")) ?>" method="post" novalidate>
              <?= csrf_field() ?>
              <input type="hidden" name="maintenance_redirect" value="<?= h((string) $maintenanceRedirectTarget) ?>">
              <?php if (($maintenanceAccess["username_required"] ?? false) === true): ?>
              <div class="form-group">
                <label class="label" for="client-preview-modal-username">Username</label>
                <input class="input" id="client-preview-modal-username" name="maintenance_username" type="text" autocomplete="username" value="<?= h($oldUsername) ?>" required data-fnlla-modal-initial-focus>
              </div>
              <?php endif; ?>
              <div class="form-group">
                <label class="label" for="client-preview-modal-password">Password</label>
                <div class="password-field">
                  <input class="input" id="client-preview-modal-password" name="maintenance_password" type="password" autocomplete="current-password" required <?= (($maintenanceAccess["username_required"] ?? false) === true) ? "" : "data-fnlla-modal-initial-focus" ?>>
                  <button class="password-toggle" type="button" data-fnlla-password-toggle data-fnlla-password-target="#client-preview-modal-password" aria-label="Toggle password visibility">Show</button>
                </div>
              </div>
              <div class="d-flex flex-wrap gap-md">
                <button class="btn btn-primary" type="submit"><?= h((string) ($clientPreview["unlock_button_label"] ?? "Unlock preview")) ?></button>
              </div>
            </form>
          </div>
        </div>
        <noscript>
          <style>
            .client-preview-actions {
              display: none !important;
            }

            .client-preview-fallback-card {
              display: block;
            }
          </style>
        </noscript>
        <script>
          window.addEventListener("DOMContentLoaded", function () {
            var countdownSource = document.getElementById("client-preview-countdown-source");
            var countdownHours = document.getElementById("client-preview-countdown-hours");
            var countdownMinutes = document.getElementById("client-preview-countdown-minutes");
            var countdownSeconds = document.getElementById("client-preview-countdown-seconds");
            var progressBar = document.getElementById("client-preview-progress-bar");
            var progressFill = document.getElementById("client-preview-progress-fill");
            var progressValue = document.getElementById("client-preview-progress-value");
            var fallbackCard = document.querySelector("[data-client-preview-fallback]");
            var modalLaunch = document.querySelector("[data-client-preview-modal-launch]");

            if (!window.FNLLARUNTIME || typeof window.FNLLARUNTIME.showModal !== "function") {
              if (modalLaunch) {
                modalLaunch.hidden = true;
              }

              if (fallbackCard) {
                fallbackCard.classList.add("is-active");
              }
            }

            if (!countdownSource) {
              return;
            }

            var targetEpoch = parseInt(countdownSource.getAttribute("data-target-epoch") || "0", 10);
            var startEpoch = parseInt(countdownSource.getAttribute("data-start-epoch") || "0", 10);
            var progressEnabled = countdownSource.getAttribute("data-progress-enabled") === "true";

            if (!targetEpoch || targetEpoch <= 0) {
              return;
            }

            function pad(value) {
              return value < 10 ? "0" + value : String(value);
            }

            function updateCountdown() {
              var remainingSeconds = Math.max(0, Math.ceil((targetEpoch * 1000 - Date.now()) / 1000));
              var hours = Math.floor(remainingSeconds / 3600);
              var minutes = Math.floor((remainingSeconds % 3600) / 60);
              var seconds = remainingSeconds % 60;

              if (countdownHours) {
                countdownHours.textContent = pad(hours);
              }

              if (countdownMinutes) {
                countdownMinutes.textContent = pad(minutes);
              }

              if (countdownSeconds) {
                countdownSeconds.textContent = pad(seconds);
              }

              if (progressEnabled && startEpoch > 0 && targetEpoch > startEpoch) {
                var totalSeconds = targetEpoch - startEpoch;
                var elapsedSeconds = Math.max(0, Math.min(totalSeconds, Math.ceil(Date.now() / 1000) - startEpoch));
                var percent = Math.max(0, Math.min(100, Math.round((elapsedSeconds / totalSeconds) * 100)));

                if (progressValue) {
                  progressValue.textContent = percent + "%";
                }

                if (progressFill) {
                  progressFill.style.width = percent + "%";
                }

                if (progressBar) {
                  progressBar.setAttribute("aria-valuenow", String(percent));
                }
              }
            }

            updateCountdown();
            window.setInterval(updateCountdown, 1000);
          });
        </script>
        <?php endif; ?>
      </div>
    </section>
  </div>
</section>
