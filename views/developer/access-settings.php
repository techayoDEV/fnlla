<?php

declare(strict_types=1);

$developerPanelTitle = "Access & Security";
$developerPanelLead = "Named developer accounts, roles, sessions, 2FA and passkey-adapter readiness.";
$developerAccounts = is_array($developerAccess["accounts"] ?? null) ? (array) $developerAccess["accounts"] : [];
$currentDeveloper = is_array($developerAccess["current_developer"] ?? null) ? (array) $developerAccess["current_developer"] : [];
$developerRoleOptions = is_array($developerAccess["role_options"] ?? null) ? (array) $developerAccess["role_options"] : [];
$currentEmail = strtolower(trim((string) ($currentDeveloper["email"] ?? "")));
$currentCapabilities = is_array($developerAccess["current_capabilities"] ?? null) ? (array) $developerAccess["current_capabilities"] : [];
$canManageDeveloperAccounts = in_array("developer.accounts.write", $currentCapabilities, true);
$security = is_array($developerAccess["security"] ?? null) ? (array) $developerAccess["security"] : [];
$hasNamedAccount = trim((string) ($currentDeveloper["email"] ?? "")) !== "";
$leadCount = 0;
$totpCount = 0;
$passkeyCount = 0;
foreach ($developerAccounts as $account) {
    if (($account["role"] ?? "") === "lead_developer") {
        $leadCount++;
    }
    if (($account["security"]["totp_enabled"] ?? false) === true) {
        $totpCount++;
    }
    if (($account["security"]["passkey_ready"] ?? false) === true) {
        $passkeyCount++;
    }
}
require __DIR__ . "/panel-header.php";
?>

        <section class="developer-dashboard-section" id="developer-access-settings" aria-label="Developer access settings">
          <div class="developer-panel-intro">
            <div class="developer-panel-intro-copy">
              <p class="feature-kicker">Access governance</p>
              <h2 class="developer-dashboard-section-title">Named developers, lead ownership and personal security live here.</h2>
              <p class="content-text mb-0">Project changes remain global, but every session should be attributable to one developer account. Lead developer accounts can add, rotate or deactivate other developers.</p>
            </div>
            <div class="developer-panel-intro-actions">
              <a class="btn btn-outline btn-sm" href="<?= h((string) ($developerLinks["profile"] ?? route("developer.panel.profile"))) ?>">My profile</a>
              <?php if ($canManageDeveloperAccounts): ?>
              <button class="btn btn-primary btn-sm" type="button" data-fnlla-modal-open="#developer-account-modal">Add developer</button>
              <?php endif; ?>
            </div>
          </div>

          <div class="developer-dashboard-status-grid">
            <article class="developer-dashboard-status-card">
              <div class="developer-dashboard-card-head"><strong>Developers</strong><span class="developer-dashboard-ok"><?= h((string) count($developerAccounts)) ?></span></div>
              <h3><?= h((string) count($developerAccounts)) ?> named <?= count($developerAccounts) === 1 ? "account" : "accounts" ?></h3>
              <p>Unique credentials keep audit events attributable.</p>
            </article>
            <article class="developer-dashboard-status-card">
              <div class="developer-dashboard-card-head"><strong>Lead ownership</strong><span class="developer-dashboard-ok"><?= h((string) $leadCount) ?></span></div>
              <h3><?= h((string) max(1, $leadCount)) ?> lead developer</h3>
              <p>Only the lead manages other developer accounts.</p>
            </article>
            <article class="developer-dashboard-status-card">
              <div class="developer-dashboard-card-head"><strong>2FA adoption</strong><span class="developer-dashboard-ok"><?= h((string) $totpCount) ?></span></div>
              <h3><?= h((string) $totpCount) ?> accounts protected</h3>
              <p>Authenticator setup is per named account.</p>
            </article>
            <article class="developer-dashboard-status-card">
              <div class="developer-dashboard-card-head"><strong>Passkey contract</strong><span class="developer-dashboard-ok"><?= ($security["passkey_ready"] ?? false) ? "READY" : "OFF" ?></span></div>
              <h3><?= h((string) $passkeyCount) ?> ready</h3>
              <p>Passkeys remain an external adapter contract.</p>
            </article>
          </div>

          <div class="developer-account-table" aria-label="Developer account list">
            <?php foreach ($developerAccounts as $account): ?>
            <?php
                $accountEmail = strtolower(trim((string) ($account["email"] ?? "")));
                $accountName = trim((string) ($account["name"] ?? $accountEmail ?: "Default developer"));
                $accountAvatar = trim((string) ($account["avatar"] ?? ""));
                $accountAvatarIsUrl = $accountAvatar !== "" && (filter_var($accountAvatar, FILTER_VALIDATE_URL) !== false || str_starts_with($accountAvatar, "/uploads/developer-avatars/"));
                $accountMarkSource = preg_replace('/[^A-Za-z0-9]/', '', $accountName) ?: "D";
                $accountMark = $accountAvatar !== "" && !$accountAvatarIsUrl ? strtoupper(substr($accountAvatar, 0, 2)) : strtoupper(substr((string) $accountMarkSource, 0, 2));
            ?>
            <article class="developer-account-row">
              <span class="developer-profile-avatar" aria-hidden="true">
                <?php if ($accountAvatarIsUrl): ?>
                <img src="<?= h($accountAvatar) ?>" alt="">
                <?php else: ?>
                <?= h($accountMark) ?>
                <?php endif; ?>
              </span>
              <div class="developer-account-copy">
                <strong><?= h($accountName) ?><?= $accountEmail === $currentEmail ? " (you)" : "" ?></strong>
                <span><?= h($accountEmail !== "" ? $accountEmail : "Default password account") ?></span>
              </div>
              <div class="developer-account-meta">
                <?= h((string) ($account["role_label"] ?? $account["role"] ?? "Developer")) ?><br>
                <?= (($account["security"]["totp_enabled"] ?? false) ? "2FA on" : "2FA off") ?> · <?= (($account["security"]["passkey_ready"] ?? false) ? "passkey ready" : "no passkey") ?>
              </div>
              <?php if ($canManageDeveloperAccounts && $accountEmail !== "" && $accountEmail !== $currentEmail && count($developerAccounts) > 1): ?>
              <form action="<?= h(route("developer.settings.developer_account.delete")) ?>" method="post">
                <?= csrf_field() ?>
                <input type="hidden" name="developer_account_email" value="<?= h($accountEmail) ?>">
                <button class="btn btn-ghost btn-sm" type="submit">Deactivate</button>
              </form>
              <?php else: ?>
              <span class="developer-dashboard-status is-neutral"><?= $accountEmail === $currentEmail ? "Current" : "Protected" ?></span>
              <?php endif; ?>
            </article>
            <?php endforeach; ?>
          </div>

          <?php if (!$canManageDeveloperAccounts): ?>
          <div class="developer-panel-status-note">
            <strong>Account management is lead-only.</strong>
            <span>Your role can use the workspace and personal security settings, but only a Lead developer can add, rotate or deactivate developer accounts.</span>
          </div>
          <?php endif; ?>
        </section>

        <section class="developer-dashboard-section" id="developer-security" aria-label="Developer security">
          <div class="developer-panel-form-grid">
            <article class="developer-panel-fieldset-card">
              <p class="feature-kicker">Two-factor authentication</p>
              <h2 class="content-title"><?= ($security["totp_enabled"] ?? false) ? "TOTP is active" : "TOTP is not active" ?></h2>
              <p class="content-text">Authenticator-based 2FA protects the private Developer Panel after the account password is accepted.</p>
              <div class="developer-dashboard-glance-table">
                <div class="developer-dashboard-glance-row"><strong>Status</strong><span><?= ($security["totp_enabled"] ?? false) ? "Enabled" : (($security["totp_configured"] ?? false) ? "Configured, not enforced" : "Not configured") ?></span></div>
                <div class="developer-dashboard-glance-row"><strong>Passkey adapter</strong><span><?= h((string) (($security["passkey_ready"] ?? false) ? ($security["passkey_label"] ?? "External") : "Disabled")) ?></span></div>
              </div>
            </article>

            <article class="developer-panel-fieldset-card">
              <p class="feature-kicker">Authenticator setup</p>
              <h2 class="content-title">Generate and confirm TOTP</h2>
              <?php if (!$hasNamedAccount): ?>
              <p class="content-text">Create a named developer account before enabling two-factor authentication.</p>
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
                <p class="form-message-text mb-0">Add this URI to a TOTP authenticator, then confirm the current six-digit code below.</p>
              </div>
              <form class="form stack gap-md" action="<?= h(route("developer.security.save")) ?>" method="post">
                <?= csrf_field() ?>
                <input type="hidden" name="developer_security_action" value="enable_totp">
                <div class="form-group">
                  <label class="label" for="developer-totp-code">Current authenticator code</label>
                  <input class="input" id="developer-totp-code" name="developer_totp_code" type="text" inputmode="numeric" maxlength="12" autocomplete="one-time-code" required>
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
            </article>

            <article class="developer-panel-fieldset-card developer-panel-card-wide">
              <p class="feature-kicker">Passkey contract</p>
              <h2 class="content-title">Register external passkey readiness</h2>
              <p class="content-text">FNLLA keeps passkeys as an adapter contract. The real WebAuthn provider, key storage and attestation policy belong to the project or hosted control plane.</p>
              <form class="form developer-modal-form-grid" action="<?= h(route("developer.security.save")) ?>" method="post">
                <?= csrf_field() ?>
                <input type="hidden" name="developer_security_action" value="passkey_contract">
                <div class="form-group">
                  <label class="label" for="developer-passkey-adapter">Passkey adapter</label>
                  <select class="select" id="developer-passkey-adapter" name="developer_passkey_adapter">
                    <option value="disabled" <?= ($security["passkey_adapter"] ?? "disabled") === "disabled" ? "selected" : "" ?>>Disabled</option>
                    <option value="external" <?= ($security["passkey_adapter"] ?? "disabled") === "external" ? "selected" : "" ?>>External adapter</option>
                  </select>
                </div>
                <div class="form-group">
                  <label class="label" for="developer-passkey-label">Passkey label</label>
                  <input class="input" id="developer-passkey-label" name="developer_passkey_label" type="text" maxlength="100" value="<?= h((string) ($security["passkey_label"] ?? "")) ?>" placeholder="Platform passkey, YubiKey, managed WebAuthn">
                </div>
                <div class="developer-modal-form-wide">
                  <button class="btn btn-primary" type="submit">Save passkey contract</button>
                </div>
              </form>
            </article>
          </div>
        </section>

        <?php if ($canManageDeveloperAccounts): ?>
        <div class="modal developer-kanban-modal" id="developer-account-modal" data-fnlla-modal role="dialog" aria-modal="true" aria-labelledby="developer-account-modal-title" hidden>
          <div class="developer-kanban-modal-backdrop" data-fnlla-modal-close></div>
          <div class="developer-kanban-modal-panel" role="document">
            <div class="developer-kanban-modal-head">
              <div>
                <p class="feature-kicker">Developer account</p>
                <h2 class="content-title mb-0" id="developer-account-modal-title">Add or rotate a named account</h2>
              </div>
              <button class="developer-kanban-modal-close" type="button" data-fnlla-modal-close aria-label="Close developer account modal">Close</button>
            </div>
            <form class="form developer-modal-form-grid" action="<?= h(route("developer.settings.developer_account")) ?>" method="post" novalidate>
              <?= csrf_field() ?>
              <div class="form-group">
                <label class="label" for="developer-account-email">Email</label>
                <input class="input" id="developer-account-email" name="developer_account_email" type="email" autocomplete="username" value="<?= h((string) old("developer_account_email")) ?>" required data-fnlla-modal-initial-focus>
              </div>
              <div class="form-group">
                <label class="label" for="developer-account-name">Name</label>
                <input class="input" id="developer-account-name" name="developer_account_name" type="text" autocomplete="name" value="<?= h((string) old("developer_account_name", "Developer")) ?>" required>
              </div>
              <div class="form-group developer-modal-form-wide">
                <label class="label" for="developer-account-role">Role</label>
                <select class="select" id="developer-account-role" name="developer_account_role">
                  <?php foreach ($developerRoleOptions as $role => $label): ?>
                  <option value="<?= h((string) $role) ?>" <?= old("developer_account_role", "application_developer") === $role ? "selected" : "" ?>><?= h((string) $label) ?></option>
                  <?php endforeach; ?>
                </select>
                <p class="help-text">Choose Lead developer only when transferring account-management ownership.</p>
              </div>
              <div class="form-group">
                <label class="label" for="developer-account-password">Password</label>
                <div class="password-field">
                  <input class="input" id="developer-account-password" name="developer_account_password" type="password" autocomplete="new-password" required>
                  <button class="password-toggle" type="button" data-fnlla-password-toggle data-fnlla-password-target="#developer-account-password" aria-label="Toggle password visibility">Show</button>
                </div>
              </div>
              <div class="form-group">
                <label class="label" for="developer-account-password-confirmation">Confirm password</label>
                <div class="password-field">
                  <input class="input" id="developer-account-password-confirmation" name="developer_account_password_confirmation" type="password" autocomplete="new-password" required>
                  <button class="password-toggle" type="button" data-fnlla-password-toggle data-fnlla-password-target="#developer-account-password-confirmation" aria-label="Toggle password visibility">Show</button>
                </div>
              </div>
              <div class="developer-modal-form-wide developer-inline-actions">
                <button class="btn btn-primary" type="submit">Save developer account</button>
                <button class="btn btn-ghost" type="button" data-fnlla-modal-close>Cancel</button>
              </div>
            </form>
          </div>
        </div>
        <?php endif; ?>

<?php require __DIR__ . "/panel-footer.php"; ?>
