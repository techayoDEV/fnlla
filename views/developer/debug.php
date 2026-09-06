<?php

declare(strict_types=1);

$developerPanelTitle = "Debug";
$developerPanelLead = "";
require VIEW_ROOT . "/developer/panel-header.php";
?>
<section class="developer-panel-section" aria-labelledby="debug-title">
  <h2 id="debug-title">Request profiler</h2>
  <dl class="developer-panel-facts">
    <dt>Environment</dt><dd><?= h(app_environment()) ?></dd>
    <dt>Availability</dt><dd><?= $debugAvailable ? "Available" : "Blocked by environment policy" ?></dd>
    <dt>Toolbar</dt><dd><?= $debugEnabled ? "Enabled" : "Disabled" ?></dd>
  </dl>
  <?php if ($canManageDebug): ?>
  <form class="developer-debug-settings" method="post" action="<?= h(route("developer.panel.debug.save")) ?>">
    <?= csrf_field() ?>
    <label><input type="checkbox" name="enabled" value="1" <?= $debugEnabled ? "checked" : "" ?> <?= !$debugAvailable ? "disabled" : "" ?>> Debug toolbar</label>
    <label><input type="checkbox" name="history_enabled" value="1" <?= $historyEnabled ? "checked" : "" ?> <?= !$debugAvailable ? "disabled" : "" ?>> Request history</label>
    <label><input type="checkbox" name="clear_history" value="1" <?= !$debugAvailable ? "disabled" : "" ?>> Clear history</label>
    <button class="btn btn-primary" type="submit">Save</button>
  </form>
  <?php endif; ?>
  <h2>Request history</h2>
  <div class="developer-history-table">
    <table>
      <thead><tr><th scope="col">Time (UTC)</th><th scope="col">Method</th><th scope="col">Status</th><th scope="col">Time (ms)</th><th scope="col">Memory (MiB)</th></tr></thead>
      <tbody>
      <?php foreach ($historyEntries as $entry): ?>
        <tr><td><?= h(gmdate("H:i:s", (int) $entry["at"])) ?></td><td><?= h($entry["method"]) ?></td><td><?= h((string) $entry["status"]) ?></td><td><?= h((string) $entry["duration_ms"]) ?></td><td><?= h(number_format($entry["memory_bytes"] / 1048576, 1)) ?></td></tr>
      <?php endforeach; ?>
      <?php if ($historyEntries === []): ?><tr><td colspan="5">No requests</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</section>
<?php require VIEW_ROOT . "/developer/panel-footer.php"; ?>
