<?php declare(strict_types=1); ?>
<aside class="developer-sign-in-hero" aria-label="FNLLA Framework">
  <div class="developer-sign-in-hero-top"><span>FNLLA / DEVELOPER</span><span>WORKSPACE</span></div>
  <div class="developer-sign-in-hero-content">
    <?php require dirname(__DIR__) . "/partials/framework-wordmark.php"; ?>
    <p class="developer-sign-in-hero-title">Your project.<br>Your workspace.</p>
  </div>
  <div class="developer-sign-in-hero-footer">
    <span>Build from blueprint.</span>
    <span class="developer-sign-in-creator">Framework created &amp; maintained by <a href="<?= h((string) config("framework.maintainer_url", "https://techayo.co.uk")) ?>" target="_blank" rel="noopener noreferrer">TechAyo</a></span>
  </div>
</aside>
