<?php

declare(strict_types=1);

$developerPanelTitle = "Documentation & Policy";
$developerPanelLead = "Operational, descriptive, policy and technical reference for FNLLA public surfaces and the Developer Panel.";
$policy = is_array($developerPolicy ?? null) ? $developerPolicy : [];
$boundary = (array) ($policy["boundary"] ?? []);
$storage = (array) ($policy["storage"] ?? []);
$about = is_array($aboutFnlla ?? null) ? (array) $aboutFnlla : [];
$officialUrl = (string) ($about["official_url"] ?? config("framework.official_url", "https://fnlla.com"));
$repositoryUrl = (string) ($about["repository"] ?? config("framework.repository_web_url", "https://github.com/techayoDEV/fnlla"));
$supportEmail = (string) ($about["support_email"] ?? config("framework.support_email", "support@fnlla.com"));
$facts = [
    "Application name" => (string) ($about["app_name"] ?? config("app.name", "FNLLA")),
    "Framework version" => (string) ($about["framework_version"] ?? config("app.framework_version", "unknown")),
    "Runtime version" => (string) ($about["runtime_version"] ?? config("fnlla_runtime.version", "unknown")),
    "Environment" => (string) ($about["environment"] ?? app_environment()),
    "Official website" => $officialUrl,
    "Source repository" => $repositoryUrl,
    "Support email" => $supportEmail,
    "Maintainer" => (string) ($about["maintainer"] ?? "TechAyo Limited"),
    "License" => (string) ($about["license"] ?? "MIT"),
];
$policySections = [
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
$publicDocs = [
    ["title" => "Application shell", "text" => "FNLLA ships a public website shell with home, about, services, contact, legal pages, cookie consent and project branding. Downstream projects replace the starter content while keeping the framework-managed runtime layer."],
    ["title" => "Consent and privacy", "text" => "Essential cookies are always on. Analytics and marketing tools are consent-gated. Local analytics and heatmap events are first-party aggregate measurements and external GA4 or Clarity tags remain optional."],
    ["title" => "Routing and assets", "text" => "Public routes live in the project route files and use framework helpers for URLs, assets, CSRF, CSP nonces and title metadata. Project assets remain under public assets while runtime assets stay framework-managed."],
    ["title" => "Analytics data", "text" => "The internal recorder aggregates requests, routes, status codes, referrer hosts, devices, form conversions, click zones and scroll-depth buckets into local framework storage."],
    ["title" => "Public starter scope", "text" => "The starter is a working handover surface, not final product copy. It should be claimed with project-specific content, contact details, legal wording and any product-owned workflows before launch."],
    ["title" => "Upload boundary", "text" => "Starter uploads are limited to configured MIME types and request-size caps. Customer documents, retention rules, virus scanning and private file workflows belong to the downstream application."],
];
$developerDocs = [
    ["title" => "Dashboard", "text" => "Dashboard is the first standalone Developer Panel destination. It gives the current operational snapshot before a developer moves into setup, workspace, security or release work."],
    ["title" => "Workspace", "text" => "Project Kanban keeps delivery work visible. Tasks use modal editing for ownership, priority, dates, progress, color labels, file or URL attachments, subtasks, comments and activity."],
    ["title" => "Project setup", "text" => "Setup checklist, project identity and access preview keep handover readiness, browser metadata, public URL and leadership visibility in one group."],
    ["title" => "Operations", "text" => "Readiness, framework updates, project logs, analytics, heatmap and integrations expose runtime health, update planning, change history, aggregate signals, adapter configuration and audit links."],
    ["title" => "Security", "text" => "Access, runtime and storage screens separate private developer access from public project behavior. Role management is lead-owned and auditable."],
    ["title" => "Notification workflow", "text" => "Notifications are an actionable review queue. Review marks an item as read and opens the source screen; archive hides it until restore is selected from the archived list."],
    ["title" => "Developer identity", "text" => "Named accounts make audit trails useful. A developer can update display name, rotate their own password, upload or remove an avatar and enable two-factor protection."],
    ["title" => "Environment policy", "text" => "The panel may write a controlled set of project-level environment keys. Secrets stay in the real environment or hosting secret store and are never committed to the framework repository."],
];
$runbookDocs = [
    [
        "title" => "First project setup",
        "text" => "Use this sequence immediately after generating or claiming a project.",
        "items" => [
            "Confirm APP_NAME, APP_TAGLINE and public URL before sharing the build.",
            "Create at least one named developer account and remove password-only access.",
            "Set preview-lock behavior and leadership visibility before client handover.",
        ],
    ],
    [
        "title" => "Before client preview",
        "text" => "Use this when a private preview link is about to be sent to a client.",
        "items" => [
            "Open Project setup and confirm identity, access and public route state.",
            "Check Project Kanban for blocked or urgent cards.",
            "Review notifications and project logs for unresolved release or security notes.",
        ],
    ],
    [
        "title" => "Release and update runbook",
        "text" => "Use this before applying framework updates or publishing a project handover.",
        "items" => [
            "Run readiness checks and cache the official GitHub release manifest first.",
            "Review the dry-run report, changed files and conflict notes before applying.",
            "Validate lint, tests and version manifest after changes land.",
        ],
    ],
    [
        "title" => "Incident or rollback review",
        "text" => "Use this when something changes unexpectedly or a deployment needs investigation.",
        "items" => [
            "Start from Project logs to see who changed project state and when.",
            "Compare Analytics and Heatmap for traffic, errors, conversions and interaction changes.",
            "Keep client-specific recovery notes in the downstream project, not FNLLA core.",
        ],
    ],
];
$configurationRows = [
    "Identity and URLs" => "APP_NAME, APP_TAGLINE, APP_URL, ASSET_URL",
    "Private preview" => "MAINTENANCE_MODE_ENABLED, MAINTENANCE_ACCESS_PASSWORD",
    "Developer access" => "DEVELOPER_ACCESS_ENABLED, DEVELOPER_ACCESS_PATH, DEVELOPER_ACCESS_USERS",
    "Session policy" => "DEVELOPER_ACCESS_TTL_MINUTES, DEVELOPER_ACCESS_ABSOLUTE_TTL_MINUTES, rate-limit window keys",
    "Leadership visibility" => "PROJECT_LEADERSHIP_* with disabled, admin or public visibility",
    "Uploads and request size" => "REQUEST_MAX_BODY_BYTES, UPLOAD_MAX_FILE_BYTES",
    "Observability" => "OBSERVABILITY_METRICS_ENABLED plus optional GA4, Clarity and webhook adapters",
    "Runtime sync" => "FNLLA_RUNTIME_ENFORCE, FNLLA_RUNTIME_AUTO_SYNC, FNLLA_RUNTIME_SYNC_INTERVAL_SECONDS",
];
$dataRows = [
    "Project Kanban" => "storage/framework/testing or storage/framework/developer workspace JSON depending on runtime context",
    "Project logs" => "storage/framework/developer/activity.jsonl",
    "Notifications" => "storage/framework/developer/notifications-state.json",
    "Metrics" => "storage/framework/metrics.json",
    "Sessions" => "storage/framework/sessions",
    "Uploaded task files" => "public/uploads/developer-workspace-attachments",
    "Developer avatars" => "public/uploads/developer-avatars",
    "Release cache" => "storage/framework/updates",
];
$technicalRows = [
    "Internal analytics endpoint" => route("fnlla.analytics.event"),
    "Consent event" => "fnlla:analytics-consent-granted",
    "Behavior event schema" => "fnlla.behavior_event.v1",
    "Heatmap report schema" => "fnlla.developer_heatmap.v1",
    "Leadership schema" => "fnlla.project_leadership.v1",
    "Leadership visibility" => "PROJECT_LEADERSHIP_VISIBILITY=disabled|admin|public",
    "Developer entry path" => "DEVELOPER_ACCESS_PATH=/developer",
    "Notification state" => "storage/framework/developer/notifications-state.json",
    "Project activity log" => "storage/framework/developer/activity.jsonl",
    "Metrics storage" => "storage/framework/metrics.json",
    "Upload size cap" => "REQUEST_MAX_BODY_BYTES / UPLOAD_MAX_FILE_BYTES",
    "Official framework website" => $officialUrl,
    "Update source" => "techayoDEV/fnlla official release manifest",
    "Source repository" => $repositoryUrl,
    "Support email" => $supportEmail,
    "Version manifest" => "version-manifest.json",
    "Framework version source" => "VERSION",
    "Runtime version source" => "public/vendor/fnlla-runtime/VERSION",
    "Validation commands" => "php scripts/lint.php, php scripts/test.php, php scripts/validate-version-manifest.php",
];

require __DIR__ . "/panel-header.php";
?>

        <section class="developer-dashboard-section" aria-label="Documentation overview">
          <div class="developer-panel-intro">
            <div class="developer-panel-intro-copy">
              <p class="feature-kicker">Documentation & policy</p>
              <h2 class="developer-dashboard-section-title">FNLLA operating manual</h2>
              <p class="content-text mb-0">A single panel reference for the public framework surface, the private Developer Panel, policy boundaries and installation facts. Official framework identity lives at <?= h($officialUrl) ?> and remains separate from downstream project branding.</p>
            </div>
            <div class="developer-panel-intro-actions">
              <span class="developer-dashboard-status is-active">Framework docs</span>
              <span class="developer-dashboard-status">Policy boundary</span>
            </div>
          </div>
        </section>

        <section class="developer-dashboard-section" aria-label="Policy boundary documentation">
          <div class="developer-dashboard-section-head">
            <h2 class="developer-dashboard-section-title">Policy boundary</h2>
            <span class="developer-dashboard-refresh">Framework vs project ownership</span>
          </div>
          <div class="developer-policy-map">
            <?php foreach ($policySections as $key => $section): ?>
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
          <article class="developer-dashboard-card developer-dashboard-card-wide mt-3">
            <p class="feature-kicker">Storage contract</p>
            <p class="content-text"><?= h((string) ($storage["default"] ?? "FNLLA stores framework-owned operational data separately from project-owned product data.")) ?></p>
            <p class="content-text mb-0"><?= h((string) ($storage["production_note"] ?? "Production storage should be reviewed before release or hosting moves.")) ?></p>
          </article>
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

        <section class="developer-dashboard-section" aria-label="Operational runbooks">
          <div class="developer-dashboard-section-head">
            <h2 class="developer-dashboard-section-title">Operational runbooks</h2>
            <span class="developer-dashboard-refresh">How to use the panel</span>
          </div>
          <div class="developer-documentation-grid">
            <?php foreach ($runbookDocs as $doc): ?>
            <article class="developer-dashboard-card">
              <p class="feature-kicker">Runbook</p>
              <h3><?= h($doc["title"]) ?></h3>
              <p class="content-text"><?= h($doc["text"]) ?></p>
              <ul class="developer-documentation-list">
                <?php foreach ((array) $doc["items"] as $item): ?>
                <li><?= h((string) $item) ?></li>
                <?php endforeach; ?>
              </ul>
            </article>
            <?php endforeach; ?>
          </div>
        </section>

        <section class="developer-dashboard-section" aria-label="Configuration and storage map">
          <div class="developer-dashboard-section-head">
            <h2 class="developer-dashboard-section-title">Configuration and data map</h2>
            <span class="developer-dashboard-refresh">Where project state lives</span>
          </div>
          <div class="developer-dashboard-overview-grid">
            <article class="developer-dashboard-card developer-dashboard-card-wide">
              <p class="feature-kicker">Configuration map</p>
              <div class="developer-dashboard-glance-table">
                <?php foreach ($configurationRows as $label => $value): ?>
                <div class="developer-dashboard-glance-row">
                  <strong><?= h((string) $label) ?></strong>
                  <span><?= h((string) $value) ?></span>
                </div>
                <?php endforeach; ?>
              </div>
            </article>
            <article class="developer-dashboard-card developer-dashboard-card-wide">
              <p class="feature-kicker">Data and storage map</p>
              <div class="developer-dashboard-glance-table">
                <?php foreach ($dataRows as $label => $value): ?>
                <div class="developer-dashboard-glance-row">
                  <strong><?= h((string) $label) ?></strong>
                  <span><?= h((string) $value) ?></span>
                </div>
                <?php endforeach; ?>
              </div>
            </article>
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

        <section class="developer-dashboard-section" aria-label="Installation facts">
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
