<?php

declare(strict_types=1);

$maintenanceAccess ??= [
    "enabled" => false,
    "configured" => false,
    "unlocked" => true,
    "username_required" => false,
    "expires_at" => 0,
    "seconds_remaining" => 0,
    "unlock_ttl_minutes" => 10,
];

$maintenanceSetup ??= [
    "enabled" => false,
    "local_only" => true,
    "is_local_request" => false,
    "can_setup" => false,
    "needs_setup" => false,
    "show_setup" => false,
    "env_exists" => false,
    "env_writable" => false,
    "message" => "",
];

$developerSetup ??= [
    "enabled" => false,
    "local_only" => true,
    "is_local_request" => false,
    "can_setup" => false,
    "needs_setup" => false,
    "show_setup" => false,
    "env_exists" => false,
    "env_writable" => false,
    "message" => "",
];

$maintenanceLocked ??= false;
$maintenanceRedirectTarget ??= "";
$countdownLabel = "";

if (($maintenanceAccess["seconds_remaining"] ?? 0) > 0) {
    $minutes = (int) floor(((int) $maintenanceAccess["seconds_remaining"]) / 60);
    $seconds = (int) (((int) $maintenanceAccess["seconds_remaining"]) % 60);
    $countdownLabel = sprintf("%02d:%02d", $minutes, $seconds);
}
?>
<?php if ($maintenanceLocked): ?>
<section class="section maintenance-lock-stage">
  <div class="container">
    <section class="card maintenance-lock-shell" id="maintenance-access" aria-label="Maintenance access form">
      <div class="card-body maintenance-lock-shell-body">
        <article class="card card-soft site-card-muted maintenance-lock-panel">
          <div class="card-body maintenance-lock-panel-body">
            <p class="feature-kicker">Access rules</p>
            <h2 class="content-title">Locked requests are redirected here until the session is unlocked.</h2>
            <p class="content-text">Once the password is accepted, this browser session stays open for <?= h((string) ($maintenanceAccess["unlock_ttl_minutes"] ?? 10)) ?> minutes before the maintenance lock restores itself automatically.</p>
            <ul class="starter-note-list">
              <li>Public routes stay protected until maintenance access succeeds.</li>
              <li>Repeated failed attempts are temporarily blocked.</li>
              <li>The lock can be restored immediately from the operator surface after review.</li>
            </ul>
          </div>
        </article>

        <article class="card maintenance-lock-panel">
          <div class="card-body maintenance-lock-panel-body">
            <?php if (!($maintenanceAccess["configured"] ?? false) && ($maintenanceSetup["show_setup"] ?? false)): ?>
            <p class="feature-kicker">Configure maintenance</p>
            <h2 class="content-title">Set the first maintenance password from the starter itself</h2>
            <p class="content-text">This local setup flow can create <code>.env</code> when it is still missing, enable maintenance mode, generate a private developer panel path and keep your current browser session unlocked for follow-up work.</p>
            <form class="form stack gap-md maintenance-lock-form" id="maintenance-setup" action="<?= h(route("maintenance.setup_access")) ?>" method="post" novalidate>
              <?= csrf_field() ?>
              <input type="hidden" name="maintenance_redirect" value="<?= h((string) $maintenanceRedirectTarget) ?>">
              <div class="form-group">
                <label class="label" for="maintenance-setup-password">Password</label>
                <div class="password-field">
                  <input class="input" id="maintenance-setup-password" name="maintenance_setup_password" type="password" autocomplete="new-password" required>
                  <button class="password-toggle" type="button" data-fnlla-password-toggle data-fnlla-password-target="#maintenance-setup-password" aria-label="Toggle password visibility">Show</button>
                </div>
              </div>
              <div class="form-group">
                <label class="label" for="maintenance-setup-password-confirmation">Confirm password</label>
                <div class="password-field">
                  <input class="input" id="maintenance-setup-password-confirmation" name="maintenance_setup_password_confirmation" type="password" autocomplete="new-password" required>
                  <button class="password-toggle" type="button" data-fnlla-password-toggle data-fnlla-password-target="#maintenance-setup-password-confirmation" aria-label="Toggle password visibility">Show</button>
                </div>
              </div>
              <div class="form-group">
                <label class="label" for="developer-setup-password">Developer panel password <span class="content-text">(optional)</span></label>
                <div class="password-field">
                  <input class="input" id="developer-setup-password" name="developer_setup_password" type="password" autocomplete="new-password">
                  <button class="password-toggle" type="button" data-fnlla-password-toggle data-fnlla-password-target="#developer-setup-password" aria-label="Toggle password visibility">Show</button>
                </div>
                <p class="help-text">Leave this blank to reuse the maintenance password for the hidden developer panel.</p>
              </div>
              <div class="form-group">
                <label class="label" for="developer-setup-password-confirmation">Confirm developer panel password</label>
                <div class="password-field">
                  <input class="input" id="developer-setup-password-confirmation" name="developer_setup_password_confirmation" type="password" autocomplete="new-password">
                  <button class="password-toggle" type="button" data-fnlla-password-toggle data-fnlla-password-target="#developer-setup-password-confirmation" aria-label="Toggle password visibility">Show</button>
                </div>
              </div>
              <div class="d-flex flex-wrap gap-md">
                <button class="btn btn-primary" type="submit">Save and enable maintenance</button>
              </div>
            </form>
            <?php elseif (!($maintenanceAccess["configured"] ?? false)): ?>
            <p class="feature-kicker">Unlock maintenance</p>
            <h2 class="content-title">Open the protected project surface</h2>
            <div class="alert alert-warning" role="alert">
              <h3 class="alert-title">Password not configured yet</h3>
              <p class="alert-text"><?= h((string) ($maintenanceSetup["message"] ?? "Set MAINTENANCE_ACCESS_PASSWORD in .env before trying to unlock this project.")) ?></p>
            </div>
            <?php else: ?>
            <p class="feature-kicker">Unlock maintenance</p>
            <h2 class="content-title">Reopen the protected project surface</h2>
            <p class="content-text">The protected access window should open automatically and return this browser session to the requested route after access succeeds.</p>
            <div class="d-flex flex-wrap gap-md maintenance-modal-launch" data-maintenance-modal-launch>
              <button class="btn btn-primary" type="button" data-fnlla-modal-open="#maintenance-unlock-modal">Unlock access</button>
            </div>
            <article class="card card-soft site-card-muted maintenance-fallback-card" data-maintenance-fallback>
              <div class="card-body maintenance-lock-panel-body">
                <p class="feature-kicker">Fallback access</p>
                <p class="content-text">The browser could not open the protected access window, so the inline unlock form is active instead.</p>
                <form class="form stack gap-md maintenance-lock-form maintenance-fallback-form" action="<?= h(route("maintenance.unlock")) ?>" method="post" novalidate>
                  <?= csrf_field() ?>
                  <input type="hidden" name="maintenance_redirect" value="<?= h((string) $maintenanceRedirectTarget) ?>">
                  <?php if ($maintenanceAccess["username_required"] ?? false): ?>
                  <div class="form-group">
                    <label class="label" for="maintenance-username">Username</label>
                    <input class="input" id="maintenance-username" name="maintenance_username" type="text" autocomplete="username" value="<?= h((string) old("maintenance_username")) ?>" required>
                  </div>
                  <?php endif; ?>
                  <div class="form-group">
                    <label class="label" for="maintenance-password">Password</label>
                    <div class="password-field">
                      <input class="input" id="maintenance-password" name="maintenance_password" type="password" autocomplete="current-password" required>
                      <button class="password-toggle" type="button" data-fnlla-password-toggle data-fnlla-password-target="#maintenance-password" aria-label="Toggle password visibility">Show</button>
                    </div>
                  </div>
                  <div class="d-flex flex-wrap gap-md">
                    <button class="btn btn-outline" type="submit">Unlock access</button>
                  </div>
                </form>
              </div>
            </article>
            <?php endif; ?>
          </div>
        </article>
      </div>
    </section>
  </div>
</section>
<?php if (($maintenanceAccess["configured"] ?? false)): ?>
<div
  class="modal"
  id="maintenance-unlock-modal"
  data-fnlla-modal
  data-fnlla-modal-locked
  role="dialog"
  aria-modal="true"
  aria-labelledby="maintenance-unlock-modal-title"
  hidden
>
  <div class="modal-content maintenance-unlock-modal-content">
    <div class="mb-3">
      <p class="feature-kicker mb-2">Maintenance access</p>
      <h2 class="content-title mb-0" id="maintenance-unlock-modal-title">Unlock the protected project surface</h2>
    </div>
    <p class="content-text">Enter the maintenance credentials to reopen the requested route in this browser session.</p>
    <form class="form stack gap-md maintenance-lock-form" action="<?= h(route("maintenance.unlock")) ?>" method="post" novalidate>
      <?= csrf_field() ?>
      <input type="hidden" name="maintenance_redirect" value="<?= h((string) $maintenanceRedirectTarget) ?>">
      <?php if ($maintenanceAccess["username_required"] ?? false): ?>
      <div class="form-group">
        <label class="label" for="maintenance-modal-username">Username</label>
        <input class="input" id="maintenance-modal-username" name="maintenance_username" type="text" autocomplete="username" value="<?= h((string) old("maintenance_username")) ?>" required data-fnlla-modal-initial-focus>
      </div>
      <?php endif; ?>
      <div class="form-group">
        <label class="label" for="maintenance-modal-password">Password</label>
        <div class="password-field">
          <input class="input" id="maintenance-modal-password" name="maintenance_password" type="password" autocomplete="current-password" required <?= ($maintenanceAccess["username_required"] ?? false) ? "" : "data-fnlla-modal-initial-focus" ?>>
          <button class="password-toggle" type="button" data-fnlla-password-toggle data-fnlla-password-target="#maintenance-modal-password" aria-label="Toggle password visibility">Show</button>
        </div>
      </div>
      <div class="d-flex flex-wrap gap-md">
        <button class="btn btn-primary" type="submit">Unlock access</button>
      </div>
    </form>
  </div>
</div>
<noscript>
  <style>
    .maintenance-modal-launch {
      display: none !important;
    }

    .maintenance-fallback-card {
      display: block;
    }
  </style>
</noscript>
<script>
  window.addEventListener("DOMContentLoaded", function () {
    var fallbackCard = document.querySelector("[data-maintenance-fallback]");
    var modalLaunch = document.querySelector("[data-maintenance-modal-launch]");

    if (!window.FNLLARUNTIME || typeof window.FNLLARUNTIME.showModal !== "function") {
      if (modalLaunch) {
        modalLaunch.hidden = true;
      }

      if (fallbackCard) {
        fallbackCard.classList.add("is-active");
      }

      return;
    }

    window.FNLLARUNTIME.showModal("#maintenance-unlock-modal");
  });
</script>
<?php endif; ?>
<?php else: ?>
<?php if (($maintenanceSetup["show_setup"] ?? false)): ?>
<section class="section pt-1">
  <div class="container">
    <section class="feature-section" id="maintenance-setup" aria-label="Maintenance setup">
      <div class="grid grid-2 gap-lg site-login-grid">
        <article class="feature-card">
          <p class="feature-kicker">Client preview setup</p>
          <h2 class="section-title mb-0">Enable maintenance protection directly from the starter before you share work in progress.</h2>
          <p class="content-text">This local setup flow writes the maintenance credentials into the project <code>.env</code>, turns the protection on, generates a hidden developer panel path and keeps this browser session unlocked so the developer can continue working.</p>
          <ul class="starter-note-list">
            <li>Use it on a fresh starter when you want a private preview link for the client.</li>
            <li>Password is required and immediately activates maintenance mode.</li>
            <li>The starter can reuse the maintenance password for the hidden developer panel or accept a separate password here.</li>
            <li>If <code>.env</code> does not exist yet, the starter can create it from <code>.env.example</code>.</li>
          </ul>
        </article>
        <article class="feature-card">
          <p class="feature-kicker">Configure maintenance access</p>
          <h2 class="content-title">Save the first maintenance password</h2>
          <form class="form stack gap-md" action="<?= h(route("maintenance.setup_access")) ?>" method="post" novalidate>
            <?= csrf_field() ?>
            <div class="form-group">
              <label class="label" for="maintenance-setup-password-unlocked">Password</label>
              <div class="password-field">
                <input class="input" id="maintenance-setup-password-unlocked" name="maintenance_setup_password" type="password" autocomplete="new-password" required>
                <button class="password-toggle" type="button" data-fnlla-password-toggle data-fnlla-password-target="#maintenance-setup-password-unlocked" aria-label="Toggle password visibility">Show</button>
              </div>
            </div>
            <div class="form-group">
              <label class="label" for="maintenance-setup-password-confirmation-unlocked">Confirm password</label>
              <div class="password-field">
                <input class="input" id="maintenance-setup-password-confirmation-unlocked" name="maintenance_setup_password_confirmation" type="password" autocomplete="new-password" required>
                <button class="password-toggle" type="button" data-fnlla-password-toggle data-fnlla-password-target="#maintenance-setup-password-confirmation-unlocked" aria-label="Toggle password visibility">Show</button>
              </div>
            </div>
            <div class="form-group">
              <label class="label" for="developer-setup-password-unlocked">Developer panel password <span class="content-text">(optional)</span></label>
              <div class="password-field">
                <input class="input" id="developer-setup-password-unlocked" name="developer_setup_password" type="password" autocomplete="new-password">
                <button class="password-toggle" type="button" data-fnlla-password-toggle data-fnlla-password-target="#developer-setup-password-unlocked" aria-label="Toggle password visibility">Show</button>
              </div>
              <p class="help-text">Leave this blank to reuse the maintenance password for the hidden developer panel.</p>
            </div>
            <div class="form-group">
              <label class="label" for="developer-setup-password-confirmation-unlocked">Confirm developer panel password</label>
              <div class="password-field">
                <input class="input" id="developer-setup-password-confirmation-unlocked" name="developer_setup_password_confirmation" type="password" autocomplete="new-password">
                <button class="password-toggle" type="button" data-fnlla-password-toggle data-fnlla-password-target="#developer-setup-password-confirmation-unlocked" aria-label="Toggle password visibility">Show</button>
              </div>
            </div>
            <div class="d-flex flex-wrap gap-md">
              <button class="btn btn-primary" type="submit">Save and enable maintenance</button>
            </div>
          </form>
        </article>
      </div>
    </section>
  </div>
</section>
<?php endif; ?>

<?php if (($maintenanceAccess["enabled"] ?? false) && ($maintenanceAccess["unlocked"] ?? false)): ?>
<section class="section pt-1">
  <div class="container">
    <section class="feature-section" aria-label="Maintenance access status">
      <div class="grid gap-md maintenance-session-stack">
        <article class="feature-card">
          <p class="feature-kicker">Maintenance session</p>
          <h2 class="section-title mb-0">This browser session currently has access to the protected project routes.</h2>
          <p class="content-text">
            <?php if (($maintenanceAccess["expires_at"] ?? 0) > 0): ?>
            Access expires at <strong><?= h((string) date("H:i:s T", (int) $maintenanceAccess["expires_at"])) ?></strong>.
            <?php endif; ?>
          </p>
        </article>
        <article class="feature-card">
          <p class="feature-kicker">Restore lock</p>
          <h2 class="content-title">Close the protected session now</h2>
          <p class="content-text">Use this when review is complete and you want public routes redirected back to maintenance immediately.</p>
          <form action="<?= h(route("maintenance.lock")) ?>" method="post">
            <?= csrf_field() ?>
            <button class="btn btn-outline" type="submit">Lock site again</button>
          </form>
        </article>
      </div>
    </section>
  </div>
</section>
<?php endif; ?>

<?php if (($developerSetup["show_setup"] ?? false) && !($developerAccess["configured"] ?? false)): ?>
<section class="section">
  <div class="container">
    <section class="feature-section" id="developer-panel-setup" aria-label="Developer panel activation">
      <div class="grid grid-2 gap-lg site-login-grid">
        <article class="feature-card">
          <p class="feature-kicker">Framework update fallback</p>
          <h2 class="section-title mb-0">Activate the hidden developer panel for an existing project that predates this feature.</h2>
          <p class="content-text">Use this once after updating an older FNLLA project. The framework will generate a private path, save a developer password and keep the public starter shell clean for the client.</p>
          <ul class="starter-note-list">
            <li>The generated path becomes the long-term service entry after client handoff.</li>
            <li>The public header stays plain. Developer tools appear only after a developer unlocks the hidden path.</li>
            <li>An active developer session can still surface a private tools dropdown for easier navigation.</li>
            <li>The new panel will let you rotate both its password and the hidden path later.</li>
          </ul>
        </article>
        <article class="feature-card">
          <p class="feature-kicker">Activate developer panel</p>
          <h2 class="content-title">Generate the hidden service surface</h2>
          <form class="form stack gap-md" action="<?= h(route("maintenance.setup_developer_access")) ?>" method="post" novalidate>
            <?= csrf_field() ?>
            <div class="form-group">
              <label class="label" for="developer-panel-activation-password">Developer panel password</label>
              <div class="password-field">
                <input class="input" id="developer-panel-activation-password" name="developer_setup_password" type="password" autocomplete="new-password" required>
                <button class="password-toggle" type="button" data-fnlla-password-toggle data-fnlla-password-target="#developer-panel-activation-password" aria-label="Toggle password visibility">Show</button>
              </div>
            </div>
            <div class="form-group">
              <label class="label" for="developer-panel-activation-password-confirmation">Confirm developer panel password</label>
              <div class="password-field">
                <input class="input" id="developer-panel-activation-password-confirmation" name="developer_setup_password_confirmation" type="password" autocomplete="new-password" required>
                <button class="password-toggle" type="button" data-fnlla-password-toggle data-fnlla-password-target="#developer-panel-activation-password-confirmation" aria-label="Toggle password visibility">Show</button>
              </div>
            </div>
            <div class="d-flex flex-wrap gap-md">
              <button class="btn btn-primary" type="submit">Activate developer panel</button>
            </div>
          </form>
        </article>
      </div>
    </section>
  </div>
</section>
<?php endif; ?>
<?php endif; ?>
