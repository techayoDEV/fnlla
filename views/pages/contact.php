<?php

declare(strict_types=1);
?>
<section class="section starter-page-hero">
  <div class="container">
    <div class="starter-page-heading">
      <p class="starter-kicker">Contact</p>
      <h1>Replace this page with the project enquiry, booking or support flow.</h1>
      <p>
        A real project can turn this into a contact form, quote request, onboarding route or authenticated support workflow.
      </p>
    </div>
  </div>
</section>

<section class="section">
  <div class="container">
    <div class="grid grid-2 gap-md">
      <?php foreach ($contactChannels as $channel): ?>
      <article class="feature-card starter-contact-card">
        <h2 class="content-title"><?= h($channel["title"]) ?></h2>
        <p class="content-text"><?= h($channel["text"]) ?></p>
        <p><a class="btn btn-outline" href="<?= h($channel["href"]) ?>"><?= h($channel["label"]) ?></a></p>
      </article>
      <?php endforeach; ?>
    </div>
  </div>
</section>
