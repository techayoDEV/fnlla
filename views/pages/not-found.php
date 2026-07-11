<?php

declare(strict_types=1);

/*
===============================================================================
FNLLA VIEW TEMPLATE
File: views\pages\not-found.php
Copyright (c) 2026 TechAyo LTD (techayo.co.uk). Released under the MIT License.
===============================================================================

FNLLA is produced, maintained and distributed by TechAyo LTD
(techayo.co.uk). This repository is the authoritative maintainer workspace for
the FNLLA framework released under the MIT License and its related delivery scripts, tests,
templates and release metadata.

Purpose:
- Defines a maintained page template for the official FNLLA demonstration surface.
*/
?>
<section class="section">
  <div class="container">
    <section class="empty-state" aria-label="Page not found">
      <span class="badge">404</span>
      <h1 class="content-title">That page could not be found.</h1>
      <p class="content-text">The router did not find a matching route for this path.</p>
      <div class="d-flex flex-wrap gap-md">
        <a class="btn btn-primary" href="<?= h(url()) ?>">Back home</a>
        <a class="btn btn-outline" href="<?= h(url("contact")) ?>">Open the form demo</a>
      </div>
    </section>
  </div>
</section>
