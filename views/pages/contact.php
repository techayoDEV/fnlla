<?php

declare(strict_types=1);

/*
===============================================================================
FNLLA PHP VIEW TEMPLATE
File: views\pages\contact.php
Copyright (c) 2026 TechAyo LTD (techayo.co.uk). Released under the MIT License.
===============================================================================

FNLLA PHP is produced, maintained and distributed by TechAyo LTD
(techayo.co.uk). This repository is the authoritative maintainer workspace for
the FNLLA PHP framework released under the MIT License and its related delivery scripts, tests,
templates and release metadata.

Purpose:
- Defines a maintained page template for the official FNLLA PHP demonstration surface.
*/

$nameError = error_for("name");
$emailError = error_for("email");
$briefError = error_for("brief");
$allErrors = errors();
?>
<section class="section">
  <div class="container">
    <div class="section-header">
      <h1 class="section-title">Contact flow demo</h1>
      <p class="section-text">This page demonstrates plain PHP form handling paired with FNLLA UI form, alert and toast patterns.</p>
    </div>

    <section class="contact-section" id="contact-form">
      <div class="contact-grid">
        <aside class="contact-card contact-summary-card" aria-label="Contact section summary">
          <p class="contact-kicker">Demo use case</p>
          <h2 class="contact-card-title">Use one reusable server-rendered contact flow instead of rebuilding validation feedback on every page.</h2>
          <p class="contact-text">Successful submits trigger a flashed confirmation. Failed submits keep the old values and show field-level guidance.</p>
          <ul class="contact-list">
            <li>CSRF token verification on submit</li>
            <li>Session-backed flash messages</li>
            <li>Preserved input values after validation errors</li>
          </ul>
        </aside>

        <article class="cta-card contact-form-card">
          <form class="form contact-form" action="<?= h(url("contact")) ?>" method="post" novalidate>
            <?= csrf_field() ?>

            <?php if ($allErrors !== []): ?>
            <div class="form-message form-message-error" role="alert" aria-labelledby="contact-form-error-title" aria-describedby="contact-form-error-text">
              <h3 class="form-message-title" id="contact-form-error-title">We still need a few details</h3>
              <p class="form-message-text" id="contact-form-error-text">Review the highlighted fields below before resubmitting the request.</p>
            </div>
            <?php endif; ?>

            <div class="grid grid-2 contact-field-grid">
              <div class="form-group contact-field">
                <label class="label" for="contact-name">Name</label>
                <input class="input" id="contact-name" name="name" type="text" autocomplete="name" placeholder="Your name" aria-describedby="<?= $nameError ? 'contact-name-error' : 'contact-name-help' ?>" <?= $nameError ? 'aria-invalid="true"' : "" ?> value="<?= h((string) old("name")) ?>" required>
                <div class="contact-field-meta">
                  <?php if ($nameError): ?>
                  <p class="error-text" id="contact-name-error"><?= h($nameError) ?></p>
                  <?php else: ?>
                  <p class="help-text" id="contact-name-help">Enter the person who owns the request.</p>
                  <?php endif; ?>
                </div>
              </div>

              <div class="form-group contact-field">
                <label class="label" for="contact-company">Company</label>
                <input class="input" id="contact-company" name="company" type="text" autocomplete="organization" placeholder="Your company" value="<?= h((string) old("company")) ?>">
                <div class="contact-field-meta">
                  <p class="help-text">Optional when the request is individual rather than organizational.</p>
                </div>
              </div>
            </div>

            <div class="grid grid-2 contact-field-grid">
              <div class="form-group contact-field">
                <label class="label" for="contact-email">Email</label>
                <input class="input" id="contact-email" name="email" type="email" autocomplete="email" placeholder="you@example.com" aria-describedby="<?= $emailError ? 'contact-email-error' : 'contact-email-help' ?>" <?= $emailError ? 'aria-invalid="true"' : "" ?> value="<?= h((string) old("email")) ?>" required>
                <div class="contact-field-meta">
                  <?php if ($emailError): ?>
                  <p class="error-text" id="contact-email-error"><?= h($emailError) ?></p>
                  <?php else: ?>
                  <p class="help-text" id="contact-email-help">Use the address where project updates should be sent.</p>
                  <?php endif; ?>
                </div>
              </div>

              <div class="form-group contact-field">
                <label class="label" for="contact-scope">Service area</label>
                <select class="select" id="contact-scope" name="scope">
                  <?php $selectedScope = (string) old("scope", "Implementation support"); ?>
                  <?php foreach (["Platform advisory", "Implementation support", "Operational support"] as $scopeOption): ?>
                  <option value="<?= h($scopeOption) ?>" <?= $selectedScope === $scopeOption ? "selected" : "" ?>><?= h($scopeOption) ?></option>
                  <?php endforeach; ?>
                </select>
                <p class="help-text">Choose the track that best matches the requested work.</p>
              </div>
            </div>

            <div class="form-group">
              <label class="label" for="contact-brief">Project brief</label>
              <textarea class="textarea" id="contact-brief" name="brief" placeholder="Outline requirements, preferred timing and any implementation notes." aria-describedby="<?= $briefError ? 'contact-brief-error' : 'contact-brief-help' ?>" <?= $briefError ? 'aria-invalid="true"' : "" ?>><?= h((string) old("brief")) ?></textarea>
              <?php if ($briefError): ?>
              <p class="error-text" id="contact-brief-error"><?= h($briefError) ?></p>
              <?php else: ?>
              <p class="help-text" id="contact-brief-help">A short implementation summary is enough for the demo.</p>
              <?php endif; ?>
            </div>

            <div class="d-flex flex-wrap gap-md">
              <button class="btn btn-primary" type="submit">Submit request</button>
              <a class="btn btn-ghost" href="<?= h(url("about")) ?>">Read the architecture</a>
            </div>
          </form>
        </article>
      </div>
    </section>
  </div>
</section>
