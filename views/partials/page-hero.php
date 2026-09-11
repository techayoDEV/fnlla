<?php

declare(strict_types=1);

$pageHero = is_array($pageHero ?? null) ? $pageHero : [];
$pageHeroActions = is_array($pageHero["actions"] ?? null) ? (array) $pageHero["actions"] : [];
$pageHeroMeta = is_array($pageHero["meta"] ?? null) ? (array) $pageHero["meta"] : [];
?>
<section class="section starter-page-title">
  <div class="container">
    <div class="starter-page-heading">
      <div class="starter-page-heading-copy">
        <p class="starter-kicker"><?= h((string) ($pageHero["eyebrow"] ?? $pageTitle ?? "Page")) ?></p>
        <h1><?= h((string) ($pageHero["title"] ?? $pageTitle ?? "Page title")) ?></h1>
        <?php if ((string) ($pageHero["text"] ?? "") !== ""): ?>
        <p><?= h((string) $pageHero["text"]) ?></p>
        <?php endif; ?>

        <?php if ($pageHeroMeta !== []): ?>
        <div class="starter-page-title-tags" aria-label="Page signals">
          <?php foreach ($pageHeroMeta as $item): ?>
          <span><?= h((string) $item) ?></span>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <?php if ($pageHeroActions !== []): ?>
        <div class="starter-page-title-actions">
          <?php foreach ($pageHeroActions as $action): ?>
          <?php $variant = (string) ($action["variant"] ?? "outline"); ?>
          <a class="btn <?= $variant === "primary" ? "btn-primary" : "btn-outline" ?>" href="<?= h((string) ($action["href"] ?? "#")) ?>"><?= h((string) ($action["label"] ?? "Open")) ?></a>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</section>
