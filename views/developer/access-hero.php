<?php
declare(strict_types=1);
$panelBrand = is_array($panelBrand ?? null) ? (array) $panelBrand : panel_branding();
$panelBrandCopyright = trim((string) ($panelBrand["copyright"] ?? ""));
$panelBrandPoweredBy = (string) ($panelBrand["powered_by"] ?? "Powered by FNLLA");
$panelBrandPoweredByUrl = (string) ($panelBrand["powered_by_url"] ?? config("framework.official_url", "https://fnlla.com"));
$panelBrandEditionLabel = strtoupper((string) ($panelBrand["edition_label"] ?? \Fnlla\Php\Support\ProjectProfile::editionLabel()));
$panelBrandEditionHeader = $panelBrandEditionLabel === "FNLLA" ? "FNLLA" : "FNLLA / " . $panelBrandEditionLabel;
?>
<aside class="developer-sign-in-hero" aria-label="FNLLA Framework workspace">
  <div class="developer-sign-in-hero-top"><span><?= h($panelBrandEditionHeader) ?></span><span>WORKSPACE</span></div>
  <div class="developer-sign-in-hero-content">
    <?php require dirname(__DIR__) . "/partials/framework-wordmark.php"; ?>
    <p class="developer-sign-in-hero-edition"><?= h((string) ($panelBrand["edition"] ?? \Fnlla\Php\Support\ProjectProfile::edition())) ?></p>
    <p class="developer-sign-in-hero-title">Your project.<br>Your workspace.</p>
  </div>
  <div class="developer-sign-in-hero-footer">
    <span><?= h($panelBrandCopyright !== "" ? $panelBrandCopyright : "Private delivery workspace.") ?></span>
    <span class="developer-sign-in-creator"><a href="<?= h($panelBrandPoweredByUrl) ?>" target="_blank" rel="noopener noreferrer"><?= h($panelBrandPoweredBy) ?></a></span>
  </div>
</aside>
