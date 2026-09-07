<?php

declare(strict_types=1);

namespace Fnlla\Php\Controllers;

use Fnlla\Php\Ai\AiProviderSettings;
use Fnlla\Php\Http\Request;
use Fnlla\Php\Http\Response;
use Fnlla\Php\Maintenance\DeveloperAccessManager;
use Fnlla\Php\Support\EnvironmentFileManager;
use RuntimeException;

final class DeveloperAiSettingsController extends DeveloperPanelController
{
    public function update(Request $request, DeveloperAccessManager $access, EnvironmentFileManager $environment, AiProviderSettings $settings): Response
    {
        if (!$this->ensureDeveloperCapability($access, "panel.settings.write")) {
            return $this->redirect(route("developer.panel.integrations"));
        }
        try {
            $values = $settings->values($request->all());
        } catch (RuntimeException $exception) {
            return $this->result("warning", $exception->getMessage());
        }
        try {
            $environment->write($values);
            $environment->apply($values);
            $settings->apply($values);
        } catch (RuntimeException) {
            return $this->result("danger", "AI settings could not be saved. Check environment file permissions.");
        }
        // Neither credentials nor prompts belong in the activity log or session old-input flash.
        developer_activity()->record("developer_ai_settings", "AI provider settings updated", "Provider configuration changed; no test request was sent.", $access->currentDeveloper());
        return $this->result("success", "AI provider settings saved. No test request was sent.");
    }

    private function result(string $variant, string $text): Response
    {
        flash_set("status", ["variant" => $variant, "title" => "AI providers", "text" => $text, "toast" => false]);
        regenerate_csrf_token();
        return $this->redirect(route("developer.panel.integrations"));
    }
}
