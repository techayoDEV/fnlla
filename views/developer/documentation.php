<?php

declare(strict_types=1);

$developerPanelTitle = "Documentation";
$developerPanelLead = "Operational, descriptive and technical reference for FNLLA public surfaces and the Developer Panel.";
$publicDocs = [
    ["title" => "Application shell", "text" => "FNLLA ships a public website shell with home, about, services, contact, legal pages, cookie consent and project branding. Downstream projects replace the starter content while keeping the framework-managed runtime layer."],
    ["title" => "Consent and privacy", "text" => "Essential cookies are always on. Analytics and marketing tools are consent-gated. Local analytics and heatmap events are first-party aggregate measurements and external GA4 or Clarity tags remain optional."],
    ["title" => "Routing and assets", "text" => "Public routes live in the project route files and use framework helpers for URLs, assets, CSRF, CSP nonces and title metadata. Project assets remain under public assets while runtime assets stay framework-managed."],
    ["title" => "Analytics data", "text" => "The internal recorder aggregates requests, routes, status codes, referrer hosts, devices, form conversions, click zones and scroll-depth buckets into local framework storage."],
];
$developerDocs = [
    ["title" => "Workspace", "text" => "Dashboard, notifications and kanban keep project execution visible. Kanban tasks use modal editing for ownership, priority, dates, progress, labels and subtasks."],
    ["title" => "Project setup", "text" => "Project identity controls the local application name, public URL, browser title metadata and optional project leadership record. Analytics and Heatmap show first-party aggregate data and optional adapter readiness."],
    ["title" => "Operations", "text" => "Readiness, operations hub, framework updates and integrations expose runtime health, update planning, adapter configuration, audit links and service-control readiness."],
    ["title" => "Security and policy", "text" => "Access, preview, runtime, storage and policy boundary screens separate private developer access from public project behavior. Role management is lead-owned and auditable."],
];
$technicalRows = [
    "Internal analytics endpoint" => route("fnlla.analytics.event"),
    "Consent event" => "fnlla:analytics-consent-granted",
    "Behavior event schema" => "fnlla.behavior_event.v1",
    "Heatmap report schema" => "fnlla.developer_heatmap.v1",
    "Leadership schema" => "fnlla.project_leadership.v1",
    "Leadership visibility" => "PROJECT_LEADERSHIP_VISIBILITY=disabled|admin|public",
    "Metrics storage" => "storage/framework/metrics.json",
    "Version manifest" => "version-manifest.json",
];

require __DIR__ . "/panel-header.php";
?>

        <section class="developer-dashboard-section" aria-label="Documentation overview">
          <div class="developer-panel-intro">
            <div class="developer-panel-intro-copy">
              <p class="feature-kicker">Developer documentation</p>
              <h2 class="developer-dashboard-section-title">FNLLA operating manual</h2>
              <p class="content-text mb-0">A single panel reference for what belongs to the public framework surface, what belongs to the private Developer Panel and how the main technical contracts fit together.</p>
            </div>
            <div class="developer-panel-intro-actions">
              <span class="developer-dashboard-status is-active">Framework docs</span>
              <span class="developer-dashboard-status">Technical reference</span>
            </div>
          </div>
        </section>

        <section class="developer-dashboard-section" aria-label="Project leadership documentation">
          <div class="developer-dashboard-section-head">
            <h2 class="developer-dashboard-section-title">Project leadership</h2>
            <span class="developer-dashboard-refresh">Responsibility, not promotion</span>
          </div>
          <div class="developer-dashboard-overview-grid">
            <article class="developer-dashboard-card">
              <p class="feature-kicker">Purpose</p>
              <h3>Named responsibility</h3>
              <p class="content-text mb-0">Use the optional leadership block to identify who is responsible for product direction, roadmap, project delivery or technical leadership.</p>
            </article>
            <article class="developer-dashboard-card">
              <p class="feature-kicker">Confirmation</p>
              <h3>Explicit approval</h3>
              <p class="content-text mb-0">A pending record can be confirmed or rejected only by the named person signed in with the matching developer email.</p>
            </article>
            <article class="developer-dashboard-card">
              <p class="feature-kicker">Client systems</p>
              <h3>Private by default</h3>
              <p class="content-text mb-0">Set visibility to private panel and documentation when a client project should not show TechAyo or named-lead information publicly.</p>
            </article>
          </div>
        </section>

        <section class="developer-dashboard-section" aria-label="FNLLA public documentation">
          <div class="developer-dashboard-section-head">
            <h2 class="developer-dashboard-section-title">FNLLA Public</h2>
            <span class="developer-dashboard-refresh">Client-facing layer</span>
          </div>
          <div class="developer-documentation-grid">
            <?php foreach ($publicDocs as $doc): ?>
            <article class="developer-dashboard-card">
              <p class="feature-kicker">Public surface</p>
              <h3><?= h($doc["title"]) ?></h3>
              <p class="content-text mb-0"><?= h($doc["text"]) ?></p>
            </article>
            <?php endforeach; ?>
          </div>
        </section>

        <section class="developer-dashboard-section" aria-label="Developer Panel documentation">
          <div class="developer-dashboard-section-head">
            <h2 class="developer-dashboard-section-title">Developer Panel</h2>
            <span class="developer-dashboard-refresh">Private operations layer</span>
          </div>
          <div class="developer-documentation-grid">
            <?php foreach ($developerDocs as $doc): ?>
            <article class="developer-dashboard-card">
              <p class="feature-kicker">Developer Panel</p>
              <h3><?= h($doc["title"]) ?></h3>
              <p class="content-text mb-0"><?= h($doc["text"]) ?></p>
            </article>
            <?php endforeach; ?>
          </div>
        </section>

        <section class="developer-dashboard-section" aria-label="Technical reference">
          <div class="developer-dashboard-overview-grid">
            <article class="developer-dashboard-card developer-dashboard-card-wide">
              <p class="feature-kicker">Technical contracts</p>
              <div class="developer-dashboard-glance-table">
                <?php foreach ($technicalRows as $label => $value): ?>
                <div class="developer-dashboard-glance-row">
                  <strong><?= h((string) $label) ?></strong>
                  <span><?= h((string) $value) ?></span>
                </div>
                <?php endforeach; ?>
              </div>
            </article>
            <article class="developer-dashboard-card">
              <p class="feature-kicker">Adapter model <span class="developer-info-tip" tabindex="0" aria-label="External adapters only load when enabled and consent allows them.">i<span>External adapters remain optional. FNLLA can run first-party analytics and heatmaps without GA4, Clarity or webhook calls.</span></span></p>
              <h3>No external calls by default</h3>
              <p class="content-text mb-0">GA4, Clarity, heatmap providers and API hooks are integrations, not required infrastructure. Configure them only when a project explicitly wants third-party reporting.</p>
            </article>
          </div>
        </section>

<?php require __DIR__ . "/panel-footer.php"; ?>
