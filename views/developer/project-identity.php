<?php

declare(strict_types=1);

$developerPanelTitle = "Project Identity";
$developerPanelLead = "Environment-owned identity used by browser titles, generated links and operational screens.";
$projectName = (string) ($projectSettings["name"] ?? config("app.name", "FNLLA Project"));
$projectTagline = (string) ($projectSettings["tagline"] ?? "");
$projectUrl = (string) ($projectSettings["url"] ?? "");
$projectLeadership = is_array($projectSettings["leadership"] ?? null) ? (array) $projectSettings["leadership"] : project_leadership("admin");
$projectLeadershipStatus = (string) ($projectLeadership["status"] ?? "pending");
$projectLeadershipVisibility = (string) ($projectLeadership["visibility"] ?? "disabled");
$projectLeadershipConfigured = (bool) ($projectLeadership["configured"] ?? false);
$projectLeadershipPublic = (bool) ($projectLeadership["public_visible"] ?? false);
$projectLeadershipManager = new \Fnlla\Php\Support\ProjectLeadership();
$projectLeadershipCanConfirm = $projectLeadershipManager->canConfirm($projectLeadership, (array) ($developerAccess["current_developer"] ?? []));
$titlePreview = "Contact | " . $projectName . ($projectTagline !== "" ? " - " . $projectTagline : "");
require __DIR__ . "/panel-header.php";
?>

        <section class="developer-dashboard-section" id="developer-project-identity" aria-label="Project identity settings">
          <div class="developer-panel-intro">
            <div class="developer-panel-intro-copy">
              <p class="feature-kicker">Project identity</p>
              <h2 class="developer-dashboard-section-title">Keep the visible project name, slogan and canonical URL aligned with this environment.</h2>
              <p class="content-text mb-0">These values are written to the environment file and reused by browser titles, generated links and developer-panel summaries.</p>
            </div>
            <div class="developer-panel-intro-actions">
              <a class="btn btn-outline btn-sm" href="<?= h((string) ($developerLinks["overview"] ?? route("developer.panel"))) ?>">Dashboard</a>
              <a class="btn btn-outline btn-sm" href="<?= h((string) ($developerLinks["home"] ?? route("home"))) ?>" target="_blank" rel="noopener noreferrer">Open public site</a>
            </div>
          </div>

          <div class="developer-dashboard-status-grid">
            <article class="developer-dashboard-status-card">
              <div class="developer-dashboard-card-head"><strong>Project name</strong><span class="developer-dashboard-ok">ENV</span></div>
              <h3><?= h($projectName) ?></h3>
              <p>Main name used across operational screens.</p>
            </article>
            <article class="developer-dashboard-status-card">
              <div class="developer-dashboard-card-head"><strong>Project slogan</strong><span class="developer-dashboard-ok"><?= $projectTagline !== "" ? "SET" : "EMPTY" ?></span></div>
              <h3><?= h($projectTagline !== "" ? $projectTagline : "Not configured") ?></h3>
              <p>Optional browser-title suffix for public pages.</p>
            </article>
            <article class="developer-dashboard-status-card">
              <div class="developer-dashboard-card-head"><strong>Public URL</strong><span class="developer-dashboard-ok"><?= $projectUrl !== "" ? "SET" : "LOCAL" ?></span></div>
              <h3><?= h($projectUrl !== "" ? $projectUrl : "Local build") ?></h3>
              <p>Used for generated links once staging or production exists.</p>
            </article>
            <article class="developer-dashboard-status-card">
              <div class="developer-dashboard-card-head"><strong>Leadership</strong><span class="developer-dashboard-ok"><?= h(strtoupper($projectLeadershipStatus)) ?></span></div>
              <h3><?= $projectLeadershipConfigured ? h((string) ($projectLeadership["person_name"] ?? "Named lead")) : "Not configured" ?></h3>
              <p><?= $projectLeadershipPublic ? "Confirmed and available for public display." : "Private, pending or disabled by project visibility." ?></p>
            </article>
          </div>

          <div class="developer-panel-workbench-grid">
            <article class="developer-panel-fieldset-card">
              <p class="feature-kicker">Identity form</p>
              <h2 class="content-title">Save project identity</h2>
              <form class="form stack gap-md" action="<?= h(route("developer.settings.project")) ?>" method="post" novalidate>
                <?= csrf_field() ?>
                <div class="form-group">
                  <label class="label" for="developer-project-name">Project name</label>
                  <input class="input" id="developer-project-name" name="project_name" type="text" value="<?= h($projectName) ?>" autocomplete="organization" required maxlength="80">
                </div>
                <div class="form-group">
                  <label class="label" for="developer-project-tagline">Project slogan <span class="content-text">(optional)</span></label>
                  <input class="input" id="developer-project-tagline" name="project_tagline" type="text" value="<?= h($projectTagline) ?>" maxlength="120" placeholder="Business systems delivered clearly">
                  <p class="help-text">Example title: <?= h($titlePreview) ?>.</p>
                </div>
                <div class="form-group">
                  <label class="label" for="developer-project-url">Public URL <span class="content-text">(optional)</span></label>
                  <input class="input" id="developer-project-url" name="project_url" type="url" value="<?= h($projectUrl) ?>" inputmode="url" autocomplete="url" placeholder="https://example.com">
                  <p class="help-text">Leave blank on a purely local build. Use HTTPS once staging or production exists.</p>
                </div>
                <div class="d-flex flex-wrap gap-md">
                  <button class="btn btn-primary" type="submit">Save project identity</button>
                </div>
              </form>
            </article>

            <article class="developer-panel-fieldset-card">
              <p class="feature-kicker">Live preview</p>
              <h2 class="content-title">How this will read</h2>
              <div class="developer-dashboard-glance-table">
                <div class="developer-dashboard-glance-row"><strong>Browser title</strong><span><?= h($titlePreview) ?></span></div>
                <div class="developer-dashboard-glance-row"><strong>Public base URL</strong><span><?= h($projectUrl !== "" ? $projectUrl : "Not set for local build") ?></span></div>
                <div class="developer-dashboard-glance-row"><strong>Panel label</strong><span><?= h($projectName) ?></span></div>
              </div>
              <div class="developer-panel-status-note">
                <strong>Scope</strong>
                <span>This changes runtime identity and generated metadata only. It does not rename routes, database tables or project-owned copy.</span>
              </div>
            </article>
          </div>
        </section>

        <section class="developer-dashboard-section" id="project-leadership" aria-label="Project leadership">
          <div class="developer-dashboard-section-head">
            <h2 class="developer-dashboard-section-title">Project leadership</h2>
            <span class="developer-dashboard-refresh">Optional responsibility block</span>
          </div>
          <div class="developer-panel-workbench-grid">
            <article class="developer-panel-fieldset-card">
              <p class="feature-kicker">System information</p>
              <h2 class="content-title">Responsibility record</h2>
              <p class="content-text">Use this for the real person responsible for product direction, roadmap or technical delivery. Public display requires confirmation by the named person.</p>
              <?php
              $projectLeadershipContext = "admin";
              $projectLeadershipActions = true;
              $projectLeadershipConfirmationRoute = (string) ($developerLinks["project_leadership_confirmation"] ?? route("developer.settings.project_leadership.confirmation"));
              require VIEW_ROOT . "/partials/project-leadership.php";
              ?>
              <?php if (!$projectLeadershipConfigured): ?>
              <div class="developer-panel-status-note">
                <strong>No named lead yet</strong>
                <span>Add the responsible person below. Client projects can keep this admin-only or disabled for the public site.</span>
              </div>
              <?php endif; ?>
            </article>

            <article class="developer-panel-fieldset-card">
              <p class="feature-kicker">Leadership form</p>
              <h2 class="content-title">Save responsibility details</h2>
              <form class="form stack gap-md" action="<?= h((string) ($developerLinks["project_leadership"] ?? route("developer.settings.project_leadership"))) ?>" method="post" novalidate>
                <?= csrf_field() ?>
                <div class="form-group">
                  <label class="label" for="project-leadership-organization">Delivery organisation</label>
                  <input class="input" id="project-leadership-organization" name="project_leadership_organization" type="text" value="<?= h((string) ($projectLeadership["organization"] ?? "")) ?>" maxlength="120" placeholder="TechAyo Limited">
                </div>
                <div class="form-group">
                  <label class="label" for="project-leadership-person-name">Responsible person</label>
                  <input class="input" id="project-leadership-person-name" name="project_leadership_person_name" type="text" value="<?= h((string) ($projectLeadership["person_name"] ?? "")) ?>" maxlength="120" autocomplete="name" placeholder="Name Surname">
                </div>
                <div class="form-group">
                  <label class="label" for="project-leadership-person-email">Confirmation email</label>
                  <input class="input" id="project-leadership-person-email" name="project_leadership_person_email" type="email" value="<?= h((string) ($projectLeadership["person_email"] ?? "")) ?>" maxlength="160" autocomplete="email" placeholder="lead@example.com">
                  <p class="help-text">The named person must sign in with this developer email to confirm or reject the responsibility.</p>
                </div>
                <div class="form-group">
                  <label class="label" for="project-leadership-person-role">Role or position</label>
                  <input class="input" id="project-leadership-person-role" name="project_leadership_person_role" type="text" value="<?= h((string) ($projectLeadership["person_role"] ?? "")) ?>" maxlength="120" placeholder="Director of TechAyo">
                </div>
                <div class="form-group">
                  <label class="label" for="project-leadership-responsibility">Responsibility scope</label>
                  <input class="input" id="project-leadership-responsibility" name="project_leadership_responsibility" type="text" value="<?= h((string) ($projectLeadership["responsibility"] ?? "")) ?>" maxlength="240" placeholder="product direction, roadmap and technical delivery">
                </div>
                <div class="form-group">
                  <label class="label" for="project-leadership-profile-url">Profile or contact URL <span class="content-text">(optional)</span></label>
                  <input class="input" id="project-leadership-profile-url" name="project_leadership_profile_url" type="url" value="<?= h((string) ($projectLeadership["profile_url"] ?? "")) ?>" maxlength="2048" inputmode="url" placeholder="https://example.com/contact">
                </div>
                <div class="form-group">
                  <label class="label" for="project-leadership-visibility">Visibility</label>
                  <select class="select" id="project-leadership-visibility" name="project_leadership_visibility">
                    <?php foreach (["admin" => "Private panel and documentation", "public" => "Public after confirmation", "disabled" => "Disabled"] as $value => $label): ?>
                    <option value="<?= h($value) ?>" <?= $projectLeadershipVisibility === $value ? "selected" : "" ?>><?= h($label) ?></option>
                    <?php endforeach; ?>
                  </select>
                  <p class="help-text">Client systems can keep this private without showing TechAyo or named-lead information on the public website.</p>
                </div>
                <div class="d-flex flex-wrap gap-md">
                  <button class="btn btn-primary" type="submit">Save leadership details</button>
                </div>
              </form>
            </article>
          </div>
        </section>

<?php require __DIR__ . "/panel-footer.php"; ?>
