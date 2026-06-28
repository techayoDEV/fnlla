<?php

declare(strict_types=1);

/*
===============================================================================
FNLLA PHP VIEW TEMPLATE
File: views\pages\dashboard.php
Copyright (c) 2026 TechAyo LTD (techayo.co.uk). Released under the MIT License.
===============================================================================

FNLLA PHP is produced, maintained and distributed by TechAyo LTD
(techayo.co.uk). This repository is the authoritative maintainer workspace for
the FNLLA PHP framework released under the MIT License and its related delivery scripts, tests,
templates and release metadata.

Purpose:
- Defines a maintained page template for the official FNLLA PHP demonstration surface.
*/
?>
<section class="section">
  <div class="container site-section-stack">
    <div class="section-header">
      <h1 class="section-title">Authenticated dashboard</h1>
      <p class="section-text">This page is protected by the `auth` middleware alias and rendered with the current session user.</p>
    </div>

    <div class="grid grid-2 gap-md">
      <article class="card">
        <h2 class="card-title">Current user</h2>
        <p class="card-text"><strong>Name:</strong> <?= h((string) ($currentUser["name"] ?? "Unknown")) ?></p>
        <p class="card-text"><strong>Email:</strong> <?= h((string) ($currentUser["email"] ?? "Unknown")) ?></p>
      </article>
      <article class="card">
        <h2 class="card-title">Included foundations</h2>
        <ul class="content-list">
          <li>Session-backed authentication guard</li>
          <li>Per-route middleware pipeline</li>
          <li>Validation and CSRF protection</li>
          <li>Query builder and CLI migrations</li>
          <li>Authorization gates, queues and scheduled commands</li>
        </ul>
      </article>
    </div>
  </div>
</section>
