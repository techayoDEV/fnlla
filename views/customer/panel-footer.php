<?php

declare(strict_types=1);
?>
    <footer class="panel-brand-attribution customer-portal-attribution">
      <?php if (trim((string) ($panelBrandCopyright ?? "")) !== ""): ?><span><?= h((string) $panelBrandCopyright) ?></span><?php endif; ?>
      <a href="<?= h((string) ($panelBrandPoweredByUrl ?? config("framework.official_url", "https://fnlla.com"))) ?>" target="_blank" rel="noopener noreferrer"><?= h((string) ($panelBrandPoweredBy ?? "Powered by FNLLA")) ?></a>
    </footer>
  </main>
</section>
