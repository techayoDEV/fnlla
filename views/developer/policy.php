<?php

declare(strict_types=1);

$developerPanelTitle = "Policy Boundary";
$developerPanelLead = "The line between FNLLA's framework-managed operations layer and the product application built above it.";
$policy = is_array($developerPolicy ?? null) ? $developerPolicy : [];
$boundary = (array) ($policy["boundary"] ?? []);
$roles = (array) ($policy["roles"] ?? []);
$capabilities = (array) ($policy["capabilities"] ?? []);
$storage = (array) ($policy["storage"] ?? []);
$sections = [
    "fnlla_managed" => [
        "title" => "FNLLA-managed",
        "label" => "Framework layer",
    ],
    "project_owned" => [
        "title" => "Project-owned",
        "label" => "Application layer",
    ],
    "forbidden_in_fnlla_core" => [
        "title" => "Never in FNLLA core",
        "label" => "Boundary guard",
    ],
];
$capabilityCount = count($capabilities);
$roleCount = count($roles);

require __DIR__ . "/panel-header.php";
?>

        <section class="developer-dashboard-section" aria-label="Framework and project boundary">
          <div class="developer-panel-intro">
            <div class="developer-panel-intro-copy">
              <p class="feature-kicker">Policy boundary</p>
              <h2 class="developer-dashboard-section-title">Framework boundary</h2>
              <p class="content-text mb-0">This separates FNLLA-managed operations from project-owned application behavior, so the framework remains portable across client products.</p>
            </div>
            <div class="developer-panel-intro-actions">
              <span class="developer-dashboard-status is-active">Policy contract active</span>
              <span class="developer-dashboard-status">Portable framework</span>
            </div>
          </div>

          <div class="developer-dashboard-status-grid">
            <article class="developer-dashboard-status-card">
              <div class="developer-dashboard-card-head"><strong>Boundary model</strong><span class="developer-dashboard-ok">ACTIVE</span></div>
              <h3><?= h((string) count($sections)) ?> zones</h3>
              <p>Framework, project and forbidden-core areas.</p>
            </article>
            <article class="developer-dashboard-status-card">
              <div class="developer-dashboard-card-head"><strong>Roles</strong><span class="developer-dashboard-ok"><?= h((string) $roleCount) ?></span></div>
              <h3><?= h((string) $roleCount) ?> developer roles</h3>
              <p>Technical permissions only, not client business roles.</p>
            </article>
            <article class="developer-dashboard-status-card">
              <div class="developer-dashboard-card-head"><strong>Capabilities</strong><span class="developer-dashboard-ok"><?= h((string) $capabilityCount) ?></span></div>
              <h3><?= h((string) $capabilityCount) ?> actions</h3>
              <p>Stable developer-panel permission catalog.</p>
            </article>
            <article class="developer-dashboard-status-card">
              <div class="developer-dashboard-card-head"><strong>Storage</strong><span class="developer-dashboard-ok">PORTABLE</span></div>
              <h3>Project-safe</h3>
              <p>Storage policy is documented without product-specific data.</p>
            </article>
          </div>

          <div class="developer-policy-map">
            <?php foreach ($sections as $key => $section): ?>
            <article class="developer-policy-zone is-<?= h((string) str_replace("_", "-", $key)) ?>">
              <div>
                <p class="feature-kicker"><?= h($section["label"]) ?></p>
                <h3><?= h($section["title"]) ?></h3>
              </div>
              <ul>
                <?php foreach ((array) ($boundary[$key] ?? []) as $item): ?>
                <li><?= h((string) $item) ?></li>
                <?php endforeach; ?>
              </ul>
            </article>
            <?php endforeach; ?>
          </div>
        </section>

        <section class="developer-dashboard-section" aria-label="Developer role capabilities">
          <div class="developer-dashboard-section-head">
            <h2 class="developer-dashboard-section-title">Developer role capabilities <span class="developer-info-tip" tabindex="0" aria-label="These are framework technical permissions, not client business roles.">i<span>These roles control private Developer Panel actions. They are not customer-facing business roles.</span></span></h2>
            <span class="developer-dashboard-refresh">Technical permissions only</span>
          </div>
          <div class="developer-policy-role-grid">
            <?php foreach ($roles as $role): ?>
            <article class="developer-dashboard-card">
              <p class="feature-kicker"><?= h((string) ($role["role"] ?? "developer")) ?></p>
              <h3><?= h((string) ($role["label"] ?? $role["role"] ?? "Developer")) ?></h3>
              <div class="developer-policy-capability-chips">
                <?php foreach ((array) ($role["capabilities"] ?? []) as $capability): ?>
                <span><?= h((string) $capability) ?></span>
                <?php endforeach; ?>
              </div>
            </article>
            <?php endforeach; ?>
          </div>
        </section>

        <section class="developer-dashboard-section" aria-label="Capability definitions and storage policy">
          <div class="developer-dashboard-overview-grid">
            <article class="developer-dashboard-card">
              <p class="feature-kicker">Capability catalog</p>
              <h3>Stable developer-panel actions</h3>
              <div class="developer-dashboard-glance-table">
                <?php foreach ($capabilities as $capability => $description): ?>
                <div class="developer-dashboard-glance-row">
                  <strong><?= h((string) $capability) ?></strong>
                  <span><?= h((string) $description) ?></span>
                </div>
                <?php endforeach; ?>
              </div>
            </article>

            <article class="developer-dashboard-card">
              <p class="feature-kicker">Storage contract</p>
              <h3>Portable by default</h3>
              <p class="content-text"><?= h((string) ($storage["default"] ?? "")) ?></p>
              <p class="content-text"><?= h((string) ($storage["production_note"] ?? "")) ?></p>
              <div class="developer-dashboard-glance-table">
                <div class="developer-dashboard-glance-row">
                  <strong>Contract label</strong>
                  <span>Policy contract active</span>
                </div>
                <div class="developer-dashboard-glance-row">
                  <strong>Technical schema</strong>
                  <span><code><?= h((string) ($policy["schema"] ?? "fnlla.developer_policy_boundary.v1")) ?></code></span>
                </div>
              </div>
            </article>
          </div>
        </section>

<?php require __DIR__ . "/panel-footer.php"; ?>
