<?php

declare(strict_types=1);

use Fnlla\Php\Support\ProjectLeadership;

$projectLeadership = is_array($projectLeadership ?? null) ? (array) $projectLeadership : project_leadership("public");
$projectLeadershipContext = (string) ($projectLeadershipContext ?? ($projectLeadership["context"] ?? "public"));
$projectLeadershipActions = (bool) ($projectLeadershipActions ?? false);
$projectLeadershipCanConfirm = (bool) ($projectLeadershipCanConfirm ?? false);
$projectLeadershipConfirmationRoute = (string) ($projectLeadershipConfirmationRoute ?? "");
$showPublic = $projectLeadershipContext === "public" && ($projectLeadership["public_visible"] ?? false) === true;
$showPrivate = $projectLeadershipContext !== "public" && ($projectLeadership["configured"] ?? false) === true;
$status = (string) ($projectLeadership["status"] ?? ProjectLeadership::STATUS_PENDING);
$visibility = (string) ($projectLeadership["visibility"] ?? ProjectLeadership::VISIBILITY_DISABLED);
$heading = (string) ($projectLeadership["heading"] ?? ($projectLeadershipContext === "public" ? "Product leadership" : "System information"));
$profileUrl = (string) ($projectLeadership["profile_url"] ?? "");
$personName = (string) ($projectLeadership["person_name"] ?? "");
$personRole = (string) ($projectLeadership["person_role"] ?? "");
$organization = (string) ($projectLeadership["organization"] ?? "");
$responsibility = (string) ($projectLeadership["responsibility"] ?? "");
?>
<?php if ($showPublic || $showPrivate): ?>
<article class="project-leadership-block" aria-label="<?= h($heading) ?>">
  <div class="project-leadership-head">
    <p class="feature-kicker"><?= h($heading) ?></p>
    <?php if ($projectLeadershipContext !== "public"): ?>
    <?php if ($status === ProjectLeadership::STATUS_CONFIRMED): ?>
    <span class="project-leadership-check" aria-label="Confirmed">&#10003;</span>
    <?php endif; ?>
    <span class="project-leadership-status project-leadership-status-<?= h($status) ?>">
      <?= $status === ProjectLeadership::STATUS_CONFIRMED ? "Confirmed" : ($status === ProjectLeadership::STATUS_REJECTED ? "Rejected" : "Pending") ?>
    </span>
    <?php elseif ($status === ProjectLeadership::STATUS_CONFIRMED): ?>
    <span class="project-leadership-check" aria-label="Confirmed">&#10003;</span>
    <?php endif; ?>
  </div>

  <?php if ($projectLeadershipContext === "public"): ?>
  <p class="project-leadership-text">
    <?= h((string) config("app.name", "This project")) ?> is led by
    <?php if ($profileUrl !== ""): ?><a class="developer-text-link" href="<?= h($profileUrl) ?>" target="_blank" rel="noopener noreferrer"><?= h($personName) ?></a><?php else: ?><?= h($personName) ?><?php endif; ?><?= $personRole !== "" ? ", " . h($personRole) : "" ?><?= $organization !== "" ? " at " . h($organization) : "" ?>,
    responsible for <?= h($responsibility) ?>.
  </p>
  <?php else: ?>
  <div class="developer-dashboard-glance-table project-leadership-table">
    <div class="developer-dashboard-glance-row"><strong>Developed and maintained by</strong><span><?= h($organization !== "" ? $organization : "Not set") ?></span></div>
    <div class="developer-dashboard-glance-row"><strong>Product and delivery lead</strong><span><?= h($personName !== "" ? $personName : "Not set") ?></span></div>
    <div class="developer-dashboard-glance-row"><strong>Role</strong><span><?= h($personRole !== "" ? $personRole : "Not set") ?></span></div>
    <div class="developer-dashboard-glance-row"><strong>Responsibility</strong><span><?= h($responsibility !== "" ? $responsibility : "Not set") ?></span></div>
    <div class="developer-dashboard-glance-row"><strong>Visibility</strong><span><?= h($visibility) ?></span></div>
    <div class="developer-dashboard-glance-row"><strong>Confirmation</strong><span><?= h($status) ?></span></div>
  </div>

  <?php if ($projectLeadershipActions && $projectLeadershipCanConfirm && $status === ProjectLeadership::STATUS_PENDING && $projectLeadershipConfirmationRoute !== ""): ?>
  <div class="developer-inline-actions project-leadership-actions">
    <form action="<?= h($projectLeadershipConfirmationRoute) ?>" method="post">
      <?= csrf_field() ?>
      <input type="hidden" name="project_leadership_action" value="confirm">
      <button class="btn btn-primary btn-sm" type="submit">Confirm responsibility</button>
    </form>
    <form action="<?= h($projectLeadershipConfirmationRoute) ?>" method="post">
      <?= csrf_field() ?>
      <input type="hidden" name="project_leadership_action" value="reject">
      <button class="btn btn-outline btn-sm" type="submit">Reject</button>
    </form>
  </div>
  <?php endif; ?>
  <?php endif; ?>
</article>
<?php endif; ?>
