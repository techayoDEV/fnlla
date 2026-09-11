<?php

declare(strict_types=1);

use Fnlla\Php\Support\DeveloperPanelLabels;

$developerPanelTitle = "Integrations";
$developerPanelLead = "Project-owned API, AI and remote-control adapters. Analytics, heatmap and error monitoring stay first-party.";
$report = is_array($operationsReport ?? null) ? (array) $operationsReport : [];
$integrations = (array) ($report["integrations"] ?? []);
$remoteControl = null;

foreach ($integrations as $integration) {
    if (($integration["key"] ?? "") === "remote_control_contract") {
        $remoteControl = (array) ($integration["contract"] ?? []);
        break;
    }
}

$remoteRuntimeContract = is_array($remoteControl["fnlla_runtime_contract"] ?? null)
    ? (array) $remoteControl["fnlla_runtime_contract"]
    : [];
$remoteControlSchema = (string) ($remoteControl["schema"] ?? "fnlla.remote_control_plugin.v1");
$remoteRuntimeSchema = (string) (($remoteRuntimeContract["response_schema"] ?? "") ?: "fnlla.techayo_remote_control_state.v2");
$integrationConfig = (array) config("integrations", []);
$remoteConfig = (array) config("developer_control.remote", []);
$fionnConfig = (array) config("ai.runtime.fionn", []);
$runtimeProviderOptions = [
    "local" => "Local reference (no AI model)",
    "fionn" => "FIONN AI adapter / optional API account required",
    "openai" => "OpenAI API / optional",
    "anthropic" => "Anthropic API / optional",
];
$runtimeProviderDocs = [
    "openai" => [
        "label" => "OpenAI API",
        "text" => "Optional external model provider for project-owned prompts, enabled only after policy and credential setup.",
    ],
    "anthropic" => [
        "label" => "Anthropic API",
        "text" => "Optional external model provider for project-owned prompts, enabled only after policy and credential setup.",
    ],
];
$defaultIntegrationFormValues = [
    "fnlla_integration_api_hooks_enabled" => (bool) ($integrationConfig["api_hooks"]["enabled"] ?? false) ? "1" : "0",
    "fnlla_integration_api_hooks_endpoint" => (string) ($integrationConfig["api_hooks"]["endpoint"] ?? ""),
    "developer_control_remote_enabled" => (bool) ($remoteConfig["enabled"] ?? false) ? "1" : "0",
    "developer_control_remote_endpoint" => (string) ($remoteConfig["endpoint"] ?? ""),
    "developer_control_remote_project_id" => (string) ($remoteConfig["project_id"] ?? ""),
    "ai_fionn_enabled" => (bool) ($fionnConfig["enabled"] ?? false) ? "1" : "0",
    "ai_fionn_endpoint" => (string) ($fionnConfig["endpoint"] ?? ""),
    "ai_fionn_chat_path" => (string) (($fionnConfig["chat_path"] ?? "") ?: "/api/chat"),
    "ai_fionn_allowed_hosts" => implode(",", (array) ($fionnConfig["allowed_hosts"] ?? ["127.0.0.1", "localhost"])),
    "ai_fionn_allow_insecure_localhost" => (bool) ($fionnConfig["allow_insecure_localhost"] ?? true) ? "1" : "0",
];
$renderIntegrationHiddenFields = static function (array $overrides = []) use ($defaultIntegrationFormValues): void {
    foreach (array_merge($defaultIntegrationFormValues, $overrides) as $field => $value) { ?>
              <input type="hidden" name="<?= h((string) $field) ?>" value="<?= h((string) $value) ?>">
<?php }
};
$firstPartyTools = [
    [
        "name" => "FNLLA Analytics",
        "status" => ((bool) config("observability.analytics.enabled", true) && (bool) config("observability.metrics.enabled", true)) ? "active" : "off",
        "text" => "Local aggregate traffic, goals, referrers, consent and slow-route signal.",
        "href" => (string) ($developerLinks["analytics"] ?? route("developer.panel.analytics")),
    ],
    [
        "name" => "FNLLA Heatmap",
        "status" => (bool) config("observability.heatmap.enabled", true) ? "active" : "off",
        "text" => "Consent-aware click zones, scroll-depth buckets, device mix and element labels.",
        "href" => (string) ($developerLinks["heatmap"] ?? route("developer.panel.heatmap")),
    ],
    [
        "name" => "FNLLA Error Monitor",
        "status" => (bool) config("debug.runtime_issues.enabled", true) ? "active" : "off",
        "text" => "Local fingerprinted runtime issue tracking for debug, debt and Kanban triage.",
        "href" => (string) ($developerLinks["debug"] ?? route("developer.panel.debug")) . "#runtime-issues",
    ],
];
require __DIR__ . "/panel-header.php";
?>

        <section class="developer-dashboard-section" aria-label="FNLLA first-party observability">
          <div class="developer-dashboard-section-head">
            <h2 class="developer-dashboard-section-title">FNLLA observability</h2>
            <span class="developer-dashboard-refresh">Local first-party signal</span>
          </div>
          <div class="developer-dashboard-status-grid">
            <?php foreach ($firstPartyTools as $tool): ?>
            <article class="developer-dashboard-status-card">
              <div class="developer-dashboard-card-head">
                <strong><?= h((string) $tool["name"]) ?></strong>
                <span class="developer-dashboard-ok"><?= h(DeveloperPanelLabels::status((string) $tool["status"])) ?></span>
              </div>
              <p><?= h((string) $tool["text"]) ?></p>
              <a class="btn btn-outline btn-sm" href="<?= h((string) $tool["href"]) ?>">Open</a>
            </article>
            <?php endforeach; ?>
            <article class="developer-dashboard-status-card">
              <div class="developer-dashboard-card-head">
                <strong>Third-party tracking scripts</strong>
                <span class="developer-dashboard-ok">Not included</span>
              </div>
              <p>Analytics, behavior mapping and runtime issue triage now run through FNLLA-owned collectors and local storage.</p>
            </article>
          </div>
        </section>

        <section class="developer-dashboard-section" aria-label="Integration adapters">
          <div class="developer-dashboard-section-head">
            <h2 class="developer-dashboard-section-title">Project-owned adapters</h2>
            <span class="developer-dashboard-refresh">Disabled unless explicitly configured</span>
          </div>
          <div class="developer-integrations-stack">
            <?php foreach ($integrations as $integration): ?>
            <?php
                $name = (string) ($integration["name"] ?? "Integration");
                $adapter = trim((string) ($integration["adapter"] ?? "Optional adapter"));
                $control = [];
                if (trim((string) ($integration["enabled_field"] ?? "")) !== "" && trim((string) ($integration["settings_modal"] ?? "")) !== "") {
                    $control = [
                        "enabled_field" => (string) $integration["enabled_field"],
                        "enabled" => (bool) ($integration["enabled"] ?? false),
                        "modal" => (string) $integration["settings_modal"],
                    ];
                }
            ?>
            <article class="developer-integrations-row">
              <div>
                <p class="feature-kicker<?= ($integration["key"] ?? "") === "ai_provider_contract" ? " fnlla-fionn-name" : "" ?>"><?= h($name) ?></p>
                <h3><?= h(DeveloperPanelLabels::status((string) ($integration["status"] ?? "disabled"))) ?></h3>
                <p class="content-text mb-0"><?= h((string) ($integration["description"] ?? "Optional adapter controlled by project configuration and policy.")) ?></p>
              </div>
              <div class="developer-integrations-policy">
                <span>Gate: <?= h(DeveloperPanelLabels::gate((string) ($integration["consent_event"] ?? "manual"))) ?></span>
                <span>Adapter: <?= h($adapter) ?></span>
                <span><?= h((string) ($integration["boundary"] ?? "No external request is made until enabled and configured.")) ?></span>
              </div>
              <div class="developer-integrations-control">
                <?php if ($control !== []): ?>
                <div class="developer-integrations-actions">
                  <form action="<?= h(route("developer.panel.integrations.settings")) ?>" method="post">
                    <?= csrf_field() ?>
                    <?php $renderIntegrationHiddenFields([(string) $control["enabled_field"] => ($control["enabled"] ?? false) ? "0" : "1"]); ?>
                    <button class="btn btn-outline btn-sm" type="submit"><?= ($control["enabled"] ?? false) ? "Disable" : "Enable" ?></button>
                  </form>
                  <button class="btn btn-primary btn-sm" type="button" data-fnlla-modal-open="#<?= h((string) $control["modal"]) ?>">Settings</button>
                </div>
                <?php endif; ?>
              </div>
            </article>
            <?php endforeach; ?>
          </div>
          <div class="developer-integrations-explainer">
            <strong>Why are adapters gated?</strong>
            <p>The adapter contract can exist in FNLLA while network requests stay disabled until the project explicitly enables an endpoint and passes the consent or server-policy gate.</p>
          </div>
        </section>

        <section class="developer-dashboard-section developer-ai-settings" aria-labelledby="ai-providers-title">
          <div class="developer-dashboard-section-head">
            <div>
              <h2 id="ai-providers-title" class="developer-dashboard-section-title">AI providers</h2>
              <p class="content-text mb-0">Choose the runtime contract first, then configure only the external provider credentials the project is allowed to use.</p>
            </div>
            <span class="developer-dashboard-refresh">Server policy gated</span>
          </div>
          <form class="form developer-ai-provider-form" action="<?= h(route("developer.panel.integrations.ai")) ?>" method="post" autocomplete="off">
            <?= csrf_field() ?>
            <div class="developer-ai-runtime-card">
              <div>
                <p class="feature-kicker">Runtime selector</p>
                <h3>Set the active AI contract</h3>
                <p class="content-text mb-0">Local reference keeps FNLLA offline. External providers require an enabled adapter and configured credentials.</p>
              </div>
              <label>Active provider
                <select class="select" name="ai_runtime_driver">
                  <?php foreach ($runtimeProviderOptions as $driver => $label): ?>
                  <option value="<?= h($driver) ?>" <?= config("ai.runtime.driver", "local") === $driver ? "selected" : "" ?>><?= h($label) ?></option>
                  <?php endforeach; ?>
                </select>
              </label>
              <label class="developer-workspace-check developer-ai-runtime-toggle"><input type="checkbox" name="ai_runtime_enabled" value="1" <?= config("ai.runtime.enabled", true) ? "checked" : "" ?>><span>Runtime AI enabled</span></label>
            </div>
            <div class="developer-ai-provider-stack">
            <?php foreach ($runtimeProviderDocs as $driver => $providerDoc):
                $provider = (array) config("ai.runtime." . $driver, []);
                $providerStatus = (new \Fnlla\Php\Ai\RuntimeAiProviderRegistry())->status($driver);
                $field = "ai_" . $driver . "_";
            ?>
            <fieldset class="developer-ai-provider">
              <legend><?= h((string) $providerDoc["label"]) ?></legend>
              <div class="developer-ai-provider-head">
                <p class="content-text mb-0"><?= h((string) $providerDoc["text"]) ?></p>
                <p class="developer-dashboard-status is-neutral"><?= ($providerStatus["provider_ready"] ?? false) ? "Configured / not live-tested" : (($providerStatus["enabled"] ?? false) ? "Configuration required" : "Disabled") ?></p>
              </div>
              <div class="developer-ai-provider-grid">
                <label class="developer-workspace-check"><input type="checkbox" name="<?= h($field) ?>enabled" value="1" <?= ($provider["enabled"] ?? false) ? "checked" : "" ?>>External requests enabled</label>
                <label>Model ID<input class="input" name="<?= h($field) ?>model" maxlength="160" value="<?= h((string) ($provider["model"] ?? "")) ?>" spellcheck="false" autocomplete="off"></label>
                <label>API key<input class="input" type="password" name="<?= h($field) ?>api_key" maxlength="512" value="" autocomplete="new-password" placeholder="<?= ($providerStatus["token_configured"] ?? false) ? "Configured; leave blank to keep" : "Not configured" ?>"></label>
                <label class="developer-workspace-check"><input type="checkbox" name="<?= h($field) ?>remove_key" value="1">Remove stored API key</label>
                <label>Maximum output tokens<input class="input" type="number" name="<?= h($field) ?>max_output_tokens" min="64" max="8192" required value="<?= (int) ($provider["max_output_tokens"] ?? 1024) ?>"></label>
                <label>Timeout (seconds)<input class="input" type="number" name="<?= h($field) ?>timeout_seconds" min="1" max="60" required value="<?= (int) ($provider["timeout_seconds"] ?? 30) ?>"></label>
              </div>
            </fieldset>
            <?php endforeach; ?>
            </div>
            <div class="developer-ai-save-row">
              <button class="btn btn-primary" type="submit">Save AI settings</button>
            </div>
          </form>
        </section>

        <div class="modal developer-kanban-modal developer-integrations-modal" id="developer-integration-api-hooks-settings" data-fnlla-modal role="dialog" aria-modal="true" aria-labelledby="developer-integration-api-hooks-settings-title" hidden>
          <div class="modal-content developer-kanban-modal-content">
            <div class="developer-kanban-modal-head">
              <div>
                <p class="feature-kicker mb-2">Generic API hooks</p>
                <h2 class="content-title mb-0" id="developer-integration-api-hooks-settings-title">Adapter settings</h2>
              </div>
              <button class="developer-kanban-modal-close" type="button" data-fnlla-modal-close aria-label="Close API hooks settings"><span aria-hidden="true">x</span></button>
            </div>
            <form class="form developer-integrations-modal-form" action="<?= h(route("developer.panel.integrations.settings")) ?>" method="post" novalidate>
              <?= csrf_field() ?>
              <?php $renderIntegrationHiddenFields(); ?>
              <label class="developer-workspace-check">
                <input type="hidden" name="fnlla_integration_api_hooks_enabled" value="0">
                <input type="checkbox" name="fnlla_integration_api_hooks_enabled" value="1" <?= (bool) ($integrationConfig["api_hooks"]["enabled"] ?? false) ? "checked" : "" ?>>
                <span>Generic API hooks enabled</span>
              </label>
              <div class="form-group developer-integrations-wide">
                <label class="label" for="fnlla-integration-api-hooks">API hooks endpoint</label>
                <input class="input" id="fnlla-integration-api-hooks" name="fnlla_integration_api_hooks_endpoint" type="url" maxlength="240" value="<?= h((string) ($integrationConfig["api_hooks"]["endpoint"] ?? "")) ?>" data-fnlla-modal-initial-focus>
              </div>
              <div class="developer-kanban-modal-actions developer-integrations-wide">
                <button class="btn btn-primary" type="submit">Save API hook settings</button>
                <button class="btn btn-ghost" type="button" data-fnlla-modal-close>Cancel</button>
              </div>
            </form>
          </div>
        </div>

        <div class="modal developer-kanban-modal developer-integrations-modal" id="developer-integration-fionn-settings" data-fnlla-modal role="dialog" aria-modal="true" aria-labelledby="developer-integration-fionn-settings-title" hidden>
          <div class="modal-content developer-kanban-modal-content">
            <div class="developer-kanban-modal-head">
              <div>
                <p class="feature-kicker mb-2 fnlla-fionn-name">FIONN AI</p>
                <h2 class="content-title mb-0" id="developer-integration-fionn-settings-title">Adapter settings</h2>
              </div>
              <button class="developer-kanban-modal-close" type="button" data-fnlla-modal-close aria-label="Close FIONN AI settings"><span aria-hidden="true">x</span></button>
            </div>
            <form class="form developer-integrations-modal-form" action="<?= h(route("developer.panel.integrations.settings")) ?>" method="post" novalidate>
              <?= csrf_field() ?>
              <?php $renderIntegrationHiddenFields(); ?>
              <label class="developer-workspace-check developer-integrations-wide">
                <input type="hidden" name="ai_fionn_enabled" value="0">
                <input type="checkbox" name="ai_fionn_enabled" value="1" <?= (bool) ($fionnConfig["enabled"] ?? false) ? "checked" : "" ?>>
                <span>FIONN AI bridge enabled</span>
              </label>
              <div class="form-group developer-integrations-wide">
                <label class="label" for="ai-fionn-endpoint">FIONN AI endpoint</label>
                <input class="input" id="ai-fionn-endpoint" name="ai_fionn_endpoint" type="url" maxlength="240" value="<?= h((string) ($fionnConfig["endpoint"] ?? "")) ?>" placeholder="Endpoint supplied with your API access" data-fnlla-modal-initial-focus>
              </div>
              <div class="form-group">
                <label class="label" for="ai-fionn-chat-path">Chat path</label>
                <input class="input" id="ai-fionn-chat-path" name="ai_fionn_chat_path" type="text" maxlength="120" value="<?= h((string) (($fionnConfig["chat_path"] ?? "") ?: "/api/chat")) ?>">
              </div>
              <div class="form-group">
                <label class="label" for="ai-fionn-allowed-hosts">Allowed hosts</label>
                <input class="input" id="ai-fionn-allowed-hosts" name="ai_fionn_allowed_hosts" type="text" maxlength="240" value="<?= h(implode(",", (array) ($fionnConfig["allowed_hosts"] ?? ["127.0.0.1", "localhost"]))) ?>">
              </div>
              <div class="form-group developer-integrations-wide">
                <label class="label" for="ai-fionn-api-token">API token</label>
                <input class="input" id="ai-fionn-api-token" name="ai_fionn_api_token" type="password" maxlength="240" value="" placeholder="Leave blank to keep current token">
              </div>
              <label class="developer-workspace-check developer-integrations-wide">
                <input type="hidden" name="ai_fionn_allow_insecure_localhost" value="0">
                <input type="checkbox" name="ai_fionn_allow_insecure_localhost" value="1" <?= (bool) ($fionnConfig["allow_insecure_localhost"] ?? true) ? "checked" : "" ?>>
                <span>Allow insecure localhost endpoint</span>
              </label>
              <div class="developer-kanban-modal-actions developer-integrations-wide">
                <button class="btn btn-primary" type="submit">Save FIONN AI settings</button>
                <button class="btn btn-ghost" type="button" data-fnlla-modal-close>Cancel</button>
              </div>
            </form>
          </div>
        </div>

        <div class="modal developer-kanban-modal developer-integrations-modal" id="developer-integration-remote-control-settings" data-fnlla-modal role="dialog" aria-modal="true" aria-labelledby="developer-integration-remote-control-settings-title" hidden>
          <div class="modal-content developer-kanban-modal-content">
            <div class="developer-kanban-modal-head">
              <div>
                <p class="feature-kicker mb-2">Remote control contract</p>
                <h2 class="content-title mb-0" id="developer-integration-remote-control-settings-title">Adapter settings</h2>
              </div>
              <button class="developer-kanban-modal-close" type="button" data-fnlla-modal-close aria-label="Close remote control settings"><span aria-hidden="true">x</span></button>
            </div>
            <form class="form developer-integrations-modal-form" action="<?= h(route("developer.panel.integrations.settings")) ?>" method="post" novalidate>
              <?= csrf_field() ?>
              <?php $renderIntegrationHiddenFields(); ?>
              <label class="developer-workspace-check developer-integrations-wide">
                <input type="hidden" name="developer_control_remote_enabled" value="0">
                <input type="checkbox" name="developer_control_remote_enabled" value="1" <?= (bool) ($remoteConfig["enabled"] ?? false) ? "checked" : "" ?>>
                <span>Remote control contract enabled</span>
              </label>
              <div class="form-group developer-integrations-wide">
                <label class="label" for="developer-control-remote-endpoint">Remote endpoint</label>
                <input class="input" id="developer-control-remote-endpoint" name="developer_control_remote_endpoint" type="url" maxlength="240" value="<?= h((string) ($remoteConfig["endpoint"] ?? "")) ?>" placeholder="https://techayo.co.uk/admin/api/fnlla-control" data-fnlla-modal-initial-focus>
              </div>
              <div class="form-group developer-integrations-wide">
                <label class="label" for="developer-control-remote-project">Project ID</label>
                <input class="input" id="developer-control-remote-project" name="developer_control_remote_project_id" type="text" maxlength="120" value="<?= h((string) ($remoteConfig["project_id"] ?? "")) ?>">
              </div>
              <div class="developer-kanban-modal-actions developer-integrations-wide">
                <button class="btn btn-primary" type="submit">Save remote control settings</button>
                <button class="btn btn-ghost" type="button" data-fnlla-modal-close>Cancel</button>
              </div>
            </form>
          </div>
        </div>

        <?php if (is_array($remoteControl)): ?>
        <section class="developer-dashboard-section" aria-label="Remote control adapter contract">
          <div class="developer-dashboard-section-head">
            <h2 class="developer-dashboard-section-title">Remote control adapter contract</h2>
            <span class="developer-dashboard-refresh"><?= h(DeveloperPanelLabels::contract($remoteControlSchema, "Adapter manifest")) ?></span>
          </div>
          <div class="developer-dashboard-overview-grid developer-remote-control-contract-stack">
            <article class="developer-dashboard-card">
              <p class="feature-kicker">Optional adapter provider</p>
              <h3><?= h((string) ($remoteControl["provider"] ?? "TechAyo Limited")) ?></h3>
              <p class="content-text">The central control plane is expected at <code><?= h((string) ($remoteControl["admin_surface"] ?? "https://techayo.co.uk/admin")) ?></code>.</p>
              <p class="developer-dashboard-status <?= (($remoteControl["status"] ?? "") === "ready") ? "is-active" : "is-neutral" ?>"><?= h(DeveloperPanelLabels::status((string) ($remoteControl["status"] ?? "disabled"))) ?></p>
            </article>
            <article class="developer-dashboard-card">
              <p class="feature-kicker">Runtime contract</p>
              <h3><?= h(DeveloperPanelLabels::contract($remoteRuntimeSchema, "Runtime state response")) ?></h3>
              <p class="content-text">The response can keep the app open, disable public access or suspend it with a service-provider message.</p>
              <div class="developer-dashboard-glance-table">
                <div class="developer-dashboard-glance-row"><strong>Method</strong><span><?= h((string) ($remoteRuntimeContract["method"] ?? "GET")) ?></span></div>
                <div class="developer-dashboard-glance-row"><strong>Endpoint</strong><span><?= h((string) (($remoteRuntimeContract["endpoint"] ?? "") ?: "Not configured")) ?></span></div>
                <div class="developer-dashboard-glance-row"><strong>Project</strong><span><?= trim((string) ($remoteControl["project_id"] ?? "")) !== "" ? "Configured" : "Configured in environment" ?></span></div>
              </div>
            </article>
            <article class="developer-dashboard-card">
              <p class="feature-kicker">Boundary</p>
              <h3>Control only, no private product logic</h3>
              <ul class="developer-dashboard-check-list">
                <?php foreach ((array) ($remoteControl["admin_responsibilities"] ?? []) as $item): ?>
                <li><?= h((string) $item) ?></li>
                <?php endforeach; ?>
              </ul>
            </article>
          </div>
        </section>
        <?php endif; ?>

<?php require __DIR__ . "/panel-footer.php"; ?>
