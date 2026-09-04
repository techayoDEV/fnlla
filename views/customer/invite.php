<?php

declare(strict_types=1);

$customerNotice = is_array($customerNotice ?? null) ? (array) $customerNotice : [];
$invitation = is_array($invitation ?? null) ? (array) $invitation : [];
$account = is_array($invitation["account"] ?? null) ? (array) $invitation["account"] : [];
$projectSettings = is_array($projectSettings ?? null) ? (array) $projectSettings : [];
$projectName = (string) ($projectSettings["name"] ?? config("app.name", "Project"));
$expiresAt = trim((string) ($invitation["expires_at_utc"] ?? ""));
?>

<section class="customer-auth" aria-label="Set customer portal password">
  <div class="customer-auth-card">
    <a class="customer-auth-brand" href="<?= h(route("home")) ?>">
      <span class="project-brand-mark <?= project_brand_logo_asset() !== null ? "is-logo" : "is-initials" ?>" aria-hidden="true">
        <?php if (project_brand_logo_asset() !== null): ?>
        <img src="<?= h((string) project_brand_logo_asset()) ?>" alt="" width="1205" height="1176" decoding="async">
        <?php else: ?>
        <?= h(project_brand_mark()) ?>
        <?php endif; ?>
      </span>
      <span><?= h($projectName) ?></span>
    </a>

    <?php if ($invitation === []): ?>
    <div>
      <p class="feature-kicker">Customer Portal</p>
      <h1>Invitation link is not valid</h1>
      <p class="content-text">Ask the project team for a fresh customer portal invitation.</p>
    </div>
    <a class="btn btn-outline" href="<?= h(route("customer.login")) ?>">Back to customer sign in</a>
    <?php else: ?>
    <div>
      <p class="feature-kicker">Customer Portal</p>
      <h1>Set your customer portal password</h1>
      <p class="content-text">This invitation is for <?= h((string) (($account["name"] ?? "") ?: ($account["email"] ?? "your customer account"))) ?><?= $expiresAt !== "" ? " and expires at " . h($expiresAt) : "" ?>.</p>
    </div>

    <?php if ($customerNotice !== []): ?>
    <div class="form-message form-message-<?= h((string) ($customerNotice["variant"] ?? "info")) ?>" role="status">
      <h2 class="form-message-title"><?= h((string) ($customerNotice["title"] ?? "Customer access")) ?></h2>
      <p class="form-message-text mb-0"><?= h((string) ($customerNotice["text"] ?? "")) ?></p>
    </div>
    <?php endif; ?>

    <form class="form stack gap-md" action="<?= h(route("customer.invite.password")) ?>" method="post" novalidate>
      <?= csrf_field() ?>
      <input type="hidden" name="customer_invite_token" value="<?= h((string) ($token ?? "")) ?>">
      <div class="form-group">
        <label class="label" for="customer-invite-password">Password</label>
        <div class="password-field">
          <input class="input" id="customer-invite-password" name="customer_invite_password" type="password" autocomplete="new-password" required autofocus>
          <button class="password-toggle" type="button" data-fnlla-password-toggle data-fnlla-password-target="#customer-invite-password" aria-label="Toggle password visibility">Show</button>
        </div>
      </div>
      <div class="form-group">
        <label class="label" for="customer-invite-password-confirmation">Confirm password</label>
        <div class="password-field">
          <input class="input" id="customer-invite-password-confirmation" name="customer_invite_password_confirmation" type="password" autocomplete="new-password" required>
          <button class="password-toggle" type="button" data-fnlla-password-toggle data-fnlla-password-target="#customer-invite-password-confirmation" aria-label="Toggle password visibility">Show</button>
        </div>
      </div>
      <button class="btn btn-primary" type="submit">Save password and open portal</button>
    </form>
    <?php endif; ?>
  </div>
</section>
