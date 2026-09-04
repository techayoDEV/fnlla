<?php

declare(strict_types=1);

$developerPanelTitle = "Developer Profile";
$developerPanelLead = "Personal developer identity, avatar and password rotation for this project.";
$currentDeveloper = is_array($developerAccess["current_developer"] ?? null) ? (array) $developerAccess["current_developer"] : [];
$developerRoleOptions = is_array($developerAccess["role_options"] ?? null) ? (array) $developerAccess["role_options"] : [];
$developerName = (string) old("developer_profile_name", (string) ($currentDeveloper["name"] ?? "Developer"));
$developerRole = (string) old("developer_profile_role", (string) ($currentDeveloper["role"] ?? "application_developer"));
$developerAvatar = (string) old("developer_profile_avatar", (string) ($currentDeveloper["avatar"] ?? ""));
$avatarIsUrl = $developerAvatar !== "" && (filter_var($developerAvatar, FILTER_VALIDATE_URL) !== false || str_starts_with($developerAvatar, "/uploads/developer-avatars/"));
$developerNameMark = (string) preg_replace('/[^A-Za-z0-9]/', '', $developerName);
$avatarMark = $developerAvatar !== "" && !$avatarIsUrl
    ? strtoupper(substr($developerAvatar, 0, 2))
    : strtoupper(substr($developerNameMark !== "" ? $developerNameMark : "D", 0, 2));
$security = is_array($developerAccess["security"] ?? null) ? (array) $developerAccess["security"] : [];
$hasNamedAccount = trim((string) ($currentDeveloper["email"] ?? "")) !== "";
$developerAvatarMaxBytes = max(1, (int) config("security.uploads.max_file_bytes", 5242880));
$developerAvatarMaxLabel = $developerAvatarMaxBytes >= 1048576
    ? rtrim(rtrim(number_format($developerAvatarMaxBytes / 1048576, 1), "0"), ".") . " MB"
    : (string) $developerAvatarMaxBytes . " bytes";
require __DIR__ . "/panel-header.php";
?>

        <section class="developer-dashboard-section" id="developer-profile" aria-label="Developer profile">
          <div class="developer-panel-intro">
            <div class="developer-panel-intro-copy">
              <p class="feature-kicker">Developer identity</p>
              <h2 class="developer-dashboard-section-title">Your profile controls how developer-panel changes are attributed. <span class="developer-info-tip" tabindex="0" aria-label="Profile data is used by audit and workspace activity.">i<span>Name and avatar make audit logs, account actions and kanban work easier to identify.</span></span></h2>
              <p class="content-text mb-0">Project-level changes remain global, but audit events, kanban activity and account actions should identify the person who made them.</p>
            </div>
            <div class="developer-panel-intro-actions">
              <a class="btn btn-outline btn-sm" href="<?= h((string) ($developerLinks["access"] ?? route("developer.panel.access"))) ?>">Access &amp; security</a>
              <a class="btn btn-outline btn-sm" href="<?= h((string) ($developerLinks["overview"] ?? route("developer.panel"))) ?>">Dashboard</a>
            </div>
          </div>

          <div class="developer-dashboard-status-grid">
            <article class="developer-dashboard-status-card">
              <div class="developer-profile-head">
                <span class="developer-profile-avatar" aria-hidden="true">
                  <?php if ($avatarIsUrl): ?>
                  <img src="<?= h($developerAvatar) ?>" alt="">
                  <?php else: ?>
                  <?= h($avatarMark) ?>
                  <?php endif; ?>
                </span>
                <div>
                  <p class="feature-kicker">Signed in as</p>
                  <h3><?= h($developerName) ?></h3>
                </div>
              </div>
            </article>
            <article class="developer-dashboard-status-card">
              <div class="developer-dashboard-card-head"><strong>Email</strong><span class="developer-dashboard-ok">NAMED</span></div>
              <h3><?= h((string) (($currentDeveloper["email"] ?? "") ?: "Named account required")) ?></h3>
              <p>Used in activity logs and workspace updates.</p>
            </article>
            <article class="developer-dashboard-status-card">
              <div class="developer-dashboard-card-head"><strong>Role</strong><span class="developer-dashboard-ok">LOCKED</span></div>
              <h3><?= h((string) ($developerRoleOptions[$developerRole] ?? $currentDeveloper["role_label"] ?? "Developer")) ?></h3>
              <p>Only a Lead developer can change account roles from Access &amp; Security.</p>
            </article>
            <article class="developer-dashboard-status-card">
              <div class="developer-dashboard-card-head"><strong>2FA</strong><span class="developer-dashboard-ok"><?= ($security["totp_enabled"] ?? false) ? "ON" : "OFF" ?></span></div>
              <h3><?= ($security["totp_enabled"] ?? false) ? "Authenticator active" : "Password only" ?></h3>
              <p>Manage 2FA from this profile.</p>
              <button class="btn btn-outline btn-sm" type="button" data-fnlla-modal-open="#developer-profile-2fa-modal">Manage 2FA</button>
            </article>
          </div>

          <div class="developer-panel-form-grid">
            <article class="developer-panel-fieldset-card">
              <p class="feature-kicker">Profile details</p>
              <h2 class="content-title">Update your developer profile</h2>
              <form class="form stack gap-md" action="<?= h(route("developer.profile.save")) ?>" method="post" enctype="multipart/form-data" novalidate>
                <?= csrf_field() ?>
                <div class="form-group">
                  <label class="label" for="developer-profile-name">Display name</label>
                  <input class="input" id="developer-profile-name" name="developer_profile_name" type="text" autocomplete="name" value="<?= h($developerName) ?>" required>
                </div>
                <div class="form-group">
                  <span class="label">Role</span>
                  <p class="developer-profile-role mb-0"><?= h((string) ($developerRoleOptions[$developerRole] ?? $currentDeveloper["role_label"] ?? "Developer")) ?></p>
                  <p class="help-text">Only a Lead developer can change account roles from Access &amp; Security.</p>
                </div>
                <div class="form-group">
                  <label class="label" for="developer-profile-avatar">Avatar mark or image URL</label>
                  <input class="input" id="developer-profile-avatar" name="developer_profile_avatar" type="text" maxlength="2048" value="<?= h($developerAvatar) ?>" placeholder="MD or https://example.test/avatar.png">
                  <p class="help-text">Use initials, a project-hosted image path or an HTTP(S) image URL when the project has an approved asset source.</p>
                </div>
                <div class="form-group">
                  <label class="label" for="developer-profile-avatar-file">Upload avatar image</label>
                  <input class="input developer-profile-file-input" id="developer-profile-avatar-file" name="developer_profile_avatar_file" type="file" accept="image/jpeg,image/png,image/webp">
                  <p class="help-text">Accepted: JPEG, PNG or WebP up to <?= h($developerAvatarMaxLabel) ?>. Uploaded files are stored under <code>public/uploads/developer-avatars</code>.</p>
                </div>
                <?php if ($developerAvatar !== ""): ?>
                <label class="checkbox-option developer-profile-remove-avatar">
                  <input type="checkbox" name="developer_profile_remove_avatar" value="1">
                  <span>Remove current avatar and use account initials.</span>
                </label>
                <?php endif; ?>
                <label class="checkbox-option">
                  <input type="checkbox" name="developer_profile_generate_avatar" value="1">
                  <span>Generate a clean local avatar from my display name.</span>
                </label>
                <div class="form-message" role="status">
                  <h3 class="form-message-title">Avatar priority</h3>
                  <p class="form-message-text mb-0">Remove clears the current avatar first. Otherwise, uploaded image wins, generated avatar follows, and the text avatar field is used when neither option is selected.</p>
                </div>
                <button class="btn btn-primary" type="submit">Save profile</button>
              </form>
            </article>

            <article class="developer-panel-fieldset-card">
              <p class="feature-kicker">Password</p>
              <h2 class="content-title">Rotate your developer password</h2>
              <form class="form stack gap-md" action="<?= h(route("developer.settings.password")) ?>" method="post" novalidate>
                <?= csrf_field() ?>
                <input type="hidden" name="developer_password_redirect" value="profile">
                <div class="form-group">
                  <label class="label" for="developer-profile-password">New developer password</label>
                  <div class="password-field">
                    <input class="input" id="developer-profile-password" name="developer_access_password" type="password" autocomplete="new-password" required>
                    <button class="password-toggle" type="button" data-fnlla-password-toggle data-fnlla-password-target="#developer-profile-password" aria-label="Toggle password visibility">Show</button>
                  </div>
                </div>
                <div class="form-group">
                  <label class="label" for="developer-profile-password-confirmation">Confirm new developer password</label>
                  <div class="password-field">
                    <input class="input" id="developer-profile-password-confirmation" name="developer_access_password_confirmation" type="password" autocomplete="new-password" required>
                    <button class="password-toggle" type="button" data-fnlla-password-toggle data-fnlla-password-target="#developer-profile-password-confirmation" aria-label="Toggle password visibility">Show</button>
                  </div>
                </div>
                <div class="developer-panel-status-note">
                  <strong>Scope</strong>
                  <span>This updates your own developer password. Lead-only account management stays in Access &amp; Security.</span>
                </div>
                <button class="btn btn-primary" type="submit">Save new password</button>
              </form>
            </article>
          </div>
        </section>

        <div class="modal developer-kanban-modal" id="developer-profile-2fa-modal" data-fnlla-modal role="dialog" aria-modal="true" aria-labelledby="developer-profile-2fa-title" hidden>
          <div class="modal-content developer-kanban-modal-content">
            <div class="developer-kanban-modal-head">
              <div>
                <p class="feature-kicker">Two-factor authentication</p>
                <h2 class="content-title" id="developer-profile-2fa-title">Configure authenticator 2FA</h2>
              </div>
              <button class="developer-kanban-modal-close" type="button" data-fnlla-modal-close aria-label="Close 2FA settings"><span aria-hidden="true">x</span></button>
            </div>

            <div class="developer-profile-security-grid">
              <article class="developer-panel-status-note">
                <strong>Status</strong>
                <span><?= ($security["totp_enabled"] ?? false) ? "TOTP is enabled for this developer account." : (($security["totp_configured"] ?? false) ? "A setup key exists but is not enforced yet." : "No authenticator setup key exists yet.") ?></span>
              </article>
              <article class="developer-panel-status-note">
                <strong>Passkey adapter</strong>
                <span><?= h((string) (($security["passkey_ready"] ?? false) ? ($security["passkey_label"] ?? "External") : "Disabled")) ?></span>
              </article>
            </div>

            <?php if (!$hasNamedAccount): ?>
            <p class="content-text mb-0">Create a named developer account before enabling two-factor authentication.</p>
            <?php else: ?>
            <form class="form stack gap-md" action="<?= h(route("developer.security.save")) ?>" method="post">
              <?= csrf_field() ?>
              <input type="hidden" name="developer_security_action" value="generate_totp">
              <button class="btn btn-outline" type="submit">Generate authenticator setup key</button>
            </form>
            <?php if (($security["totp_configured"] ?? false) === true): ?>
            <div class="form-message" role="status">
              <h3 class="form-message-title">Authenticator URI</h3>
              <p class="form-message-text"><code><?= h((string) ($security["totp_uri"] ?? "")) ?></code></p>
              <p class="form-message-text mb-0">Add this URI to a TOTP authenticator, then confirm the current code below.</p>
            </div>
            <form class="form stack gap-md" action="<?= h(route("developer.security.save")) ?>" method="post">
              <?= csrf_field() ?>
              <input type="hidden" name="developer_security_action" value="enable_totp">
              <div class="form-group">
                <label class="label" for="developer-profile-totp-code">Current authenticator code</label>
                <input class="input" id="developer-profile-totp-code" name="developer_totp_code" type="text" inputmode="numeric" maxlength="12" autocomplete="one-time-code" required>
              </div>
              <button class="btn btn-primary" type="submit">Enable 2FA</button>
            </form>
            <form class="form" action="<?= h(route("developer.security.save")) ?>" method="post">
              <?= csrf_field() ?>
              <input type="hidden" name="developer_security_action" value="disable_totp">
              <button class="btn btn-ghost" type="submit">Disable TOTP for this account</button>
            </form>
            <?php endif; ?>
            <?php endif; ?>
          </div>
        </div>

<?php require __DIR__ . "/panel-footer.php"; ?>
