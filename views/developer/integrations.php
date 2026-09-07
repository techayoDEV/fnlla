<?php

declare(strict_types=1);

$developerPanelTitle = "Integrations";
$developerPanelLead = "Consent-aware adapter contracts for analytics, diagnostics, FIONN AI and remote control.";
$report = is_array($operationsReport ?? null) ? (array) $operationsReport : [];
$integrations = (array) ($report["integrations"] ?? []);
$heatmaps = (array) ($report["heatmaps"] ?? []);
$remoteControl = null;

foreach ($integrations as $integration) {
    if (($integration["name"] ?? "") === "TechAyo Remote Control") {
        $remoteControl = (array) ($integration["contract"] ?? []);
        break;
    }
}

$remoteRuntimeContract = is_array($remoteControl["fnlla_runtime_contract"] ?? null)
    ? (array) $remoteControl["fnlla_runtime_contract"]
    : [];
$integrationConfig = (array) config("integrations", []);
$remoteConfig = (array) config("developer_control.remote", []);
$fionnConfig = (array) config("ai.runtime.fionn", []);
$integrationDescriptions = [
    "GA4" => "Optional Google Analytics measurement for traffic and conversion trends after analytics consent.",
    "Microsoft Clarity" => "Optional session analytics and UX diagnostics after analytics consent; keep disabled until a project needs it.",
    "Sentry" => "Server-side exception reporting for production diagnostics when a DSN and environment are configured.",
    "FIONN AI" => "Persistent Personal Intelligence by TechAyo. Built-in gateway; FIONN developer account and API access required.",
    "Generic API hooks" => "Project-specific webhook adapter for outbound events such as consent updates or operational hooks.",
    "TechAyo Remote Control" => "Central TechAyo control-plane contract for project status and operational toggles, not private product logic.",
];
$integrationBoundaries = [
    "GA4" => "Loads only when enabled, configured and analytics consent has been granted.",
    "Microsoft Clarity" => "Loads only when enabled, configured and analytics consent has been granted.",
    "Sentry" => "Sends server diagnostics only when enabled and the DSN exists.",
    "FIONN AI" => "No remote model call is made unless an approved provider policy enables it.",
    "Generic API hooks" => "No webhook request is made unless the endpoint is configured and the adapter is enabled.",
    "TechAyo Remote Control" => "No central-control request is made unless the remote contract is enabled and configured.",
];
$defaultIntegrationFormValues = [
    "fnlla_integration_ga4_enabled" => (bool) ($integrationConfig["ga4"]["enabled"] ?? false) ? "1" : "0",
    "fnlla_integration_ga4_measurement_id" => (string) ($integrationConfig["ga4"]["measurement_id"] ?? ""),
    "fnlla_integration_clarity_enabled" => (bool) ($integrationConfig["clarity"]["enabled"] ?? false) ? "1" : "0",
    "fnlla_integration_clarity_project_id" => (string) ($integrationConfig["clarity"]["project_id"] ?? ""),
    "fnlla_integration_sentry_enabled" => (bool) ($integrationConfig["sentry"]["enabled"] ?? false) ? "1" : "0",
    "fnlla_integration_sentry_dsn" => (string) ($integrationConfig["sentry"]["dsn"] ?? ""),
    "fnlla_integration_sentry_environment" => (string) ($integrationConfig["sentry"]["environment"] ?? app_environment()),
    "fnlla_integration_api_hooks_enabled" => (bool) ($integrationConfig["api_hooks"]["enabled"] ?? false) ? "1" : "0",
    "fnlla_integration_api_hooks_endpoint" => (string) ($integrationConfig["api_hooks"]["endpoint"] ?? ""),
    "fnlla_integration_heatmaps_enabled" => (bool) ($integrationConfig["heatmaps"]["enabled"] ?? false) ? "1" : "0",
    "fnlla_integration_heatmaps_provider" => (string) ($integrationConfig["heatmaps"]["provider"] ?? ""),
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
$integrationControls = [
    "GA4" => ["enabled_field" => "fnlla_integration_ga4_enabled", "enabled" => (bool) ($integrationConfig["ga4"]["enabled"] ?? false), "modal" => "developer-integration-ga4-settings"],
    "Microsoft Clarity" => ["enabled_field" => "fnlla_integration_clarity_enabled", "enabled" => (bool) ($integrationConfig["clarity"]["enabled"] ?? false), "modal" => "developer-integration-clarity-settings"],
    "Sentry" => ["enabled_field" => "fnlla_integration_sentry_enabled", "enabled" => (bool) ($integrationConfig["sentry"]["enabled"] ?? false), "modal" => "developer-integration-sentry-settings"],
    "FIONN AI" => ["enabled_field" => "ai_fionn_enabled", "enabled" => (bool) ($fionnConfig["enabled"] ?? false), "modal" => "developer-integration-fionn-settings"],
    "Generic API hooks" => ["enabled_field" => "fnlla_integration_api_hooks_enabled", "enabled" => (bool) ($integrationConfig["api_hooks"]["enabled"] ?? false), "modal" => "developer-integration-api-hooks-settings"],
    "TechAyo Remote Control" => ["enabled_field" => "developer_control_remote_enabled", "enabled" => (bool) ($remoteConfig["enabled"] ?? false), "modal" => "developer-integration-remote-control-settings"],
];
$heatmapControl = ["enabled_field" => "fnlla_integration_heatmaps_enabled", "enabled" => (bool) ($integrationConfig["heatmaps"]["enabled"] ?? false), "modal" => "developer-integration-heatmaps-settings"];
require __DIR__ . "/panel-header.php";
?>

        <section class="developer-dashboard-section" aria-label="Integration adapters">
          <div class="developer-dashboard-section-head">
            <h2 class="developer-dashboard-section-title">Integration adapters</h2>
            <span class="developer-dashboard-refresh">Disabled until configured</span>
          </div>
          <div class="developer-integrations-stack">
            <?php foreach ($integrations as $integration): ?>
            <?php $name = (string) ($integration["name"] ?? "Integration"); ?>
            <?php $control = (array) ($integrationControls[$name] ?? []); ?>
            <article class="developer-integrations-row">
              <div>
                <p class="feature-kicker<?= $name === "FIONN AI" ? " fnlla-fionn-name" : "" ?>"><?= h($name) ?></p>
                <h3><?= h((string) ($integration["status"] ?? "disabled")) ?></h3>
                <p class="content-text mb-0"><?= h((string) ($integrationDescriptions[$name] ?? "Optional adapter controlled by project configuration and policy.")) ?></p>
              </div>
              <div class="developer-integrations-policy">
                <span>Gate: <code><?= h((string) ($integration["consent_event"] ?? "manual")) ?></code></span>
                <span><?= h((string) ($integrationBoundaries[$name] ?? "No external request is made until enabled and configured.")) ?></span>
              </div>
              <p class="developer-dashboard-status <?= ($integration["external_calls"] ?? false) ? "is-neutral" : "is-active" ?>"><?= ($integration["external_calls"] ?? false) ? "External calls possible" : "No external calls by default" ?></p>
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
            </article>
            <?php endforeach; ?>
            <article class="developer-integrations-row">
              <div>
                <p class="feature-kicker">Heatmaps</p>
                <h3><?= h((string) ($heatmaps["status"] ?? "disabled")) ?></h3>
                <p class="content-text mb-0"><?= h((string) ($heatmaps["notes"] ?? "Heatmaps remain an opt-in adapter outside core.")) ?></p>
              </div>
              <div class="developer-integrations-policy">
                <span>Gate: <code><?= h((string) ($heatmaps["consent_event"] ?? "fnlla:analytics-consent-granted")) ?></code></span>
                <span>Used for UX friction analysis only after a provider is selected and analytics consent is present.</span>
              </div>
              <p class="developer-dashboard-status is-neutral"><?= h((string) ($heatmaps["mode"] ?? "opt-in adapter")) ?></p>
              <div class="developer-integrations-actions">
                <form action="<?= h(route("developer.panel.integrations.settings")) ?>" method="post">
                  <?= csrf_field() ?>
                  <?php $renderIntegrationHiddenFields([(string) $heatmapControl["enabled_field"] => ($heatmapControl["enabled"] ?? false) ? "0" : "1"]); ?>
                  <button class="btn btn-outline btn-sm" type="submit"><?= ($heatmapControl["enabled"] ?? false) ? "Disable" : "Enable" ?></button>
                </form>
                <button class="btn btn-primary btn-sm" type="button" data-fnlla-modal-open="#<?= h((string) $heatmapControl["modal"]) ?>">Settings</button>
              </div>
            </article>
          </div>
          <div class="developer-integrations-explainer">
            <strong>Why "No external calls by default"?</strong>
            <p>It means the adapter contract exists in FNLLA, but the application does not send network requests to that third party until the project explicitly enables the adapter, provides the required ID/endpoint/DSN and passes the consent or server-policy gate.</p>
          </div>
        </section>

        <section class="developer-ai-settings" aria-labelledby="ai-providers-title">
          <h2 id="ai-providers-title" class="content-title">AI providers</h2>
          <form class="form" action="<?= h(route("developer.panel.integrations.ai")) ?>" method="post" autocomplete="off">
            <?= csrf_field() ?>
            <div class="developer-ai-settings-grid">
              <label>Active provider
                <select class="select" name="ai_runtime_driver">
                  <?php foreach (["local" => "Local reference (no AI model)", "fionn" => "FIONN AI by TechAyo / API account required", "openai" => "OpenAI API / optional", "anthropic" => "Anthropic API / optional"] as $driver => $label): ?>
                  <option value="<?= h($driver) ?>" <?= config("ai.runtime.driver", "local") === $driver ? "selected" : "" ?>><?= h($label) ?></option>
                  <?php endforeach; ?>
                </select>
              </label>
              <label class="developer-workspace-check"><input type="checkbox" name="ai_runtime_enabled" value="1" <?= config("ai.runtime.enabled", true) ? "checked" : "" ?>>Runtime AI enabled</label>
            </div>
            <?php foreach (["openai" => "OpenAI API", "anthropic" => "Anthropic API"] as $driver => $label):
                $provider = (array) config("ai.runtime." . $driver, []);
                $providerStatus = (new \Fnlla\Php\Ai\RuntimeAiProviderRegistry())->status($driver);
                $field = "ai_" . $driver . "_";
            ?>
            <fieldset class="developer-ai-provider">
              <legend><?= h($label) ?></legend>
              <p class="developer-dashboard-status is-neutral"><?= ($providerStatus["provider_ready"] ?? false) ? "Configured / not live-tested" : (($providerStatus["enabled"] ?? false) ? "Configuration required" : "Disabled") ?></p>
              <div class="developer-ai-settings-grid">
                <label class="developer-workspace-check"><input type="checkbox" name="<?= h($field) ?>enabled" value="1" <?= ($provider["enabled"] ?? false) ? "checked" : "" ?>>External requests enabled</label>
                <label>Model ID<input class="input" name="<?= h($field) ?>model" maxlength="160" value="<?= h((string) ($provider["model"] ?? "")) ?>" spellcheck="false" autocomplete="off"></label>
                <label>API key<input class="input" type="password" name="<?= h($field) ?>api_key" maxlength="512" value="" autocomplete="new-password" placeholder="<?= ($providerStatus["token_configured"] ?? false) ? "Configured; leave blank to keep" : "Not configured" ?>"></label>
                <label class="developer-workspace-check"><input type="checkbox" name="<?= h($field) ?>remove_key" value="1">Remove stored API key</label>
                <label>Maximum output tokens<input class="input" type="number" name="<?= h($field) ?>max_output_tokens" min="64" max="8192" required value="<?= (int) ($provider["max_output_tokens"] ?? 1024) ?>"></label>
                <label>Timeout (seconds)<input class="input" type="number" name="<?= h($field) ?>timeout_seconds" min="1" max="60" required value="<?= (int) ($provider["timeout_seconds"] ?? 30) ?>"></label>
              </div>
            </fieldset>
            <?php endforeach; ?>
            <button class="btn btn-primary" type="submit">Save AI settings</button>
          </form>
        </section>

        <div class="modal developer-kanban-modal developer-integrations-modal" id="developer-integration-ga4-settings" data-fnlla-modal role="dialog" aria-modal="true" aria-labelledby="developer-integration-ga4-settings-title" hidden>
          <div class="modal-content developer-kanban-modal-content">
            <div class="developer-kanban-modal-head">
              <div>
                <p class="feature-kicker mb-2">GA4</p>
                <h2 class="content-title mb-0" id="developer-integration-ga4-settings-title">Adapter settings</h2>
              </div>
              <button class="developer-kanban-modal-close" type="button" data-fnlla-modal-close aria-label="Close GA4 settings"><span aria-hidden="true">x</span></button>
            </div>
            <form class="form developer-integrations-modal-form" action="<?= h(route("developer.panel.integrations.settings")) ?>" method="post" novalidate>
              <?= csrf_field() ?>
              <?php $renderIntegrationHiddenFields(); ?>
              <label class="developer-workspace-check">
                <input type="hidden" name="fnlla_integration_ga4_enabled" value="0">
                <input type="checkbox" name="fnlla_integration_ga4_enabled" value="1" <?= (bool) ($integrationConfig["ga4"]["enabled"] ?? false) ? "checked" : "" ?>>
                <span>GA4 enabled</span>
              </label>
              <div class="form-group developer-integrations-wide">
                <label class="label" for="fnlla-integration-ga4-id">GA4 measurement ID</label>
                <input class="input" id="fnlla-integration-ga4-id" name="fnlla_integration_ga4_measurement_id" type="text" maxlength="80" value="<?= h((string) ($integrationConfig["ga4"]["measurement_id"] ?? "")) ?>" placeholder="G-XXXXXXXXXX" data-fnlla-modal-initial-focus>
              </div>
              <div class="developer-kanban-modal-actions developer-integrations-wide">
                <button class="btn btn-primary" type="submit">Save GA4 settings</button>
                <button class="btn btn-ghost" type="button" data-fnlla-modal-close>Cancel</button>
              </div>
            </form>
          </div>
        </div>

        <div class="modal developer-kanban-modal developer-integrations-modal" id="developer-integration-clarity-settings" data-fnlla-modal role="dialog" aria-modal="true" aria-labelledby="developer-integration-clarity-settings-title" hidden>
          <div class="modal-content developer-kanban-modal-content">
            <div class="developer-kanban-modal-head">
              <div>
                <p class="feature-kicker mb-2">Microsoft Clarity</p>
                <h2 class="content-title mb-0" id="developer-integration-clarity-settings-title">Adapter settings</h2>
              </div>
              <button class="developer-kanban-modal-close" type="button" data-fnlla-modal-close aria-label="Close Clarity settings"><span aria-hidden="true">x</span></button>
            </div>
            <form class="form developer-integrations-modal-form" action="<?= h(route("developer.panel.integrations.settings")) ?>" method="post" novalidate>
              <?= csrf_field() ?>
              <?php $renderIntegrationHiddenFields(); ?>
              <label class="developer-workspace-check">
                <input type="hidden" name="fnlla_integration_clarity_enabled" value="0">
                <input type="checkbox" name="fnlla_integration_clarity_enabled" value="1" <?= (bool) ($integrationConfig["clarity"]["enabled"] ?? false) ? "checked" : "" ?>>
                <span>Clarity enabled</span>
              </label>
              <div class="form-group developer-integrations-wide">
                <label class="label" for="fnlla-integration-clarity-id">Clarity project ID</label>
                <input class="input" id="fnlla-integration-clarity-id" name="fnlla_integration_clarity_project_id" type="text" maxlength="120" value="<?= h((string) ($integrationConfig["clarity"]["project_id"] ?? "")) ?>" data-fnlla-modal-initial-focus>
              </div>
              <div class="developer-kanban-modal-actions developer-integrations-wide">
                <button class="btn btn-primary" type="submit">Save Clarity settings</button>
                <button class="btn btn-ghost" type="button" data-fnlla-modal-close>Cancel</button>
              </div>
            </form>
          </div>
        </div>

        <div class="modal developer-kanban-modal developer-integrations-modal" id="developer-integration-heatmaps-settings" data-fnlla-modal role="dialog" aria-modal="true" aria-labelledby="developer-integration-heatmaps-settings-title" hidden>
          <div class="modal-content developer-kanban-modal-content">
            <div class="developer-kanban-modal-head">
              <div>
                <p class="feature-kicker mb-2">Heatmaps</p>
                <h2 class="content-title mb-0" id="developer-integration-heatmaps-settings-title">Adapter settings</h2>
              </div>
              <button class="developer-kanban-modal-close" type="button" data-fnlla-modal-close aria-label="Close heatmap settings"><span aria-hidden="true">x</span></button>
            </div>
            <form class="form developer-integrations-modal-form" action="<?= h(route("developer.panel.integrations.settings")) ?>" method="post" novalidate>
              <?= csrf_field() ?>
              <?php $renderIntegrationHiddenFields(); ?>
              <label class="developer-workspace-check">
                <input type="hidden" name="fnlla_integration_heatmaps_enabled" value="0">
                <input type="checkbox" name="fnlla_integration_heatmaps_enabled" value="1" <?= (bool) ($integrationConfig["heatmaps"]["enabled"] ?? false) ? "checked" : "" ?>>
                <span>Heatmaps enabled after consent</span>
              </label>
              <div class="form-group developer-integrations-wide">
                <label class="label" for="fnlla-integration-heatmaps-provider">Heatmap provider</label>
                <input class="input" id="fnlla-integration-heatmaps-provider" name="fnlla_integration_heatmaps_provider" type="text" maxlength="120" value="<?= h((string) ($integrationConfig["heatmaps"]["provider"] ?? "")) ?>" placeholder="clarity, custom, internal" data-fnlla-modal-initial-focus>
              </div>
              <div class="developer-kanban-modal-actions developer-integrations-wide">
                <button class="btn btn-primary" type="submit">Save heatmap settings</button>
                <button class="btn btn-ghost" type="button" data-fnlla-modal-close>Cancel</button>
              </div>
            </form>
          </div>
        </div>

        <div class="modal developer-kanban-modal developer-integrations-modal" id="developer-integration-sentry-settings" data-fnlla-modal role="dialog" aria-modal="true" aria-labelledby="developer-integration-sentry-settings-title" hidden>
          <div class="modal-content developer-kanban-modal-content">
            <div class="developer-kanban-modal-head">
              <div>
                <p class="feature-kicker mb-2">Sentry</p>
                <h2 class="content-title mb-0" id="developer-integration-sentry-settings-title">Adapter settings</h2>
              </div>
              <button class="developer-kanban-modal-close" type="button" data-fnlla-modal-close aria-label="Close Sentry settings"><span aria-hidden="true">x</span></button>
            </div>
            <form class="form developer-integrations-modal-form" action="<?= h(route("developer.panel.integrations.settings")) ?>" method="post" novalidate>
              <?= csrf_field() ?>
              <?php $renderIntegrationHiddenFields(); ?>
              <label class="developer-workspace-check">
                <input type="hidden" name="fnlla_integration_sentry_enabled" value="0">
                <input type="checkbox" name="fnlla_integration_sentry_enabled" value="1" <?= (bool) ($integrationConfig["sentry"]["enabled"] ?? false) ? "checked" : "" ?>>
                <span>Sentry enabled</span>
              </label>
              <div class="form-group developer-integrations-wide">
                <label class="label" for="fnlla-integration-sentry-dsn">Sentry DSN</label>
                <input class="input" id="fnlla-integration-sentry-dsn" name="fnlla_integration_sentry_dsn" type="text" maxlength="240" value="<?= h((string) ($integrationConfig["sentry"]["dsn"] ?? "")) ?>" data-fnlla-modal-initial-focus>
              </div>
              <div class="form-group developer-integrations-wide">
                <label class="label" for="fnlla-integration-sentry-env">Sentry environment</label>
                <input class="input" id="fnlla-integration-sentry-env" name="fnlla_integration_sentry_environment" type="text" maxlength="80" value="<?= h((string) ($integrationConfig["sentry"]["environment"] ?? app_environment())) ?>">
              </div>
              <div class="developer-kanban-modal-actions developer-integrations-wide">
                <button class="btn btn-primary" type="submit">Save Sentry settings</button>
                <button class="btn btn-ghost" type="button" data-fnlla-modal-close>Cancel</button>
              </div>
            </form>
          </div>
        </div>

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
                <p class="feature-kicker mb-2">TechAyo central control</p>
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
        <section class="developer-dashboard-section" aria-label="TechAyo remote control plugin contract">
          <div class="developer-dashboard-section-head">
            <h2 class="developer-dashboard-section-title">TechAyo Remote Control plugin</h2>
            <span class="developer-dashboard-refresh"><?= h((string) ($remoteControl["schema"] ?? "fnlla.remote_control_plugin.v1")) ?></span>
          </div>
          <div class="developer-dashboard-overview-grid">
            <article class="developer-dashboard-card">
              <p class="feature-kicker">Admin surface</p>
              <h3><?= h((string) ($remoteControl["provider"] ?? "TechAyo Limited")) ?></h3>
              <p class="content-text">The central control plane is expected at <code><?= h((string) ($remoteControl["admin_surface"] ?? "https://techayo.co.uk/admin")) ?></code>.</p>
              <p class="developer-dashboard-status <?= (($remoteControl["status"] ?? "") === "ready") ? "is-active" : "is-neutral" ?>"><?= h((string) ($remoteControl["status"] ?? "disabled")) ?></p>
            </article>
            <article class="developer-dashboard-card">
              <p class="feature-kicker">Runtime contract</p>
              <h3><?= h((string) (($remoteRuntimeContract["response_schema"] ?? "") ?: "fnlla.techayo_remote_control_state.v1")) ?></h3>
              <div class="developer-dashboard-glance-table">
                <div class="developer-dashboard-glance-row"><strong>Method</strong><span><?= h((string) ($remoteRuntimeContract["method"] ?? "GET")) ?></span></div>
                <div class="developer-dashboard-glance-row"><strong>Endpoint</strong><span><?= h((string) (($remoteRuntimeContract["endpoint"] ?? "") ?: "Not configured")) ?></span></div>
                <div class="developer-dashboard-glance-row"><strong>Project</strong><span><?= h((string) (($remoteControl["project_id"] ?? "") ?: "Configured by env")) ?></span></div>
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
