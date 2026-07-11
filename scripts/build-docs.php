<?php

declare(strict_types=1);

/*
===============================================================================
FNLLA MAINTAINER SCRIPT
File: scripts\build-docs.php
Copyright (c) 2026 TechAyo LTD (techayo.co.uk). Released under the MIT License.
===============================================================================

FNLLA is produced, maintained and distributed by TechAyo LTD
(techayo.co.uk). This repository is the authoritative maintainer workspace for
the FNLLA framework released under the MIT License and its related delivery scripts, tests,
templates and release metadata.

Purpose:
- Builds the static documentation set under docs/ from one shared shell.
- Keeps guide pages aligned with the maintained Markdown source files.
*/

$repoRoot = dirname(__DIR__);
$docsRoot = $repoRoot . DIRECTORY_SEPARATOR . "docs";
$assetsRoot = $docsRoot . DIRECTORY_SEPARATOR . "assets";
$checkOnly = in_array("--check", $argv, true);
$version = read_repository_version($repoRoot . DIRECTORY_SEPARATOR . "VERSION");

$rootPages = [
    [
        "label" => "Overview",
        "href" => "index.html",
        "title" => "Overview",
        "document_title" => "Overview - FNLLA Documentation",
    ],
    [
        "label" => "Distribution",
        "href" => "distribution.html",
        "title" => "Distribution",
        "document_title" => "Distribution - FNLLA Documentation",
    ],
    [
        "label" => "Built-in Runtime",
        "href" => "fnlla-runtime.html",
        "title" => "Built-in Runtime",
        "document_title" => "Built-in Runtime - FNLLA Documentation",
    ],
    [
        "label" => "Getting Started",
        "href" => "getting-started.html",
        "title" => "Getting Started",
        "document_title" => "Getting Started - FNLLA Documentation",
    ],
    [
        "label" => "Building",
        "href" => "building.html",
        "title" => "Building",
        "document_title" => "Building - FNLLA Documentation",
    ],
    [
        "label" => "API",
        "href" => "api.html",
        "title" => "API",
        "document_title" => "API - FNLLA Documentation",
    ],
    [
        "label" => "Guides",
        "href" => "guides.html",
        "title" => "Guides",
        "document_title" => "Guides - FNLLA Documentation",
    ],
];

$guidePages = [
    [
        "label" => "Starting a New Project",
        "href" => "starting-a-new-project.html",
        "source" => "docs/STARTING-A-NEW-PROJECT.md",
        "source_name" => "STARTING-A-NEW-PROJECT.md",
        "title" => "Starting a New Project",
        "document_title" => "Starting a New Project - FNLLA Documentation",
        "lead" => "Recommended starter-export workflow for real downstream projects built on FNLLA.",
    ],
    [
        "label" => "Building with FNLLA",
        "href" => "building-with-fnlla.html",
        "source" => "docs/BUILDING-WITH-FNLLA.md",
        "source_name" => "BUILDING-WITH-FNLLA.md",
        "title" => "Building with FNLLA",
        "document_title" => "Building with FNLLA - FNLLA Documentation",
        "lead" => "Long-form implementation guidance for routes, controllers, views, forms, MySQL and auth.",
    ],
    [
        "label" => "Project Scripts Reference",
        "href" => "project-scripts-reference.html",
        "source" => "docs/PROJECT-SCRIPTS-REFERENCE.md",
        "source_name" => "PROJECT-SCRIPTS-REFERENCE.md",
        "title" => "Project Scripts Reference",
        "document_title" => "Project Scripts Reference - FNLLA Documentation",
        "lead" => "Exact responsibilities, boundaries and downstream usage notes for the scripts kept in the FNLLA project export.",
    ],
];

$guideLinkMap = [
    "./STARTING-A-NEW-PROJECT.md" => "./starting-a-new-project.html",
    "./BUILDING-WITH-FNLLA.md" => "./building-with-fnlla.html",
    "./PROJECT-SCRIPTS-REFERENCE.md" => "./project-scripts-reference.html",
];

$pagesToWrite = [
    [
        "target" => $docsRoot . DIRECTORY_SEPARATOR . "index.html",
        "content" => render_docs_page([
            "root_pages" => $rootPages,
            "guide_pages" => $guidePages,
            "current_root_label" => "Overview",
            "document_title" => "Overview - FNLLA Documentation",
            "page_title" => "Overview",
            "page_lead" => "Server-rendered PHP framework documentation for teams building websites and application surfaces on top of the FNLLA stack.",
            "version" => $version,
            "content_html" => render_overview_content($version),
        ]),
    ],
    [
        "target" => $docsRoot . DIRECTORY_SEPARATOR . "distribution.html",
        "content" => render_docs_page([
            "root_pages" => $rootPages,
            "guide_pages" => $guidePages,
            "current_root_label" => "Distribution",
            "document_title" => "Distribution - FNLLA Documentation",
            "page_title" => "Distribution",
            "page_lead" => "Repository packaging, built-in runtime boundaries and the line between downstream runtime files and maintainer-owned internals.",
            "version" => $version,
            "content_html" => render_distribution_content(),
        ]),
    ],
    [
        "target" => $docsRoot . DIRECTORY_SEPARATOR . "getting-started.html",
        "content" => render_docs_page([
            "root_pages" => $rootPages,
            "guide_pages" => $guidePages,
            "current_root_label" => "Getting Started",
            "document_title" => "Getting Started - FNLLA Documentation",
            "page_title" => "Getting Started",
            "page_lead" => "Local setup, starter export, first delivery steps and the maintainer commands worth running before any real implementation work.",
            "version" => $version,
            "content_html" => render_getting_started_content(),
        ]),
    ],
    [
        "target" => $docsRoot . DIRECTORY_SEPARATOR . "fnlla-runtime.html",
        "content" => render_docs_page([
            "root_pages" => $rootPages,
            "guide_pages" => $guidePages,
            "current_root_label" => "Built-in Runtime",
            "document_title" => "Built-in Runtime - FNLLA Documentation",
            "page_title" => "Built-in Runtime",
            "page_lead" => "How FNLLA ships one built-in runtime and how to use it when writing views.",
            "version" => $version,
            "content_html" => render_fnlla_runtime_content(),
        ]),
    ],
    [
        "target" => $docsRoot . DIRECTORY_SEPARATOR . "building.html",
        "content" => render_docs_page([
            "root_pages" => $rootPages,
            "guide_pages" => $guidePages,
            "current_root_label" => "Building",
            "document_title" => "Building - FNLLA Documentation",
            "page_title" => "Building",
            "page_lead" => "The practical delivery model for routes, controllers, views, validation, persistence, auth and built-in runtime page composition.",
            "version" => $version,
            "content_html" => render_building_content(),
        ]),
    ],
    [
        "target" => $docsRoot . DIRECTORY_SEPARATOR . "api.html",
        "content" => render_docs_page([
            "root_pages" => $rootPages,
            "guide_pages" => $guidePages,
            "current_root_label" => "API",
            "document_title" => "API - FNLLA Documentation",
            "page_title" => "API",
            "page_lead" => "Stable framework touchpoints: public entrypoints, helpers, middleware aliases, CLI commands and repository-level support contracts.",
            "version" => $version,
            "content_html" => render_api_content(),
        ]),
    ],
    [
        "target" => $docsRoot . DIRECTORY_SEPARATOR . "guides.html",
        "content" => render_docs_page([
            "root_pages" => $rootPages,
            "guide_pages" => $guidePages,
            "current_root_label" => "Guides",
            "document_title" => "Guides - FNLLA Documentation",
            "page_title" => "Guides",
            "page_lead" => "Long-form maintainers' and delivery guides generated from the Markdown sources kept in the repository.",
            "version" => $version,
            "content_html" => render_guides_content($guidePages),
        ]),
    ],
];

foreach ($guidePages as $guidePage) {
    $sourcePath = $repoRoot . DIRECTORY_SEPARATOR . str_replace("/", DIRECTORY_SEPARATOR, $guidePage["source"]);
    $markdown = (string) file_get_contents($sourcePath);
    [$guideHtml, $tocItems] = render_markdown_document($markdown, $guideLinkMap);

    $pagesToWrite[] = [
        "target" => $docsRoot . DIRECTORY_SEPARATOR . $guidePage["href"],
        "content" => render_docs_page([
            "root_pages" => $rootPages,
            "guide_pages" => $guidePages,
            "current_root_label" => "Guides",
            "root_current_aria_label" => null,
            "current_guide_href" => $guidePage["href"],
            "document_title" => $guidePage["document_title"],
            "page_title" => $guidePage["title"],
            "page_lead" => $guidePage["lead"],
            "version" => $version,
            "content_html" => render_guide_content($guideHtml, $tocItems, $guidePages, $guidePage),
        ]),
    ];
}

ensure_directory($assetsRoot);
$outdatedFiles = [];
$writtenFiles = [];

foreach ($pagesToWrite as $page) {
    $target = $page["target"];
    $content = normalize_line_endings($page["content"]);
    $current = is_file($target) ? normalize_line_endings((string) file_get_contents($target)) : null;

    if ($current === $content) {
        continue;
    }

    if ($checkOnly) {
        $outdatedFiles[] = relative_path($repoRoot, $target);
        continue;
    }

    ensure_directory(dirname($target));
    file_put_contents($target, $content);
    $writtenFiles[] = relative_path($repoRoot, $target);
}

if ($checkOnly) {
    if ($outdatedFiles !== []) {
        fwrite(STDOUT, "FNLLA docs are out of date." . PHP_EOL);

        foreach ($outdatedFiles as $outdatedFile) {
            fwrite(STDOUT, "- " . $outdatedFile . PHP_EOL);
        }

        exit(1);
    }

    fwrite(STDOUT, "FNLLA docs are in sync." . PHP_EOL);
    exit(0);
}

if ($writtenFiles === []) {
    fwrite(STDOUT, "FNLLA docs are already up to date." . PHP_EOL);
    exit(0);
}

fwrite(STDOUT, "FNLLA docs updated:" . PHP_EOL);

foreach ($writtenFiles as $writtenFile) {
    fwrite(STDOUT, "- " . $writtenFile . PHP_EOL);
}

exit(0);

function render_docs_page(array $page): string
{
    $documentTitle = escape_html($page["document_title"]);
    $pageTitle = escape_html($page["page_title"]);
    $pageLead = escape_html($page["page_lead"]);
    $version = escape_html($page["version"]);
    $rootPages = $page["root_pages"];
    $guidePages = $page["guide_pages"];
    $currentRootLabel = $page["current_root_label"];
    $rootCurrentAriaLabel = array_key_exists("root_current_aria_label", $page) ? $page["root_current_aria_label"] : $currentRootLabel;
    $contentHtml = $page["content_html"];
    $rootPagesMarkup = render_root_navigation($rootPages, $currentRootLabel, $rootCurrentAriaLabel);

    return <<<HTML
<!DOCTYPE html>
<!-- FNLLA documentation page. Copyright (c) 2026 TechAyo LTD (techayo.co.uk). Released under the MIT License. -->
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="theme-color" content="#18352f">
  <title>{$documentTitle}</title>
  <link rel="icon" href="./assets/brand/fnlla.svg" type="image/svg+xml">
  <link rel="stylesheet" href="../public/vendor/fnlla-runtime/assets/css/fnlla-runtime.css">
  <link rel="stylesheet" href="./assets/docs.css">
</head>
<body data-fnlla-theme="default">
  <a class="skip-link" href="#main-content">Skip to main content</a>

  <main class="doc-wrapper" id="main-content">
    <header class="doc-header" aria-label="FNLLA documentation shell">
      <div class="doc-header-bar">
        <span class="doc-kicker">Application framework</span>
        <span class="doc-status">Stable {$version}</span>
      </div>
      <div class="doc-header-grid">
        <p class="doc-overline">Server-rendered delivery stack</p>
        <div class="doc-brand">
          <svg class="doc-brand-mark" viewBox="0 0 560 520" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" focusable="false">
            <rect class="doc-brand-mark-tile" x="143" y="75" width="82" height="82" rx="12"></rect>
            <rect class="doc-brand-mark-tile" x="239" y="75" width="82" height="82" rx="12"></rect>
            <rect class="doc-brand-mark-tile" x="335" y="75" width="82" height="82" rx="12"></rect>
            <rect class="doc-brand-mark-tile" x="143" y="171" width="82" height="82" rx="12"></rect>
            <rect class="doc-brand-mark-tile" x="239" y="171" width="82" height="82" rx="12"></rect>
            <rect class="doc-brand-mark-tile" x="143" y="267" width="82" height="82" rx="12"></rect>
            <rect class="doc-brand-mark-tile" x="143" y="363" width="82" height="82" rx="12"></rect>
          </svg>
          <span class="doc-brand-badge" aria-hidden="true">PHP</span>
          <span class="doc-brand-separator" aria-hidden="true">-</span>
          <p class="doc-display">Documentation</p>
        </div>
        <p class="doc-lead">{$pageLead}</p>
      </div>
    </header>

    <nav class="doc-nav" aria-label="FNLLA documentation">
      <div class="doc-nav-top">
        <p class="doc-nav-label">Docs Navigation</p>
        <button class="btn btn-ghost btn-sm doc-nav-toggle" type="button" data-doc-nav-toggle aria-expanded="false" aria-controls="doc-nav-panel">
          Browse Docs
        </button>
      </div>
      <div class="doc-nav-panel" id="doc-nav-panel" data-doc-nav-panel>
{$rootPagesMarkup}
        <div class="doc-nav-controls">
          <label class="switch doc-theme-toggle">
            <input class="switch-input" data-doc-theme-toggle type="checkbox" aria-label="Enable dark mode for the docs">
            <span class="switch-slider" aria-hidden="true"></span>
            <span class="switch-label">Dark mode</span>
          </label>
        </div>
      </div>
    </nav>

{$contentHtml}

    <footer class="doc-footer" aria-label="FNLLA ownership notice">
      <p class="content-text">FNLLA &copy; 2026 TechAyo LTD (<a href="https://techayo.co.uk">techayo.co.uk</a>). Released under the MIT License.</p>
    </footer>
  </main>

  <script src="../public/vendor/fnlla-runtime/assets/js/fnlla-runtime.js"></script>
  <script src="./assets/docs.js"></script>
</body>
</html>
HTML;
}

function render_root_navigation(array $rootPages, string $activeLabel, ?string $ariaCurrentLabel = null): string
{
    $markup = [];

    foreach ($rootPages as $page) {
        $className = $page["label"] === $activeLabel ? "btn btn-outline btn-sm" : "btn btn-ghost btn-sm";
        $ariaCurrent = $ariaCurrentLabel !== null && $page["label"] === $ariaCurrentLabel ? ' aria-current="page"' : "";
        $markup[] = '        <a class="' . $className . '" href="./' . escape_html($page["href"]) . '"' . $ariaCurrent . '>' . escape_html($page["label"]) . "</a>";
    }

    return implode(PHP_EOL, $markup);
}

function render_guide_navigation(array $guidePages, ?string $currentGuideHref): string
{
    $markup = [];

    foreach ($guidePages as $page) {
        $className = $page["href"] === $currentGuideHref ? "btn btn-outline btn-sm" : "btn btn-ghost btn-sm";
        $ariaCurrent = $page["href"] === $currentGuideHref ? ' aria-current="page"' : "";
        $markup[] = '        <a class="' . $className . '" href="./' . escape_html($page["href"]) . '"' . $ariaCurrent . '>' . escape_html($page["label"]) . "</a>";
    }

    return implode(PHP_EOL, $markup);
}

function render_guide_sidebar_navigation(array $guidePages, array $currentGuide): string
{
    $markup = [];

    foreach ($guidePages as $page) {
        $label = $page["label"] === "Building with FNLLA" ? "Building" : $page["label"];
        $className = $page["href"] === $currentGuide["href"] ? "btn btn-outline btn-sm" : "btn btn-ghost btn-sm";
        $ariaCurrent = $page["href"] === $currentGuide["href"] ? ' aria-current="page"' : "";
        $markup[] = '            <a class="' . $className . '" href="./' . escape_html($page["href"]) . '"' . $ariaCurrent . '>' . escape_html($label) . "</a>";
    }

    return implode(PHP_EOL, $markup);
}

function render_overview_content(string $version): string
{
    $version = escape_html($version);

    return <<<HTML
    <section class="section pt-1">
      <div class="section-header">
        <h2 class="section-title">Start here</h2>
        <p class="section-text">FNLLA is the maintained PHP application framework in the FNLLA product family. This docs set maps the repository contract, the starter-export workflow and the practical delivery rules for downstream websites and applications.</p>
      </div>

      <div class="grid">
        <article class="card">
          <h2 class="card-title">Stable repository contract</h2>
          <pre><code>public/index.php
public/router.php
public/vendor/fnlla-runtime/
src/
routes/
views/</code></pre>
          <p class="card-text">Version {$version} keeps the framework intentionally explicit: request flow, built-in runtime boundary and maintainer scripts are visible in the repository rather than hidden behind packaging layers.</p>
        </article>

        <article class="card">
          <h2 class="card-title">Build real projects outside this repo</h2>
          <ul class="doc-checklist">
            <li><strong>Framework mode:</strong> maintain <code>techayoDEV/fnlla</code>, its scripts, docs and shared delivery foundations here.</li>
            <li><strong>Project mode:</strong> export a downstream starter with <code>php fnlla make:project</code> and build the real client application in that separate directory.</li>
            <li><strong>UI rule:</strong> keep the built-in runtime under <code>public/vendor/fnlla-runtime/</code> as the only supported UI layer.</li>
          </ul>
        </article>

        <article class="card">
          <h2 class="card-title">Read next by task</h2>
          <ul class="doc-checklist">
            <li><a href="./distribution.html"><code>distribution.html</code></a> for runtime and repository boundaries.</li>
            <li><a href="./getting-started.html"><code>getting-started.html</code></a> for local setup and starter export.</li>
            <li><a href="./building.html"><code>building.html</code></a> for route, controller, view and form patterns.</li>
            <li><a href="./guides.html"><code>guides.html</code></a> for the long-form Markdown guides rendered as HTML.</li>
          </ul>
        </article>
      </div>
    </section>

    <section class="section">
      <div class="section-header">
        <h2 class="section-title">Documentation routes</h2>
        <p class="section-text">Use the shortest route to the kind of answer you need: packaging, first setup, implementation patterns, framework API or long-form maintainers' guidance.</p>
      </div>

      <div class="doc-link-grid">
        <a class="doc-link-card" href="./distribution.html">
          <span class="doc-link-label">Packaging</span>
          <p class="doc-link-title">Distribution</p>
          <p class="doc-link-text">What belongs to the downstream runtime surface, what stays maintainer-only and how the built-in runtime should be treated.</p>
        </a>
        <a class="doc-link-card" href="./fnlla-runtime.html">
          <span class="doc-link-label">Runtime</span>
          <p class="doc-link-title">Built-in Runtime</p>
          <p class="doc-link-text">Where the built-in runtime lives, how to keep it healthy and how to use it while composing FNLLA views.</p>
        </a>
        <a class="doc-link-card" href="./getting-started.html">
          <span class="doc-link-label">Bootstrap</span>
          <p class="doc-link-title">Getting Started</p>
          <p class="doc-link-text">Local server boot, environment setup, starter export and the first files you normally touch in a real delivery.</p>
        </a>
        <a class="doc-link-card" href="./building.html">
          <span class="doc-link-label">Implementation</span>
          <p class="doc-link-title">Building</p>
          <p class="doc-link-text">Practical patterns for request flow, controllers, views, forms, validation, migrations and protected areas.</p>
        </a>
        <a class="doc-link-card" href="./api.html">
          <span class="doc-link-label">Contract</span>
          <p class="doc-link-title">API</p>
          <p class="doc-link-text">Helpers, middleware aliases, CLI commands and the public framework touchpoints downstream projects are meant to rely on.</p>
        </a>
        <a class="doc-link-card" href="./guides.html">
          <span class="doc-link-label">Workflow</span>
          <p class="doc-link-title">Guides</p>
          <p class="doc-link-text">HTML guide pages generated from the maintained Markdown sources already living under <code>docs/</code>.</p>
        </a>
      </div>
    </section>

    <section class="section">
      <div class="section-header">
        <h2 class="section-title">Keep these rules in your head</h2>
        <p class="section-text">These are the habits that keep FNLLA strong instead of slowly turning it into a one-project-only code dump.</p>
      </div>

      <div class="doc-card-grid doc-card-grid-2">
        <article class="card">
          <h3 class="card-title">Export before delivery work</h3>
          <p class="card-text">Treat this repository as the framework source of truth. Use <code>make:project</code> to start real downstream websites and apps in their own repositories.</p>
        </article>
        <article class="card">
          <h3 class="card-title">Integrated UI runtime stays mandatory</h3>
          <p class="card-text">Do not replace the built-in runtime with Tailwind, Bootstrap or another CSS framework. The shared layout and guard rails assume the vendored runtime is present.</p>
        </article>
        <article class="card">
          <h3 class="card-title">MySQL is the official database target</h3>
          <p class="card-text">The framework contract assumes PHP 8.3 with <code>pdo_mysql</code> and a reachable MySQL server for application persistence work.</p>
        </article>
        <article class="card">
          <h3 class="card-title">Validation is part of maintenance</h3>
          <p class="card-text">Run tests, lint, UI runtime validation, version-manifest checks and docs sync checks before calling a release state finished.</p>
        </article>
      </div>
    </section>
HTML;
}

function render_fnlla_runtime_content(): string
{
    return <<<HTML
    <section class="section pt-1">
      <div class="section-header">
        <h2 class="section-title">The runtime is built into FNLLA</h2>
        <p class="section-text">FNLLA is not designed to be UI-agnostic in the official stack. The maintained application shell, shared layout and validation flow all assume that the built-in runtime is present as the only supported UI layer.</p>
      </div>

      <div class="doc-card-grid doc-card-grid-2">
        <article class="card">
          <h2 class="card-title">Where it lives</h2>
          <p class="card-text">The built-in runtime lives under <code>public/vendor/fnlla-runtime/</code>. That directory is the local downstream copy used by the framework and by starter exports created from this repository.</p>
        </article>
        <article class="card">
          <h2 class="card-title">Why it matters</h2>
          <p class="card-text">Shared views, docs shells, theme switching, consent UI and application primitives all expect the shipped runtime classes, tokens and JavaScript behavior to exist locally.</p>
        </article>
      </div>
    </section>

    <section class="section">
      <div class="section-header">
        <h2 class="section-title">How to use it in views</h2>
        <p class="section-text">Downstream work should treat the runtime as part of FNLLA itself. When you build views, start from the shipped runtime primitives instead of introducing a second design system.</p>
      </div>

      <div class="doc-card-grid doc-card-grid-2">
        <article class="card">
          <h3 class="card-title">View-writing rules</h3>
          <ul class="doc-checklist">
            <li>Keep shared shell markup and asset loading in <code>views/layouts/app.php</code>.</li>
            <li>Keep page body markup in <code>views/pages/</code>.</li>
            <li>Build with the shipped <code>section</code>, <code>container</code>, <code>stack</code>, <code>grid</code>, <code>card</code>, <code>btn</code> and form primitives first.</li>
            <li>Use <code>public/assets/app.css</code> only for project-specific refinements that the built-in runtime does not already cover.</li>
          </ul>
        </article>
        <article class="card">
          <h3 class="card-title">Healthy starter shape</h3>
          <pre><code>&lt;section class="section"&gt;
  &lt;div class="container"&gt;
    &lt;div class="stack gap-lg"&gt;
      &lt;header class="stack gap-sm"&gt;...&lt;/header&gt;
      &lt;div class="grid grid-2 gap-md"&gt;...&lt;/div&gt;
    &lt;/div&gt;
  &lt;/div&gt;
&lt;/section&gt;</code></pre>
          <p class="card-text">That is the default mental model: page content in <code>views/pages/</code>, global shell in the layout, runtime classes for structure, then narrow project styling where needed.</p>
        </article>
      </div>
    </section>

    <section class="section">
      <div class="section-header">
        <h2 class="section-title">Supported runtime boundary</h2>
        <p class="section-text">The runtime is part of FNLLA, not a replaceable add-on.</p>
      </div>

      <div class="doc-mini-grid">
        <article class="card">
          <h3 class="card-title">Supported</h3>
          <ul class="doc-checklist">
            <li>Keep the built-in runtime under <code>public/vendor/fnlla-runtime/</code>.</li>
            <li>Sync updates through the official TechAyo-maintained runtime workflow.</li>
            <li>Build pages with the shipped runtime layout, card, button, alert and form primitives.</li>
          </ul>
        </article>
        <article class="card">
          <h3 class="card-title">Unsupported</h3>
          <ul class="doc-checklist">
            <li>Replacing the built-in runtime with Tailwind, Bootstrap or another framework in the official stack.</li>
            <li>Loading third-party UI assets from random CDNs instead of the built-in runtime.</li>
            <li>Changing shared shell markup so the framework can no longer validate the runtime contract.</li>
          </ul>
        </article>
        <article class="card">
          <h3 class="card-title">Practical consequence</h3>
          <p class="card-text">FNLLA docs, starter pages and application shell all assume the same runtime classes and behavior, so view work stays aligned when you build with those primitives from the start.</p>
        </article>
      </div>
    </section>

    <section class="section">
      <div class="section-header">
        <h2 class="section-title">Full starter shell syntax</h2>
        <p class="section-text">The built-in runtime is meant to be used through the full FNLLA starter shell, not only through isolated page fragments. The real baseline is the shared layout in <code>views/layouts/app.php</code> plus page content rendered inside its <code>main</code> area.</p>
      </div>

      <div class="doc-card-grid doc-card-grid-2">
        <article class="card">
          <h3 class="card-title">Shared starter layout</h3>
          <pre><code class="language-php">&lt;!DOCTYPE html&gt;
&lt;html lang="en"&gt;
&lt;head&gt;
  &lt;meta charset="UTF-8"&gt;
  &lt;meta name="viewport" content="width=device-width, initial-scale=1.0"&gt;
  &lt;meta name="theme-color" content="#1A4137"&gt;
  &lt;title&gt;&lt;?= h(\$pageMeta["title"]) ?&gt;&lt;/title&gt;
  &lt;link rel="stylesheet" href="&lt;?= h(asset("vendor/fnlla-runtime/assets/css/fnlla-runtime.css")) ?&gt;"&gt;
  &lt;link rel="stylesheet" href="&lt;?= h(asset("assets/app.css")) ?&gt;"&gt;
&lt;/head&gt;
&lt;body data-fnlla-theme="default"&gt;
  &lt;header&gt;
    &lt;section class="section"&gt;
      &lt;div class="container"&gt;
        Header / Navigation
      &lt;/div&gt;
    &lt;/section&gt;
  &lt;/header&gt;

  &lt;main&gt;
    &lt;?= \$content ?&gt;
  &lt;/main&gt;

  &lt;footer&gt;
    &lt;section class="section"&gt;
      &lt;div class="container"&gt;
        Footer
      &lt;/div&gt;
    &lt;/section&gt;
  &lt;/footer&gt;

  &lt;script src="&lt;?= h(asset("vendor/fnlla-runtime/assets/js/fnlla-runtime.js")) ?&gt;"&gt;&lt;/script&gt;
&lt;/body&gt;
&lt;/html&gt;</code></pre>
          <p class="card-text">This is the real starter baseline: runtime assets in <code>head</code>, shared navigation in <code>header</code>, page injection through <code>&lt;?= \$content ?&gt;</code> inside <code>main</code>, then a simple footer and runtime JavaScript at the end of <code>body</code>.</p>
        </article>
        <article class="card">
          <h3 class="card-title">Starter route, controller and page body</h3>
          <pre><code class="language-php">\$router->get("/", [PageController::class, "home"])->name("home");
\$router->get("/about", [PageController::class, "about"])->name("about");
\$router->get("/services", [PageController::class, "services"])->name("services");
\$router->get("/contact", [PageController::class, "contact"])->name("contact");

public function home(Request \$request): Response
{
    return \$this->view("pages/home", [
        "pageTitle" => "Home",
        "pageTitleHome" => true,
        "starterPages" => [
            [
                "title" => "Home",
                "text" => "The opening story starts here.",
            ],
        ],
    ]);
}

&lt;section class="section pt-1"&gt;
  &lt;div class="container"&gt;
    &lt;section class="hero hero-compact"&gt;
      &lt;div class="grid gap-md hero-copy"&gt;
        &lt;h1 class="hero-title"&gt;...&lt;/h1&gt;
        &lt;p class="hero-text"&gt;...&lt;/p&gt;
        &lt;div class="hero-actions"&gt;
          &lt;a class="btn btn-primary btn-xl" href="&lt;?= h(route("services")) ?&gt;"&gt;View services&lt;/a&gt;
        &lt;/div&gt;
      &lt;/div&gt;
    &lt;/section&gt;
  &lt;/div&gt;
&lt;/section&gt;

&lt;?php foreach (\$starterPages as \$starterPage): ?&gt;
  &lt;article class="feature-card"&gt;
    &lt;h3 class="content-title"&gt;&lt;?= h(\$starterPage["title"]) ?&gt;&lt;/h3&gt;
    &lt;p class="content-text"&gt;&lt;?= h(\$starterPage["text"]) ?&gt;&lt;/p&gt;
  &lt;/article&gt;
&lt;?php endforeach; ?&gt;</code></pre>
          <p class="card-text">This is the second half of the starter: routes point at <code>PageController</code>, the controller passes plain PHP data into the view and the page body stays focused on sections rendered inside the shared <code>main</code> shell.</p>
        </article>
      </div>
    </section>

    <section class="section">
      <div class="section-header">
        <h2 class="section-title">Sync and validation workflow</h2>
        <p class="section-text">When the maintained runtime source changes, update the built-in runtime deliberately and validate it before treating the repository as release-ready.</p>
      </div>

      <div class="grid grid-2 gap-md">
        <article class="card">
          <h3 class="card-title">Recommended commands</h3>
          <pre><code class="language-bash">php fnlla fnlla-runtime:sync
php fnlla fnlla-runtime:validate
php scripts/build-docs.php --check</code></pre>
          <p class="card-text">Use the explicit sync command when you want to pull in maintained runtime changes. The timed development guard keeps local state fresh and can auto-repair a missing built-in runtime, while these commands remain the deliberate maintainer workflow.</p>
        </article>
        <article class="card">
          <h3 class="card-title">Operational rule</h3>
          <p class="card-text">If the maintained runtime changes upstream, sync the vendored copy before shipping FNLLA changes that rely on the new runtime behavior or styles.</p>
        </article>
      </div>
    </section>

    <section class="section">
      <div class="section-header">
        <h2 class="section-title">Starter-export consequence</h2>
        <p class="section-text">A new project created from FNLLA inherits the built-in runtime immediately, so downstream teams can build pages without adding a separate UI package step.</p>
      </div>

      <div class="doc-flow-grid">
        <article class="card">
          <p class="doc-link-label">1. Export</p>
          <h3 class="card-title">Project scaffold</h3>
          <p class="card-text">The starter export includes the application code and the built-in runtime together.</p>
        </article>
        <article class="card">
          <p class="doc-link-label">2. Build</p>
          <h3 class="card-title">Shared layout</h3>
          <p class="card-text">Views render against the same local runtime already used by the framework docs and app shell.</p>
        </article>
        <article class="card">
          <p class="doc-link-label">3. Maintain</p>
          <h3 class="card-title">Future updates</h3>
          <p class="card-text">Later UI improvements are pulled in by syncing the built-in runtime instead of introducing a second styling system.</p>
        </article>
      </div>
    </section>
HTML;
}

function render_distribution_content(): string
{
    return <<<HTML
    <section class="section pt-1">
      <div class="section-header">
        <h2 class="section-title">Repository and runtime boundary</h2>
        <p class="section-text">FNLLA is maintained as a full repository, but downstream consumers should only treat a smaller subset as the supported application runtime surface.</p>
      </div>

      <div class="doc-card-grid doc-card-grid-2">
        <article class="card">
          <h2 class="card-title">Supported downstream runtime surface</h2>
          <ul class="doc-checklist">
            <li><code>public/index.php</code> and <code>public/router.php</code> as HTTP entrypoints.</li>
            <li><code>public/assets/</code> for project-owned public assets.</li>
            <li><code>public/vendor/fnlla-runtime/</code> for the built-in authoritative UI runtime.</li>
            <li><code>views/layouts/</code> and <code>views/pages/</code> as the server-rendered delivery layer.</li>
          </ul>
        </article>
        <article class="card">
          <h2 class="card-title">Maintainer-owned internals</h2>
          <ul class="doc-checklist">
            <li><code>bootstrap/</code>, <code>config/</code>, <code>routes/</code> and <code>src/</code> define framework behavior.</li>
            <li><code>database/</code> and <code>tests/</code> keep schema, seed and repository-level verification logic.</li>
            <li><code>scripts/</code> owns sync, validation, lint and docs-generation routines.</li>
            <li><code>MANIFEST.json</code>, <code>VERSION</code>, <code>README.md</code> and <code>LICENSE.md</code> are release state files, not app content.</li>
          </ul>
        </article>
      </div>
    </section>

    <section class="section">
      <div class="section-header">
        <h2 class="section-title">What ships together</h2>
        <p class="section-text">A downstream project exported from this repository receives a working application base rather than a bare framework package.</p>
      </div>

      <div class="grid grid-2 gap-md">
        <article class="card">
          <h3 class="card-title">Included in a starter export</h3>
          <pre><code>bootstrap/
config/
database/
lang/
public/
routes/
src/
storage/
tests/
views/</code></pre>
          <p class="card-text">The export already includes runtime code, templates, validation scripts and the built-in UI runtime so the new project can boot immediately.</p>
        </article>
        <article class="card">
          <h3 class="card-title">Intentionally excluded from starter export</h3>
          <pre><code>.git
.github
README.md
CODE_OF_CONDUCT.md
SECURITY.md</code></pre>
          <p class="card-text">Framework-maintainer metadata stays in the source repository so the downstream project starts with its own Git history and its own project-level documentation.</p>
        </article>
      </div>
    </section>

    <section class="section">
      <div class="section-header">
        <h2 class="section-title">Integrated runtime boundary</h2>
        <p class="section-text">FNLLA is not UI-agnostic in the official stack. The built-in runtime is a first-class part of the framework, not an optional styling preference.</p>
      </div>

      <div class="doc-mini-grid">
        <article class="card">
          <h3 class="card-title">Supported rule</h3>
          <p class="card-text">Keep the built-in runtime under <code>public/vendor/fnlla-runtime/</code> and sync it only from the official TechAyo-maintained source of truth.</p>
        </article>
        <article class="card">
          <h3 class="card-title">Unsupported rule</h3>
          <p class="card-text">Do not swap in Tailwind, Bootstrap, Bulma, Foundation, UIkit, Materialize or Semantic UI in the official framework stack.</p>
        </article>
        <article class="card">
          <h3 class="card-title">Operational consequence</h3>
          <p class="card-text">Bootstrap, validation and the shared application layout all assume the shipped runtime shell structure and class families stay intact.</p>
        </article>
      </div>
    </section>
HTML;
}

function render_getting_started_content(): string
{
    return <<<HTML
    <section class="section pt-1">
      <div class="section-header">
        <h2 class="section-title">Quickstart</h2>
        <p class="section-text">The fastest safe path is: keep this repo as the framework source, export a project for the real delivery, configure MySQL and validate the built-in runtime before building features.</p>
      </div>

      <div class="grid">
        <article class="card">
          <h2 class="card-title">Run the maintained framework locally</h2>
          <pre><code>cd /workspace/fnlla
php -S 127.0.0.1:8080 -t public public/router.php</code></pre>
          <p class="card-text">Open <code>http://127.0.0.1:8080</code>. For Apache-based hosting, set the document root to <code>public/</code> and keep <code>public/.htaccess</code> in place.</p>
        </article>

        <article class="card">
          <h2 class="card-title">Environment essentials</h2>
          <ul class="doc-checklist">
            <li>Copy <code>.env.example</code> to <code>.env</code>.</li>
            <li>Set <code>APP_URL</code>.</li>
            <li>Set <code>DB_HOST</code>, <code>DB_PORT</code>, <code>DB_DATABASE</code>, <code>DB_USERNAME</code> and <code>DB_PASSWORD</code>.</li>
            <li>Set <code>CONTACT_NOTIFICATION_EMAIL</code> when using the contact flow.</li>
            <li>The template starts with <code>APP_ENV=development</code>, <code>APP_DEBUG=true</code> and <code>SESSION_SECURE=false</code> so local HTTP on <code>127.0.0.1</code> works without session-cookie surprises.</li>
            <li>Before production deployment, switch those values back to production-safe settings and serve the app over HTTPS.</li>
          </ul>
        </article>

        <article class="card">
          <h2 class="card-title">Starter export workflow</h2>
          <pre><code>php fnlla make:project ..\my-new-project "My New Project"</code></pre>
          <p class="card-text">Use the exported directory as the real application repository. That keeps framework maintenance and project-specific delivery work cleanly separated.</p>
        </article>
      </div>
    </section>

    <section class="section">
      <div class="section-header">
        <h2 class="section-title">Recommended first steps for a new downstream project</h2>
        <p class="section-text">The exported project already runs, but the first pass should focus on wiring, runtime validation and replacing the demonstration surface with the actual product flow.</p>
      </div>

      <div class="doc-card-grid doc-card-grid-2">
        <article class="card">
          <h3 class="card-title">Initial commands</h3>
          <pre><code>php fnlla fnlla-runtime:sync
php fnlla fnlla-runtime:validate
php fnlla framework:update --check --github
php scripts/test.php
php scripts/lint.php
php scripts/validate-version-manifest.php</code></pre>
        </article>
        <article class="card">
          <h3 class="card-title">First files to review</h3>
          <ul class="doc-checklist">
            <li><code>config/app.php</code></li>
            <li><code>routes/web.php</code></li>
            <li><code>src/Controllers/</code></li>
            <li><code>views/pages/</code></li>
            <li><code>public/assets/app.css</code></li>
            <li><code>database/migrations/</code></li>
          </ul>
        </article>
      </div>
    </section>

    <section class="section">
      <div class="section-header">
        <h2 class="section-title">When to stay in this repo</h2>
        <p class="section-text">Direct work inside <code>techayoDEV/fnlla</code> is still the right move when the goal is framework maintenance rather than project delivery.</p>
      </div>

      <div class="doc-mini-grid">
        <article class="card">
          <h3 class="card-title">Good reasons</h3>
          <p class="card-text">Hardening shared routing, auth, migrations, docs, scripts, source layout or the starter export itself.</p>
        </article>
        <article class="card">
          <h3 class="card-title">Bad reasons</h3>
          <p class="card-text">Building one client website directly in the framework repository and letting project-specific pages or schema pollute the shared base.</p>
        </article>
        <article class="card">
          <h3 class="card-title">Reference guide</h3>
          <p class="card-text">Use <a href="./starting-a-new-project.html"><code>starting-a-new-project.html</code></a> for the full reasoning and starter-export flow.</p>
        </article>
      </div>
    </section>
HTML;
}

function render_building_content(): string
{
    return <<<HTML
    <section class="section pt-1">
      <div class="section-header">
        <h2 class="section-title">Build with the grain of the framework</h2>
        <p class="section-text">FNLLA stays fast and legible when delivery work follows the small request lifecycle that already exists in the repository instead of layering new abstractions too early.</p>
      </div>

      <div class="doc-flow-grid">
        <article class="card">
          <p class="doc-link-label">1. Request</p>
          <h2 class="card-title">Entrypoints</h2>
          <p class="card-text"><code>public/index.php</code> and <code>public/router.php</code> are the maintained HTTP entrypoints.</p>
        </article>
        <article class="card">
          <p class="doc-link-label">2. Bootstrap</p>
          <h2 class="card-title">Bootstrap and router</h2>
          <p class="card-text"><code>bootstrap/app.php</code>, <code>bootstrap/common.php</code> and <code>bootstrap/router.php</code> wire environment, services, middleware aliases and route resolution.</p>
        </article>
        <article class="card">
          <p class="doc-link-label">3. Delivery</p>
          <h2 class="card-title">Controller or closure</h2>
          <p class="card-text">A controller action or a small closure builds the response payload and decides whether the output is HTML, JSON or a redirect.</p>
        </article>
        <article class="card">
          <p class="doc-link-label">4. Response</p>
          <h2 class="card-title">View or API output</h2>
          <p class="card-text"><code>views/layouts/app.php</code> owns the HTML shell, while <code>Response::json()</code> and <code>JsonResource</code> cover structured API output.</p>
        </article>
      </div>
    </section>

    <section class="section">
      <div class="section-header">
        <h2 class="section-title">Page-building pattern</h2>
        <p class="section-text">New product surfaces usually land in four moves: route, controller, view and optional persistence or auth rules.</p>
      </div>

      <div class="grid grid-2 gap-md">
        <article class="card">
          <h3 class="card-title">Route and controller</h3>
          <pre><code>\$router->get("/services", [PageController::class, "services"])->name("services");

public function services(Request \$request): Response
{
    return \$this->view("pages/services", [
        "pageTitle" => "Services",
    ]);
}</code></pre>
        </article>
        <article class="card">
          <h3 class="card-title">View structure</h3>
          <pre><code>&lt;section class="section"&gt;
  &lt;div class="container"&gt;
    &lt;div class="grid grid-2 gap-md"&gt;...&lt;/div&gt;
  &lt;/div&gt;
&lt;/section&gt;</code></pre>
          <p class="card-text">Keep page templates under <code>views/pages/</code>, let the shared layout own the document shell and shared runtime assets, and compose the page with built-in runtime classes first.</p>
        </article>
      </div>
    </section>

    <section class="section">
      <div class="section-header">
        <h2 class="section-title">Built-in runtime first</h2>
        <p class="section-text">When you write a new view, think in terms of the runtime FNLLA already ships rather than a second front-end layer you add later.</p>
      </div>

      <div class="doc-card-grid doc-card-grid-2">
        <article class="card">
          <h3 class="card-title">Reach for these first</h3>
          <ul class="doc-checklist">
            <li><code>section</code> and <code>container</code> for page framing</li>
            <li><code>stack</code> and <code>grid</code> for spacing and layout</li>
            <li><code>card</code>, <code>badge</code> and <code>btn</code> for common UI blocks</li>
            <li><code>input</code>, <code>select</code> and <code>textarea</code> for forms</li>
          </ul>
        </article>
        <article class="card">
          <h3 class="card-title">Avoid early drift</h3>
          <ul class="doc-checklist">
            <li>Do not load a second CSS framework.</li>
            <li>Do not rebuild the global shell inside a page template.</li>
            <li>Do not bypass runtime tokens when the existing spacing, color or typography scale already fits the need.</li>
          </ul>
        </article>
      </div>
    </section>

    <section class="section">
      <div class="section-header">
        <h2 class="section-title">Forms, validation and redirects</h2>
        <p class="section-text">The framework is happiest when forms follow a predictable GET page + POST submit + validation + redirect pattern.</p>
      </div>

      <div class="doc-card-grid doc-card-grid-2">
        <article class="card">
          <h3 class="card-title">Form rules</h3>
          <ul class="doc-checklist">
            <li>Submit to a named POST route.</li>
            <li>Use <code>csrf_field()</code> in every state-changing form.</li>
            <li>Validate before side effects.</li>
            <li>Flash errors or success state back into the next request.</li>
            <li>Redirect after success instead of rendering directly from the POST.</li>
          </ul>
        </article>
        <article class="card">
          <h3 class="card-title">Validation example</h3>
          <pre><code>\$payload = [
    "email" => trim((string) \$request->input("email", "")),
];

\$this->validate(\$payload, [
    "email" => ["required", "email", "max:160"],
]);</code></pre>
        </article>
      </div>
    </section>

    <section class="section">
      <div class="section-header">
        <h2 class="section-title">Persistence and protected areas</h2>
        <p class="section-text">Use MySQL for official projects, keep query logic out of views and rely on middleware plus authorization gates for protected sections.</p>
      </div>

      <div class="doc-mini-grid">
        <article class="card">
          <h3 class="card-title">Database workflow</h3>
          <p class="card-text">Create migrations under <code>database/migrations/</code>, run them with <code>php fnlla migrate</code> and keep repeated data access in controllers or dedicated services.</p>
        </article>
        <article class="card">
          <h3 class="card-title">Protected routes</h3>
          <p class="card-text">Use <code>auth</code> middleware for identity checks and <code>authorize()</code> or route-level authorization for capabilities such as admin access.</p>
        </article>
        <article class="card">
          <h3 class="card-title">Reference guide</h3>
          <p class="card-text">Use <a href="./building-with-fnlla.html"><code>building-with-fnlla.html</code></a> for the longer delivery playbook and examples.</p>
        </article>
      </div>
    </section>
HTML;
}

function render_api_content(): string
{
    return <<<HTML
    <section class="section pt-1">
      <div class="section-header">
        <h2 class="section-title">Stable touchpoints</h2>
        <p class="section-text">These are the framework surfaces downstream projects are expected to depend on directly, and the ones maintainers should treat carefully when evolving the repository.</p>
      </div>

      <div class="doc-card-grid doc-card-grid-2">
        <article class="card">
          <h2 class="card-title">Public HTTP entrypoints</h2>
          <ul class="doc-checklist">
            <li><code>public/index.php</code></li>
            <li><code>public/router.php</code></li>
            <li><code>views/layouts/app.php</code> as the shared HTML shell</li>
            <li><code>public/vendor/fnlla-runtime/</code> as the mandatory UI runtime bundle</li>
          </ul>
        </article>
        <article class="card">
          <h2 class="card-title">Helper surface</h2>
          <ul class="doc-checklist">
            <li><code>asset()</code> for public asset URLs</li>
            <li><code>route()</code> for named route generation</li>
            <li><code>h()</code> for escaped output</li>
            <li><code>csrf_field()</code> and <code>csrf_token()</code> for form protection</li>
            <li><code>auth()</code> for the auth manager access point</li>
          </ul>
        </article>
      </div>
    </section>

    <section class="section">
      <div class="section-header">
        <h2 class="section-title">Middleware aliases</h2>
        <p class="section-text">The router bootstrap registers a small and explicit middleware map, and route definitions build on top of those aliases.</p>
      </div>

      <div class="grid grid-2 gap-md">
        <article class="card">
          <h3 class="card-title">Registered aliases</h3>
          <pre><code>csrf
auth
authorize
cors
throttle</code></pre>
          <p class="card-text">These are bound in <code>bootstrap/router.php</code> and are the supported names used in route definitions and groups.</p>
        </article>
        <article class="card">
          <h3 class="card-title">Route-level helpers</h3>
          <pre><code>\$router->get("/dashboard", [AuthController::class, "dashboard"])
    ->middleware("auth")
    ->authorize("view-dashboard")
    ->name("dashboard");</code></pre>
          <p class="card-text"><code>authorize()</code> and <code>throttle()</code> add metadata and the matching middleware behavior to the route definition.</p>
        </article>
      </div>
    </section>

    <section class="section">
      <div class="section-header">
        <h2 class="section-title">CLI surface</h2>
        <p class="section-text">The maintained console application covers project export, schema work, queueing, scheduling and repository maintenance routines.</p>
      </div>

      <div class="doc-card-grid doc-card-grid-2">
        <article class="card">
          <h3 class="card-title">Framework and runtime commands</h3>
          <ul class="doc-checklist">
            <li><code>php fnlla make:project</code></li>
            <li><code>php fnlla fnlla-runtime:sync</code></li>
            <li><code>php fnlla fnlla-runtime:validate</code></li>
            <li><code>php fnlla framework:update --check --github</code></li>
            <li><code>php fnlla framework:update --check --source &lt;path-to-fnlla&gt;</code> when a local maintainer checkout is preferred</li>
            <li><code>php fnlla route:list</code></li>
            <li><code>php fnlla version:status</code> and <code>php fnlla version:sync</code></li>
          </ul>
        </article>
        <article class="card">
          <h3 class="card-title">Data and background work</h3>
          <ul class="doc-checklist">
            <li><code>php fnlla migrate</code>, <code>migrate:rollback</code> and <code>migrate:status</code></li>
            <li><code>php fnlla db:seed</code></li>
            <li><code>php fnlla queue:work</code></li>
            <li><code>php fnlla schedule:run</code></li>
            <li><code>php fnlla cache:clear</code></li>
          </ul>
        </article>
      </div>
    </section>

    <section class="section">
      <div class="section-header">
        <h2 class="section-title">Support files and machine-readable state</h2>
        <p class="section-text">Versioning, ownership and runtime expectations are intentionally visible in repository metadata rather than hidden inside package registries.</p>
      </div>

      <div class="doc-mini-grid">
        <article class="card">
          <h3 class="card-title">State files</h3>
          <p class="card-text"><code>MANIFEST.json</code>, <code>README.md</code>, <code>VERSION</code> and <code>LICENSE.md</code> should stay aligned whenever release metadata changes.</p>
        </article>
        <article class="card">
          <h3 class="card-title">Validation scripts</h3>
          <p class="card-text"><code>scripts/validate-fnlla-runtime.php</code>, <code>scripts/validate-version-manifest.php</code> and <code>scripts/build-docs.php --check</code> are the core content-contract checks.</p>
        </article>
        <article class="card">
          <h3 class="card-title">Source of truth</h3>
          <p class="card-text">FNLLA is the public source of truth for the official stack maintained by TechAyo LTD. Third-party package registries are outside the official maintainer workflow.</p>
        </article>
      </div>
    </section>
HTML;
}

function render_guides_content(array $guidePages): string
{
    $cards = [];

    foreach ($guidePages as $page) {
        $cards[] = <<<HTML
        <a class="doc-link-card" href="./{$page["href"]}">
          <span class="doc-link-label">Guide</span>
          <p class="doc-link-title">{$page["label"]}</p>
          <p class="doc-link-text">{$page["lead"]} Source file: <code>docs/{$page["source_name"]}</code>.</p>
        </a>
HTML;
    }

    $cardsMarkup = implode(PHP_EOL, $cards);

    return <<<HTML
    <section class="section pt-1">
      <div class="section-header">
        <h2 class="section-title">Guide library</h2>
        <p class="section-text">These guide pages are generated from the Markdown files kept under <code>docs/</code>, so long-form content can stay authorable in plain text while still shipping in the same docs shell as the rest of the reference set.</p>
      </div>

      <div class="doc-link-grid">
{$cardsMarkup}
      </div>
    </section>

    <section class="section">
      <div class="section-header">
        <h2 class="section-title">Maintainer note</h2>
        <p class="section-text">After editing a Markdown guide or changing the shared docs shell, run the docs builder so the published HTML stays in sync.</p>
      </div>

      <article class="card">
        <pre><code>php scripts/build-docs.php
php scripts/build-docs.php --check</code></pre>
      </article>
    </section>
HTML;
}

function render_guide_content(string $guideHtml, array $tocItems, array $guidePages, array $currentGuide): string
{
    $tocMarkup = render_guide_toc($tocItems);
    $guidePagesMarkup = render_guide_sidebar_navigation($guidePages, $currentGuide);

    return <<<HTML
    <section class="section pt-1">
      <div class="section-header">
        <h2 class="section-title">{$currentGuide["title"]}</h2>
        <p class="section-text">{$currentGuide["lead"]}</p>
      </div>

      <div class="doc-guide-layout">
        <article class="card doc-guide-card doc-guide-prose doc-guide-prose-numbered">
{$guideHtml}
        </article>
        <aside class="doc-guide-sidebar scrollbar scrollbar-thin">
          <div class="card doc-guide-card">
            <p class="doc-panel-label">Guide pages</p>
            <div class="doc-guide-nav" aria-label="Guide pages">
{$guidePagesMarkup}
            </div>
          </div>
          <div class="card doc-guide-card">
            <p class="doc-panel-label">On this page</p>
{$tocMarkup}
          </div>
        </aside>
      </div>
    </section>
HTML;
}

function render_guide_toc(array $tocItems): string
{
    if ($tocItems === []) {
        return '            <p class="card-text">No generated sections were found in the source document.</p>';
    }

    $items = [];

    foreach ($tocItems as $item) {
        $levelClass = $item["level"] >= 3 ? "doc-guide-toc-level-3" : "doc-guide-toc-level-2";
        $items[] = '              <a class="doc-guide-toc-link ' . $levelClass . '" href="#' . escape_html($item["id"]) . '">' . escape_html($item["text"]) . "</a>";
    }

    return "            <div class=\"doc-guide-toc doc-guide-toc-numbered\">\n" . implode(PHP_EOL, $items) . "\n            </div>";
}

function render_markdown_document(string $markdown, array $linkMap): array
{
    $lines = preg_split("/\r\n|\n|\r/", normalize_line_endings($markdown)) ?: [];
    $html = [];
    $toc = [];
    $paragraph = [];
    $listItems = [];
    $listType = null;
    $inCodeBlock = false;
    $codeLanguage = "";
    $codeLines = [];

    $flushParagraph = static function () use (&$paragraph, &$html, $linkMap): void {
        if ($paragraph === []) {
            return;
        }

        $text = trim(implode(" ", $paragraph));
        $html[] = "            <p>" . render_inline_markdown($text, $linkMap) . "</p>";
        $paragraph = [];
    };

    $flushList = static function () use (&$listItems, &$listType, &$html): void {
        if ($listType === null || $listItems === []) {
            $listItems = [];
            $listType = null;
            return;
        }

        $html[] = "            <" . $listType . ">";

        foreach ($listItems as $item) {
            $html[] = "              <li>" . $item . "</li>";
        }

        $html[] = "            </" . $listType . ">";
        $listItems = [];
        $listType = null;
    };

    foreach ($lines as $line) {
        if (preg_match('/^```(.*)$/', $line, $matches) === 1) {
            $flushParagraph();
            $flushList();

            if ($inCodeBlock) {
                $languageClass = $codeLanguage !== "" ? ' class="language-' . escape_html($codeLanguage) . '"' : "";
                $code = escape_html(implode("\n", $codeLines));
                $html[] = "            <pre><code{$languageClass}>{$code}</code></pre>";
                $inCodeBlock = false;
                $codeLanguage = "";
                $codeLines = [];
            } else {
                $inCodeBlock = true;
                $codeLanguage = trim($matches[1]);
            }

            continue;
        }

        if ($inCodeBlock) {
            $codeLines[] = $line;
            continue;
        }

        if (trim($line) === "") {
            $flushParagraph();
            $flushList();
            continue;
        }

        if (preg_match('/^(#{1,3})\s+(.+)$/', $line, $matches) === 1) {
            $flushParagraph();
            $flushList();
            $level = strlen($matches[1]);
            $text = trim($matches[2]);

            if ($level === 1) {
                continue;
            }

            $id = slugify_heading($text);
            $tag = "h" . $level;
            $html[] = "            <{$tag} id=\"" . escape_html($id) . "\">" . render_inline_markdown($text, $linkMap) . "</{$tag}>";

            if ($level >= 2) {
                $toc[] = [
                    "id" => $id,
                    "text" => strip_markdown_inline($text),
                    "level" => $level,
                ];
            }

            continue;
        }

        if (preg_match('/^[-*]\s+(.+)$/', $line, $matches) === 1) {
            $flushParagraph();

            if ($listType !== "ul") {
                $flushList();
                $listType = "ul";
            }

            $listItems[] = render_inline_markdown(trim($matches[1]), $linkMap);
            continue;
        }

        if (preg_match('/^\d+\.\s+(.+)$/', $line, $matches) === 1) {
            $flushParagraph();

            if ($listType !== "ol") {
                $flushList();
                $listType = "ol";
            }

            $listItems[] = render_inline_markdown(trim($matches[1]), $linkMap);
            continue;
        }

        $paragraph[] = trim($line);
    }

    $flushParagraph();
    $flushList();

    if ($inCodeBlock) {
        $languageClass = $codeLanguage !== "" ? ' class="language-' . escape_html($codeLanguage) . '"' : "";
        $code = escape_html(implode("\n", $codeLines));
        $html[] = "            <pre><code{$languageClass}>{$code}</code></pre>";
    }

    return [implode(PHP_EOL, $html), $toc];
}

function render_inline_markdown(string $text, array $linkMap): string
{
    $placeholders = [];
    $index = 0;
    $working = preg_replace_callback('/`([^`]+)`/', static function (array $matches) use (&$placeholders, &$index): string {
        $token = "__FNLLA_CODE_" . $index++ . "__";
        $placeholders[$token] = "<code>" . escape_html($matches[1]) . "</code>";
        return $token;
    }, $text);

    $working = escape_html($working ?? $text);
    $working = preg_replace('/\*\*(.+?)\*\*/s', '<strong>$1</strong>', $working) ?? $working;
    $working = preg_replace_callback('/\[(.+?)\]\((.+?)\)/', static function (array $matches) use ($linkMap): string {
        $label = $matches[1];
        $href = $linkMap[$matches[2]] ?? $matches[2];
        return '<a href="' . escape_html($href) . '">' . $label . "</a>";
    }, $working) ?? $working;

    foreach ($placeholders as $token => $replacement) {
        $working = str_replace(escape_html($token), $replacement, $working);
    }

    return $working;
}

function strip_markdown_inline(string $text): string
{
    $text = preg_replace('/`([^`]+)`/', '$1', $text) ?? $text;
    $text = preg_replace('/\[(.+?)\]\((.+?)\)/', '$1', $text) ?? $text;
    $text = str_replace("**", "", $text);

    return trim($text);
}

function slugify_heading(string $text): string
{
    $plain = strtolower(strip_markdown_inline($text));
    $plain = preg_replace('/[^a-z0-9]+/', '-', $plain) ?? $plain;
    $plain = trim($plain, '-');

    return $plain !== "" ? $plain : "section";
}

function read_repository_version(string $versionPath): string
{
    $contents = (string) file_get_contents($versionPath);
    $lines = preg_split("/\r\n|\n|\r/", $contents) ?: [];
    $version = trim((string) ($lines[0] ?? ""));

    return $version !== "" ? $version : "unknown";
}

function ensure_directory(string $path): void
{
    if (is_dir($path)) {
        return;
    }

    mkdir($path, 0777, true);
}

function relative_path(string $root, string $path): string
{
    $normalizedRoot = str_replace("\\", "/", rtrim($root, "\\/"));
    $normalizedPath = str_replace("\\", "/", $path);

    if (str_starts_with($normalizedPath, $normalizedRoot . "/")) {
        return substr($normalizedPath, strlen($normalizedRoot) + 1);
    }

    return $normalizedPath;
}

function normalize_line_endings(string $contents): string
{
    return str_replace(["\r\n", "\r"], "\n", $contents);
}

function escape_html(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, "UTF-8");
}
