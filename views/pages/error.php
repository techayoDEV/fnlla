<?php

declare(strict_types=1);

/*
===============================================================================
FNLLA VIEW TEMPLATE
File: views\pages\error.php
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
    <div class="alert alert-danger" role="alert">
      <h1 class="alert-title"><?= h((string) ($headline ?? "Application error")) ?></h1>
      <p class="alert-text"><?= h((string) ($message ?? "The application hit an unexpected error.")) ?></p>
      <?php if (!empty($requestReference ?? null)): ?>
      <p class="help-text mt-2">Reference ID: <code><?= h((string) $requestReference) ?></code></p>
      <?php endif; ?>
    </div>
    <div class="d-flex flex-wrap gap-md mt-3">
      <a class="btn btn-primary" href="<?= h(url()) ?>">Back home</a>
      <a class="btn btn-outline" href="<?= h(route("services")) ?>">Open services</a>
    </div>
  </div>
</section>
