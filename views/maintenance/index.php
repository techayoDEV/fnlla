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

$developerAccess ??= [
    "configured" => false,
    "path" => "",
    "unlocked" => false,
    "expires_at" => 0,
    "seconds_remaining" => 0,
    "unlock_ttl_minutes" => 120,
    "operations_nav_mode" => "hidden",
    "operations_nav_visible" => false,
];

$projectSetup ??= [
    "name" => (string) config("app.name", "FNLLA Project"),
    "url" => (string) config("app.base_url", ""),
];

$maintenanceLocked ??= false;
$maintenanceRedirectTarget ??= "";
$countdownLabel = "";
$freshDeveloperOnboarding = !($maintenanceAccess["enabled"] ?? false)
    && !($maintenanceAccess["configured"] ?? false)
    && !($developerAccess["configured"] ?? false);

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
            <ul class="project-note-list">
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
            <h2 class="content-title">Set the first maintenance password from the project itself</h2>
            <p class="content-text">This local setup flow can create <code>.env</code> when it is still missing, enable maintenance mode, save a hashed developer password and keep your current browser session unlocked for follow-up work.</p>
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
                <label class="label" for="developer-setup-email">Developer email</label>
                <input class="input" id="developer-setup-email" name="developer_setup_email" type="email" autocomplete="username" required>
                <p class="help-text">Used as the named login for the first developer account.</p>
              </div>
              <div class="form-group">
                <label class="label" for="developer-setup-password">Developer panel password <span class="content-text">(optional)</span></label>
                <div class="password-field">
                  <input class="input" id="developer-setup-password" name="developer_setup_password" type="password" autocomplete="new-password">
                  <button class="password-toggle" type="button" data-fnlla-password-toggle data-fnlla-password-target="#developer-setup-password" aria-label="Toggle password visibility">Show</button>
                </div>
                <p class="help-text">Leave this blank to reuse the maintenance password for the developer panel.</p>
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
  (() => {
    window.addEventListener("DOMContentLoaded", () => {
      /*
      The runtime modal is the preferred locked-screen experience. If the
      vendored runtime cannot open it, the static fallback stays available.
      */
      const fallbackCard = document.querySelector("[data-maintenance-fallback]");
      const modalLaunch = document.querySelector("[data-maintenance-modal-launch]");
      const canUseRuntimeModal = Boolean(
        window.FNLLARUNTIME && typeof window.FNLLARUNTIME.showModal === "function"
      );

      if (!canUseRuntimeModal) {
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
  })();
</script>
<?php endif; ?>
<?php else: ?>
<?php if (($maintenanceSetup["show_setup"] ?? false) && !$freshDeveloperOnboarding): ?>
<section class="section pt-1">
  <div class="container">
    <section class="feature-section" id="maintenance-setup" aria-label="Maintenance setup">
      <div class="grid grid-2 gap-lg site-login-grid">
        <article class="feature-card">
          <p class="feature-kicker">Client preview setup</p>
          <h2 class="section-title mb-0">Enable maintenance protection directly from the project before you share work in progress.</h2>
          <p class="content-text">This local setup flow writes the maintenance credentials into the project <code>.env</code>, turns the protection on, saves a hashed developer password and keeps this browser session unlocked so the developer can continue working.</p>
          <ul class="project-note-list">
            <li>Use it on a fresh project export when you want a private preview link for the client.</li>
            <li>Password is required and immediately activates maintenance mode.</li>
            <li>The project setup flow can reuse the maintenance password for the developer panel or accept a separate password here.</li>
            <li>If <code>.env</code> does not exist yet, the project setup flow can create it from <code>.env.example</code>.</li>
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
              <label class="label" for="developer-setup-email-unlocked">Developer email</label>
              <input class="input" id="developer-setup-email-unlocked" name="developer_setup_email" type="email" autocomplete="username" required>
              <p class="help-text">Used as the named login for the first developer account.</p>
            </div>
            <div class="form-group">
              <label class="label" for="developer-setup-password-unlocked">Developer panel password <span class="content-text">(optional)</span></label>
              <div class="password-field">
                <input class="input" id="developer-setup-password-unlocked" name="developer_setup_password" type="password" autocomplete="new-password">
                <button class="password-toggle" type="button" data-fnlla-password-toggle data-fnlla-password-target="#developer-setup-password-unlocked" aria-label="Toggle password visibility">Show</button>
              </div>
              <p class="help-text">Leave this blank to reuse the maintenance password for the developer panel.</p>
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
    <section class="feature-section" id="developer-panel-setup" aria-label="<?= $freshDeveloperOnboarding ? "Project setup" : "Developer panel activation" ?>">
      <div class="grid grid-2 gap-lg site-login-grid">
        <article class="feature-card project-setup-hero-card">
          <div class="project-setup-hero-content">
            <div class="project-setup-hero-topline">
              <p class="feature-kicker"><?= $freshDeveloperOnboarding ? "Project setup" : "Framework update fallback" ?></p>
              <span class="project-setup-local-badge">Local first</span>
            </div>
            <h2 class="section-title mb-0"><?= $freshDeveloperOnboarding
                ? "Project identity and private developer entry, set before handoff."
                : "Activate the developer panel for an existing project that predates this feature." ?></h2>
            <p class="content-text"><?= $freshDeveloperOnboarding
                ? "This step stores the public identity and creates the first named developer account while keeping maintenance and private preview disabled until you choose otherwise."
                : "Use this once after updating an older FNLLA project. The framework will save a developer password and keep the public project shell clean for the client." ?></p>
          </div>

          <div class="project-setup-visual" aria-hidden="true">
            <?php require dirname(__DIR__) . "/partials/framework-wordmark.php"; ?>
            <img class="developer-brand-signature" src="<?= h(asset("assets/brand/fnlla/binary-signature.png")) ?>" alt="" width="640" height="360">
            <div class="project-setup-flow">
              <span><strong>01</strong> Identity</span>
              <span><strong>02</strong> Access</span>
              <span><strong>03</strong> Panel</span>
            </div>
          </div>

          <ul class="project-note-list project-blueprint-list project-setup-contract-list">
            <?php if ($freshDeveloperOnboarding): ?>
            <li><code>identity.title</code><span>Browser title, header, operations.</span></li>
            <li><code>identity.slogan?</code><span>Optional title suffix after the site name.</span></li>
            <li><code>route.private</code><span><code>/developer</code> for technical project work.</span></li>
            <li><code>protection.mode</code><span>Maintenance and preview stay off until enabled.</span></li>
            <li><code>developer.password</code><span>Stored as a hash, rotated from the panel.</span></li>
            <?php else: ?>
            <li><code>route.private</code><span><code>/developer</code> stays the service entry after handoff.</span></li>
            <li><code>public.chrome</code><span>Developer tools appear only after unlock.</span></li>
            <li><code>session.tools</code><span>Unlocked sessions can show private navigation.</span></li>
            <li><code>developer.password</code><span>Stored as a hash, rotated from the panel.</span></li>
            <?php endif; ?>
          </ul>
        </article>
        <article class="feature-card">
          <p class="feature-kicker"><?= $freshDeveloperOnboarding ? "Developer panel" : "Activate developer panel" ?></p>
          <h2 class="content-title"><?= $freshDeveloperOnboarding ? "Save project setup and private access" : "Activate the developer surface" ?></h2>
          <form class="form stack gap-md" action="<?= h(route("maintenance.setup_developer_access")) ?>" method="post" novalidate>
            <?= csrf_field() ?>
            <?php if ($freshDeveloperOnboarding): ?>
            <div class="form-group">
              <label class="label" for="project-setup-name">Project name</label>
              <input class="input" id="project-setup-name" name="project_name" type="text" value="<?= h((string) ($projectSetup["name"] ?? "")) ?>" autocomplete="organization" required maxlength="80">
              <p class="help-text">Used in browser titles, the header and framework operation screens.</p>
            </div>
            <div class="form-group">
              <label class="label" for="project-setup-tagline">Project slogan <span class="content-text">(optional)</span></label>
              <input class="input" id="project-setup-tagline" name="project_tagline" type="text" value="<?= h((string) ($projectSetup["tagline"] ?? "")) ?>" maxlength="120" placeholder="Business systems delivered clearly">
              <p class="help-text">Appended to public browser titles, for example: Contact | Project - Slogan.</p>
            </div>
            <div class="form-group">
              <label class="label" for="project-setup-url">Public URL <span class="content-text">(optional)</span></label>
              <input class="input" id="project-setup-url" name="project_url" type="url" value="<?= h((string) ($projectSetup["url"] ?? "")) ?>" inputmode="url" autocomplete="url" placeholder="https://example.com">
              <p class="help-text">Leave blank until the project has a real local, staging or production address.</p>
            </div>
            <details class="developer-optional-section">
              <summary>
                <strong>Optional responsibility information</strong>
                <span>Name the real product or delivery lead only when the person can confirm that responsibility from a matching developer account.</span>
              </summary>
              <div class="developer-optional-section-body">
                <div class="form-group">
                  <label class="label" for="project-setup-leadership-organization">Delivery organisation <span class="content-text">(optional)</span></label>
                  <input class="input" id="project-setup-leadership-organization" name="project_leadership_organization" type="text" maxlength="120" placeholder="TechAyo Limited">
                </div>
                <div class="form-group">
                  <label class="label" for="project-setup-leadership-person">Responsible person <span class="content-text">(optional)</span></label>
                  <input class="input" id="project-setup-leadership-person" name="project_leadership_person_name" type="text" maxlength="120" autocomplete="name" placeholder="Name Surname">
                </div>
                <div class="form-group">
                  <label class="label" for="project-setup-leadership-email">Confirmation email <span class="content-text">(optional)</span></label>
                  <input class="input" id="project-setup-leadership-email" name="project_leadership_person_email" type="email" maxlength="160" autocomplete="email" placeholder="lead@example.com">
                </div>
                <div class="form-group">
                  <label class="label" for="project-setup-leadership-role">Role or position <span class="content-text">(optional)</span></label>
                  <input class="input" id="project-setup-leadership-role" name="project_leadership_person_role" type="text" maxlength="120" placeholder="Director of TechAyo">
                </div>
                <div class="form-group">
                  <label class="label" for="project-setup-leadership-responsibility">Responsibility scope <span class="content-text">(optional)</span></label>
                  <input class="input" id="project-setup-leadership-responsibility" name="project_leadership_responsibility" type="text" maxlength="240" placeholder="product direction, roadmap and technical delivery">
                </div>
                <div class="form-group">
                  <label class="label" for="project-setup-leadership-profile">Profile or contact URL <span class="content-text">(optional)</span></label>
                  <input class="input" id="project-setup-leadership-profile" name="project_leadership_profile_url" type="url" maxlength="2048" inputmode="url" placeholder="https://example.com/contact">
                </div>
                <div class="form-group">
                  <label class="label" for="project-setup-leadership-visibility">Leadership visibility <span class="content-text">(optional)</span></label>
                  <select class="select" id="project-setup-leadership-visibility" name="project_leadership_visibility">
                    <option value="disabled">Disabled</option>
                    <option value="admin">Private panel and documentation</option>
                    <option value="public">Public after confirmation</option>
                  </select>
                </div>
              </div>
            </details>
            <?php endif; ?>
            <div class="form-group">
              <label class="label" for="developer-panel-activation-email">Developer email</label>
              <input class="input" id="developer-panel-activation-email" name="developer_setup_email" type="email" autocomplete="username" required>
              <p class="help-text">This email becomes the first named developer login for <code>/developer</code>.</p>
            </div>
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
            <?php require dirname(__DIR__) . "/developer/module-options.php"; ?>
            <div class="d-flex flex-wrap gap-md">
              <button class="btn btn-primary" type="submit"><?= $freshDeveloperOnboarding ? "Save setup and open developer panel" : "Activate developer panel" ?></button>
            </div>
          </form>
        </article>
      </div>
    </section>
  </div>
</section>
<?php endif; ?>
<?php endif; ?>
