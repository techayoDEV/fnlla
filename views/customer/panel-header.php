<?php

declare(strict_types=1);

$customerAccess ??= [
    "configured" => false,
    "path" => "/client",
    "unlocked" => false,
    "current_customer" => [
        "email" => "",
        "name" => "Customer",
        "company" => "",
        "permissions" => ["kanban", "analytics", "heatmap", "preview"],
    ],
];
$customerLinks ??= [];
$customerPanelActive ??= "overview";
$customerPanelTitle ??= (string) ($pageTitle ?? "Customer Portal");
$customerPanelLead ??= "Read-only project visibility for the customer team.";
$customerNotice ??= null;
$projectSettings = is_array($projectSettings ?? null) ? (array) $projectSettings : [];
$currentCustomer = is_array($customerAccess["current_customer"] ?? null) ? (array) $customerAccess["current_customer"] : [];
$customerPermissions = array_values((array) ($currentCustomer["permissions"] ?? []));
$canSee = static fn (string $permission): bool => in_array($permission, $customerPermissions, true);
$customerDisplayName = trim((string) ($currentCustomer["name"] ?? "Customer"));
$customerCompany = trim((string) ($currentCustomer["company"] ?? ""));
$customerEmail = strtolower(trim((string) ($currentCustomer["email"] ?? "")));
$customerInitialSource = preg_replace('/[^A-Za-z0-9]/', '', $customerDisplayName) ?: "CU";
$customerInitials = strtoupper(substr((string) $customerInitialSource, 0, 2));
$projectBrandLogo = project_brand_logo_asset();
$navigation = [
    "overview" => ["label" => "Overview", "href" => (string) ($customerLinks["overview"] ?? route("customer.panel")), "visible" => true],
    "kanban" => ["label" => "Project Kanban", "href" => (string) ($customerLinks["kanban"] ?? route("customer.panel.kanban")), "visible" => $canSee("kanban")],
    "analytics" => ["label" => "Analytics", "href" => (string) ($customerLinks["analytics"] ?? route("customer.panel.analytics")), "visible" => $canSee("analytics")],
    "heatmap" => ["label" => "Heatmap", "href" => (string) ($customerLinks["heatmap"] ?? route("customer.panel.heatmap")), "visible" => $canSee("heatmap")],
];
?>
<section class="customer-portal" aria-label="Customer portal">
  <header class="customer-portal-header">
    <a class="customer-portal-brand" href="<?= h((string) ($customerLinks["overview"] ?? route("customer.panel"))) ?>">
      <span class="project-brand-mark <?= $projectBrandLogo !== null ? "is-logo" : "is-initials" ?>" aria-hidden="true">
        <?php if ($projectBrandLogo !== null): ?>
        <img src="<?= h($projectBrandLogo) ?>" alt="" width="1205" height="1176" decoding="async">
        <?php else: ?>
        <?= h(project_brand_mark()) ?>
        <?php endif; ?>
      </span>
      <span><?= h((string) ($projectSettings["name"] ?? config("app.name", "Project"))) ?></span>
    </a>
    <nav class="customer-portal-nav" aria-label="Customer portal navigation">
      <?php foreach ($navigation as $key => $item): ?>
      <?php if (($item["visible"] ?? false) === true): ?>
      <a href="<?= h((string) ($item["href"] ?? "#")) ?>" <?= $customerPanelActive === $key ? 'aria-current="page"' : "" ?>><?= h((string) ($item["label"] ?? $key)) ?></a>
      <?php endif; ?>
      <?php endforeach; ?>
      <?php if ($canSee("preview")): ?>
      <a href="<?= h((string) ($projectSettings["preview_url"] ?? route("home"))) ?>" target="_blank" rel="noopener noreferrer">Preview</a>
      <?php endif; ?>
    </nav>
    <div class="customer-portal-actions">
      <span class="customer-portal-avatar" aria-hidden="true"><?= h($customerInitials) ?></span>
      <span class="customer-portal-identity">
        <strong><?= h($customerDisplayName !== "" ? $customerDisplayName : "Customer") ?></strong>
        <small><?= h($customerCompany !== "" ? $customerCompany : $customerEmail) ?></small>
      </span>
      <form action="<?= h((string) ($customerLinks["lock"] ?? route("customer.lock"))) ?>" method="post">
        <?= csrf_field() ?>
        <button class="btn btn-outline btn-sm" type="submit">Lock</button>
      </form>
    </div>
  </header>

  <?php if (is_array($customerNotice)): ?>
  <div class="customer-portal-notice customer-portal-notice-<?= h((string) ($customerNotice["variant"] ?? "info")) ?>" role="status">
    <strong><?= h((string) ($customerNotice["title"] ?? "Customer portal")) ?></strong>
    <span><?= h((string) ($customerNotice["text"] ?? "")) ?></span>
  </div>
  <?php endif; ?>

  <main class="customer-portal-main">
    <section class="customer-portal-title">
      <p class="feature-kicker">Customer Portal</p>
      <h1><?= h($customerPanelTitle) ?></h1>
      <p><?= h($customerPanelLead) ?></p>
    </section>
