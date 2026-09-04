<?php

declare(strict_types=1);

$customerAccess = is_array($customerAccess ?? null) ? (array) $customerAccess : [];
$customerNotice = is_array($customerNotice ?? null) ? (array) $customerNotice : [];
$projectSettings = is_array($projectSettings ?? null) ? (array) $projectSettings : [];
$projectName = (string) ($projectSettings["name"] ?? config("app.name", "Project"));
?>

<section class="customer-auth" aria-label="Customer portal sign in">
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
    <div>
      <p class="feature-kicker">Customer Portal</p>
      <h1>Sign in to review project progress</h1>
      <p class="content-text">Use the customer account created by the lead developer. First-time users should open the invitation link and set a password first.</p>
    </div>

    <?php if ($customerNotice !== []): ?>
    <div class="form-message form-message-<?= h((string) ($customerNotice["variant"] ?? "info")) ?>" role="status">
      <h2 class="form-message-title"><?= h((string) ($customerNotice["title"] ?? "Customer access")) ?></h2>
      <p class="form-message-text mb-0"><?= h((string) ($customerNotice["text"] ?? "")) ?></p>
    </div>
    <?php endif; ?>

    <form class="form stack gap-md" action="<?= h(route("customer.login.unlock")) ?>" method="post" novalidate>
      <?= csrf_field() ?>
      <div class="form-group">
        <label class="label" for="customer-access-email">Email</label>
        <input class="input" id="customer-access-email" name="customer_access_email" type="email" autocomplete="username" required autofocus>
      </div>
      <div class="form-group">
        <label class="label" for="customer-access-password">Password</label>
        <div class="password-field">
          <input class="input" id="customer-access-password" name="customer_access_password" type="password" autocomplete="current-password" required>
          <button class="password-toggle" type="button" data-fnlla-password-toggle data-fnlla-password-target="#customer-access-password" aria-label="Toggle password visibility">Show</button>
        </div>
      </div>
      <button class="btn btn-primary" type="submit">Open customer portal</button>
    </form>
  </div>
</section>
