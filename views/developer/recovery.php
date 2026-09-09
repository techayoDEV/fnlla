<?php
declare(strict_types=1);
$titles = ["request" => "Forgot password?", "sent" => "Check your inbox", "reset" => "Choose a new password", "invalid" => "This link is unavailable", "complete" => "Password updated"];
?>
<section class="developer-sign-in" aria-labelledby="developer-sign-in-title">
  <?php require __DIR__ . "/access-hero.php"; ?>
  <div class="developer-sign-in-main"><div class="developer-sign-in-inner">
    <a class="developer-sign-in-brand" href="<?= h(route("home")) ?>">
      <span><?= h((string) config("app.name", "FNLLA")) ?></span>
    </a>
    <h1 id="developer-sign-in-title"><?= h($titles[$step]) ?></h1>
    <?php if ($recoveryError !== ""): ?><p class="alert alert-warning" role="alert"><?= h($recoveryError) ?></p><?php endif; ?>
    <?php if ($step === "request"): ?>
      <p class="developer-sign-in-intro">Enter the email address for your developer account.</p>
      <form class="form stack gap-md" action="<?= h(route("developer.password.email")) ?>" method="post">
        <?= csrf_field() ?>
        <div class="form-group"><label class="label" for="recovery-email">Email</label><input class="input" id="recovery-email" name="email" type="email" autocomplete="username" maxlength="160" required></div>
        <button class="btn btn-primary" type="submit">Send reset link</button>
      </form>
    <?php elseif ($step === "reset"): ?>
      <p class="developer-sign-in-intro">Your authenticator settings will stay unchanged.</p>
      <form class="form stack gap-md" action="<?= h(route("developer.password.update")) ?>" method="post">
        <?= csrf_field() ?>
        <div class="form-group">
          <label class="label" for="recovery-password">New password</label>
          <div class="password-field">
            <input class="input" id="recovery-password" name="password" type="password" autocomplete="new-password" minlength="12" maxlength="72" aria-describedby="recovery-password-help" required>
            <button class="password-toggle developer-password-toggle" type="button" data-developer-password-toggle="recovery-password" aria-label="Show password" aria-pressed="false" title="Show password"><svg width="20" height="20" aria-hidden="true"><use href="<?= h(asset("vendor/fnlla-runtime/assets/icons/sprite.svg")) ?>#eye"></use></svg></button>
          </div>
          <p class="help-text" id="recovery-password-help">At least 12 characters. Up to 72 bytes; some characters use more than one byte.</p>
        </div>
        <div class="form-group"><label class="label" for="recovery-confirmation">Confirm password</label><input class="input" id="recovery-confirmation" name="password_confirmation" type="password" autocomplete="new-password" minlength="12" maxlength="72" required></div>
        <button class="btn btn-primary" type="submit">Save new password</button>
      </form>
    <?php elseif ($step === "sent"): ?>
      <p class="developer-sign-in-intro" role="status">If an account matches that address, a reset link will be sent. Check your inbox and spam folder. If no email arrives, contact the project owner.</p>
    <?php elseif ($step === "complete"): ?>
      <p class="developer-sign-in-intro" role="status">Sign in with your new password. Existing developer sessions have been signed out.</p>
      <a class="btn btn-primary" href="<?= h(route("developer.login")) ?>">Back to sign in</a>
    <?php else: ?>
      <p class="developer-sign-in-intro">The link is invalid, expired or already used.</p>
      <a class="btn btn-primary" href="<?= h(route("developer.password.forgot")) ?>">Request a new link</a>
    <?php endif; ?>
    <?php if ($step !== "complete"): ?><a class="developer-sign-in-back" href="<?= h(route("developer.login")) ?>">Back to sign in</a><?php endif; ?>
  </div></div>
</section>
