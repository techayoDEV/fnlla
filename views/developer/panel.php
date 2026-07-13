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
<section class="section pt-1">
  <div class="container site-page-stack">
    <?php if (is_array($developerNotice) && isset($developerNotice["path"], $developerNotice["title"], $developerNotice["text"])): ?>
    <section class="feature-section" aria-label="Developer path notice">
      <article class="feature-card">
        <p class="feature-kicker">Private developer path</p>
        <h2 class="section-title mb-0"><?= h((string) $developerNotice["title"]) ?></h2>
        <p class="content-text"><?= h((string) $developerNotice["text"]) ?></p>
        <p class="developer-secret-path"><code><?= h((string) $developerNotice["path"]) ?></code></p>
      </article>
    </section>
    <?php endif; ?>

    <section class="feature-section" aria-label="Developer panel overview">
      <div class="grid gap-md developer-overview-grid">
        <article class="feature-card">
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
          <p class="feature-kicker">Private route</p>
          <h2 class="content-title mb-0">Current hidden developer path</h2>
          <p class="developer-secret-path"><code><?= h((string) ($developerAccess["path"] ?? "")) ?></code></p>
          <p class="content-text">Keep this private address inside the developer team. If it leaks, regenerate it below and the previous path stops working immediately.</p>
        </article>
        <article class="feature-card">
          <p class="feature-kicker">Navigation mode</p>
          <h2 class="content-title mb-0">DEV OPERATIONS only appears for unlocked developer sessions.</h2>
          <p class="content-text">The public starter stays plain for the client. After a developer unlocks this hidden panel, the header surfaces a direct DEV OPERATIONS button for quick return to the panel.</p>
        </article>
      </div>
    </section>

    <section class="feature-section" id="developer-maintenance-settings" aria-label="Maintenance credential settings">
      <div class="grid gap-md site-login-grid">
        <article class="feature-card">
          <p class="feature-kicker">Maintenance credentials</p>
          <h2 class="section-title mb-0">Rotate the preview password without reopening the starter bootstrap flow.</h2>
          <p class="content-text">This panel now keeps the maintenance flow password-first. Saving a new password also refreshes the current maintenance session for the developer.</p>
        </article>
        <article class="feature-card">
          <p class="feature-kicker">Update maintenance access</p>
          <h2 class="content-title">Save new maintenance password</h2>
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
            <div class="d-flex flex-wrap gap-md">
              <button class="btn btn-primary" type="submit">Save maintenance password</button>
            </div>
          </form>
        </article>
      </div>
    </section>

    <section class="feature-section" id="developer-access-settings" aria-label="Developer access settings">
      <div class="grid gap-md site-login-grid">
        <article class="feature-card">
          <p class="feature-kicker">Developer access controls</p>
          <h2 class="section-title mb-0">Change the hidden password and treat path rotation like an emergency brake when needed.</h2>
          <p class="content-text">The password protects the panel itself. Regenerating the private path immediately invalidates the previous address if a link escaped to the wrong place.</p>
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

    <section class="feature-section" aria-label="Developer path rotation">
      <div class="grid gap-md site-login-grid">
        <article class="feature-card">
          <p class="feature-kicker">Emergency rotation</p>
          <h2 class="section-title mb-0">Regenerate the private developer path if the current link may have leaked.</h2>
          <ul class="starter-note-list">
            <li>The previous hidden path stops opening the panel immediately after rotation.</li>
            <li>Your current unlocked session is preserved and redirected to the new address.</li>
            <li>Share the replacement path only with the developer team that should retain access.</li>
          </ul>
        </article>
        <article class="feature-card">
          <p class="feature-kicker">Rotate developer path</p>
          <h2 class="content-title">Generate a new private address now</h2>
          <form class="form stack gap-md" action="<?= h(route("developer.settings.rotate_path")) ?>" method="post">
            <?= csrf_field() ?>
            <div class="d-flex flex-wrap gap-md">
              <button class="btn btn-outline" type="submit">Regenerate private developer path</button>
            </div>
          </form>
        </article>
      </div>
    </section>
  </div>
</section>
