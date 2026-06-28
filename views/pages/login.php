<?php

declare(strict_types=1);

/*
===============================================================================
FNLLA PHP VIEW TEMPLATE
File: views\pages\login.php
Copyright (c) 2026 TechAyo LTD (techayo.co.uk). Released under the MIT License.
===============================================================================

FNLLA PHP is produced, maintained and distributed by TechAyo LTD
(techayo.co.uk). This repository is the authoritative maintainer workspace for
the FNLLA PHP framework released under the MIT License and its related delivery scripts, tests,
templates and release metadata.

Purpose:
- Defines a maintained page template for the official FNLLA PHP demonstration surface.
*/

$emailError = error_for("email");
$passwordError = error_for("password");
?>
<section class="section">
  <div class="container">
    <div class="section-header">
      <h1 class="section-title">Sign in</h1>
      <p class="section-text">This example uses the built-in auth manager, validation and session-backed guard.</p>
    </div>

    <article class="cta-card contact-form-card">
      <form class="form contact-form" action="<?= h(route("login.submit")) ?>" method="post" novalidate>
        <?= csrf_field() ?>

        <div class="form-group">
          <label class="label" for="login-email">Email</label>
          <input class="input" id="login-email" name="email" type="email" value="<?= h((string) old("email")) ?>" autocomplete="email" <?= $emailError ? 'aria-invalid="true"' : "" ?>>
          <?php if ($emailError): ?>
          <p class="error-text"><?= h($emailError) ?></p>
          <?php else: ?>
          <p class="help-text">Use a user from the `users` table after running migrations.</p>
          <?php endif; ?>
        </div>

        <div class="form-group">
          <label class="label" for="login-password">Password</label>
          <input class="input" id="login-password" name="password" type="password" autocomplete="current-password" <?= $passwordError ? 'aria-invalid="true"' : "" ?>>
          <?php if ($passwordError): ?>
          <p class="error-text"><?= h($passwordError) ?></p>
          <?php else: ?>
          <p class="help-text">Passwords are verified by the framework hasher service.</p>
          <?php endif; ?>
        </div>

        <div class="d-flex flex-wrap gap-md">
          <button class="btn btn-primary" type="submit">Sign in</button>
          <a class="btn btn-ghost" href="<?= h(route("about")) ?>">Read the framework overview</a>
        </div>
      </form>
    </article>
  </div>
</section>
