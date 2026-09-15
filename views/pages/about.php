<?php

declare(strict_types=1);
?>
<?php require VIEW_ROOT . "/partials/page-hero.php"; ?>

<section class="section">
  <div class="container">
    <div class="starter-simple-list" aria-label="Project story structure">
      <?php foreach ($aboutPillars as $pillar): ?>
      <article class="starter-simple-item">
        <h2 class="content-title"><?= h($pillar["title"]) ?></h2>
        <p class="content-text"><?= h($pillar["text"]) ?></p>
      </article>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<?php
$projectLeadership = project_leadership("public");
$projectLeadershipContext = "public";
if (($projectLeadership["public_visible"] ?? false) === true):
?>
<section class="section">
  <div class="container">
    <?php require VIEW_ROOT . "/partials/project-leadership.php"; ?>
  </div>
</section>
<?php endif; ?>
