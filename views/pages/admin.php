<?php

declare(strict_types=1);

/*
===============================================================================
FNLLA PHP VIEW TEMPLATE
File: views\pages\admin.php
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
      <h1 class="section-title">Admin area</h1>
      <p class="section-text">This page demonstrates the authorization gate layered on top of authenticated routes.</p>
    </div>

    <article class="card">
      <h2 class="card-title">Access context</h2>
      <p class="card-text"><strong>User:</strong> <?= h((string) ($currentUser["email"] ?? "Unknown")) ?></p>
      <p class="card-text"><strong>Role:</strong> <?= h((string) ($currentUser["role"] ?? "user")) ?></p>
    </article>
  </div>
</section>
