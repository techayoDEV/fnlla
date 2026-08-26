<?php

declare(strict_types=1);

$developerAccess ??= [
    "configured" => false,
    "path" => "",
    "unlocked" => false,
    "expires_at" => 0,
    "unlock_ttl_minutes" => 120,
    "operations_nav_mode" => "hidden",
];

$maintenanceAccess ??= [
    "enabled" => false,
    "configured" => false,
    "unlocked" => true,
    "username_required" => false,
];

$developerLinks ??= [];
$developerNotice ??= null;
?>
<section class="section pt-1 developer-surface developer-panel-surface">
  <div class="container site-page-stack">
    <?php if (is_array($developerNotice) && isset($developerNotice["title"], $developerNotice["text"])): ?>
    <section class="feature-section" aria-label="Developer path notice">
      <article class="feature-card">
        <p class="feature-kicker">Developer session</p>
        <h2 class="section-title mb-0"><?= h((string) $developerNotice["title"]) ?></h2>
        <p class="content-text"><?= h((string) $developerNotice["text"]) ?></p>
      </article>
    </section>
    <?php endif; ?>

    <section class="feature-section" aria-label="Developer panel overview">
      <div class="grid gap-md developer-overview-grid">
        <article class="feature-card developer-session-card">
          <p class="feature-kicker">Developer session</p>
          <h1 class="section-title mb-0">This browser session can currently reach the hidden operator surfaces for the project.</h1>
          <p class="content-text">
            <?php if (($developerAccess["expires_at"] ?? 0) > 0): ?>
            Access expires at <strong><?= h((string) date("H:i:s T", (int) $developerAccess["expires_at"])) ?></strong>.
            <?php endif; ?>
          </p>
          <div class="d-flex flex-wrap gap-md">
            <form action="<?= h(route("developer.lock")) ?>" method="post">
              <?= csrf_field() ?>
              <button class="btn btn-outline" type="submit">Lock developer panel</button>
            </form>
            <a class="btn btn-ghost" href="<?= h((string) ($developerLinks["home"] ?? route("home"))) ?>" target="_blank" rel="noopener noreferrer">Open public site</a>
          </div>
        </article>
        <article class="feature-card">
          <p class="feature-kicker">Standard access</p>
          <h2 class="content-title mb-0">Use the standard developer address</h2>
          <p class="content-text">This project uses <code>/developer</code> for sign-in and <code>/developer/panel</code> for the protected operator surface. No secret URL needs to be distributed or rotated.</p>
        </article>
        <article class="feature-card">
          <p class="feature-kicker">Navigation mode</p>
          <h2 class="content-title mb-0">DEV OPERATIONS only appears for unlocked developer sessions.</h2>
          <p class="content-text">The public project shell stays plain for the client. After a developer unlocks the panel, the header surfaces a direct DEV OPERATIONS menu for quick access to operator tools.</p>
        </article>
      </div>
    </section>

    <section class="feature-section" id="developer-maintenance-settings" aria-label="Maintenance credential settings">
      <div class="grid gap-md site-login-grid">
        <article class="feature-card">
          <p class="feature-kicker">Maintenance credentials</p>
          <h2 class="section-title mb-0">Rotate the preview password without reopening the project bootstrap flow.</h2>
          <p class="content-text"><?= ($maintenanceAccess["enabled"] ?? false)
              ? "Maintenance mode is currently active. Save settings here to keep the lock enabled or turn it off while preserving a prepared password."
              : "Maintenance mode is currently off. Save a password here when you want to prepare a private preview lock, then choose whether it should stay off or be enabled immediately." ?></p>
        </article>
        <article class="feature-card">
          <p class="feature-kicker">Update maintenance access</p>
          <h2 class="content-title">Save maintenance settings</h2>
          <form class="form stack gap-md" action="<?= h(route("developer.settings.maintenance")) ?>" method="post" novalidate>
            <?= csrf_field() ?>
            <div class="form-group">
              <label class="label" for="maintenance-access-password">Password</label>
              <div class="password-field">
                <input class="input" id="maintenance-access-password" name="maintenance_access_password" type="password" autocomplete="new-password" required>
                <button class="password-toggle" type="button" data-fnlla-password-toggle data-fnlla-password-target="#maintenance-access-password" aria-label="Toggle password visibility">Show</button>
              </div>
            </div>
            <div class="form-group">
              <label class="label" for="maintenance-access-password-confirmation">Confirm password</label>
              <div class="password-field">
                <input class="input" id="maintenance-access-password-confirmation" name="maintenance_access_password_confirmation" type="password" autocomplete="new-password" required>
                <button class="password-toggle" type="button" data-fnlla-password-toggle data-fnlla-password-target="#maintenance-access-password-confirmation" aria-label="Toggle password visibility">Show</button>
              </div>
            </div>
            <input type="hidden" name="maintenance_access_enabled" value="0">
            <div class="form-group">
              <label class="label" for="maintenance-access-enabled">
                <input id="maintenance-access-enabled" name="maintenance_access_enabled" type="checkbox" value="1" <?= ($maintenanceAccess["enabled"] ?? false) ? "checked" : "" ?>>
                <?= ($maintenanceAccess["enabled"] ?? false) ? "Keep maintenance mode enabled after saving" : "Enable maintenance mode after saving" ?>
              </label>
              <p class="help-text">Leave this unchecked to store or rotate the maintenance password without locking the public routes yet.</p>
            </div>
            <div class="d-flex flex-wrap gap-md">
              <button class="btn btn-primary" type="submit">Save maintenance settings</button>
            </div>
          </form>
        </article>
      </div>
    </section>

    <section class="feature-section" id="developer-access-settings" aria-label="Developer access settings">
      <div class="grid gap-md site-login-grid">
        <article class="feature-card">
          <p class="feature-kicker">Developer access controls</p>
          <h2 class="section-title mb-0">Change the developer password when access needs to be rotated.</h2>
          <p class="content-text">The password protects the panel itself. Existing sessions are invalidated automatically after the password changes.</p>
        </article>
        <article class="feature-card">
          <p class="feature-kicker">Update developer access</p>
          <h2 class="content-title">Save a new developer password</h2>
          <form class="form stack gap-md" action="<?= h(route("developer.settings.password")) ?>" method="post" novalidate>
            <?= csrf_field() ?>
            <div class="form-group">
              <label class="label" for="developer-panel-password">Developer panel password</label>
              <div class="password-field">
                <input class="input" id="developer-panel-password" name="developer_access_password" type="password" autocomplete="new-password" required>
                <button class="password-toggle" type="button" data-fnlla-password-toggle data-fnlla-password-target="#developer-panel-password" aria-label="Toggle password visibility">Show</button>
              </div>
            </div>
            <div class="form-group">
              <label class="label" for="developer-panel-password-confirmation">Confirm developer panel password</label>
              <div class="password-field">
                <input class="input" id="developer-panel-password-confirmation" name="developer_access_password_confirmation" type="password" autocomplete="new-password" required>
                <button class="password-toggle" type="button" data-fnlla-password-toggle data-fnlla-password-target="#developer-panel-password-confirmation" aria-label="Toggle password visibility">Show</button>
              </div>
            </div>
            <div class="d-flex flex-wrap gap-md">
              <button class="btn btn-primary" type="submit">Save developer password</button>
            </div>
          </form>
        </article>
      </div>
    </section>
  </div>
</section>
