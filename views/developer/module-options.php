<?php declare(strict_types=1); ?>
<fieldset class="developer-module-options stack gap-sm">
  <legend class="label">Modules</legend>
  <input type="hidden" name="fnlla_modules_present" value="1">
  <?php foreach (\Fnlla\Php\Support\DeveloperModules::OPTIONS as $module => $label): ?>
    <label class="d-flex align-items-center gap-sm" for="fnlla-module-<?= h($module) ?>">
      <input id="fnlla-module-<?= h($module) ?>" name="fnlla_module_<?= h($module) ?>" type="checkbox" value="1" <?= \Fnlla\Php\Support\DeveloperModules::enabled($module) ? "checked" : "" ?>>
      <span><?= h($label) ?></span>
    </label>
  <?php endforeach; ?>
</fieldset>
