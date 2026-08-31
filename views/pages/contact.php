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
      <article class="feature-card starter-contact-form-card">
        <p class="feature-kicker">Starter contact form</p>
        <h2 class="content-title">Send an enquiry</h2>
        <p class="content-text">Use this as the first project-owned form. Add fields, persistence, queues or CRM delivery only when the real product requires them.</p>

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
              <label class="label" for="contact-phone">Phone <span class="label-optional">optional</span></label>
              <input class="input" id="contact-phone" name="contact_phone" type="tel" autocomplete="tel" maxlength="40" value="<?= h((string) old("contact_phone")) ?>">
              <?php if (error_for("contact_phone") !== null): ?><p class="help-text form-error"><?= h((string) error_for("contact_phone")) ?></p><?php endif; ?>
            </div>
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
            <a class="btn btn-ghost" href="<?= h(route("privacy")) ?>">Privacy Policy</a>
          </div>
        </form>
      </article>

      <aside class="starter-contact-sidebar" aria-label="Contact starter notes">
        <?php foreach ($contactChannels as $channel): ?>
        <article class="feature-card starter-contact-card">
          <h2 class="content-title"><?= h($channel["title"]) ?></h2>
          <p class="content-text"><?= h($channel["text"]) ?></p>
          <p><a class="btn btn-outline" href="<?= h($channel["href"]) ?>"><?= h($channel["label"]) ?></a></p>
        </article>
        <?php endforeach; ?>

        <article class="feature-card starter-contact-card">
          <h2 class="content-title">How to extend this</h2>
          <ul class="starter-contact-list">
            <li>Add file upload when the project needs documents or images.</li>
            <li>Persist validated enquiries in a project-owned database table.</li>
            <li>Queue mail delivery once the application has background workers.</li>
            <li>Replace subject options with real services, departments or workflows.</li>
          </ul>
        </article>
      </aside>
    </div>
  </div>
</section>
