<?php

declare(strict_types=1);

$legalSections = is_array($legalSections ?? null) ? $legalSections : [];
?>
<?php require VIEW_ROOT . "/partials/page-hero.php"; ?>

<section class="section">
  <div class="container">
    <div class="legal-snippet-stack">
      <?php foreach ($legalSections as $section): ?>
      <article class="feature-card legal-snippet-card"<?= isset($section["id"]) ? ' id="' . h((string) $section["id"]) . '"' : "" ?>>
        <h2 class="content-title"><?= h((string) ($section["title"] ?? "Section")) ?></h2>
        <ul class="legal-snippet-list">
          <?php foreach ((array) ($section["items"] ?? []) as $item): ?>
          <li><?= h((string) $item) ?></li>
          <?php endforeach; ?>
        </ul>
      </article>
      <?php endforeach; ?>
    </div>

    <?php if ((string) ($legalNote ?? "") !== ""): ?>
    <article class="alert alert-info legal-snippet-note" role="note">
      <h2 class="alert-title">Starter legal copy</h2>
      <p class="alert-text"><?= h((string) $legalNote) ?></p>
    </article>
    <?php endif; ?>
  </div>
</section>
