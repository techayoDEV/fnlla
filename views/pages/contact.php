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
$topicError = error_for("topic");
$messageError = error_for("message");
$allErrors = errors();
?>
<section class="section pt-1">
  <div class="container site-page-stack">
    <section class="hero hero-compact" aria-label="Contact page introduction">
      <div class="grid gap-md hero-copy">
        <div class="d-flex flex-wrap items-center gap-md">
          <span class="tag">Working form example</span>
          <span class="badge">Validation</span>
          <span class="badge">CSRF</span>
          <span class="badge">Flash feedback</span>
        </div>
        <h1 class="hero-title">A real project contact flow is already part of the starter application shell.</h1>
        <p class="hero-text">Use this page as one of the first delivery surfaces to reshape: replace the placeholder copy, point it at the real mailbox or CRM, and adjust the validation to the actual project workflow.</p>
        <div class="hero-actions">
          <a class="btn btn-primary" href="<?= h(route("project.launch")) ?>">Review project launch flow</a>
          <a class="btn btn-outline" href="<?= h(route("home")) ?>">Back to home</a>
        </div>
      </div>
    </section>
  </div>
</section>

<section class="section">
  <div class="container">
    <section class="process-section" aria-label="Contact flow process">
      <div class="section-header mb-0">
        <p class="process-kicker">Delivery sequence</p>
        <h2 class="section-title">This flow is simple on purpose, but it models the same pattern that should scale into real project work.</h2>
        <p class="section-text">Treat it as a reusable request-capture baseline rather than static demonstration copy.</p>
      </div>
      <div class="process-grid">
        <?php foreach ($deliverySteps as $step): ?>
        <article class="process-step">
          <span class="process-step-number"><?= h($step["number"]) ?></span>
          <h3 class="process-step-title"><?= h($step["title"]) ?></h3>
          <p class="process-step-text"><?= h($step["text"]) ?></p>
        </article>
        <?php endforeach; ?>
      </div>
    </section>
  </div>
</section>

<section class="section">
  <div class="container">
    <section class="contact-section" id="contact-form">
      <div class="contact-grid">
        <aside class="contact-card contact-summary-card" aria-label="Contact section summary">
          <p class="contact-kicker">Flow summary</p>
          <h2 class="contact-card-title">Use one reusable server-rendered intake pattern instead of rebuilding form feedback on every new page.</h2>
          <p class="contact-text">The starter shows a complete baseline: request data capture, validation, flashed status and a real redirect-after-post flow.</p>
          <ul class="contact-list">
            <li>CSRF token verification on submit</li>
            <li>Session-backed flash messages</li>
            <li>Preserved input values after validation errors</li>
            <li>Mail and event hooks after a successful submit</li>
          </ul>
        </aside>

        <article class="cta-card contact-form-card">
          <form class="form contact-form" action="<?= h(route("contact.submit")) ?>" method="post" novalidate>
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
                <label class="label" for="contact-topic">Topic</label>
                <select class="select" id="contact-topic" name="topic" aria-describedby="<?= $topicError ? 'contact-topic-error' : 'contact-topic-help' ?>" <?= $topicError ? 'aria-invalid="true"' : "" ?>>
                  <?php $selectedTopic = (string) old("topic", "Portal or application"); ?>
                  <?php foreach ($contactTopics as $topicOption): ?>
                  <option value="<?= h($topicOption) ?>" <?= $selectedTopic === $topicOption ? "selected" : "" ?>><?= h($topicOption) ?></option>
                  <?php endforeach; ?>
                </select>
                <?php if ($topicError): ?>
                <p class="error-text" id="contact-topic-error"><?= h($topicError) ?></p>
                <?php else: ?>
                <p class="help-text" id="contact-topic-help">Choose the path that best matches the project request.</p>
                <?php endif; ?>
              </div>
            </div>

            <div class="form-group">
              <label class="label" for="contact-message">Message</label>
              <textarea class="textarea" id="contact-message" name="message" placeholder="Outline the goals, timing and any important implementation notes." aria-describedby="<?= $messageError ? 'contact-message-error' : 'contact-message-help' ?>" <?= $messageError ? 'aria-invalid="true"' : "" ?>><?= h((string) old("message")) ?></textarea>
              <?php if ($messageError): ?>
              <p class="error-text" id="contact-message-error"><?= h($messageError) ?></p>
              <?php else: ?>
              <p class="help-text" id="contact-message-help">A short project summary is enough for the initial application shell.</p>
              <?php endif; ?>
            </div>

            <div class="d-flex flex-wrap gap-md">
              <button class="btn btn-primary" type="submit">Submit request</button>
              <a class="btn btn-ghost" href="<?= h(route("project.launch")) ?>">Open project launch</a>
            </div>
          </form>
        </article>
      </div>
    </section>
  </div>
</section>
