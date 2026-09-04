<?php

declare(strict_types=1);

$developerAccess ??= [
    "configured" => false,
    "path" => "",
    "unlocked" => false,
    "unlock_ttl_minutes" => 120,
    "email_required" => true,
];

$developerNotice ??= null;
$developerTotpRequired = (bool) ($developerTotpRequired ?? false);
$emailRequired = true;
?>
<section class="section pt-1 developer-surface developer-entry-surface">
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

    <section class="feature-section" aria-label="Developer access unlock">
      <div class="grid gap-md site-login-grid">
        <article class="feature-card">
          <p class="feature-kicker">Developer session</p>
          <h1 class="section-title mb-0">Developer tools stay locked until this browser session is explicitly unlocked.</h1>
          <p class="content-text">This private address is only the login entry. The actual developer panel remains unavailable until the correct developer email and password open a developer session for <?= h((string) ($developerAccess["unlock_ttl_minutes"] ?? 120)) ?> minutes.</p>
          <ul class="project-note-list">
            <li>The public project header stays plain for the client.</li>
            <li>The developer panel itself only opens after a successful unlock.</li>
            <li>The private path and password can still be rotated later from inside the panel.</li>
          </ul>
        </article>
        <article class="feature-card developer-login-card">
          <p class="feature-kicker">Developer login</p>
          <h2 class="content-title">Unlock developer session</h2>
          <form class="form stack gap-md" action="<?= h(route("developer.login.unlock")) ?>" method="post" novalidate>
            <?= csrf_field() ?>
            <div class="form-group">
              <label class="label" for="developer-access-email">Email</label>
              <input class="input" id="developer-access-email" name="developer_access_email" type="email" autocomplete="username" value="<?= h((string) old("developer_access_email")) ?>" required>
            </div>
            <div class="form-group">
              <label class="label" for="developer-access-password">Password</label>
              <div class="password-field">
                <input class="input" id="developer-access-password" name="developer_access_password" type="password" autocomplete="current-password" required>
                <button class="password-toggle" type="button" data-fnlla-password-toggle data-fnlla-password-target="#developer-access-password" aria-label="Toggle password visibility">Show</button>
              </div>
            </div>
            <?php if ($developerTotpRequired): ?>
              <div class="form-group">
                <label class="label" for="developer-access-totp">Authenticator code</label>
                <input class="input" id="developer-access-totp" name="developer_access_totp" type="text" inputmode="numeric" autocomplete="one-time-code" maxlength="12" placeholder="Six-digit code" required autofocus>
                <p class="help-text">This developer account has two-factor authentication enabled.</p>
              </div>
            <?php endif; ?>
            <div class="d-flex flex-wrap gap-md">
              <button class="btn btn-primary" type="submit">Unlock developer panel</button>
            </div>
          </form>
        </article>
      </div>
    </section>
  </div>
</section>
