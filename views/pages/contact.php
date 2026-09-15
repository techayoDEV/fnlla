<?php

declare(strict_types=1);

$contactSubjects = [
    "Project enquiry",
    "Support request",
    "Partnership",
    "Other",
];
?>
<?php require VIEW_ROOT . "/partials/page-hero.php"; ?>

<section class="section">
  <div class="container">
    <div class="starter-contact-layout" id="contact-form">
      <article class="starter-contact-form-card">
        <form class="form starter-contact-form" action="<?= h(route("contact.submit")) ?>" method="post" novalidate>
          <?= csrf_field() ?>
          <div class="starter-honeypot" aria-hidden="true">
            <label for="contact-website">Website</label>
            <input id="contact-website" name="contact_website" type="text" tabindex="-1" autocomplete="off">
          </div>

          <div class="starter-contact-field-grid">
            <div class="form-group">
              <label class="label" for="contact-name">Name</label>
              <input class="input" id="contact-name" name="contact_name" type="text" autocomplete="name" maxlength="120" value="<?= h((string) old("contact_name")) ?>" required>
              <?php if (error_for("contact_name") !== null): ?><p class="help-text form-error"><?= h((string) error_for("contact_name")) ?></p><?php endif; ?>
            </div>
            <div class="form-group">
              <label class="label" for="contact-email">Email</label>
              <input class="input" id="contact-email" name="contact_email" type="email" autocomplete="email" maxlength="160" value="<?= h((string) old("contact_email")) ?>" required>
              <?php if (error_for("contact_email") !== null): ?><p class="help-text form-error"><?= h((string) error_for("contact_email")) ?></p><?php endif; ?>
            </div>
          </div>

          <div class="starter-contact-field-grid">
            <div class="form-group">
              <label class="label" for="contact-subject">Subject</label>
              <select class="select" id="contact-subject" name="contact_subject" required>
                <option value="">Select a subject</option>
                <?php foreach ($contactSubjects as $subject): ?>
                <option value="<?= h($subject) ?>" <?= old("contact_subject") === $subject ? "selected" : "" ?>><?= h($subject) ?></option>
                <?php endforeach; ?>
              </select>
              <?php if (error_for("contact_subject") !== null): ?><p class="help-text form-error"><?= h((string) error_for("contact_subject")) ?></p><?php endif; ?>
            </div>
          </div>

          <div class="form-group">
            <label class="label" for="contact-message">Message</label>
            <textarea class="textarea" id="contact-message" name="contact_message" rows="7" maxlength="2000" required><?= h((string) old("contact_message")) ?></textarea>
            <?php if (error_for("contact_message") !== null): ?><p class="help-text form-error"><?= h((string) error_for("contact_message")) ?></p><?php endif; ?>
          </div>

          <label class="checkbox-option starter-contact-consent">
            <input type="checkbox" name="contact_consent" value="1" <?= old("contact_consent") === "1" ? "checked" : "" ?> required>
            <span>I agree that this project may use my details to respond to this enquiry.</span>
          </label>
          <?php if (error_for("contact_consent") !== null): ?><p class="help-text form-error"><?= h((string) error_for("contact_consent")) ?></p><?php endif; ?>

          <div class="starter-contact-actions">
            <button class="btn btn-primary" type="submit">Send enquiry</button>
            <a class="starter-contact-privacy" href="<?= h(route("privacy")) ?>">Privacy Policy</a>
          </div>

          <p class="starter-contact-note">Messages are delivered through the configured project mail route.</p>
        </form>
      </article>
    </div>
  </div>
</section>
