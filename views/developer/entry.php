<?php

declare(strict_types=1);

$developerNotice ??= null;
$developerTotpRequired = (bool) ($developerTotpRequired ?? false);
?>
<section class="developer-sign-in" aria-labelledby="developer-sign-in-title">
  <?php require __DIR__ . "/access-hero.php"; ?>
  <div class="developer-sign-in-main">
  <div class="developer-sign-in-inner">
    <a class="developer-sign-in-brand" href="<?= h(route("home")) ?>">
      <span><?= h((string) config("app.name", "FNLLA")) ?></span>
    </a>
    <h1 id="developer-sign-in-title">Unlock developer session</h1>
    <p class="developer-sign-in-intro">Sign in to your developer account.</p>
    <?php if (is_array($developerNotice) && isset($developerNotice["path"], $developerNotice["title"], $developerNotice["text"])): ?>
      <div class="alert alert-info" role="status">
        <strong><?= h((string) $developerNotice["title"]) ?></strong>
        <p><?= h((string) $developerNotice["text"]) ?></p>
        <code><?= h((string) $developerNotice["path"]) ?></code>
      </div>
    <?php endif; ?>
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
          <button class="password-toggle developer-password-toggle" type="button" data-developer-password-toggle="developer-access-password" aria-label="Show password" aria-pressed="false" title="Show password"><svg width="20" height="20" aria-hidden="true"><use href="<?= h(asset("vendor/fnlla-runtime/assets/icons/sprite.svg")) ?>#eye"></use></svg></button>
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
      <?php if ((bool) config("developer_access.recovery_enabled", true)): ?>
        <a class="developer-sign-in-recovery" href="<?= h(route("developer.password.forgot")) ?>">Forgot password?</a>
      <?php endif; ?>
    </form>
    <a class="developer-sign-in-back" href="<?= h(route("home")) ?>">Back to project</a>
  </div>
  </div>
</section>
