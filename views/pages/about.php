<?php

declare(strict_types=1);

/*
===============================================================================
FNLLA PHP VIEW TEMPLATE
File: views\pages\about.php
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
      <h1 class="section-title">What FNLLA PHP includes</h1>
      <p class="section-text">This starter is intentionally modest: enough structure to keep a PHP app organized, but small enough to stay understandable and easy to extend.</p>
    </div>

    <div class="grid grid-2 gap-md">
      <article class="card">
        <h2 class="card-title">Guiding principles</h2>
        <ul class="content-list">
          <?php foreach ($principles as $principle): ?>
          <li><?= h($principle) ?></li>
          <?php endforeach; ?>
        </ul>
      </article>
      <article class="card">
        <h2 class="card-title">Request lifecycle</h2>
        <div class="table-responsive">
          <table class="table table-striped" aria-label="Request lifecycle">
            <thead>
              <tr>
                <th scope="col">Step</th>
                <th scope="col">Responsibility</th>
              </tr>
            </thead>
            <tbody>
              <tr>
                <td><code>public/index.php</code></td>
                <td>Boot the application and hand over the request.</td>
              </tr>
              <tr>
                <td><code>bootstrap/app.php</code></td>
                <td>Register the autoloader, session handling and route definitions.</td>
              </tr>
              <tr>
                <td><code>Router</code></td>
                <td>Match the current path and invoke the right controller or closure.</td>
              </tr>
              <tr>
                <td><code>Controller</code></td>
                <td>Prepare page data, validate input and choose the response type.</td>
              </tr>
              <tr>
                <td><code>View</code></td>
                <td>Render plain PHP templates inside the shared FNLLA UI layout shell.</td>
              </tr>
            </tbody>
          </table>
        </div>
      </article>
    </div>

    <div class="grid grid-3 gap-md">
      <article class="card">
        <h2 class="card-title">Where to extend first</h2>
        <p class="card-text">A mailer, database layer, route parameters and dedicated domain services are the most natural next additions once the starter leaves demo territory.</p>
      </article>
      <article class="card">
        <h2 class="card-title">What stays simple</h2>
        <p class="card-text">The framework deliberately avoids hidden service containers, template compilers and magic route discovery so tracing behavior stays straightforward.</p>
      </article>
      <article class="card">
        <h2 class="card-title">Why FNLLA UI fits here</h2>
        <p class="card-text">Server-rendered HTML can still feel polished when the component and section system is already solved by a published runtime like FNLLA UI.</p>
      </article>
    </div>
  </div>
</section>
