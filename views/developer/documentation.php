<?php

declare(strict_types=1);

use Fnlla\Php\Support\DeveloperPanelLabels;

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
$sourceDocumentationFiles = [
    ["title" => "Documentation map", "section" => "Start", "path" => "docs/README.md"],
    ["title" => "Starting a new project", "section" => "Start", "path" => "docs/STARTING-A-NEW-PROJECT.md"],
    ["title" => "Building with FNLLA", "section" => "Build", "path" => "docs/BUILDING-WITH-FNLLA.md"],
    ["title" => "Developer Panel", "section" => "Panel", "path" => "docs/DEVELOPER-PANEL.md"],
    ["title" => "Release and operations", "section" => "Operations", "path" => "docs/RELEASE-AND-OPERATIONS.md"],
    ["title" => "Environment", "section" => "Configuration", "path" => "docs/ENVIRONMENT.md"],
    ["title" => "Business application reference", "section" => "Application", "path" => "docs/BUSINESS-APP-REFERENCE.md"],
    ["title" => "Public API", "section" => "Contracts", "path" => "docs/PUBLIC-API.md"],
    ["title" => "Runtime contracts", "section" => "Contracts", "path" => "docs/framework/RUNTIME-CONTRACTS.md"],
    ["title" => "Migration", "section" => "Updates", "path" => "docs/MIGRATION.md"],
    ["title" => "Architecture roadmap", "section" => "Architecture", "path" => "docs/ARCHITECTURE-ROADMAP.md"],
    ["title" => "Modernization status", "section" => "Architecture", "path" => "docs/MODERNIZATION-STATUS.md"],
    ["title" => "AI context", "section" => "AI", "path" => "docs/AI-CONTEXT.md"],
    ["title" => "Framework support", "section" => "Support", "path" => "docs/framework/SUPPORT.md"],
    ["title" => "Framework trademarks", "section" => "Legal", "path" => "docs/framework/TRADEMARKS.md"],
    ["title" => "FNLLA changelog", "section" => "Release history", "path" => "CHANGELOG.md"],
];
$renderDocumentationInline = static function (string $value): string {
    $html = h($value);
    $html = preg_replace('/\*\*([^*]+)\*\*/', '<strong>$1</strong>', $html) ?? $html;
    $html = preg_replace('/`([^`]+)`/', '<code>$1</code>', $html) ?? $html;
    $html = preg_replace('/\[([^\]]+)\]\(([^)]+)\)/', '<span class="developer-documentation-inline-link">$1</span>', $html) ?? $html;

    return $html;
};
$documentationHeadingIds = [];
$documentationSlug = static function (string $value) use (&$documentationHeadingIds): string {
    $slug = strtolower(trim((string) preg_replace('/[^a-zA-Z0-9]+/', "-", $value), "-"));
    $slug = $slug !== "" ? $slug : "section";
    $base = $slug;
    $index = 2;

    while (isset($documentationHeadingIds[$slug])) {
        $slug = $base . "-" . (string) $index;
        $index++;
    }

    $documentationHeadingIds[$slug] = true;

    return $slug;
};
$renderDocumentationMarkdown = static function (string $markdown) use ($renderDocumentationInline, $documentationSlug): string {
    $lines = preg_split('/\n/', str_replace(["\r\n", "\r"], "\n", $markdown)) ?: [];
    $html = [];
    $openList = "";
    $inCode = false;
    $codeLines = [];
    $closeList = static function () use (&$html, &$openList): void {
        if ($openList !== "") {
            $html[] = "</" . $openList . ">";
            $openList = "";
        }
    };

    foreach ($lines as $line) {
        if (preg_match('/^\s*```/', $line) === 1) {
            if ($inCode) {
                $html[] = '<pre><code>' . h(implode("\n", $codeLines)) . '</code></pre>';
                $codeLines = [];
                $inCode = false;
                continue;
            }

            $closeList();
            $inCode = true;
            $codeLines = [];
            continue;
        }

        if ($inCode) {
            $codeLines[] = $line;
            continue;
        }

        $trimmed = trim($line);

        if ($trimmed === "") {
            $closeList();
            continue;
        }

        if (preg_match('/^(#{1,4})\s+(.+)$/', $trimmed, $matches) === 1) {
            $closeList();
            $level = min(6, strlen((string) $matches[1]) + 2);
            $heading = (string) $matches[2];
            $id = "docs-source-" . $documentationSlug($heading);
            $html[] = '<h' . $level . ' id="' . h($id) . '"><a class="developer-documentation-anchor" href="#' . h($id) . '">' . $renderDocumentationInline($heading) . '</a></h' . $level . '>';
            continue;
        }

        if (preg_match('/^[-*]\s+(.+)$/', $trimmed, $matches) === 1) {
            if ($openList !== "ul") {
                $closeList();
                $openList = "ul";
                $html[] = "<ul>";
            }
            $html[] = '<li>' . $renderDocumentationInline((string) $matches[1]) . '</li>';
            continue;
        }

        if (preg_match('/^\d+\.\s+(.+)$/', $trimmed, $matches) === 1) {
            if ($openList !== "ol") {
                $closeList();
                $openList = "ol";
                $html[] = "<ol>";
            }
            $html[] = '<li>' . $renderDocumentationInline((string) $matches[1]) . '</li>';
            continue;
        }

        $closeList();
        $html[] = '<p>' . $renderDocumentationInline($trimmed) . '</p>';
    }

    if ($inCode) {
        $html[] = '<pre><code>' . h(implode("\n", $codeLines)) . '</code></pre>';
    }

    $closeList();

    return implode("\n", $html);
};
$sourceDocumentation = [];
foreach ($sourceDocumentationFiles as $sourceDocumentationFile) {
    $sourcePath = base_path((string) $sourceDocumentationFile["path"]);
    if (!is_file($sourcePath)) {
        continue;
    }

    $sourceMarkdown = trim((string) file_get_contents($sourcePath));
    if ($sourceMarkdown === "") {
        continue;
    }

    $sourceDocumentation[] = [
        "title" => (string) $sourceDocumentationFile["title"],
        "section" => (string) $sourceDocumentationFile["section"],
        "html" => $renderDocumentationMarkdown($sourceMarkdown),
        "search" => strtolower((string) $sourceDocumentationFile["title"] . " " . (string) $sourceDocumentationFile["section"]),
    ];
}
$documentationToc = [
    ["href" => "#docs-overview", "label" => "Overview"],
    ["href" => "#docs-operating-map", "label" => "Operating map"],
    ["href" => "#docs-start", "label" => "Start here"],
    ["href" => "#docs-framework", "label" => "FNLLA framework"],
    ["href" => "#docs-public", "label" => "FNLLA Public"],
    ["href" => "#docs-developer-panel", "label" => "Developer Panel"],
    ["href" => "#docs-runbooks", "label" => "Runbooks"],
    ["href" => "#docs-policy-boundary", "label" => "Policy boundary"],
    ["href" => "#docs-config-storage", "label" => "Config & data"],
    ["href" => "#docs-technical-reference", "label" => "Technical reference"],
    ["href" => "#docs-changelog", "label" => "Changelog"],
    ["href" => "#docs-source-reference", "label" => "Source manual"],
    ["href" => "#docs-installation-facts", "label" => "Installation facts"],
];
$documentationSectionLabels = [];
foreach ($documentationToc as $documentationTocItem) {
    $documentationSectionLabels[ltrim((string) $documentationTocItem["href"], "#")] = (string) $documentationTocItem["label"];
}
$documentationSectionTitle = static fn (string $sectionId, string $fallback): string => $documentationSectionLabels[$sectionId] ?? $fallback;
$manualChapters = [
    [
        "label" => "Project start",
        "title" => "Create, claim and verify",
        "text" => "Generate a project outside the FNLLA source tree, install dependencies, claim the project identity, configure APP_URL and run validation before sharing a preview.",
        "items" => [
            "Use an empty external target for a new project export.",
            "Keep .env private and treat .env.example as the public setup contract.",
            "Run lint, tests, runtime validation and health checks after setup.",
        ],
    ],
    [
        "label" => "Application model",
        "title" => "Routes, controllers and views",
        "text" => "FNLLA keeps the product surface direct: routes declare HTTP entrypoints, controllers shape validated data and PHP views render server-side HTML.",
        "items" => [
            "Public pages are project-owned and can be replaced by the downstream application.",
            "Framework-managed private routes stay behind Developer Panel, customer preview or maintenance access.",
            "Use helpers for routes, assets, CSRF fields, old input, validation errors and escaped output.",
        ],
    ],
    [
        "label" => "Developer Panel",
        "title" => "Private operating workspace",
        "text" => "The panel is for trusted developers, not public users. It centralises setup, identity, project tasks, technical debt, access, readiness, updates, logs and local observability.",
        "items" => [
            "Shared project changes made in the panel are project-global.",
            "Named developer accounts make audit trails and responsibility clear.",
            "Operations screens should guide decisions, not become a second product dashboard.",
        ],
    ],
    [
        "label" => "Security",
        "title" => "Least privilege and release gates",
        "text" => "Developer access uses role capabilities, session limits, TOTP readiness and setup restrictions. Regulated projects should add SSO, MFA policy and external approval gates.",
        "items" => [
            "Separate view, approve and apply capabilities for sensitive operations.",
            "Keep production secrets in the hosting environment or secret store.",
            "Use preview locks and service control deliberately before client handover.",
        ],
    ],
    [
        "label" => "Observability",
        "title" => "Public-only analytics and heatmap",
        "text" => "FNLLA records first-party aggregate measurements for public pages only. Developer Panel, maintenance, client, API and internal routes are excluded from public analytics and heatmap reports.",
        "items" => [
            "No raw IP addresses, raw user agents, session replay, keystrokes or form values are stored.",
            "Analytics and heatmap require project consent and local policy to allow collection.",
            "Use Error Monitor for runtime issues instead of mixing private errors into public traffic charts.",
        ],
    ],
    [
        "label" => "Updates",
        "title" => "Framework updates and drift review",
        "text" => "Framework Updates is a controlled workflow: check source, inspect dry-run output, review blocked items, apply only when policy allows it and validate the project afterwards.",
        "items" => [
            "Major updates require compatibility review before apply.",
            "Production apply should be tied to backup, maintenance window and CI/CD approval.",
            "Keep accepted risk and unresolved decisions visible in Review queue or technical debt.",
        ],
    ],
];
$changelogRows = [
    "Current line" => "FNLLA " . (string) ($about["framework_version"] ?? config("app.framework_version", "unknown")),
    "Release notes source" => "CHANGELOG.md in the maintained FNLLA repository",
    "Download source" => "Official GitHub Releases for techayoDEV/fnlla",
    "2.2.0 summary" => "Integrated starter, private Developer Panel, diagnostics, framework updates, Project work, first-party analytics and optional AI provider adapters.",
    "Upgrade rule" => "Review migration notes, run dry-run update checks, then validate lint, tests, runtime and version manifest after apply.",
];
$documentationMetrics = [
    ["label" => "Source documents", "value" => (string) count($sourceDocumentation), "text" => "Maintained FNLLA docs rendered inside this panel."],
    ["label" => "Runbooks", "value" => "4", "text" => "Operational sequences for setup, preview, release and incident review."],
    ["label" => "Policy zones", "value" => "3", "text" => "Framework, project and forbidden-core boundaries."],
    ["label" => "Release line", "value" => (string) ($about["framework_version"] ?? config("app.framework_version", "unknown")), "text" => "Version context for this installation."],
];
$operatingMap = [
    ["label" => "Start", "title" => "Create or claim", "href" => "#docs-start", "text" => "Use the setup sequence before product work begins."],
    ["label" => "Workspace", "title" => "Identity and delivery", "href" => "#docs-developer-panel", "text" => "Project identity and Project work stay together as handover context."],
    ["label" => "Operations", "title" => "Access and runtime", "href" => "#docs-runbooks", "text" => "Security, review queue, release readiness, observability and adapters are operational checks."],
    ["label" => "Policy", "title" => "Docs and contracts", "href" => "#docs-technical-reference", "text" => "Stable schemas, storage paths and public API boundaries remain traceable from Operations."],
];
$frameworkDocs = [
    ["title" => "Framework philosophy", "text" => "FNLLA is a lightweight PHP framework for teams that want explicit server-rendered applications without adopting a large full-stack framework by default."],
    ["title" => "Project ownership", "text" => "The framework provides setup, routing, view helpers, runtime assets, validation, security helpers and operational surfaces; the downstream project owns domain models, customer data and business workflows."],
    ["title" => "Runtime shape", "text" => "A project runs through the public entrypoint, bootstraps configuration, resolves the route, applies middleware and renders PHP views with escaped output by default."],
    ["title" => "Starter boundary", "text" => "The starter is a working foundation with public pages, contact form, maintenance, customer preview and Developer Panel. It is not final client copy."],
    ["title" => "Testing contract", "text" => "Keep project tests close to user-facing behavior. Use lint, static analysis, runtime health and route smoke checks before handover or update work."],
    ["title" => "Release posture", "text" => "A passing validation run proves the current tree is coherent. It does not replace human review of dirty changes, docs, migration notes or release scope."],
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
    ["title" => "Consent and privacy", "text" => "Essential cookies are always on. Analytics and marketing tools are consent-gated. Local analytics and heatmap events are first-party aggregate measurements."],
    ["title" => "Routing and assets", "text" => "Public routes live in the project route files and use framework helpers for URLs, assets, CSRF, CSP nonces and title metadata. Project assets remain under public assets while runtime assets stay framework-managed."],
    ["title" => "Analytics data", "text" => "The internal recorder aggregates requests, routes, status codes, referrer hosts, devices, form conversions, click zones and scroll-depth buckets into local framework storage."],
    ["title" => "Public starter scope", "text" => "The starter is a working handover surface, not final product copy. It should be claimed with project-specific content, contact details, legal wording and any product-owned workflows before launch."],
    ["title" => "Upload boundary", "text" => "Starter uploads are limited to configured MIME types and request-size caps. Customer documents, retention rules, virus scanning and private file workflows belong to the downstream application."],
];
$developerDocs = [
    ["title" => "Dashboard", "text" => "Dashboard is the first standalone Developer Panel destination. It gives the current operational snapshot before a developer moves into setup, workspace, security or release work."],
    ["title" => "Workspace", "text" => "Project identity, leadership context and Project work live together because they describe what the project is and what delivery work is still open. Project work contains the task board, technical debt and project changelog."],
    ["title" => "Operations", "text" => "Access and security, Review queue, Release & readiness, Observability and Adapters & AI expose runtime risk, update planning, adapter configuration and audit links without crowding the sidebar."],
    ["title" => "Reference", "text" => "Documentation & policy stays in Reference for in-panel guidance, storage rules, capability boundaries and operating contracts."],
    ["title" => "Security", "text" => "Access controls are treated as operational risk. Role management is lead-owned, auditable and separate from public project behavior."],
    ["title" => "Review queue", "text" => "Review queue combines actionable notifications with setup decisions. Notification items can be marked read or archived; checklist items link directly to the source screen."],
    ["title" => "Developer identity", "text" => "Named accounts make audit trails useful. A developer can update display name, rotate their own password, upload or remove an avatar and enable two-factor protection."],
    ["title" => "Environment policy", "text" => "The panel may write a controlled set of project-level environment keys. Secrets stay in the real environment or hosting secret store and are never committed to the framework repository."],
];
$runbookDocs = [
    [
        "title" => "First project setup",
        "text" => "Use this sequence immediately after generating or claiming a project.",
        "items" => [
            "Confirm APP_NAME, APP_TAGLINE and public URL before sharing the build.",
            "Open Operations > Access & security and create at least one named developer account.",
            "Set preview-lock behavior and leadership visibility before client handover.",
        ],
    ],
    [
        "title" => "Before client preview",
        "text" => "Use this when a private preview link is about to be sent to a client.",
        "items" => [
            "Open Workspace > Project identity and confirm identity, leadership and public route state.",
            "Open Operations > Access & security and confirm developer account state.",
            "Check Project work for blocked or urgent cards.",
            "Review queue and project logs for unresolved release or security notes.",
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
    "Observability" => "OBSERVABILITY_METRICS_ENABLED plus analytics, heatmap, error monitor and optional webhook adapter settings",
    "Runtime sync" => "FNLLA_RUNTIME_ENFORCE, FNLLA_RUNTIME_AUTO_SYNC, FNLLA_RUNTIME_SYNC_INTERVAL_SECONDS",
];
$dataRows = [
    "Project work" => "storage/framework/testing or storage/framework/developer workspace JSON depending on runtime context",
    "Project logs" => "storage/framework/developer/activity.jsonl",
    "Review queue state" => "storage/framework/developer/notifications-state.json",
    "Metrics" => "storage/framework/metrics.json",
    "Sessions" => "storage/framework/sessions",
    "Uploaded task files" => "public/uploads/developer-workspace-attachments",
    "Developer avatars" => "public/uploads/developer-avatars",
    "Release cache" => "storage/framework/updates",
];
$technicalRows = [
    ["label" => "Internal analytics endpoint", "value" => "First-party event collector", "id" => route("fnlla.analytics.event")],
    ["label" => "Consent event", "value" => DeveloperPanelLabels::event("fnlla:analytics-consent-granted"), "id" => "fnlla:analytics-consent-granted"],
    ["label" => "Behavior event schema", "value" => DeveloperPanelLabels::contract("fnlla.behavior_event.v1"), "id" => "fnlla.behavior_event.v1"],
    ["label" => "Heatmap report schema", "value" => DeveloperPanelLabels::contract("fnlla.developer_heatmap.v1"), "id" => "fnlla.developer_heatmap.v1"],
    ["label" => "Leadership schema", "value" => DeveloperPanelLabels::contract("fnlla.project_leadership.v1"), "id" => "fnlla.project_leadership.v1"],
    ["label" => "Leadership visibility", "value" => "Private, admin-visible or public", "id" => "PROJECT_LEADERSHIP_VISIBILITY=disabled|admin|public"],
    ["label" => "Developer entry path", "value" => "Private Developer Panel path", "id" => "DEVELOPER_ACCESS_PATH=/developer"],
    ["label" => "Review queue state", "value" => "Decision queue state file", "id" => "storage/framework/developer/notifications-state.json"],
    ["label" => "Project activity log", "value" => "Developer audit events", "id" => "storage/framework/developer/activity.jsonl"],
    ["label" => "Metrics storage", "value" => "First-party aggregate metrics", "id" => "storage/framework/metrics.json"],
    ["label" => "Upload size cap", "value" => "Request and upload byte limits", "id" => "REQUEST_MAX_BODY_BYTES / UPLOAD_MAX_FILE_BYTES"],
    ["label" => "Official framework website", "value" => $officialUrl, "id" => ""],
    ["label" => "Update source", "value" => "Official FNLLA release manifest", "id" => "techayoDEV/fnlla"],
    ["label" => "Source repository", "value" => $repositoryUrl, "id" => ""],
    ["label" => "Support email", "value" => $supportEmail, "id" => ""],
    ["label" => "Version manifest", "value" => "Framework version manifest", "id" => "version-manifest.json"],
    ["label" => "Framework version source", "value" => "Framework version file", "id" => "VERSION"],
    ["label" => "Runtime version source", "value" => "Runtime asset version file", "id" => "public/vendor/fnlla-runtime/VERSION"],
    ["label" => "Validation commands", "value" => "Lint, tests and manifest validation", "id" => "php scripts/lint.php, php scripts/test.php, php scripts/validate-version-manifest.php"],
];

require __DIR__ . "/panel-header.php";
?>

        <div class="developer-documentation-layout">
          <aside class="developer-documentation-toc" aria-label="Documentation sections">
            <div class="developer-documentation-toc-head">
              <p class="feature-kicker">Manual</p>
              <span><?= h((string) count($documentationToc)) ?> sections</span>
            </div>
            <nav data-developer-docs-nav>
              <?php foreach ($documentationToc as $tocIndex => $tocItem): ?>
              <a href="<?= h((string) $tocItem["href"]) ?>"<?= $tocIndex === 0 ? ' aria-current="location"' : "" ?>>
                <span><?= h(str_pad((string) ($tocIndex + 1), 2, "0", STR_PAD_LEFT)) ?></span>
                <strong><?= h((string) $tocItem["label"]) ?></strong>
              </a>
              <?php endforeach; ?>
            </nav>
          </aside>
          <div class="developer-documentation-manual">
        <section class="developer-dashboard-section" id="docs-overview" aria-label="Documentation overview">
          <div class="developer-panel-intro">
            <div class="developer-panel-intro-copy">
              <p class="feature-kicker">Documentation & policy</p>
              <h2 class="developer-dashboard-section-title"><?= h($documentationSectionTitle("docs-overview", "Overview")) ?></h2>
              <p class="content-text mb-0">A complete in-panel HTML reference for the FNLLA framework, the public starter surface, the private Developer Panel, policy boundaries and installation facts. Official framework identity lives at <?= h($officialUrl) ?> and remains separate from downstream project branding.</p>
            </div>
            <div class="developer-panel-intro-actions">
              <span class="developer-dashboard-status is-active">HTML manual</span>
              <span class="developer-dashboard-status">Policy boundary</span>
            </div>
          </div>
          <div class="developer-documentation-metrics">
            <?php foreach ($documentationMetrics as $metric): ?>
            <article>
              <strong><?= h((string) $metric["value"]) ?></strong>
              <span><?= h((string) $metric["label"]) ?></span>
              <p><?= h((string) $metric["text"]) ?></p>
            </article>
            <?php endforeach; ?>
          </div>
        </section>

        <section class="developer-dashboard-section" id="docs-operating-map" aria-label="Documentation operating map">
          <div class="developer-dashboard-section-head">
            <h2 class="developer-dashboard-section-title"><?= h($documentationSectionTitle("docs-operating-map", "Operating map")) ?></h2>
            <span class="developer-dashboard-refresh">Where to start</span>
          </div>
          <div class="developer-documentation-operating-map">
            <?php foreach ($operatingMap as $item): ?>
            <a href="<?= h((string) $item["href"]) ?>">
              <span><?= h((string) $item["label"]) ?></span>
              <strong><?= h((string) $item["title"]) ?></strong>
              <em><?= h((string) $item["text"]) ?></em>
            </a>
            <?php endforeach; ?>
          </div>
        </section>

        <section class="developer-dashboard-section" id="docs-start" aria-label="FNLLA in-panel manual">
          <div class="developer-dashboard-section-head">
            <h2 class="developer-dashboard-section-title"><?= h($documentationSectionTitle("docs-start", "Start here")) ?></h2>
            <span class="developer-dashboard-refresh">Complete HTML manual</span>
          </div>
          <div class="developer-documentation-chapter-grid">
            <?php foreach ($manualChapters as $chapter): ?>
            <article class="developer-dashboard-card">
              <p class="feature-kicker"><?= h((string) $chapter["label"]) ?></p>
              <h3><?= h((string) $chapter["title"]) ?></h3>
              <p class="content-text"><?= h((string) $chapter["text"]) ?></p>
              <ul class="developer-documentation-list">
                <?php foreach ((array) $chapter["items"] as $item): ?>
                <li><?= h((string) $item) ?></li>
                <?php endforeach; ?>
              </ul>
            </article>
            <?php endforeach; ?>
          </div>
        </section>

        <section class="developer-dashboard-section" id="docs-framework" aria-label="FNLLA framework documentation">
          <div class="developer-dashboard-section-head">
            <h2 class="developer-dashboard-section-title"><?= h($documentationSectionTitle("docs-framework", "FNLLA framework")) ?></h2>
            <span class="developer-dashboard-refresh">What the framework owns</span>
          </div>
          <div class="developer-documentation-grid">
            <?php foreach ($frameworkDocs as $doc): ?>
            <article class="developer-dashboard-card">
              <p class="feature-kicker">Framework reference</p>
              <h3><?= h($doc["title"]) ?></h3>
              <p class="content-text mb-0"><?= h($doc["text"]) ?></p>
            </article>
            <?php endforeach; ?>
          </div>
        </section>

        <section class="developer-dashboard-section" id="docs-policy-boundary" aria-label="Policy boundary documentation">
          <div class="developer-dashboard-section-head">
            <h2 class="developer-dashboard-section-title"><?= h($documentationSectionTitle("docs-policy-boundary", "Policy boundary")) ?></h2>
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

        <section class="developer-dashboard-section" id="docs-leadership" aria-label="Project leadership documentation">
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
              <p class="content-text mb-0">A pending record can be confirmed or rejected by the named person signed in with the matching developer email, or by a lead developer approving the project record.</p>
            </article>
            <article class="developer-dashboard-card">
              <p class="feature-kicker">Client systems</p>
              <h3>Private by default</h3>
              <p class="content-text mb-0">Set visibility to private panel and documentation when a client project should not show TechAyo or named-lead information publicly.</p>
            </article>
          </div>
        </section>

        <section class="developer-dashboard-section" id="docs-public" aria-label="FNLLA public documentation">
          <div class="developer-dashboard-section-head">
            <h2 class="developer-dashboard-section-title"><?= h($documentationSectionTitle("docs-public", "FNLLA Public")) ?></h2>
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

        <section class="developer-dashboard-section" id="docs-developer-panel" aria-label="Developer Panel documentation">
          <div class="developer-dashboard-section-head">
            <h2 class="developer-dashboard-section-title"><?= h($documentationSectionTitle("docs-developer-panel", "Developer Panel")) ?></h2>
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

        <section class="developer-dashboard-section" id="docs-runbooks" aria-label="Operational runbooks">
          <div class="developer-dashboard-section-head">
            <h2 class="developer-dashboard-section-title"><?= h($documentationSectionTitle("docs-runbooks", "Runbooks")) ?></h2>
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

        <section class="developer-dashboard-section" id="docs-config-storage" aria-label="Configuration and storage map">
          <div class="developer-dashboard-section-head">
            <h2 class="developer-dashboard-section-title"><?= h($documentationSectionTitle("docs-config-storage", "Config & data")) ?></h2>
            <span class="developer-dashboard-refresh">Configuration and data map</span>
          </div>
          <div class="developer-dashboard-overview-grid developer-documentation-map-stack">
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

        <section class="developer-dashboard-section" id="docs-technical-reference" aria-label="Technical reference">
          <div class="developer-dashboard-section-head">
            <h2 class="developer-dashboard-section-title"><?= h($documentationSectionTitle("docs-technical-reference", "Technical reference")) ?></h2>
            <span class="developer-dashboard-refresh">Contracts and stable identifiers</span>
          </div>
          <div class="developer-dashboard-overview-grid developer-documentation-map-stack">
            <article class="developer-dashboard-card developer-dashboard-card-wide">
              <p class="feature-kicker">Technical contracts</p>
              <div class="developer-dashboard-glance-table">
                <?php foreach ($technicalRows as $row): ?>
                <div class="developer-dashboard-glance-row">
                  <strong><?= h((string) $row["label"]) ?></strong>
                  <span>
                    <?= h((string) $row["value"]) ?>
                    <?php if (trim((string) ($row["id"] ?? "")) !== ""): ?>
                    <code class="developer-technical-id"><?= h((string) $row["id"]) ?></code>
                    <?php endif; ?>
                  </span>
                </div>
                <?php endforeach; ?>
              </div>
            </article>
            <article class="developer-dashboard-card">
              <p class="feature-kicker">Adapter model <span class="developer-info-tip" tabindex="0" aria-label="Outbound adapters only run when enabled and consent or server policy allows them.">i<span>Outbound adapters remain optional. FNLLA runs first-party analytics, heatmaps and error monitoring without vendor tracking scripts.</span></span></p>
              <h3>External adapters stay gated</h3>
              <p class="content-text mb-0">API hooks, AI providers and remote-control endpoints are integrations, not required infrastructure. Configure them only when a project explicitly needs outbound project data.</p>
            </article>
          </div>
        </section>

        <section class="developer-dashboard-section" id="docs-changelog" aria-label="FNLLA changelog">
          <div class="developer-dashboard-section-head">
            <h2 class="developer-dashboard-section-title"><?= h($documentationSectionTitle("docs-changelog", "Changelog")) ?></h2>
            <span class="developer-dashboard-refresh">Current release line</span>
          </div>
          <article class="developer-dashboard-card developer-dashboard-card-wide">
            <p class="content-text">A short in-panel changelog belongs here because developers need release context next to framework updates, migration notes and readiness checks. The authoritative release text remains the maintained repository changelog and GitHub Release entry.</p>
            <div class="developer-dashboard-glance-table">
              <?php foreach ($changelogRows as $label => $value): ?>
              <div class="developer-dashboard-glance-row">
                <strong><?= h((string) $label) ?></strong>
                <span><?= h((string) $value) ?></span>
              </div>
              <?php endforeach; ?>
            </div>
          </article>
        </section>

        <section class="developer-dashboard-section" id="docs-source-reference" aria-label="Source-backed FNLLA documentation">
          <div class="developer-dashboard-section-head">
            <div>
              <h2 class="developer-dashboard-section-title"><?= h($documentationSectionTitle("docs-source-reference", "Source manual")) ?></h2>
              <p class="content-text mb-0">Source-backed FNLLA docs are rendered here as HTML so developers can read the maintained framework reference without opening raw Markdown files.</p>
            </div>
            <span class="developer-dashboard-refresh"><?= h((string) count($sourceDocumentation)) ?> source documents</span>
          </div>
          <div class="developer-documentation-search" role="search">
            <label class="visually-hidden" for="developer-documentation-search">Search FNLLA documentation</label>
            <input class="input" id="developer-documentation-search" type="search" placeholder="Search documentation..." autocomplete="off" data-developer-docs-search>
            <span data-developer-docs-count><?= h((string) count($sourceDocumentation)) ?> documents</span>
          </div>
          <div class="developer-documentation-source-docs">
            <?php foreach ($sourceDocumentation as $sourceIndex => $sourceDoc): ?>
            <details class="developer-documentation-source-card" data-developer-docs-card data-developer-docs-search-text="<?= h((string) $sourceDoc["search"]) ?>" <?= $sourceIndex === 0 ? "open" : "" ?>>
              <summary>
                <span>
                  <small><?= h((string) $sourceDoc["section"]) ?></small>
                  <strong><?= h((string) $sourceDoc["title"]) ?></strong>
                </span>
                <em>HTML</em>
              </summary>
              <div class="developer-documentation-source-html">
                <?= $sourceDoc["html"] ?>
              </div>
            </details>
            <?php endforeach; ?>
            <p class="developer-documentation-empty" data-developer-docs-empty hidden>No documentation sections match this search.</p>
          </div>
        </section>

        <section class="developer-dashboard-section" id="docs-installation-facts" aria-label="Installation facts">
          <div class="developer-dashboard-section-head">
            <h2 class="developer-dashboard-section-title"><?= h($documentationSectionTitle("docs-installation-facts", "Installation facts")) ?></h2>
            <span class="developer-dashboard-refresh">Current installation context</span>
          </div>
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
          </div>
        </div>

<?php require __DIR__ . "/panel-footer.php"; ?>
