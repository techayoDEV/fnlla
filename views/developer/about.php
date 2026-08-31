<?php

declare(strict_types=1);

$developerPanelTitle = "About FNLLA";
$developerPanelLead = "Framework identity, runtime version and maintainer information for this project installation.";
$about = is_array($aboutFnlla ?? null) ? (array) $aboutFnlla : [];
$facts = [
    "Application name" => (string) ($about["app_name"] ?? config("app.name", "FNLLA")),
    "Framework version" => (string) ($about["framework_version"] ?? config("app.framework_version", "unknown")),
    "Runtime version" => (string) ($about["runtime_version"] ?? config("fnlla_runtime.version", "unknown")),
    "Environment" => (string) ($about["environment"] ?? app_environment()),
    "Maintainer" => (string) ($about["maintainer"] ?? "TechAyo Limited"),
    "License" => (string) ($about["license"] ?? "MIT"),
];
$projectLeadership = project_leadership("admin");
$principles = [
    ["title" => "Portable framework layer", "text" => "FNLLA keeps reusable runtime, maintenance, developer access and release readiness behavior separate from product-specific application code."],
    ["title" => "Private Developer Panel", "text" => "The panel is an operational workspace for trusted developers: identity, access, preview, analytics, heatmap, readiness, integrations, updates and policy controls."],
    ["title" => "First-party observability", "text" => "The built-in analytics and heatmap modules provide local aggregate insight so projects can run without mandatory Google Analytics or Microsoft Clarity accounts."],
];

require __DIR__ . "/panel-header.php";
?>

        <section class="developer-dashboard-section" aria-label="About FNLLA">
          <div class="developer-panel-intro">
            <div class="developer-panel-intro-copy">
              <p class="feature-kicker">Framework information</p>
              <h2 class="developer-dashboard-section-title">FNLLA by TechAyo Limited</h2>
              <p class="content-text mb-0">FNLLA is the framework-managed base used to deliver the public project surface and private developer operations panel.</p>
            </div>
            <div class="developer-panel-intro-actions">
              <a class="btn btn-outline btn-sm" href="https://techayo.co.uk" target="_blank" rel="noopener noreferrer">TechAyo</a>
              <a class="btn btn-outline btn-sm" href="<?= h((string) ($developerLinks["documentation"] ?? route("developer.panel.documentation"))) ?>">Documentation</a>
            </div>
          </div>

          <div class="developer-dashboard-status-grid">
            <article class="developer-dashboard-status-card">
              <div class="developer-dashboard-card-head"><strong>Framework</strong><span class="developer-dashboard-ok">FNLLA</span></div>
              <h3><?= h((string) ($facts["Framework version"] ?: "unknown")) ?></h3>
              <p>Current framework version reported by this installation.</p>
            </article>
            <article class="developer-dashboard-status-card">
              <div class="developer-dashboard-card-head"><strong>Runtime</strong><span class="developer-dashboard-ok">PHP</span></div>
              <h3><?= h((string) ($facts["Runtime version"] ?: "unknown")) ?></h3>
              <p>Runtime package version available to the project.</p>
            </article>
            <article class="developer-dashboard-status-card">
              <div class="developer-dashboard-card-head"><strong>Environment</strong><span class="developer-dashboard-ok"><?= h(strtoupper((string) $facts["Environment"])) ?></span></div>
              <h3><?= h((string) $facts["Application name"]) ?></h3>
              <p>Application identity loaded from project configuration.</p>
            </article>
            <article class="developer-dashboard-status-card">
              <div class="developer-dashboard-card-head"><strong>Maintainer</strong><span class="developer-dashboard-ok">MIT</span></div>
              <h3><a class="developer-text-link" href="https://techayo.co.uk" target="_blank" rel="noopener noreferrer"><?= h((string) $facts["Maintainer"]) ?></a></h3>
              <p>Maintained and distributed under the configured license.</p>
            </article>
          </div>
        </section>

        <section class="developer-dashboard-section" aria-label="System information">
          <div class="developer-dashboard-section-head">
            <h2 class="developer-dashboard-section-title">System information</h2>
            <span class="developer-dashboard-refresh">Responsibility record</span>
          </div>
          <?php
          $projectLeadershipContext = "admin";
          require VIEW_ROOT . "/partials/project-leadership.php";
          ?>
          <?php if (($projectLeadership["configured"] ?? false) !== true): ?>
          <article class="developer-dashboard-card developer-dashboard-card-wide">
            <p class="feature-kicker">System information</p>
            <h3>No named leadership record</h3>
            <p class="content-text mb-0">Add one from Project identity when the project needs a named person responsible for product direction, delivery or technical leadership.</p>
          </article>
          <?php endif; ?>
        </section>

        <section class="developer-dashboard-section" aria-label="Framework principles">
          <div class="developer-dashboard-section-head">
            <h2 class="developer-dashboard-section-title">What this installation contains</h2>
            <span class="developer-dashboard-refresh">Framework boundary</span>
          </div>
          <div class="developer-documentation-grid">
            <?php foreach ($principles as $principle): ?>
            <article class="developer-dashboard-card">
              <p class="feature-kicker">FNLLA</p>
              <h3><?= h($principle["title"]) ?></h3>
              <p class="content-text mb-0"><?= h($principle["text"]) ?></p>
            </article>
            <?php endforeach; ?>
          </div>
        </section>

        <section class="developer-dashboard-section" aria-label="About technical facts">
          <article class="developer-dashboard-card developer-dashboard-card-wide">
            <p class="feature-kicker">Installation facts</p>
            <div class="developer-dashboard-glance-table">
              <?php foreach ($facts as $label => $value): ?>
              <div class="developer-dashboard-glance-row">
                <strong><?= h((string) $label) ?></strong>
                <span><?= h((string) $value) ?></span>
              </div>
              <?php endforeach; ?>
            </div>
          </article>
        </section>

<?php require __DIR__ . "/panel-footer.php"; ?>
